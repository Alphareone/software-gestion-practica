# Reporte de Desarrollo y Estado de Avance: Módulo Cencosud

Este reporte resume todas las implementaciones de negocio, optimizaciones y análisis técnicos realizados en el módulo **Cencosud Chile**.

---

## 1. Carga Masiva mediante Arrastre de Archivos (Drag & Drop)
* **Módulo afectado:** `Cencosud -> Actualizar precios/stock`
* **Vistas y Controladores:** 
  * [content-updates.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Views/cencosud/content-updates.php)
  * [CencosudUpdateService.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Services/CencosudUpdateService.php)
* **Descripción de la funcionalidad:**
  * Se implementó una interfaz interactiva de arrastre de archivos Excel (.xlsx) y CSV. Permite la carga masiva ágil de stocks y precios promocionales específicos para Cencosud.
  * **Control de SKUs faltantes:** El validador analiza el archivo y, si detecta SKUs que no existen en el catálogo local de Cencosud, muestra de forma dinámica una tabla de **"SKUs no encontrados"** que permite:
    1. Copiar los SKUs al portapapeles individualmente.
    2. Enlace de un clic ("Agregar") para redirigir al creador de productos.
    3. Descargar un archivo CSV consolidado de todos los SKUs faltantes para importaciones masivas al catálogo maestro.
  * **Canal de Respaldo y Continuidad Operativa:** Este flujo se diseñó como un canal de contingencia clave. Dado que el personal ya está capacitado y acostumbrado a subir planillas en Mercado Libre, la curva de aprendizaje es nula. Si la sincronización API automática llegara a tener caídas o inconvenientes externos, los trabajadores pueden seguir actualizando catálogos y productos manualmente mediante planillas sin detener la operación.

---

## 2. Regla de Quiebre de Stock Crítico (Requerimiento 14 de Julio)
* **Módulo afectado:** `Cencosud Sync / Updates`
* **Vistas y Controladores:** 
  * [CencosudSyncService.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Services/CencosudSyncService.php)
  * [CencosudUpdateService.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Services/CencosudUpdateService.php)
* **Descripción de la funcionalidad:**
  * **Regla de stock ≤ 5:** Si el stock de bodega de un producto cae en el rango crítico (entre 1 y 5 unidades), el sistema **fuerza automáticamente el stock a 0** tanto en la base de datos local como en el llamado API de Cencosud, mitigando quiebres de stock en ofertas de alta demanda.
  * **Notificaciones de alerta:** Se activa una notificación inmediata en español en la campana de alertas del panel de administración del usuario:
    * *Título:* `"No queda stock — [Nombre de Tienda] (SKU: [SKU])"`
    * *Descripción:* `"El stock era de X unidades y se forzó a 0 para evitar quiebres."`
  * **Compatibilidad CLI:** Si la sincronización se corre automáticamente vía Cron en segundo plano (CLI), el script localiza el propietario de la tienda para enviar la notificación directamente a su bandeja.

---

## 3. Conexión de Nuevas Tiendas y Pruebas de Sincronización
* **Módulo afectado:** `Cencosud -> Conexiones / Panel Cencosud`
* **Descripción de la funcionalidad:**
  * Se validó y probó la creación y conexión de nuevas tiendas (ej. la tienda de pruebas **Ripholia**).
  * Se probó el ciclo completo de sincronización de catálogo en modo de simulación segura (Dry-Run), el cual consulta al simulador en [CencosudApiHelper.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Helpers/CencosudApiHelper.php), descarga los productos e inicializa el inventario local sin interactuar con servidores de producción.

---

## 4. Búsqueda Avanzada de Variantes por SKU (Requerimiento 20 de Julio)
* **Módulo afectado:** `Cencosud -> Productos`
* **Modelos y Archivos:**
  * [CencosudProductsCacheModel.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Models/CencosudProductsCacheModel.php#L133-L151)
* **Descripción de la funcionalidad:**
  * Para asegurar que la auditoría de precios y stock sea correcta frente al uso de empaques múltiples, se optimizó el buscador usando expresiones regulares (`REGEXP`) de MySQL.
  * Al buscar un SKU base original (ej. `A-201`) o cualquiera de sus variantes (individual `A-201-ROJ-S`, pack `PACK-A-201-ROJ-AZU-S` o tripack `TRIPACK-A-201-ROJ-AZU-VER-S`), el sistema **muestra agrupadas las 4 formas del producto**.
  * **Filtrado seguro:** Se eliminaron las coincidencias parciales que generaban falsos positivos (ej. evitar que al buscar `A-201` se mostrara el producto `A-2010` de otra línea).

---

## 5. Implementación del Módulo de Reportes e Inventario
* **Módulo afectado:** `Cencosud -> Reportes`
* **Vistas y Controladores:**
  * [CencosudController.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Controllers/CencosudController.php)
  * [content-reports.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Views/cencosud/content-reports.php)
  * [cencosud_sync_products.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/cli/cencosud_sync_products.php)
* **Descripción de la funcionalidad:**
  * **Planilla Interactiva Estilo Excel:** Se creó un panel comparativo de cuadrícula limpia. Muestra la información cruzada de stock y precios de Bodega física (`products_master`) contra Cencosud. Destaca visualmente en color naranja/rojo los descuadres (diferencias de stock) y en color verde las ofertas activas en Cencosud (precios menores al de lista).
  * **Exportador Excel en Tiempo Real:** El botón "Exportar a Excel" genera y descarga al instante un archivo Excel `.xlsx` estructurado, diseñado profesionalmente con filtros de búsqueda activos y marcado con fecha y hora exacta.
  * **Replicación de Stock Directa:** Los administradores pueden forzar la replicación del stock de bodega física hacia Cencosud para un producto con un solo clic.
  * **Remoción preventiva de Simulación de Ventas:** Dado que se iniciarán pruebas reales este viernes con las credenciales oficiales de la API (API Keys), se removieron por seguridad todas las interfaces y botones de "Simular Venta" para evitar confusiones de datos y errores durante la auditoría real.
  * **Automatización y Frecuencia de Cron de 10 minutos:** Se estableció la frecuencia recomendada a **10 minutos** en el script automático de segundo plano ([cencosud_sync_products.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/cli/cencosud_sync_products.php)). Esto equilibra la seguridad de stock frente al tiempo promedio de compra del cliente (8-10 min) y los límites de tasa de peticiones de Cencosud.

---

## 6. Guía de Operación Paso a Paso: Comparación Física de Stock y Detección Crítica

Para facilitar la comprensión paso a paso de cómo se sincroniza y protege el inventario físico contra Cencosud, se detalla el siguiente flujo lógico:

### Paso 1: Frecuencia de Ejecución (Cron Job)
* **¿Qué es?:** El script automático ([cencosud_sync_products.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/cli/cencosud_sync_products.php)) se ejecuta de manera automatizada en el servidor **cada 10 minutos**.
* **¿Qué hace?:** Realiza dos tareas clave de forma secuencial:
  1. Descarga el catálogo y existencias de Cencosud a la tabla local `cencosud_products_cache`.
  2. Verifica que ningún producto en Cencosud tenga stock crítico (en el rango de 1 a 5 unidades).

### Paso 2: Ejecución de la Regla de Quiebre de Stock Crítico (1 a 5 unidades)
* **Escenario A: Sincronización Automática (Cron de 10 minutos)**
  1. El script lee el stock retornado por Cencosud.
  2. Si el stock está entre **1 y 5 unidades**, el sistema detecta riesgo crítico de quiebre.
  3. Ejecuta una petición `PUT` a la API de Cencosud para **forzar su stock a 0**.
  4. Actualiza el stock en la base de datos local (`cencosud_products_cache.available_quantity`) a **0**.
  5. Crea una notificación de tipo `low_stock` que se muestra en la **Campanita del menú de administración** (ej. *"No queda stock — Ripholia (SKU: A-201)"*).

* **Escenario B: Replicación Directa / Manual**
  1. En el panel de reportes ([content-reports.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Views/cencosud/content-reports.php)), el administrador ve los descuadres entre bodega física (`products_master`) y Cencosud.
  2. Al hacer clic en **"Replicar"**, el sistema lee el stock real en bodega (`products_master.stock`).
  3. Si el stock real en bodega es **menor o igual a 5** (y mayor a 0), el sistema calcula automáticamente un stock de **0** para Cencosud.
  4. Envía la actualización de **0** a la API de Cencosud y actualiza la caché local a **0**.
  5. Inserta una notificación de tipo `stock_warning` en la base de datos que se refleja en la **Campanita** (ej. *"Se replicó stock de bodega (3 unidades). Por regla crítica, se forzó stock a 0."*).

### Paso 3: Exportación del Reporte (Excel)
* **¿Qué es?:** En la vista de Reportes, el administrador dispone de un botón **"Exportar a Excel"**.
* **¿Qué datos incluye?:** Tanto la tabla en pantalla como el reporte Excel `.xlsx` generado en tiempo real contienen:
  * El **SKU** del producto.
  * La **Marca** del producto (asociada al SKU desde `products_master`), útil para identificar a qué marca/proveedor corresponde cada fila.
  * El **Título/Producto**.
  * El tipo de empaque (Unidad, Pack, Tripack).
  * El stock físico real registrado en la bodega (`products_master`).
  * El stock activo publicado en Cencosud (`cencosud_products_cache`).
  * La discrepancia (diferencia de unidades) marcada con estilos visuales.
  * El precio maestro y el precio activo en Cencosud (con marcas si posee ofertas).

---

## 7. Bitácora de Pruebas de Verificación Exitosas

Con el fin de comprobar el correcto funcionamiento de las notificaciones sin interrupciones y corregir posibles problemas de integridad, se diseñó y ejecutó un script de verificación automatizado en el entorno de desarrollo con los siguientes resultados:

1. **Prueba 1: Replicación con Stock Normal (8 unidades):**
   * *Acción:* Se insertó un stock de 8 unidades en `products_master`.
   * *Resultado:* Se actualizó Cencosud a 8 de forma correcta. **No se generaron alertas** en la campanita (Comportamiento Esperado).
2. **Prueba 2: Replicación con Stock Crítico (3 unidades):**
   * *Acción:* Se insertó un stock de 3 unidades en `products_master` (rango <= 5).
   * *Resultado:* Se forzó el stock de Cencosud a 0. Se generó la alerta `stock_warning` en la **Campanita** informando el ajuste preventivo.
3. **Prueba 3: Sincronización con Stock Crítico en API (4 unidades):**
   * *Acción:* Se simuló la detección de un stock de 4 unidades proveniente de la API.
   * *Resultado:* El sincronizador lo forzó a 0 e insertó una alerta `low_stock` en la **Campanita** indicando el límite de seguridad.

*Nota: Todas las pruebas finalizaron con estado `[PASS]` y sin errores de base de datos.*

---

## 8. Integración de Campos Técnicos Obligatorios
* **Módulo afectado:** `Cencosud -> Reportes`, `Cencosud -> Productos` y `Excel`
* **Vistas y Controladores:**
  - [CencosudController.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Controllers/CencosudController.php)
  - [CencosudProductsCacheModel.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Models/CencosudProductsCacheModel.php)
  - [content-reports.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Views/cencosud/content-reports.php)
  - [content-products.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Views/cencosud/content-products.php)
* **Descripción de la funcionalidad:**
  - **Carga Dinámica de Medidas:** La API de Cencosud exige obligatoriamente peso y dimensiones (alto, ancho, largo). Para evitar crear tablas redundantes, el controlador consulta la tabla `product_medidas` en memoria y cruza el producto en base a su SKU directo (`variante`) o coincidencia de categoría/título.
  - **Ficha Técnica en Pantalla:** Se rediseñó el reporte para mostrar las medidas y la categoría de cada producto. Si faltan datos, muestra un badge de advertencia naranja `⚠️ Incompleto`, alertando que Cencosud no aceptará el producto sin esa información.
  - **Columna SKU / Foto:** En lugar de mostrar solo el SKU de texto, ahora mostramos una miniatura visual (`36x36px`) junto al SKU con un enlace discreto "Link foto" que permite ver el archivo original.
  - **Excel Completo de Auditoría:** El botón de exportación se extendió para incluir columnas separadas de *Categoría*, *Descripción*, *Peso (Kg)*, *Alto (cm)*, *Ancho (cm)*, *Largo (cm)* y *URL Foto*, completando la ficha técnica exigida por Cencosud en el reporte descargado.

---

## 9. Módulo "Traer Plantilla" de Carga y Sincronización Manual
* **Módulo afectado:** `Cencosud -> Productos`
* **Vistas y Controladores:**
  - [CencosudController.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Controllers/CencosudController.php) (métodos `searchMasterProduct` y `saveAndSyncProduct`)
  - [content-products.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Views/cencosud/content-products.php)
* **Descripción de la funcionalidad:**
  - **Botón "Traer Plantilla":** Agrega un botón verde en la barra superior del catálogo de productos.
  - **Modal Interactivo de Sincronización:**
    1. Permite escribir un SKU de bodega y buscarlo en tiempo real en la base de datos local.
    2. Al encontrar el producto, muestra un botón verde **"Cargar plantilla"**.
    3. Al presionarlo, se **auto-completan instantáneamente todos los campos** de la ficha: SKU, Título, Marca, Categoría, Descripción, Foto, Peso y Dimensiones pre-calculadas.
    4. Permite que el usuario edite o corrija cualquier medida o texto en la interfaz.
    5. Al hacer clic en **"Guardar y publicar en Cencosud"**, se guardan los cambios básicos en `products_master`, se almacena la plantilla de medidas específica en `product_medidas` usando la SKU en la columna `variante` como sobreescritura, se crea/actualiza la caché local en estado publicado (`PUBLISHED`), y se envía a la API simulada de Cencosud.

---

## 10. Reglas de Protección y Validación de Precios Anti-Error (Riesgo SERNAC)
* **Objetivo:** Prevenir pérdidas financieras y problemas legales por actualización de precios erróneos (ej. publicar un producto por $1 peso por error de tipeo o celda corrida en Excel).
* **Especificación e Integración:**
  1. **Piso Mínimo Absoluto (Safety Price Floor):** Bloquear automáticamente cualquier intento de enviar un precio igual o menor a **$500 CLP** a la API `PUT /v1/product-price/upsert/{sku}`.
  2. **Detección de Caída Drástica (> 70% Descuento):** Comparar el nuevo precio contra `products_master.price`. Si la variación supera el 70% de descuento respecto al precio maestro, bloquear la actualización y marcar con el estado `Bloqueado por Riesgo SERNAC`.
  3. **Notificaciones Automáticas en la Campanita:** Cada vez que el sistema detecta un precio por debajo del piso mínimo o con una caída superior al 70%, dispara inmediatamente una notificación `price_alert` / `price_warning` en la campanita del menú de administración del usuario notificando la tienda, SKU y detalle del bloqueo.
  4. **Estructura Completa de Payload:** Formatear el JSON con `value`, `showFrom`, `showTo`, `store` y `price` exigidos por la documentación oficial de Cencosud.

---

## 11. Protocolo de Certificación Sandbox y Pase a Producción (Go-Live)
* **Documento Consolidado:** [CERTIFICACION_CENCOSUD_SANDBOX.md](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/Docs/CERTIFICACION_CENCOSUD_SANDBOX.md)
* **Resumen del Protocolo:**
  1. **Matriz de Pruebas de Certificación:** Se estructuraron las 9 pruebas de visto bueno exigidas por Cencosud (Auth JWT, `GET /v1/stock/sku-variant/{sku}`, paginación `pagging`, payload `attributeDetails`, variantes por empaque, piso mínimo de precios, regla de quiebre de stock crítico y rate limit).
  2. **Switch de Entorno Operativo:** La vista de conexiones ([content-connect.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Views/cencosud/content-connect.php)) cuenta con el selector dinámico para alternar entre `sandbox_mock` y `production` una vez recibida la aprobación oficial de Cencosud Chile.

---

## 12. Estandarización de Columnas, Atributos de Variante y Scroll Horizontal Responsivo
* **Módulos afectados:** `Cencosud -> Productos`, `Cencosud -> Reportes` y `Exportador Excel`
* **Vistas:** [content-products.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Views/cencosud/content-products.php) y [content-reports.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Views/cencosud/content-reports.php)
* **Descripción de la Mejora:**
  1. **Incorporación de Atributos de Variante (Color, Talla, Género):** Se integró la lectura de atributos extendidos desde `products_master` (`color`, `size`, `gender`), desplegando en pantalla y en la exportación Excel la ficha de variantes exacta requerida por la API de Cencosud (`PATCH /v2/products/{id}`).
  2. **Scroll Horizontal Responsivo (`overflow-x: auto` & `min-width: 1450px/1600px`):** Se dotó al contenedor de las tablas de desplazamiento horizontal fluido (`overflow-x: auto`) y un ancho mínimo garantizado para evitar que las celdas o recuadros se compriman excesivamente en pantalla.
  3. **Secuencia Unificada de Columnas:**
     1. **SKU / Foto:** Miniatura de 36x36px junto con enlace directo a la imagen.
     2. **Marca:** Marca registrada del producto (`products_master.brand`).
     3. **Producto:** Título completo del producto y descripción emergente.
     4. **Categoría:** Categoría asignada.
     5. **Tipo:** Badge visual identificando Unidad, Pack o Tripack.
     6. **Atributos / Variante:** Ficha de Color, Talla y Género.
     7. **Stock:** Existencias (Stock Bodega, Stock Cencosud y Diferencia).
     8. **Precio:** Precios de lista y precios activos Cencosud.
     9. **Medidas:** Ficha técnica de peso y dimensiones (`product_medidas`).
     10. **Estado:** Estado de publicación y promociones activas.
---

## 13. Mapeo de 4 Parámetros de Medidas y Cálculo de Despacho Volumétrico
* **Objetivo:** Resolver la discrepancia de nombres entre la planilla de creación Cencosud, la documentación oficial (`developers.ecomm.cencosud.com`) y la tabla `product_medidas`.
* **Equivalencia de Parámetros:**
  | Planilla / API Cencosud | Tabla `product_medidas` | Campo en Vista / Reporte | Explicación |
  |---|---|---|---|
  | **Peso (Kg)** | `peso_kg` | `Peso Real` | Peso físico real del paquete |
  | **Alto (cm)** | `alto_cm` | `Alto` | Altura del empaque |
  | **Ancho (cm)** | `ancho_cm` | `Ancho` | Anchura del empaque |
  | **Profundidad / Largo (cm)** | `grueso_cm` | `Profundidad / Largo` | Longitud o grosor del empaque |

---

## 14. Sistema de Alertas a la Campanita por Inconsistencia de Atributos y Fallbacks
* **Servicio:** [NotificationService.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Services/NotificationService.php) y [CencosudApiService.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Services/CencosudApiService.php)
* **Comportamiento:**
  * Si al construir el JSON de Cencosud se detecta que un SKU no posee definidos atributos obligatorios en `products_master` ni en `extra_data` (ej. faltan *Marca*, *Color*, *Talla* o *Género*):
  1. **Aplica el Respaldo Seguro:** Inserta el valor predeterminado (*Genérica*, *Único*, *Estándar*, *Unisex*) para no detener la sincronización de inventario ni generar rechazo `400 Bad Request`.
  2. **Dispara Notificación Instantánea:** Envía una alerta a la **Campanita** (`type: product_fallback`) advirtiendo al usuario:
     > **Inconsistencia en Ficha SKU [SKU]:** El producto *'[Título]*' se procesó con valores de respaldo automáticos debido a atributos faltantes en el Maestro: *[Atributos Faltantes]*. Favor completar la ficha técnica.

---

## 15. Blindaje de Autenticación y Extracción Segura de Tokens (Anti-Inyección)
* **Servicio:** [CencosudAuthService.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Services/CencosudAuthService.php)
* **Controles de Seguridad:**
  1. **Validación Estricta de Respuestas HTTP 200:** Se exige la presencia de un token válido tipo string (`accessToken` o `access_token`). Si la API de Cencosud no retorna HTTP 200 o el objeto JSON viene corrupto, la petición se rechaza inmediatamente sin retornar datos.
  2. **Compatibilidad Dual de Tokens JWT:** Soporta tanto el formato moderno de Cencosud (`accessToken` con expiración `14400` segundos / 4 horas) como el formato heredado (`access_token`).
  3. **Persistencia Cifrada:** Los tokens nunca se exponen ni almacenan en texto plano en la base de datos; siempre se guardan con cifrado reversible mediante `CryptoHelper::encrypt()`.

---

## 16. Paginación de Catálogo por `offset` y Bucle `while` Seguro
* **Servicio:** [CencosudApiService.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Services/CencosudApiService.php#L168)
* **Control Preciso del Parámetro `offset`:**
  * En cada iteración, el servicio envía `GET /v2/products/search?limit=100&offset=X`.
  * Incrementa `$offset += count($items)` en cada ciclo.
  * Compara explícitamente `$offset < $total` (donde `$total` es la cantidad total informada por la API de Cencosud) y mantiene el corta-circuito de `$pageCount < $maxPages` para garantizar que la descarga termine de forma matemática y segura.

## 17. Sistema de Ratificación Continua de Medidas y Respaldos (`getRatifiedMeasures`)
* **Servicio:** [CencosudApiService.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Services/CencosudApiService.php#L420)
* **Mecanismo de Respaldo y Ratificación Continua:**
  * Ante planillas corruptas, vacías o peticiones de API con errores en dimensiones (`peso_kg`, `alto_cm`, `ancho_cm`, `grueso_cm`):
  1. **Ratificación por SKU Exacto:** Consulta la tabla `product_medidas` por coincidencia exacta de SKU/variante.
  2. **Ratificación por Categoría:** Si no existe coincidencia de SKU, consulta la regla por categoría en `product_medidas`.
  3. **Respaldo Estándar Seguro (0.5 Kg, 10x10x10 cm):** Garantiza que ninguna plantilla errónea replique datos nulos en Cencosud ni trabe la emisión de etiquetas de despacho en Envíame/Paris.
  4. **Alerta a la Campanita:** En caso de aplicar respaldos de categoría o estándar, notifica automáticamente a la **Campanita**.

---

## 18. Diagrama de Arquitectura de Producción (Hostinger)
```mermaid
graph TD
    Client[" Cliente / Navegador Web"] -->|HTTPS / GET / POST| HostingerPublic[" Hostinger Public HTML (public_html/index.php)"]
    HostingerPublic -->|Resolución Dinámica $baseDir| BackendCore[" Backend Core (backend-software/app/)"]
    
    subgraph Servidor Global Hostinger (LiteSpeed / PHP-FPM)
        BackendCore --> Auth[" CencosudAuthService (AES-256)"]
        BackendCore --> ApiService[" CencosudApiService"]
        BackendCore --> UpdateService[" CencosudUpdateService (Protección SERNAC)"]
        BackendCore --> SyncMl[" Sincronización Bidireccional Stock ML"]
    end

    ApiService -->|cURL Directo / HTTP Socket| CencoAPI[" API Cencosud Paris.cl (api.cencosud.cl)"]
    SyncMl <-->|PDO MySQL| DB[(" Base de Datos MariaDB (gestion)")]
    Auth <-->|PDO MySQL| DB
```

---

## 19. Módulo de Pedidos, Subórdenes y Caché de Ventas Cencosud
* **Módulo afectado:** `Cencosud -> Pedidos`
* **Vistas y Controladores:**
  - [content-orders.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Views/cencosud/content-orders.php)
  - [CencosudOrdersCacheModel.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Models/CencosudOrdersCacheModel.php)
  - [CencosudApiService.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Services/CencosudApiService.php) (`getOrders`, `getSubOrders`)
* **Descripción de la funcionalidad:**
  - **Monitoreo de Ventas:** Visualización de pedidos emitidos en Cencosud Paris.cl con cliente, monto total, estado de suborden y despacho.
  - **Caché en Base de Datos (`cencosud_orders_cache`):** Permite consultar el historial de compras y estado de envíos sin saturar la API externa de Cencosud.
  - **Sincronización On-Demand y Filtrado:** Botón "Sincronizar Ventas" para refrescar el estado de las órdenes en tiempo real con selector de tienda activa.

---

## 20. Sincronización Paginada por Chunks para Catálogos Extensos
* **Módulo afectado:** `Cencosud -> Sincronización`
* **Vistas y Controladores:**
  - [CencosudController.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Controllers/CencosudController.php) (`syncStart`, `syncChunk`, `syncStop`)
  - [CencosudSyncService.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Services/CencosudSyncService.php)
* **Descripción de la funcionalidad:**
  - **Prevención de Timeouts HTTP:** Para evitar bloqueos por límite de ejecución (Max Execution Time en PHP/Hostinger), el proceso de sincronización fracciona las llamadas a la API en pequeños "chunks" (bloques) por cursor/offset.
  - **Control de Estado (`cencosud_sync_status`):** Almacena el número total de productos, productos sincronizados, `next_cursor` y marcas de bloqueo (`locked_at`, `locked_by`) para permitir la reanudación segura si se interrumpe la conexión.

---

## 21. Sistema de Notificaciones de Correo por Alto Stock (`sendHighStockEmail`)
* **Módulo afectado:** `Cencosud -> Alertas`
* **Vistas y Controladores:**
  - [CencosudController.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Controllers/CencosudController.php) (`sendHighStockEmail`)
  - [MailService.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Services/MailService.php)
* **Descripción de la funcionalidad:**
  - **Alertas de Sobre-Inventario:** Dispara un correo automático a los administradores cuando un SKU supera umbrales masivos de stock en bodega o Cencosud, permitiendo tomar decisiones de promociones y descuentos proactivos.
