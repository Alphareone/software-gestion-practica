# Reglas para desarrollo front-end

Estas reglas aseguran que el sistema se mantenga consistente y mantenible.

## CSS

### 1. Usa siempre variables, nunca colores fijos

```css
/* ❌ Incorrecto */
.precio-alto { color: #e74c3c; }

/* ✅ Correcto */
.precio-alto { color: var(--danger); }
```

Los únicos colores fijos permitidos son los de marca registrada
(ej: `#3483fa` de MercadoLibre) y deben tener una variable
documentada.

### 2. No agregues `[data-theme="light"]` overrides

Si un componente se ve distinto en modo claro, agrega una variable
nueva en `_foundation.css`:

```css
/* ❌ Incorrecto */
[data-theme="light"] .mi-componente { color: #374151; }

/* ✅ Correcto */
:root { --mi-color: valor-dark; }
:root[data-theme="light"] { --mi-color: valor-light; }
.mi-componente { color: var(--mi-color); }
```

### 3. Los partials son inmutables (excepto _main.css)

Los partiales fuera de `_main.css` están bien definidos. Si necesitas
modificar un componente, busca su partial específico.

`_main.css` es el archivo legacy que eventualmente se dividirá en
partials más pequeños.

### 4. No edites `dist/` directamente

`dist/tailwind.css` y `dist/tailwind.min.css` se generan automáticamente.
Siempre edita los partials y recompila.

## PHP

### 5. No uses inline styles en las vistas

```php
<!-- ❌ Incorrecto -->
<div style="color: var(--accent); padding: 1rem;">

<!-- ✅ Correcto -->
<div class="mi-componente">
```

Si un componente no tiene clase, agrégala al partial correspondiente.

### 6. No uses clases `text-gray-*` o `dark:text-gray-*` en auth

Las vistas de autenticación (`auth.php`, `login.php`) usan clases
Tailwind directas. Prefiere usar las variables del tema:

```php
<!-- ❌ Incorrecto -->
<h1 class="text-gray-900 dark:text-gray-100">

<!-- ✅ Correcto -->
<h1 class="text-primary">
```

## JavaScript

### 7. No insertes CSS desde JavaScript

```js
// ❌ Incorrecto
element.style.color = '#e74c3c';

// ✅ Correcto — usa clases
element.classList.add('text-danger');
```

Si necesitas un color dinámico, usa variables CSS:

```js
element.style.color = 'var(--danger)';
```

### 8. Toast y notificaciones

Usa siempre `showToast()` (definido en `toast.js`). Los tipos
disponibles son: `success`, `error`, `warning`, `info`, `download`.

## Flujo de trabajo

1. Edita el partial correspondiente
2. Si el componente no tiene partial, busca en `_main.css`
3. Recompila con `npm run build:css`
4. Verifica en el navegador (hard refresh)
5. Antes de commit: `npm run build:prod` para verificar que no hay warnings
