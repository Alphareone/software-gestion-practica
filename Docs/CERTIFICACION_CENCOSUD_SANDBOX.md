# Protocolo de Acreditación y Solicitud de Pase a Producción: Módulo Cencosud Chile

Este documento constituye el **Checklist Formal de Certificación** para solicitar el visto bueno (Go-Live Sign-Off) y la entrega de credenciales oficiales de Producción por parte del equipo de soporte e integración de **Cencosud Marketplace Chile**.

---

## 1. Información General del Integrador

* **Nombre de la Aplicación:** Sistema de Gestión E-Commerce & Multi-Marketplace
* **Módulo Integrado:** Cencosud Marketplace (Paris, Jumbo, Easy)
* **Entorno de Pruebas Actual:** Sandbox / Mock (`sandbox_mock`)
* **Mecanismo de Autenticación:** Bearer JWT Token (OAuth2 Client Credentials)
* **Estándar de Cifrado Local:** AES-256-CBC

---

## 2. Checklist de Acreditación de Pruebas en Sandbox

| # | Requisito / Prueba Técnica | Endpoint / Componente Cencosud | Estado de Cumplimiento | Evidencia / Mecanismo de Validación |
|---|---|---|---|---|
| **1** | **Autenticación y Bearer Token** | `POST /v1/auth` / `OAuth2` | **APROBADO** | Obtención y refresco de JWT token cifrado en base de datos. |
| **2** | **Consulta de Stock por SKU Variante** | `GET /v1/stock/sku-variant/{sku}` | **APROBADO** | Empalme nativo por SKU variante sin traductores intermedios. |
| **3** | **Descarga Paginada de Catálogo** | `GET /v1/family`, `GET /v1/category` | **APROBADO** | Paginación por `limit` y `offset` respetando el objeto `pagging`. |
| **4** | **Creación/Edición con Ficha Técnica** | `POST /v1/product`, `PUT /v1/product/{sku}` | **APROBADO** | Envío de payload obligatorio `attributeDetails` (peso, alto, ancho, largo y marca de `product_medidas`). |
| **5** | **Gestión de Variantes por Empaque** | `POST /v1/variant`, `PUT /v1/variant/{sku}` | **APROBADO** | Agrupación inteligente de SKU base en presentaciones Unidad, Pack y Tripack. |
| **6** | **Protección de Precios Anti-Error** | `PUT /v1/product-price/upsert/{sku}` | **APROBADO** | Bloqueo automático de precios $< \$500$ CLP y caídas $> 70\%$ desc. (Riesgo SERNAC). |
| **7** | **Regla de Quiebre de Stock Crítico** | `PUT /v3/inventory` | **APROBADO** | Forzado a 0 de productos con 1 a 5 unidades en bodega + alerta en campanita. |
| **8** | **Control de Frecuencia (Rate Limit)** | Cron de 10 minutos / Backoff | **APROBADO** | Evita bloqueos HTTP 429 respetando la cuota de llamadas permitidas por Cencosud. |
| **9** | **Auditoría e Informes a Bodega** | Módulo de Reportes & Email | **APROBADO** | Exportador Excel `.xlsx` en tiempo real y despacho directo a Bodega/Adquisiciones. |

---

## 3. Protocolo de Conmutación a Producción (Go-Live)

Una vez otorgado el visto bueno por Cencosud y recibidas las credenciales oficiales de Producción:

1. **Ingresar al Panel de Conexiones Cencosud:** Navegar a `Cencosud -> Conexiones` ([content-connect.php](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/app/Views/cencosud/content-connect.php)).
2. **Ingresar Credenciales Reales:**
   * `Client ID` / `API Key` de producción.
   * `Client Secret` cifrado automáticamente en base de datos.
3. **Cambio de Entorno:**
   * Cambiar el selector de entorno de `sandbox_mock` a **`production`**.
4. **Guardar y Verificar:**
   * Hacer clic en **"Probar Conexión"**. Al recibir el estado `HTTP 200 OK`, la tienda queda operando automáticamente con la API en vivo de Cencosud Chile.

---

> [!NOTE]
> Este documento junto al archivo [PLAN_DE_PRUEBAS_INTEGRACION.md](file:///Users/amparovaldivia/practica/SOFWARE-DE-GESTI-N/Docs/PLAN_DE_PRUEBAS_INTEGRACION.md) conforman la carpeta técnica completa para auditoría.
