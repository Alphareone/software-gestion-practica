<div class="ahub">

  <!-- Filtros -->
  <div class="flex items-center gap-3 mb-5 flex-wrap">
    <div class="flex items-center gap-2 flex-1 min-w-0">
      <input type="text" id="alSearch" placeholder="Buscar por usuario, descripción o N° de registro…" value="<?php echo htmlspecialchars($data['search'] ?? ''); ?>" class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2.5 py-1.5 focus:outline-none focus:border-accent w-full max-w-sm">
      <?php if (!empty($data['search']) || !empty($data['action_filter']) || !empty($data['user_filter'])): ?>
        <button id="alClearBtn" class="text-xs text-muted hover:text-primary transition-colors bg-transparent border-none cursor-pointer p-0"><?php echo icon('x', ['size' => 14]); ?></button>
      <?php else: ?>
        <button id="alClearBtn" style="display:none;" class="text-xs text-muted hover:text-primary transition-colors bg-transparent border-none cursor-pointer p-0"><?php echo icon('x', ['size' => 14]); ?></button>
      <?php endif; ?>
    </div>
    <select id="alAction" class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2.5 py-1.5 focus:outline-none focus:border-accent cursor-pointer">
      <option value="">Todas las acciones</option>
      <?php foreach ($data['actions'] ?? [] as $a): ?>
        <option value="<?php echo htmlspecialchars($a->action); ?>" <?php echo ($data['action_filter'] ?? '') === $a->action ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars($a->action); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <select id="alUser" class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2.5 py-1.5 focus:outline-none focus:border-accent cursor-pointer">
      <option value="">Todos los usuarios</option>
      <?php foreach ($data['users'] ?? [] as $u): ?>
        <option value="<?php echo (int)$u->user_id; ?>" <?php echo ($data['user_filter'] ?? '') === (string)(int)$u->user_id ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars($u->username); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button id="alPurgeBtn" class="text-xs px-2.5 py-1.5 rounded-lg border border-danger/50 text-danger hover:text-danger hover:border-danger hover:bg-danger/5 transition-colors flex items-center gap-1.5 ml-auto bg-transparent cursor-pointer" title="Purgar registros">
      <?php echo icon('trash-2', ['size' => 12]); ?>
      Purga
    </button>
  </div>

  <!-- Content -->
  <div id="alContent">
    <div class="audit-batch-empty" id="alEmpty" style="display:none;">
      <?php echo icon('clock-3', ['size' => 32, 'stroke' => 1.2]); ?>
      <p id="alEmptyMsg">No se encontraron registros.</p>
    </div>
    <div id="alLoading" style="display:flex;flex-direction:column;align-items:center;gap:0.75rem;padding:3rem 1rem;color:var(--text-secondary);font-size:0.9rem;">
      <div class="loading-spinner" style="width:28px;height:28px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin 0.7s linear infinite;"></div>
      <span>Cargando actividad...</span>
    </div>
    <div id="alTableWrapper" style="display:none;">
      <div style="border:1px solid var(--border);border-radius:10px;overflow-x:auto;background:var(--card-bg);">
        <table class="al-table" style="width:100%;border-collapse:collapse;font-size:0.82rem;">
          <thead>
            <tr>
              <th style="padding:0.55rem 0.75rem;text-align:left;font-weight:600;color:var(--text-secondary);font-size:0.72rem;text-transform:uppercase;letter-spacing:0.03em;">Fecha</th>
              <th style="padding:0.55rem 0.75rem;text-align:left;font-weight:600;color:var(--text-secondary);font-size:0.72rem;text-transform:uppercase;letter-spacing:0.03em;">Usuario</th>
              <th style="padding:0.55rem 0.75rem;text-align:left;font-weight:600;color:var(--text-secondary);font-size:0.72rem;text-transform:uppercase;letter-spacing:0.03em;">Acción</th>
              <th style="padding:0.55rem 0.75rem;text-align:left;font-weight:600;color:var(--text-secondary);font-size:0.72rem;text-transform:uppercase;letter-spacing:0.03em;">Descripción</th>
              <th style="padding:0.55rem 0.75rem;text-align:left;font-weight:600;color:var(--text-secondary);font-size:0.72rem;text-transform:uppercase;letter-spacing:0.03em;">IP</th>
              <th style="width:36px;"></th>
            </tr>
          </thead>
          <tbody id="alBody"></tbody>
        </table>
      </div>
      <div class="flex items-center justify-center gap-2 mt-4 flex-wrap" id="alPagination"></div>
    </div>
  </div>
</div>
<script>
(function() {
  var currentPage = <?php echo (int)($data['page'] ?? 1); ?>;
  var currentQuery = <?php echo json_encode($data['search'] ?? ''); ?>;
  var currentAction = <?php echo json_encode($data['action_filter'] ?? ''); ?>;
  var currentUser = <?php echo json_encode($data['user_filter'] ?? ''); ?>;
  var csrfToken = <?php echo json_encode($data['csrf_token'] ?? ''); ?>;
  var totalPages = <?php echo (int)($data['total_pages'] ?? 1); ?>;

  function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function fetchData(page, silent) {
    page = page || currentPage;
    var params = new URLSearchParams();
    params.set('page', page);
    if (currentQuery) params.set('q', currentQuery);
    if (currentAction) params.set('action', currentAction);
    if (currentUser) params.set('user', currentUser);

    var url = '<?php echo URLROOT; ?>/admin/activityLogData?' + params.toString();

    if (!silent) {
      document.getElementById('alTableWrapper').style.display = 'none';
      document.getElementById('alEmpty').style.display = 'none';
      document.getElementById('alLoading').style.display = 'flex';
    }

    fetch(url, { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        document.getElementById('alLoading').style.display = 'none';

        // Update action filter select with translated labels
        var actionSelect = document.getElementById('alAction');
        var currentVal = actionSelect.value;
        actionSelect.innerHTML = '<option value="">Todas las acciones</option>';
        (data.actions || []).forEach(function(a) {
          var opt = document.createElement('option');
          opt.value = a.action;
          opt.textContent = a.label;
          if (a.action === currentVal) opt.selected = true;
          actionSelect.appendChild(opt);
        });

        // Update user filter select
        var userSelect = document.getElementById('alUser');
        var currentUserVal = userSelect.value;
        userSelect.innerHTML = '<option value="">Todos los usuarios</option>';
        (data.users || []).forEach(function(u) {
          var opt = document.createElement('option');
          opt.value = u.id;
          opt.textContent = u.username;
          if (String(u.id) === String(currentUserVal)) opt.selected = true;
          userSelect.appendChild(opt);
        });

        currentPage = data.page;
        totalPages = data.total_pages;

        if (data.logs.length === 0) {
          document.getElementById('alEmpty').style.display = 'flex';
          var msg = currentQuery ? 'No se encontraron registros para "' + currentQuery + '".' : currentAction ? 'No hay registros con esa acción.' : 'No se encontraron registros.';
          document.getElementById('alEmptyMsg').textContent = msg;
          return;
        }

        var tbody = document.getElementById('alBody');
        tbody.innerHTML = '';
        data.logs.forEach(function(log, idx) {
          var tr = document.createElement('tr');
          tr.className = log.row_class || '';
          if (silent) tr.style.animation = 'none';
          else tr.style.animationDelay = (idx * 0.03) + 's';
          tr.style.cursor = 'pointer';
          tr.addEventListener('click', function(e) {
            if (e.target.closest('.al-delete-btn')) return;
            window.location.href = '<?php echo URLROOT; ?>/admin/activityLogDetail/' + log.id;
          });
          tr.innerHTML =
            '<td data-label="Fecha"><span class="alc-time">' + escHtml(log.time) + '</span></td>' +
            '<td data-label="Usuario"><span class="alc-user">' + escHtml(log.username) + '</span></td>' +
            '<td data-label="Acción"><span class="alc-action"><span class="alc-action-dot"></span><span class="alc-action-label">' + escHtml(log.action_label) + '</span></span></td>' +
            '<td data-label="Descripción"><span class="alc-desc" title="' + escHtml(log.description) + '">' + escHtml(log.description) + '</span></td>' +
            '<td data-label="IP"><span class="alc-ip">' + escHtml(log.ip) + '</span></td>' +
            '<td style="text-align:center"><button class="al-delete-btn" data-id="' + log.id + '" title="Eliminar registro"><?php echo icon('trash-2', ['size' => 12]); ?></button></td>';
          tbody.appendChild(tr);
        });

        document.getElementById('alTableWrapper').style.display = 'block';
        renderPagination();
        updateClearBtn();
      })
      .catch(function(err) {
        console.error('Fetch error:', err);
        document.getElementById('alLoading').style.display = 'none';
        document.getElementById('alEmpty').style.display = 'flex';
        document.getElementById('alEmptyMsg').textContent = 'Error al cargar actividad.';
      });
  }

  function renderPagination() {
    var container = document.getElementById('alPagination');
    if (totalPages <= 1) { container.innerHTML = ''; container.style.display = 'none'; return; }
    container.style.display = 'flex';
    var html = '';
    if (currentPage > 1) html += '<a href="#" data-page="' + (currentPage - 1) + '" class="al-page al-page-prev">← Anterior</a>';
    for (var p = 1; p <= totalPages; p++) {
      if (p === 1 || p === totalPages || Math.abs(p - currentPage) <= 2) {
        html += '<a href="#" data-page="' + p + '" class="al-page' + (p === currentPage ? ' current' : '') + '">' + p + '</a>';
      } else if (p === 2 || p === totalPages - 1) {
        html += '<span class="al-page" style="border:none;pointer-events:none;">…</span>';
      }
    }
    if (currentPage < totalPages) html += '<a href="#" data-page="' + (currentPage + 1) + '" class="al-page al-page-next">Siguiente →</a>';
    container.innerHTML = html;
    container.querySelectorAll('a[data-page]').forEach(function(a) {
      a.addEventListener('click', function(e) {
        e.preventDefault();
        var p = parseInt(this.getAttribute('data-page'));
        if (p && p !== currentPage) fetchData(p);
      });
    });
  }

  function updateClearBtn() {
    var btn = document.getElementById('alClearBtn');
    if (!btn) return;
    btn.style.display = (currentQuery || currentAction || currentUser) ? 'inline-flex' : 'none';
  }

  // Purge all logs
  document.getElementById('alPurgeBtn').addEventListener('click', function() {
    openConfirm({
      title: 'Purgar todos los registros',
      message: '¿Eliminar permanentemente todos los registros de actividad? Esta acci\u00f3n no se puede deshacer.<br><br>Escribe <strong>"Si, deseo borrar los registros"</strong> para confirmar.',
      confirmText: 'Purgar todo',
      confirmClass: 'btn-danger',
      requirePhrase: 'Si, deseo borrar los registros',
      onConfirm: function() {
        var form = new FormData();
        form.set('csrf_token', csrfToken);

        fetch('<?php echo URLROOT; ?>/admin/purgeAllLogs', {
          method: 'POST',
          body: form,
          credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.ok) {
            // Refresh to show empty state
            fetchData(1);
          }
        });
      }
    });
  });

  document.getElementById('alSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); clearTimeout(searchTimer); currentQuery = this.value.trim(); fetchData(1); }
  });

  document.getElementById('alAction').addEventListener('change', function() {
    currentAction = this.value;
    fetchData(1);
  });

  document.getElementById('alUser').addEventListener('change', function() {
    currentUser = this.value;
    fetchData(1);
  });

  var clearBtn = document.getElementById('alClearBtn');
  if (clearBtn) {
    clearBtn.addEventListener('click', function() {
      document.getElementById('alSearch').value = '';
      document.getElementById('alAction').value = '';
      document.getElementById('alUser').value = '';
      currentQuery = '';
      currentAction = '';
      currentUser = '';
      clearBtn.style.display = 'none';
      fetchData(1);
    });
  }

  // Delete handler (event delegation)
  document.getElementById('alBody').addEventListener('click', function(e) {
    var btn = e.target.closest('.al-delete-btn');
    if (!btn) return;
    var id = btn.getAttribute('data-id');

    openConfirm({
      title: 'Eliminar registro',
      message: '\u00bfEliminar permanentemente el registro de actividad N\u00b0' + id + '? Esta acci\u00f3n no se puede deshacer.<br><br>Escribe <strong>"Si, deseo borrar los registros"</strong> para confirmar.',
      confirmText: 'Eliminar',
      confirmClass: 'btn-danger',
      requirePhrase: 'Si, deseo borrar los registros',
      onConfirm: function() {
        var form = new FormData();
        form.set('id', id);
        form.set('csrf_token', csrfToken);

        fetch('<?php echo URLROOT; ?>/admin/deleteLog', {
          method: 'POST',
          body: form,
          credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.ok) {
            // Remove row with fade effect
            var tr = btn.closest('tr');
            tr.style.transition = 'opacity 0.2s';
            tr.style.opacity = '0';
            setTimeout(function() { tr.remove(); }, 200);
          }
        });
      }
    });
  });

  fetchData(1);

  // Auto-refresh cada 5 segundos
  setInterval(function() { fetchData(currentPage, true); }, 5000);
})();
</script>
