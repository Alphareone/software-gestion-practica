<?php ?>

<div class="settings-page" style="max-width:100%;">

  <?php if (!empty($data['dry_run'])): ?>
    <div class="d-badge exp" style="display:inline-block;margin-bottom:1rem;">Modo DRY-RUN activo</div>
  <?php endif; ?>

  <div style="display:flex;gap:0.75rem;align-items:center;margin-bottom:1rem;">
    <select id="wmHistType" class="rename-input" style="max-width:200px;">
      <option value="">Precios y stock</option>
      <option value="price">Solo precios</option>
      <option value="stock">Solo stock</option>
    </select>
  </div>

  <div class="ut-table-wrap">
    <table class="ut-table">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Tipo</th>
          <th>Tienda</th>
          <th>Usuario</th>
          <th>Ítems</th>
          <th>OK</th>
          <th>Errores</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="wmHistBody">
        <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-secondary);">Cargando…</td></tr>
      </tbody>
    </table>
  </div>

  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.75rem;">
    <span style="font-size:0.78rem;color:var(--text-secondary);" id="wmHistInfo"></span>
    <div style="display:flex;gap:0.5rem;">
      <button type="button" class="ut-btn" id="wmHistPrev" onclick="wmHistPage(-1)">Anterior</button>
      <button type="button" class="ut-btn" id="wmHistNext" onclick="wmHistPage(1)">Siguiente</button>
    </div>
  </div>
</div>

<!-- Modal detalle de batch -->
<div id="wmBatchModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal-card" style="max-width:760px;width:92%;" onclick="event.stopPropagation()">
    <h3 style="margin-bottom:0.5rem;">Detalle del batch <code id="wmBatchModalId" style="font-size:0.75rem;"></code></h3>
    <div class="ut-table-wrap" style="max-height:420px;overflow-y:auto;">
      <table class="ut-table">
        <thead><tr><th>SKU</th><th>Anterior</th><th>Nuevo</th><th>Dif.</th><th>Resultado</th></tr></thead>
        <tbody id="wmBatchModalBody"></tbody>
      </table>
    </div>
    <div class="modal-actions" style="margin-top:0.75rem;">
      <button type="button" class="btn btn-cancel" onclick="document.getElementById('wmBatchModal').style.display='none'">Cerrar</button>
    </div>
  </div>
</div>

<script>
(function() {
  var BASE = '<?php echo URLROOT; ?>/walmart';
  var state = { page: 1, totalPages: 1, type: '' };

  function esc(s) {
    var div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
  }

  function load() {
    fetch(BASE + '/updateHistoryData?page=' + state.page + '&type=' + encodeURIComponent(state.type))
      .then(function(r) { return r.json(); })
      .then(function(res) {
        var body = document.getElementById('wmHistBody');
        if (!res.batches || res.batches.length === 0) {
          body.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-secondary);">Sin batches registrados.</td></tr>';
          document.getElementById('wmHistInfo').textContent = '';
          state.totalPages = 1;
          updateButtons();
          return;
        }
        body.innerHTML = res.batches.map(function(b) {
          var typeBadge = b.update_type === 'stock'
            ? '<span class="d-badge country">Stock</span>'
            : '<span class="d-badge country">Precios</span>';
          return '<tr class="ut-tr">'
            + '<td style="font-size:0.78rem;">' + esc(b.started_at) + '</td>'
            + '<td>' + typeBadge + '</td>'
            + '<td>' + esc(b.store_name || '—') + '</td>'
            + '<td>' + esc(b.username || '—') + '</td>'
            + '<td>' + b.total + '</td>'
            + '<td><span class="d-badge ok">' + b.success_count + '</span></td>'
            + '<td>' + (b.error_count > 0 ? '<span class="d-badge exp">' + b.error_count + '</span>' : '0') + '</td>'
            + '<td><button type="button" class="ut-edit-btn" onclick="wmShowBatch(\'' + esc(b.batch_id) + '\')" title="Ver detalle">Ver</button></td>'
            + '</tr>';
        }).join('');
        state.totalPages = res.total_pages;
        state.page = res.page;
        document.getElementById('wmHistInfo').textContent =
          res.total_batches + ' batches — página ' + res.page + ' de ' + res.total_pages;
        updateButtons();
      })
      .catch(function() {
        document.getElementById('wmHistBody').innerHTML =
          '<tr><td colspan="8" style="text-align:center;padding:2rem;">Error de red.</td></tr>';
      });
  }

  function updateButtons() {
    document.getElementById('wmHistPrev').disabled = state.page <= 1;
    document.getElementById('wmHistNext').disabled = state.page >= state.totalPages;
  }

  window.wmHistPage = function(delta) {
    var next = state.page + delta;
    if (next < 1 || next > state.totalPages) return;
    state.page = next;
    load();
  };

  window.wmShowBatch = function(batchId) {
    fetch(BASE + '/updateBatchLogs/' + batchId)
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (res.error) return;
        document.getElementById('wmBatchModalId').textContent = batchId;
        document.getElementById('wmBatchModalBody').innerHTML = (res.logs || []).map(function(l) {
          var badge = l.status === 'success'
            ? '<span class="d-badge ok">OK</span>'
            : '<span class="d-badge exp" title="' + esc(l.message) + '">' + esc((l.message || '').substring(0, 60)) + '</span>';
          var diff = l.diff_amount !== null && l.diff_amount !== undefined
            ? esc(l.diff_amount) + (l.diff_percent !== null ? ' (' + esc(l.diff_percent) + '%)' : '')
            : '—';
          return '<tr class="ut-tr">'
            + '<td><code style="font-size:0.75rem;">' + esc(l.sku) + '</code></td>'
            + '<td>' + esc(l.old_value === null ? '—' : l.old_value) + '</td>'
            + '<td>' + esc(l.new_value) + '</td>'
            + '<td style="font-size:0.78rem;">' + diff + '</td>'
            + '<td>' + badge + '</td></tr>';
        }).join('');
        document.getElementById('wmBatchModal').style.display = 'flex';
      })
      .catch(function() {});
  };

  document.getElementById('wmHistType').addEventListener('change', function() {
    state.type = this.value;
    state.page = 1;
    load();
  });

  load();
})();
</script>
