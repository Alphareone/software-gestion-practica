# 🚀 Informe Técnico y Capacidades del Módulo Cencosud Marketplace
**Documento de Arquitectura y Especificación Técnica**  
**Proyecto:** Sistema de Gestión — Módulo Cencosud Marketplace (Paris.cl)  
**Preparado para:** Equipo de Desarrollo y Operaciones

---

## 📌 Resumen Ejecutivo
El presente módulo de integración con **Cencosud Marketplace (Paris.cl)** no es solo un conector de consultas básicas, sino una **Plataforma Empresarial de Integración Robusta, Segura y Autoremediable**. Ha sido construida para operar sin interrupciones tanto en **Ambiente de Pruebas (Staging)** como en **Producción**, garantizando la aprobación de fichas técnicas en paris.cl, protegiendo a la empresa de demandas legales por errores de precios (SERNAC) y asegurando el cálculo tarifario de despachos logísticos.

---

## 🏗️ Diagrama de Arquitectura y Despliegue en Producción (Hostinger)

```mermaid
graph TD
    Client["💻 Cliente / Navegador Web"] -->|HTTPS / GET / POST| HostingerPublic["🌐 Hostinger Public HTML (public_html/index.php)"]
    HostingerPublic -->|Resolución Dinámica $baseDir| BackendCore["⚙️ Backend Core (backend-software/app/)"]
    
    subgraph Servidor Global Hostinger (LiteSpeed / PHP-FPM)
        BackendCore --> Auth["🔒 CencosudAuthService (AES-256)"]
        BackendCore --> ApiService["📡 CencosudApiService"]
        BackendCore --> UpdateService["🛡️ CencosudUpdateService (Protección SERNAC)"]
        BackendCore --> SyncMl["🔄 Sincronización Bidireccional Stock ML"]
    end

    ApiService -->|cURL Directo / HTTP Socket| CencoAPI["🏬 API Cencosud Paris.cl (api.cencosud.cl)"]
    SyncMl <-->|PDO MySQL| DB[("🗄️ Base de Datos MariaDB (gestion)")]
    Auth <-->|PDO MySQL| DB
```

---

## 🛠️ Matriz de Capacidades del Sistema

### 1. 👕 Ficha Técnica Completa y Atributos de Variantes (`POST /v2/products` & `PATCH /v2/products/{id}`)
* **Cumplimiento de Auditoría Cencosud:** Genera payloads JSON enriquecidos separando adecuadamente los atributos de nivel producto de los atributos de nivel variante:
  * **Nivel Producto:** Marca, Categoría, Descripción, Material, Origen, Instrucciones de Cuidado.
  * **Nivel Variante:** Color Comercial, Talla, Género, Fotografías de alta resolución (`medias`).
* **Sincronización Total:** Garantiza que los productos no queden en estado `REJECTED` ni retenidos en la revisión nocturna de Paris.cl.

---

### 2. 📦 Medidas Físicas y Cálculo Tarifario de Despacho (Courier Envíame / Paris)
* **Mapeo de 4 Parámetros Logísticos:**
  * **Peso Real:** `peso_kg`
  * **Alto:** `alto_cm`
  * **Ancho:** `ancho_cm`
  * **Profundidad / Largo:** `grueso_cm`
* **Cálculo Automático de Peso Volumétrico:**
  $$\text{Peso Volumétrico (kg)} = \frac{\text{Alto (cm)} \times \text{Ancho (cm)} \times \text{Profundidad (cm)}}{4000}$$
* **Costo de Envío Garantizado:** La interfaz y el servicio determinan automáticamente el tramo tarifario exacto cobrado por el courier (`Max(Peso Real, Peso Volumétrico)`), evitando rechazos en la generación de etiquetas de despacho.

---

### 3. 🧩 Agrupación Inteligente por Expresiones Regulares (Unidades, Packs y Tripacks)
* **Consolidación de Inventario:** Mediante la expresión regular MySQL `^(TRIPACK-|PACK-)?SKU_BASE(-|$)`, el sistema vincula automáticamente las presentaciones de **Unidad**, **Pack** y **Tripack** al producto base en bodega.
* **Control de Stock:** Previene la duplicación de SKUs y garantiza que las ventas de packs descuenten de forma transparente las unidades reales almacenadas.

---

### 4. 🛡️ Protección de Precios Anti-Error (Prevención de Riesgo SERNAC)
* **Piso Mínimo Anti-Error:** Bloquea cualquier intento de publicar o actualizar un precio igual o inferior a **$500 CLP**.
* **Detección de Caídas Drásticas (>70% Desc.):** Si se detecta un precio con más de un 70% de descuento respecto al precio maestro en `products_master`, la actualización se congela antes de llegar a Cencosud.
* **Sistema de Notificación Inmediata:** Dispara una alerta instantánea a la **Campanita** (`NotificationService`) con detalles del producto para revisión del administrador.

---

### 5. 🧹 Autorremediación Inteligente, Sanitización y Fallbacks
* **Fuente Primaria de Verdad:** Compara siempre `extra_data` contra la tabla maestra `products_master`.
* **Sanitización de Datos Corruptos:** Limpia cadenas vacías, espacios nulos y valores erróneos (`null`, `undefined`, `"[object Object]"`).
* **Valores de Respaldo Garantizados (Fallbacks):** Si una ficha viene incompleta, asigna automáticamente valores seguros (*Genérica*, *Único*, *Estándar*, *Unisex*) para que la API de Cencosud apruebe el envío sin retornar `400 Bad Request`.
* **Aviso a la Campanita:** Registra una alerta de advertencia para completar la ficha en el Maestro sin detener la operación comercial.

---

### 6. 🔒 Seguridad de Credenciales y Resiliencia de Red
* **Cifrado de Alta Seguridad:** Todos los Client Secret y Access Tokens OAuth 2.0 / JWT (con vigencia de 4 horas / 14.400s) se almacenan cifrados con AES-256 (`CryptoHelper::encrypt()`).
* **Verificación de Estado Activo:** Antes de emitir cualquier petición, valida que la conexión esté habilitada en la base de datos (`is_active = 1`).
* **Control de Rate Limit:** Cumple la ventana móvil de **4.000 peticiones por minuto** exigida por Cencosud mediante `CencosudApiRateLimiter.php`.
* **Reintentos Inteligentes:** Implementa **Backoff Exponencial** (1s -> 2s -> 4s -> 8s) ante errores de servidor (`5xx`) o límites de tasa (`429`).

---

### 7. 🖥️ Interfaz Responsiva, Paginación y Exportación Auditada
* **Diseño Responsivo con Scroll Horizontal:** Tablas equipadas con `overflow-x: auto; min-width: 1450px / 1600px` para evitar la compresión excesiva de recuadros.
* **Paginación Oficial:** Soporta paginación por `offset/limit` (v2) y `nextCursor` (v3).
* **Buscador en Tiempo Real:** Entrada debounced (300ms) que filtra instantáneamente por SKU base, variaciones o título.
* **Exportador a Excel `.xlsx`:** Genera planillas estandarizadas con **20 columnas de auditoría** listas para presentar al equipo de Onboarding de Cencosud.

---

### 8. 🔄 Sincronización Bidireccional de Stock con Mercado Libre y Gestión de Órdenes
* **Impacto Inmediato:** Al actualizar inventario en Cencosud (vía API, formulario o lote), el método `syncStockToMl()` actualiza automáticamente el inventario de la tabla `ml_products_cache` para mantener alineados los stock de Mercado Libre.
* **Procesamiento de Órdenes:** Incorpora la consulta y registro de órdenes (`GET /v1/orders`) y sub-órdenes de despacho (`GET /v3/sub-orders`).

---

## 🚀 Conclusión
El sistema está **100% probado, auditado y listo para producción**. Cuenta con el interruptor `CENCOSUD_DRY_RUN`:
* `CENCOSUD_DRY_RUN = true`: Modo simulación local con datos enriquecidos.
* `CENCOSUD_DRY_RUN = false`: Modo Producción/Staging vivo directo con los servidores de Cencosud.
