<?php
$connections = $data['connections'] ?? [];
$isAdmin = !empty($data['is_admin']);
?>

<div class="settings-page" style="max-width:100%;">

  <?php if (!empty($data['dry_run'])): ?>
    <div class="d-badge exp" style="display:inline-block;margin-bottom:1rem;">Modo DRY-RUN activo: no se contacta la API real de Cencosud</div>
  <?php endif; ?>

  <?php if ($isAdmin): ?>
    <div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
      <a href="<?php echo URLROOT; ?>/cencosud/adminstores" class="ut-btn" style="background:#4b5563; color:white; border-color:#4b5563; display:inline-flex; align-items:center; gap:0.5rem; font-size:0.85rem; padding: 6px 12px; border-radius: 4px;">
        <?php echo icon('settings', ['size' => 14]); ?>
        Administrar tiendas
      </a>
    </div>
  <?php endif; ?>

  <?php if (empty($connections)): ?>
    <div class="dash-empty" style="margin-top:8rem;padding:0;">
      <h3>No hay tiendas Cencosud conectadas</h3>
      <?php if ($isAdmin): ?>
        <p>Conecta una cuenta de vendedor desde <a href="<?php echo URLROOT; ?>/cencosud/connect">Conectar tienda</a>.</p>
      <?php else: ?>
        <p>Pide a un administrador que conecte una cuenta de vendedor de Cencosud Chile.</p>
      <?php endif; ?>
    </div>
  <?php else: ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:1rem;">
      <?php foreach ($connections as $c): ?>
        <div class="ut-card" style="padding:1.25rem;" data-store-card="<?php echo (int)$c->id; ?>">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
            <div style="display:flex;align-items:center;gap:0.5rem;">
              <?php echo icon('store', ['size' => 18]); ?>
              <strong style="font-size:0.9rem;"><?php echo htmlspecialchars($c->store_name ?: 'Sin nombre'); ?></strong>
            </div>
            <span class="d-badge" id="csSyncBadge-<?php echo (int)$c->id; ?>">—</span>
          </div>

          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem;margin-bottom:0.9rem;">
            <div>
              <div style="font-size:1.3rem;font-weight:600;" id="csTotal-<?php echo (int)$c->id; ?>">…</div>
              <div style="font-size:0.7rem;color:var(--text-secondary);">Productos</div>
            </div>
            <div>
              <div style="font-size:1.3rem;font-weight:600;" id="csPublished-<?php echo (int)$c->id; ?>">…</div>
              <div style="font-size:0.7rem;color:var(--text-secondary);">Publicados</div>
            </div>
            <div>
              <div style="font-size:1.3rem;font-weight:600;color:var(--warning,#d97706);" id="csLowStock-<?php echo (int)$c->id; ?>">…</div>
              <div style="font-size:0.7rem;color:var(--text-secondary);">Stock bajo</div>
            </div>
          </div>

          <div id="csProgressWrap-<?php echo (int)$c->id; ?>" style="display:none;margin-bottom:0.75rem;">
            <div style="background:var(--bg-secondary,#eee);border-radius:4px;height:6px;overflow:hidden;">
              <div id="csProgressBar-<?php echo (int)$c->id; ?>" style="background:var(--primary,#2563eb);height:100%;width:0%;transition:width .3s;"></div>
            </div>
            <div style="font-size:0.7rem;color:var(--text-secondary);margin-top:0.25rem;" id="csProgressText-<?php echo (int)$c->id; ?>"></div>
          </div>

          <div style="display:flex;gap:0.5rem;justify-content:space-between;align-items:center;">
            <span style="font-size:0.7rem;color:var(--text-muted);" id="csLastSync-<?php echo (int)$c->id; ?>"></span>
            <button type="button" class="ut-btn ut-btn-primary" id="csSyncBtn-<?php echo (int)$c->id; ?>"
                    onclick="csStartSync(<?php echo (int)$c->id; ?>)">
              <?php echo icon('refresh-cw', ['size' => 14]); ?>
              Sincronizar
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>
</div>

<script>
(function() {
  var CSRF = '<?php echo htmlspecialchars($data['csrf_token']); ?>';
  var BASE = '<?php echo URLROOT; ?>/cencosud';
  var storeIds = <?php echo json_encode(array_map(function ($c) { return (int) $c->id; }, $connections)); ?>;
  var syncing = {};

  function loadMetrics(id) {
    fetch(BASE + '/metrics/' + id)
      .then(function(r) { return r.json(); })
      .then(function(m) {
        if (m.error) return;
        setText('csTotal-' + id, m.total);
        setText('csPublished-' + id, m.published);
        setText('csLowStock-' + id, m.low_stock);
        var badge = document.getElementById('csSyncBadge-' + id);
        if (badge) {
          if (m.sync_status === 'syncing') { badge.className = 'd-badge country'; badge.textContent = 'Sincronizando'; }
          else if (m.sync_status === 'error') { badge.className = 'd-badge exp'; badge.textContent = 'Error'; }
          else { badge.className = 'd-badge ok'; badge.textContent = 'Al día'; }
        }
        if (m.last_sync_at) setText('csLastSync-' + id, 'Último sync: ' + m.last_sync_at);
        if (m.sync_status === 'syncing' && !syncing[id]) {
          // Sync iniciado en otra pestaña/proceso: continuar los chunks desde aquí
          resumeSync(id);
        }
      })
      .catch(function() {});
  }

  function setText(elId, value) {
    var el = document.getElementById(elId);
    if (el) el.textContent = value;
  }

  function post(url) {
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    return fetch(url, { method: 'POST', body: fd }).then(function(r) { return r.json(); });
  }

  function showProgress(id, processed, total) {
    var wrap = document.getElementById('csProgressWrap-' + id);
    var bar = document.getElementById('csProgressBar-' + id);
    var txt = document.getElementById('csProgressText-' + id);
    if (wrap) wrap.style.display = 'block';
    var pct = total > 0 ? Math.min(100, Math.round(processed * 100 / total)) : 0;
    if (bar) bar.style.width = pct + '%';
    if (txt) txt.textContent = processed + ' de ' + (total || '?') + ' productos';
  }

  function hideProgress(id) {
    var wrap = document.getElementById('csProgressWrap-' + id);
    if (wrap) wrap.style.display = 'none';
  }

  function chunkLoop(id) {
    post(BASE + '/syncChunk/' + id).then(function(res) {
      if (!res.ok) {
        syncing[id] = false;
        hideProgress(id);
        if (typeof showToast === 'function') showToast(res.error || 'Error en la sincronización.', 'error');
        loadMetrics(id);
        setBtn(id, false);
        return;
      }
      showProgress(id, res.processed || 0, res.total || 0);
      if (res.done) {
        syncing[id] = false;
        hideProgress(id);
        if (typeof showToast === 'function') showToast('Sincronización completada.', 'success');
        loadMetrics(id);
        setBtn(id, false);
      } else {
        setTimeout(function() { chunkLoop(id); }, 400);
      }
    }).catch(function() {
      syncing[id] = false;
      setBtn(id, false);
      hideProgress(id);
    });
  }

  function setBtn(id, disabled) {
    var btn = document.getElementById('csSyncBtn-' + id);
    if (btn) btn.disabled = disabled;
  }

  window.csStartSync = function(id) {
    if (syncing[id]) return;
    syncing[id] = true;
    setBtn(id, true);
    post(BASE + '/syncStart/' + id).then(function(res) {
      if (!res.ok) {
        syncing[id] = false;
        setBtn(id, false);
        if (typeof showToast === 'function') showToast(res.error || 'No se pudo iniciar la sincronización.', 'error');
        return;
      }
      if (res.done) {
        syncing[id] = false;
        setBtn(id, false);
        if (typeof showToast === 'function') showToast('Sincronización completada.', 'success');
        loadMetrics(id);
        return;
      }
      showProgress(id, 0, res.total || 0);
      chunkLoop(id);
    }).catch(function() {
      syncing[id] = false;
      setBtn(id, false);
    });
  };

  function resumeSync(id) {
    if (syncing[id]) return;
    syncing[id] = true;
    setBtn(id, true);
    chunkLoop(id);
  }

  storeIds.forEach(loadMetrics);
})();
</script>
