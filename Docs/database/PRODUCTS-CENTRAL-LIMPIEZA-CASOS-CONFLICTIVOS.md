# Limpieza de `products_central` — qué cambió y qué revisar

Fecha: 2026-07-15
Código: `app/Models/ProductsCentralModel.php` (`mirrorFromMlCache()`, `cleanCacheRow()`,
`normalizeTitle()`, `normalizeSku()`, `insertCleanRows()`)

**Probado localmente contra el mirror de producción** (las 4 conexiones `[MIRROR PRODUCCION]`,
154.586 filas en `ml_products_cache`): corrí `mirrorFromMlCache()` real sobre las 4 tiendas. Los
números de las secciones siguientes son de esa corrida real, no estimados.

## Qué cambió

Antes, `mirrorFromMlCache()` era un `INSERT ... SELECT` directo: `products_central` era un
espejo 1:1 exacto de `ml_products_cache`, sin tocar los datos. Ahora el mirror lee las filas
de la conexión, las limpia en PHP fila por fila, y recién ahí inserta. Sigue siendo
"borra + reinserta en transacción" por conexión — no hay diffing.

**No toqué `ml_products_cache`** (sigue siendo el dato crudo tal cual llega de la API de ML) ni
`getProducts()` / la deduplicación por título del listado del Catálogo Central — ver la sección
"Lo que NO se tocó" más abajo, es el punto más importante de este documento.

## Reglas de limpieza aplicadas

| Campo | Regla |
|---|---|
| `title` | Trim, colapsa espacios/tabs/saltos de línea repetidos, decodifica entidades HTML (`&amp;` → `&`), quita tags. **No** cambia mayúsculas/minúsculas ni acentos. |
| `sku` | Trim, colapsa espacios, pasa a MAYÚSCULAS, y placeholders (`-`, `--`, `n/a`, `null`, `none`, `s/n`, `0`, etc.) se tratan como ausencia de SKU (`NULL`). |
| `price`, `original_price` | Si viene negativo (dato corrupto), se lleva a `0` / `NULL` — no se descarta el producto por esto. |
| `available_quantity`, `sold_quantity` | Igual: negativo → `0`. |
| Fila completa | Se descarta **solo** si, tras limpiar, queda sin título Y sin SKU (no hay con qué mostrarla ni buscarla). Se cuenta y se loguea (`error_log`, prefijo `[PRODUCTS_CENTRAL]`), no se detiene el sync. |
| `parent_ml_item_id`, `condition`, `listing_type_id`, `status`, `category_id`, `category_name`, `thumbnail`, `permalink` | Solo trim. No se tocan valores ni se descartan por su contenido (un `status = 'closed'` sigue siendo un dato válido, por ejemplo). |

### Resultado real (mirror de producción, 154.586 filas, 4 tiendas)

- **0 filas descartadas** en las 4 tiendas — la API de ML siempre trae `title`, así que el caso
  "sin título y sin SKU" resultó ser prácticamente inexistente en datos reales. `central_count`
  quedó igual a `cache_count` en las 4 conexiones.
- **2.073 títulos** tenían espacios dobles/triples internos (ej. `"Cono Training 7  Naranjo"` →
  `"Cono Training 7 Naranjo"`) — se limpiaron.
- **0 placeholders de SKU** (`-`, `N/A`, etc.) encontrados en los datos reales — esa regla no tuvo
  efecto en esta corrida, pero se deja porque es barata y protege contra el caso.
- **100.286 filas (64.9%) ya tenían `sku` NULL/vacío en el origen**, antes de cualquier limpieza —
  esto no lo generé yo, es el estado real de los datos: 2 de cada 3 productos en ML no tienen SKU
  cargado. Ver el punto siguiente sobre por qué esto es más grave de lo que parece.

## Lo que NO se tocó (léelo con atención)

**El problema real de las variantes (color/talla) ya existía antes de este cambio y sigue sin
resolverse — está en la capa de lectura, no en el mirror.**

En `MlProductsCacheModel::upsertFromMlItem()`, cuando un ítem de ML tiene variaciones
(`$item->variations`), cada variación se guarda como su propia fila en `ml_products_cache`
(`parent_ml_item_id` = id del padre, `sku` propio de la variación), **pero hereda el `title` del
padre sin modificarlo** (`$vData = array_merge($data, [...])` no toca `title`). Es decir: un padre
y todas sus variantes de color/talla comparten el **mismo título exacto**.

`ProductsCentralModel::getProducts()` deduplica el listado del Catálogo Central agrupando por
título exacto (`ROW_NUMBER() OVER (PARTITION BY t.title ...)`) y muestra **una sola fila por
título**, priorizando la que tenga SKU no vacío. Con productos con variantes, esto significa que
si tienes "Zapatilla X" en talla 38 (SKU A, stock 5), talla 40 (SKU B, stock 0) y talla 42 (SKU C,
stock 3), el Catálogo Central hoy solo te muestra **una** de las tres — probablemente la talla 38
por orden de `id`, ocultando que hay stock también en la talla 42.

**Esto no es hipotético — lo medí contra el mirror de producción y es mucho más grave de lo que
esperaba:**

- `products_central`: **154.586 filas**, pero solo **31.351 títulos distintos**.
- El dedup de `getProducts()` **oculta 123.235 filas (79.7% del catálogo)**. De cada 5 productos
  reales publicados, el Catálogo Central hoy solo muestra 1.
- Ejemplos reales (título → filas reales / lo que se muestra):
  - `"Tripack Boxer Hombre Jockey Y-77 Cotton Spandex"` → 303 filas reales (3 tiendas), stock real
    combinado 4.590 unidades → el Catálogo Central muestra **1 fila**.
  - `"Tripack Cuadro Maxi Mujer Lady Genny C-941 Cotton & Encaje"` → 303 filas, stock real 3.240 →
    **1 fila** mostrada.
  - Varios títulos con 303 filas reales muestran `stock_total_real = 0` en el agregado — es decir,
    la fila que el dedup elige para mostrar podría ser una con stock 0 mientras otra variante del
    mismo grupo sí tiene stock, y no hay forma de saberlo desde el listado actual.
- Esto pasa tanto **dentro de una tienda** (variantes de color/talla, como se explicó arriba) como
  **entre tiendas** (el mismo producto publicado en las 3 tiendas del negocio, cada una con su
  propio `ml_item_id`, comparte título exacto).

Esta cifra (79.7%) es la razón por la que este punto está primero en el documento: es el hallazgo
más importante de esta revisión, aunque no lo haya causado ni resuelto este cambio.

**Por qué no lo arreglé en este cambio**: arreglarlo bien requiere decidir la unidad de
deduplicación correcta (¿por título+SKU-padre? ¿agrupar variantes bajo el padre y mostrar el
conjunto?), lo que cambia el contrato de `getProducts()` (paginación, `store_count`, la tabla
materializada `products_central_title_stats`) — es un cambio de UI/UX, no de limpieza de datos.
Se decidió explícitamente limitar este trabajo al mirror (`ml_products_cache` → `products_central`)
sin tocar la deduplicación de lectura.

**Qué hice en su lugar**: en el mirror, cada fila (padre o variante) sigue insertándose en
`products_central` de forma independiente, una por `ml_item_id`, exactamente igual que antes.
No se pierde ningún dato en el mirror — el problema es 100% de cómo `getProducts()` agrupa lo
que ya está guardado.

**Recomendación para cuando se quiera resolver** (Fase 2 o antes si molesta en el día a día):
- Cambiar la partición de `ROW_NUMBER()` de `t.title` a algo que distinga variantes, por ejemplo
  `PARTITION BY t.title, COALESCE(t.parent_ml_item_id, t.ml_item_id)` en vez de solo `t.title`, y
  decidir si el Catálogo Central debe mostrar variantes como filas separadas (con indicador
  "3 variantes") o expandibles bajo el padre.
- Revisar `products_central_title_stats` (conteo de tiendas por título) — si se cambia la
  agrupación, este conteo también hay que recalcularlo con la nueva clave.

## Otros casos límite a revisar

1. **SKU pasado a mayúsculas**: si dos SKUs que antes se veían "distintos" solo por
   mayúsculas/minúsculas (`abc123` vs `ABC123`) en realidad correspondían a productos distintos
   mal cargados, ahora quedan con el mismo valor de texto en `products_central`. No se fusionan
   filas (cada `ml_item_id` sigue siendo su propia fila), pero si buscas por SKU en el Catálogo
   Central verás ambos con la misma etiqueta. Confirmado: `products_central` **no** se usa en
   los flujos de actualización de precio/stock hacia ML ni en auditorías (`PriceService`,
   `AuditService` trabajan directo sobre `ml_products_cache`), así que este cambio de mayúsculas
   es cosmético y aislado al Catálogo Central.

2. **Placeholders de SKU tratados como NULL**: la lista actual es
   `-`, `--`, `n/a`, `na`, `null`, `none`, `s/n`, `sn`, `0` (case-insensitive). Si en tus tiendas
   hay un SKU real que coincida con alguno de estos valores (poco probable, pero posible en
   catálogos viejos), se guardará como `NULL` en `products_central` en vez de con ese texto.
   Revisa el log tras el primer sync post-deploy si te extraña algún producto sin SKU que antes
   sí lo tenía.

3. **Filas descartadas por completo**: solo pasa si un ítem no tiene título NI SKU utilizable
   tras la limpieza — debería ser rarísimo (la API de ML casi siempre trae `title`). Si
   `centralVerify()` (`/ml` → endpoint admin `centralVerify`, o revisar `error_log` con prefijo
   `[PRODUCTS_CENTRAL]`) muestra un `discarded` alto para alguna tienda, es señal de un problema
   de datos real en esa conexión, no de un bug de la limpieza — vale la pena mirar esos ítems en
   `ml_products_cache` directamente.

4. **Precio/stock negativo llevado a 0 en vez de descartar**: si ves un producto con precio 0 que
   antes tenía un valor "raro" (negativo), no es que desapareció — se corrigió el valor pero se
   conservó el producto. Si prefieres que estos casos se descarten en vez de corregirse, es un
   cambio de una línea en `cleanCacheRow()`.

5. **`verifyAgainstCache()` / `centralVerify()`**: ya no exige `cache_count === central_count`.
   Ahora `match = true` cuando `central_count <= cache_count`; si alguna vez ves
   `central_count > cache_count`, **eso sí es un bug real** (el mirror no puede inventar filas) y
   hay que investigarlo de inmediato.

## Ya se probó localmente (2026-07-15)

Antes de dejar este documento, corrí `mirrorFromMlCache()` real —el mismo código que usa
`MlSyncService`— contra las 4 conexiones `[MIRROR PRODUCCION]` de la base local (154.586 filas en
`ml_products_cache`). Resultado: `ok=true` en las 4, `central_count === cache_count` en las 4 (0
descartes), `getProducts()` respondió sin errores después del cambio. Los números de este
documento (2.073 títulos limpiados, 64.9% sin SKU, 79.7% oculto por el dedup de variantes) salen
de esa corrida, no son estimados.

## Cómo verificar en tu entorno

1. Correr un sync completo de alguna tienda (o esperar al próximo automático).
2. Revisar `error_log` filtrando por `[PRODUCTS_CENTRAL]` — si hay descartes, va a aparecer una
   línea por conexión con el conteo.
3. Pegarle al endpoint admin `centralVerify` (`MlController::centralVerify`) y confirmar que todas
   las conexiones muestran `match: true` y que `discarded` es 0 o un número que tiene sentido para
   esa tienda.
4. Entrar al Catálogo Central y comparar visualmente algunos productos con variantes conocidas
   contra lo que muestra ahora — para confirmar que el problema de variantes (sección anterior)
   sigue siendo el mismo de antes (no lo empeoré, pero tampoco lo arreglé).
