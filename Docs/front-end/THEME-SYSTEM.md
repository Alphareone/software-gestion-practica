# Sistema de temas

## Cómo funciona

El sistema soporta tres modos: **oscuro** (default), **claro** y **automático**
(sigue la preferencia del sistema operativo).

El tema se controla mediante:
1. Atributo `data-theme` en `<html>` (`"dark"` | `"light"`)
2. Clase `.dark` en `<html>` (para compatibilidad con Tailwind dark mode)

Ambos se sincronizan desde JavaScript al cargar la página, leyendo el valor
guardado en `localStorage` (key: `theme`).

## Design tokens disponibles

Todas las variables se definen en `:root` (dark) y se sobrescriben en
`:root[data-theme="light"]`.

### Colores base

| Variable | Dark | Light | Uso |
|---|---|---|---|
| `--bg` | `#0f1117` | `#f0f4f8` | Fondo general |
| `--bg-main` | `#0f1117` | `#f0f4f8` | Fondo main content |
| `--bg-sidebar` | `#0f172a` | `#ffffff` | Fondo sidebar |
| `--bg-secondary` | `#15182a` | `#f8fafc` | Fondos secundarios dentro de cards |
| `--text-primary` | `#eef0f5` | `#111827` | Texto principal |
| `--text-secondary` | `#9ca3b0` | `#6b7280` | Texto secundario/muted |
| `--border` | `#1e293b` | `#e2e8f0` | Bordes |
| `--card-bg` | `#1a1d2e` | `#ffffff` | Fondo de cards |

### Colores semánticos

| Variable | Dark | Light | Uso |
|---|---|---|---|
| `--accent` | `#0ea5e9` | `#0284c7` | Color primario de acción |
| `--accent-rgb` | `14, 165, 233` | `2, 132, 199` | RGB channels (para alpha) |
| `--brand-blue` | `#0ea5e9` | `#0284c7` | Marca |
| `--danger` | `#f87171` | `#b91c1c` | Error/peligro |
| `--success` | `#34d399` | `#2d7d4e` | Éxito |
| `--warning` | `#fbbf24` | `#92400e` | Advertencia |

### Sidebar

| Variable | Dark | Light |
|---|---|---|
| `--sidebar-bg` | `#0f172a` | `#ffffff` |
| `--sidebar-surface` | `#172554` | `#f0f4f8` |
| `--sidebar-border` | `#1e3a5f` | `#e2e8f0` |
| `--sidebar-text` | `#e2e8f0` | `#111827` |
| `--sidebar-text-secondary` | `#94a3b8` | `#64748b` |
| `--sidebar-icon` | `#94a3b8` | `#64748b` |
| `--sidebar-active-text` | `#0ea5e9` | `#0284c7` |
| `--sidebar-accent` | `#0ea5e9` | `#0284c7` |

### Componentes

| Variable | Dark | Light |
|---|---|---|
| `--section-title-color` | `#c8d0e0` | `#374151` |
| `--table-header-bg` | `#141826` | `#e2e8f0` |
| `--table-header-color` | `var(--text-secondary)` | `#374151` |
| `--table-row-border` | `var(--border)` | `#f1f5f9` |
| `--table-row-hover` | `rgba(0,0,0,0.015)` | `#f8fafc` |
| `--scrollbar-thumb` | `var(--border)` | `#d1d5db` |
| `--scrollbar-thumb-hover` | `var(--text-secondary)` | `#9ca3af` |
| `--card-system-bg` | `var(--card-bg)` | `#f8fafc` |

### Sidebar (adicionales)

| Variable | Dark | Light |
|---|---|---|
| `--sidebar-disabled-color` | `var(--sidebar-text-secondary)` | `#475569` |
| `--sidebar-disabled-opacity` | `0.45` | `1` |
| `--sidebar-sub-disabled-opacity` | `0.5` | `0.5` |

### Shadows

| Variable | Dark | Light |
|---|---|---|
| `--focus-ring` | `0 0 0 3px rgba(accent, 0.15)` | `0 0 0 3px rgba(accent, 0.1)` |
| `--card-shadow` | `0 2px 8px rgba(0,0,0,0.06)` | `0 1px 3px / 0 4px 12px` |

### Toast (scoped en `.toast-item`)

| Variable | Valor |
|---|---|
| `--toast-bg` | `var(--card-bg)` |
| `--toast-border` | `var(--border)` |
| `--toast-text` | `var(--text-primary)` |
| `--toast-text-secondary` | `var(--text-secondary)` |
| `--toast-accent` | `var(--accent)` (cambia según el tipo) |

## Cómo agregar un nuevo color

1. Agrega la variable en `_foundation.css` dentro de `:root` (dark)
2. Agrega el valor correspondiente en `:root[data-theme="light"]`
3. Usa `var(--mi-variable)` en el componente
4. **No** agregues un `[data-theme="light"]` override — la variable lo maneja

## Cómo agregar un nuevo componente

1. Crea su partial en `public_html/assets/css/partials/`
2. Agrega el `@import` en `tailwind.css`
3. Usa solo `var(--...)` para colores, nunca valores fijos
4. Si necesita colores específicos por tema, agrega variables nuevas
