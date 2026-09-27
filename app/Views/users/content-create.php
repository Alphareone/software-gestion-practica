<?php require_once __DIR__ . '/../../Helpers/IconHelper.php'; ?>
<div class="settings-page">
  <form method="post" action="<?php echo URLROOT; ?>/users/store" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? '', ENT_QUOTES); ?>">
    <input type="text" style="position:absolute;top:-9999px;left:-9999px" aria-hidden="true" tabindex="-1">

    <div class="settings-section">
      <h3 class="settings-heading">
        <?php echo icon('user', ['size' => 18]); ?>
        Información de cuenta
      </h3>

      <div class="setting-row">
        <div class="setting-info">
          <span class="setting-label">Nombre <span class="required">*</span></span>
          <span class="setting-hint">Nombre real del usuario.</span>
        </div>
        <div class="setting-control">
          <input type="text" name="first_name" maxlength="100" placeholder="Nombre" class="s-input" style="width:300px;text-align:left" required>
        </div>
      </div>

      <div class="setting-row">
        <div class="setting-info">
          <span class="setting-label">Apellido <span class="required">*</span></span>
          <span class="setting-hint">Apellido real del usuario.</span>
        </div>
        <div class="setting-control">
          <input type="text" name="last_name" maxlength="100" placeholder="Apellido" class="s-input" style="width:300px;text-align:left" required>
        </div>
      </div>

      <div class="setting-row">
        <div class="setting-info">
          <span class="setting-label">Correo electrónico <span class="required">*</span></span>
          <span class="setting-hint">Se usará para notificaciones, recuperación de cuenta y envío del enlace de activación.</span>
        </div>
        <div class="setting-control">
          <input type="email" name="email" required placeholder="correo@ejemplo.cl" class="s-input" style="width:300px;text-align:left" autocomplete="email" title="Ingresa un correo electrónico válido, ej: usuario@dominio.com">
        </div>
      </div>

      <div class="setting-row">
        <div class="setting-info">
          <span class="setting-label">Rol <span class="required">*</span></span>
          <span class="setting-hint">Permisos y acceso a secciones del sistema.</span>
        </div>
        <div class="setting-control">
          <select name="role" class="filter-select">
            <option value="user">Usuario</option>
            <option value="logistics">Logística</option>
            <option value="sales">Ventas</option>
            <option value="products">Productos</option>
            <option value="admin">Administrador</option>
          </select>
        </div>
      </div>

      <div class="setting-row setting-row--last">
        <div class="setting-info">
          <span class="setting-label">Contraseña</span>
          <span class="setting-hint">El usuario recibirá un correo para establecer su propia contraseña segura.</span>
        </div>
        <div class="setting-control">
          <span class="text-xs text-muted">Se enviará un enlace de activación al correo electrónico.</span>
        </div>
      </div>
    </div>

    <div class="settings-footer gap-3">
      <a href="<?php echo URLROOT; ?>/users" class="btn-secondary bg-surface/50">Cancelar</a>
      <button type="button" class="ut-btn bg-accent/80 text-white rounded-lg border-0" id="btnSave">
        Crear usuario
      </button>
    </div>
  </form>
</div>

<div id="createUserModal" class="umodal" style="display:none;">
  <div class="umodal-backdrop"></div>
  <div class="umodal-card">
    <div class="umodal-body">
      <div class="umodal-icon"><?php echo icon('info', ['size' => 28]); ?></div>
      <h3 class="umodal-title">¿Crear usuario?</h3>
      <p class="umodal-text">Se creará un nuevo usuario. Recibirá un correo con las instrucciones para activar su cuenta y establecer su contraseña.</p>
    </div>
    <div class="umodal-actions">
      <button type="button" class="ut-btn ut-btn-secondary" id="modalCancel">Cancelar</button>
      <button type="button" class="ut-btn ut-btn-primary" id="modalConfirm">Crear</button>
    </div>
  </div>
</div>

<script>
var btnSave = document.getElementById('btnSave');
var modal = document.getElementById('createUserModal');
var modalCancel = document.getElementById('modalCancel');
var modalConfirm = document.getElementById('modalConfirm');
var form = document.querySelector('form');

function validateForm() {
  var firstName = document.querySelector('[name="first_name"]').value.trim();
  var lastName = document.querySelector('[name="last_name"]').value.trim();
  var email = document.querySelector('[name="email"]').value.trim();

  if (!firstName || !lastName || !email) {
    showToast('Todos los campos obligatorios deben estar completos.', 'error', 4000);
    return false;
  }

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    showToast('El correo electrónico ingresado no es válido.', 'error', 4000);
    return false;
  }

  return true;
}

// Live validation: disable button if required fields are empty
function updateButtonState() {
  var firstName = document.querySelector('[name="first_name"]').value.trim();
  var lastName = document.querySelector('[name="last_name"]').value.trim();
  var email = document.querySelector('[name="email"]').value.trim();
  btnSave.disabled = !firstName || !lastName || !email;
}
updateButtonState();
document.querySelector('[name="first_name"]').addEventListener('input', updateButtonState);
document.querySelector('[name="last_name"]').addEventListener('input', updateButtonState);
document.querySelector('[name="email"]').addEventListener('input', updateButtonState);

if (btnSave && modal) {
  btnSave.addEventListener('click', function() {
    if (!validateForm()) return;
    modal.style.display = '';
    document.body.style.overflow = 'hidden';
  });

  function closeModal() {
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }

  modalCancel.addEventListener('click', closeModal);
  modal.querySelector('.umodal-backdrop').addEventListener('click', closeModal);
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal.style.display !== 'none') closeModal();
  });

  modalConfirm.addEventListener('click', function() {
    modalConfirm.disabled = true;
    modalConfirm.textContent = 'Creando...';
    form.submit();
  });
}
</script>
