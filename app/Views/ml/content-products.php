<?php
$connections = $data['connections'] ?? [];
$activeStore = $data['active_store'] ?? null;
$activeStoreId = $data['active_store_id'] ?? null;
$searchQuery = $data['search_query'] ?? '';
$statusFilter = $data['status_filter'] ?? '';
$csrfToken = $data['csrf_token'] ?? '';
$activeConnections = array_filter($connections, function($c) { return (bool)($c->is_active ?? false); });
$q = htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8');
$estandarCount = (int) ($data['estandar_count'] ?? 0);
?>

<?php if (empty($connections)): ?>
<div class="dash-empty" style="margin-top:8rem;padding:0;">
  <h3>No hay tiendas conectadas</h3>
  <p>Actualmente no hay ninguna tienda de MercadoLibre conectada. Contacta a un administrador para que configure una tienda.</p>
</div>
<?php elseif (!$activeStore): ?>
<div class="dash-empty" style="margin-top:8rem;padding:0;">
  <h3>Ninguna tienda seleccionada</h3>
  <p>Selecciona una tienda desde el dashboard de MercadoLibre para ver sus productos.</p>
</div>
<?php else: ?>

<div class="ahub">
  <div class="audit-breadcrumb">
    <a href="<?php echo URLROOT; ?>/connections/dashboard" class="audit-back">
      <?php echo icon('arrow-left', ['size' => 14]); ?>
      Conexiones
    </a>
  </div>

  <!-- Filtros -->
  <div class="flex items-center gap-3 mb-5 flex-wrap">
    <select id="storeSelect" class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2.5 py-1.5 focus:outline-none focus:border-accent cursor-pointer">
      <option value="">Seleccionar tienda…</option>
      <?php foreach ($activeConnections as $c): ?>
        <option value="<?php echo (int)$c->id; ?>" <?php echo $activeStoreId && (int)$c->id === (int)$activeStoreId ? 'selected' : ''; ?>><?php echo htmlspecialchars($c->store_name ?? $c->ml_nickname ?? 'Sin nombre'); ?></option>
      <?php endforeach; ?>
    </select>

    <form method="get" class="flex items-center gap-2">
      <?php if ($activeStoreId): ?>
        <input type="hidden" name="store" value="<?php echo (int)$activeStoreId; ?>">
      <?php endif; ?>
      <input type="text" name="q" placeholder="Buscar por SKU o nombre…" value="<?php echo $q; ?>" class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2.5 py-1.5 focus:outline-none focus:border-accent w-48">
      <?php if ($searchQuery !== ''): ?>
        <a href="?store=<?php echo $activeStoreId ? (int)$activeStoreId : 'all'; ?>" class="text-xs text-muted hover:text-primary transition-colors"><?php echo icon('x', ['size' => 14]); ?></a>
      <?php endif; ?>
    </form>

    <div class="flex items-center gap-1.5">
      <a href="#" data-status="" class="status-pill text-xs px-2.5 py-1.5 rounded-lg border border-border transition-colors <?php echo empty($statusFilter) ? 'bg-accent/10 text-accent border-accent/30' : 'text-muted hover:border-muted hover:text-primary'; ?>">Todas</a>
      <a href="#" data-status="active" class="status-pill text-xs px-2.5 py-1.5 rounded-lg border border-border transition-colors flex items-center gap-1 <?php echo $statusFilter === 'active' ? 'bg-success/10 text-success border-success/30' : 'text-muted hover:border-muted hover:text-primary'; ?>"><span class="w-1.5 h-1.5 rounded-full bg-success"></span>Activo</a>
      <a href="#" data-status="paused" class="status-pill text-xs px-2.5 py-1.5 rounded-lg border border-border transition-colors flex items-center gap-1 <?php echo $statusFilter === 'paused' ? 'bg-warning/10 text-warning border-warning/30' : 'text-muted hover:border-muted hover:text-primary'; ?>"><span class="w-1.5 h-1.5 rounded-full bg-warning"></span>Pausado</a>
      <a href="#" data-status="closed" class="status-pill text-xs px-2.5 py-1.5 rounded-lg border border-border transition-colors flex items-center gap-1 <?php echo $statusFilter === 'closed' ? 'bg-border text-primary' : 'text-muted hover:border-muted hover:text-primary'; ?>"><span class="w-1.5 h-1.5 rounded-full" style="background:var(--text-secondary)"></span>Cerrado</a>
      <a href="#" data-status="under_review" class="status-pill text-xs px-2.5 py-1.5 rounded-lg border border-border transition-colors flex items-center gap-1 <?php echo $statusFilter === 'under_review' ? 'bg-danger/10 text-danger border-danger/30' : 'text-muted hover:border-muted hover:text-primary'; ?>"><span class="w-1.5 h-1.5 rounded-full bg-danger"></span>Revisión</a>
    </div>

    <button type="button" id="btnExport" class="pd-export-btn ml-auto">
      <?php echo icon('download', ['size' => 14]); ?>
      Exportar XLSX
    </button>
  </div>

  <!-- Products Table -->
  <div class="products-table-wrap" id="productsTableWrap">
    <div class="products-loading" id="productsLoading">
      <div class="loading-spinner"></div>
      <span>Cargando productos...</span>
    </div>

    <div class="products-error" id="productsError" style="display:none;">
      <?php echo icon('circle-x', ['size' => 32]); ?>
      <p id="productsErrorMsg">Error al cargar productos.</p>
      <button onclick="loadProducts(1)" class="btn-retry">Reintentar</button>
    </div>

    <div class="products-empty" id="productsEmpty" style="display:none;">
      <?php echo icon('package', ['size' => 48]); ?>
      <p id="productsEmptyMsg">No se encontraron productos.</p>
    </div>

    <table class="products-table" id="productsTable" style="display:none;">
      <thead>
        <tr>
          <th class="th-img"></th>
          <th class="th-title">Producto</th>
          <th class="th-sku">SKU</th>
          <th class="th-category">Categoría</th>
          <th class="th-price">Precio</th>
          <th class="th-stock">Stock</th>
          <th class="th-status">Estado</th>
          <th class="th-link"></th>
        </tr>
      </thead>
      <tbody id="productsBody"></tbody>
    </table>
  </div>

  <!-- Pagination -->
  <div class="products-pagination" id="productsPagination" style="display:none;"></div>

  <!-- Results Info -->
  <div class="products-info" id="productsInfo" style="display:none;"></div>
</div>

<!-- Export Modal -->
<div class="modal-overlay" id="exportModal" style="display:none;">
  <div class="modal-box" style="max-width:420px;">
    <h3 style="margin:0 0 0.25rem;font-size:1rem;font-weight:600;">Exportar a Excel</h3>
    <p class="export-desc">Selecciona los campos que quieres incluir en el archivo.</p>
    <div class="field-list">
      <label class="field-check"><input type="checkbox" value="codigo" checked><span>Código ML</span></label>
      <label class="field-check"><input type="checkbox" value="titulo" checked><span>Título</span></label>
      <label class="field-check"><input type="checkbox" value="sku" checked><span>SKU</span></label>
      <label class="field-check"><input type="checkbox" value="categoria" checked><span>Categoría</span></label>
      <label class="field-check"><input type="checkbox" value="precio" checked><span>Precio</span></label>
      <label class="field-check"><input type="checkbox" value="stock" checked><span>Stock</span></label>
      <label class="field-check"><input type="checkbox" value="condicion" checked><span>Condición</span></label>
      <label class="field-check"><input type="checkbox" value="tipo" checked><span>Tipo de publicación</span></label>
      <label class="field-check"><input type="checkbox" value="enlace"><span>Enlace ML</span></label>
      <label class="field-check"><input type="checkbox" value="atributos"><span>Atributos</span></label>
      <label class="field-check"><input type="checkbox" value="variaciones"><span>Variaciones</span></label>
      <label class="field-check"><input type="checkbox" value="garantia"><span>Garantía</span></label>
    </div>

    <div class="export-status">
      <label class="export-status-label">Filtrar por estado</label>
      <select id="exportStatus" class="export-status-select">
        <option value="">Todos los estados</option>
        <option value="active">Activo</option>
        <option value="paused">Pausado</option>
        <option value="closed">Cerrado</option>
        <option value="under_review">Revisión</option>
      </select>
    </div>

    <div class="export-actions" style="margin-top:1rem;">
      <button type="button" class="btn-modal-secondary" id="exportCancel">Cancelar</button>
      <button type="button" class="btn-modal-primary" id="exportConfirm">Descargar</button>
    </div>
  </div>
</div>

<?php if ($estandarCount > 0): ?>
<!-- Estandar warning modal -->
<div class="modal-overlay" id="estandarModal" style="display:none;">
  <div class="modal-box" style="max-width:400px;">
    <div class="text-center py-4">
      <div class="flex items-center justify-center w-12 h-12 rounded-full bg-red-500/10 mx-auto mb-4">
        <?php echo icon('triangle-alert', ['size' => 24, 'class' => 'text-red-500']); ?>
      </div>
      <h3 class="text-base font-semibold mb-1"><?php echo number_format($estandarCount); ?> productos sin SKU real</h3>
      <p class="text-sm text-muted mb-4">Tienen SKU "Estándar" y no podrás identificarlos en la auditoría uno por uno. Agrega <strong>Código ML</strong> como campo adicional para diferenciarlos.</p>
      <div class="flex items-center justify-center gap-3">
        <button type="button" class="btn-secondary text-sm" id="estandarBack">Volver</button>
        <button type="button" class="btn-primary text-sm btn-danger-bg" id="estandarConfirm">Descargar igual</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>



<?php if ($activeStore): ?>
<script>
(function() {
  var baseUrl = '<?php echo URLROOT; ?>/connections/productsData';
  var pageSize = 50;
  var currentPage = 1;
  var urlParams = new URLSearchParams(window.location.search);
  var currentQuery = urlParams.get('q') || '';
  var currentStore = urlParams.get('store') ? parseInt(urlParams.get('store'), 10) : null;
  var currentStatus = urlParams.get('status') || '';
  var isLoading = false;

  function buildUrl(page) {
    var params = new URLSearchParams();
    params.set('page', page);
    params.set('limit', pageSize);
    if (currentQuery) params.set('q', currentQuery);
    if (currentStore) params.set('store', currentStore);
    if (currentStatus) params.set('status', currentStatus);
    return baseUrl + '?' + params.toString();
  }

  window.loadProducts = function(page) {
    if (isLoading) return;
    isLoading = true;

    var loading = document.getElementById('productsLoading');
    var errorEl = document.getElementById('productsError');
    var emptyEl = document.getElementById('productsEmpty');
    var table = document.getElementById('productsTable');
    var tbody = document.getElementById('productsBody');
    var pagination = document.getElementById('productsPagination');
    var info = document.getElementById('productsInfo');

    loading.style.display = 'flex';
    errorEl.style.display = 'none';
    emptyEl.style.display = 'none';
    table.style.display = 'none';
    pagination.style.display = 'none';
    info.style.display = 'none';

    var url = buildUrl(page);

    fetch(url, { credentials: 'same-origin' })
      .then(function(res) {
        if (res.status === 401 || res.status === 403) {
          window.location.href = '<?php echo URLROOT; ?>/auth';
          return;
        }
        return res.json();
      })
      .then(function(data) {
        isLoading = false;
        loading.style.display = 'none';

        if (data.error) {
          errorEl.style.display = 'flex';
          document.getElementById('productsErrorMsg').textContent = data.error;
          return;
        }

        var items = data.items || [];
        var paging = data.paging || {};

        if (items.length === 0) {
          emptyEl.style.display = 'flex';
          var msg = currentQuery ? 'No se encontraron productos para "' + currentQuery + '".' : currentStatus ? 'No hay productos con ese estado.' : 'Esta tienda no tiene productos publicados.';
          document.getElementById('productsEmptyMsg').textContent = msg;
          return;
        }

        tbody.innerHTML = '';
        items.forEach(function(item, idx) {
          var price = Number(item.price).toLocaleString('es-CL');
          var stockClass = item.available_quantity === 0 ? 'out' : item.available_quantity <= 10 ? 'low' : 'ok';

          var statusMap = {
            'active': { label: 'Activo', cls: 'status-active' },
            'paused': { label: 'Pausado', cls: 'status-paused' },
            'closed': { label: 'Cerrado', cls: 'status-closed' },
            'under_review': { label: 'Revisión', cls: 'status-review' }
          };
          var st = statusMap[item.status] || { label: item.status || '-', cls: 'status-other' };
          var category = item.category_name || item.category_id || '-';
          var fallbackSvg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="3" x2="21" y2="21"/></svg>';

          var tr = document.createElement('tr');
          tr.className = 'product-row';
          tr.dataset.id = item.id;

          var imgHtml =
            '<div class="prod-img" style="position:relative">' +
              (item.thumbnail
                ? '<img src="' + item.thumbnail.replace('http://', 'https://') + '" alt="" class="prod-img-src" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><div class="prod-img-fallback" style="display:none">' + fallbackSvg + '</div>'
                : '<div class="prod-img-fallback">' + fallbackSvg + '</div>'
              );
          if (item.has_variations) {
            imgHtml += '<button class="var-toggle" data-id="' + item.id + '" title="Ver variaciones">▶</button>';
          }
          imgHtml += '</div>';

          var detailUrl = '<?php echo URLROOT; ?>/connections/productDetail/' + item.id;

          tr.innerHTML =
            '<td class="td-img">' + imgHtml + '</td>' +
            '<td class="td-title"><span class="prod-link">' + escHtml(item.title) + '</span></td>' +
            '<td class="td-sku">' + (item.sku ? '<span class="sku-tag">' + escHtml(item.sku) + '</span>' : '<span class="text-muted">-</span>') + '</td>' +
            '<td class="td-category">' + escHtml(category) + '</td>' +
            '<td class="td-price">' + item.currency_id + ' $' + price + '</td>' +
            '<td class="td-stock stock-' + stockClass + '">' + item.available_quantity + ' uds' + '</td>' +
            '<td class="td-status"><span class="status-badge ' + st.cls + '">' + st.label + '</span></td>' +
            '<td class="td-link"><a href="' + item.permalink + '" target="_blank" class="prod-mlink" title="Ver en MercadoLibre" onclick="event.stopPropagation()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg></a></td>';

          // Click on row → navigate to detail
          tr.addEventListener('click', function(e) {
            // Don't navigate if clicking the toggle button
            if (e.target.classList.contains('var-toggle')) return;
            window.location.href = detailUrl;
          });

          tr.style.animationDelay = (idx * 0.04) + 's';
          tbody.appendChild(tr);

          // Variations toggle logic
          if (item.has_variations) {
            var varRow = document.createElement('tr');
            varRow.className = 'variations-row';
            varRow.style.animation = 'none';
            varRow.dataset.parentId = item.id;
            varRow.style.display = 'none';
            var varTd = document.createElement('td');
            varTd.colSpan = 8;
            varTd.style.padding = '0';

            var varsHtml = '<div style="border-top:1px solid var(--border);background:var(--bg-sidebar);padding:0.5rem 0.5rem 0.5rem 3rem;">';
            (item.variations || []).forEach(function(v) {
              var vPrice = Number(v.price).toLocaleString('es-CL');
              var vSt = statusMap[v.status] || { label: v.status || '-', cls: 'status-other' };
              var vStockClass = v.available_quantity === 0 ? 'out' : v.available_quantity <= 10 ? 'low' : 'ok';
              var vFallback = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="3" x2="21" y2="21"/></svg>';
              var vDetailUrl = '<?php echo URLROOT; ?>/connections/productDetail/' + v.id;
              varsHtml +=
                '<div class="variation-item" style="display:flex;align-items:center;gap:0.6rem;padding:0.35rem 0;cursor:pointer;border-bottom:1px solid var(--border);transition:background 0.12s;" onclick="window.location.href=\'' + vDetailUrl + '\'" onmouseover="this.style.background=\'var(--card-bg)\'" onmouseout="this.style.background=\'\'">' +
                  '<div style="width:28px;height:28px;border-radius:5px;overflow:hidden;background:var(--card-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;">' +
                      (v.thumbnail
                        ? '<img src="' + v.thumbnail.replace('http://', 'https://') + '" alt="" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display=\'none\';this.parentNode.querySelector(\'.var-fallback\').style.display=\'flex\'"><span class="var-fallback" style="display:none">' + vFallback + '</span>'
                        : '<span class="var-fallback">' + vFallback + '</span>'
                    ) +
                  '</div>' +
                  '<span style="flex:1;font-size:0.8rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-primary);">' + escHtml(v.title) + '</span>' +
                  '<span style="font-size:0.78rem;color:var(--text-muted);width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + (v.sku ? escHtml(v.sku) : '-') + '</span>' +
                  '<span style="font-size:0.8rem;font-weight:500;width:90px;text-align:right;">' + (v.currency_id || 'CLP') + ' $' + vPrice + '</span>' +
                  '<span style="font-size:0.8rem;width:50px;text-align:right;font-weight:500;" class="stock-' + vStockClass + '">' + v.available_quantity + '</span>' +
                  '<span style="width:70px;text-align:center;"><span class="status-badge ' + vSt.cls + '" style="font-size:0.68rem;">' + vSt.label + '</span></span>' +
                '</div>';
            });
            varsHtml += '</div>';
            varTd.innerHTML = varsHtml;
            varRow.appendChild(varTd);
            tbody.appendChild(varRow);

            // Toggle button
            var toggleBtn = tr.querySelector('.var-toggle');
            if (toggleBtn) {
              toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                var expanded = varRow.style.display !== 'none';
                varRow.style.display = expanded ? 'none' : 'table-row';
                this.textContent = expanded ? '▶' : '▼';
              });
            }
          }
        });

        currentPage = paging.page || page;
        table.style.display = '';
        pagination.style.display = 'flex';
        info.style.display = 'block';

        renderPagination(paging);
        renderInfo(paging, items.length);
      })
      .catch(function(err) {
        isLoading = false;
        loading.style.display = 'none';
        errorEl.style.display = 'flex';
        document.getElementById('productsErrorMsg').textContent = 'Error de conexión. Intenta recargar la página.';
      });
  };

  function escHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  function renderPagination(paging) {
    var container = document.getElementById('productsPagination');
    var totalPages = paging.total_pages || 1;
    var page = paging.page || 1;

    if (totalPages <= 1) {
      container.style.display = 'none';
      return;
    }

    var range = 2;
    var start = Math.max(1, page - range);
    var end = Math.min(totalPages, page + range);
    var html = '';

    if (page > 1) {
      html += '<button class="page-btn" data-page="1" title="Primera">&laquo;</button>';
      html += '<button class="page-btn" data-page="' + (page - 1) + '">&lsaquo;</button>';
    }

    if (start > 1) html += '<span class="page-dots">...</span>';

    for (var i = start; i <= end; i++) {
      html += '<button class="page-btn' + (i === page ? ' current' : '') + '" data-page="' + i + '">' + i + '</button>';
    }

    if (end < totalPages) html += '<span class="page-dots">...</span>';

    if (page < totalPages) {
      html += '<button class="page-btn" data-page="' + (page + 1) + '">&rsaquo;</button>';
      html += '<button class="page-btn" data-page="' + totalPages + '" title="Última">&raquo;</button>';
    }

    container.innerHTML = html;
    container.style.display = 'flex';

    container.querySelectorAll('.page-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var p = parseInt(this.getAttribute('data-page'));
        if (p && p !== currentPage) {
          loadProducts(p);
          document.getElementById('productsTableWrap').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  }

  function renderInfo(paging, shownItems) {
    var info = document.getElementById('productsInfo');
    var total = paging.total || shownItems;
    var offset = paging.offset || 0;
    var from = total > 0 ? offset + 1 : 0;
    var to = offset + shownItems;
    info.textContent = 'Mostrando ' + from + '-' + to + ' de ' + total + ' productos';
  }

  // Auto-search with debounce
  var searchInput = document.querySelector('input[name="q"]');
  var searchTimer = null;

  function triggerSearch(query) {
    currentQuery = query.trim();
    currentPage = 1;
    if (currentQuery !== <?php echo json_encode($searchQuery); ?>) {
      loadProducts(1);
      var url = new URL(window.location);
      if (currentQuery) url.searchParams.set('q', currentQuery);
      else url.searchParams.delete('q');
      window.history.replaceState({}, '', url);
    } else {
      loadProducts(1);
    }
  }

  if (searchInput) {
    searchInput.addEventListener('input', function() {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function() {
        triggerSearch(searchInput.value);
      }, 300);
    });

    // Also keep Enter key as immediate search
    searchInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(searchTimer);
        triggerSearch(searchInput.value);
      }
    });
  }

  // Store select — auto-switch via AJAX
  var storeSelect = document.getElementById('storeSelect');
  if (storeSelect) {
    storeSelect.addEventListener('change', function() {
      var val = this.value;
      if (!val) return;
      currentStore = parseInt(val, 10);
      currentPage = 1;
      currentQuery = '';
      if (searchInput) searchInput.value = '';
      loadProducts(1);
      var url = new URL(window.location);
      url.searchParams.set('store', currentStore);
      url.searchParams.delete('q');
      url.searchParams.delete('status');
      currentStatus = '';
      window.history.replaceState({}, '', url);
    });
  }

  // Status filter pills
  document.querySelectorAll('.status-pill').forEach(function(pill) {
    pill.addEventListener('click', function(e) {
      e.preventDefault();
      var status = this.getAttribute('data-status');
      if (status === currentStatus) return;
      currentStatus = status;
      currentPage = 1;
      loadProducts(1);
      // Reset all pills
      document.querySelectorAll('.status-pill').forEach(function(p) {
        p.classList.remove('bg-accent/10', 'text-accent', 'border-accent/30', 'font-medium', 'bg-success/10', 'text-success', 'border-success/30', 'bg-warning/10', 'text-warning', 'border-warning/30', 'bg-danger/10', 'text-danger', 'border-danger/30', 'bg-border', 'text-primary');
        p.classList.add('text-muted', 'border-border');
      });
      // Highlight clicked pill
      var cls = 'bg-accent/10 text-accent border-accent/30';
      if (status === 'active') cls = 'bg-success/10 text-success border-success/30';
      else if (status === 'paused') cls = 'bg-warning/10 text-warning border-warning/30';
      else if (status === 'under_review') cls = 'bg-danger/10 text-danger border-danger/30';
      else if (status === 'closed') cls = 'bg-border text-primary';
      this.classList.remove('text-muted', 'border-border');
      cls.split(' ').forEach(function(c) { pill.classList.add(c); });
      // Update URL
      var url = new URL(window.location);
      if (status) url.searchParams.set('status', status);
      else url.searchParams.delete('status');
      window.history.replaceState({}, '', url);
    });
  });

  // Export modal
  var exportModal = document.getElementById('exportModal');
  var btnExport = document.getElementById('btnExport');
  var exportCancel = document.getElementById('exportCancel');
  var exportConfirm = document.getElementById('exportConfirm');

  btnExport.addEventListener('click', function() {
    exportModal.style.display = 'flex';
  });

  exportCancel.addEventListener('click', function() {
    exportModal.style.display = 'none';
  });

  exportModal.addEventListener('click', function(e) {
    if (e.target === exportModal) exportModal.style.display = 'none';
  });

  function doExport() {
    var checks = exportModal.querySelectorAll('.field-check input[type="checkbox"]');
    var fields = [];
    checks.forEach(function(cb) {
      if (cb.checked) fields.push(cb.value);
    });
    if (fields.length === 0) {
      alert('Selecciona al menos un campo.');
      return;
    }
    var params = fields.map(function(f) { return 'fields[]=' + encodeURIComponent(f); }).join('&');
    var status = document.getElementById('exportStatus').value;
    if (status) params += '&status=' + encodeURIComponent(status);
    var url = '<?php echo URLROOT; ?>/connections/exportXlsx?' + params;

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function(resp) {
      if (!resp.ok) throw new Error('Error al exportar');
      var filename = 'ml_productos.xlsx';
      var disposition = resp.headers.get('Content-Disposition');
      if (disposition && disposition.indexOf('filename=') !== -1) {
        var m = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
        if (m && m[1]) filename = m[1].replace(/['"]/g, '');
      }
      if (typeof showDownloadToast === 'function') showDownloadToast(filename, 6000);
      return resp.blob().then(function(blob) {
        var blobUrl = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = blobUrl; a.download = filename;
        document.body.appendChild(a); a.click(); a.remove();
        URL.revokeObjectURL(blobUrl);
      });
    }).catch(function(err) {
      if (typeof showToast === 'function') showToast(err.message, 'error', 4000);
    });
    exportModal.style.display = 'none';
  }

  var estandarModal = document.getElementById('estandarModal');
  var estandarBack = document.getElementById('estandarBack');
  var estandarConfirm = document.getElementById('estandarConfirm');

  exportConfirm.addEventListener('click', function() {
    var checks = exportModal.querySelectorAll('.field-check input[type="checkbox"]');
    var hasSku = false, hasCodigo = false;
    checks.forEach(function(cb) {
      if (cb.checked) {
        if (cb.value === 'sku') hasSku = true;
        if (cb.value === 'codigo') hasCodigo = true;
      }
    });
    <?php if ($estandarCount > 0): ?>
    if (hasSku && !hasCodigo && estandarModal) {
      estandarModal.style.display = 'flex';
      return;
    }
    <?php endif; ?>
    doExport();
  });

  if (estandarBack) {
    estandarBack.addEventListener('click', function() {
      estandarModal.style.display = 'none';
    });
  }
  if (estandarConfirm) {
    estandarConfirm.addEventListener('click', function() {
      estandarModal.style.display = 'none';
      doExport();
    });
  }
  if (estandarModal) {
    estandarModal.addEventListener('click', function(e) {
      if (e.target === estandarModal) estandarModal.style.display = 'none';
    });
  }

  // Initial load
  loadProducts(1);
})();
</script>
<?php endif; ?>
