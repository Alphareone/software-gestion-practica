# Normalización de base de datos

**Fecha**: 18 de junio de 2026

## Resumen

Se ejecutó una normalización integral de la base de datos para eliminar
malas prácticas de integridad referencial y redundancia de datos.

### 1. Foreign keys faltantes (11 constraints añadidas)

Se agregaron constraints a 7 tablas que carecían de FKs, dejando la BD
de 0 a 16 constraints.

### 2. Tipos de columna corregidos

Se unificaron tipos `INT` SIGNED/UNSIGNED entre columnas relacionadas
para permitir la creación de FKs.

### 3. Columnas nullable

Se cambiaron 5 columnas de `NOT NULL` a `DEFAULT NULL` en tablas que
usan `ON DELETE SET NULL`.

### 4. Datos huérfanos reparados

- 2 conexiones ML reasignadas de user_id=5 (eliminado) a admin (id=22)
- 1274 registros de auditoría con user_id huérfano → `NULL`

### 5. Redundancia eliminada: `is_admin`

Columna `is_admin` eliminada de `users`. Se computa desde `role` vía
expresión SQL `role = 'admin' AS is_admin`. 42 referencias en PHP
actualizadas en Model, Service y Auth.

### 6. Código simplificado

`MlController::deleteStore()` ya no necesita borrar manualmente
`price_batch_pending` ni actualizar `users.current_store_id` — las
FKs lo manejan automáticamente.

---

## Archivos modificados

| Archivo | Cambio |
|---|---|
| `db/Estructura actual.sql` | Schema actualizado con nuevas FKs, tipos y columnas nullable |
| `app/Models/UserModel.php` | 7 SQL + método `isAdmin()` sin columna `is_admin` |
| `app/Services/UserService.php` | Eliminada lógica redundante de `is_admin` |
| `app/Services/AuthService.php` | Sesión deriva `is_admin` de `role` |
| `app/Controllers/MlController.php` | Simplificado `deleteStore()` |

## Documentación

Ver `docs/database/` para detalle completo de cada cambio.
