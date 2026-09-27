# Documentación General del Proyecto

Bienvenido a la documentación de referencia del sistema de gestión. Aquí encontrarás la información necesaria para entender la arquitectura, guías de integración, pruebas, despliegue y diseño del sistema.

---

## 📁 Estructura de Documentación

```
Docs/
├── README.md                           ← Índice general de documentación
├── PLAN_DE_PRUEBAS_INTEGRACION.md      ← Plan de pruebas de integración y matriz QA
├── CERTIFICACION_CENCOSUD_SANDBOX.md   ← Protocolo de certificación Sandbox y pase a producción
├── DESPLIEGUE_HOSTINGER.md             ← Guía de despliegue en servidor Hostinger
├── DEPLOY.md                           ← Manual de despliegue y estructura de archivos
├── CHANGELOG-*.md                      ← Historial de cambios y refactorizaciones
│
├── database/                           ← Documentación de base de datos y esquema
│   ├── 01-MODULOS.md                   ← Estructura de tablas por módulo (incluye Módulo Cencosud)
│   ├── 02-FOREIGN-KEYS.md              ← Claves foráneas y relaciones
│   ├── 03-IS-ADMIN.md                  ← Roles y permisos de usuarios
│   ├── 04-BEST-PRACTICES.md            ← Buenas prácticas de consultas y PDO
│   ├── 05-EMAIL-SYSTEM.md              ← Sistema de registros y envío de correos
│   ├── MIGRATION-2026-07-14-CENCOSUD.sql ← Script de migración SQL Cencosud
│   └── cencosud_migration.sql          ← Esquema de tablas Cencosud
│
└── front-end/                          ← Arquitectura y guía visual de UI
    ├── CSS-ARCHITECTURE.md             ← Organización del CSS (partials, layers, @import)
    ├── BUILD-PIPELINE.md               ← Compilación de assets (dev, prod, watch)
    ├── COMPONENT-REFERENCE.md          ← Mapa de componentes visuales
    ├── THEME-SYSTEM.md                 ← Variables CSS y sistema de temas
    └── CODING-GUIDELINES.md            ← Estándares de desarrollo de código

Documentos Técnicos en Raíz:
├── INFORME_TECNICO_INTEGRACION_CENCOSUD.md  ← Arquitectura e informe técnico del módulo Cencosud
└── REPORTE_DESARROLLO_CENCOSUD.md           ← Reporte de funcionalidades desarrolladas
```

---

## 📌 Guía de Consulta Rápidas

| Documento | Propósito / Cuándo Consultarlo |
|---|---|
| `INFORME_TECNICO_INTEGRACION_CENCOSUD.md` | Especificación técnica, diagramas Mermaid y capacidades del conector Cencosud. |
| `REPORTE_DESARROLLO_CENCOSUD.md` | Detalle funcional de stock crítico, validaciones SERNAC, alertas a la campanita y reportes Excel. |
| `PLAN_DE_PRUEBAS_INTEGRACION.md` | Matriz de pruebas de integración (QA Matrix), resiliencia de payloads y escenarios límite. |
| `CERTIFICACION_CENCOSUD_SANDBOX.md` | Matriz de 9 pruebas de vistos buenos para certificación en Cencosud Paris.cl. |
| `DESPLIEGUE_HOSTINGER.md` / `DEPLOY.md` | Configuración de PHP, variables de entorno y despliegue en servidor `epracticas.cl`. |
| `database/01-MODULOS.md` | Tablas de base de datos (`cencosud_connections`, `cencosud_products_cache`, `cencosud_orders_cache`, etc.). |
| `front-end/` | Modificación de interfaz, diseño responsivo, temas y arquitectura CSS. |
