<?php $isAdmin = $data['is_admin'] ?? false; ?>
<?php $username = htmlspecialchars($data['username'] ?? ''); ?>
<?php $email = htmlspecialchars($data['email'] ?? ''); ?>
<?php $twofactorEnabled = $data['twofactor_enabled'] ?? false; ?>
<?php $role = $data['role'] ?? ''; ?>
<?php $roleLabels = ['owner'=>'Dueño','admin'=>'Administrador','logistics'=>'Logística','sales'=>'Ventas','products'=>'Productos','user'=>'Usuario']; ?>
<?php $roleLabel = $roleLabels[$role] ?? 'Usuario'; ?>
<?php $memberSince = $data['member_since'] ?? ''; ?>
<?php $memberSinceFormatted = $memberSince ? date('j \d\e F, Y', strtotime($memberSince . ' UTC')) : '—'; ?>

<div class="settings-page">

  <div class="settings-tabs">
    <button class="settings-tab active" data-panel="account">Mi cuenta</button>
    <button class="settings-tab" data-panel="security">Seguridad</button>
  </div>

</div>
    <div class="settings-panel active" id="panel-account">
      <div class="setting-row">
        <div class="setting-info">
          <span class="setting-label">Usuario</span>
          <span class="setting-hint">Nombre de usuario con el que inicias sesión.</span>
        </div>
        <div class="setting-value"><?php echo $username; ?></div>
      </div>

      <div class="setting-row">
        <div class="setting-info">
          <span class="setting-label">Email</span>
          <span class="setting-hint">Correo electrónico asociado a tu cuenta.</span>
        </div>
        <div class="setting-value"><?php echo $email ?: '—'; ?></div>
      </div>

      <div class="setting-row">
        <div class="setting-info">
          <span class="setting-label">Rol</span>
          <span class="setting-hint">Permisos que tienes en el sistema.</span>
        </div>
        <div class="setting-value"><?php echo htmlspecialchars($roleLabel); ?></div>
      </div>

      <div class="setting-row">
        <div class="setting-info">
          <span class="setting-label">Miembro desde</span>
          <span class="setting-hint">Fecha de creación de tu cuenta.</span>
        </div>
        <div class="setting-value"><?php echo $memberSinceFormatted; ?></div>
      </div>

      <div class="setting-row">
        <div class="setting-info">
          <span class="setting-label">Contraseña</span>
          <span class="setting-hint">Modifica tu contraseña de acceso.</span>
        </div>
        <div class="setting-value">
          <button type="button" class="btn-settings-link" id="btnChangePassword">Cambiar contraseña</button>
        </div>
      </div>

      <div class="setting-row">
        <div class="setting-info">
          <span class="setting-label">Informaciones</span>
          <span class="setting-hint">Ayuda, información del sistema y recursos.</span>
        </div>
        <div class="setting-value">
          <a href="<?php echo URLROOT; ?>/info" class="btn-settings-link">Ver información →</a>
        </div>
      </div>
    </div>

    <!-- MODAL: Cambiar contraseña -->
    <div class="modal-overlay" id="passwordModal">
      <div class="modal-box modal-box--sm">
        <div class="modal-icon" style="color:var(--accent)">
          <?php echo icon('shield-check', ['size' => 28]); ?>
        </div>
        <h3 style="margin-bottom:1rem">Cambiar contraseña</h3>

        <form id="passwordForm" method="post" autocomplete="off" class="settings-form">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

          <div class="pw-field">
            <label for="pwCurrent">Contraseña actual</label>
            <input type="password" name="current_password" id="pwCurrent" class="pw-input" placeholder="Ingresa tu contraseña actual" required autocomplete="current-password">
          </div>

          <div class="pw-divider"></div>

          <div class="pw-field">
            <label for="pwNew">Nueva contraseña</label>
            <input type="password" name="new_password" id="pwNew" class="pw-input" placeholder="Mínimo 8 caracteres, mayúscula, número y símbolo" required autocomplete="new-password">
            <ul class="pw-requirements" id="pwRules">
              <li data-rule="min"><span class="pw-dot">•</span> Al menos <strong>8 caracteres</strong></li>
              <li data-rule="upper"><span class="pw-dot">•</span> Una <strong>mayúscula</strong></li>
              <li data-rule="lower"><span class="pw-dot">•</span> Una <strong>minúscula</strong></li>
              <li data-rule="number"><span class="pw-dot">•</span> Un <strong>número</strong></li>
              <li data-rule="symbol"><span class="pw-dot">•</span> Un <strong>símbolo</strong></li>
            </ul>
          </div>

          <div class="pw-field">
            <label for="pwConfirm">Confirmar nueva contraseña</label>
            <input type="password" name="confirm_password" id="pwConfirm" class="pw-input" placeholder="Confirma la nueva contraseña" required autocomplete="new-password">
          </div>
        </form>

        <div class="modal-actions modal-actions--top" style="justify-content:center">
          <button class="btn btn-cancel" id="btnPwCancel">Cancelar</button>
          <button class="btn btn-confirm--accent" id="btnPwSave"><span id="pwSaveSpinner" style="display:none;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 0.7s linear infinite;vertical-align:middle;margin-right:5px;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span><span id="pwSaveText">Guardar</span></button>
        </div>
      </div>
    </div>

    <!-- ═══ PANEL: Seguridad ═══ -->
    <div class="settings-panel" id="panel-security">
      <div class="setting-row">
        <div class="setting-info">
          <span class="setting-label">Autenticación en dos pasos</span>
          <span class="setting-hint">Agrega una capa extra de seguridad a tu cuenta.</span>
        </div>
        <div class="setting-value">
          <?php if ($twofactorEnabled): ?>
            <span class="badge-ok">Activado</span>
          <?php else: ?>
            <span class="badge-off">Desactivado</span>
            <a href="<?php echo URLROOT; ?>/auth/twofactor" class="btn-settings-link">Configurar</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($twofactorEnabled): ?>
      <div class="setting-row">
        <div class="setting-info">
          <span class="setting-label">Códigos de recuperación</span>
          <span class="setting-hint">Usa estos códigos si pierdes el acceso a tu aplicación de autenticación.</span>
        </div>
        <div class="setting-value">
          <span class="badge-info"><?php echo (int)$data['recovery_codes_count']; ?> restantes</span>
          <button type="button" class="btn-text" id="btnRegenRecovery">Regenerar</button>
        </div>
      </div>
      <?php endif; ?>

      <div class="setting-row">
        <div class="setting-info">
          <span class="setting-label">Sesiones activas</span>
          <span class="setting-hint">Si crees que alguien más accedió a tu cuenta, puedes cerrar todas las sesiones activas.</span>
        </div>
        <div class="setting-value">
          <button type="button" class="btn-logout-all" id="btnLogoutAll">Cerrar sesión en todos los dispositivos</button>
          <form method="POST" action="<?php echo URLROOT; ?>/settings/logoutAllDevices" id="logoutForm" style="display:none;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL: Confirmar cierre de sesión -->
    <div class="modal-overlay" id="logoutModal">
      <div class="modal-box modal-box--xs">
        <div class="modal-icon danger">
          <?php echo icon('log-out', ['size' => 28]); ?>
        </div>
        <h3 style="margin-bottom:1rem">Cerrar sesión en todos los dispositivos</h3>
        <p class="modal-desc">Se cerrará tu sesión en todos los dispositivos. Vas a tener que iniciar sesión nuevamente.</p>
        <div class="modal-actions" style="justify-content:center">
          <button class="btn btn-cancel" id="btnLogoutCancel">Cancelar</button>
          <button class="btn btn-confirm--danger" id="btnLogoutConfirm">Cerrar sesión</button>
        </div>
      </div>
    </div>

    <!-- MODAL: Confirmar regenerar códigos -->
    <div class="modal-overlay" id="regenModal">
      <div class="modal-box modal-box--xs">
        <div class="modal-icon" style="color:var(--accent)">
          <?php echo icon('shield-check', ['size' => 28]); ?>
        </div>
        <h3 style="margin-bottom:1rem">Regenerar códigos de recuperación</h3>
        <p class="modal-desc">Los códigos actuales dejarán de funcionar. Ingresa tu contraseña para confirmar.</p>
        <form method="POST" action="<?php echo URLROOT; ?>/auth/regeneraterecoverycodes" id="regenForm">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
          <div class="regen-input-wrap">
            <input type="password" name="password" class="pw-input" placeholder="Contraseña actual" required autocomplete="current-password">
          </div>
        </form>
        <div class="modal-actions" style="justify-content:center">
          <button class="btn btn-cancel" id="btnRegenCancel">Cancelar</button>
          <button class="btn btn-confirm--accent" id="btnRegenConfirm">Regenerar</button>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Tab switching
  var tabs = document.querySelectorAll('.settings-tab');
  tabs.forEach(function(tab) {
    tab.addEventListener('click', function() {
      var panelId = this.getAttribute('data-panel');
      tabs.forEach(function(t) { t.classList.remove('active'); });
      this.classList.add('active');
      document.querySelectorAll('.settings-panel').forEach(function(p) {
        p.classList.toggle('active', p.id === 'panel-' + panelId);
      });
    });
  });

  // Password change modal
  var btnPw = document.getElementById('btnChangePassword');
  var modal = document.getElementById('passwordModal');
  var btnCancel = document.getElementById('btnPwCancel');
  var btnSave = document.getElementById('btnPwSave');
  var pwForm = document.getElementById('passwordForm');
  var pwRules = document.getElementById('pwRules');

  function validatePassword(pw) {
    var checks = {
      min: pw.length >= 8,
      upper: /[A-Z]/.test(pw),
      lower: /[a-z]/.test(pw),
      number: /[0-9]/.test(pw),
      symbol: /[^A-Za-z0-9]/.test(pw),
    };
    pwRules.querySelectorAll('[data-rule]').forEach(function(el) {
      var rule = el.getAttribute('data-rule');
      el.classList.toggle('pw-ok', checks[rule]);
    });
    return Object.values(checks).every(Boolean);
  }

  if (btnPw && modal) {
    btnPw.addEventListener('click', function() {
      modal.style.display = 'flex';
      pwForm.reset();
      pwRules.querySelectorAll('[data-rule]').forEach(function(el) { el.classList.remove('pw-ok'); });
    });

    btnCancel.addEventListener('click', function() {
      modal.style.display = 'none';
    });

    modal.addEventListener('click', function(e) {
      if (e.target === modal) modal.style.display = 'none';
    });

    document.getElementById('pwNew').addEventListener('input', function() {
      validatePassword(this.value);
    });

    btnSave.addEventListener('click', function() {
      var fd = new FormData(pwForm);
      var newPw = fd.get('new_password');
      var confirmPw = fd.get('confirm_password');

      if (!validatePassword(newPw)) return;

      if (newPw !== confirmPw) {
        if (typeof showToast === 'function') {
          showToast('Las contraseñas no coinciden.', 'error', 4000);
        }
        return;
      }

      btnSave.disabled = true;
      btnSave.style.opacity = '0.8';
      document.getElementById('pwSaveSpinner').style.display = 'inline';
      document.getElementById('pwSaveText').textContent = 'Guardando...';

      fetch('<?php echo URLROOT; ?>/settings/changePassword', {
        method: 'POST',
        body: fd,
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        btnSave.disabled = false;
        btnSave.style.opacity = '';
        document.getElementById('pwSaveSpinner').style.display = 'none';
        document.getElementById('pwSaveText').textContent = 'Guardar';
        if (data.error) {
          modal.style.display = 'none';
          if (typeof showToast === 'function') {
            showToast('Contraseña actual incorrecta.', 'error', 5000);
          }
        } else {
          modal.style.display = 'none';
          if (typeof showToast === 'function') {
            showToast('Contraseña cambiada correctamente', 'success', 4000);
          }
        }
      })
      .catch(function() {
        btnSave.disabled = false;
        btnSave.style.opacity = '';
        document.getElementById('pwSaveSpinner').style.display = 'none';
        document.getElementById('pwSaveText').textContent = 'Guardar';
        if (typeof showToast === 'function') {
          showToast('Ocurrió un error al cambiar la contraseña.', 'error', 5000);
        }
      });
    });
  }

  // Logout all devices modal
  var btnLogoutAll = document.getElementById('btnLogoutAll');
  var logoutModal = document.getElementById('logoutModal');
  var btnLogoutCancel = document.getElementById('btnLogoutCancel');
  var btnLogoutConfirm = document.getElementById('btnLogoutConfirm');
  var logoutForm = document.getElementById('logoutForm');

  if (btnLogoutAll && logoutModal) {
    btnLogoutAll.addEventListener('click', function() {
      logoutModal.style.display = 'flex';
    });

    btnLogoutCancel.addEventListener('click', function() {
      logoutModal.style.display = 'none';
    });

    logoutModal.addEventListener('click', function(e) {
      if (e.target === logoutModal) logoutModal.style.display = 'none';
    });

    btnLogoutConfirm.addEventListener('click', function() {
      logoutForm.submit();
    });
  }

  // Regenerate recovery codes modal
  var btnRegen = document.getElementById('btnRegenRecovery');
  var regenModal = document.getElementById('regenModal');
  var btnRegenCancel = document.getElementById('btnRegenCancel');
  var btnRegenConfirm = document.getElementById('btnRegenConfirm');
  var regenForm = document.getElementById('regenForm');

  if (btnRegen && regenModal) {
    btnRegen.addEventListener('click', function() {
      regenModal.style.display = 'flex';
      regenForm.querySelector('input[name="password"]').value = '';
      regenForm.querySelector('input[name="password"]').focus();
    });

    btnRegenCancel.addEventListener('click', function() {
      regenModal.style.display = 'none';
    });

    regenModal.addEventListener('click', function(e) {
      if (e.target === regenModal) regenModal.style.display = 'none';
    });

    btnRegenConfirm.addEventListener('click', function() {
      regenForm.submit();
    });

    regenForm.querySelector('input[name="password"]').addEventListener('keydown', function(e) {
      if (e.key === 'Enter') regenForm.submit();
    });
  }
});
</script>
