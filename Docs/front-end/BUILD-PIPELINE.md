# Build pipeline

## Scripts disponibles

```bash
# Desarrollo (rápido, sin minificar)
npm run build:css

# Desarrollo con sourcemaps (debugging)
npm run build:dev

# Producción (minificado + optimizado)
npm run build:prod

# Watch mode (re-compila automáticamente al guardar)
npm run watch:css

# Limpiar compilados
npm run clean
```

## Output

| Script | Archivo generado | Tamaño aprox |
|---|---|---|
| `build:css` | `dist/tailwind.css` | ~320 KB |
| `build:dev` | `dist/tailwind.css` + `.map` | ~320 KB + sourcemap |
| `build:prod` | `dist/tailwind.min.css` | ~250 KB |

## Carga en el navegador

Los layouts cargan el CSS según el entorno:

```
app/Views/layouts/app.php
app/Views/layouts/auth.php
app/Views/info/index.php
```

Todas usan:
```php
<link rel="stylesheet"
      href="<?php echo URLROOT; ?>/assets/css/dist/tailwind.css?v=<?php echo CSS_VERSION; ?>">
```

La versión (`CSS_VERSION`) se calcula con `filemtime()` del archivo
compilado, forzando el cache busting al recompilar.

## Flujo de trabajo recomendado

```bash
# 1. En desarrollo, deja watch corriendo
npm run watch:css

# 2. Edita los partials en public_html/assets/css/partials/
#    (no editar dist/ — se sobreescribe)

# 3. Antes de commit, asegúrate de compilar
npm run build:css
```

## Solución de problemas

| Problema | Causa | Solución |
|---|---|---|
| `Can't resolve 'tailwind.config.js'` | Ruta relativa incorrecta | El `@config` debe estar en `tailwind.css` (no en partials) |
| Warnings de "dangling combinator" | Comentario pegado a `}` | Buscar `}/*` en los partials y agregar un salto de línea |
| Los cambios no se ven | Cache del navegador | Hard refresh o verificar que `CSS_VERSION` cambió |
