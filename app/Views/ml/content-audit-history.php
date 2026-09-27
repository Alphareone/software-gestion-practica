<?php
$connections = $data['connections'] ?? [];
$selectedStoreId = $data['selected_store_id'] ?? 'all';
$searchQuery = $data['search_query'] ?? '';
$statusFilter = $data['status_filter'] ?? 'all';
$statusCounts = $data['status_counts'] ?? ['perfect' => 0, 'warn' => 0, 'danger' => 0];
$csrfToken = $data['csrf_token'] ?? '';
$typeLabels = [
    'prices' => 'Precios',
    'stock'  => 'Stock',
];
?>
<div class="ahub">
  <div class="audit-breadcrumb">
    <a href="<?php echo URLROOT; ?>/connections/auditHub" class="audit-back">
      <?php echo icon('arrow-left', ['size' => 14]); ?>
      Panel de auditorías
    </a>
  </div>

  <!-- Filtros -->
  <div class="flex items-center gap-3 mb-5 flex-wrap">
    <select id="ahStoreSelect" class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2.5 py-1.5 focus:outline-none focus:border-accent cursor-pointer">
      <option value="all" <?php echo $selectedStoreId === 'all' ? 'selected' : ''; ?>>Todas las tiendas</option>
      <?php foreach ($connections as $c): ?>
        <option value="<?php echo (int)$c->id; ?>" <?php echo $selectedStoreId === (string)$c->id ? 'selected' : ''; ?>><?php echo htmlspecialchars($c->store_name ?? 'Sin nombre'); ?></option>
      <?php endforeach; ?>
    </select>

    <div class="flex items-center gap-2">
      <input type="text" id="ahSearchInput" placeholder="Buscar por ID de lote…" value="<?php echo htmlspecialchars($searchQuery); ?>" class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2.5 py-1.5 focus:outline-none focus:border-accent w-48">
      <?php if ($searchQuery !== ''): ?>
        <button id="ahSearchClear" class="text-xs text-muted hover:text-primary transition-colors bg-transparent border-none cursor-pointer p-0"><?php echo icon('x', ['size' => 14]); ?></button>
      <?php endif; ?>
    </div>

    <div class="flex items-center gap-1.5 ml-auto">
      <span class="text-xs text-muted mr-1">Estado:</span>
      <button data-status="all" class="ah-status-pill text-xs px-2.5 py-1.5 rounded-lg border transition-colors cursor-pointer bg-transparent <?php echo $statusFilter === 'all' ? 'bg-accent/10 text-accent border-accent/30' : 'border-border text-muted hover:border-muted hover:text-primary'; ?>">Todas</button>
      <button data-status="perfect" class="ah-status-pill text-xs px-2.5 py-1.5 rounded-lg border transition-colors cursor-pointer bg-transparent flex items-center gap-1 <?php echo $statusFilter === 'perfect' ? 'bg-success/10 text-success border-success/30' : 'border-border text-muted hover:border-muted hover:text-primary'; ?>"><span class="w-1.5 h-1.5 rounded-full bg-success"></span>Perfecto (<span class="ah-count" data-status="perfect"><?php echo $statusCounts['perfect']; ?></span>)</button>
      <button data-status="warn" class="ah-status-pill text-xs px-2.5 py-1.5 rounded-lg border transition-colors cursor-pointer bg-transparent flex items-center gap-1 <?php echo $statusFilter === 'warn' ? 'bg-warning/10 text-warning border-warning/30' : 'border-border text-muted hover:border-muted hover:text-primary'; ?>"><span class="w-1.5 h-1.5 rounded-full bg-warning"></span>Parcial (<span class="ah-count" data-status="warn"><?php echo $statusCounts['warn']; ?></span>)</button>
      <button data-status="danger" class="ah-status-pill text-xs px-2.5 py-1.5 rounded-lg border transition-colors cursor-pointer bg-transparent flex items-center gap-1 <?php echo $statusFilter === 'danger' ? 'bg-danger/10 text-danger border-danger/30' : 'border-border text-muted hover:border-muted hover:text-primary'; ?>"><span class="w-1.5 h-1.5 rounded-full bg-danger"></span>Crítico (<span class="ah-count" data-status="danger"><?php echo $statusCounts['danger']; ?></span>)</button>
    </div>
    <?php if (!empty($data['is_admin'])): ?>
    <button id="ahPurgeBtn" class="text-xs px-2.5 py-1.5 rounded-lg border border-danger/50 text-danger hover:text-danger hover:border-danger hover:bg-danger/5 transition-colors flex items-center gap-1.5 bg-transparent cursor-pointer" title="Purgar auditorías">
      <?php echo icon('trash-2', ['size' => 12]); ?>
      Purga
    </button>
    <?php endif; ?>
  </div>

  <!-- Content -->
  <div class="card">
    <div id="ahContent">
    <div class="audit-batch-empty" id="ahEmpty" <?php echo empty($data['batches']) ? '' : 'style="display:none;"'; ?>>
      <p><?php
        if ($searchQuery !== '') echo 'No se encontraron auditorías con ese ID de lote.';
        elseif ($statusFilter !== 'all') echo 'No hay auditorías con ese estado.';
        elseif ($selectedStoreId !== 'all') echo 'Esta tienda no ha tenido auditorías.';
        else echo 'Todavía no hay auditorías. Ejecuta una desde el panel de auditorías.';
      ?></p>
    </div>
    <div id="ahLoading" style="display:flex;flex-direction:column;align-items:center;gap:0.75rem;padding:3rem 1rem;color:var(--text-secondary);font-size:0.9rem;">
      <div class="loading-spinner" style="width:28px;height:28px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin 0.7s linear infinite;"></div>
      <span>Cargando auditorías...</span>
    </div>
    <div id="ahList" style="display:none;"></div>
  </div>
  </div>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
(function() {
  var currentStore = '<?php echo $selectedStoreId; ?>';
  var currentQuery = <?php echo json_encode($searchQuery); ?>;
  var currentStatus = '<?php echo $statusFilter; ?>';

  function fetchData() {
    var params = new URLSearchParams();
    if (currentStore !== 'all') params.set('store', currentStore);
    if (currentQuery) params.set('q', currentQuery);
    if (currentStatus !== 'all') params.set('status', currentStatus);

    var url = '<?php echo URLROOT; ?>/connections/auditHistoryData?' + params.toString();

    document.getElementById('ahList').style.display = 'none';
    document.getElementById('ahEmpty').style.display = 'none';
    document.getElementById('ahLoading').style.display = 'flex';

    fetch(url, { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        document.getElementById('ahLoading').style.display = 'none';

        // Update status counts
        if (data.status_counts) {
          document.querySelectorAll('.ah-count').forEach(function(el) {
            var s = el.getAttribute('data-status');
            el.textContent = data.status_counts[s] || 0;
          });
        }

        var groups = data.groups || {};
        var keys = Object.keys(groups);
        // Sort groups: Hoy, Ayer, Esta semana, Anteriores
        var sortOrder = { 'Hoy': 0, 'Ayer': 1, 'Esta semana': 2 };
        keys.sort(function(a, b) {
          var oa = sortOrder[a] !== undefined ? sortOrder[a] : 9;
          var ob = sortOrder[b] !== undefined ? sortOrder[b] : 9;
          return oa - ob;
        });

        if (keys.length === 0) {
          document.getElementById('ahEmpty').style.display = 'flex';
          var msg = currentQuery ? 'No se encontraron auditorías con ese ID de lote.' : currentStatus !== 'all' ? 'No hay auditorías con ese estado.' : currentStore !== 'all' ? 'Esta tienda no ha tenido auditorías.' : 'Todavía no hay auditorías.';
          document.querySelector('#ahEmpty p').textContent = msg;
          return;
        }

        var html = '<div class="audit-batch-list">';
        var rowIdx = 0;
        keys.forEach(function(groupLabel) {
          var items = groups[groupLabel];
          html += '<div class="audit-batch-group">';
          html += '<div class="audit-batch-group-label">' + escHtml(groupLabel) + '</div>';
          items.forEach(function(item) {
            html += '<a href="<?php echo URLROOT; ?>/connections/auditBatchDetail/' + encodeURIComponent(item.batch_id) + '" class="audit-batch-row" style="animation-delay:' + (rowIdx * 0.04) + 's">';
            html += '<span class="audit-batch-dot" style="background:' + item.dot_color + '"></span>';
            html += '<div class="audit-batch-row-body">';
            html += '<div class="audit-batch-row-title">' + escHtml(item.type_label) + ' — ' + escHtml(item.store_name) + '</div>';
            html += '<div class="audit-batch-row-sub">';
            html += item.total_skus + ' SKUs';
            if (item.mismatches > 0) {
              var cls = item.mismatches >= item.total_skus ? 'audit-batch-issue' : 'audit-batch-warn';
              html += ' <span class="' + cls + '"> · ' + item.mismatches + ' diferencias</span>';
            }
            if (item.not_found > 0) {
              var cls = item.not_found >= item.total_skus ? 'audit-batch-issue' : 'audit-batch-warn';
              html += ' <span class="' + cls + '"> · ' + item.not_found + ' no encontrados</span>';
            }
            if (item.mismatches === 0 && item.not_found === 0) {
              html += ' <span class="audit-batch-ok"> · Perfecto</span>';
            }
            if (item.performed_by) {
              html += ' · ' + escHtml(item.performed_by);
            }
            html += '</div></div>';
            html += '<span class="audit-batch-row-date">' + escHtml(item.time_ago) + '</span>';
            html += '</a>';
            html += '<div class="audit-batch-separator"></div>';
            rowIdx++;
          });
          html += '</div>';
        });
        html += '</div>';

        document.getElementById('ahList').innerHTML = html;
        document.getElementById('ahList').style.display = 'block';
      })
      .catch(function() {
        document.getElementById('ahLoading').style.display = 'none';
        document.getElementById('ahEmpty').style.display = 'flex';
        document.querySelector('#ahEmpty p').textContent = 'Error al cargar auditorías.';
      });
  }

  function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  // Store select
  document.getElementById('ahStoreSelect').addEventListener('change', function() {
    currentStore = this.value;
    currentQuery = '';
    document.getElementById('ahSearchInput').value = '';
    fetchData();
  });

  // Search with debounce
  var searchTimer;
  document.getElementById('ahSearchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() {
      currentQuery = document.getElementById('ahSearchInput').value.trim();
      fetchData();
    }, 300);
  });
  document.getElementById('ahSearchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      clearTimeout(searchTimer);
      currentQuery = this.value.trim();
      fetchData();
    }
  });

  <?php if ($searchQuery !== ''): ?>
  document.getElementById('ahSearchClear').addEventListener('click', function() {
    document.getElementById('ahSearchInput').value = '';
    currentQuery = '';
    fetchData();
  });
  <?php endif; ?>

  // Status pills
  document.querySelectorAll('.ah-status-pill').forEach(function(pill) {
    pill.addEventListener('click', function() {
      var status = this.getAttribute('data-status');
      if (status === currentStatus) return;
      currentStatus = status;
      // Update active UI
      document.querySelectorAll('.ah-status-pill').forEach(function(p) {
        p.classList.remove('bg-accent/10', 'text-accent', 'border-accent/30', 'bg-success/10', 'text-success', 'border-success/30', 'bg-warning/10', 'text-warning', 'border-warning/30', 'bg-danger/10', 'text-danger', 'border-danger/30', 'text-muted', 'border-border');
        p.classList.add('text-muted', 'border-border');
      });
      this.classList.remove('text-muted');
      if (status === 'all') this.classList.add('bg-accent/10', 'text-accent', 'border-accent/30');
      else if (status === 'perfect') this.classList.add('bg-success/10', 'text-success', 'border-success/30');
      else if (status === 'warn') this.classList.add('bg-warning/10', 'text-warning', 'border-warning/30');
      else if (status === 'danger') this.classList.add('bg-danger/10', 'text-danger', 'border-danger/30');
      fetchData();
    });
  });

  // Initial fetch replaces the server-rendered data
  fetchData();

  var purgeBtn = document.getElementById('ahPurgeBtn');
  if (purgeBtn) {
    purgeBtn.addEventListener('click', function() {
      openConfirm({
        title: 'Purgar todas las auditor\u00edas',
        message: '\u00bfEliminar permanentemente todos los registros de auditor\u00eda? Esta acci\u00f3n no se puede deshacer.<br><br>Escribe <strong>"Si, deseo borrar los registros"</strong> para confirmar.',
        confirmText: 'Purgar todo',
        confirmClass: 'btn-danger',
        requirePhrase: 'Si, deseo borrar los registros',
        onConfirm: function() {
          var form = new FormData();
          form.set('csrf_token', '<?php echo htmlspecialchars($data['csrf_token'], ENT_QUOTES, "UTF-8"); ?>');
          fetch('<?php echo URLROOT; ?>/connections/purgeAudits', {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
          })
          .then(function(r) { return r.json(); })
          .then(function(d) {
            if (d.ok) {
              showToast('Auditor\u00edas eliminadas: ' + d.deleted, 'success', 4000);
              fetchData();
            } else {
              showToast(d.error || 'Error al purgar.', 'error', 6000);
            }
          })
          .catch(function() {
            showToast('Error de conexi\u00f3n.', 'error', 6000);
          });
        }
      });
    });
  }
})();
</script>
