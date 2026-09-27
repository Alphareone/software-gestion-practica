<?php $isConfigured = $data['ml_app_id'] && $data['ml_has_db']; ?>
<div class="settings-page">
  <div class="settings-section">
    <p style="margin:0 0 1rem;padding:0 0 0.75rem;border-bottom:1px solid var(--border);font-size:0.85rem;color:var(--text-secondary);line-height:1.6;">
      Estas credenciales identifican tu aplicaci&oacute;n en MercadoLibre y permiten que el sistema se conecte para sincronizar productos.
    </p>

    <?php if (!$isConfigured): ?>
    <p style="margin:0 0 1.25rem;padding:0.6rem 0.75rem;background:var(--bg-secondary);border-radius:6px;font-size:0.82rem;color:var(--text-secondary);line-height:1.6;">
      <strong>&#9654; &iquest;C&oacute;mo obtenerlas?</strong><br>
      1. Ve a <a href="https://developers.mercadolibre.cl/devcenter" target="_blank" rel="noopener" style="color:var(--accent);">developers.mercadolibre.cl/devcenter</a><br>
      2. Inicia sesi&oacute;n y selecciona tu aplicaci&oacute;n (o crea una nueva)<br>
      3. Copia el <strong>APP ID</strong> y el <strong>Client Secret</strong><br>
      4. P&eacute;galos abajo y haz clic en <strong>Guardar</strong>
    </p>
    <?php endif; ?>

    <?php if (!empty($_SESSION['is_owner'])): ?>

    <!-- ===== OWNER VIEW ===== -->

    <div class="setting-row">
      <div class="setting-info">
        <span class="setting-label">APP ID <span style="color:#dc2626;">*</span></span>
        <span class="setting-hint">ID de tu aplicaci&oacute;n en MercadoLibre DevCenter.</span>
      </div>
      <div class="setting-control">
        <input type="text" id="mlAppInput" class="s-input" style="width:300px;font-family:monospace;text-align:left;<?php echo $isConfigured ? 'opacity:0.6;' : ''; ?>"
               autocomplete="off" <?php echo $isConfigured ? 'disabled' : 'readonly onfocus="this.removeAttribute(\'readonly\')"'; ?>
               placeholder="<?php echo $isConfigured ? 'Configurado' : 'Ej: 1234567890123456'; ?>">
      </div>
    </div>

    <div class="setting-row">
      <div class="setting-info">
        <span class="setting-label">CLIENT SECRET <span style="color:#dc2626;">*</span></span>
        <span class="setting-hint">C&oacute;digo secreto de la aplicaci&oacute;n. Se guarda cifrado en la base de datos.</span>
      </div>
      <div class="setting-control" style="flex-direction:column;align-items:stretch;gap:0.35rem;">
        <input type="password" id="mlSecretInput" class="s-input" style="width:300px;font-family:monospace;text-align:left;<?php echo $isConfigured ? 'opacity:0.6;' : ''; ?>"
               autocomplete="off" <?php echo $isConfigured ? 'disabled' : 'readonly onfocus="this.removeAttribute(\'readonly\')"'; ?>
               placeholder="<?php echo $isConfigured ? 'Configurado' : 'Pega tu Client Secret aqu&iacute;'; ?>">
        <div style="display:flex;align-items:center;gap:0.5rem;min-height:24px;">
          <button type="button" id="mlSecretToggle" class="ut-btn ut-btn-secondary" style="font-size:0.78rem;display:none;">Mostrar</button>
          <span id="mlSecretStatus" style="font-size:0.78rem;font-weight:600;display:none;">
            <span style="color:var(--success);">&#10003; Configurado</span>
          </span>
        </div>
      </div>
    </div>

    <div class="setting-row" style="display:flex;align-items:center;justify-content:flex-end;gap:1rem;padding-top:0.5rem;">
      <button type="button" class="ut-btn ut-btn-primary" id="btnSaveMl" style="<?php echo $isConfigured ? 'display:none;' : ''; ?>" <?php echo !$isConfigured ? 'disabled' : ''; ?>>Guardar</button>
      <button type="button" id="btnClearMl" style="padding:0.45rem 1.1rem;font-size:0.82rem;font-weight:600;border:none;border-radius:6px;cursor:pointer;background:#dc2626;color:#fff;<?php echo !$isConfigured ? 'display:none;' : ''; ?>">Limpiar credenciales</button>
    </div>

    <?php else: ?>

    <!-- ===== NON-OWNER ADMIN VIEW ===== -->

    <div class="setting-row">
      <div class="setting-info">
        <span class="setting-label">APP ID</span>
        <span class="setting-hint">ID de la aplicaci&oacute;n en MercadoLibre DevCenter.</span>
      </div>
      <div class="setting-control">
        <code style="font-size:0.85rem;padding:0.35rem 0.6rem;border:1px solid var(--border);border-radius:4px;background:var(--bg-secondary);">
          <?php echo htmlspecialchars($data['ml_app_id'] ?: 'No configurado'); ?>
        </code>
      </div>
    </div>

    <div class="setting-row">
      <div class="setting-info">
        <span class="setting-label">CLIENT SECRET</span>
        <span class="setting-hint">C&oacute;digo secreto de la aplicaci&oacute;n (solo el due&ntilde;o puede verlo).</span>
      </div>
      <div class="setting-control">
        <?php if ($data['ml_has_db']): ?>
          <span style="font-size:0.85rem;color:var(--success);font-weight:600;">&#10003; Configurado</span>
        <?php else: ?>
          <span style="font-size:0.85rem;color:var(--text-muted);">&mdash; No configurado</span>
        <?php endif; ?>
      </div>
    </div>

    <?php endif; ?>
  </div>
</div>

<script>
<?php if (!empty($_SESSION['is_owner'])): ?>

var isConfigured = <?php echo $isConfigured ? 'true' : 'false'; ?>;
var mlAppInput = document.getElementById('mlAppInput');
var mlSecretInput = document.getElementById('mlSecretInput');
var mlSecretToggle = document.getElementById('mlSecretToggle');
var mlSecretStatus = document.getElementById('mlSecretStatus');
var btnSaveMl = document.getElementById('btnSaveMl');
var btnClearMl = document.getElementById('btnClearMl');

// Load APP ID from PHP (no value attr in HTML to prevent browser autofill)
var initialAppId = <?php echo json_encode($data['ml_app_id']); ?>;
if (initialAppId) {
  mlAppInput.value = initialAppId;
}

// If configured: pre-fill secret, show toggle + status
if (isConfigured) {
  mlSecretToggle.style.display = '';
  mlSecretStatus.style.display = '';
  fetch('<?php echo URLROOT; ?>/admin/getMlSecret', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      csrf_token: '<?php echo htmlspecialchars($data['csrf_token'], ENT_QUOTES, "UTF-8"); ?>',
    }),
  })
  .then(function(r) { return r.json(); })
  .then(function(d) {
    if (d.ok && d.client_secret) {
      mlSecretInput.value = d.client_secret;
    }
  })
  .catch(function() {});
}

// Enable/disable save button based on both fields being filled
function validateMlForm() {
  if (!btnSaveMl) return;
  var appId = mlAppInput.value.trim();
  var secret = mlSecretInput.value.trim();
  btnSaveMl.disabled = !appId || !secret;
}

if (!isConfigured) {
  mlAppInput.addEventListener('input', validateMlForm);
  mlSecretInput.addEventListener('input', validateMlForm);
  // Initial validation
  setTimeout(validateMlForm, 100);
}

// Toggle CLIENT SECRET visibility (only works if input has a value)
mlSecretToggle.addEventListener('click', function() {
  if (mlSecretInput.value) {
    if (mlSecretInput.type === 'password') {
      mlSecretInput.type = 'text';
      mlSecretToggle.textContent = 'Ocultar';
    } else {
      mlSecretInput.type = 'password';
      mlSecretToggle.textContent = 'Mostrar';
    }
  }
});

// Save
if (btnSaveMl) {
  btnSaveMl.addEventListener('click', function() {
    var appId = mlAppInput.value.trim();
    var secret = mlSecretInput.value.trim();

    btnSaveMl.disabled = true;
    btnSaveMl.textContent = 'Guardando...';

    fetch('<?php echo URLROOT; ?>/admin/saveMlCredentials', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        csrf_token: '<?php echo htmlspecialchars($data['csrf_token'], ENT_QUOTES, "UTF-8"); ?>',
        ml_app_id: appId,
        ml_client_secret: secret,
      }),
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      btnSaveMl.disabled = false;
      btnSaveMl.textContent = 'Guardar';
      if (d.ok) {
        showToast(d.message || 'Credenciales guardadas correctamente.', 'success', 4000);
        setTimeout(function() { location.reload(); }, 1500);
      } else {
        showToast(d.error || 'Error al guardar.', 'error', 6000);
      }
    })
    .catch(function() {
      btnSaveMl.disabled = false;
      btnSaveMl.textContent = 'Guardar';
      showToast('Error de conexi&oacute;n.', 'error', 6000);
    });
  });
}

// Clear credentials (with confirmation)
btnClearMl.addEventListener('click', function() {
  openConfirm({
    title: '\u00bfLimpiar credenciales?',
    message: 'Se eliminar\u00e1n el APP ID y CLIENT SECRET de la base de datos.<br><br><strong>\u00a1Atenci\u00f3n!</strong> Las tiendas conectadas con este APP ID quedar\u00e1n deshabilitadas cuando sus tokens expiren. Para que vuelvan a funcionar deber\u00e1s reconectarlas manualmente despu\u00e9s de configurar las nuevas credenciales.',
    confirmText: 'Limpiar',
    confirmClass: 'btn-danger',
    requirePhrase: 'limpiar',
    onConfirm: function() {
      btnClearMl.disabled = true;
      btnClearMl.textContent = 'Limpiando...';

      fetch('<?php echo URLROOT; ?>/admin/saveMlCredentials', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          csrf_token: '<?php echo htmlspecialchars($data['csrf_token'], ENT_QUOTES, "UTF-8"); ?>',
          ml_app_id: '',
          ml_client_secret: '',
        }),
      })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.ok) {
          showToast('Credenciales eliminadas correctamente.', 'success', 4000);
          setTimeout(function() { location.reload(); }, 1200);
        } else {
          btnClearMl.disabled = false;
          btnClearMl.textContent = 'Limpiar credenciales';
          showToast(d.error || 'Error al limpiar.', 'error', 6000);
        }
      })
      .catch(function() {
        btnClearMl.disabled = false;
        btnClearMl.textContent = 'Limpiar credenciales';
        showToast('Error de conexi&oacute;n.', 'error', 6000);
      });
    }
  });
});

<?php endif; ?>
</script>