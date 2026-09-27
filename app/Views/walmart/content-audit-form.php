<?php $cfg = $data['audit_cfg']; ?>
  <div class="audit-container">

  <?php if (!empty($data['error'])): ?>
    <div class="flex items-center gap-3 p-4 mb-4 rounded-xl text-sm bg-red-500/5 border border-red-500/20 text-muted" role="alert">
      <?php echo icon('info', ['size' => 18]); ?>
      <span><?php echo htmlspecialchars($data['error']); ?></span>
    </div>
  <?php endif; ?>

  <div class="mb-5 mt-1">
    <div class="mb-2">
      <a href="<?php echo URLROOT; ?>/walmart/auditHub" class="audit-back">
        <?php echo icon('arrow-left', ['size' => 14]); ?>
        Auditorías Walmart
      </a>
    </div>
    <?php if (!empty($data['active_store'])): ?>
    <div class="flex items-center gap-2 flex-wrap">
      <select id="storeSelect" class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2 py-1 focus:outline-none focus:border-accent cursor-pointer">
        <?php foreach ($data['connections'] as $c): ?>
          <option value="<?php echo (int)$c->id; ?>" <?php echo (int)$c->id === (int)$data['active_store_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c->store_name ?: 'Sin nombre'); ?></option>
        <?php endforeach; ?>
      </select>
      <span class="text-xs text-muted">· <?php echo number_format($data['cache_count']); ?> productos en caché</span>
      <?php if ($data['time_ago']): ?>
        <span class="text-xs text-muted">· último sync <?php echo htmlspecialchars($data['time_ago']); ?></span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if (empty($data['active_store'])): ?>
    <div class="flex items-center gap-3 p-5 rounded-xl text-sm bg-amber-500/5 border border-amber-500/20 text-muted">
      <?php echo icon('info', ['size' => 18]); ?>
      <span>No hay ninguna tienda Walmart conectada todavía. Conecta una tienda en <strong>Gestión Walmart</strong> para usar la auditoría.</span>
    </div>
  <?php else: ?>

  <div id="auditStep1">
    <div class="audit-card">
      <div class="audit-card-header">
        <div class="audit-card-icon">
          <?php echo icon('upload', ['size' => 22]); ?>
        </div>
        <div>
          <h3><?php echo $cfg['cardTitle']; ?></h3>
          <p>Archivo <code>.xlsx</code> o <code>.csv</code> con columnas <strong>SKU</strong> y <strong><?php echo $data['type'] === 'price' ? 'Precio' : 'Stock'; ?></strong></p>
        </div>
      </div>

      <form id="auditForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
        <input type="hidden" name="store_id" id="auditStoreId" value="<?php echo (int) $data['active_store_id']; ?>">

        <div class="audit-drop-zone pw-drop" id="auditDropZone" role="button" tabindex="0" aria-label="Área para arrastrar archivo de auditoría">
          <label class="pw-file-label" id="auditLabel">
            <?php echo icon('upload', ['size' => 36]); ?>
            <span class="pw-file-text">Arrastra tu archivo <code>.xlsx</code> o <code>.csv</code> aquí</span>
            <span class="pw-file-text text-sm">o haz clic para seleccionar</span>
            <input type="file" name="audit_file" id="auditFile" accept=".xlsx,.csv" class="pw-file-input">
          </label>
        </div>

        <div id="auditFileSelected" class="hidden mt-4 p-4 rounded-xl bg-card border border-border">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <?php echo icon('file-text', ['size' => 24, 'class' => 'text-accent']); ?>
              <div>
                <p id="auditFileName" class="font-medium text-primary">archivo.xlsx</p>
                <p id="auditFileSize" class="text-xs text-muted">0 KB</p>
              </div>
            </div>
            <button type="button" id="auditFileRemove" class="btn-secondary text-xs" aria-label="Quitar archivo">
              <?php echo icon('x', ['size' => 14]); ?>
              Quitar
            </button>
          </div>
          <div id="auditFileValidation"></div>
        </div>

        <div class="flex items-center justify-between mt-6">
          <div id="auditStatus" aria-live="polite" aria-atomic="true">
            <span class="text-sm text-muted">Selecciona un archivo para comenzar</span>
          </div>
          <button type="button" class="btn-primary" id="btnCompare" disabled>
            Iniciar auditoría
          </button>
        </div>
      </form>
    </div>
  </div>

  <div id="auditStep2" class="hidden mt-6">
    <div class="text-center">
      <div class="loader-spinner mx-auto mb-4"></div>
      <h3 class="text-base font-semibold mb-1">Procesando auditoría…</h3>
      <p class="text-sm text-muted mb-1">Comparando datos contra la caché local de Walmart</p>
      <p class="text-xs text-muted mb-4">Esto puede tardar unos segundos según la cantidad de productos.</p>
      <button type="button" id="btnCancel" class="btn-secondary text-xs">
        <?php echo icon('x', ['size' => 14]); ?>
        Cancelar
      </button>
    </div>
  </div>

  <div id="auditStep3" class="hidden mt-6">
    <div class="text-center p-8 rounded-xl bg-card border border-border">
      <div class="w-12 h-12 rounded-full bg-success/10 flex items-center justify-center mx-auto mb-3">
        <?php echo icon('circle-check', ['size' => 24, 'class' => 'text-success']); ?>
      </div>
      <h3 class="text-base font-semibold mb-1">Auditoría completada</h3>
      <p class="text-sm text-muted mb-4">El archivo con las discrepancias se ha descargado automáticamente.</p>
      <button type="button" id="btnNewAudit" class="btn-primary">
        Nueva auditoría
      </button>
    </div>
  </div>

  <div id="auditError" class="flex items-center gap-3 p-4 rounded-xl bg-red-500/5 border border-red-500/20 text-muted text-sm hidden mt-6" role="alert" aria-live="assertive">
    <?php echo icon('info', ['size' => 18]); ?>
    <span id="auditErrorMsg"></span>
  </div>

  <?php endif; ?>
</div>

<?php if (!empty($data['active_store'])): ?>
<script>
(function(){
  var dropZone = document.getElementById('auditDropZone');
  var fileInput = document.getElementById('auditFile');
  var fileSelected = document.getElementById('auditFileSelected');
  var fileName = document.getElementById('auditFileName');
  var fileSize = document.getElementById('auditFileSize');
  var fileRemove = document.getElementById('auditFileRemove');
  var fileValidation = document.getElementById('auditFileValidation');
  var btnCompare = document.getElementById('btnCompare');
  var btnCancel = document.getElementById('btnCancel');
  var btnNewAudit = document.getElementById('btnNewAudit');
  var step1 = document.getElementById('auditStep1');
  var step2 = document.getElementById('auditStep2');
  var step3 = document.getElementById('auditStep3');
  var errorEl = document.getElementById('auditError');
  var errorMsg = document.getElementById('auditErrorMsg');
  var form = document.getElementById('auditForm');

  if (!fileInput || !dropZone) return;

  var validTypes = ['xlsx', 'csv'];
  var maxSize = 10 * 1024 * 1024;
  var currentFile = null;
  var currentFetch = null;

  function setValidation(message, type) {
    fileValidation.innerHTML = '';
    if (!message) return;
    var el = document.createElement('div');
    el.className = 'file-validation ' + (type === 'error' ? 'file-error' : 'file-success') + ' mt-2';
    el.innerHTML = '<span>' + message + '</span>';
    fileValidation.appendChild(el);
  }

  function resetFileUI() {
    fileInput.value = '';
    currentFile = null;
    fileSelected.classList.add('hidden');
    dropZone.classList.remove('hidden');
    btnCompare.disabled = true;
    btnCompare.innerHTML = 'Iniciar auditor\u00eda';
    setValidation(null);
  }

  function handleFile(file) {
    var name = file.name;
    var sizeKB = (file.size / 1024).toFixed(1);
    var ext = name.split('.').pop().toLowerCase();

    if (!validTypes.includes(ext)) {
      setValidation('Formato inv\u00e1lido. Solo se permiten .xlsx y .csv', 'error');
      return false;
    }
    if (file.size > maxSize) {
      setValidation('Archivo demasiado grande (m\u00e1x 10MB).', 'error');
      return false;
    }

    currentFile = file;
    fileName.textContent = name;
    fileSize.textContent = sizeKB + ' KB';
    fileSelected.classList.remove('hidden');
    dropZone.classList.add('hidden');
    btnCompare.disabled = false;
    setValidation('Archivo v\u00e1lido \u2014 haz clic en \u201cIniciar auditor\u00eda\u201d', 'success');

    if (typeof showToast === 'function') showToast('Archivo cargado: ' + name, 'success', 3000);
    return true;
  }

  dropZone.addEventListener('dragenter', function(e) { e.preventDefault(); e.stopPropagation(); dropZone.classList.add('dragover'); });
  dropZone.addEventListener('dragover', function(e) { e.preventDefault(); e.stopPropagation(); dropZone.classList.add('dragover'); });
  dropZone.addEventListener('dragleave', function(e) { e.preventDefault(); e.stopPropagation(); dropZone.classList.remove('dragover'); });
  dropZone.addEventListener('drop', function(e) {
    e.preventDefault(); e.stopPropagation();
    dropZone.classList.remove('dragover');
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      fileInput.files = e.dataTransfer.files;
      handleFile(e.dataTransfer.files[0]);
    }
  });
  dropZone.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
  });

  fileInput.addEventListener('change', function(){
    if (this.files && this.files.length > 0) handleFile(this.files[0]);
  });

  fileRemove.addEventListener('click', function() {
    resetFileUI();
    dropZone.focus();
    if (typeof showToast === 'function') showToast('Archivo eliminado', 'info', 2000);
  });

  btnCompare.addEventListener('click', function(){
    if (!currentFile) return;

    errorEl.classList.add('hidden');
    setValidation(null);

    step1.classList.add('hidden');
    step2.classList.remove('hidden');
    step3.classList.add('hidden');

    var formData = new FormData(form);
    currentFetch = fetch('<?php echo URLROOT . $cfg['endpoint']; ?>', {
      method: 'POST',
      body: formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    currentFetch.then(function(response) {
      currentFetch = null;
      if (!response.ok) {
        return response.json().then(function(d) { throw new Error(d.error || 'Error desconocido.'); });
      }

      var disposition = response.headers.get('Content-Disposition');
      var filename = '<?php echo $cfg['filePrefix']; ?>.xlsx';
      if (disposition && disposition.indexOf('filename=') !== -1) {
        var match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
        if (match && match[1]) filename = match[1].replace(/['"]/g, '');
      }

      if (typeof showDownloadToast === 'function') showDownloadToast(filename, 6000);

      return response.blob().then(function(blob) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = filename;
        document.body.appendChild(a); a.click(); a.remove();
        URL.revokeObjectURL(url);
      });
    }).then(function() {
      step2.classList.add('hidden');
      step3.classList.remove('hidden');
      resetFileUI();
      if (typeof showToast === 'function') showToast('Auditor\u00eda completada exitosamente', 'success', 5000);
    }).catch(function(err) {
      currentFetch = null;
      step2.classList.add('hidden');
      step1.classList.remove('hidden');
      errorEl.classList.remove('hidden');
      errorMsg.textContent = err.message || 'Error al procesar el archivo. Intenta de nuevo.';
      if (typeof showToast === 'function') showToast(err.message || 'Error durante la auditor\u00eda', 'error', 5000);
    });
  });

  function cancelAudit() {
    if (currentFetch) { try { currentFetch.abort(); } catch(e) {} currentFetch = null; }
    step2.classList.add('hidden');
    step1.classList.remove('hidden');
    btnCompare.focus();
    if (typeof showToast === 'function') showToast('Auditor\u00eda cancelada', 'info', 3000);
  }

  btnCancel.addEventListener('click', cancelAudit);

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !step2.classList.contains('hidden') && currentFetch) cancelAudit();
  });

  btnNewAudit.addEventListener('click', function() {
    step3.classList.add('hidden');
    step1.classList.remove('hidden');
    errorEl.classList.add('hidden');
  });

  var storeSelect = document.getElementById('storeSelect');
  var storeIdInput = document.getElementById('auditStoreId');
  if (storeSelect) {
    storeSelect.addEventListener('change', function() {
      var val = this.value;
      if (!val) return;
      var url = new URL(window.location);
      url.searchParams.set('store', val);
      window.location.href = url.toString();
    });
  }
  if (storeIdInput && storeSelect) {
    storeIdInput.value = storeSelect.value;
  }
})();
</script>
<?php endif; ?>
