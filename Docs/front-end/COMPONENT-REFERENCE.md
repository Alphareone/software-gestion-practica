# Referencia de componentes

Mapa rápido para encontrar dónde está definido cada componente visual.

## Partial files y su contenido

### `_foundation.css`
- `@theme` block: design tokens (variables → Tailwind utilities)
- `@layer base`: `:root` y `:root[data-theme="light"]` — todas las variables CSS
- `@layer components`: `.btn-primary`, `.btn-secondary`, `.card`, `.input`

### `_sidebar.css`
- `.sidebar`, `.sidebar-brand`, `.sidebar-search`
- `.nav-sections`, `.nav-item`, `.nav-link`
- `.nav-sub-links`, `.nav-collapse-chevron`
- `.sidebar-overlay`, `.mobile-menu-btn`
- Variables `--sidebar-*`

### `_layout.css`
- `.dashboard-app` (layout principal)
- `header`, `.header-left`, `.header-right`
- `.user-profile`, `.user-dropdown-menu`, `.theme-option`
- `.main-content`, `.container`
- `.dashboard-title`, `.stats-grid`

### `_notifications.css`
- `.notif-btn`, `.notif-badge`, `.notif-panel`
- `.notif-item`, `.notif-unread`, `.notif-dot`

### `_auth.css`
- `.auth-gradient`, `.auth-orb` (fondo animado login)
- `.auth-card`, `.auth-input`, `.auth-label`
- `.auth-alert.is-*` (success/error/warning/info)
- `.auth-theme-btn`, `.auth-pw-toggle`
- `.auth-page-wrapper`, `.auth-brand`, `.auth-footer`
- `.btn-auth`, `.input-auth`, `.otp-input`
- `input:-webkit-autofill`

### `_toast.css`
- `.toast-container`, `.toast-item`
- `.toast-accent-bar`, `.toast-icon`
- `.toast-title`, `.toast-message`, `.toast-close`
- `.toast-progress`, `toast-*` variant types

### `_prices.css`
Price wizard + batch upload/processing:
- `.pw-*` (step indicator, card, buttons, progress, logs, result)
- `.upload-card`, `.preview-card`, `.process-card`
- `.file-drop`, `.file-validation`
- `.btn-upload`, `.btn-execute`, `.btn-cancel-batch`
- `.incomplete-banner`, `.price-breadcrumb`, `.price-steps`

### `_price-history.css`
- `.history-header`, `.filter-form`, `.hcs-*`

### `_settings-admin.css`
- `.settings-nav`, `.settings-content`, `.settings-panel`
- `.purge-card`, `.purge-grid`

### `_price-history-v3.css`
- `.ph-*` (batch cards, stats, progress)
- `.hb-*` (history batch)

### `_users.css`
Users module (table + form + edit):
- `.ut-*` (table, search, buttons, toggle, role filter, status, 2FA)
- `.uf-*` (user form, avatar, card)
- `.ue-*` (user edit layout, danger zone, modal)
- `.umodal-*` (unified modal)

### `_connections.css`
- `.connect-*` (steps, copy field, benefits, help)
- `.store-*` (list, card, grid, actions)
- `.kpi-card`, `.skeleton-loading`, `.charts-grid`

### `_ml.css`
MercadoLibre connect + audit:
- `.btn-connect`, `.btn-select`, `.btn-disconnect`
- `.store-icon`, `.alert-config`
- `.audit-batch-*`, `.audit-table`, `.audit-diff-*`
- `.ml-header`, `.ml-dashboard`, `.ml-status-*`

### `_price-hub.css`
Price hub v2 (dashboard de precios):
- `.phub-*` (cards, metrics, stats, banner)
- `.products-table`, `.results-table`, `.price-cell`
- `.status-badge`, `.recent-batches`, `.rb-*`

### `_activity.css`
Activity log v2:
- `.activity-*` (filters, rows, pagination)
- `.al-table`, `.al-page`, `.al-delete-btn`
- `.al-row-*` (auth, modify, delete, export)

### `_webproducts.css`
WebProducts module (self-contained):
- `.wp-*` (wrap, section, card, alert, badge, table, modal, dropzone, skeleton, etc.)

### `_sync-widget.css`
- `.sync-widget`, `.sync-widget-toggle`
- `.sync-widget-panel`, `.sync-widget-store`
- `.sync-dot` (idle/syncing/error)

### `_utilities.css`
- `::-webkit-scrollbar-thumb` (scrollbar global)
- `input:focus`, `select:focus`, `textarea:focus`
- `@media (prefers-reduced-motion)`
- `.card-system`
