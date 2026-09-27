<div class="audit-container">

  <?php if (!empty($data['error'])): ?>
    <div class="flex items-center gap-3 p-4 mb-4 rounded-xl text-sm bg-red-500/5 border border-red-500/20 text-muted">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="flex-shrink-0 text-red-500"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
      <span><?php echo htmlspecialchars($data['error']); ?></span>
    </div>
  <?php endif; ?>

  <?php if (empty($data['active_store'])): ?>
    <div class="flex items-center gap-3 p-4 mb-4 rounded-xl text-sm bg-accent/5 border border-accent/20 text-muted">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="flex-shrink-0 text-accent"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
      <span>No hay tienda activa seleccionada. Selecciona una tienda en <strong>Conexiones</strong> para usar la auditoría.</span>
    </div>
  <?php else: ?>

  <div class="audit-card">
    <div class="audit-card-header">
      <div class="audit-card-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      </div>
      <div>
        <h3>Subir archivo de precios</h3>
        <p>Archivo <code>.xlsx</code> o <code>.csv</code> con columnas <strong>SKU</strong> y <strong>Precio</strong></p>
      </div>
    </div>
    <div class="audit-card-body">
      <form id="auditForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
        <div class="audit-file-row">
          <label class="btn-primary" id="auditLabel">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Elegir archivo
            <input type="file" name="audit_file" id="auditFile" accept=".xlsx,.csv" class="hidden">
          </label>
          <span id="auditFileName" class="audit-file-name">Ningún archivo seleccionado</span>
        </div>
      </form>
    </div>
  </div>

  <div class="audit-card">
    <div class="audit-card-header">
      <div class="audit-card-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
      </div>
      <div>
        <h3>Contrastar con MercadoLibre</h3>
        <p>Tienda: <strong><?php echo htmlspecialchars($data['active_store']->store_name); ?></strong></p>
      </div>
    </div>
    <div class="audit-card-body">
      <p id="auditStatus" class="audit-status">Selecciona un archivo para comenzar.</p>
      <button type="button" class="btn-primary" id="btnCompare" disabled>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        Contrastar y descargar
      </button>
    </div>
  </div>

  <div id="auditProgress" class="text-center py-8 hidden">
    <div class="spinner w-7 h-7 border-3"></div>
    <p id="auditProgressText">Comparando precios con MercadoLibre...</p>
    <p class="audit-progress-sub">Esto puede tardar unos segundos según la cantidad de productos.</p>
  </div>

  <div id="auditError" class="flex items-center gap-3 p-4 rounded-xl bg-red-500/5 border border-red-500/20 text-muted text-sm mt-4 hidden">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="flex-shrink-0 text-red-500"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
    <span id="auditErrorMsg"></span>
  </div>

  <?php endif; ?>
</div>


<?php if (!empty($data['active_store'])): ?>
<script>
(function(){
  var fileInput = document.getElementById('auditFile');
  var fileName = document.getElementById('auditFileName');
  var btnCompare = document.getElementById('btnCompare');
  var statusEl = document.getElementById('auditStatus');
  var progressEl = document.getElementById('auditProgress');
  var progressText = document.getElementById('auditProgressText');
  var errorEl = document.getElementById('auditError');
  var errorMsg = document.getElementById('auditErrorMsg');
  var form = document.getElementById('auditForm');

  if (!fileInput) return;

  fileInput.addEventListener('change', function(){
    if (this.files && this.files.length > 0) {
      var name = this.files[0].name;
      var size = (this.files[0].size / 1024).toFixed(1);
      fileName.textContent = name + ' (' + size + ' KB)';
      btnCompare.disabled = false;
      var count = '?';
      statusEl.textContent = 'Archivo listo: ' + name.replace(/</g,'<').replace(/>/g,'>') + '. Hacé clic en "Contrastar y descargar".';
    }
  });

  btnCompare.addEventListener('click', function(){
    if (!fileInput.files || fileInput.files.length === 0) return;

    errorEl.classList.add('hidden');
    btnCompare.disabled = true;
    btnCompare.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px;"></span> Procesando...';
    statusEl.textContent = 'Comparando precios con MercadoLibre...';

    progressEl.classList.remove('hidden');
    var cards = document.querySelectorAll('.audit-card');
    cards.forEach(function(c){ c.classList.add('hidden'); });

    var formData = new FormData(form);

    fetch('<?php echo URLROOT; ?>/connections/compareAudit', {
      method: 'POST',
      body: formData
    }).then(function(response) {
      progressEl.classList.add('hidden');
      if (!response.ok) {
        return response.json().then(function(d) {
          throw new Error(d.error || 'Error desconocido.');
        }).catch(function(e) {
          throw e;
        });
      }
      var disposition = response.headers.get('Content-Disposition');
      var filename = 'auditoria_precios.xlsx';
      if (disposition && disposition.indexOf('filename=') !== -1) {
        var match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
        if (match && match[1]) filename = match[1].replace(/['"]/g, '');
      }
      return response.blob().then(function(blob) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
        location.reload();
      });
    }).catch(function(err) {
      progressEl.classList.add('hidden');
      cards.forEach(function(c){ c.classList.remove('hidden'); });
      btnCompare.disabled = false;
      btnCompare.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Contrastar y descargar';
      errorEl.classList.remove('hidden');
      errorMsg.textContent = err.message || 'Error al procesar el archivo.';
      statusEl.textContent = 'Ocurrió un error. Intenta de nuevo.';
    });
  });
})();
</script>
<?php endif; ?>