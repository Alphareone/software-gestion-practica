<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<div class="auth-body">
    <div class="auth-body-header">
        <p>Accede a tu cuenta</p>
    </div>

    <?php if ($flash_success !== ''): ?>
        <div class="auth-alert is-success" role="alert">
            <span><?php echo $flash_success; ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="auth-alert is-error" role="alert">
            <span><?php echo htmlspecialchars($_SESSION['error']); ?></span>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['warning'])): ?>
        <div class="auth-alert is-warning" role="alert">
            <span><?php echo htmlspecialchars($_SESSION['warning']); ?></span>
        </div>
        <?php unset($_SESSION['warning']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['info'])): ?>
        <div class="auth-alert is-info" role="alert">
            <span><?php echo htmlspecialchars($_SESSION['info']); ?></span>
        </div>
        <?php unset($_SESSION['info']); ?>
    <?php endif; ?>

    <form action="<?php echo URLROOT; ?>/auth/login" method="POST" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">

        <div class="mb-4">
            <label class="auth-label" for="username">Usuario</label>
            <input type="text" id="username" name="username"
                   class="auth-input"
                   autocomplete="username" maxlength="50"
                   placeholder="nombre.usuario" required>
        </div>

        <div class="mb-6">
            <label class="auth-label" for="password">Contraseña</label>
            <div class="relative">
                <input type="password" id="password" name="password"
                       class="auth-input auth-input--pw"
                       autocomplete="current-password" maxlength="72"
                       placeholder="••••••••" required>
                <button type="button" class="auth-pw-toggle" id="passwordToggle" aria-label="Mostrar contraseña">
                    <span id="eyeIcon">
                        <span class="eye-icon eye-icon-closed"><?php echo icon('eye-off', ['size' => 16]); ?></span>
                        <span class="eye-icon eye-icon-open hidden"><?php echo icon('eye', ['size' => 16]); ?></span>
                    </span>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-auth w-full h-11 rounded-xl text-sm font-semibold cursor-pointer transition-all disabled:opacity-40 disabled:cursor-not-allowed">
            Autenticar
        </button>

        <p class="auth-help-text">
            Si tienes algún problema revisa las
            <a href="<?php echo URLROOT; ?>/auth/helpauth" class="auth-help-link">opciones de ayuda</a>
        </p>
    </form>

    <div class="auth-loading-state">
        <div class="auth-loading-bar">
            <div class="auth-loading-sweep"></div>
        </div>
        <div class="auth-loading-text">Verificando credenciales</div>
    </div>
</div>
