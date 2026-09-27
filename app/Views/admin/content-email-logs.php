<div class="ahub">

  <!-- Filtros -->
  <div class="flex items-center gap-3 mb-5 flex-wrap">
    <div class="flex items-center gap-2 flex-1 min-w-0">
      <input type="text" id="elSearch" placeholder="Buscar por correo o usuario…" value="<?php echo htmlspecialchars($data['search'] ?? ''); ?>" class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2.5 py-1.5 focus:outline-none focus:border-accent w-full max-w-sm">
      <?php if (!empty($data['search']) || !empty($data['template_filter'])): ?>
        <button id="elClearBtn" class="text-xs text-muted hover:text-primary transition-colors bg-transparent border-none cursor-pointer p-0"><?php echo icon('x', ['size' => 14]); ?></button>
      <?php else: ?>
        <button id="elClearBtn" style="display:none;" class="text-xs text-muted hover:text-primary transition-colors bg-transparent border-none cursor-pointer p-0"><?php echo icon('x', ['size' => 14]); ?></button>
      <?php endif; ?>
    </div>
    <select id="elTemplate" class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2.5 py-1.5 focus:outline-none focus:border-accent cursor-pointer">
      <option value="">Todos los templates</option>
      <?php foreach ($data['templates'] ?? [] as $t): ?>
        <option value="<?php echo htmlspecialchars($t->template); ?>" <?php echo ($data['template_filter'] ?? '') === $t->template ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars($t->template); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button id="elPurgeBtn" class="text-xs px-2.5 py-1.5 rounded-lg border border-danger/50 text-danger hover:text-danger hover:border-danger hover:bg-danger/5 transition-colors flex items-center gap-1.5 bg-transparent cursor-pointer" title="Purgar registros">
      <?php echo icon('trash-2', ['size' => 12]); ?>
      Purga
    </button>
  </div>

  <!-- Content -->
  <div id="elContent">
    <div class="audit-batch-empty" id="elEmpty" style="display:none;">
      <?php echo icon('mail', ['size' => 32, 'stroke' => 1.2]); ?>
      <p id="elEmptyMsg">No se encontraron registros de correos.</p>
    </div>
    <div id="elLoading" style="display:flex;flex-direction:column;align-items:center;gap:0.75rem;padding:3rem 1rem;color:var(--text-secondary);font-size:0.9rem;">
      <div class="loading-spinner" style="width:28px;height:28px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin 0.7s linear infinite;"></div>
      <span>Cargando registros...</span>
    </div>
    <div id="elTableWrapper" style="display:none;">
      <div style="border:1px solid var(--border);border-radius:10px;overflow-x:auto;background:var(--card-bg);">
        <table class="al-table" style="width:100%;border-collapse:collapse;font-size:0.82rem;">
          <thead>
            <tr>
              <th style="padding:0.55rem 0.75rem;text-align:left;font-weight:600;color:var(--text-secondary);font-size:0.72rem;text-transform:uppercase;letter-spacing:0.03em;">Fecha</th>
              <th style="padding:0.55rem 0.75rem;text-align:left;font-weight:600;color:var(--text-secondary);font-size:0.72rem;text-transform:uppercase;letter-spacing:0.03em;">Destinatario</th>
              <th style="padding:0.55rem 0.75rem;text-align:left;font-weight:600;color:var(--text-secondary);font-size:0.72rem;text-transform:uppercase;letter-spacing:0.03em;">Template</th>
              <th style="padding:0.55rem 0.75rem;text-align:left;font-weight:600;color:var(--text-secondary);font-size:0.72rem;text-transform:uppercase;letter-spacing:0.03em;">Asunto</th>
              <th style="padding:0.55rem 0.75rem;text-align:center;font-weight:600;color:var(--text-secondary);font-size:0.72rem;text-transform:uppercase;letter-spacing:0.03em;">Estado</th>
            </tr>
          </thead>
          <tbody id="elBody"></tbody>
        </table>
      </div>
      <div class="flex items-center justify-center gap-2 mt-4 flex-wrap" id="elPagination"></div>
    </div>
  </div>
</div>

<style>
#elBody tr { animation: product-row-in 0.3s ease both; }
.el-table tbody td { padding: 0.5rem 0.75rem; border-top: 1px solid var(--border); vertical-align: middle; color: var(--text-primary); }
.el-table tbody tr:last-child td { border-bottom: none; }
.el-table tbody tr:hover { background: var(--bg-sidebar); transition: background 0.12s; }
.el-status-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: 5px; vertical-align: middle; }
.el-time { color: var(--text-secondary); font-size: 0.78rem; }
.el-recipient { font-weight: 500; }
.el-template { font-family: monospace; font-size: 0.78rem; color: var(--text-secondary); }
.el-subject { color: var(--text-secondary); font-size: 0.78rem; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle; }
</style>

<script>
(function() {
  var currentPage = <?php echo (int)($data['page'] ?? 1); ?>;
  var currentQuery = <?php echo json_encode($data['search'] ?? ''); ?>;
  var currentTemplate = <?php echo json_encode($data['template_filter'] ?? ''); ?>;
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
    if (currentTemplate) params.set('template', currentTemplate);

    var url = '<?php echo URLROOT; ?>/admin/emailLogData?' + params.toString();

    if (!silent) {
      document.getElementById('elTableWrapper').style.display = 'none';
      document.getElementById('elEmpty').style.display = 'none';
      document.getElementById('elLoading').style.display = 'flex';
    }

    fetch(url, { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        document.getElementById('elLoading').style.display = 'none';
        currentPage = data.page;
        totalPages = data.total_pages;

        if (data.logs.length === 0) {
          document.getElementById('elEmpty').style.display = 'flex';
          document.getElementById('elEmptyMsg').textContent = currentQuery
            ? 'No se encontraron registros para "' + currentQuery + '".'
            : 'No se encontraron registros de correos.';
          return;
        }

        var tbody = document.getElementById('elBody');
        tbody.innerHTML = '';
        data.logs.forEach(function(log, idx) {
          var tr = document.createElement('tr');
          if (!silent) tr.style.animationDelay = (idx * 0.03) + 's';
          var statusClass = log.status === 'sent' ? '#10b981' : '#ef4444';
          tr.innerHTML =
            '<td><span class="el-time">' + escHtml(log.time) + '</span></td>' +
            '<td><span class="el-recipient">' + escHtml(log.recipient) + '</span></td>' +
            '<td><span class="el-template">' + escHtml(log.template) + '</span></td>' +
            '<td><span class="el-subject" title="' + escHtml(log.subject) + '">' + escHtml(log.subject) + '</span></td>' +
            '<td style="text-align:center"><span style="display:inline-flex;align-items:center;gap:4px;font-size:0.78rem;color:' + statusClass + '"><span class="el-status-dot" style="background:' + statusClass + '"></span>' + (log.status === 'sent' ? 'Enviado' : 'Fallido') + '</span></td>';
          tbody.appendChild(tr);
        });

        document.getElementById('elTableWrapper').style.display = 'block';
        renderPagination();
        updateClearBtn();
      })
      .catch(function(err) {
        console.error('Fetch error:', err);
        document.getElementById('elLoading').style.display = 'none';
        document.getElementById('elEmpty').style.display = 'flex';
        document.getElementById('elEmptyMsg').textContent = 'Error al cargar registros.';
      });
  }

  function renderPagination() {
    var container = document.getElementById('elPagination');
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
    var btn = document.getElementById('elClearBtn');
    if (!btn) return;
    btn.style.display = (currentQuery || currentTemplate) ? 'inline-flex' : 'none';
  }

  document.getElementById('elSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); currentQuery = this.value.trim(); fetchData(1); }
  });

  document.getElementById('elTemplate').addEventListener('change', function() {
    currentTemplate = this.value;
    fetchData(1);
  });

  var clearBtn = document.getElementById('elClearBtn');
  if (clearBtn) {
    clearBtn.addEventListener('click', function() {
      document.getElementById('elSearch').value = '';
      document.getElementById('elTemplate').value = '';
      currentQuery = '';
      currentTemplate = '';
      clearBtn.style.display = 'none';
      fetchData(1);
    });
  }

  var purgeBtn = document.getElementById('elPurgeBtn');
  if (purgeBtn) {
    purgeBtn.addEventListener('click', function() {
      openConfirm({
        title: 'Purgar registros de correos',
        message: '\u00bfEliminar permanentemente todos los registros de correos? Esta acci\u00f3n no se puede deshacer.<br><br>Escribe <strong>"Si, deseo borrar los registros"</strong> para confirmar.',
        confirmText: 'Purgar todo',
        confirmClass: 'btn-danger',
        requirePhrase: 'Si, deseo borrar los registros',
        onConfirm: function() {
          var form = new FormData();
          form.set('csrf_token', '<?php echo htmlspecialchars($data['csrf_token'], ENT_QUOTES, "UTF-8"); ?>');
          fetch('<?php echo URLROOT; ?>/admin/purgeEmailLogs', {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
          })
          .then(function(r) { return r.json(); })
          .then(function(d) {
            if (d.ok) {
              showToast('Registros de correos eliminados: ' + d.deleted, 'success', 4000);
              fetchData(1);
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

  fetchData(1);
})();
</script>
