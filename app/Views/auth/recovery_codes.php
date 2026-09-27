<?php
if (session_status() === PHP_SESSION_NONE) {
 session_start();
}
?>
<div class="auth-body">
 <div class="mb-5">
 <p class="text-sm text-muted ">Códigos de recuperación</p>
 </div>

 <div class="mb-5 text-sm text-muted leading-relaxed">
 Guarda estos códigos en un lugar seguro. Cada código solo se puede usar <strong class="text-muted ">una vez</strong>.<br>
 Si pierdes el acceso a tu aplicación de autenticación, podrás usar uno de estos códigos para iniciar sesión.
 </div>

 <div class="codes-box">
 <div class="flex flex-col gap-2">
 <?php foreach ($data['codes'] as $index => $code): ?>
 <div class="flex items-center gap-3">
 <span class="text-xs text-muted font-semibold w-5 text-right shrink-0"><?php echo str_pad($index + 1, 2, '0', STR_PAD_LEFT); ?></span>
 <code class="font-mono text-sm font-bold text-primary tracking-wide"><?php echo htmlspecialchars($code); ?></code>
 </div>
 <?php endforeach; ?>
 </div>
 </div>

 <button type="button" id="downloadCodes" class="auth-help-link">
 Descargar códigos
 </button>

 <form action="<?php echo URLROOT; ?>/auth/confirmrecoverycodes" method="POST" class="mt-6">
 <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
 <button type="submit" class="auth-help-link">
 He guardado mis códigos
 </button>
 </form>
</div>

<script>
document.getElementById('downloadCodes')?.addEventListener('click', function() {
 var codes = <?php echo json_encode($data['codes']); ?>;
 var brand = <?php echo json_encode(BRAND_NAME); ?>;
 var lines = ['Códigos de recuperación - ' + brand, '', 'Guarda estos códigos en un lugar seguro.', 'Cada código solo se puede usar una vez.', '', '─────────────────────────', ''];
 codes.forEach(function(code, i) {
 var num = String(i + 1).padStart(2, '0');
 lines.push(num + ' ' + code);
 });
 lines.push('', '─────────────────────────', '', 'Generado el ' + new Date().toLocaleDateString('es-CL'));
 var blob = new Blob([lines.join('\n')], { type: 'text/plain;charset=utf-8' });
 var url = URL.createObjectURL(blob);
 var a = document.createElement('a');
 a.href = url;
 a.download = 'codigos-recuperacion.txt';
 document.body.appendChild(a);
 a.click();
 a.remove();
 URL.revokeObjectURL(url);
});
</script>
