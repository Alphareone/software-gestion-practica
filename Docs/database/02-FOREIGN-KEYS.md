# Foreign Keys

## Diagnóstico inicial

De 16 relaciones semánticas entre tablas, solo **5** tenían una FK explícita.
Las 11 restantes eran implícitas (columnas con índices pero sin constraint).

### FKs pre-existentes (correctas)

| Tabla | Columna | Referencia | ON DELETE |
|---|---|---|---|
| `activity_logs` | `user_id` | `users(id)` | SET NULL |
| `notifications` | `user_id` | `users(id)` | CASCADE |
| `rate_limits` | `user_id` | `users(id)` | CASCADE |
| `recovery_codes` | `user_id` | `users(id)` | CASCADE |
| `password_resets` | `user_id` | `users(id)` | CASCADE |

### FKs faltantes (agregadas)

| Tabla | Columna | Referencia | ON DELETE | Comportamiento |
|---|---|---|---|---|
| `ml_connections` | `user_id` | `users(id)` | CASCADE | Si se borra usuario, se borran sus tiendas |
| `ml_products_cache` | `ml_connection_id` | `ml_connections(id)` | CASCADE | Si se borra tienda, se borra su cache |
| `ml_sync_status` | `ml_connection_id` | `ml_connections(id)` | CASCADE | Si se borra tienda, se borra su sync status |
| `auditorias` | `user_id` | `users(id)` | SET NULL | Si se borra usuario, auditoría preserva NULL |
| `auditorias` | `ml_connection_id` | `ml_connections(id)` | SET NULL | Si se borra tienda, auditoría preserva NULL |
| `price_change_logs` | `user_id` | `users(id)` | SET NULL | Historial preservado aunque usuario desaparezca |
| `price_change_logs` | `ml_connection_id` | `ml_connections(id)` | SET NULL | Historial preservado aunque tienda desaparezca |
| `price_batch_pending` | `user_id` | `users(id)` | SET NULL | Registro preservado si el usuario se elimina |
| `price_batch_pending` | `ml_connection_id` | `ml_connections(id)` | CASCADE | Lote inservible sin tienda |
| `notifications` | `ml_connection_id` | `ml_connections(id)` | SET NULL | Notificación preservada si tienda se elimina |
| `users` | `current_store_id` | `ml_connections(id)` | SET NULL | Tienda activa del usuario se limpia al borrar tienda |
| `cencosud_connections` | `user_id` | `users(id)` | CASCADE | Si se borra usuario, se borran sus tiendas Cencosud |
| `cencosud_products_cache` | `cencosud_connection_id` | `cencosud_connections(id)` | CASCADE | Si se borra tienda Cencosud, se borra su caché |
| `cencosud_sync_status` | `cencosud_connection_id` | `cencosud_connections(id)` | CASCADE | Si se borra tienda Cencosud, se borra su sync status |
| `cencosud_update_batches` | `cencosud_connection_id` | `cencosud_connections(id)` | CASCADE | Lote inservible sin tienda Cencosud |
| `cencosud_update_batches` | `user_id` | `users(id)` | SET NULL | Registro preservado si el usuario se elimina |
| `cencosud_update_logs` | `cencosud_connection_id` | `cencosud_connections(id)` | SET NULL | Historial preservado aunque la tienda desaparezca |
| `cencosud_update_logs` | `user_id` | `users(id)` | SET NULL | Historial preservado aunque el usuario desaparezca |
| `cencosud_orders_cache` | `cencosud_connection_id` | `cencosud_connections(id)` | CASCADE | Si se borra tienda Cencosud, se borran sus órdenes |

### FKs del módulo Walmart (2026-07-13, nativas desde la migración)

| Tabla | Columna | Referencia | ON DELETE | Comportamiento |
|---|---|---|---|---|
| `walmart_connections` | `user_id` | `users(id)` | CASCADE | Si se borra usuario, se borran sus tiendas Walmart |
| `walmart_products_cache` | `walmart_connection_id` | `walmart_connections(id)` | CASCADE | Si se borra tienda, se borra su cache |
| `walmart_sync_status` | `walmart_connection_id` | `walmart_connections(id)` | CASCADE | Si se borra tienda, se borra su sync status |
| `walmart_update_batches` | `user_id` | `users(id)` | SET NULL | Batch preservado si el usuario se elimina |
| `walmart_update_batches` | `walmart_connection_id` | `walmart_connections(id)` | CASCADE | Lote inservible sin tienda |
| `walmart_update_logs` | `user_id` | `users(id)` | SET NULL | Historial preservado aunque usuario desaparezca |
| `walmart_update_logs` | `walmart_connection_id` | `walmart_connections(id)` | SET NULL | Historial preservado aunque tienda desaparezca |

---

## Cambios de tipo requeridos

Para que las FKs funcionen, los tipos de columna (SIGNED/UNSIGNED) deben
coincidir entre padre e hijo.

| Columna | Antes | Después |
|---|---|---|
| `ml_connections.user_id` | `INT(10) UNSIGNED` | `INT(11)` |
| `auditorias.ml_connection_id` | `INT(11)` | `INT(11) UNSIGNED` |
| `price_change_logs.ml_connection_id` | `INT(11)` | `INT(11) UNSIGNED` |
| `price_batch_pending.ml_connection_id` | `INT(11)` | `INT(11) UNSIGNED` |
| `users.current_store_id` | `INT(11)` | `INT(11) UNSIGNED` |

---

## Columnas cambiadas a nullable

Para poder usar `ON DELETE SET NULL`, las columnas deben ser `DEFAULT NULL`.

| Tabla | Columna | Antes | Después |
|---|---|---|---|
| `auditorias` | `user_id` | `NOT NULL` | `DEFAULT NULL` |
| `auditorias` | `ml_connection_id` | `NOT NULL` | `DEFAULT NULL` |
| `price_change_logs` | `user_id` | `NOT NULL` | `DEFAULT NULL` |
| `price_change_logs` | `ml_connection_id` | `NOT NULL` | `DEFAULT NULL` |
| `price_batch_pending` | `user_id` | `NOT NULL` | `DEFAULT NULL` |

---

## Datos huérfanos reparados

Antes de agregar las FKs, se encontraron registros huérfanos:

| Tabla | Registros | Problema | Solución |
|---|---|---|---|
| `ml_connections` | 2 (id=19 "BAXMAN", id=20 "TEST") | `user_id=5` no existe en `users` | Reasignado a admin (id=22) |
| `auditorias` | 1274 | `user_id=5` no existe en `users` | Seteado a `NULL` |

---

## Código simplificado

### `MlController::deleteStore()`

Antes: 3 queries manuales (UPDATE users, DELETE price_batch_pending, DELETE ml_connections).
Después: 1 query (DELETE ml_connections). Las FKs se encargan del resto.

```php
// MercadoLibre: al borrar la conexión
$db->query('DELETE FROM ml_connections WHERE id = ?')->execute([$storeId]);
// users.current_store_id → ON DELETE SET NULL
// price_batch_pending.ml_connection_id → ON DELETE CASCADE
// ml_products_cache.ml_connection_id → ON DELETE CASCADE
// ml_sync_status.ml_connection_id → ON DELETE CASCADE
// auditorias.ml_connection_id → ON DELETE SET NULL
// price_change_logs.ml_connection_id → ON DELETE SET NULL
// notifications.ml_connection_id → ON DELETE SET NULL

// Cencosud: al borrar la conexión
$db->query('DELETE FROM cencosud_connections WHERE id = ?')->execute([$storeId]);
// cencosud_products_cache.cencosud_connection_id → ON DELETE CASCADE
// cencosud_sync_status.cencosud_connection_id → ON DELETE CASCADE
// cencosud_update_batches.cencosud_connection_id → ON DELETE CASCADE
// cencosud_orders_cache.cencosud_connection_id → ON DELETE CASCADE
// cencosud_update_logs.cencosud_connection_id → ON DELETE SET NULL
```
