<?php
if (session_status() === PHP_SESSION_NONE) {
 session_start();
}
?>
<div class="auth-body">
 <div class="mb-7">
 <h1 class="text-lg font-semibold text-primary tracking-tight">Recordar usuario</h1>
 <p class="text-sm text-muted mt-1">Ingresa tu correo electrónico registrado</p>
 </div>

 <?php if (isset($_SESSION['error'])): ?>
 <div class="auth-alert is-error" role="alert">
 <span><?php echo htmlspecialchars($_SESSION['error']); ?></span>
 </div>
 <?php unset($_SESSION['error']); ?>
 <?php endif; ?>

 <form action="<?php echo URLROOT; ?>/auth/forgotusername" method="POST" class="auth-form">
 <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">

 <div class="mb-6">
 <label class="auth-label" for="email">Correo electrónico</label>
 <input type="email"
 id="email"
 name="email"
 class="auth-input"
 autocomplete="email"
 placeholder="correo@ejemplo.com"
 required>
 </div>

 <button type="submit"
 class="btn-auth w-full h-11 rounded-xl text-sm font-semibold cursor-pointer transition-all disabled:opacity-40 disabled:cursor-not-allowed">
 Enviar recordatorio
 </button>
 </form>

 <div class="mt-5">
 <p class="auth-help-text">
 <a href="<?php echo URLROOT; ?>/auth/helpauth" class="auth-help-link">Volver a ayuda</a>
 </p>
 </div>
</div>
