# Segmentación de SKUs: packs, tripacks y SKU base

Agregado 2026-07-20. Migración: `MIGRATION-2026-07-20-PRODUCTS-CENTRAL-SKU-PACK.sql`.
Cálculo: `ProductsCentralModel::assignSkuPackFields()`, llamado desde `mirrorFromMlCache()`.

## Por qué

Varios vendedores arman SKUs como:

```
A-201                       (SKU individual/base)
A-201-ROJ-S                 (variante individual: color + talla)
PACK-A-201-ROJ-AZU-S        (pack de 2 colores)
TRIPACK-A-201-ROJ-AZU-VER-S (pack de 3 colores)
```

Se agregaron dos columnas derivadas para poder agrupar un pack con su SKU base sin tocar el dato
crudo ni intentar "adivinar" campos que el patrón no garantiza (color/talla estructurados).

## Columnas

- `sku_pack_type`: `NULL` (SKU individual), `'PACK'` (2 unidades) o `'TRIPACK'` (3 unidades), detectado
  solo por el prefijo del SKU (`PACK-` / `TRIPACK-`).
- `sku_base`: el SKU base vinculado, **solo cuando matchea un SKU real de la misma tienda** tras quitar
  el prefijo (probando de menos a más segmentos separados por `-`). Si no hay match, queda `NULL` — no
  se inventa el dato.

Ambas se recalculan en cada mirror; no son editables a mano.

## Por qué el matching es por tienda, no global

Un pack lo arma un vendedor a partir de su propio catálogo. `assignSkuPackFields()` construye el set de
SKUs candidatos solo con las filas del mismo `mirrorFromMlCache($connectionId)` (misma conexión/tienda),
nunca cruzando SKUs entre tiendas distintas.

## Qué NO se hizo (a propósito)

No se intentó extraer color/talla como campos estructurados (`sku_color`, `sku_size`, etc.). Antes de
implementar se corrió un análisis exploratorio contra los ~154k SKUs reales de producción y se encontró
ruido suficiente para que ese parseo no fuera confiable a nivel de catálogo completo:

- Solo el 55.1% de los SKUs termina en una talla reconocible (S/M/L/XL/XXL/número de 2-3 dígitos).
- Hay SKUs que en realidad son texto descriptivo, no códigos (`ALPARGATAS UNISEX EXCELENTE CALIDAD`).
- Hay SKUs que son códigos internos de MercadoLibre, no del vendedor (`MLC2822903524`).

Por eso el alcance quedó limitado a lo que sí se pudo validar con alta confianza: el prefijo de pack (100%
determinístico) y el vínculo a SKU base (validado empíricamente, ver resultados abajo).

## Resultados reales (corrida completa, 154,586 filas / 4 conexiones activas, 2026-07-20)

| `sku_pack_type` | Filas |
|---|---|
| `NULL` (individual) | 137,253 |
| `PACK` | 9,906 |
| `TRIPACK` | 7,427 |

Resolución de `sku_base` dentro de cada grupo de pack:

| Tipo | Con `sku_base` resuelto | % |
|---|---|---|
| `TRIPACK` | 6,655 / 7,427 | 89.6% |
| `PACK` | 6,206 / 9,906 | 62.6% |

Ejemplos con `sku_base` resuelto:

```
PACK-A-107-BL-BL-T/U    => sku_base = A-107
PACK-A-107-BL-NU-T/U    => sku_base = A-107
PACK-2.20.14-AZM-AZ     => sku_base = 2.20.14-AZM
```

Ejemplos sin `sku_base` (el pack existe pero ninguna combinación de prefijo matchea un SKU real de esa
tienda — típicamente porque el vendedor nunca publicó esa variante individual, solo el pack):

```
PACK-A-209-COL-COL-T/U
TRIPACK-MP-443-BL-BL-BL-T/U
PACK-MA-575-MEN-MEN-XXL
```

Este `NULL` es correcto, no un bug: refleja que no existe (en el catálogo de esa tienda) un SKU
individual al que vincular el pack, no una falla del parser.

## Probado localmente

Se corrió `mirrorFromMlCache()` para las 4 conexiones reales (`ml_connection_id` 22-25, datos espejo de
producción) y se verificaron los conteos y ejemplos de arriba directamente contra `products_central`.
