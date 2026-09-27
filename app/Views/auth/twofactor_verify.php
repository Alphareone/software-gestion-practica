<?php
if (session_status() === PHP_SESSION_NONE) {
 session_start();
}
?>
<div class="auth-body">
 <div class="mb-5">
 <p class="text-sm text-muted ">Verificación de seguridad</p>
 </div>

 <?php if (isset($_SESSION['error'])): ?>
 <div class="relative pl-4 py-2.5 mb-5 text-sm text-red-600 border-l-[3px] border-red-500" role="alert">
 <span><?php echo htmlspecialchars($_SESSION['error']); ?></span>
 </div>
 <?php unset($_SESSION['error']); ?>
 <?php endif; ?>

 <div id="otpSection">
 <form action="<?php echo URLROOT; ?>/auth/verifytwofactor" method="POST" class="auth-form">
 <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">

 <div class="mb-6">
 <label class="block text-xs font-medium text-muted mb-3">Código de 6 dígitos</label>
 <div class="otp-row">
 <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="\d" autocomplete="one-time-code" required>
 <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="\d" required>
 <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="\d" required>
 <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="\d" required>
 <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="\d" required>
 <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="\d" required>
 <input type="hidden" name="otp" id="otp-hidden">
 </div>

 <label class="flex items-center gap-2.5 cursor-pointer select-none text-sm text-muted ">
  <input type="checkbox" name="remember_device" value="1" class="auth-checkbox">
 No pedir en este dispositivo por 7 días
 </label>
 </div>
 </form>
 </div>

 <div class="hidden" id="recoveryInputWrap">
 <form action="<?php echo URLROOT; ?>/auth/verifyrecovery" method="POST" class="flex gap-2 items-center">
 <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
  <input type="text" name="recovery_code" class="auth-input font-mono tracking-widest uppercase"
 placeholder="XXXX-XXXX-XXXX" maxlength="14" autocomplete="off">
 <button type="submit" class="btn-auth h-11 px-5 rounded-xl text-sm font-semibold cursor-pointer transition-all shrink-0">Verificar</button>
 </form>
 </div>

 <div class="mt-5 text-center">
 <button type="button" class="text-xs text-muted hover:text-muted :text-muted bg-transparent border-none cursor-pointer underline underline-offset-2 transition-colors" id="recoveryToggle">Usar código de recuperación</button>
 </div>

 <div class="hidden flex-col items-center justify-center gap-5 text-center auth-loading-state">
 <div class="auth-loading-bar">
 <div class="absolute top-0 left-[-40%] w-[40%] h-full rounded-full auth-loading-sweep"></div>
 </div>
 <div class="text-sm text-muted font-medium">Verificando código</div>
 </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
 var toggle = document.getElementById('recoveryToggle');
 var wrap = document.getElementById('recoveryInputWrap');
 var otpSection = document.getElementById('otpSection');

 <?php if (!empty($data['recovery_mode'])): ?>
 if (toggle && wrap && otpSection) {
 wrap.classList.remove('hidden');
 wrap.classList.add('block');
 otpSection.style.display = 'none';
 toggle.textContent = 'Usar código de 6 dígitos';
 setTimeout(function() { wrap.querySelector('input').focus(); }, 100);
 }
 <?php endif; ?>

 if (toggle && wrap) {
 toggle.addEventListener('click', function() {
 var revealed = wrap.classList.contains('hidden');
 if (revealed) {
 wrap.classList.remove('hidden');
 wrap.classList.add('block');
 otpSection.style.display = 'none';
 this.textContent = 'Usar código de 6 dígitos';
 setTimeout(function() { wrap.querySelector('input').focus(); }, 100);
 } else {
 wrap.classList.add('hidden');
 wrap.classList.remove('block');
 otpSection.style.display = '';
 this.textContent = 'Usar código de recuperación';
 }
 });
 }

 var rcInput = wrap ? wrap.querySelector('input') : null;
 if (rcInput) {
 rcInput.addEventListener('input', function() {
 var val = this.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 12);
 var formatted = '';
 for (var i = 0; i < val.length; i++) {
 if (i === 4 || i === 8) formatted += '-';
 formatted += val[i];
 }
 this.value = formatted;
 });
 }
});
</script>
