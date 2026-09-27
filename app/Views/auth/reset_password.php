<?php
if (session_status() === PHP_SESSION_NONE) {
 session_start();
}
?>
<div class="auth-body">
 <div class="mb-7">
 <h1 class="text-lg font-semibold text-primary tracking-tight">Nueva contraseña</h1>
 <p class="text-sm text-muted mt-1">Tu contraseña debe cumplir estos requisitos</p>
 </div>

 <?php if (isset($_SESSION['error'])): ?>
 <div class="auth-alert is-error" role="alert">
 <span><?php echo htmlspecialchars($_SESSION['error']); ?></span>
 </div>
 <?php unset($_SESSION['error']); ?>
 <?php endif; ?>

 <form action="<?php echo URLROOT; ?>/auth/resetpassword" method="POST" class="auth-form">
 <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
 <input type="hidden" name="token" value="<?php echo htmlspecialchars($data['token'] ?? ''); ?>">

 <div class="mb-4">
 <label class="auth-label" for="password">Nueva contraseña</label>
 <div class="relative">
 <input type="password"
 id="password"
 name="password"
 class="auth-input auth-input--pw"
 autocomplete="new-password"
 maxlength="72"
 placeholder="••••••••"
 required>
 <button type="button"
 class="auth-pw-toggle"
 id="passwordToggle"
 aria-label="Mostrar contraseña">
 <span id="eyeIcon">
 <span class="eye-icon eye-icon-closed"><?php echo icon('eye-off', ['size' => 16]); ?></span>
 <span class="eye-icon eye-icon-open hidden"><?php echo icon('eye', ['size' => 16]); ?></span>
 </span>
 </button>
 </div>
 <ul class="pw-requirements" id="pwRequirements">
 <li data-rule="length"><span class="pw-dot">•</span> Al menos <strong>8 caracteres</strong></li>
 <li data-rule="upper"><span class="pw-dot">•</span> Una <strong>mayúscula</strong> (A-Z)</li>
 <li data-rule="lower"><span class="pw-dot">•</span> Una <strong>minúscula</strong> (a-z)</li>
 <li data-rule="number"><span class="pw-dot">•</span> Un <strong>número</strong> (0-9)</li>
 <li data-rule="symbol"><span class="pw-dot">•</span> Un <strong>símbolo</strong> (!@#$% etc.)</li>
 </ul>
 </div>

 <div class="mb-6">
 <label class="auth-label" for="password_confirm">Confirmar contraseña</label>
 <input type="password"
 id="password_confirm"
 name="password_confirm"
 class="auth-input"
 autocomplete="new-password"
 maxlength="72"
 placeholder="••••••••"
 required>
 </div>

 <button type="submit"
 class="btn-auth w-full h-11 rounded-xl text-sm font-semibold cursor-pointer transition-all disabled:opacity-40 disabled:cursor-not-allowed">
 Cambiar contraseña
 </button>
 </form>

 <p class="auth-help-text">
 <a href="<?php echo URLROOT; ?>/auth" class="auth-help-link">Volver al inicio de sesión</a>
 </p>
</div>

<script>
document.getElementById('password').addEventListener('input', function() {
 var v = this.value;
 document.querySelector('[data-rule="length"]').classList.toggle('pw-ok', v.length >= 8);
 document.querySelector('[data-rule="upper"]').classList.toggle('pw-ok', /[A-Z]/.test(v));
 document.querySelector('[data-rule="lower"]').classList.toggle('pw-ok', /[a-z]/.test(v));
 document.querySelector('[data-rule="number"]').classList.toggle('pw-ok', /\d/.test(v));
 document.querySelector('[data-rule="symbol"]').classList.toggle('pw-ok', /[^A-Za-z0-9]/.test(v));
});
</script>
