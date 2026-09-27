# Sistema de correos electrónicos

## Arquitectura

```
┌─────────────────────────────────────────────────────────────────┐
│                         MailService                             │
│  ┌──────────┐  ┌────────────────┐  ┌─────────────────────────┐  │
│  │ send()   │  │ render()       │  │ parseUserAgent()        │  │
│  │ envía +  │→ │ template +     │  │ Chrome 125 · macOS      │  │
│  │ loguea   │  │ layout → HTML  │  │ Firefox · Windows 11    │  │
│  └──────────┘  └────────────────┘  └─────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
         │                                  ▲
         ▼                                  │
  ┌──────────────┐                   ┌──────────────┐
  │   mail()     │                   │ EmailLogModel│
  │  (PHP nativo)│                   │  INSERT log   │
  └──────────────┘                   └──────┬───────┘
                                           │
                                           ▼
                                    ┌──────────────┐
                                    │  email_logs   │
                                    │    tabla      │
                                    └──────────────┘
```

## Componentes

### MailService (`app/Services/MailService.php`)

Servicio centralizado que maneja el envío de correos HTML.

```php
$mail = new MailService();
$mail->send($to, $subject, $template, $data);
```

**Parámetros adicionales** (dentro de `$data`):
- `user_id` — ID del usuario destinatario (para log)
- `sender_id` — ID del admin que envía (para log)

Estos se extraen antes de renderizar y no pasan al template.

### Templates (`app/Views/emails/`)

| Archivo | Template | Variables |
|---|---|---|
| `password-reset.php` | Restablecer contraseña | `username`, `reset_url` |
| `username-reminder.php` | Recordar usuario | `username` |
| `welcome.php` | Bienvenida / activación | `username`, `reset_url` |
| `admin-reset.php` | Reset por admin | `username`, `reset_url` |
| `login-notification.php` | Inicio de sesión nuevo | `username`, `login_date`, `login_time`, `login_ip`, `login_device`, `login_location`, `protect_url` |
| `account-suspended.php` | Cuenta deshabilitada | `username`, `admin_name`, `suspended_at`, `reason`, `app_email` |
| `password-changed.php` | Contraseña actualizada | `username`, `changed_at`, `changed_ip`, `changed_device`, `reset_url` |

Todos los templates reciben automáticamente: `logo_url`, `brand_name`, `brand_company`, `app_email`, `sitename`.

### Layout (`app/Views/emails/layout.php`)

Estructura base con tabla HTML (compatible con clientes de correo):
- Brand header: logo + "Sistema de gestión" / "e-practicas"
- Separador
- Contenido del template (`$content`)
- Footer: empresa + correo de soporte

Colores del sistema en modo claro:
- Fondo: `#f0f4f8`
- Card: `#ffffff`
- Acento (botones): `#0284c7`
- Peligro (botones): `#dc2626`
- Texto: `#111827` / `#6b7280`

### EmailLogModel (`app/Models/EmailLogModel.php`)

| Método | Descripción |
|---|---|
| `log(recipiente, usuario, template, asunto, estado, user_id, sender_id, metadata)` | Inserta un registro |
| `findAll(page, perPage, search, templateFilter)` | Listado paginado con filtros |
| `getDistinctTemplates()` | Templates únicos para filtro |
| `getTodayCount()` | Correos enviados hoy |

### Detección de login nuevo

En `AuthService::completeLoginSession()`:

```php
$ip       = $_SERVER['REMOTE_ADDR'];
$ua       = $_SERVER['HTTP_USER_AGENT'];
$uaParsed = MailService::parseUserAgent($ua);  // "Chrome · macOS"

// Comparar con stored values
$isNewIp = $ip !== $user->last_ip;
$isNewUa = $uaParsed !== $user->last_user_agent;

// Si cambió IP o dispositivo → enviar notificación
if ($isNewIp || $isNewUa) {
    $mail->send(..., 'login-notification', [...]);
}

// Actualizar stored values
$this->user->updateLoginMeta($user->id, $ip, $ua);
```

### parseUserAgent() — detección de navegador/SO

```php
MailService::parseUserAgent($_SERVER['HTTP_USER_AGENT']);
// → "Chrome 125 · macOS Sonoma"
// → "Firefox 128 · Windows 11"
// → "Safari · iOS"
```

Soporta: Chrome, Firefox, Edge, Safari, Opera, Windows 10/11, macOS, iOS, Android, Linux.

## Envíos por template

| Template | Dónde se envía | Gatillante |
|---|---|---|
| `password-reset` | `AuthController::forgotpassword()` | Usuario solicita restablecer contraseña |
| `username-reminder` | `AuthController::forgotusername()` | Usuario solicita recordatorio de usuario |
| `welcome` | `UsersController::create()` | Admin crea un nuevo usuario |
| `admin-reset` | `UsersController::sendResetLink()` | Admin envía link de restablecimiento |
| `login-notification` | `AuthService::completeLoginSession()` | Login exitoso desde IP/dispositivo nuevo |
| `account-suspended` | `UsersController::toggleStatus()` | Admin deshabilita usuario |
| `account-suspended` | `UsersController::update()` | Admin actualiza usuario a estado inactivo |
| `password-changed` | `AuthController::resetpassword()` | Usuario completa restablecimiento de contraseña |

## Administración de logs

**Ruta**: `/admin/emailLogs` (sidebar: "Correos")

Misma interfaz que el registro de actividad:
- Tabla paginada con fecha, destinatario, template, asunto, estado
- Filtro por template
- Búsqueda por correo o nombre de usuario
- Indicador visual: verde = enviado, rojo = fallido

## Base de datos

### Tabla `email_logs`

```sql
CREATE TABLE email_logs (
    id              INT(11) AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(100) NOT NULL,
    recipient_user  VARCHAR(50) DEFAULT NULL,
    template        VARCHAR(50) NOT NULL,
    subject         VARCHAR(255) NOT NULL,
    status          VARCHAR(20) NOT NULL DEFAULT 'sent',
    sent_at         TIMESTAMP DEFAULT current_timestamp(),
    user_id         INT(11) DEFAULT NULL,
    sender_id       INT(11) DEFAULT NULL,
    metadata        TEXT DEFAULT NULL,
    KEY idx_template (template),
    KEY idx_sent_at (sent_at),
    KEY idx_user_id (user_id)
);
```

### Columnas agregadas a `users`

```sql
ALTER TABLE users
  ADD COLUMN last_ip VARCHAR(45) DEFAULT NULL,
  ADD COLUMN last_user_agent VARCHAR(500) DEFAULT NULL;
```

## ⚠️ DEBUG — eliminar antes de producción

- `MailService::preview()` — renderiza sin enviar
- `AdminController::emailpreview()` — endpoint `/admin/emailpreview`

Buscar `⚠️ DEBUG` en el código para localizar estos métodos.
