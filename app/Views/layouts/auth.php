<?php
require_once __DIR__ . '/../../Helpers/IconHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$flash_success = '';
if (!empty($_COOKIE['flash_success'])) {
    $flash_success = htmlspecialchars($_COOKIE['flash_success']);
    $isHttps = isHttps();
    setcookie('flash_success', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'Inicio de sesión'); ?> | <?php echo SITENAME; ?></title>
    <link rel="stylesheet" href="<?php echo URLROOT; ?>/assets/css/dist/tailwind.css?v=<?php echo CSS_VERSION; ?>">
    <script>
    (function(){
        var t = localStorage.getItem('theme') || 'auto';
        if (t === 'auto') {
            t = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
        }
        document.documentElement.classList.toggle('dark', t !== 'light');
        document.documentElement.classList.add('preload');
        window.addEventListener('load', function(){
            document.documentElement.classList.remove('preload');
        });
    })();
    </script>
</head>
    <body class="auth-page">
    <!-- Dummy input para absorber autofill del navegador -->
    <input type="text" aria-hidden="true" tabindex="-1" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;" autocomplete="off">

    <div class="auth-gradient"></div>
    <div class="auth-orb"></div>
    <div class="auth-orb"></div>
    <div class="auth-orb"></div>

    <div class="auth-page-content">

    <button type="button"
            class="auth-theme-btn"
            id="themeToggle"
            aria-label="Cambiar tema">
        <span id="themeIcon">
            <span class="theme-icon theme-icon-sun hidden"><?php echo icon('sun', ['size' => 15]); ?></span>
            <span class="theme-icon theme-icon-moon hidden"><?php echo icon('moon', ['size' => 15]); ?></span>
            <span class="theme-icon theme-icon-monitor hidden"><?php echo icon('monitor', ['size' => 15]); ?></span>
        </span>
    </button>

    <div class="auth-page-wrapper">
        <div class="auth-page-inner">
            <div class="auth-card">
                <?php if (defined('BRAND_LOGO') && BRAND_LOGO): ?>
                    <div class="auth-brand">
                        <img src="<?php echo BRAND_LOGO; ?>" alt="<?php echo BRAND_NAME; ?>" class="auth-brand-logo">
                        <div>
                            <h1 class="auth-brand-name"><?php echo BRAND_NAME; ?></h1>
                            <p class="auth-brand-company"><?php echo BRAND_COMPANY; ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <?php
                if (!isset($data)) {
                    $data = [];
                }
                include $view_content;
                ?>
            </div>
        </div>
    </div>

    <div class="auth-footer">
        <span class="auth-footer-text"><?php echo BRAND_COMPANY; ?></span>
        <span class="auth-footer-sep">·</span>
        <span class="auth-footer-text"><?php echo BRAND_VERSION; ?></span>
    </div>

    <script src="<?php echo URLROOT; ?>/assets/js/auth.js"></script>
    </div>
</body>
</html>
