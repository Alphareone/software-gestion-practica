# Buenas prácticas de base de datos

## Convenciones de nomenclatura

### Tablas

- `snake_case` en plural: `ml_connections`, `price_change_logs`, `recovery_codes`
- Excepciones justificadas: `auditorias` (heredada), `product_medidas` (heredada)
- Tablas pivote: `{tabla1}_{tabla2}` (ej. si existiera `users_roles`)

### Columnas

- `snake_case`: `store_name`, `is_active`, `last_login_at`
- FK columnas: `{tabla_referencia}_id` → `user_id`, `ml_connection_id`
- Flags booleanas: prefijo `is_`, `has_`, `can_` → `is_active`, `is_admin` (eliminada), `is_read`, `twofa_enabled`
- Timestamps: sufijo `_at` → `created_at`, `updated_at`, `last_login_at`, `synced_at`
- Fechas: sufijo `_date` → `audit_date` (cuando no es timestamp UTC)
- Contraseñas/tokens: sufijo `_hash` → `code_hash`, `twofa_remember_token_hash`

### Constraints

| Tipo | Prefijo | Ejemplo |
|---|---|---|
| Primary Key | (implícito) | `PRIMARY KEY (id)` |
| Foreign Key | `fk_` | `fk_ml_connections_user` |
| Unique | `uk_` | `uk_store_item` |
| Index | `idx_` | `idx_user_id` |

Formato: `{prefijo}_{tabla}_{columna}` o `{prefijo}_{tabla}_{columnas}`

### Evitar

- Nombres reservados de SQL como columnas (`order`, `group`, `select`, `status` —
  aunque `status` ya se usa, se escapa con backticks donde sea necesario)
- Abreviaturas crípticas (`usr_id` → `user_id`, `ml_conn` → `ml_connection_id`)
- Plurales en columnas FK (`users_id` → `user_id`)

---

## Tipos de datos

### Enteros

| Tipo | Rango | Uso |
|---|---|---|
| `TINYINT(1)` | 0–255 (o ±127) | Flags booleanas (`is_read`, `twofa_enabled`) |
| `INT(11)` | ±2.1×10⁹ | IDs de registro, FK a `users(id)` |
| `INT(10) UNSIGNED` | 0–4.2×10⁹ | IDs de auto-increment en `ml_connections` |
| `BIGINT(20)` | ±9.2×10¹⁸ | IDs externos (ej. `ml_user_id` de ML) |

Regla: **SIGNED vs UNSIGNED debe coincidir** entre padre e hijo en una FK.

### Decimales

- Precios: `DECIMAL(12,2)` — precisión exacta, 10 dígitos + 2 decimales
- Porcentajes: `DECIMAL(8,2)` — 6 dígitos + 2 decimales
- Pesos/dimensiones: `DECIMAL(8,2)` — salvo que se requiera más precisión
- **Nunca** `FLOAT`/`DOUBLE` para dinero (pérdida de precisión)

### Cadenas

| Tipo | Uso |
|---|---|
| `VARCHAR(50)` | Usernames, códigos cortos |
| `VARCHAR(100)` | SKU, emails, nombres de tienda |
| `VARCHAR(255)` | Tokens, títulos, nombres largos |
| `VARCHAR(500)` | Descripciones, URLs |
| `TEXT` | Descripciones largas, mensajes |
| `LONGTEXT` | JSON grandes, datos en bruto |

### Timestamps y fechas

- **Auditoría temporal** (`created_at`, `updated_at`): usar `TIMESTAMP` (UTC, automático con `current_timestamp()`)
- **Fechas de negocio** (`audit_date`, `token_expires_at`): usar `DATETIME` (sin conversión de zona horaria)
- `updated_at` debe tener `ON UPDATE current_timestamp()` para actualización automática
- Timestamps de sesión/actividad: siempre `DATETIME` (se maneja en PHP la conversión UTC → local)

### Columnas que deberían ser `VARCHAR` pero son `DECIMAL`

`auditorias.ml_price` y `auditorias.prev_ml_price` son `VARCHAR(255)` porque
MercadoLibre puede devolver precios con formato localizado (puntos, comas).
No convertir a `DECIMAL` a menos que se normalice la entrada.

---

## Foreign keys

### Criterio de selección de ON DELETE

| Regla | Cuándo usar |
|---|---|
| `CASCADE` | El hijo no tiene sentido sin el padre (cache, batches pendientes, códigos de recuperación) |
| `SET NULL` | El hijo debe preservarse como registro histórico aunque el padre desaparezca (logs, auditorías) |
| `RESTRICT` (default) | Cuando no debería ser posible borrar el padre si existen hijos (no se usa actualmente en el sistema) |

### Aplicación actual por módulo

```
                CASCADE                SET NULL
           ┌─────────────────┬──────────────────────┐
  Auth     │ recovery_codes  │ activity_logs        │
           │ password_resets │                      │
           │ rate_limits     │                      │
           │ notifications   │                      │
           ├─────────────────┼──────────────────────┤
  ML       │ ml_connections  │ auditorias           │
           │ ml_products_cac │ price_change_logs    │
           │ ml_sync_status  │ notifications        │
           │ price_batch_pe. │ users.current_store  │
           └─────────────────┴──────────────────────┘
```

### Reglas adicionales

- Toda FK debe tener un índice en la columna hija (ya se cumple en todas)
- Nombrar la constraint con prefijo `fk_`: `fk_auditorias_user`, `fk_price_batch_connection`
- No usar `ON UPDATE CASCADE` a menos que sea estrictamente necesario (IDs de auto-increment no cambian)

---

## Índices

### Reglas generales

1. **Toda FK debe tener un índice** — mejora JOINs y evita table scans
2. **Índices compuestos** para queries con múltiples condiciones de filtro:
   - `(ml_connection_id, status)` para filtrar por tienda + estado
   - `(ml_connection_id, sku)` para búsqueda por SKU por tienda
   - `(user_id, used_at)` para filtrar códigos de recuperación no usados
3. **No duplicar índices** — `(a, b)` ya cubre queries con solo `a`
4. **Índices FULLTEXT** para búsqueda textual (`ml_products_cache.title`, `sku`)
5. **No indexar columnas con baja cardinalidad** (`is_active`, `status`) a menos que se combinen con otras columnas

### Índices actuales

| Tabla | Índices | Notas |
|---|---|---|
| `users` | PK, `username` UNIQUE, `email` UNIQUE | Suficiente |
| `activity_logs` | PK, `user_id`, `action`, `created_at` | Cubre FKs y filtros |
| `ml_products_cache` | PK, `uk_store_item` UNIQUE, `idx_store_status`, `idx_store_sku`, `idx_parent`, FULLTEXT `idx_search` | Bien cubierto |
| `auditorias` | PK, `idx_user_id`, `idx_ml_connection_id`, `idx_sku`, `idx_audit_type` | Suficiente |

---

## Migraciones

### Workflow para Hostinger (sin SSH)

1. Exportar dump desde phpMyAdmin (solo estructura)
2. Reemplazar `db/Estructura actual.sql`
3. Commit con mensaje descriptivo
4. Ejecutar cambios manualmente en phpMyAdmin (copiar ALTER TABLE)
5. Actualizar `docs/database/SCHEMA.sql` reflejando el estado final

### Reglas

- **Nunca** modificar la BD directamente sin actualizar el schema file
- Los cambios se documentan en `docs/CHANGELOG-{YYYY-MM-DD}.md`
- Cada migración debe ser atómica y reversible (tener el `ROLLBACK` a mano)

---

## Consultas SQL

### Prepared statements

Todas las consultas deben usar prepared statements. El Model base ya lo
aplica:

```php
// ✅ Correcto
$this->row('SELECT * FROM users WHERE id = ?', [$id]);

// ❌ Incorrecto
$this->query("SELECT * FROM users WHERE id = $id");
```

### `SELECT *` vs columnas explícitas

- `SELECT *` aceptable solo en `findByUsername()` / `findById()` (se necesita
  el objeto completo para sesión)
- En listados y reportes, especificar columnas necesarias (`search()`,
  `getEditData()`, `getBasicInfo()`)

### Prefijo de tablas en JOINs

Siempre usar alias y prefijar columnas:

```sql
SELECT a.*, u.username
FROM activity_logs a
LEFT JOIN users u ON a.user_id = u.id
```

---

## Collations

Actualmente hay una mezcla de collations. Objetivo a futuro:

- **`utf8mb4_unicode_ci`** — collation estándar para todas las tablas
  (soporta caracteres multi-byte como emojis, aunque no se usen actualmente)
- **`utf8mb4_spanish_ci`** — alternativa si se requiere ordenamiento
  específico del español (Ñ, acentos)

Tablas pendientes de migrar de `utf8mb4_general_ci` → `utf8mb4_unicode_ci`:

`users`, `activity_logs`, `auditorias`, `ml_products_cache`, `ml_sync_status`,
`notifications`, `price_batch_pending`, `price_change_logs`, `app_settings`,
`login_attempts`

---

## Pendiente para futuras iteraciones

- [ ] Unificar collations a `utf8mb4_unicode_ci`
- [ ] Corregir `notifications.product_id` (INT) vs `products_master.sku` (VARCHAR) — referencia inviable
- [ ] Agregar FK faltante en `notifications.product_id` una vez normalizada la relación
- [ ] Migrar `auditorias.ml_price` / `prev_ml_price` de VARCHAR a DECIMAL si se normaliza el formato
