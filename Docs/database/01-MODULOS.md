# Módulos de base de datos

**Base de datos**: `gestion` (MariaDB 10.4, InnoDB, charset `utf8mb4`)

---

## Mapa de módulos

```
┌─────────────────────────────────────────────────────────────────┐
│                            SISTEMA                              │
├──────────┬──────────┬──────────┬──────────┬─────────┬───────────┤
│   AUTH   │    ML    │ PRECIOS  │ CATÁLOGO │SISTEMA  │ CENCOSUD  │
│          │          │  AUDIT   │          │         │           │
├──────────┼──────────┼──────────┼──────────┼─────────┼───────────┤
│  users   │ml_conn.. │ auditor. │products. │app_set. │cenco_conn.│
│  recovery│ml_prod.. │price_ch. │product_m.│notific. │cenco_prod.│
│  passwd. │ml_sync.. │price_ba. │categ._h. │activity │cenco_sync.│
│  rate_li.│          │          │          │         │cenco_batch│
│  login_a.│          │          │          │         │cenco_logs │
│          │          │          │          │         │cenco_ord..│
└──────────┴──────────┴──────────┴──────────┴─────────┴───────────┘
```

---

## 1. Autenticación y seguridad (Auth)

### Tablas

| Tabla | Propósito | Columnas clave |
|---|---|---|
| `users` | Cuentas de usuario | `id`, `username`, `password`, `role`, `email`, `status`, `twofa_*`, `current_store_id` |
| `recovery_codes` | Códigos de respaldo 2FA | `user_id` → users CASCADE |
| `password_resets` | Tokens de restablecimiento | `user_id` → users CASCADE |
| `rate_limits` | Rate limiting por usuario/IP | `user_id` → users CASCADE |
| `login_attempts` | Historial de intentos de login | `username`, `ip_address` |

### Relaciones

```
users (1) ──CASCADE──→ (N) recovery_codes
users (1) ──CASCADE──→ (N) password_resets
users (1) ──CASCADE──→ (N) rate_limits
users (1) ──SET NULL──→ (N) activity_logs
users (1) ──SET NULL──→ (N) auditorias
users (1) ──SET NULL──→ (N) price_change_logs
users (1) ──SET NULL──→ (N) price_batch_pending
users (1) ──CASCADE──→ (N) ml_connections
users (1) ──CASCADE──→ (N) cencosud_connections
```

---

## 2. Conexiones MercadoLibre (ML)

### Tablas

| Tabla | Propósito | Columnas clave |
|---|---|---|
| `ml_connections` | Tiendas/vendedores ML conectados | `id`, `user_id` → users CASCADE, `store_name`, `is_active`, `access_token` |
| `ml_products_cache` | Cache de productos sincronizados | `ml_connection_id` → connections CASCADE, `ml_item_id`, `sku`, `price`, stock |
| `ml_sync_status` | Estado de sincronización por tienda | `ml_connection_id` → connections CASCADE, `status`, `total_products` |

### Relaciones

```
ml_connections (1) ──CASCADE──→ (N) ml_products_cache
ml_connections (1) ──CASCADE──→ (N) ml_sync_status
ml_connections (1) ──SET NULL──→ (N) auditorias
ml_connections (1) ──SET NULL──→ (N) price_change_logs
ml_connections (1) ──CASCADE──→ (N) price_batch_pending
ml_connections (1) ──SET NULL──→ (N) notifications
ml_connections (1) ──SET NULL──→ (N) users.current_store_id
```

---

## 3. Auditoría y precios (Audit & Price)

### Tablas

| Tabla | Propósito | Columnas clave |
|---|---|---|
| `auditorias` | Resultados de auditorías de precios | `user_id` → users SET NULL, `ml_connection_id` → connections SET NULL, `sku`, `ml_item_id`, precios |
| `price_change_logs` | Historial de cambios de precio | `user_id` → users SET NULL, `ml_connection_id` → connections SET NULL, `batch_id`, `old_price`, `new_price` |
| `price_batch_pending` | Lotes de precios pendientes de procesar | `user_id` → users SET NULL, `ml_connection_id` → connections CASCADE, `data` |

### Relaciones

```
users ──SET NULL──→ auditorias
ml_connections ──SET NULL──→ auditorias
users ──SET NULL──→ price_change_logs
ml_connections ──SET NULL──→ price_change_logs
users ──SET NULL──→ price_batch_pending
ml_connections ──CASCADE──→ price_batch_pending
```

---

## 4. Catálogo de productos (Catalog)

### Tablas

| Tabla | Propósito |
|---|---|
| `products_master` | Catálogo maestro de productos (SKU como PK natural) |
| `product_medidas` | Medidas/peso por categoría de producto |
| `categories_hierarchy` | Jerarquía de categorías (principal → secundaria → filtro) |

No tienen dependencias FK con otros módulos. Son datos de referencia.

---

## 5. Productos web (Web Products)

### Tablas

| Tabla | Propósito |
|---|---|
| `web_products_source` | Datos importados desde archivos Excel (transitorio por batch) |

Tabla independiente, sin FKs externas.

---

## 6. Notificaciones

### Tablas

| Tabla | Propósito | Columnas clave |
|---|---|---|
| `notifications` | Notificaciones por usuario | `user_id` → users CASCADE, `ml_connection_id` → connections SET NULL, `type`, `is_read` |

---

## 7. Actividad y logging (Activity)

### Tablas

| Tabla | Propósito | Columnas clave |
|---|---|---|
| `activity_logs` | Registro de actividad del sistema | `user_id` → users SET NULL, `action`, `description`, `ip_address` |

---

## 8. Configuración del sistema (System Config)

### Tablas

| Tabla | Propósito |
|---|---|
| `app_settings` | Pares clave-valor de configuración global |

Tabla independiente, sin FKs.

---

## 9. Walmart Chile Marketplace (Walmart)

Agregado el 2026-07-13 (`MIGRATION-2026-07-13-WALMART.sql`).

### Tablas

| Tabla | Propósito | Columnas clave |
|---|---|---|
| `walmart_connections` | Cuentas de vendedor Walmart conectadas (multi-cuenta) | `user_id` → users CASCADE, `client_id`, `client_secret` (cifrado AES-256), `channel_type`, `api_base_url`, `access_token` (token de 15 min cacheado, cifrado) |
| `walmart_products_cache` | Cache del catálogo sincronizado (SKU como clave natural) | `walmart_connection_id` → connections CASCADE, UNIQUE (`walmart_connection_id`,`sku`), `wpid`, `price`, `available_quantity`, `published_status` |
| `walmart_sync_status` | Estado de sincronización por tienda | `walmart_connection_id` → connections CASCADE, `status`, `next_cursor` (cursor de paginación persistido) |
| `walmart_update_batches` | Batches pendientes/reanudables de precio o stock | `user_id` → users SET NULL, `walmart_connection_id` → connections CASCADE, `update_type` (price/stock), `data` JSON |
| `walmart_update_logs` | Historial por ítem de las actualizaciones ejecutadas | `user_id` → users SET NULL, `walmart_connection_id` → connections SET NULL, `batch_id`, `old_value`, `new_value` |

### Relaciones

```
users (1) ──CASCADE──→ (N) walmart_connections
users (1) ──SET NULL──→ (N) walmart_update_batches
users (1) ──SET NULL──→ (N) walmart_update_logs
walmart_connections (1) ──CASCADE──→ (N) walmart_products_cache
walmart_connections (1) ──CASCADE──→ (N) walmart_sync_status
walmart_connections (1) ──CASCADE──→ (N) walmart_update_batches
walmart_connections (1) ──SET NULL──→ (N) walmart_update_logs
```

A diferencia de ML no hay refresh_token: la autenticación es OAuth 2.0
client_credentials (Basic + `WM_SEC.ACCESS_TOKEN` de ~15 min pedido on-demand).

---

## 10. Base de datos central de productos — Fase 1 (Products Central)

Agregado el 2026-07-14 (`MIGRATION-2026-07-14-PRODUCTS-CENTRAL.sql`).

### Tablas

| Tabla | Propósito | Columnas clave |
|---|---|---|
| `products_central` | Fase 1: copia limpia y sincronizada (solo lectura) de `ml_products_cache` | `ml_connection_id` → connections CASCADE, UNIQUE (`ml_connection_id`,`ml_item_id`), `sku`, `source_channel` |

### Relaciones

```
ml_connections (1) ──CASCADE──→ (N) products_central
```

### Cómo se mantiene sincronizada

`ProductsCentralModel::mirrorFromMlCache($connectionId)` borra y reinserta (en transacción) las filas
de esa conexión a partir de `ml_products_cache`, pasando cada fila por una limpieza antes de insertar
(ver `ProductsCentralModel::cleanCacheRow()`). Se dispara automáticamente al final de cada sync ML,
desde `MlSyncService::finishSync()` y `MlSyncService::runFullSync()`. Si el mirror falla, se loguea
pero no interrumpe el sync de ML (que ya quedó completo en `ml_products_cache`).

**Limpieza aplicada** (detalle y casos límite en
`Docs/database/PRODUCTS-CENTRAL-LIMPIEZA-CASOS-CONFLICTIVOS.md`):

- `title`: trim, colapso de espacios/tabs/saltos de línea, decodificación de entidades HTML, quita tags.
  No toca mayúsculas ni acentos (evita fusionar productos distintos con nombres parecidos).
- `sku`: trim, colapso de espacios, mayúsculas, y placeholders tipo `-`, `N/A`, `0` se tratan como `NULL`.
- Se descarta la fila **solo** si queda sin título y sin SKU (no hay forma de mostrarla ni buscarla).
- `price` / `available_quantity` / `sold_quantity` negativos (datos corruptos) se llevan a 0 en vez de
  descartar el producto.
- **Nunca se fusionan filas por título**: cada `ml_item_id` sigue generando como máximo una fila propia,
  aunque comparta título exacto con su producto padre o con otras variantes (color/talla) — importante
  porque en este sistema las variaciones de un ítem ML heredan el título del padre y solo cambian
  `sku`/`price`/`available_quantity` (ver `MlProductsCacheModel::upsertFromMlItem()`).

Como consecuencia, `products_central` **ya no es necesariamente 1:1** en conteo de filas con
`ml_products_cache` (puede tener menos filas si se descartó basura). `ProductsCentralModel::verifyAgainstCache()`
y `MlController::centralVerify()` consideran correcto que `central_count <= cache_count`; que sea
mayor sí sigue siendo un bug.

Mismo grano que `ml_products_cache` (por conexión, no SKU único global) — muchos ítems de ML no traen
SKU (`SELLER_SKU` es opcional), así que forzar unicidad global de SKU queda para cuando se resuelva ese
problema de datos, en una fase posterior.

`source_channel` (hoy siempre `'mercadolibre'`) deja preparado el esquema para espejar otros canales
(Walmart, etc.) sin tener que rehacer la tabla.

**Descripciones** (agregado 2026-07-15, `MIGRATION-2026-07-15-PRODUCTS-CENTRAL-DESCRIPTION.sql`):
columna `description`, cargada aparte del mirror (no viene de `ml_products_cache`) solo para las
filas "ganadoras" del dedup por título (~31k de ~154k), vía botón admin en `/productcentral`. Ver
`Docs/database/PRODUCTS-CENTRAL-DESCRIPTIONS.md`.

**Segmentación de packs** (agregado 2026-07-20, `MIGRATION-2026-07-20-PRODUCTS-CENTRAL-SKU-PACK.sql`):
columnas `sku_pack_type` (`PACK`/`TRIPACK`/`NULL`) y `sku_base`, calculadas en cada `mirrorFromMlCache()`
detectando el prefijo del SKU y vinculando contra el SKU base real de la misma tienda cuando el match es
confiable (si no, queda `NULL`, no se inventa el dato). Ver `Docs/database/PRODUCTS-CENTRAL-SKU-SEGMENTACION.md`.

**Fase 2 (pendiente)**: invertir la dirección — `products_central` pasa a ser la fuente de verdad y
empuja cambios de precio/stock/publicación hacia los marketplaces vía API, en vez de solo reflejarlos.

---

## 11. Cencosud Marketplace (Cencosud)

Agregado el 2026-07-14 (`MIGRATION-2026-07-14-CENCOSUD.sql`).

### Tablas

### Tablas

| Tabla | Propósito | Columnas clave |
|---|---|---|
| `cencosud_connections` | Tiendas Cencosud (Paris.cl) conectadas | `id`, `user_id` → users CASCADE, `store_name`, `client_id`, `client_secret` (AES-256), `access_token`, `token_expires_at`, `is_active` |
| `cencosud_products_cache` | Caché local de productos sincronizados desde Cencosud | `id`, `cencosud_connection_id` → connections CASCADE, `sku`, `cencosud_product_id`, `gtin`, `upc`, `title`, `price`, `available_quantity`, `published_status`, `extra_data` |
| `cencosud_sync_status` | Estado de sincronización por tienda Cencosud | `id`, `cencosud_connection_id` → connections CASCADE, `status`, `total_products`, `synced_products`, `next_cursor`, `last_sync_at`, `last_error` |
| `cencosud_update_batches` | Lotes de actualización masiva de precio/stock | `id`, `user_id` → users SET NULL, `cencosud_connection_id` → connections CASCADE, `batch_id`, `update_type`, `data`, `total`, `processed`, `status` |
| `cencosud_update_logs` | Historial de actualizaciones de precio/stock Cencosud | `id`, `user_id` → users SET NULL, `cencosud_connection_id` → connections SET NULL, `store_name`, `batch_id`, `update_type`, `sku`, `old_value`, `new_value`, `status` |
| `cencosud_orders_cache` | Caché de órdenes y subórdenes de despacho | `id`, `cencosud_connection_id` → connections CASCADE, `order_id`, `status`, `total_amount`, `customer_name`, `shipping_status`, `created_at_cenco`, `raw_data` |

### Relaciones

```
cencosud_connections (1) ──CASCADE──→ (N) cencosud_products_cache
cencosud_connections (1) ──CASCADE──→ (N) cencosud_sync_status
cencosud_connections (1) ──CASCADE──→ (N) cencosud_update_batches
cencosud_connections (1) ──CASCADE──→ (N) cencosud_orders_cache
cencosud_connections (1) ──SET NULL──→ (N) cencosud_update_logs
users (1) ──CASCADE──→ (N) cencosud_connections
users (1) ──SET NULL──→ (N) cencosud_update_batches
users (1) ──SET NULL──→ (N) cencosud_update_logs
```

> **Nota sobre la simbología de relaciones (`──CASCADE──→` y `──SET NULL──→`):**
> - `(1)` indica el registro padre (1 usuario o 1 tienda) y `(N)` indica muchos registros hijos (múltiples productos, lotes o ventas).
> - La flecha **`──CASCADE──→` (Eliminación en Cascada)** significa que si se borra el registro padre, la base de datos elimina automáticamente todos sus datos asociados para no dejar registros huérfanos.
> - La flecha **`──SET NULL──→` (Conservar con valor Nulo)** significa que si el usuario o tienda desaparece, los registros de historial (como auditorías o cambios de precio) se conservan para no perder el registro histórico, dejando la referencia en `NULL`.

---

## Colaciones de Base de Datos (Collations)

> **¿Qué es una colación en MySQL?**
> Una **colación (collation)** es el conjunto de reglas que utiliza la base de datos para comparar, buscar y ordenar textos (definición de tildes, eñes `ñ`, mayúsculas y minúsculas).

### Estado actual: Mezcla de colaciones

Actualmente existen dos reglas distintas según el momento en que se crearon las tablas:

| Collation | Descripción | Tablas afectadas |
|---|---|---|
| `utf8mb4_general_ci` | Regla antigua más rápida pero menos precisa con caracteres especiales | `users`, `activity_logs`, `auditorias`, `ml_products_cache`, `ml_sync_status`, `notifications`, `price_batch_pending`, `price_change_logs`, `app_settings`, `login_attempts` |
| `utf8mb4_unicode_ci` | Regla moderna estándar internacional (respeta correctamente tildes, eñes `ñ` y emojis) | `ml_connections`, `cencosud_connections`, `cencosud_products_cache`, `cencosud_sync_status`, `cencosud_update_batches`, `cencosud_update_logs`, `cencosud_orders_cache`, `categories_hierarchy`, `products_master`, `product_medidas`, `rate_limits`, `recovery_codes`, `web_products_source`, `password_resets`, `products_central`, `products_central_title_stats`, `product_cross_links`, `product_cross_link_members`, `product_cross_link_dismissed` |

> **¿Por qué se señala como pendiente?**
> Cuando dos tablas tienen distinta colación, intentar hacer búsquedas combinadas (`JOIN` directo) entre textos puede generar un aviso de conflicto de tipos en MySQL. Por ello, se recomienda unificar todas las tablas a `utf8mb4_unicode_ci` en el futuro.
