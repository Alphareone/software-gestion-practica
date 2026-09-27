<?php
$connections = $data['connections'] ?? [];
$pending = $data['pending_batch'] ?? null;
?>

<div class="settings-page" style="max-width:860px;">

  <?php if (!empty($data['dry_run'])): ?>
    <div class="d-badge exp" style="display:inline-block;margin-bottom:1rem;">Modo DRY-RUN activo: los cambios NO se envían a Walmart</div>
  <?php endif; ?>

  <?php if (empty($connections)): ?>
    <div class="dash-empty" style="margin-top:8rem;padding:0;">
      <h3>No hay tiendas Walmart activas</h3>
      <p>Conecta y sincroniza una tienda antes de actualizar precios o stock.</p>
    </div>
  <?php else: ?>

  <!-- Batch pendiente (reanudable) -->
  <div class="ut-card" style="padding:1.25rem;margin-bottom:1rem;<?php echo $pending ? '' : 'display:none;'; ?>" id="wmPendingCard">
    <h3 style="margin-bottom:0.5rem;">Batch pendiente</h3>
    <p style="font-size:0.82rem;color:var(--text-secondary);" id="wmPendingInfo">
      <?php if ($pending): ?>
        Batch de <?php echo $pending['update_type'] === 'stock' ? 'stock' : 'precios'; ?>:
        <?php echo (int) $pending['processed']; ?> de <?php echo (int) $pending['total']; ?> procesados
        (<?php echo (int) $pending['ok']; ?> OK, <?php echo (int) $pending['errors']; ?> errores).
      <?php endif; ?>
    </p>
    <div style="display:flex;gap:0.5rem;margin-top:0.75rem;">
      <button type="button" class="ut-btn ut-btn-primary" onclick="wmResumeBatch()">Reanudar</button>
      <button type="button" class="ut-btn is-danger" onclick="wmCancelBatch()">Cancelar batch</button>
    </div>
  </div>

  <!-- Formulario de carga -->
  <div class="ut-card" style="padding:1.5rem;margin-bottom:1rem;" id="wmUploadCard">
    <h3 style="margin-bottom:0.35rem;">Actualización masiva</h3>
    <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:1.25rem;">
      Sube un archivo <strong>.xlsx</strong> o <strong>.csv</strong> con dos columnas:
      <strong>SKU</strong> y <strong>valor</strong> (precio en CLP o stock según el tipo elegido).
      Máximo 10 MB / 5.000 filas. La primera fila puede ser encabezado.
    </p>

    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1rem;">
      <select id="wmUpdStore" class="rename-input" style="max-width:240px;">
        <?php foreach ($connections as $c): ?>
          <option value="<?php echo (int)$c->id; ?>"><?php echo htmlspecialchars($c->store_name ?: 'Sin nombre'); ?></option>
        <?php endforeach; ?>
      </select>
      <select id="wmUpdType" class="rename-input" style="max-width:200px;">
        <option value="price">Actualizar precios</option>
        <option value="stock">Actualizar stock</option>
      </select>
      <input type="file" id="wmUpdFile" accept=".xlsx,.csv" class="rename-input" style="flex:1;min-width:220px;">
    </div>

    <div style="display:flex;justify-content:flex-end;">
      <button type="button" class="ut-btn ut-btn-primary" id="wmPrepareBtn" onclick="wmPrepare()">Analizar archivo</button>
    </div>
  </div>

  <!-- Preview -->
  <div class="ut-card" style="padding:1.5rem;margin-bottom:1rem;display:none;" id="wmPreviewCard">
    <h3 style="margin-bottom:0.5rem;">Vista previa</h3>
    <p style="font-size:0.82rem;color:var(--text-secondary);" id="wmPreviewSummary"></p>
    <div id="wmValidationErrors" style="margin:0.5rem 0;"></div>
    <div class="ut-table-wrap" style="max-height:280px;overflow-y:auto;">
      <table class="ut-table">
        <thead><tr><th>SKU</th><th id="wmPreviewValueCol">Precio nuevo</th></tr></thead>
        <tbody id="wmPreviewBody"></tbody>
      </table>
    </div>
    <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem;">
      <button type="button" class="btn btn-cancel" onclick="wmCancelBatch()">Descartar</button>
      <button type="button" class="ut-btn ut-btn-primary" id="wmApplyBtn" onclick="wmApply()">Aplicar cambios en Walmart</button>
    </div>
  </div>

  <!-- Progreso y resultados -->
  <div class="ut-card" style="padding:1.5rem;display:none;" id="wmResultsCard">
    <h3 style="margin-bottom:0.5rem;">Procesando batch</h3>
    <div style="background:var(--bg-secondary,#eee);border-radius:4px;height:8px;overflow:hidden;margin-bottom:0.5rem;">
      <div id="wmBatchBar" style="background:var(--primary,#2563eb);height:100%;width:0%;transition:width .3s;"></div>
    </div>
    <p style="font-size:0.82rem;color:var(--text-secondary);" id="wmBatchText"></p>
    <div class="ut-table-wrap" style="max-height:320px;overflow-y:auto;margin-top:0.75rem;">
      <table class="ut-table">
        <thead><tr><th>SKU</th><th>Anterior</th><th>Nuevo</th><th>Resultado</th></tr></thead>
        <tbody id="wmResultsBody"></tbody>
      </table>
    </div>
  </div>

  <?php endif; ?>
</div>

<?php if (!empty($connections)): ?>
<script>
(function() {
  var CSRF = '<?php echo htmlspecialchars($data['csrf_token']); ?>';
  var BASE = '<?php echo URLROOT; ?>/walmart';
  var applying = false;

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

  window.wmPrepare = function() {
    var fileInput = document.getElementById('wmUpdFile');
    if (!fileInput.files || fileInput.files.length === 0) {
      toast('Selecciona un archivo primero.', 'error');
      return;
    }
    var btn = document.getElementById('wmPrepareBtn');
    btn.disabled = true;

    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('store_id', document.getElementById('wmUpdStore').value);
    fd.append('update_type', document.getElementById('wmUpdType').value);
    fd.append('file', fileInput.files[0]);

    fetch(BASE + '/prepareUpdate', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        btn.disabled = false;
        var errsDiv = document.getElementById('wmValidationErrors');
        if (!res.ok) {
          toast(res.error || 'Error al analizar el archivo.', 'error');
          if (res.validation_errors && res.validation_errors.length) {
            show('wmPreviewCard', true);
            document.getElementById('wmPreviewSummary').textContent = 'El archivo no tiene filas válidas.';
            errsDiv.innerHTML = res.validation_errors.map(function(e) {
              return '<div class="d-badge exp" style="display:block;margin-bottom:2px;">' + esc(e) + '</div>';
            }).join('');
            document.getElementById('wmPreviewBody').innerHTML = '';
          }
          return;
        }
        show('wmPreviewCard', true);
        show('wmPendingCard', false);
        document.getElementById('wmPreviewValueCol').textContent =
          res.update_type === 'stock' ? 'Stock nuevo' : 'Precio nuevo';
        document.getElementById('wmPreviewSummary').textContent =
          res.total + ' filas válidas listas para aplicar'
          + (res.validation_errors.length ? ' — ' + res.validation_errors.length + ' filas con advertencias' : '')
          + (res.total > 20 ? ' (mostrando las primeras 20)' : '') + '.';
        errsDiv.innerHTML = res.validation_errors.map(function(e) {
          return '<div class="d-badge exp" style="display:block;margin-bottom:2px;">' + esc(e) + '</div>';
        }).join('');
        document.getElementById('wmPreviewBody').innerHTML = res.preview.map(function(en) {
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
          toast(res.error || 'Error al procesar el batch.', 'error');
          return;
        }
        var pct = res.total > 0 ? Math.round(res.processed * 100 / res.total) : 0;
        document.getElementById('wmBatchBar').style.width = pct + '%';
        document.getElementById('wmBatchText').textContent =
          res.processed + ' de ' + res.total + ' — ' + res.ok_count + ' OK, ' + res.error_count + ' errores';

        var body = document.getElementById('wmResultsBody');
        (res.results || []).forEach(function(r) {
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
          toast('Batch completado: ' + res.ok_count + ' OK, ' + res.error_count + ' errores.',
            res.error_count > 0 ? 'error' : 'success');
          document.getElementById('wmBatchText').textContent += ' — COMPLETADO';
        } else {
          setTimeout(applyLoop, 300);
        }
      })
      .catch(function() {
        applying = false;
        toast('Error de red. El batch queda guardado: puedes reanudarlo recargando la página.', 'error');
      });
  }

  window.wmApply = function() {
    if (applying) return;
    applying = true;
    show('wmPreviewCard', false);
    show('wmUploadCard', false);
    show('wmResultsCard', true);
    applyLoop();
  };

  window.wmResumeBatch = function() {
    if (applying) return;
    applying = true;
    show('wmPendingCard', false);
    show('wmUploadCard', false);
    show('wmResultsCard', true);
    applyLoop();
  };

  window.wmCancelBatch = function() {
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fetch(BASE + '/cancelUpdateBatch', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function() {
        show('wmPendingCard', false);
        show('wmPreviewCard', false);
        show('wmUploadCard', true);
        toast('Batch descartado.', 'success');
      })
      .catch(function() {});
  };
})();
</script>
<?php endif; ?>
