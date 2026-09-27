# Refactor ML, Base de Datos y mantenimiento

**Fecha**: 21 de junio de 2026

## Nuevo

### Credenciales ML desde UI
- Nueva página `/admin/mlCredentials` para configurar APP ID y CLIENT SECRET
- Las credenciales se guardan cifradas en `app_settings` (no más `.env`)
- Sidebar: punto verde si están configuradas, naranjo si faltan
- Botón "Limpiar credenciales" con confirmación por frase

### Store linking con APP ID
- Columna `config_app_id` en `ml_connections`: guarda qué APP ID se usó al conectar
- Admin stores muestra si el APP ID fue cambiado (badge rojo "Cambiado")

### Persistencia de store_name
- Columnas `store_name` en `auditorias` y `price_change_logs`
- Al borrar una tienda, los registros históricos conservan el nombre
- Backfill de datos existentes

### Página "Base de Datos" (`/admin/databaseTools`)
- Centraliza purga de: notificaciones, activity logs, email logs, auditorías
- Cada acción con `openConfirm` + `requirePhrase`
- Enlace en sidebar (icono database)

### Debug token (`public_html/debug-token.php`)
- Script independiente para verificar token ML
- Muestra: store, APP ID, expiración del token, test `/users/me`
- Solo accesible para admins

### Notificaciones
- Eliminación individual (hover X en cada notificación)
- Endpoints: `delete(id)`, `deleteAll`, `deleteAllUsers`
- Método `purgeOld()` para limpieza programada

## Cambios

### Connect page (`/connections/connect`)
- Alerta de credenciales usa `.dash-alert` (dashboard-style)
- Eliminado `min-height: 100vh` (ya no centra verticalmente)
- CSS migrado a variables de tema (sin colores hardcodeados)

### Empty states
- Admin stores y Products: reemplazado `.users-empty` / `.empty-state` por `.dash-empty`
- Sin iconos, centrado, mismo estilo que el dashboard

### Sidebar
- Renombrado: "Credenciales ML" y "Gestión ML"
- Agregado "Base de Datos"

### Callback de ML (bugfix)
- `MercadoLibreAuthService` ahora recibe `$this->db()` en el callback
- Antes se creaba sin DB, enviaba credenciales vacías a ML y fallaba

### AppSettingModel::set()
- Cambiado de `UPDATE` a `INSERT ... ON DUPLICATE KEY UPDATE`
- Soluciona bug donde el guardado fallaba silenciosamente si la fila no existía

### Correo electrónico
- `Reply-To` cambiado a `noreply-software@epracticas.cl`
- Voseo eliminado en `welcome.php` (`"para vos"` → `"para ti"`)

### Product Creator
- ~25 instancias de voseo reemplazadas por español neutro
- `matchear`/`matchean` → `hacer coincidir`/`asignan`

### Variables de entorno
- Eliminadas `ML_APP_ID` y `ML_CLIENT_SECRET` de `.env` (obsoletas)
- Eliminados sus `define()` en `config.php`

## Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Controllers/AdminController.php` | `mlCredentials()`, `saveMlCredentials()`, `getMlSecret()`, `databaseTools()` |
| `app/Controllers/MlController.php` | `callback()` bugfix, `purgeAudits()`, `deleteAuditBatch()`, consultas simplificadas |
| `app/Controllers/DashboardController.php` | Consulta de auditorías usa `a.store_name` |
| `app/Controllers/NotificationsController.php` | `delete()`, `deleteAll()`, `deleteAllUsers()` |
| `app/Models/AppSettingModel.php` | `set()` usa INSERT...ON DUPLICATE KEY UPDATE |
| `app/Models/ConnectionModel.php` | Agregado `config_app_id` en queries |
| `app/Models/EmailLogModel.php` | `purgeAll()` |
| `app/Models/NotificationModel.php` | `deleteById()`, `deleteAllForUser()`, `deleteAll()`, `purgeOld()` |
| `app/Models/PriceLogModel.php` | `store_name` en insert y select simplificado |
| `app/Services/NotificationService.php` | `delete()`, `deleteAllForUser()`, `deleteAll()`, `purgeOld()` |
| `app/Services/PriceService.php` | `logChange()` guarda store_name |
| `app/Services/MailService.php` | Reply-To fijo a noreply-software@epracticas.cl |
| `app/Helpers/MlApiHelper.php` | Limpieza de método obsoleto `getAppInfo()` |
| `app/Views/admin/content-ml-credentials.php` | Rediseño completo |
| `app/Views/admin/content-database-tools.php` | **Nuevo** |
| `app/Views/ml/content-connect.php` | Alerta dashboard-style |
| `app/Views/ml/content-admin-stores.php` | Empty state dash-empty, columna App ID |
| `app/Views/ml/content-products.php` | Empty state dash-empty |
| `app/Views/ml/content-audit-history.php` | Botón Purga (admin) |
| `app/Views/ml/content-audit-batch-detail.php` | Botón Borrar lote (admin) |
| `app/Views/layouts/app.php` | Notificaciones: hover X para eliminar |
| `app/Views/layouts/partials/sidebar.php` | Items renombrados, base de datos, badge verde/naranjo |
| `app/Views/emails/welcome.php` | Voseo corregido |
| `app/Views/productcreator/content-index.php` | Voseo corregido |
| `public_html/debug-token.php` | **Nuevo** |
| `public_html/assets/css/partials/_ml.css` | Connect page: colores hardcodeados → CSS variables |
| `public_html/assets/css/partials/_sidebar.css` | `.ml-alert-badge--ok` para punto verde |
| `public_html/assets/css/partials/_notifications.css` | `.notif-delete-btn` hover rojo |
| `Docs/DEPLOY.md` | **Nuevo** — guía de deploy en Hostinger |
| `Docs/CHANGELOG-2026-06-21.md` | Este archivo |
| `Docs/database/SCHEMA.sql` | Actualizado con columnas nuevas |

## Base de datos

### Migraciones

```sql
ALTER TABLE ml_connections
  ADD COLUMN config_app_id varchar(64) DEFAULT NULL AFTER country,
  ADD INDEX idx_config_app_id (config_app_id);

ALTER TABLE auditorias
  ADD COLUMN store_name varchar(100) DEFAULT NULL AFTER ml_connection_id;

ALTER TABLE price_change_logs
  ADD COLUMN store_name varchar(100) DEFAULT NULL AFTER ml_connection_id;
```

### Backfill

```sql
UPDATE auditorias a LEFT JOIN ml_connections c ON a.ml_connection_id = c.id
  SET a.store_name = c.store_name;

UPDATE price_change_logs l LEFT JOIN ml_connections c ON l.ml_connection_id = c.id
  SET l.store_name = c.store_name;
```
