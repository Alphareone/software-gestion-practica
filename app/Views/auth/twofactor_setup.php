<?php
if (session_status() === PHP_SESSION_NONE) {
 session_start();
}
?>
<div class="auth-body">
 <div class="mb-5">
 <p class="text-sm text-muted ">Configurar 2FA</p>
 </div>

 <?php if (isset($_SESSION['error'])): ?>
 <div class="relative pl-4 py-2.5 mb-5 text-sm text-red-600 border-l-[3px] border-red-500" role="alert">
 <span><?php echo htmlspecialchars($_SESSION['error']); ?></span>
 </div>
 <?php unset($_SESSION['error']); ?>
 <?php endif; ?>

 <div class="text-center mb-6">
 <img src="<?php echo htmlspecialchars($data['qrcode_url'] ?? ''); ?>"
 alt="QR Code"
 class="w-44 h-44 inline-block mb-6">
 <p class="text-xs text-muted font-medium">Escanea con tu aplicación de confianza</p>
 </div>

 <form action="<?php echo URLROOT; ?>/auth/verifytwofactor" method="POST" class="auth-form">
 <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">

 <div class="mb-6">
 <label class="block text-xs font-medium text-muted mb-3">Código de verificación</label>
 <div class="otp-row">
 <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="\d" autocomplete="one-time-code" required>
 <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="\d" required>
 <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="\d" required>
 <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="\d" required>
 <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="\d" required>
 <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="\d" required>
 <input type="hidden" name="otp" id="otp-hidden">
 </div>
 </div>

 <button type="submit" class="btn-auth w-full h-11 rounded-xl text-sm font-semibold cursor-pointer transition-all">
 Activar
 </button>
 </form>

 <div class="hidden flex-col items-center justify-center gap-5 text-center auth-loading-state">
 <div class="auth-loading-bar">
 <div class="absolute top-0 left-[-40%] w-[40%] h-full rounded-full auth-loading-sweep"></div>
 </div>
 <div class="text-sm text-muted font-medium">Verificando código</div>
 </div>
</div>
