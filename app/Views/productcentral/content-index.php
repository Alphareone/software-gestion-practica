<?php
$connections = $data['connections'] ?? [];
$storeFilter = $data['store_filter'] ?? null;
$searchQuery = $data['search_query'] ?? '';
$statusFilter = $data['status_filter'] ?? '';
$isAdmin = !empty($data['is_admin']);
$csrfToken = $data['csrf_token'] ?? '';
$q = htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8');
?>

<?php if (empty($connections)): ?>
<div class="dash-empty" style="margin-top:8rem;padding:0;">
  <h3>No hay tiendas conectadas</h3>
  <p>Actualmente no hay ninguna tienda conectada. Contacta a un administrador para que configure una tienda.</p>
</div>
<?php else: ?>

<div class="ahub">

  <!-- Filtros -->
  <div class="flex items-center gap-3 mb-5 flex-wrap">
    <select id="storeSelect" class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2.5 py-1.5 focus:outline-none focus:border-accent cursor-pointer">
      <option value="">Todas las tiendas</option>
      <?php foreach ($connections as $c): ?>
        <option value="<?php echo (int)$c->id; ?>" <?php echo $storeFilter && (int)$c->id === (int)$storeFilter ? 'selected' : ''; ?>><?php echo htmlspecialchars($c->store_name ?? $c->ml_nickname ?? 'Sin nombre'); ?></option>
      <?php endforeach; ?>
    </select>

    <form method="get" class="flex items-center gap-2">
      <?php if ($storeFilter): ?>
        <input type="hidden" name="store" value="<?php echo (int)$storeFilter; ?>">
      <?php endif; ?>
      <input type="text" name="q" placeholder="Buscar por SKU o nombre…" value="<?php echo $q; ?>" class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2.5 py-1.5 focus:outline-none focus:border-accent w-48">
      <?php if ($searchQuery !== ''): ?>
        <a href="?<?php echo $storeFilter ? 'store=' . (int)$storeFilter : ''; ?>" class="text-xs text-muted hover:text-primary transition-colors"><?php echo icon('x', ['size' => 14]); ?></a>
      <?php endif; ?>
    </form>

    <div class="flex items-center gap-1.5">
      <a href="#" data-status="" class="status-pill text-xs px-2.5 py-1.5 rounded-lg border border-border transition-colors <?php echo empty($statusFilter) ? 'bg-accent/10 text-accent border-accent/30' : 'text-muted hover:border-muted hover:text-primary'; ?>">Todas</a>
      <a href="#" data-status="active" class="status-pill text-xs px-2.5 py-1.5 rounded-lg border border-border transition-colors flex items-center gap-1 <?php echo $statusFilter === 'active' ? 'bg-success/10 text-success border-success/30' : 'text-muted hover:border-muted hover:text-primary'; ?>"><span class="w-1.5 h-1.5 rounded-full bg-success"></span>Activo</a>
      <a href="#" data-status="paused" class="status-pill text-xs px-2.5 py-1.5 rounded-lg border border-border transition-colors flex items-center gap-1 <?php echo $statusFilter === 'paused' ? 'bg-warning/10 text-warning border-warning/30' : 'text-muted hover:border-muted hover:text-primary'; ?>"><span class="w-1.5 h-1.5 rounded-full bg-warning"></span>Pausado</a>
      <a href="#" data-status="closed" class="status-pill text-xs px-2.5 py-1.5 rounded-lg border border-border transition-colors flex items-center gap-1 <?php echo $statusFilter === 'closed' ? 'bg-border text-primary' : 'text-muted hover:border-muted hover:text-primary'; ?>"><span class="w-1.5 h-1.5 rounded-full" style="background:var(--text-secondary)"></span>Cerrado</a>
      <a href="#" data-status="under_review" class="status-pill text-xs px-2.5 py-1.5 rounded-lg border border-border transition-colors flex items-center gap-1 <?php echo $statusFilter === 'under_review' ? 'bg-danger/10 text-danger border-danger/30' : 'text-muted hover:border-muted hover:text-primary'; ?>"><span class="w-1.5 h-1.5 rounded-full bg-danger"></span>Revisión</a>
    </div>
  </div>

  <?php if ($isAdmin): ?>
  <!-- Carga de descripciones (admin) -->
  <div id="descPanel" class="flex items-center gap-3 mb-5 flex-wrap text-xs rounded-lg border border-border bg-surface/50 px-3 py-2.5">
    <button type="button" id="descBtn" class="text-xs px-2.5 py-1.5 rounded-lg border border-accent/30 bg-accent/10 text-accent hover:bg-accent/20 transition-colors cursor-pointer">Traer descripciones</button>
    <span id="descStats" class="text-muted">Cargando estado…</span>
    <span id="descProgress" class="text-muted" style="display:none;"></span>
  </div>

  <!-- Sugerencias de vínculos entre tiendas (admin) -->
  <div id="suggPanel" class="flex items-center gap-3 mb-5 flex-wrap text-xs rounded-lg border border-border bg-surface/50 px-3 py-2.5">
    <button type="button" id="suggBtn" class="text-xs px-2.5 py-1.5 rounded-lg border border-accent/30 bg-accent/10 text-accent hover:bg-accent/20 transition-colors cursor-pointer">Revisar sugerencias de vínculos</button>
    <span id="suggStats" class="text-muted">Cargando…</span>
  </div>
  <?php endif; ?>

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
          <th class="th-store">Tienda</th>
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

<script>
(function() {
  var baseUrl = '<?php echo URLROOT; ?>/productcentral/data';
  var familyUrl = '<?php echo URLROOT; ?>/productcentral/familyMembers';
  var linkSearchUrl = '<?php echo URLROOT; ?>/productcentral/linkSearch';
  var linkCreateUrl = '<?php echo URLROOT; ?>/productcentral/linkCreate';
  var linkAddMemberUrl = '<?php echo URLROOT; ?>/productcentral/linkAddMember';
  var linkRemoveMemberUrl = '<?php echo URLROOT; ?>/productcentral/linkRemoveMember';
  var linkMembersUrl = '<?php echo URLROOT; ?>/productcentral/linkMembers';
  var isAdminUser = <?php echo json_encode($isAdmin); ?>;
  var csrfTokenMain = <?php echo json_encode($csrfToken); ?>;
  var pageSize = 20;
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
          var msg = currentQuery ? 'No se encontraron productos para "' + currentQuery + '".' : currentStatus ? 'No hay productos con ese estado.' : 'No hay productos en el catálogo central.';
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

          var familyStock = item.member_count > 1 ? item.family_stock : item.available_quantity;
          var stockClassFam = familyStock === 0 ? 'out' : familyStock <= 10 ? 'low' : 'ok';

          var tr = document.createElement('tr');
          tr.className = 'product-row';
          tr.dataset.id = item.id;
          tr.dataset.connectionId = item.ml_connection_id;
          tr.dataset.familyKey = item.family_key;
          tr.dataset.crossLinkId = item.cross_link_id || '';

          var imgHtml =
            '<div class="prod-img" style="position:relative">' +
              (item.thumbnail
                ? '<img src="' + item.thumbnail.replace('http://', 'https://') + '" alt="" class="prod-img-src" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><div class="prod-img-fallback" style="display:none">' + fallbackSvg + '</div>'
                : '<div class="prod-img-fallback">' + fallbackSvg + '</div>'
              ) + '</div>';

          var titleHtml = escHtml(item.title);
          if (item.member_count > 1) {
            titleHtml += '<br><span class="sku-tag variant-badge" style="cursor:pointer;">' + item.member_count + ' variantes/presentaciones ▾</span>';
          }

          var skuHtml = item.sku ? '<span class="sku-tag">' + escHtml(item.sku) + '</span>' : '<span class="text-muted">-</span>';
          if (item.sku_pack_type) {
            var packCls = item.sku_pack_type === 'TRIPACK' ? 'status-review' : 'status-paused';
            skuHtml += '<br><span class="status-badge ' + packCls + '" style="font-size:0.65rem;padding:1px 6px;">' + item.sku_pack_type + '</span>';
            skuHtml += item.sku_base
              ? '<span class="text-muted" style="font-size:0.7rem;"> de ' + escHtml(item.sku_base) + '</span>'
              : '<span class="text-muted" style="font-size:0.7rem;"> (sin base)</span>';
          }

          var storeHtml = escHtml(item.store_name || '-');
          if (item.cross_link_id) {
            storeHtml += '<br><span class="sku-tag cross-link-badge" style="cursor:pointer;">Vinculado: ' + item.cross_link_store_count + ' tiendas</span>';
          } else if (isAdminUser) {
            storeHtml += '<br><span class="text-muted cross-link-add" style="cursor:pointer;font-size:0.72rem;text-decoration:underline;">Vincular con otra tienda</span>';
          }

          tr.innerHTML =
            '<td class="td-img">' + imgHtml + '</td>' +
            '<td class="td-title"><span class="prod-link">' + titleHtml + '</span></td>' +
            '<td class="td-sku">' + skuHtml + '</td>' +
            '<td class="td-category">' + escHtml(category) + '</td>' +
            '<td class="td-store">' + storeHtml + '</td>' +
            '<td class="td-price">' + item.currency_id + ' $' + price + '</td>' +
            '<td class="td-stock stock-' + stockClassFam + '">' + familyStock + ' uds' + '</td>' +
            '<td class="td-status"><span class="status-badge ' + st.cls + '">' + st.label + '</span></td>' +
            '<td class="td-link"><a href="' + item.permalink + '" target="_blank" class="prod-mlink" title="Ver en MercadoLibre" onclick="event.stopPropagation()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg></a></td>';

          tr.style.animationDelay = (idx * 0.04) + 's';
          tbody.appendChild(tr);
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
    div.appendChild(document.createTextNode(str || ''));
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

  // ── Expandir variantes/packs de una familia ──
  var tbodyEl = document.getElementById('productsBody');
  tbodyEl.addEventListener('click', function(e) {
    var badge = e.target.closest('.variant-badge');
    if (badge) {
      e.stopPropagation();
      toggleFamilyDetail(badge.closest('tr'));
      return;
    }
    var linkBadge = e.target.closest('.cross-link-badge');
    if (linkBadge) {
      e.stopPropagation();
      openLinkMembersPanel(linkBadge.closest('tr'));
      return;
    }
    var addLink = e.target.closest('.cross-link-add');
    if (addLink) {
      e.stopPropagation();
      openLinkSearchModal(addLink.closest('tr'));
      return;
    }
  });

  function toggleFamilyDetail(row) {
    var next = row.nextElementSibling;
    if (next && next.classList.contains('family-detail-row')) {
      next.remove();
      return;
    }
    var connectionId = row.dataset.connectionId;
    var familyKey = row.dataset.familyKey;
    var detailRow = document.createElement('tr');
    detailRow.className = 'family-detail-row';
    var td = document.createElement('td');
    td.colSpan = 9;
    td.style.padding = '0.5rem 1rem';
    td.textContent = 'Cargando variantes…';
    detailRow.appendChild(td);
    row.parentNode.insertBefore(detailRow, row.nextSibling);

    fetch(familyUrl + '?connection=' + encodeURIComponent(connectionId) + '&family=' + encodeURIComponent(familyKey), { credentials: 'same-origin' })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data.ok || !data.items || !data.items.length) {
          td.textContent = 'No se pudieron cargar las variantes.';
          return;
        }
        var html = '<table class="family-detail-table" style="width:100%;font-size:0.78rem;">' +
          '<thead><tr><th style="text-align:left;padding:2px 6px;">SKU</th><th style="text-align:left;padding:2px 6px;">Tipo</th><th style="text-align:right;padding:2px 6px;">Precio</th><th style="text-align:right;padding:2px 6px;">Stock</th><th style="text-align:left;padding:2px 6px;">Estado</th></tr></thead><tbody>';
        data.items.forEach(function(m) {
          html += '<tr>' +
            '<td style="padding:2px 6px;">' + (m.sku ? escHtml(m.sku) : '<span class="text-muted">-</span>') + '</td>' +
            '<td style="padding:2px 6px;">' + (m.sku_pack_type ? escHtml(m.sku_pack_type) : (m.parent_ml_item_id ? 'Variante' : 'Individual')) + '</td>' +
            '<td style="padding:2px 6px;text-align:right;">' + Number(m.price).toLocaleString('es-CL') + '</td>' +
            '<td style="padding:2px 6px;text-align:right;">' + m.available_quantity + '</td>' +
            '<td style="padding:2px 6px;">' + escHtml(m.status || '-') + '</td>' +
            '</tr>';
        });
        html += '</tbody></table>';
        td.innerHTML = html;
      })
      .catch(function() { td.textContent = 'Error al cargar las variantes.'; });
  }

  // ── Vínculo manual entre tiendas (admin) ──
  function openLinkMembersPanel(row) {
    var linkId = row.dataset.crossLinkId;
    if (!linkId) return;

    fetch(linkMembersUrl + '?link_id=' + encodeURIComponent(linkId), { credentials: 'same-origin' })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data.ok) return;
        var lines = data.items.map(function(m) {
          var store = m.store_name || m.ml_nickname || '-';
          return store + ': ' + m.title;
        });
        var msg = 'Productos vinculados:\n\n' + lines.join('\n');
        if (isAdminUser) {
          if (confirm(msg + '\n\n¿Quitar el vínculo de este producto?')) {
            removeCrossLinkMember(linkId, row.dataset.connectionId, row.dataset.familyKey);
          }
        } else {
          alert(msg);
        }
      });
  }

  function removeCrossLinkMember(linkId, connectionId, familyKey) {
    var body = new URLSearchParams();
    body.set('csrf_token', csrfTokenMain);
    body.set('link_id', linkId);
    body.set('connection_id', connectionId);
    body.set('family_key', familyKey);

    fetch(linkRemoveMemberUrl, { method: 'POST', credentials: 'same-origin', body: body })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data.ok) loadProducts(currentPage);
        else alert(data.error || 'No se pudo quitar el vínculo.');
      });
  }

  function openLinkSearchModal(row) {
    if (!isAdminUser) return;
    var connectionId = parseInt(row.dataset.connectionId, 10);
    var familyKey = row.dataset.familyKey;

    var overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;';
    overlay.innerHTML =
      '<div style="background:var(--bg-surface,#1a1a1a);border-radius:12px;padding:1.25rem;width:420px;max-width:90vw;max-height:80vh;overflow:auto;">' +
        '<h3 style="margin:0 0 0.75rem;font-size:0.95rem;">Vincular con producto de otra tienda</h3>' +
        '<input type="text" id="linkSearchInput" placeholder="Buscar por título o SKU…" style="width:100%;padding:0.4rem 0.6rem;border-radius:8px;border:1px solid var(--border,#333);margin-bottom:0.75rem;background:transparent;color:inherit;">' +
        '<div id="linkSearchResults" style="display:flex;flex-direction:column;gap:0.4rem;"></div>' +
        '<div style="text-align:right;margin-top:0.75rem;"><button type="button" id="linkSearchClose" style="padding:0.35rem 0.8rem;border-radius:8px;border:1px solid var(--border,#333);background:transparent;color:inherit;cursor:pointer;">Cerrar</button></div>' +
      '</div>';
    document.body.appendChild(overlay);

    var input = overlay.querySelector('#linkSearchInput');
    var results = overlay.querySelector('#linkSearchResults');
    var timer = null;

    function runSearch() {
      var q = input.value.trim();
      fetch(linkSearchUrl + '?q=' + encodeURIComponent(q) + '&exclude=' + connectionId, { credentials: 'same-origin' })
        .then(function(res) { return res.json(); })
        .then(function(data) {
          results.innerHTML = '';
          if (!data.ok || !data.items.length) {
            results.innerHTML = '<div class="text-muted" style="font-size:0.8rem;">Sin resultados.</div>';
            return;
          }
          data.items.forEach(function(candidate) {
            var opt = document.createElement('div');
            opt.style.cssText = 'display:flex;justify-content:space-between;align-items:center;padding:0.4rem 0.5rem;border:1px solid var(--border,#333);border-radius:8px;cursor:pointer;font-size:0.8rem;';
            opt.innerHTML = '<span>' + escHtml(candidate.store_name || candidate.ml_nickname || '-') + ' — ' + escHtml(candidate.title) + '</span>';
            opt.addEventListener('click', function() {
              createCrossLink(connectionId, familyKey, candidate.ml_connection_id, candidate.family_key, overlay);
            });
            results.appendChild(opt);
          });
        });
    }

    input.addEventListener('input', function() {
      clearTimeout(timer);
      timer = setTimeout(runSearch, 300);
    });
    overlay.querySelector('#linkSearchClose').addEventListener('click', function() {
      overlay.remove();
    });
    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) overlay.remove();
    });

    runSearch();
  }

  function createCrossLink(connectionId, familyKey, otherConnectionId, otherFamilyKey, overlay) {
    var body = new URLSearchParams();
    body.set('csrf_token', csrfTokenMain);
    body.set('members', JSON.stringify([
      { connection_id: connectionId, family_key: familyKey },
      { connection_id: otherConnectionId, family_key: otherFamilyKey }
    ]));

    fetch(linkCreateUrl, { method: 'POST', credentials: 'same-origin', body: body })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        overlay.remove();
        if (!data.ok) {
          alert(data.error || 'No se pudo crear el vínculo.');
          return;
        }
        loadProducts(currentPage);
      })
      .catch(function() {
        overlay.remove();
        alert('Error de conexión al crear el vínculo.');
      });
  }

  // Auto-search with debounce
  var searchInput = document.querySelector('input[name="q"]');
  var searchTimer = null;

  function triggerSearch(query) {
    currentQuery = query.trim();
    currentPage = 1;
    loadProducts(1);
    var url = new URL(window.location);
    if (currentQuery) url.searchParams.set('q', currentQuery);
    else url.searchParams.delete('q');
    window.history.replaceState({}, '', url);
  }

  if (searchInput) {
    searchInput.addEventListener('input', function() {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function() {
        triggerSearch(searchInput.value);
      }, 300);
    });

    searchInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(searchTimer);
        triggerSearch(searchInput.value);
      }
    });
  }

  // Store select — filtra vía AJAX, sin persistir en sesión
  var storeSelect = document.getElementById('storeSelect');
  if (storeSelect) {
    storeSelect.addEventListener('change', function() {
      var val = this.value;
      currentStore = val ? parseInt(val, 10) : null;
      currentPage = 1;
      loadProducts(1);
      var url = new URL(window.location);
      if (currentStore) url.searchParams.set('store', currentStore);
      else url.searchParams.delete('store');
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
      document.querySelectorAll('.status-pill').forEach(function(p) {
        p.classList.remove('bg-accent/10', 'text-accent', 'border-accent/30', 'font-medium', 'bg-success/10', 'text-success', 'border-success/30', 'bg-warning/10', 'text-warning', 'border-warning/30', 'bg-danger/10', 'text-danger', 'border-danger/30', 'bg-border', 'text-primary');
        p.classList.add('text-muted', 'border-border');
      });
      var cls = 'bg-accent/10 text-accent border-accent/30';
      if (status === 'active') cls = 'bg-success/10 text-success border-success/30';
      else if (status === 'paused') cls = 'bg-warning/10 text-warning border-warning/30';
      else if (status === 'under_review') cls = 'bg-danger/10 text-danger border-danger/30';
      else if (status === 'closed') cls = 'bg-border text-primary';
      this.classList.remove('text-muted', 'border-border');
      cls.split(' ').forEach(function(c) { pill.classList.add(c); });
      var url = new URL(window.location);
      if (status) url.searchParams.set('status', status);
      else url.searchParams.delete('status');
      window.history.replaceState({}, '', url);
    });
  });

  // Initial load
  loadProducts(1);
})();
</script>

<?php if ($isAdmin): ?>
<script>
(function() {
  var statusUrl = '<?php echo URLROOT; ?>/productcentral/descriptionsStatus';
  var processUrl = '<?php echo URLROOT; ?>/productcentral/descriptionsProcess';
  var csrfToken = <?php echo json_encode($csrfToken); ?>;
  var btn = document.getElementById('descBtn');
  var statsEl = document.getElementById('descStats');
  var progressEl = document.getElementById('descProgress');
  var running = false;

  function renderStats(s) {
    statsEl.textContent = s.done + ' de ' + s.total + ' con descripción · ' + s.pending + ' pendientes' +
      (s.blocked_inactive > 0 ? ' · ' + s.blocked_inactive + ' bloqueadas (tienda inactiva)' : '');
    btn.style.display = s.pending > 0 ? '' : 'none';
  }

  function loadStatus() {
    fetch(statusUrl, { credentials: 'same-origin' })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data.ok) renderStats(data);
        else statsEl.textContent = 'No se pudo cargar el estado.';
      })
      .catch(function() { statsEl.textContent = 'No se pudo cargar el estado.'; });
  }

  function processChunk() {
    var body = new URLSearchParams();
    body.set('csrf_token', csrfToken);

    fetch(processUrl, { method: 'POST', credentials: 'same-origin', body: body })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data.ok) {
          progressEl.style.display = 'none';
          statsEl.textContent = data.error || 'Error al procesar.';
          running = false;
          btn.disabled = false;
          btn.textContent = 'Traer descripciones';
          return;
        }

        renderStats(data.stats);
        progressEl.style.display = '';
        progressEl.textContent = 'Última tanda: ' + data.chunk.processed + ' procesadas (' +
          data.chunk.fetched + ' con texto, ' + data.chunk.empty + ' sin descripción en ML, ' +
          data.chunk.errors + ' errores).';

        if (data.stats.pending > 0 && running) {
          processChunk();
        } else {
          running = false;
          btn.disabled = false;
          btn.textContent = 'Traer descripciones';
        }
      })
      .catch(function() {
        running = false;
        btn.disabled = false;
        btn.textContent = 'Traer descripciones';
        progressEl.style.display = '';
        progressEl.textContent = 'Error de conexión, se detuvo. Puedes volver a apretar el botón para continuar.';
      });
  }

  btn.addEventListener('click', function() {
    if (running) return;
    running = true;
    btn.disabled = true;
    btn.textContent = 'Procesando…';
    processChunk();
  });

  loadStatus();
})();
</script>

<script>
(function() {
  var suggestionsUrl = '<?php echo URLROOT; ?>/productcentral/linkSuggestions';
  var dismissUrl = '<?php echo URLROOT; ?>/productcentral/linkDismissSuggestion';
  var createUrl = '<?php echo URLROOT; ?>/productcentral/linkCreate';
  var csrfToken = <?php echo json_encode($csrfToken); ?>;
  var btn = document.getElementById('suggBtn');
  var statsEl = document.getElementById('suggStats');
  var pageSize = 20;
  var offset = 0;

  function escHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str || ''));
    return div.innerHTML;
  }

  function loadCount() {
    fetch(suggestionsUrl + '?limit=1&offset=0', { credentials: 'same-origin' })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data.ok) { statsEl.textContent = 'No se pudo cargar el estado.'; return; }
        statsEl.textContent = data.total + ' grupo(s) de posibles duplicados entre tiendas.';
      })
      .catch(function() { statsEl.textContent = 'No se pudo cargar el estado.'; });
  }

  function openPanel() {
    offset = 0;
    var overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;';
    overlay.innerHTML =
      '<div style="background:var(--bg-surface,#1a1a1a);border-radius:12px;padding:1.25rem;width:640px;max-width:92vw;max-height:85vh;overflow:auto;">' +
        '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">' +
          '<h3 style="margin:0;font-size:0.95rem;">Sugerencias de vínculos entre tiendas</h3>' +
          '<button type="button" id="suggClose" style="padding:0.3rem 0.7rem;border-radius:8px;border:1px solid var(--border,#333);background:transparent;color:inherit;cursor:pointer;">Cerrar</button>' +
        '</div>' +
        '<div id="suggList" style="display:flex;flex-direction:column;gap:0.6rem;"></div>' +
        '<div style="text-align:center;margin-top:0.75rem;"><button type="button" id="suggMore" style="padding:0.4rem 1rem;border-radius:8px;border:1px solid var(--border,#333);background:transparent;color:inherit;cursor:pointer;">Cargar más</button></div>' +
      '</div>';
    document.body.appendChild(overlay);

    overlay.querySelector('#suggClose').addEventListener('click', function() { overlay.remove(); });
    overlay.addEventListener('click', function(e) { if (e.target === overlay) overlay.remove(); });
    overlay.querySelector('#suggMore').addEventListener('click', function() { loadPage(overlay); });

    loadPage(overlay);
  }

  function loadPage(overlay) {
    var list = overlay.querySelector('#suggList');
    var moreBtn = overlay.querySelector('#suggMore');
    moreBtn.textContent = 'Cargando…';
    moreBtn.disabled = true;

    fetch(suggestionsUrl + '?limit=' + pageSize + '&offset=' + offset, { credentials: 'same-origin' })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        moreBtn.disabled = false;
        moreBtn.textContent = 'Cargar más';

        if (!data.ok || !data.items.length) {
          if (offset === 0) list.innerHTML = '<div class="text-muted" style="font-size:0.8rem;">No hay sugerencias pendientes.</div>';
          moreBtn.style.display = 'none';
          return;
        }

        data.items.forEach(function(group) {
          var card = document.createElement('div');
          card.style.cssText = 'border:1px solid var(--border,#333);border-radius:10px;padding:0.6rem 0.75rem;';
          var isSku = group.match_type === 'sku';
          var membersHtml = group.members.map(function(m) {
            return '<div style="font-size:0.78rem;padding:2px 0;">' + escHtml(m.store_name) + ' — ' +
              (isSku ? escHtml(m.title) + ' — ' : '') +
              (m.sku ? escHtml(m.sku) : '<span class="text-muted">sin SKU</span>') + ' — $' + Number(m.price).toLocaleString('es-CL') + '</div>';
          }).join('');
          var heading = isSku
            ? 'SKU ' + escHtml(group.match_value) + ' <span style="font-size:0.68rem;font-weight:500;padding:1px 6px;border-radius:6px;border:1px solid var(--border,#333);color:var(--muted,#888);margin-left:4px;">por SKU</span>'
            : escHtml(group.match_value);
          card.innerHTML =
            '<div style="font-weight:600;font-size:0.85rem;margin-bottom:0.3rem;">' + heading + ' <span class="text-muted" style="font-weight:400;">(' + group.store_count + ' tiendas)</span></div>' +
            membersHtml +
            '<div style="margin-top:0.5rem;display:flex;gap:0.5rem;">' +
              '<button type="button" class="sugg-approve" style="padding:0.3rem 0.7rem;border-radius:8px;border:1px solid var(--accent,#4a9eff);background:transparent;color:inherit;cursor:pointer;font-size:0.78rem;">Vincular todos</button>' +
              '<button type="button" class="sugg-dismiss" style="padding:0.3rem 0.7rem;border-radius:8px;border:1px solid var(--border,#333);background:transparent;color:inherit;cursor:pointer;font-size:0.78rem;">Ignorar</button>' +
            '</div>';

          card.querySelector('.sugg-approve').addEventListener('click', function() {
            approveGroup(group, card);
          });
          card.querySelector('.sugg-dismiss').addEventListener('click', function() {
            dismissGroup(group.match_type, group.match_value, card);
          });

          list.appendChild(card);
        });

        offset += data.items.length;
        if (data.items.length < pageSize) moreBtn.style.display = 'none';
      })
      .catch(function() {
        moreBtn.disabled = false;
        moreBtn.textContent = 'Cargar más';
      });
  }

  function approveGroup(group, card) {
    var body = new URLSearchParams();
    body.set('csrf_token', csrfToken);
    body.set('members', JSON.stringify(group.members.map(function(m) {
      return { connection_id: m.connection_id, family_key: m.family_key };
    })));

    fetch(createUrl, { method: 'POST', credentials: 'same-origin', body: body })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data.ok) { alert(data.error || 'No se pudo vincular.'); return; }
        card.remove();
        loadCount();
        if (window.loadProducts) window.loadProducts(1);
      });
  }

  function dismissGroup(matchType, matchValue, card) {
    var body = new URLSearchParams();
    body.set('csrf_token', csrfToken);
    body.set('match_type', matchType);
    body.set('match_value', matchValue);

    fetch(dismissUrl, { method: 'POST', credentials: 'same-origin', body: body })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data.ok) { alert(data.error || 'No se pudo ignorar.'); return; }
        card.remove();
        loadCount();
      });
  }

  btn.addEventListener('click', openPanel);
  loadCount();
})();
</script>
<?php endif; ?>

<?php endif; ?>
