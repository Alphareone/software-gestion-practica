<?php
$connections = $data['connections'] ?? [];
?>

<div class="settings-page" style="max-width:100%;">

  <?php if (!empty($data['dry_run'])): ?>
    <div class="d-badge exp" style="display:inline-block;margin-bottom:1rem;">Modo DRY-RUN activo: no se contacta la API real de Walmart</div>
  <?php endif; ?>

  <?php if (empty($connections)): ?>
    <div class="dash-empty" style="margin-top:8rem;padding:0;">
      <h3>No hay tiendas Walmart conectadas</h3>
      <p>Conecta una tienda y sincroniza su catálogo para explorar los productos.</p>
    </div>
  <?php else: ?>

    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem;">
      <select id="wmStoreSelect" class="rename-input" style="max-width:240px;">
        <?php foreach ($connections as $c): ?>
          <option value="<?php echo (int)$c->id; ?>"><?php echo htmlspecialchars($c->store_name ?: 'Sin nombre'); ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" id="wmSearch" class="rename-input" style="flex:1;min-width:200px;" placeholder="Buscar por título o SKU...">
      <select id="wmStatusFilter" class="rename-input" style="max-width:180px;">
        <option value="">Todos los estados</option>
        <option value="PUBLISHED">Publicados</option>
        <option value="UNPUBLISHED">No publicados</option>
      </select>
    </div>

    <div class="ut-table-wrap">
      <table class="ut-table">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Producto</th>
            <th>Tipo</th>
            <th>Precio</th>
            <th>Stock</th>
            <th>Estado</th>
            <th>Sincronizado</th>
          </tr>
        </thead>
        <tbody id="wmProductsBody">
          <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-secondary);">Cargando…</td></tr>
        </tbody>
      </table>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.75rem;">
      <span style="font-size:0.78rem;color:var(--text-secondary);" id="wmPagingInfo"></span>
      <div style="display:flex;gap:0.5rem;">
        <button type="button" class="ut-btn" id="wmPrevBtn" onclick="wmGoPage(-1)">Anterior</button>
        <button type="button" class="ut-btn" id="wmNextBtn" onclick="wmGoPage(1)">Siguiente</button>
      </div>
    </div>

  <?php endif; ?>
</div>

<?php if (!empty($connections)): ?>
<script>
(function() {
  var BASE = '<?php echo URLROOT; ?>/walmart';
  var state = { page: 1, totalPages: 1, q: '', status: '' };
  var searchTimer = null;

  function esc(s) {
    var div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
  }

  function formatPrice(p, currency) {
    try {
      return new Intl.NumberFormat('es-CL', { style: 'currency', currency: currency || 'CLP', maximumFractionDigits: 0 }).format(p);
    } catch (e) {
      return (currency || 'CLP') + ' ' + p;
    }
  }

  function load() {
    var store = document.getElementById('wmStoreSelect').value;
    var url = BASE + '/productsData?store=' + store + '&page=' + state.page
      + '&q=' + encodeURIComponent(state.q) + '&status=' + encodeURIComponent(state.status);
    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(res) {
        var body = document.getElementById('wmProductsBody');
        if (res.error) {
          body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;">' + esc(res.error) + '</td></tr>';
          return;
        }
        if (!res.items || res.items.length === 0) {
          body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-secondary);">Sin productos. Sincroniza la tienda desde el Panel Walmart.</td></tr>';
          document.getElementById('wmPagingInfo').textContent = '';
          state.totalPages = 1;
          updateButtons();
          return;
        }
        var rows = res.items.map(function(it) {
          var statusBadge = it.published_status === 'PUBLISHED'
            ? '<span class="d-badge ok">Publicado</span>'
            : '<span class="d-badge exp">' + esc(it.published_status || '—') + '</span>';
          var stock = it.available_quantity === null ? '—' : it.available_quantity;
          var stockStyle = (it.available_quantity !== null && it.available_quantity <= 5) ? 'color:var(--warning,#d97706);font-weight:600;' : '';
          return '<tr class="ut-tr" style="cursor:pointer;" onclick="window.location.href=\'' + BASE + '/productDetail/' + store + '/' + encodeURIComponent(it.sku) + '\'">'
            + '<td><code style="font-size:0.75rem;">' + esc(it.sku) + '</code></td>'
            + '<td style="max-width:380px;">' + esc(it.title) + '</td>'
            + '<td style="font-size:0.78rem;">' + esc(it.product_type || '—') + '</td>'
            + '<td>' + formatPrice(it.price, it.currency) + '</td>'
            + '<td style="' + stockStyle + '">' + stock + '</td>'
            + '<td>' + statusBadge + '</td>'
            + '<td style="font-size:0.72rem;color:var(--text-secondary);">' + esc(it.synced_at || '') + '</td>'
            + '</tr>';
        });
        body.innerHTML = rows.join('');
        state.totalPages = res.paging.total_pages;
        state.page = res.paging.page;
        document.getElementById('wmPagingInfo').textContent =
          res.paging.total + ' productos — página ' + res.paging.page + ' de ' + res.paging.total_pages;
        updateButtons();
      })
      .catch(function() {
        document.getElementById('wmProductsBody').innerHTML =
          '<tr><td colspan="7" style="text-align:center;padding:2rem;">Error de red.</td></tr>';
      });
  }

  function updateButtons() {
    document.getElementById('wmPrevBtn').disabled = state.page <= 1;
    document.getElementById('wmNextBtn').disabled = state.page >= state.totalPages;
  }

  window.wmGoPage = function(delta) {
    var next = state.page + delta;
    if (next < 1 || next > state.totalPages) return;
    state.page = next;
    load();
  };

  document.getElementById('wmStoreSelect').addEventListener('change', function() { state.page = 1; load(); });
  document.getElementById('wmStatusFilter').addEventListener('change', function() {
    state.status = this.value;
    state.page = 1;
    load();
  });
  document.getElementById('wmSearch').addEventListener('input', function() {
    var val = this.value;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() {
      state.q = val.trim();
      state.page = 1;
      load();
    }, 350);
  });

  load();
})();
</script>
<?php endif; ?>
