# Descripciones de producto en el Catálogo Central

Fecha: 2026-07-15
Código: `app/Services/ProductDescriptionService.php`, `app/Models/ProductsCentralModel.php`
(`getDescriptionStats()`, `getNextDescriptionCandidates()`, `saveDescription()`),
`app/Controllers/ProductcentralController.php` (`descriptionsStatus`, `descriptionsProcess`).
Migración: `MIGRATION-2026-07-15-PRODUCTS-CENTRAL-DESCRIPTION.sql`.

## Por qué existe

`ml_products_cache` (y por lo tanto `products_central`, su espejo limpio — ver
`PRODUCTS-CENTRAL-LIMPIEZA-CASOS-CONFLICTIVOS.md`) nunca trajo la descripción del producto: el sync
usa el endpoint bulk `/items?ids=...` de MercadoLibre, que no incluye descripción. Traerla requiere
una llamada aparte por ítem (`GET /items/{id}/description`), sin versión bulk.

Pedirla para las 154.586 filas de `ml_products_cache` sería ~10+ horas de presupuesto de API (rate
limit propio: 300/min, 15.000/hora, compartido con el sync normal). En cambio, el Catálogo Central
ya deduplica por título y solo **muestra** 31.351 filas "ganadoras" (ver la sección de variantes en
el documento de limpieza) — así que solo tiene sentido pedir la descripción para esas ~31k, no para
el resto. Eso reduce el costo a un par de horas.

## Qué se agregó

- Columna `products_central.description` (TEXT, NULL por defecto).
  - `NULL` = todavía no se intentó.
  - `''` (string vacío) = se intentó y ML no tiene descripción para ese ítem (o devolvió 404).
  - texto no vacío = descripción real.
- `ProductsCentralModel::getDescriptionStats()`: cuenta `total` (filas ganadoras), `done`,
  `pending` (sin descripción, tienda activa) y `blocked_inactive` (sin descripción, tienda
  desconectada — no se les puede pedir hasta reactivar la conexión).
- `ProductsCentralModel::getNextDescriptionCandidates($limit)`: siguiente tanda de filas ganadoras
  pendientes con tienda activa, usando el mismo criterio de desempate que `getProducts()`
  (`ROW_NUMBER() PARTITION BY title ORDER BY (sku IS NULL), id`), para pedirle la descripción
  exactamente a la fila que el Catálogo Central muestra.
- `ProductDescriptionService::processNextChunk($limit = 20)`: procesa una tanda, usando
  `MercadoLibreAuthService::getTokenForStore()` (refresca el token si está por expirar, igual que
  `MlSyncService`) y `MercadoLibreApiService::callWithRetry()` (mismo rate limiter que el sync
  normal). Si una tienda devuelve 401/403 en la tanda, deja de pedirle más a esa tienda en esa
  misma tanda (no marca las filas, se reintentan en la próxima corrida) para no desperdiciar
  llamadas contra un token roto.
- Endpoints admin (`requireAdmin()`, protegidos con CSRF):
  - `GET /productcentral/descriptionsStatus` → estado actual (JSON).
  - `POST /productcentral/descriptionsProcess` → procesa una tanda de 20 y devuelve el resultado.
- Botón "Traer descripciones" en `/productcentral` (solo visible para admins): al apretarlo, el
  frontend llama `descriptionsProcess` en loop hasta que `pending` llega a 0 o hay un error. Es
  reanudable: si se cierra la pestaña o hay un error de red a mitad de camino, el progreso ya
  guardado no se pierde — al volver a apretar el botón continúa desde donde quedó (la cola es la
  propia tabla, no un batch en memoria).

## Por qué no se pierde al re-sincronizar

`mirrorFromMlCache()` borra y reinserta todas las filas de una conexión en cada sync (ver
`PRODUCTS-CENTRAL-LIMPIEZA-CASOS-CONFLICTIVOS.md`), y `description` no viene de `ml_products_cache`
— se perdería en cada sync si no se hiciera nada. Por eso, antes del `DELETE`, se guardan aparte las
descripciones ya traídas de esa conexión (keyed por `ml_item_id`, no por `id` — el `id` autoincrement
cambia en cada reinsert) y se reaplican después del `INSERT`. Probado localmente: guardé una
descripción de prueba, corrí `mirrorFromMlCache()` para esa conexión, y la descripción seguía ahí
tras el reinsert (buscando por `ml_item_id`, ya que el `id` había cambiado).

## Limitación conocida

Si la fila "ganadora" de un título pertenece a una tienda desconectada (`is_active = 0`), no hay
token válido para pedirle la descripción — queda contabilizada en `blocked_inactive` hasta que esa
tienda se reactive. No hay forma de evitar esto sin cambiar cuál fila se considera "ganadora"
(afectaría también qué tienda/precio/stock se muestra en el Catálogo Central, no es un problema
específico de descripciones).

## Probado localmente (2026-07-15)

- `getDescriptionStats()` contra las 154.586 filas reales: `total=31.351`, y con las 4 conexiones
  mirror inactivas (`is_active=0`), `blocked_inactive=31.351`, `pending=0` — correcto.
- Activando temporalmente una conexión (`id=22`, 9.436 productos ganadores le pertenecen):
  `pending` pasó a 9.436 y `getNextDescriptionCandidates()` devolvió filas de esa conexión
  correctamente. Se revirtió el flag `is_active` al terminar la prueba.
- `saveDescription()` + `mirrorFromMlCache()`: confirmado que la descripción sobrevive al
  delete+reinsert (test descrito arriba).
- **No se probó la llamada real a `GET /items/{id}/description`** — las conexiones locales son un
  mirror de datos de producción con tokens reales; no correspondía hacer llamadas en vivo a la
  cuenta de un vendedor real desde una prueba de desarrollo. Falta probar ese tramo (el llamado a la
  API en sí, el parseo de `plain_text`/`text`, y el manejo de 401/403/404) contra una tienda de
  prueba real o en el próximo uso en producción.
