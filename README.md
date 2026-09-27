# Automatizaciones en centro de practica

Sistema web para gestión y automatización de procesos de productos, con integración a tres marketplaces (MercadoLibre, Walmart Chile y Cencosud/Paris.cl), catálogo central de productos, control de precios, auditorías y administración de usuarios. Desarrollado en PHP 8+ con MySQL, arquitectura por capas (Controller → Service → Model → DB).

> **Disclaimer:** Este proyecto fue desarrollado en un período acotado de **2 semanas** como parte de un encargo puntual. Los contribuyentes originales no asumen ninguna responsabilidad por el uso, implementación, modificación o despliegue que terceros hagan del código. Cualquier fuga de información, problema de seguridad, pérdida de datos o incumplimiento normativo derivado de su operación es responsabilidad exclusiva de quien lo administre o del representante legal a cargo. El proyecto se entrega "tal cual", sin garantías de ningún tipo.

---

## Contribuidores

| Persona | Contacto |
|---------|----------|
| Matías Baxman | matiasbaxman@gmail.com |
| Hugo Tapia | hugomctapia@gmail.com |
| Camilo Gonzalez | cg784027@gmail.com |
| Marcos Álvarez E. | mgalvezpinto261@gmail.com |
| Marcos Gálvez | mgalvezpinto261@gmail.com |
| Benjamín Ojeda | be.ojedao@duocuc.cl |
| Freddy Sepulveda | fsepulveda13@gmail.com |
| Luis Anacona | luisfelipe.anacona@gmail.com |
| Ricardo Aravena | aravenavasquez.r@gmail.com |
| Amparo Valdivia | @amparovaldivia (GitHub) |
| Abraham González | abra.gonzalezs@duocuc.cl |
---

## Responsables por módulo

### Matías Baxman — Autenticación, Seguridad, Administración, Integración ML y Core

Autenticación con 2FA, gestión de usuarios, panel de administración, dashboard, configuración, logging de actividad, integración con MercadoLibre (OAuth, sincronización, precios, notificaciones) y framework base.

#### Autenticación y seguridad

| Funcionalidad | Detalle |
|--------------|---------|
| **Login** | Rate-limited (5/15 min), dummy bcrypt hash anti-timing, bloqueo por IP/usuario |
| **2FA** | TOTP via Google Authenticator, QR setup, remember device (7 días por defecto) |
| **Códigos de recuperación** | 10 códigos formato XXXX-XXXX-XXXX, hasheados con bcrypt, regenerables |
| **Sesión** | Idle timeout 30 min, invalidation masiva, regeneración de session_id en login |
| **CSRF** | Token en sesión, validado con hash_equals() en toda mutación |
| **Rate Limiting** | 5 acciones configurables (login, 2FA, import, export, admin) |
| **Headers de seguridad** | X-Frame-Options, CSP, Permissions-Policy, etc. en index.php |

**Archivos:** `AuthController.php`, `AuthService.php`, `UserModel.php`, `RateLimiter.php`, `RateLimitModel.php`

#### Administración de usuarios

CRUD completo de usuarios (solo admin) con roles: `admin`, `logistics`, `sales`, `products`, `user`. Protección contra auto-deshabilitación del admin principal. Búsqueda y filtro por rol. Estado activo/inactivo.

**Archivos:** `UsersController.php`, `UserService.php`

#### Configuración del sistema

- Cambio de contraseña (política: 8+ chars, mayúscula, minúscula, número, símbolo)
- Regenerar códigos de recuperación
- Cerrar sesión en todos los dispositivos
- Admin: editar settings del sistema (retención de logs, threshold stock bajo, chunk size batch)
- Admin: purgar logs antiguos

**Archivos:** `SettingsController.php`, `AdminController.php`, `AppSettingModel.php`

#### Dashboard

Página principal con resumen del sistema: cantidad de usuarios, últimos registros, saludo personalizado por rol.

**Archivos:** `DashboardController.php`, `Views/dashboard/content-index.php`

#### Activity logging

Registro completo de actividad del sistema con 18 tipos de eventos. Cada entrada almacena usuario, acción, descripción, IP, timestamp y severidad (color-coded). Panel admin con tabla paginada, búsqueda en vivo, filtro por acción/usuario, eliminación individual y purge completo. Vista de detalle individual con metadata completa. Chart de actividad semanal en dashboard admin. Integrado en AuthController, MlController, UsersController, SettingsController, AdminController y CLI cron. Auto-purga configurable (default 6 meses).

**Archivos:** `ActivityService.php`, `ActivityLogModel.php`, `TrackActivityTrait.php`, `Views/admin/content-activity-logs.php`, `Views/admin/content-activity-log-detail.php`, `AdminController.php` (activityLogData, deleteLog, purgeAllLogs, activityChartData)

#### Sistema de Correos Electrónicos

Sistema completo de notificaciones por correo con templates HTML renderizados sobre un layout común, logging de todos los envíos y panel de administración.

| Funcionalidad | Detalle |
|---|---|
| **Motor de envío** | `MailService` — renderiza templates con layout, envía vía `mail()` nativo de PHP |
| **Templates** | 7: bienvenida, restablecer contraseña, recordatorio de username, reset por admin, notificación de login, cuenta suspendida, cambio de contraseña |
| **Logging** | Todos los envíos registrados en tabla `email_logs` con destinatario, template, estado, usuario y metadatos |
| **Admin UI** | Vista `/admin/emailLogs` con tabla paginada, búsqueda por email/usuario, filtro por template, indicador visual de éxito/error y purge |
| **User-Agent** | `parseUserAgent()` detecta navegador y SO para notificaciones de login |

**Disparadores:**

| Template | Evento |
|---|---|
| `welcome` | Admin crea un nuevo usuario |
| `password-reset` | Usuario solicita restablecer contraseña |
| `admin-reset` | Admin envía link de restablecimiento |
| `username-reminder` | Usuario solicita recordatorio de username |
| `login-notification` | Login desde IP o dispositivo nuevo |
| `account-suspended` | Admin deshabilita una cuenta |
| `password-changed` | Usuario completa cambio de contraseña |

**Archivos:** `MailService.php`, `EmailLogModel.php`, `Views/emails/` (7 templates + layout), `Views/admin/content-email-logs.php`

#### Procesos Web (MercadoLibre)

Integración completa con la API de MercadoLibre: conexión OAuth, sincronización de productos, gestión de precios masiva, exploración de publicaciones y notificaciones automáticas.

**Conexión OAuth:**
- Flujo completo authorization_code con state firmado (HMAC-SHA256)
- Tokens cifrados con AES-256-CBC (ENCRYPTION_KEY de 64 chars hex)
- Refresco automático de tokens (< 5 min de expiración)
- Manejo de múltiples tiendas por usuario
- Renombrar, desconectar, reactivar y eliminar tiendas

**Sincronización de productos:**
- Escaneo paginado de todos los ítems ML → productos locales
- Extracción de SKU desde atributos o título
- Upsert en tabla products con detección de cambios
- Notificación automática de stock bajo

**Gestión de precios masiva:**
- Subida de archivo XLSX/CSV con SKU + Precio (validado: 10 MB, 5000 filas, MIME)
- Procesamiento por lotes configurable (chunk size + rate limiting ms)
- Reanudable ante cortes (persiste batches en DB)
- Historial de cambios con filtros y auto-purga
- Exportación de resultados y logs a XLSX

**Exploración de publicaciones:**
- Listado paginado con búsqueda y selector de tienda
- Métricas en vivo (total items, activos, stock bajo)
- Exportación a XLSX con 12 campos seleccionables

**Notificaciones automáticas:**
- Stock bajo (threshold configurable, desduplicado)
- Batch de precios completado (éxito/errores)
- Advertencias de importación
- Sincronización completada
- Usuario creado (notificación a admin)

**Archivos involucrados:**
- `app/Controllers/MlController.php` — 38 métodos (conexión, productos, precios, auditorías, exportación)
- `app/Controllers/ConnectionsController.php` — Extiende MlController (dashboard)
- `app/Controllers/NotificationsController.php` — 4 métodos (data, markRead, markAllRead, generate)
- `app/Services/MercadoLibreAuthService.php` — OAuth, cifrado, refresh
- `app/Services/MercadoLibreApiService.php` — API calls con retry (3 intentos, backoff)
- `app/Services/SyncService.php` — Scan + upsert productos
- `app/Services/PriceService.php` — Parseo, procesamiento batch, logging
- `app/Services/NotificationService.php` — 12 métodos de notificación
- `app/Helpers/MlApiHelper.php` — cURL calls a ML API
- `app/Helpers/RateLimiter.php` — Rate limiting configurable
- `app/Views/ml/` — Vistas de dashboard, productos, precios, conexiones, auditorías
- `app/Views/notifications/` — Vistas de notificaciones

**Logros:**
- Tokens OAuth seguros con cifrado AES-256 y refresco automático
- Rate limiting por acción (login, 2FA, import, export, admin)
- Procesamiento de precios reanudable ante cortes de conexión
- Notificaciones en tiempo real con desduplicación
- Sincronización respetando rate limits de ML API (500ms entre batches)
- Detección de estado de ítems (cerrados, bajo revisión, pausados)
- Exportaciones con throttling para no saturar el servidor

#### Framework Core

| Componente | Función |
|-----------|---------|
| `App.php` | Router — URL pattern /controller/method/params, case-insensitive, redirects /ml/* → /connections/* |
| `Controller.php` | Base — view(), db(), requireAuth(), requireAdmin(), checkSessionValidity() |
| `Database.php` | PDO wrapper con FETCH_OBJ, UTC timezone, transacciones |
| `Model.php` | Helpers row(), rows(), value(), execute(), insertGetId() |
| `EnvLoader.php` | Parser de .env |
| `config.php` | Constantes de entorno, DB, OAuth, rate limiting, sesión |

**Archivos:** `app/core/`, `app/config/`, `public_html/index.php`, `.env.example`

---

### Hugo Tapia — Auditoría y exportaciones

Módulo de **auditoría** que permite contrastar datos locales contra la información en vivo de MercadoLibre. El usuario sube un Excel/CSV con SKUs y el sistema consulta la API de ML, compara campo por campo y descarga un reporte XLSX con las diferencias.

**Tipos de auditoría disponibles:**

| Tipo | Compara | Columnas en reporte |
|------|---------|---------------------|
| **Estado** | SKU → estado actual en ML (Activa, Pausada, etc.) | SKU, Item ID, Título, Estado ML, Resultado |
| **Precios** | Precio subido vs precio en ML | SKU, Precio Subido, Precio ML, Item ID, Diferencia $, Diferencia %, Resultado |
| **Stock** | Stock subido vs stock en ML | SKU, Stock Subido, Stock ML, Item ID, Diferencia, Resultado |
| **Títulos** | Título subido vs título en ML | SKU, Título Subido, Título ML, Item ID, Resultado |
| **Descripciones** | Descripción subida vs descripción en ML | SKU, Descripción Subida, Descripción ML, Item ID, Resultado |

**Archivos involucrados:**
- `app/Services/AuditService.php` — Lector de archivos, parser de entradas, comparador, generador de XLSX
- `app/Services/ExportService.php` — Exportación de productos, batches de precios y resultados a XLSX
- `app/Controllers/MlController.php` — Métodos `audit*()`, `compareAudit*()`, `export*()`
- `app/Views/ml/content-audit-*.php` — 7 vistas (hub + 5 tipos + 1 genérica)
- `public_html/assets/css/ml/audit.css` — Estilos propios del módulo

**Logros:**
- Comparación masiva contra API de ML con batching de 20 ítems
- Detección automática de fila de encabezados en archivos subidos
- Parseo inteligente de precios con formato locale (puntos, comas, símbolo $)
- Cálculo de diferencia ($ y %) en auditoría de precios
- Reportes descargables en XLSX con formato profesional
- Throttling de descarga (~2.5 MB/s) para estabilidad
- 5 tipos de auditoría + panel hub con acceso rápido

---

### Camilo Gonzalez y Marcos Álvarez E. — Creación de productos

#### Creación de Productos

Flujo completo para importar productos desde Excel, generar variantes (tallas/colores) y llenar automáticamente plantillas de publicación masiva de MercadoLibre.

**Dos flujos de importación:**

| Flujo | Descripción |
|-------|-------------|
| **Plantilla de variantes** | Descargar plantilla → completar SKU, título, colores/tallas separados por coma → subir → el sistema genera hijos automáticamente |
| **Auto-detect (cualquier Excel)** | Subir cualquier Excel → el sistema detecta columnas automáticamente (30+ sinónimos por campo) |

**Llenado de plantilla MeLi:**
- Analiza la plantilla de ML (multi-pestaña) y detecta ~30 tipos de columnas
- Procesa hoja "Guías de tallas" para asignar códigos de guía por género + talla
- Asigna medidas físicas (peso, alto, ancho, grueso) por matching de categoría en título
- Genera EAN-13 únicos automáticamente
- Escribe todo en la plantilla MeLi y descarga el archivo listo para publicar

**Gestión de medidas:**
- ABM manual de medidas (peso_kg, alto_cm, ancho_cm, grueso_cm)
- Importación masiva vía Excel con detección de columnas
- Matching por substring sobre categoría + título del producto

**Archivos involucrados:**
- `app/Controllers/ProductcreatorController.php` — 17 métodos públicos
- `app/Helpers/ColumnMapper.php` — Detección de columnas, sinónimos, normalización, EAN-13
- `app/Views/productcreator/content-index.php` — Vista única con todas las secciones + manuales desplegables
- `app/Helpers/XlsxWriter.php` / `XlsxReader.php` — Lectura/escritura ligera de XLSX
- `vendor/phpoffice/phpspreadsheet` — Lectura/escritura avanzada (usado en uploadTemplate, fillTemplate, uploadVariants, downloadVariantsTemplate)

**Base de datos:**
- Tabla `products_master` — Productos importados con SKU, título, precio, stock, categoría, marca, género, color, talla
- Tabla `product_medidas` — Medidas físicas por categoría + variante

**Logros:**
- Generación automática de variantes hijo desde productos padre (tallas × colores)
- División inteligente de stock entre variantes
- Auto-detección de columnas en cualquier Excel (header scoring)
- Matching de hojas por similitud de nombre con la categoría del producto
- Asignación de EAN-13 masiva con checksum válido
- Parseo de guías de tallas con fallback por solo talla (sin género)
- Interfaz con buscador JS en medidas, vista previa de variantes, manuales desplegables
- Importación masiva de medidas

---

### Benjamín Ojeda — Página de Información

Rediseño completo de la página de Información del sistema (`/info`), con secciones organizadas, panel plegable con desplegables, integración de iconos SVG, selector de tema (claro/oscuro/auto) y navegación de regreso al dashboard.

**Archivos involucrados:**
- `app/Controllers/InfoController.php` — Controlador que renderiza la vista con datos de branding
- `app/Views/info/index.php` — Vista completa con layout propio, hero, secciones y desplegables
- `public_html/assets/css/info.css` — Estilos específicos de la página

**Logros:**
- Diseño responsivo con navegación propia fuera del layout general
- Secciones colapsables con toggle visual
- Selector de tema integrado con persistencia en localStorage
- Coherencia visual con el branding del sistema

---

### Freddy Sepulveda — Investigación de factibilidad para comparación de precios competitivos

Realizó el análisis técnico de factibilidad para un módulo de comparación automatizada de precios entre publicaciones propias y posibles competidores en MercadoLibre. El objetivo inicial era evaluar si la API permitía identificar productos equivalentes, obtener precios comparables y generar reportes útiles para apoyar decisiones comerciales.

Durante la investigación se revisó documentación oficial de MercadoLibre Developers y se realizaron pruebas con scripts para consultar publicaciones propias, buscar posibles competidores, validar SKU, atributos, categorías, catálogo e identificadores de producto.

El análisis concluyó que la comparación automática exacta presenta baja factibilidad práctica, ya que MercadoLibre no entrega una relación directa, general y confiable entre productos propios y publicaciones competidoras equivalentes. La principal dificultad no fue obtener precios, sino validar que los productos comparados fueran realmente equivalentes.

**Estado:** El módulo no se integró como funcionalidad productiva, pero la investigación permitió identificar una limitación técnica y comercial relevante, evitando avanzar con una automatización poco confiable y recomendando alternativas más realistas como monitoreo referencial, validación humana y comparación limitada a productos correctamente catalogados.

**Aportes principales:**
- Evaluación técnica de la API de MercadoLibre para comparación competitiva.
- Pruebas de búsqueda por título, categoría, SKU, atributos e identificadores.
- Detección de riesgos por falsos positivos y datos incompletos.
- Elaboración de informe de factibilidad técnica.
- Recomendación de enfoque alternativo: monitoreo referencial con validación humana.

---

### Luis Anacona — Módulo base Walmart (conexión y catálogo)

Primera versión de la integración con Walmart Chile Marketplace: conexión con la API, sincronización de catálogo, listado de productos y actualización masiva de precios/stock. Esta base es la que Ricardo Aravena extendió posteriormente (ver sección siguiente) con auditorías, reportes y el resto de funcionalidades listadas ahí.

#### Conexión y sincronización

| Funcionalidad | Detalle |
|---|---|
| **Conexión API** | Credenciales `client_id`/`client_secret` por tienda (OAuth2 client_credentials, token de 15 min obtenido on-demand), múltiples tiendas por usuario |
| **Gestión de tiendas** | Renombrar, desconectar, reactivar y eliminar, prueba de conexión (`testConnection`) |
| **Sincronización de catálogo** | Escaneo paginado de Items API + enriquecimiento de stock vía Inventory API (fallback troceado por SKU si la cuenta no tiene acceso paginado), reanudable ante cortes (persiste cursor en `walmart_sync_status`, no en sesión) |
| **Modo dual** | UI (polling desde el navegador, `syncStart`/`syncChunk`) y CLI (`walmart_sync_products.php`, una sola pasada por cron) |

**Archivos:** `WalmartController.php`, `WalmartApiService.php`, `WalmartSyncService.php`, `WalmartAuthService.php`, `WalmartConnectionModel.php`, `WalmartProductsCacheModel.php`, `WalmartSyncStatusModel.php`

#### Gestión de productos y actualización masiva

- Listado de catálogo sincronizado con búsqueda, filtro por estado y selector de tienda
- Actualización masiva de precios y stock vía Excel/CSV, procesamiento por lotes reanudable (persiste batch en DB, igual patrón que `PriceService` de ML)
- Historial de actualizaciones con detalle por lote

**Archivos:** `WalmartUpdateService.php`, `Views/walmart/content-products.php`, `content-updates.php`, `content-update-history.php`

**Base de datos:** `walmart_connections`, `walmart_products_cache`, `walmart_sync_status`, `walmart_update_batches`, `walmart_update_logs`

---

### Ricardo Aravena — Integración Walmart

Extensión del módulo base de Walmart (ver sección de Luis Anacona arriba) con auditoría, ficha de detalle de producto, reportes cruzados de canal, agrupación inteligente de SKU y reporte de ventas — sin modificar el módulo ML original.

#### Auditorías Walmart

Módulo de auditoría con paridad funcional respecto al ya existente para ML (`AuditService`), adaptado a las particularidades del catálogo Walmart y con dos mejoras propias:

| Tipo | Compara | Particularidad |
|------|---------|------|
| **Precios** | Precio subido vs. caché local (`walmart_products_cache`) | Comparación contra caché en vez de la API en vivo — auditar miles de SKU es una sola consulta, no una llamada HTTP por fila |
| **Stock** | Stock subido vs. caché local | Mismo mecanismo que precios |
| **Verificación en vivo** | Bajo demanda, solo sobre filas con diferencia | Consulta puntual a la API de Walmart (`getItem`/`getInventory`) sin gastar cuota en toda la auditoría |
| **Coincidencia por código corto** | Cuando el SKU subido no incluye el color (ej. `300-1-L` en vez de `RIP-300-1-AM-L`) | Se deriva el código sin color desde el catálogo real y se compara contra eso; si dos productos compiten por el mismo código corto se marca `Ambiguo` en vez de adivinar |

Cada fila de resultado registra explícitamente el método de coincidencia usado (`exact` / `shortcode` / `fuzzy` / `ambiguous`) en la tabla `auditorias_walmart`, visible tanto en pantalla como en el Excel exportado.

**Archivos:** `WalmartAuditService.php`, métodos `audit*()`/`compareAudit*()`/`auditVerifyLive()` en `WalmartController.php`, `Views/walmart/content-audit-*.php` (hub, form, history, batch-detail)

#### Exportación de catálogo

Volcado directo de `walmart_products_cache` a Excel (precio o stock), sin comparar contra ningún archivo — a diferencia de la auditoría. Botón manual desde el panel de auditorías y generación automática al finalizar cada sincronización.

**Archivos:** `WalmartExportService.php`

#### Ficha de detalle de producto

Pantalla individual por producto (antes solo existía el listado): información completa, otras publicaciones del mismo producto (mismo título, distinta talla/color) y familia de SKU relacionada.

**Archivos:** `content-product-detail.php`, método `productDetail()` en `WalmartController.php`, `getProduct()`/`getSiblingsByTitle()` en `WalmartProductsCacheModel.php`

#### Agrupación de familias de SKU (individual / pack / tripack)

El catálogo vende el mismo producto en distintas presentaciones (`A-201-ROJ-S` individual, `PACK-A-201-ROJ-AZU-S`, `TRIPACK-A-201-ROJ-AZU-VER-S`). Se construyó resolución automática por patrón (quita prefijo de presentación, despega talla y N colores usando un diccionario editable) más una tabla de excepciones manuales para los casos que el patrón no logra resolver.

**Base de datos:** `sku_family_map` (sku → base_sku, tipo de variante, origen auto/manual), `sku_color_codes` (diccionario de abreviaturas de color, editable sin tocar código)

**Archivos:** `SkuFamilyService.php`, `app/cli/rebuild_sku_families.php`

#### Reportes (cross-canal ML + Walmart, incluye Ventas)

Sección nueva e independiente de Walmart en sí — cuatro reportes: inventario por tienda (ambos canales), resumen histórico de auditorías (con detección de SKU con diferencias recurrentes), snapshot de precios del catálogo completo, y ventas Walmart (por tienda, producto y día) obtenidas desde la Orders API de Walmart.

**Archivos:** `ReportsController.php`, `ReportService.php`, `Views/reports/`, `WalmartOrderModel.php`, `WalmartOrderSyncService.php`, `app/cli/walmart_sync_orders.php`

#### Traducción de categorías de producto

Walmart entrega la categoría del producto (`product_type`) en inglés. Se agregó una traducción a español respaldada en una tabla editable (`walmart_product_type_es`), visible en la ficha de detalle y en el listado de productos.

**Archivos:** `WalmartProductTypeService.php`

#### Base de datos

Tablas nuevas: `auditorias_walmart`, `sku_family_map`, `sku_color_codes`, `walmart_orders`, `walmart_order_lines`, `walmart_product_type_es` (las de conexión/catálogo base — `walmart_connections`, `walmart_products_cache`, etc. — se listan en la sección de Luis Anacona).

> **Nota de cierre:** durante el desarrollo se evaluaron también un panel de salud de conexión API y un sistema de notificaciones para Walmart (columna `notifications.walmart_connection_id` incluida). Se decidió en conjunto con el equipo no continuar con esos dos puntos; el código correspondiente fue revertido y no forma parte del estado final de este módulo.

**Logros:**
- Extensión del módulo Walmart existente (auditoría, ficha de producto, reportes, ventas) sin modificar una sola línea del módulo ML ni del módulo base de Walmart
- Auditoría contra caché local en vez de API en vivo — reduce drásticamente las llamadas HTTP necesarias para auditar catálogos grandes
- Resolución de coincidencia por código corto y por familia de SKU sin depender de una relación padre-hijo explícita en la base de datos original
- Transparencia total del método de coincidencia usado en cada auditoría (nunca se aplica un valor a ciegas ante ambigüedad)
- Integración de la Orders API de Walmart con parseo defensivo (fechas, precios y estados en múltiples formas posibles) y respaldo del JSON crudo por si la forma real difiere de la esperada
- Reportes que cruzan ambos canales de venta en una sola vista

---

### Luis Anacona — Catálogo Central de Productos (Products Central)

Base de datos central de productos: una copia limpia, normalizada y deduplicada del catálogo de MercadoLibre, pensada como paso previo a que el sistema deje de ser un espejo de los marketplaces y pase a ser la fuente de verdad.

#### Espejo y limpieza del catálogo

`ProductsCentralModel::mirrorFromMlCache($connectionId)` borra y reinserta en una transacción las filas de esa conexión a partir de `ml_products_cache`, pasando cada fila por `cleanCacheRow()`. Se dispara al final de cada sync ML desde `MlSyncService::finishSync()` y `runFullSync()`; si el espejo falla se loguea pero no interrumpe el sync (el dato ya quedó completo en la caché).

| Regla de limpieza | Criterio |
|---|---|
| **Título** | Trim, colapso de espacios/tabs/saltos, decodificación de entidades HTML, quita tags. No toca mayúsculas ni acentos, para no fusionar productos distintos con nombres parecidos |
| **SKU** | Trim, colapso de espacios, mayúsculas; placeholders (`-`, `N/A`, `0`) se tratan como `NULL` |
| **Descarte** | Solo si la fila queda sin título **y** sin SKU — no hay forma de mostrarla ni buscarla |
| **Valores negativos** | `price` / `available_quantity` / `sold_quantity` corruptos se llevan a 0 en vez de descartar el producto |
| **Nunca fusiona por título** | Cada `ml_item_id` genera como máximo una fila propia, aunque comparta título exacto con su padre u otras variantes |

#### Segmentación de SKU y familias

Los vendedores arman SKUs como `A-201-ROJ-S` (individual), `PACK-A-201-ROJ-AZU-S` y `TRIPACK-A-201-ROJ-AZU-VER-S`. Se agregaron columnas derivadas (`sku_pack_type`, `sku_base`, `family_key`) que permiten agrupar un pack con su SKU base sin tocar el dato crudo ni inventar campos que el patrón no garantiza.

#### Cross-links entre canales

Vinculación manual y asistida de productos que son el mismo ítem en distintas tiendas o canales, sin depender de que compartan SKU. Incluye búsqueda, alta y baja de miembros, sugerencias automáticas de coincidencia y descarte permanente de sugerencias erróneas (para que no reaparezcan).

**Tablas:** `product_cross_links`, `product_cross_link_members`, `product_cross_link_dismissed`

#### Descripciones y métricas

- `ProductDescriptionService` completa descripciones faltantes por lotes (`processNextChunk()`), con estadísticas de avance consultables desde la UI
- `getGlobalMetrics()` entrega los KPIs **deduplicados por título** que alimentan el panel de inicio — no la suma cruda por tienda, que contaría el mismo producto una vez por cada tienda donde está publicado
- `products_central_title_stats` cachea el conteo por título; `verifyAgainstCache()` permite contrastar el espejo contra la caché de origen

**Archivos:** `ProductcentralController.php`, `ProductsCentralModel.php`, `ProductCrossLinkModel.php`, `ProductDescriptionService.php`, `Views/productcentral/content-index.php` (más `app/cli/espejar_products_central.php`, aún sin versionar)

**Base de datos:** `products_central`, `products_central_title_stats`, `product_cross_links`, `product_cross_link_members`, `product_cross_link_dismissed`

**Documentación propia:** `Docs/database/PRODUCTS-CENTRAL-SKU-SEGMENTACION.md`, `PRODUCTS-CENTRAL-LIMPIEZA-CASOS-CONFLICTIVOS.md`, `PRODUCTS-CENTRAL-DESCRIPTIONS.md`

**Logros:**
- Catálogo unificado y limpio sin alterar el dato crudo de `ml_products_cache`, que sigue siendo la fuente
- Criterios de limpieza explícitos y documentados caso a caso, incluidos los conflictivos
- Deduplicación por título que corrige el conteo inflado que producía la suma por tienda
- Agrupación de packs y familias por patrón, sin exigir una relación padre-hijo en la base original

---

### Amparo Valdivia y Abraham González — Cencosud Marketplace y abstracción multi-marketplace

Integración con Cencosud (Paris.cl) y primera capa de abstracción para que el sistema deje de estar acoplado a un marketplace concreto. Desarrollado en la rama `amparo2`, que integra a su vez la rama `abraham_cencosud`.

#### Abraham González — Base del módulo y abstracción

| Aporte | Detalle |
|---|---|
| **Módulo Cencosud inicial** | Controlador, modelos de conexión/caché/estado/órdenes, servicios de API y autenticación, y las vistas de conexiones y pedidos |
| **Abstracción multi-marketplace** | `MarketplaceInterface` con contrato común (productos, guardar, borrar, stock, sync, órdenes) más `MarketplaceFactory` y los adaptadores `CencosudAdapter` y `MercadoLibreAdapter` |
| **Sincronización bidireccional de stock** | Réplica de stock por SKU entre MercadoLibre y Cencosud, comparando delta para evitar peticiones HTTP redundantes |
| **Regla de stock crítico** | Si el stock real disponible en ML es **≤ 5 unidades**, el stock en Cencosud se fuerza a 0 automáticamente para evitar quiebres y sobreventa. Cada aplicación de la regla genera una notificación (`notifyStockSyncProtection`) |

**Archivos:** `MarketplaceInterface.php`, `MarketplaceFactory.php`, `CencosudAdapter.php`, `MercadoLibreAdapter.php`, `CencosudApiService.php`, `CencosudAuthService.php`, `CencosudApiHelper.php`

#### Amparo Valdivia — Integración, reportes y despliegue

| Aporte | Detalle |
|---|---|
| **Precios y stock** | Actualización masiva por lotes con historial por ítem, siguiendo el mismo patrón reanudable de ML y Walmart |
| **Reportes Cencosud** | Panel de reportes con exportación a XLSX, filtrado de stock alto y consulta de dimensiones del producto contra el maestro |
| **Aviso de stock alto** | Envío de reporte por correo (`Views/emails/high_stock_report.php`) |
| **Ficha y sincronización puntual** | Búsqueda contra el maestro de productos y guardado con sincronización inmediata (`searchMasterProduct`, `saveAndSyncProduct`) |
| **Caché de órdenes** | Tabla `cencosud_orders_cache` con órdenes, subórdenes de despacho y respaldo del JSON crudo |
| **Arquitectura de despliegue** | Consolidación del despliegue en Hostinger con diagrama Mermaid, y `$baseDir` en `public_html/index.php` para que el mismo código funcione en local y en el layout de producción (`backend-software` fuera del docroot) |
| **Notificaciones** | Aviso de ficha SKU procesada con valores de respaldo cuando faltan atributos en el maestro (`notifyProductFallbackWarning`) |
| **Cifrado** | `CryptoHelper` para el manejo de secretos por conexión |

**Documentación aportada:** `INFORME_TECNICO_INTEGRACION_CENCOSUD.md`, `REPORTE_DESARROLLO_CENCOSUD.md`, `Docs/CERTIFICACION_CENCOSUD_SANDBOX.md`, `Docs/PLAN_DE_PRUEBAS_INTEGRACION.md`, `Docs/DESPLIEGUE_HOSTINGER.md`

**Base de datos:** `cencosud_connections`, `cencosud_products_cache`, `cencosud_sync_status`, `cencosud_update_batches`, `cencosud_update_logs`, `cencosud_orders_cache`

**Logros:**
- Tercer marketplace integrado reutilizando los patrones ya probados en ML y Walmart (sync reanudable con cursor persistido, lotes de actualización, caché local)
- Primera abstracción real de marketplace del proyecto, que abre la puerta a agregar canales sin duplicar controladores
- Regla de protección de stock que prioriza no sobrevender por sobre maximizar publicación
- Documentación de certificación en sandbox y plan de pruebas, poco habitual en el resto de los módulos

---

## Integración de ramas y estado actual

El trabajo de los últimos módulos se desarrolló en ramas paralelas que partieron de puntos distintos del historial. Ambas se integraron sobre `luis_anacona`.

### `origin/ricardo` → `luis_anacona` (commit `b12fbf5`)

9 commits, 39 archivos, +4245 líneas. Base común `ffc0eb0`, sin conflictos: las auditorías Walmart, reportes, órdenes y familias de SKU entraron limpias sobre el módulo base.

### `origin/amparo2` → `luis_anacona` (commit `2b3218c`)

13 commits de Amparo y 2 de Abraham. La base común fue `02606ea`, **anterior a la integración Walmart**, por lo que la rama traía su propia copia más antigua de esos archivos y el merge produjo 59 conflictos. Resolución aplicada:

| Conflicto | Decisión |
|---|---|
| Walmart (controlador, servicios API/sync, modelo de caché, vista de productos) | Se conservó la versión de `luis_anacona`. La de `amparo2` era anterior: el controlador tenía 684 líneas contra 1370, con un diff de 2 añadidas y 688 borradas |
| `node_modules/` | 45 de los 59 conflictos. Se mantuvo el estado previo del repositorio (ver *Próximos pasos*) |
| Menú lateral | Se sumaron Cencosud y Gestión Cencosud junto a Reportes y Walmart. Se descartó un segundo menú Cencosud duplicado que apuntaba a `/cencosud/connections` y `/cencosud/orders`, rutas sin método en el controlador |
| `DashboardController` | El automerge había eliminado en silencio el bloque que define `$filterConnId`, dejando la variable indefinida. Se recuperó, se extendió a tiendas Cencosud y se mantuvieron los KPIs deduplicados por título |
| `SCHEMA.sql` | Los dos archivos son dumps incompatibles (`CREATE TABLE` vs `CREATE TABLE IF NOT EXISTS`, distinto orden) y el automerge mezcló la cola de una tabla dentro de otra. Se reconstruyó desde el dump de `luis_anacona` más la sección Cencosud |
| Autoload de Composer | Unión de ambos lados, menos la entrada huérfana `MetricsController` (archivo inexistente tras la limpieza de métricas) |
| `config.php` | Se añadió `DB_PORT` (requerido por el nuevo `Database.php`), con `3306` por defecto en vez del `3307` del entorno Docker de la rama |
| `.env`, `.DS_Store`, temporales de Office | Quedaron fuera del merge |

**Correcciones aplicadas durante la integración:**

- `MercadoLibreAdapter.php` tenía un bloque de código fuera de todo método que provocaba un *parse error*, y no implementaba `getOrders()` pese a declarar `MarketplaceInterface`. Se corrigieron ambos.
- El closure de `set_exception_handler` en `public_html/index.php` usaba `$baseDir` sin `use ($baseDir)`, por lo que la página de error 500 en producción no habría encontrado la ruta.

### Migraciones aplicadas

Tras los merges se aplicaron 6 migraciones, en este orden por dependencia:

```
MIGRATION-2026-07-15-WALMART-AUDIT.sql        → auditorias_walmart
MIGRATION-2026-07-21-AUDIT-MATCH-METHOD.sql   → columnas matched_sku, match_method
MIGRATION-2026-07-17-SKU-FAMILY.sql           → sku_color_codes, sku_family_map
MIGRATION-2026-07-21-PRODUCT-TYPE-ES.sql      → walmart_product_type_es
MIGRATION-2026-07-24-WALMART-ORDERS.sql       → walmart_orders, walmart_order_lines
MIGRATION-2026-07-14-CENCOSUD.sql             → las 6 tablas cencosud_*
```

`cencosud_migration.sql` es un duplicado de la migración Cencosud canónica y no debe aplicarse además de ella. `MIGRACION-2026-08-28-BASE-COMPLETA-PRACTICAS.sql` es un consolidado para el entorno de prácticas y reemplaza a las migraciones sueltas: tampoco se aplica junto a ellas.

---

## Próximos pasos recomendados

Ordenados por relación entre impacto y esfuerzo. Los tres primeros salieron de problemas concretos detectados durante la integración.

### 1. Sacar `node_modules/` y `vendor/` del control de versiones

Hoy hay **805 archivos de `node_modules` versionados**, y fueron el origen de 45 de los 59 conflictos del último merge. Son dependencias reproducibles desde `package.json` y `composer.json`.

```bash
git rm -r --cached node_modules vendor
# añadir /node_modules/ y /vendor/ a .gitignore
# documentar en el README: npm install && composer install
```

Requiere coordinar con el equipo, porque quien haga `pull` después necesitará instalar dependencias por su cuenta. Mientras sigan versionados, **no** conviene añadirlos a `.gitignore`: un archivo ya rastreado que además figura como ignorado es una trampa silenciosa.

### 2. Adoptar un registro de migraciones aplicadas

No existe tabla de control: hoy saber qué migración corrió en qué entorno exige inspeccionar el esquema a mano. Una tabla `schema_migrations` con el nombre del archivo y la fecha, más un script que aplique solo las pendientes, elimina toda una clase de errores — incluido el riesgo de correr dos veces un `ALTER` no idempotente como `MIGRATION-2026-07-21-AUDIT-MATCH-METHOD.sql`.

### 3. Cablear la abstracción multi-marketplace

`MarketplaceInterface`, `MarketplaceFactory` y los adaptadores están implementados pero **ningún controlador los usa todavía**. Migrar `CencosudController` y `WalmartController` a la fábrica es lo que convierte la abstracción en algo útil; mientras tanto es código muerto que puede desincronizarse del resto.

### 4. Completar el filtrado del panel por tienda Cencosud

`ProductsCentralModel::getGlobalMetrics()` solo filtra por conexión de MercadoLibre. Al seleccionar una tienda Cencosud en el panel de inicio, los KPIs siguen mostrando el total global. El punto está marcado con un comentario en `DashboardController`.

### 5. Revisar cuentas de usuario

- `wmtest_temp` es una cuenta de pruebas con rol `admin` **activa** y sin uso desde julio. Conviene desactivarla o eliminarla.
- En producción, la cuenta genérica `admin` está `inactive` desde junio, mientras el equipo entra con cuentas nominales. Si la desactivación fue deliberada, vale documentarlo para que nadie la reactive por error.

### 6. Corregir `CencosudSyncStatusModel::get()`

Es un método de lectura que **inserta** una fila si no la encuentra, por lo que revienta con violación de clave foránea si se lo llama con un `connectionId` inexistente. Desde el panel es inofensivo (solo se lo invoca iterando conexiones reales), pero es una trampa para cualquier código futuro.

### 7. Unificar colaciones de la base

Conviven `utf8mb4_general_ci` y `utf8mb4_unicode_ci`. Un `JOIN` directo entre columnas de texto de tablas con distinta colación puede fallar. El detalle está en `Docs/database/01-MODULOS.md`.

### 8. Higiene del repositorio

- Existen las ramas `Ricardo` y `ricardo`, que apuntan al mismo commit: son la misma rama duplicada por mayúsculas. Conviene borrar una.
- `.env` llegó a versionarse en alguna rama. Añadir un `.env.example` con las claves pero sin valores, y verificar que `.env` esté ignorado en todas las ramas activas.
- Hay cuatro copias del backend en el servidor (`backend-software`, `-prueba`, `-backup-`, `-bak-abraham`). Conviene consolidar y borrar las que ya no se usen.

### 9. Cobertura de pruebas

El proyecto no tiene pruebas automatizadas. Los puntos de mayor retorno serían las reglas con lógica no trivial y consecuencias en dinero o inventario: la limpieza de `cleanCacheRow()`, la segmentación de SKU, la regla de stock crítico ≤ 5 y la coincidencia por código corto de las auditorías.

---

## Despliegue en Producción

La rama [`prod`](https://github.com/matiasbaxman/e-practicas-software/tree/prod) contiene el proyecto con rutas y configuración ajustadas para Hostinger (subdominio `software.epracticas.cl`).

### Estructura en Hostinger

Las carpetas del backend (`app/`, `vendor/`, etc.) quedan **fuera del web root** por seguridad. Solo lo público va dentro del document root del subdominio.

```
/home/usuario/
├── public_html/
│   └── software/              ← Document root (software.epracticas.cl)
│       ├── .htaccess          ← URL rewriting + HTTPS + security headers
│       ├── index.php          ← Entry point (require_once apunta a backend-software/)
│       ├── assets/
│       │   ├── css/dist/      ← tailwind.css compilado
│       │   ├── js/
│       │   ├── icons/
│       │   └── img/
│       └── uploads/
│           └── .htaccess      ← Bloquea ejecución PHP
├── backend-software/          ← PRIVADO (fuera del web root)
│   ├── .env                   ← Valores de producción
│   ├── app/                   ← Controllers, Models, Services, Views, core, config
│   ├── vendor/                ← Dependencias Composer
│   ├── composer.json
│   └── composer.lock
```

### Cambios incluidos en la rama `prod`

| Archivo | Diferencia vs `main` |
|---|---|
| `public_html/index.php` | `dirname(__DIR__)` → `dirname(__DIR__, 2) . '/backend-software/...'` |
| `app/config/config.php` | Ruta CSS cache busting apunta a `public_html/software/` |
| `public_html/.htaccess` | HTTPS redirect habilitado |
| `.env.example` | Valores de referencia para producción (`URLROOT`, `APP_ENV`, `ML_REDIRECT_URI`, DB) |

### Pasos para desplegar

```bash
# 1. Clonar rama prod
git clone -b prod https://github.com/matiasbaxman/e-practicas-software.git

# 2. Compilar CSS (ejecutar local antes de subir)
npm install && npm run build:prod

# 3. Subir por FTP / File Manager:
#    public_html/*        → public_html/software/
#    app/ vendor/ etc.    → backend-software/

# 4. Crear backend-software/.env con credenciales reales
#    (usar .env.example como referencia)

# 5. Importar base de datos
#    Docs/database/SCHEMA.sql → MySQL en Hostinger

# 6. Instalar dependencias (en el servidor, dentro de backend-software/)
composer install --no-dev
```

### Cron Jobs (hPanel → Advanced → Cron Jobs)

```bash
# Sincronización ML — cada 15 minutos
php /home/u123456789/backend-software/app/cli/sync_products.php

# Refresco de tokens ML — cada 4 horas
php /home/u123456789/backend-software/app/cli/refresh_tokens.php

# Sincronización Walmart — cada 30 minutos
php /home/u123456789/backend-software/app/cli/walmart_sync_products.php

# Reconstrucción de familias de SKU — cada pocas horas
php /home/u123456789/backend-software/app/cli/rebuild_sku_families.php

# Sincronización de órdenes/ventas Walmart — cada 2-4 horas
php /home/u123456789/backend-software/app/cli/walmart_sync_orders.php
# Sincronización Cencosud — cada 10 minutos
php /home/u123456789/backend-software/app/cli/cencosud_sync_products.php
```

### Consideraciones

- **PHP**: Versión 8.0+, extensiones `pdo_mysql`, `mbstring`, `openssl`, `zip`, `xml`
- **MySQL**: Crear BD y usuario desde hPanel, importar schema, actualizar `.env`
- **Permisos**: `backend-software/app/temp/` debe ser escribible por PHP
- **Email**: El servidor Hostinger debe permitir `mail()`. Si no, reemplazar `MailService` por SMTP
- **Assets**: Los CSS/JS son estáticos y van en `public_html/software/assets/`

---

## Arquitectura General

```
.htaccess (raíz)                ← Apache: bloquea app/, vendor/, .env; redirige a public_html/
  └─ public_html/.htaccess      ← Apache: rewrite si no es un archivo → index.php?url=$1
       └─ public_html/index.php ← Entry point PHP (autoloader, config, handlers, session)
            └─ app/core/App.php ← Router (parsea URL, resuelve Controller/Method)
                 └─ Controllers/         ← HTTP handling
                      ├─ AuthController.php
                      ├─ DashboardController.php
                      ├─ MlController.php (+ ConnectionsController.php)
                      ├─ WalmartController.php
                      ├─ ReportsController.php
                      ├─ CencosudController.php
                      ├─ NotificationsController.php
                      ├─ ProductcreatorController.php
                      ├─ ProductsController.php
                      ├─ WebproductsController.php
                      ├─ UsersController.php
                      ├─ AdminController.php
                      ├─ SettingsController.php
                      └─ InfoController.php
                 └─ Services/             ← Business logic
                      ├─ AuthService.php
                      ├─ MercadoLibreAuthService.php
                      ├─ MercadoLibreApiService.php
                      ├─ SyncService.php
                      ├─ PriceService.php
                      ├─ CencosudAuthService.php
                      ├─ CencosudApiService.php
                      ├─ CencosudSyncService.php
                      ├─ CencosudUpdateService.php
                      ├─ ProductService.php
                      ├─ ExportService.php
                      ├─ AuditService.php
                      ├─ WalmartApiService.php
                      ├─ WalmartAuthService.php
                      ├─ WalmartSyncService.php
                      ├─ WalmartUpdateService.php
                      ├─ WalmartAuditService.php
                      ├─ WalmartExportService.php
                      ├─ WalmartOrderSyncService.php
                      ├─ WalmartProductTypeService.php
                      ├─ SkuFamilyService.php
                      ├─ ReportService.php
                      ├─ NotificationService.php
                      ├─ UserService.php
                      └─ ActivityService.php
                 └─ Models/               ← Data access (PDO)
                      ├─ CencosudConnectionModel.php
                      ├─ CencosudProductsCacheModel.php
                      ├─ CencosudSyncStatusModel.php
                      ├─ CencosudBatchModel.php
                      ├─ CencosudUpdateLogModel.php
                      ├─ CencosudOrdersCacheModel.php
                      └─ ...
                 └─ Helpers/              ← Utilities (ColumnMapper, MlApiHelper, MlApiRateLimiter,
                      CencosudApiHelper, CencosudApiRateLimiter, RateLimiter, XlsxWriter, XlsxReader,
                      IconHelper, WalmartProductTypeTranslator)
                 └─ libs/                 ← GoogleAuthenticator.php
                 └─ Views/                ← HTML templates (incluye app/Views/cencosud/)
                 └─ core/                 ← Framework (App, Controller, Database, Model, EnvLoader)
                 └─ config/               ← config.php
                 └─ temp/                 ← Archivos temporales
```

### Reglas de capas
- **Controller** → Lee input, renderiza vistas. NO SQL directo, NO lógica de negocio.
- **Service** → Orquesta reglas de negocio, llama a Models y Helpers. NO toca $_SESSION/$_GET/$_POST, NO hace echo/header, NO SQL directo.
- **Model** → Consultas SQL, CRUD por tabla. NO lógica de negocio, NO dependencias HTTP.
- **Helper** → Funciones utilitarias sin estado. NO depende de otras capas.
