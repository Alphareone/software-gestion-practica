<?php
if (session_status() === PHP_SESSION_NONE) {
 session_start();
}
?>
<div class="auth-body">
 <div class="mb-7">
 <h1 class="text-lg font-semibold text-primary tracking-tight">Contactar a soporte</h1>
 <p class="text-sm text-muted mt-1">Estamos para ayudarte</p>
 </div>

 <div class="text-sm text-muted leading-relaxed space-y-4">
 <p>
 Para resolver cualquier inconveniente puedes escribir al correo de contacto o comunicarte directamente con tu jefe de área para una atención más rápida.
 </p>

 <div class="btn-auth w-full h-11 rounded-xl text-sm inline-flex items-center gap-2.5 px-4">
 <?php echo icon('mail', ['size' => 16]); ?>
 <span class="font-mono tracking-tight"><?php echo defined('APP_EMAIL') ? htmlspecialchars(APP_EMAIL) : 'soporte@ofertasimperdibles.cl'; ?></span>
 </div>
 </div>

 <p class="auth-help-text">
 <a href="<?php echo URLROOT; ?>/auth/helpauth" class="auth-help-link">Volver a ayuda</a>
 </p>
</div>
