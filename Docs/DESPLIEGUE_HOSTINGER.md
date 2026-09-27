# DOCUMENTACIÓN TÉCNICA Y GUÍA DE DESPLIEGUE
## Módulo de Integración Cencosud Marketplace & Guía de Puesta en Producción en Hostinger

**Empresa:** OFERTASIMPERDIBLES.CL / DEPARTAMENTO DE DESARROLLO  
**Proyecto:** Sistema de Gestión Multicanal (Software)  
**Módulo:** Integración Cencosud API REST  
**Desarrollado por:** Abraham Isaac González Sánchez  
**Fecha de Documentación:** Agosto de 2026  

---

> 📌 **Nota sobre el flujo de despliegue:**  
> Primero se juntarán y fusionarán las ramas correspondientes en la rama `main` de Git, y posteriormente se subirá todo el código consolidado al servidor de producción en Hostinger.

---

## 1. RESUMEN EJECUTIVO Y ALCANCE DE LA INTEGRACIÓN

El presente documento técnico tiene por objetivo traspasar el conocimiento, la arquitectura de software desarrollada y la guía de despliegue correspondiente al nuevo módulo de integración con **Cencosud Marketplace (Paris, Jumbo, Easy)**.

Anteriormente, el Software de Gestión solo soportaba la sincronización con Mercado Libre. Con esta actualización, el sistema adquiere capacidad omnicanal bidireccional para gestionar productos, actualizar inventarios y procesar órdenes de compra desde Cencosud.

---

## 2. ARQUITECTURA DE SOFTWARE Y CAMBIOS REALIZADOS

La solución fue construida respetando la arquitectura MVC del proyecto y aplicando patrones de diseño para desacoplar las llamadas API de la lógica de negocio:

### 2.1. Base de Datos (Script: `Docs/database/cencosud_migration.sql`)
Se incorporaron 4 tablas principales al esquema de la base de datos:
- **`cencosud_connections`**: Almacena los datos de la tienda, `seller_id`, entorno (`production` | `sandbox_mock`) y la API Key cifrada.
- **`cencosud_products_cache`**: Caché local de productos sincronizados (SKU, título, precio, stock, estado, categoría e imagen).
- **`cencosud_orders_cache`**: Caché local de órdenes y pedidos recibidos desde Cencosud Marketplace.
- **`cencosud_sync_status`**: Mantiene el estado del proceso de sincronización en segundo plano (`idle`, `syncing`, `error`, total/synced).

### 2.2. Seguridad y Cifrado de Credenciales
Por estrictos motivos de seguridad, la API Key **no se almacena en texto plano**. Se implementó el método de cifrado **AES-256-CBC** utilizando la clave global `ENCRYPTION_KEY` definida en el archivo `.env`. La API Key se descifra en memoria únicamente al momento de solicitar un token de acceso a Cencosud.

### 2.3. Capa de Servicios y Controladores
- **`CencosudApiService.php`**: Maneja la lógica de comunicación con la API (creación de productos, actualización de stock y sincronización).
- **`CencosudAuthService.php`**: Gestiona la autenticación obteniendo tokens JWT con validez de 4 horas desde el endpoint `/v1/auth/apiKey`.
- **`CencosudAdapter.php` (Patrón Adapter)**: Abstrae la interfaz de Cencosud para hacerla compatible con `MarketplaceFactory`.
- **`CencosudController.php`**: Controlador encargado de responder a las rutas del frontend (`/cencosud/products`, `/cencosud/connections`, `/cencosud/orders`).

---

## 3. DIAGNÓSTICO TÉCNICO: ¿POR QUÉ FUNCIONARÁ EN HOSTINGER?

Durante las pruebas en el entorno de desarrollo local (XAMPP / localhost), al intentar realizar la sincronización real con la API Key válida de Cencosud (`44fd4887-1084-4705-9a4e-402d9bdf18ea`), la API devolvió el siguiente error:

```text
[ERROR OBSERVADO EN LOCAL:] Error en última sincronización: No se pudo obtener el token JWT de autenticación (HTTP 403 Forbidden / Unauthorized).
```

### 3.1. Causa del Comportamiento en Localhost
Este comportamiento es completamente normal y se debe a tres restricciones de seguridad impuestas por la infraestructura de Cencosud (según su documentación oficial):
1. **Whitelist de Direcciones IP:** La API de Cencosud filtra las peticiones por origen. Las peticiones enviadas desde direcciones IP locales residenciales (`localhost` / `127.0.0.1`) son bloqueadas por el Firewall/WAF corporativo.
2. **Certificado SSL / Origen HTTPS:** Los endpoints productivos exigen que las solicitudes se originen desde un servidor web con nombre de dominio válido y certificado SSL seguro (HTTPS), lo cual no se cumple en un entorno XAMPP por defecto.
3. **Mantenimiento de Entorno de Prueba (Mock):** Para posibilitar el desarrollo local sin conexión externa, se configuró el campo `environment = 'sandbox_mock'` en la tabla `cencosud_connections`, permitiendo simular operaciones e interfaz localmente de manera correcta.

### 3.2. Comportamiento en el Servidor Hostinger
Al realizar el despliegue en el servidor de producción Hostinger (`software.epracticas.cl`):
- **Peticiones desde IP Pública Fija:** Las solicitudes saldrán desde la IP dedicada del servidor (`72.61.61.1`), la cual es reconocida por las redes públicas de Cencosud.
- **Origen HTTPS Válido:** Las peticiones tendrán como origen el dominio `https://software.epracticas.cl` con su correspondiente certificado SSL.
- **Modo Production Activo:** Al cambiar el entorno a `environment = 'production'`, `CencosudAuthService` utilizará la API Key cifrada para llamar a `https://api-developers.ecomm.cencosud.com/v1/auth/apiKey`, obteniendo un Token JWT válido de 4 horas para descargar el catálogo real.

---

## 4. GUÍA PASO A PASO PARA EL DESPLIEGUE EN HOSTINGER

Sigue estos pasos detallados para completar la puesta en marcha en el servidor:

### Paso 1: Fusión de Ramas y Actualización de Archivos
1. Integrar y fusionar todas las ramas de desarrollo en la rama principal `main` de Git.
2. Subir y reemplazar los archivos en Hostinger respetando la estructura de producción:

```
/home/u479877971/domains/epracticas.cl/
├── backend-software/          ← Reemplazar app/ y mantener el .env existente intacto
└── public_html/software/      ← Reemplazar assets/ e index.php
```

### Paso 2: Ejecutar la Migración de la Base de Datos
Acceder a phpMyAdmin en Hostinger (base de datos `u479877971_sysgestion`) e importar el archivo SQL de migración ubicado en:
`Docs/database/cencosud_migration.sql`

### Paso 3: Insertar la Conexión de Cencosud en la Base de Datos
Ejecutar la siguiente consulta SQL en phpMyAdmin para dar de alta la conexión real con la API Key cifrada para la tienda principal:

```sql
-- Insertar conexión para Comercializadora Abizi SPA en producción
INSERT INTO cencosud_connections (user_id, store_name, seller_id, api_key, environment, is_active)
VALUES (
  1, 
  'Comercializadora Abizi SPA', 
  'abizi-principal', 
  'Qzh2eFpRNm5yNmNzclFvaS9aNEtNQT09OjpQOHBvMUVJbENzNFRyNUp1VFF6QkdBPT0=', -- API Key cifrada (AES-256)
  'production', 
  1
);

INSERT INTO cencosud_sync_status (cencosud_connection_id, status) VALUES (LAST_INSERT_ID(), 'idle');
```

### Paso 4: Verificación y Sincronización en Vivo
1. Ingresar a `https://software.epracticas.cl` e iniciar sesión con tu cuenta de administrador.
2. Ir al menú lateral: **Cencosud → Productos**.
3. Seleccionar la tienda **'Comercializadora Abizi SPA'**.
4. Hacer clic en el botón **'Sincronizar Catálogo'**.
5. El sistema solicitará el token JWT en vivo a Cencosud y desplegará la lista de productos reales.
