# Arquitectura CSS

## Stack tecnológico

- **Framework**: Tailwind CSS v4.3.0 (vía `@tailwindcss/cli`)
- **Preprocesador**: Ninguno (CSS nativo)
- **Compilación**: CLI de Tailwind, que usa LightningCSS internamente
- **Carga**: `<link>` directo en layouts PHP

## Estructura de archivos

```
public_html/assets/css/
├── tailwind.css              ← Entry point (solo @import + @config)
│
├── partials/ (18 archivos)
│   ├── _foundation.css       @theme, @layer base, @layer components
│   ├── _sidebar.css          Sidebar (navegación principal)
│   ├── _layout.css           Dashboard layout (header, container, dropdowns)
│   ├── _notifications.css    Campana + panel de notificaciones
│   ├── _auth.css             Auth page (login, gradient, orbs + utilidades)
│   ├── _toast.css            Sistema de notificaciones toast
│   │
│   ├── _prices.css           Price wizard, batch processing
│   ├── _price-history.css    Price history content
│   ├── _settings-admin.css   Admin settings panel
│   ├── _price-history-v3.css Price history v3 card layout
│   ├── _users.css            Users table, form, edit
│   ├── _connections.css      Connections dashboard, stores, KPI, charts
│   ├── _ml.css               ML connect, audit batch, audit table
│   ├── _price-hub.css        Price hub v2
│   ├── _activity.css         Activity log v2
│   ├── _webproducts.css      WebProducts module
│   │
│   ├── _sync-widget.css      Widget flotante de sincronización
│   └── _utilities.css        Utilities (scrollbar, focus, reduced motion)
│
└── dist/
    ├── tailwind.css           Compilado (dev — sin minificar)
    └── tailwind.min.css       Compilado (prod — minificado)
```

## Orden de importación

```css
/* tailwind.css (26 líneas) */
@import "tailwindcss";
@config "../../../tailwind.config.js";
@custom-variant dark (&:where(.dark, .dark *));

/* Capa fundacional */
@import "./partials/_foundation.css";

/* Layout y componentes base */
@import "./partials/_sidebar.css";
@import "./partials/_layout.css";
@import "./partials/_notifications.css";
@import "./partials/_auth.css";
@import "./partials/_toast.css";

/* Módulos funcionales (ordenados alfabéticamente) */
@import "./partials/_prices.css";
@import "./partials/_price-history.css";
@import "./partials/_settings-admin.css";
@import "./partials/_price-history-v3.css";
@import "./partials/_users.css";
@import "./partials/_connections.css";
@import "./partials/_ml.css";
@import "./partials/_price-hub.css";
@import "./partials/_activity.css";
@import "./partials/_webproducts.css";

/* Widgets y utilidades */
@import "./partials/_sync-widget.css";
@import "./partials/_utilities.css";
```

Las importaciones se resuelven en tiempo de compilación (LightningCSS
inlinea los archivos). Tailwind procesa el resultado completo.

## Capas (layers)

El archivo `_foundation.css` contiene tres capas:

### `@layer base`
Define las variables CSS en `:root` y `:root[data-theme="light"]`.
Aquí vive todo el sistema de diseño (design tokens).

### `@layer components`
Clases utilitarias de componentes atómicos:
- `.btn-primary` / `.btn-secondary`
- `.card`
- `.input`

### Sin layer
El resto de los partials declaran estilos sin layer. Esto les da
prioridad sobre `@layer base` y `@layer components`.

## Cómo encontrar un estilo

1. Busca el nombre de clase en el partial correspondiente
   (ver `COMPONENT-REFERENCE.md`)
2. Los valores de color deben estar en variables CSS
   (ver `THEME-SYSTEM.md`)
