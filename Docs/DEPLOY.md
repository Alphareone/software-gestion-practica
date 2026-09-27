# Deploy — Hostinger

## 1. Estructura en producción

```
/home/u479877971/domains/epracticas.cl/
├── backend-software/          ← app (privado, fuera de public_html)
│   ├── app/
│   ├── vendor/
│   ├── .env
│   ├── Docs/
│   └── app/cli/
│       ├── sync_products.php      (cada 2h)
│       └── refresh_tokens.php     (cada 1h)
└── public_html/
    └── software/              ← frontend (público)
        ├── index.php
        ├── assets/
        └── .htaccess
```

---

## 2. Variables de entorno (`.env`)

| Variable | Obligatorio | Descripción |
|---|---|---|
| `DB_HOST` | Sí | Host de la base de datos |
| `DB_USER` | Sí | Usuario de la BD |
| `DB_PASS` | Sí | Contraseña de la BD |
| `DB_NAME` | Sí | Nombre de la BD |
| `URLROOT` | Sí | `https://software.epracticas.cl` |
| `APP_ENV` | Sí | `production` |
| `ENCRYPTION_KEY` | Sí | 64 caracteres hex (32 bytes). Sin esto la app no arranca en producción |
| `APP_EMAIL` | No | Correo usado como remitente (`soporte@...`) |
| `ML_REDIRECT_URI` | Sí | `https://software.epracticas.cl/ml/callback` |
| `ML_AUTH_URL` | Sí | `https://auth.mercadolibre.cl` |

> **Nota:** `ML_APP_ID` y `ML_CLIENT_SECRET` ya no van en `.env`. Se configuran desde el panel: **Administración → Credenciales ML**.

---

## 3. CRON jobs

Configurar en **hPanel → Avanzado → Cron Jobs**, tipo **PHP**:

| Frecuencia | Comando |
|---|---|
| Cada 2 horas (`0 */2 * * *`) | `/usr/bin/php /home/u479877971/domains/epracticas.cl/backend-software/app/cli/sync_products.php` |
| Cada 1 hora (`0 * * * *`) | `/usr/bin/php /home/u479877971/domains/epracticas.cl/backend-software/app/cli/refresh_tokens.php` |
| Cada 30 min (`*/30 * * * *`) | `/usr/bin/php /home/u479877971/domains/epracticas.cl/backend-software/app/cli/walmart_sync_products.php` |
| Cada 10 minutos (`*/10 * * * *`) | `/usr/bin/php /home/u479877971/domains/epracticas.cl/backend-software/app/cli/cencosud_sync_products.php` |

### sync_products.php
Sincroniza productos de MercadoLibre a la caché local. Loggea en `activity_logs` como `sync_cron`.

### refresh_tokens.php
Refresca tokens OAuth próximos a expirar (dentro de 30 min). Si falla, crea una notificación `token_expired` para el admin.

### cencosud_sync_products.php
Sincroniza productos e inventario de Cencosud Paris.cl a la caché local (`cencosud_products_cache`), aplica la regla de stock crítico (rango 1 a 5 → forzado a 0) y genera notificaciones automáticas a la campanita.

### walmart_sync_products.php
Sincroniza el cat&aacute;logo de Walmart Chile a la cach&eacute; local (`walmart_products_cache`) e inventario. Loggea en `activity_logs` como `walmart_sync_cron`. No requiere cron de refresh de tokens: el token de 15 minutos (client_credentials) se obtiene on-demand. Requiere la migraci&oacute;n `Docs/database/MIGRATION-2026-07-13-WALMART.sql`.

---

## 4. Primer deploy

1. Subir `backend-software/` y `public_html/software/` al servidor
2. Crear la base de datos y ejecutar `Docs/database/SCHEMA.sql`
3. Configurar el `.env` con los valores de producción
4. Ingresar a `https://software.epracticas.cl/admin/mlCredentials`
5. Configurar **APP ID** y **CLIENT SECRET** desde el panel (importante: usar la `ENCRYPTION_KEY` de producción)
6. Configurar los CRON jobs en hPanel

---

## 5. Migraciones BD ejecutadas (post-SCHEMA.sql)

```sql
ALTER TABLE ml_connections
  ADD COLUMN config_app_id varchar(64) DEFAULT NULL AFTER country,
  ADD INDEX idx_config_app_id (config_app_id);

ALTER TABLE auditorias
  ADD COLUMN store_name varchar(100) DEFAULT NULL AFTER ml_connection_id;

ALTER TABLE price_change_logs
  ADD COLUMN store_name varchar(100) DEFAULT NULL AFTER ml_connection_id;
```

---

## 6. Composer

```bash
cd /home/u479877971/domains/epracticas.cl/backend-software
php composer install --no-dev --optimize-autoloader
```
