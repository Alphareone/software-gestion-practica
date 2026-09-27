# Refactoring de estilos — Resumen

**Fecha**: 18 de junio de 2026
**Autor**: opencode (asistente IA)

## Problemas encontrados y corregidos

### Bug: `--bg-secondary` no definido

La variable `--bg-secondary` se usaba en **4 componentes** pero nunca estaba
definida en `:root`. En modo claro el valor caía a `transparent`, haciendo
invisibles los backgrounds de:

- `.pw-preview-metric`
- `.pw-preview-table th`
- `.pw-preview-table-footer`
- `.audit-card-header code`

**Fix**: Se agregó la variable en `:root` y `:root[data-theme="light"]`.

### Bug: `--wp-text` con referencia circular

```css
/* Antes (nunca resolvía a un color real) */
--wp-text: var(--wp-text);

/* Después */
--wp-text: var(--text-primary);
```

### Dead code: `--wp-shadow-md`

Definido pero nunca usado en ningún lugar del proyecto. Se eliminó.

## Cambios de arquitectura

### Split del monolito (1 archivo → 9 partials)

| Antes | Después |
|---|---|
| `tailwind.css` (11.500+ líneas) | `tailwind.css` entry (18 líneas) + 8 partials |

Los partials se importan vía CSS `@import` y se resuelven en tiempo de
compilación. El archivo compilado final (`dist/tailwind.css`) contiene
exactamente el mismo CSS que antes.

### Nuevos design tokens (9 variables agregadas)

| Variable | Dark | Light | Propósito |
|---|---|---|---|
| `--bg-secondary` | `#15182a` | `#f8fafc` | Fondos secundarios dentro de cards |
| `--section-title-color` | `#c8d0e0` | `#374151` | Títulos de sección |
| `--table-header-bg` | `#141826` | `#e2e8f0` | Cabeceras de tabla |
| `--table-header-color` | `var(--text-secondary)` | `#374151` | Texto en cabeceras |
| `--table-row-border` | `var(--border)` | `#f1f5f9` | Bordes entre filas |
| `--table-row-hover` | `rgba(0,0,0,0.015)` | `#f8fafc` | Hover en filas |
| `--scrollbar-thumb` | `var(--border)` | `#d1d5db` | Scrollbar |
| `--scrollbar-thumb-hover` | `var(--text-secondary)` | `#9ca3af` | Scrollbar hover |
| `--focus-ring` | `0 0 0 3px rgba(accent, 0.15)` | `0 0 0 3px rgba(accent, 0.1)` | Focus en inputs |
| `--card-shadow` | `0 2px 8px rgba(0,0,0,0.06)` | `0 1px 3px / 0 4px 12px` | Sombra de cards |

### Overrides eliminados

| Tipo | Antes | Después |
|---|---|---|
| `[data-theme="light"]` blocks | **89** | **41** |
| `[data-theme="dark"]` blocks | ~15 | ~5 |

Se migraron a variables CSS los overrides de:

- `.ut-table`, `.products-table`, `.al-table` (cabeceras, bordes, hover)
- `.audit-batch-row`, `.audit-batch-dot`, `.audit-batch-group-label`
- `.dash-section-title`
- `.card` shadow
- `auth-fade-out` duplicados
- `.modal-card` / `.rename-input` duplicados

Los 41 overrides restantes corresponden a componentes con colores de marca
de MercadoLibre (botones connect/disconnect/reactivate, copy fields) que
requieren un tratamiento específico.

### Build pipeline profesional

| Script | Uso | Características |
|---|---|---|
| `npm run build:css` | Desarrollo | Build rápido sin minificar |
| `npm run build:dev` | Debug | Con sourcemaps |
| `npm run build:prod` | Producción | Minificado + optimizado |
| `npm run watch:css` | Live dev | Watch mode |
| `npm run clean` | Mantenimiento | Limpia dist/ |

### Cache busting

Se reemplazó el `?v=71` hardcodeado por `?v=CSS_VERSION`, que usa el
`filemtime()` del CSS compilado. Se actualiza automáticamente al recompilar.

## Componentes cuyos estilos base se actualizaron

- Tablas (`.ut-table`, `.products-table`, `.al-table`, `.audit-batch-row`)
- Títulos de sección (`.dash-section-title`)
- Scrollbar global (`::-webkit-scrollbar-thumb`)
- Focus ring (`input:focus`, `select:focus`, `textarea:focus`)
- Cards (`.card` - shadow unificada)
- Auth fade-out (simplificado)

## Pendiente para futuras iteraciones

1. Eliminar los ~34 overrides `[data-theme="light"]` restantes (botones ML — colores de marca)
2. Consolidar definiciones duplicadas de `.alert-config`
3. Migrar inline styles remanentes en `content-product-detail.php` (overlay JS)

---

## Histórico de cambios

### 18/06/2026 — Sesión 1 (refactoring inicial)
- Bugs corregidos: `--bg-secondary`, `--wp-text` circular, `--wp-shadow-md`
- Nuevos tokens: 10 variables de diseño agregadas
- Overrides eliminados: ~48 bloques `[data-theme="light"]`
- Split inicial: 1 → 9 partials
- Build pipeline: scripts dev/prod/minify/sourcemaps
- Cache busting: `CSS_VERSION` dinámico
- Docs creados: 7 archivos en `Docs/`

### 18/06/2026 — Sesión 2 (Fase 4, 5, 6)
- **Fase 4**: Migración masiva de inline styles PHP → CSS
  - 13 archivos PHP modificados, ~80 inline styles eliminados
  - 2 `<style>` blocks movidos a CSS
  - Todas las vistas auth (8) sin clases `text-gray-*` ni `dark:*`
- **Fase 5**: Auth layout refactorizado
  - `<style>` block eliminado (gradient/orbs → `_auth.css`)
  - Theme toggle: `bg-white/70 text-gray-400` → `.auth-theme-btn`
  - 20+ clases CSS nuevas para auth
- **Fase 6**: `.card` consolidado (7 → 2 definiciones, sin `!important`)
- **Split `_main.css`**: ~10.600 líneas → 10 partials funcionales
- Overrides adicionales eliminados: sidebar (3), card-system (1)
- **Total overrides eliminados: ~55** (de 89 a 34)
