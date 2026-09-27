<?php
$roleLabels = [
    'owner' => 'Dueño',
    'admin' => 'Administrador',
    'logistics' => 'Logística',
    'sales' => 'Ventas',
    'products' => 'Productos',
    'user' => 'Usuario',
];
$roleLabel = $roleLabels[$role] ?? 'Usuario';

$mlCredsMissing = false;
if (!empty($_SESSION['is_admin']) && class_exists('AppSettingModel')) {
    try {
        $mlDb = new Database();
        $mlModel = new AppSettingModel($mlDb);
        $mlAppId = $mlModel->get('ml_app_id');
        $mlSecret = $mlModel->get('ml_client_secret');
        $mlCredsMissing = empty($mlAppId) || empty($mlSecret);
    } catch (Exception $e) {}
}

$navSections = [
    'main' => [
        'title' => 'Principal',
        'admin_only' => false,
        'items' => [
            [
                'type' => 'link',
                'label' => 'Inicio',
                'icon' => 'house',
                'route' => '/dashboard',
                'nav' => 'dashboard',
            ],
            [
                'type' => 'link',
                'label' => 'Conexiones',
                'icon' => 'link-2',
                'route' => '/connections',
                'nav' => 'connections',
            ],
            [
                'type' => 'link',
                'label' => 'Productos ML',
                'icon' => 'package',
                'route' => '/connections/products',
                'nav' => 'products',
            ],
            [
                'type' => 'link',
                'label' => 'Catálogo Central',
                'icon' => 'database',
                'route' => '/productcentral',
                'nav' => 'productcentral',
            ],
            [

                'type' => 'collapse',
                'label' => 'Auditorías',
                'icon' => 'book-open',
                'nav' => 'audit',
                'children' => [
                    ['label' => 'Panel de auditorías', 'route' => '/connections/auditHub', 'nav' => 'audit', 'type' => ''],
                    ['label' => 'Auditar precios', 'route' => '/connections/auditPrices', 'nav' => 'audit', 'type' => 'prices'],
                    ['label' => 'Auditar stock', 'route' => '/connections/auditStock', 'nav' => 'audit', 'type' => 'stock'],
                    ['label' => 'Ver historial', 'route' => '/connections/auditHistory', 'nav' => 'audit', 'type' => 'history'],

                ],
            ],
            [
                'type' => 'collapse',
                'label' => 'Walmart',
                'icon' => 'store',
                'nav' => ['walmart', 'walmart-products', 'walmart-updates', 'walmart-history', 'walmart-audit'],
                'children' => [
                    ['label' => 'Panel Walmart', 'route' => '/walmart', 'nav' => 'walmart'],
                    ['label' => 'Productos', 'route' => '/walmart/products', 'nav' => 'walmart-products'],
                    ['label' => 'Actualizar precios/stock', 'route' => '/walmart/updates', 'nav' => 'walmart-updates'],
                    ['label' => 'Historial de actualizaciones', 'route' => '/walmart/updateHistory', 'nav' => 'walmart-history'],
                    ['label' => 'Panel de auditorías', 'route' => '/walmart/auditHub', 'nav' => 'walmart-audit'],
                    ['label' => 'Auditar precios', 'route' => '/walmart/auditPrices', 'nav' => 'walmart-audit'],
                    ['label' => 'Auditar stock', 'route' => '/walmart/auditStock', 'nav' => 'walmart-audit'],
                    ['label' => 'Ver historial de auditorías', 'route' => '/walmart/auditHistory', 'nav' => 'walmart-audit'],
                ],
            ],
            [
                'type' => 'collapse',
                'label' => 'Cambios masivos',
                'icon' => 'zap',
                'nav' => 'bulk',
                'badge' => 'Prox',
                'children' => [
                    ['label' => 'Panel de cambios masivos', 'route' => '#', 'nav' => 'bulk-panel', 'disabled' => true],
                    ['label' => 'Cambiar precios', 'route' => '#', 'nav' => 'bulk-prices', 'disabled' => true],
                    ['label' => 'Cambiar stock', 'route' => '#', 'nav' => 'bulk-stock', 'disabled' => true],
                ],
            ],
            [
                'type' => 'collapse',
                'label' => 'Creación Web',
                'icon' => 'layout-grid',
                'nav' => ['webproducts', 'webproducts-review'],
                'children' => [
                    ['label' => 'Gestión', 'route' => '/webproducts', 'nav' => 'webproducts'],
                    ['label' => 'Revisar plantillas', 'route' => '/webproducts/review', 'nav' => 'webproducts-review'],
                ],
            ],
            [
                'type' => 'link',
                'label' => 'Creación de Productos',
                'icon' => 'circle-plus',
                'route' => '/productcreator',
                'nav' => 'productcreator',
            ],
            [
                'type' => 'collapse',
                'label' => 'Cencosud',
                'icon' => 'shopping-bag',
                'nav' => ['cencosud', 'cencosud-products', 'cencosud-updates', 'cencosud-history', 'cencosud-reports'],
                'children' => [
                    ['label' => 'Panel Cencosud', 'route' => '/cencosud', 'nav' => 'cencosud'],
                    ['label' => 'Productos', 'route' => '/cencosud/products', 'nav' => 'cencosud-products'],
                    ['label' => 'Actualizar precios/stock', 'route' => '/cencosud/updates', 'nav' => 'cencosud-updates'],
                    ['label' => 'Historial', 'route' => '/cencosud/updateHistory', 'nav' => 'cencosud-history'],
                    ['label' => 'Reportes', 'route' => '/cencosud/reports', 'nav' => 'cencosud-reports'],
                ],
            ],
            [
                'type' => 'link',
                'label' => 'Reportes',
                'icon' => 'bar-chart-3',
                'route' => '/reports',
                'nav' => 'reports',
            ],
        ],
    ],
    'admin' => [
        'title' => 'Administración',
        'admin_only' => true,
        'items' => [
            [
                'type' => 'link',
                'label' => 'Usuarios',
                'icon' => 'user-cog',
                'route' => '/users',
                'nav' => 'users',
            ],
            [
                'type' => 'link',
                'label' => 'Actividad',
                'icon' => 'clock-3',
                'route' => '/admin/activityLog',
                'nav' => 'activity',
            ],
            [
                'type' => 'link',
                'label' => 'Correos',
                'icon' => 'mail',
                'route' => '/admin/emailLogs',
                'nav' => 'email-logs',
            ],
            [
                'type' => 'link',
                'label' => 'Credenciales ML',
                'icon' => 'shield-check',
                'route' => '/admin/mlCredentials',
                'nav' => 'ml-credentials',
            ],
            [
                'type' => 'link',
                'label' => 'Gesti&oacute;n ML',
                'icon' => 'shopping-bag',
                'route' => '/connections/adminstores',
                'nav' => 'ml-admin',
            ],
            [
                'type' => 'link',
                'label' => 'Gestión Walmart',
                'icon' => 'store',
                'route' => '/walmart/adminstores',
                'nav' => 'walmart-admin',
            ],
            [
                'type' => 'link',
                'label' => 'Gestión Cencosud',
                'icon' => 'shopping-bag',
                'route' => '/cencosud/adminstores',
                'nav' => 'cencosud-admin',
            ],
            [
                'type' => 'link',
                'label' => 'Base de Datos',
                'icon' => 'database',
                'route' => '/admin/databaseTools',
                'nav' => 'database-tools',
            ],
        ],
    ],
];

function isNavActive($navValue, $current_nav): bool {
    if (is_array($navValue)) {
        return in_array($current_nav, $navValue, true);
    }
    return $navValue === $current_nav;
}

function isCollapseOpen($item, string $current_nav): bool {
    if (!isset($item['nav'])) return false;
    return isNavActive($item['nav'], $current_nav);
}

function isChildActive(array $child, string $current_nav, array $data): bool {
    if (isset($child['disabled']) && $child['disabled']) {
        return false;
    }
    if (($child['nav'] ?? '') !== $current_nav) {
        return false;
    }
    if (array_key_exists('type', $child)) {
        $currentType = $data['type'] ?? '';
        return $currentType === $child['type'];
    }
    return true;
}

$data = $data ?? [];
?>
<aside class="sidebar" aria-label="Navegación principal">
    <div class="sidebar-brand">
        <?php if (defined('BRAND_LOGO') && BRAND_LOGO): ?>
            <img class="brand-logo" src="<?php echo BRAND_LOGO; ?>" alt="<?php echo BRAND_NAME; ?>">
        <?php elseif (defined('BRAND_ICON') && BRAND_ICON): ?>
            <span class="brand-icon"><?php echo icon(BRAND_ICON, ['size' => 22]); ?></span>
        <?php endif; ?>
        <div>
            <span class="brand-name"><?php echo BRAND_NAME; ?></span>
            <span class="brand-sub"><?php echo BRAND_COMPANY; ?></span>
        </div>
    </div>

    <div class="sidebar-search">
        <?php echo icon('search', ['size' => 14, 'class' => 'search-icon']); ?>
        <input type="text" class="search-input" placeholder="Buscar en el menú..." id="sidebarSearch" autocomplete="chrome-off">
    </div>

    <nav class="nav-sections" aria-label="Secciones">
        <?php foreach ($navSections as $section): ?>
            <?php if ($section['admin_only'] && empty($is_admin)) continue; ?>
            <div class="nav-section">
                <div class="section-title">
                    <span><?php echo $section['title']; ?></span>
                </div>
                <ul class="nav-links">
                    <?php foreach ($section['items'] as $item): ?>
                        <?php if ($item['type'] === 'link'): ?>
                            <li class="nav-item">
                                <a href="<?php echo URLROOT . $item['route']; ?>"
                                   class="nav-link <?php echo isNavActive($item['nav'], $current_nav) ? 'active' : ''; ?>">
                                    <span class="nav-icon"><?php echo icon($item['icon'], ['size' => 18]); ?></span>
                                    <span class="nav-text"><?php echo $item['label']; ?><?php if (($item['nav'] ?? '') === 'ml-credentials'): ?> <span class="ml-alert-badge <?php echo $mlCredsMissing ? '' : 'ml-alert-badge--ok'; ?>"></span><?php endif; ?></span>
                                </a>
                            </li>
                        <?php elseif ($item['type'] === 'collapse'): ?>
                            <?php $isOpen = isCollapseOpen($item, $current_nav); ?>
                            <?php $hasBadge = !empty($item['badge']); ?>
                            <li class="nav-item nav-item-collapse <?php echo $isOpen ? 'open' : ''; ?><?php echo $hasBadge ? ' disabled' : ''; ?>">
                                <button type="button"
                                        class="nav-link nav-collapse-toggle <?php echo $isOpen ? 'active' : ''; ?>"
                                        aria-expanded="<?php echo $isOpen ? 'true' : 'false'; ?>">
                                    <span class="nav-icon"><?php echo icon($item['icon'], ['size' => 18]); ?></span>
                                    <span class="nav-text"><?php echo $item['label']; ?> <?php if (!empty($item['badge'])): ?><span class="nav-disabled-badge"><?php echo $item['badge']; ?></span><?php endif; ?></span>
                                    <span class="nav-collapse-chevron"><?php echo icon('chevron-right', ['size' => 14]); ?></span>
                                </button>
                                <ul class="nav-sub-links">
                                    <?php foreach ($item['children'] as $child): ?>
                                        <li>
                                            <?php if (!empty($child['disabled'])): ?>
                                                <a class="nav-sub-link disabled" aria-disabled="true"><?php echo $child['label']; ?></a>
                                            <?php else: ?>
                                                <?php 
                                                    $isExternal = !empty($child['external']); 
                                                    $href = $isExternal ? $child['route'] : URLROOT . $child['route'];
                                                ?>
                                                <a href="<?php echo $href; ?>"
                                                   <?php echo $isExternal ? 'target="_blank" rel="noopener"' : ''; ?>
                                                   <?php if (!empty($child['confirm'])): ?>
                                                   onclick="event.preventDefault(); openConfirm({title: 'Redirección', message: <?php echo htmlspecialchars(json_encode($child['confirm']), ENT_QUOTES, 'UTF-8'); ?>, confirmText: 'Continuar', onConfirm: function() { window.open('<?php echo $href; ?>', '_blank'); }});"
                                                   <?php endif; ?>
                                                   class="nav-sub-link <?php echo isChildActive($child, $current_nav, $data) ? 'active' : ''; ?>">
                                                    <?php echo $child['label']; ?>
                                                </a>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </nav>

</aside>
