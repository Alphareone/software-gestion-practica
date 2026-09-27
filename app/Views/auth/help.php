<?php
if (session_status() === PHP_SESSION_NONE) {
 session_start();
}
?>
<div class="auth-body">
 <div class="mb-7">
 <h1 class="text-lg font-semibold text-primary tracking-tight">Ayuda / Recuperación</h1>
 <p class="text-sm text-muted mt-1">Elige la opción que necesites</p>
 </div>

 <?php if (isset($_SESSION['error'])): ?>
 <div class="auth-alert relative pl-4 py-2.5 mb-5 text-sm text-red-600 border-l-[3px] border-red-500" role="alert">
 <span><?php echo htmlspecialchars($_SESSION['error']); ?></span>
 </div>
 <?php unset($_SESSION['error']); ?>
 <?php endif; ?>

 <div class="flex flex-col gap-3">
 <a href="<?php echo URLROOT; ?>/auth/forgotpassword"
 class="btn-auth w-full h-11 rounded-xl text-sm font-semibold cursor-pointer transition-all inline-flex items-center gap-2.5 px-4 no-underline">
 <?php echo icon('lock', ['size' => 16]); ?>
 Olvidé mi contraseña
 </a>

 <a href="<?php echo URLROOT; ?>/auth/forgotusername"
 class="btn-auth w-full h-11 rounded-xl text-sm font-semibold cursor-pointer transition-all inline-flex items-center gap-2.5 px-4 no-underline">
 <?php echo icon('user', ['size' => 16]); ?>
 Olvide mi usuario
 </a>

 <a href="<?php echo URLROOT; ?>/auth/contact"
 class="btn-auth w-full h-11 rounded-xl text-sm font-semibold cursor-pointer transition-all inline-flex items-center gap-2.5 px-4 no-underline">
 <?php echo icon('mail', ['size' => 16]); ?>
 Contactar a soporte
 </a>
 </div>

 <p class="auth-help-text">
 ¿Recordaste tus datos?<br>
 <a href="<?php echo URLROOT; ?>/auth" class="auth-help-link">Iniciar sesión</a>
 </p>
</div>
