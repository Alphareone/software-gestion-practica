# Sistema de correos electrónicos

**Fecha**: 19 de junio de 2026

## Implementación

### MailService con logging

- `MailService::send()` ahora registra cada envío en `email_logs`
- Método `parseUserAgent()` para detectar navegador y SO desde el User-Agent
- Logging automático: destinatario, template, asunto, estado, metadata

### 7 templates de email HTML

Con layout profesional: logo + "Sistema de gestión" / "e-practicas", card blanca, botón CTA, footer.

### Login notification automática

- `AuthService::completeLoginSession()` compara IP y User-Agent actual vs almacenado
- Si cambian, envía `login-notification` con datos del dispositivo
- Almacena `last_ip` y `last_user_agent` en `users`

### Envíos reales conectados

| Template | Dónde |
|---|---|
| `account-suspended` | `UsersController::toggleStatus()` y `update()` |
| `password-changed` | `AuthController::resetpassword()` |
| `login-notification` | `AuthService::completeLoginSession()` |

### Admin email logs

- Nueva página `/admin/emailLogs`
- Tabla paginada con filtros por template y búsqueda
- Indicador visual de estado (enviado/fallido)
- Enlace en sidebar (icono mail)

### Base de datos

- Tabla `email_logs` creada
- Columnas `last_ip`, `last_user_agent` agregadas a `users`

## Archivos creados

| Archivo | Contenido |
|---|---|
| `app/Services/MailService.php` | MailService con logging + UA parser |
| `app/Views/emails/{7 templates}.php` | Templates HTML |
| `app/Models/EmailLogModel.php` | Modelo para consultar email_logs |
| `app/Views/admin/content-email-logs.php` | Vista admin de logs |
| `Docs/database/05-EMAIL-SYSTEM.md` | Documentación del sistema |

## Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Services/AuthService.php` | Login notification en `completeLoginSession()` |
| `app/Controllers/AuthController.php` | Envío de password-changed |
| `app/Controllers/UsersController.php` | Envío de account-suspended |
| `app/Controllers/AdminController.php` | Métodos emailLogs + emailLogData |
| `app/Views/layouts/partials/sidebar.php` | Enlace a Correos |
| `app/Models/UserModel.php` | Método `updateLoginMeta()` |

## ⚠️ Pendiente de eliminar antes de producción

- `MailService::preview()`
- `AdminController::emailpreview()`
- Ruta `/admin/emailpreview`
