<?php
$connections = $data['connections'] ?? [];
$pending = $data['pending_batch'] ?? null;
?>

<div class="settings-page" style="max-width:860px;">

  <?php if (!empty($data['dry_run'])): ?>
    <div class="d-badge exp" style="display:inline-block;margin-bottom:1rem;">Modo DRY-RUN activo: los cambios NO se envían a Cencosud</div>
  <?php endif; ?>

  <?php if (empty($connections)): ?>
    <div class="dash-empty" style="margin-top:8rem;padding:0;">
      <h3>No hay tiendas Cencosud activas</h3>
      <p>Conecta y sincroniza una tienda antes de actualizar precios o stock.</p>
    </div>
  <?php else: ?>

  <!-- Lote pendiente (reanudable) -->
  <div class="ut-card" style="padding:1.25rem;margin-bottom:1rem;<?php echo $pending ? '' : 'display:none;'; ?>" id="csPendingCard">
    <h3 style="margin-bottom:0.5rem;">Lote pendiente</h3>
    <p style="font-size:0.82rem;color:var(--text-secondary);" id="csPendingInfo">
      <?php if ($pending): ?>
        Lote de <?php echo $pending['update_type'] === 'stock' ? 'stock' : 'precios'; ?>:
        <?php echo (int) $pending['processed']; ?> de <?php echo (int) $pending['total']; ?> procesados
        (<?php echo (int) $pending['ok']; ?> OK, <?php echo (int) $pending['errors']; ?> errores).
      <?php endif; ?>
    </p>
    <div style="display:flex;gap:0.5rem;margin-top:0.75rem;">
      <button type="button" class="ut-btn ut-btn-primary" onclick="csResumeBatch()">Reanudar</button>
      <button type="button" class="ut-btn is-danger" onclick="csCancelBatch()">Cancelar lote</button>
    </div>
  </div>

  <!-- Formulario de carga -->
  <div class="ut-card" style="padding:1.5rem;margin-bottom:1rem;" id="csUploadCard">
    <h3 style="margin-bottom:0.35rem;">Actualización masiva</h3>
    <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:1.25rem;">
      Sube un archivo <strong>.xlsx</strong> o <strong>.csv</strong> con dos columnas:
      <strong>SKU</strong> y <strong>valor</strong> (precio en CLP o stock según el tipo elegido).
      Máximo 10 MB / 5.000 filas. La primera fila puede ser encabezado.
    </p>

    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1rem;">
      <select id="csUpdStore" class="rename-input" style="max-width:240px;">
        <?php foreach ($connections as $c): ?>
          <option value="<?php echo (int)$c->id; ?>"><?php echo htmlspecialchars($c->store_name ?: 'Sin nombre'); ?></option>
        <?php endforeach; ?>
      </select>
      <select id="csUpdType" class="rename-input" style="max-width:200px;">
        <option value="price">Actualizar precios</option>
        <option value="stock">Actualizar stock</option>
      </select>
    </div>

    <!-- Zona Drag & Drop -->
    <div class="pw-drop" id="csDropZone" style="margin-bottom:1rem;">
      <label class="pw-file-label" style="display:flex;flex-direction:column;align-items:center;gap:0.5rem;cursor:pointer;">
        <?php echo icon('upload', ['size' => 36]); ?>
        <span class="pw-file-text" id="csFileText">Arrastra tu archivo <strong>.xlsx</strong> o <strong>.csv</strong> aquí</span>
        <span class="pw-file-text text-sm" style="font-size:0.78rem;color:var(--text-secondary);">o haz clic para seleccionar</span>
        <input type="file" id="csUpdFile" accept=".xlsx,.csv" class="pw-file-input">
      </label>
    </div>

    <div style="display:flex;justify-content:flex-end;">
      <button type="button" class="ut-btn ut-btn-primary" id="csPrepareBtn" onclick="csPrepare()">Analizar archivo</button>
    </div>
  </div>

  <!-- Vista previa -->
  <div class="ut-card" style="padding:1.5rem;margin-bottom:1rem;display:none;" id="csPreviewCard">
    <h3 style="margin-bottom:0.5rem;">Vista previa</h3>
    <p style="font-size:0.82rem;color:var(--text-secondary);" id="csPreviewSummary"></p>
    <div id="csValidationErrors" style="margin:0.5rem 0;"></div>
    <div class="ut-table-wrap" style="max-height:280px;overflow-y:auto;">
      <table class="ut-table">
        <thead><tr><th>SKU</th><th id="csPreviewValueCol">Precio nuevo</th></tr></thead>
        <tbody id="csPreviewBody"></tbody>
      </table>
    </div>
    <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem;">
      <button type="button" class="btn btn-cancel" onclick="csCancelBatch()">Descartar</button>
      <button type="button" class="ut-btn ut-btn-primary" id="csApplyBtn" onclick="csApply()">Aplicar cambios en Cencosud</button>
    </div>
  </div>

  <!-- Progreso y resultados -->
  <div class="ut-card" style="padding:1.5rem;display:none;" id="csResultsCard">
    <h3 style="margin-bottom:0.5rem;">Procesando lote</h3>
    <div style="background:var(--bg-secondary,#eee);border-radius:4px;height:8px;overflow:hidden;margin-bottom:0.5rem;">
      <div id="csBatchBar" style="background:var(--primary,#2563eb);height:100%;width:0%;transition:width .3s;"></div>
    </div>
    <p style="font-size:0.82rem;color:var(--text-secondary);" id="csBatchText"></p>
    <div class="ut-table-wrap" style="max-height:320px;overflow-y:auto;margin-top:0.75rem;">
      <table class="ut-table">
        <thead><tr><th>SKU</th><th>Anterior</th><th>Nuevo</th><th>Resultado</th></tr></thead>
        <tbody id="csResultsBody"></tbody>
      </table>
    </div>
  </div>

  <!-- SKUs No Encontrados -->
  <div class="ut-card" style="padding:1.5rem;margin-top:1rem;display:none;" id="csNotFoundCard">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;flex-wrap:wrap;gap:0.5rem;">
      <h3 style="margin-bottom:0;color:#dc2626;">Productos no encontrados (SKU no encontrado)</h3>
      <button type="button" class="ut-btn" style="background:var(--bg-secondary,#eee);color:var(--text-primary);padding:0.35rem 0.75rem;font-size:0.8rem;" onclick="csDownloadNotFoundCsv()">
        <?php echo icon('download'); ?> Descargar CSV de no encontrados
      </button>
    </div>
    <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:1rem;">
      Estos SKUs se incluyeron en tu archivo, pero no existen en el catálogo local de esta tienda. Descarga el CSV para importarlos a la base de datos o agrégalos individualmente.
    </p>
    <div class="ut-table-wrap" style="max-height:250px;overflow-y:auto;">
      <table class="ut-table">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Valor subido</th>
            <th>Mensaje</th>
            <th style="width:140px;">Acciones</th>
          </tr>
        </thead>
        <tbody id="csNotFoundBody"></tbody>
      </table>
    </div>
  </div>

  <?php endif; ?>
</div>

<?php if (!empty($connections)): ?>
<script>
(function() {
  var CSRF = '<?php echo htmlspecialchars($data['csrf_token']); ?>';
  var BASE = '<?php echo URLROOT; ?>/cencosud';
  var applying = false;
  var notFoundList = [];

  function esc(s) {
    var div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
  }

  function show(id, visible) {
    var el = document.getElementById(id);
    if (el) el.style.display = visible ? '' : 'none';
  }

  function toast(msg, type) {
    if (typeof showToast === 'function') showToast(msg, type);
  }

  // ---- Drag & Drop Event Listeners ----
  var dropZone = document.getElementById('csDropZone');
  var fileInput = document.getElementById('csUpdFile');
  var fileText = document.getElementById('csFileText');

  if (dropZone && fileInput) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(eventName) {
      dropZone.addEventListener(eventName, function(e) {
        e.preventDefault();
        e.stopPropagation();
      }, false);
    });

    ['dragenter', 'dragover'].forEach(function(eventName) {
      dropZone.addEventListener(eventName, function() {
        dropZone.classList.add('dragover');
      }, false);
    });

    ['dragleave', 'drop'].forEach(function(eventName) {
      dropZone.addEventListener(eventName, function() {
        dropZone.classList.remove('dragover');
      }, false);
    });

    dropZone.addEventListener('drop', function(e) {
      var dt = e.dataTransfer;
      var files = dt.files;
      if (files && files.length > 0) {
        fileInput.files = files;
        updateFileInfo(files[0]);
      }
    });

    fileInput.addEventListener('change', function() {
      if (fileInput.files && fileInput.files.length > 0) {
        updateFileInfo(fileInput.files[0]);
      }
    });

    function updateFileInfo(file) {
      var name = file.name;
      var sizeKB = (file.size / 1024).toFixed(1);
      if (fileText) {
        fileText.innerHTML = 'Archivo cargado: <strong>' + esc(name) + '</strong> (' + sizeKB + ' KB)';
      }
      toast('Archivo cargado: ' + name, 'success');
    }
  }

  window.csPrepare = function() {
    notFoundList = [];
    var notFoundTbody = document.getElementById('csNotFoundBody');
    if (notFoundTbody) notFoundTbody.innerHTML = '';
    show('csNotFoundCard', false);

    var fileInput = document.getElementById('csUpdFile');
    if (!fileInput.files || fileInput.files.length === 0) {
      toast('Selecciona un archivo primero.', 'error');
      return;
    }
    var btn = document.getElementById('csPrepareBtn');
    btn.disabled = true;

    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('store_id', document.getElementById('csUpdStore').value);
    fd.append('update_type', document.getElementById('csUpdType').value);
    fd.append('file', fileInput.files[0]);

    fetch(BASE + '/prepareUpdate', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        btn.disabled = false;
        var errsDiv = document.getElementById('csValidationErrors');
        if (!res.ok) {
          toast(res.error || 'Error al analizar el archivo.', 'error');
          if (res.validation_errors && res.validation_errors.length) {
            show('csPreviewCard', true);
            document.getElementById('csPreviewSummary').textContent = 'El archivo no tiene filas válidas.';
            errsDiv.innerHTML = res.validation_errors.map(function(e) {
              return '<div class="d-badge exp" style="display:block;margin-bottom:2px;">' + esc(e) + '</div>';
            }).join('');
            document.getElementById('csPreviewBody').innerHTML = '';
          }
          return;
        }
        show('csPreviewCard', true);
        show('csPendingCard', false);
        document.getElementById('csPreviewValueCol').textContent =
          res.update_type === 'stock' ? 'Stock nuevo' : 'Precio nuevo';
        document.getElementById('csPreviewSummary').textContent =
          res.total + ' filas válidas listas para aplicar'
          + (res.validation_errors.length ? ' — ' + res.validation_errors.length + ' filas con advertencias' : '')
          + (res.total > 20 ? ' (mostrando las primeras 20)' : '') + '.';
        errsDiv.innerHTML = res.validation_errors.map(function(e) {
          return '<div class="d-badge exp" style="display:block;margin-bottom:2px;">' + esc(e) + '</div>';
        }).join('');
        document.getElementById('csPreviewBody').innerHTML = res.preview.map(function(en) {
          return '<tr class="ut-tr"><td><code style="font-size:0.75rem;">' + esc(en.sku) + '</code></td><td>' + esc(en.value) + '</td></tr>';
        }).join('');
      })
      .catch(function() {
        btn.disabled = false;
        toast('Error de red.', 'error');
      });
  };

  function applyLoop() {
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fetch(BASE + '/applyUpdateBatch', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (!res.ok) {
          applying = false;
          toast(res.error || 'Error al procesar el lote.', 'error');
          return;
        }
        var pct = res.total > 0 ? Math.round(res.processed * 100 / res.total) : 0;
        document.getElementById('csBatchBar').style.width = pct + '%';
        document.getElementById('csBatchText').textContent =
          res.processed + ' de ' + res.total + ' — ' + res.ok_count + ' OK, ' + res.error_count + ' errores';

        var body = document.getElementById('csResultsBody');
        (res.results || []).forEach(function(r) {
          var isNotFound = r.status === 'error' && (r.message.indexOf('no encontrado') !== -1 || r.message.indexOf('no existe') !== -1);
          if (isNotFound) {
            var exists = notFoundList.some(function(item) { return item.sku === r.sku; });
            if (!exists) {
              notFoundList.push({ sku: r.sku, value: r.new_value, error: r.message });
              var notFoundTbody = document.getElementById('csNotFoundBody');
              if (notFoundTbody) {
                show('csNotFoundCard', true);
                var creatorUrl = BASE.replace('/cencosud', '') + '/productcreator?sku=' + encodeURIComponent(r.sku);
                notFoundTbody.insertAdjacentHTML('beforeend',
                  '<tr class="ut-tr">'
                  + '<td><code style="font-size:0.75rem;">' + esc(r.sku) + '</code></td>'
                  + '<td>' + esc(r.new_value) + '</td>'
                  + '<td><span class="d-badge exp" style="font-size:0.75rem;">' + esc(r.message) + '</span></td>'
                  + '<td>'
                  + '  <button type="button" class="ut-edit-btn" onclick="navigator.clipboard.writeText(\'' + esc(r.sku) + '\');toast(\'SKU copiado\',\'success\')" style="margin-right:0.35rem;padding:0.15rem 0.4rem;font-size:0.7rem;">Copiar</button>'
                  + '  <a href="' + creatorUrl + '" target="_blank" class="ut-btn font-semibold" style="padding:0.2rem 0.5rem;font-size:0.7rem;text-decoration:none;background:var(--primary,#2563eb);color:#fff;border-radius:4px;">Agregar</a>'
                  + '</td>'
                  + '</tr>'
                );
              }
            }
          }

          var badge = r.status === 'success'
            ? '<span class="d-badge ok">OK</span>'
            : '<span class="d-badge exp" title="' + esc(r.message) + '">' + esc(r.message) + '</span>';
          body.insertAdjacentHTML('beforeend',
            '<tr class="ut-tr"><td><code style="font-size:0.75rem;">' + esc(r.sku) + '</code></td>'
            + '<td>' + esc(r.old_value === null ? '—' : r.old_value) + '</td>'
            + '<td>' + esc(r.new_value) + '</td><td>' + badge + '</td></tr>');
        });

        if (res.done) {
          applying = false;
          toast('Lote completado: ' + res.ok_count + ' OK, ' + res.error_count + ' errores.',
            res.error_count > 0 ? 'error' : 'success');
          document.getElementById('csBatchText').textContent += ' — COMPLETADO';
        } else {
          setTimeout(applyLoop, 300);
        }
      })
      .catch(function() {
        applying = false;
        toast('Error de red. El lote queda guardado: puedes reanudarlo recargando la página.', 'error');
      });
  }

  window.csApply = function() {
    if (applying) return;
    applying = true;
    show('csPreviewCard', false);
    show('csUploadCard', false);
    show('csResultsCard', true);
    applyLoop();
  };

  window.csResumeBatch = function() {
    if (applying) return;
    applying = true;
    show('csPendingCard', false);
    show('csUploadCard', false);
    show('csResultsCard', true);
    applyLoop();
  };

  window.csCancelBatch = function() {
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fetch(BASE + '/cancelUpdateBatch', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function() {
        show('csPendingCard', false);
        show('csPreviewCard', false);
        show('csUploadCard', true);
        notFoundList = [];
        var notFoundTbody = document.getElementById('csNotFoundBody');
        if (notFoundTbody) notFoundTbody.innerHTML = '';
        show('csNotFoundCard', false);
        if (fileInput) fileInput.value = '';
        if (fileText) fileText.innerHTML = 'Arrastra tu archivo <strong>.xlsx</strong> o <strong>.csv</strong> aquí';
        toast('Lote descartado.', 'success');
      })
      .catch(function() {});
  };

  window.csDownloadNotFoundCsv = function() {
    if (!notFoundList.length) return;
    var csvRows = ['sku,valor'];
    notFoundList.forEach(function(item) {
      csvRows.push(item.sku + ',' + item.value);
    });
    var csvContent = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvRows.join('\n'));
    var dlAnchor = document.createElement('a');
    dlAnchor.setAttribute('href', csvContent);
    dlAnchor.setAttribute('download', 'skus_no_encontrados_cencosud.csv');
    document.body.appendChild(dlAnchor);
    dlAnchor.click();
    dlAnchor.remove();
  };
})();
</script>
<?php endif; ?>
