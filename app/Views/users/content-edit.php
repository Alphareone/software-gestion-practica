<?php require_once __DIR__ . '/../../Helpers/IconHelper.php'; ?>
<?php
  $u = $data['usuario'] ?? null;
  $stats = $data['user_stats'] ?? [];
  $st = $u->status ?? 'active';
  $role = $stats['role'] ?? 'user';
  $twofaEnabled = $stats['twofa_enabled'] ?? false;
  $lastLoginDate = $stats['last_login_at'] ? date('d/m/Y H:i', strtotime($stats['last_login_at'] . ' UTC')) : 'Nunca';
  $isOnline = $stats['is_online'] ?? false;
  $roleLabels = [
      'owner' => 'Dueño',
      'admin' => 'Administrador',
      'logistics' => 'Logística',
      'sales' => 'Ventas',
      'products' => 'Productos',
      'user' => 'Usuario',
  ];
  $roleLabel = $roleLabels[$role] ?? 'Usuario';
  $lastActivityStr = $stats['last_activity_at'] ?? null;
  $lastActivityAgo = $lastActivityStr ? (time() - strtotime($lastActivityStr . ' UTC')) : null;
  $lastActivityLabel = $lastActivityAgo !== null ? ($lastActivityAgo < 120 ? 'Ahora' : (floor($lastActivityAgo / 60) . ' min')) : 'Desconocida';

  $roleOptions = '';
  foreach ($roleLabels as $val => $label) {
      if ($val === 'owner') continue;
      $sel = $role === $val ? 'selected' : '';
      $roleOptions .= '<option value="' . $val . '" ' . $sel . '>' . $label . '</option>';
  }
?>
<div class="uf-layout">
  <div class="uf-card">
    <div class="uf-profile">
      <div class="uf-profile-info">
        <h2><?php echo htmlspecialchars($u->username ?? ''); ?></h2>
        <span class="uf-profile-email"><?php echo htmlspecialchars($u->email ?? ''); ?></span>
        <span class="uf-profile-meta">
          <span style="display:inline-flex;align-items:center;gap:4px;">
            <span style="width:6px;height:6px;border-radius:50%;background:<?php echo $st === 'active' ? '#10b981' : '#ef4444'; ?>;display:inline-block;"></span>
            <?php echo $st === 'active' ? 'Cuenta activa' : 'Cuenta inactiva'; ?>
          </span>
          <span class="uf-divider">·</span>
          <span>ID #<?php echo (int)($u->id ?? 0); ?></span>
          <span class="uf-divider">·</span>
          <span>Registro <?php echo date('d/m/Y', strtotime($u->created_at ?? 'now')); ?></span>
        </span>
      </div>
    </div>

    <form method="post" action="<?php echo URLROOT; ?>/users/update/<?php echo (int)($u->id ?? 0); ?>" class="uf-form" id="editForm">

      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? '', ENT_QUOTES); ?>">

      <!-- ═══ Contacto ═══ -->
      <div class="uf-section">
        <h3>Información de contacto</h3>

        <div class="uf-row">
          <div class="uf-field uf-half">
            <label for="first_name">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              Nombres <span style="color:#ef4444;">*</span>
            </label>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($u->first_name ?? '', ENT_QUOTES); ?>" maxlength="100" placeholder="Nombre" required>
          </div>
          <div class="uf-field uf-half">
            <label for="last_name">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              Apellidos <span style="color:#ef4444;">*</span>
            </label>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($u->last_name ?? '', ENT_QUOTES); ?>" maxlength="100" placeholder="Apellido" required>
          </div>
        </div>

        <div class="uf-field">
          <label for="username">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Nombre de usuario
          </label>
          <input type="text" id="username" value="<?php echo htmlspecialchars($u->username ?? '', ENT_QUOTES); ?>" disabled>
          <span class="uf-hint">El identificador de inicio de sesión no se puede modificar.</span>
        </div>

        <div class="uf-field">
          <label for="email">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              Correo electrónico <span style="color:#ef4444;">*</span>
          </label>
          <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($u->email ?? '', ENT_QUOTES); ?>" required placeholder="ej: jperez@empresa.cl">
          <span class="uf-hint">Se usará para notificaciones y recuperación de cuenta.</span>
        </div>

        <div class="uf-field">
          <label for="role">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              Rol <span style="color:#ef4444;">*</span>
          </label>
          <select id="role" name="role" class="uf-select">
            <?php echo $roleOptions; ?>
          </select>
          <span class="uf-hint">Define los permisos del usuario en el sistema.</span>
        </div>
      </div>

      <!-- ═══ Acciones ═══ -->
      <div class="uf-actions">
        <button type="button" class="ut-btn ut-btn-secondary" id="btnBack">← Volver al listado</button>
        <button type="submit" class="ut-btn ut-btn-primary" id="btnSave">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/></svg>
          Guardar cambios
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal confirmación guardar -->
<div id="editUserModal" class="umodal" style="display:none;">
  <div class="umodal-backdrop"></div>
  <div class="umodal-card" style="max-width:420px;">
    <div class="umodal-body">
      <div class="umodal-icon"><?php echo icon('info', ['size' => 28]); ?></div>
      <h3 class="umodal-title">¿Guardar cambios?</h3>
      <p class="umodal-text">Los datos del usuario serán actualizados.</p>
    </div>
    <div class="umodal-actions">
      <button type="button" class="ut-btn ut-btn-secondary" id="modalCancel">Cancelar</button>
      <button type="button" class="ut-btn ut-btn-primary" id="modalConfirm">Guardar</button>
    </div>
  </div>
</div>

<script>
var urlroot = <?php echo json_encode(URLROOT); ?>;
var userId = <?php echo (int)($u->id ?? 0); ?>;
var csrfToken = <?php echo json_encode($data['csrf_token'] ?? ''); ?>;
var btnSave = document.getElementById('btnSave');
var modal = document.getElementById('editUserModal');
var modalCancel = document.getElementById('modalCancel');
var modalConfirm = document.getElementById('modalConfirm');
var form = document.getElementById('editForm');

function validateEditForm() {
  var email = document.querySelector('[name="email"]').value.trim();
  if (!email) { showToast('El correo electrónico es obligatorio.', 'error', 4000); return false; }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showToast('El correo electrónico ingresado no es válido.', 'error', 4000); return false; }
  return true;
}

function showStatus(msg, type) {
  if (typeof showToast === 'function') showToast(msg, type, 4000);
}

if (btnSave && modal) {
  btnSave.addEventListener('click', function(e) {
    e.preventDefault();
    if (!validateEditForm()) return;
    modal.style.display = '';
    document.body.style.overflow = 'hidden';
  });

  function closeModal() { modal.style.display = 'none'; document.body.style.overflow = ''; }
  modalCancel.addEventListener('click', closeModal);
  modal.querySelector('.umodal-backdrop').addEventListener('click', closeModal);
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal.style.display !== 'none') closeModal();
  });

  modalConfirm.addEventListener('click', function() {
    modalConfirm.disabled = true;
    modalConfirm.textContent = 'Guardando...';
    form.submit();
  });
}

function escHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

// ── Back confirmation ──
document.getElementById('btnBack').addEventListener('click', function() {
  openConfirm({
    title: '¿Salir sin guardar?',
    message: 'Hay cambios sin guardar en el formulario. Si sales se perderán las modificaciones.',
    confirmText: 'Salir sin guardar',
    confirmClass: 'btn-warn',
    onConfirm: function() { window.location.href = urlroot + '/users'; }
  });
});

// ── Live validation ──
var requiredFields = {
  first_name: { el: document.getElementById('first_name'), label: 'Nombres' },
  last_name:  { el: document.getElementById('last_name'),  label: 'Apellidos' },
  email:      { el: document.getElementById('email'),      label: 'Correo electrónico' },
};

function isValidEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }

function validateLive() {
  var ok = true;
  Object.keys(requiredFields).forEach(function(key) {
    var f = requiredFields[key];
    var val = f.el.value.trim();
    var empty = val === '';
    var invalidEmail = key === 'email' && !empty && !isValidEmail(val);
    var hasError = empty || invalidEmail;

    f.el.style.borderColor = hasError ? '#ef4444' : '';
    f.el.style.boxShadow = hasError ? '0 0 0 2px rgba(239,68,68,0.15)' : '';

    if (hasError) ok = false;
  });
  btnSave.disabled = !ok;
  btnSave.style.opacity = ok ? '' : '0.45';
  btnSave.style.pointerEvents = ok ? '' : 'none';
}

Object.keys(requiredFields).forEach(function(key) {
  requiredFields[key].el.addEventListener('input', validateLive);
  requiredFields[key].el.addEventListener('change', validateLive);
});
validateLive();
</script>
