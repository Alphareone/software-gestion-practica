<?php
$connections = $data['connections'] ?? [];
?>

<div class="settings-page" style="max-width:100%;">

  <?php if (!empty($data['dry_run'])): ?>
    <div class="d-badge exp" style="display:inline-block;margin-bottom:1rem;">Modo DRY-RUN activo: no se contacta la API real de Walmart</div>
  <?php endif; ?>

  <div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
    <a href="<?php echo URLROOT; ?>/walmart/connect" class="ut-btn ut-btn-primary">
      <?php echo icon('store', ['size' => 16]); ?>
      Conectar nueva tienda
    </a>
  </div>

  <?php if (empty($connections)): ?>
    <div class="dash-empty" style="margin-top:8rem;padding:0;">
      <h3>No hay tiendas Walmart conectadas</h3>
      <p>Usa la opci&oacute;n <strong>"Conectar nueva tienda"</strong> para agregar una cuenta de vendedor de Walmart Chile con su Client ID y Client Secret.</p>
    </div>
  <?php else: ?>

    <div class="ut-table-wrap">
      <table class="ut-table">
        <thead>
          <tr>
            <th>Tienda</th>
            <th>Client ID</th>
            <th>Mercado</th>
            <th>Token</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($connections as $c): ?>
          <tr class="ut-tr ut-tr-<?php echo $c->is_active ? 'active' : 'inactive'; ?>" data-store-id="<?php echo (int)$c->id; ?>">
            <td>
              <div class="td-store">
                <div class="td-icon"><?php echo icon('store', ['size' => 16]); ?></div>
                <div class="td-info">
                  <span style="font-weight:500;color:var(--text-primary);font-size:0.84rem;"><?php echo htmlspecialchars($c->store_name ?: 'Sin nombre'); ?></span>
                  <span style="font-size:0.72rem;color:var(--text-secondary);"><?php echo htmlspecialchars($c->api_base_url); ?></span>
                </div>
              </div>
            </td>
            <td><code style="font-size:0.72rem;"><?php echo htmlspecialchars(substr($c->client_id, 0, 12)) . (strlen($c->client_id) > 12 ? '…' : ''); ?></code></td>
            <td><span class="d-badge country"><?php echo htmlspecialchars(strtoupper($c->market)); ?></span></td>
            <td>
              <span class="d-badge <?php echo !empty($c->token_ok) ? 'ok' : 'exp'; ?>" id="tokenBadge-<?php echo (int)$c->id; ?>">
                <?php echo !empty($c->token_ok) ? 'OK' : 'Sin token'; ?>
              </span>
            </td>
            <td>
              <?php if ($c->is_active): ?>
                <span class="d-badge active">Activa</span>
              <?php else: ?>
                <span class="d-badge inactive">Inactiva</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="td-actions">
                <button type="button" class="ut-edit-btn" onclick="testWalmartConn(<?php echo (int)$c->id; ?>, this)" title="Probar conexión">
                  <?php echo icon('refresh-cw', ['size' => 14]); ?>
                </button>

                <a class="ut-edit-btn" href="<?php echo URLROOT; ?>/walmart/connect?edit=<?php echo (int)$c->id; ?>" title="Editar credenciales">
                  <?php echo icon('pencil', ['size' => 14]); ?>
                </a>

                <?php if ($c->is_active): ?>
                  <button type="button" class="ut-edit-btn is-warning" onclick="confirmWalmartAction({
                    title: 'Desactivar tienda',
                    message: '¿Desactivar ' + <?php echo htmlspecialchars(json_encode($c->store_name ?: 'Sin nombre'), ENT_QUOTES); ?> + '? Se detendrán las sincronizaciones.',
                    confirmText: 'Desactivar',
                    confirmClass: 'btn-warn',
                    action: '<?php echo URLROOT; ?>/walmart/disconnect/<?php echo (int)$c->id; ?>',
                    csrf: '<?php echo htmlspecialchars($data['csrf_token']); ?>'
                  })" title="Desactivar">
                    <?php echo icon('power', ['size' => 14]); ?>
                  </button>
                <?php else: ?>
                  <button type="button" class="ut-edit-btn is-success" onclick="confirmWalmartAction({
                    title: 'Reactivar tienda',
                    message: '¿Reactivar ' + <?php echo htmlspecialchars(json_encode($c->store_name ?: 'Sin nombre'), ENT_QUOTES); ?> + '?',
                    confirmText: 'Reactivar',
                    confirmClass: 'btn-success',
                    action: '<?php echo URLROOT; ?>/walmart/reactivate/<?php echo (int)$c->id; ?>',
                    csrf: '<?php echo htmlspecialchars($data['csrf_token']); ?>'
                  })" title="Reactivar">
                    <?php echo icon('refresh-cw', ['size' => 14]); ?>
                  </button>
                <?php endif; ?>

                <button type="button" class="ut-edit-btn is-danger" onclick="confirmWalmartAction({
                  title: 'Eliminar tienda',
                  message: '¿Eliminar permanentemente ' + <?php echo htmlspecialchars(json_encode($c->store_name ?: 'Sin nombre'), ENT_QUOTES); ?> + '? Se borrará también su caché de productos e historial. Esta acción no se puede deshacer.',
                  confirmText: 'Eliminar',
                  confirmClass: 'btn-danger',
                  action: '<?php echo URLROOT; ?>/walmart/deleteStore/<?php echo (int)$c->id; ?>',
                  csrf: '<?php echo htmlspecialchars($data['csrf_token']); ?>'
                })" title="Eliminar permanentemente">
                  <?php echo icon('trash-2', ['size' => 14]); ?>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  <?php endif; ?>
</div>

<script>
function confirmWalmartAction(opts) {
  openConfirm({
    title: opts.title,
    message: opts.message,
    confirmText: opts.confirmText,
    confirmClass: opts.confirmClass === 'btn-warning' ? 'btn-warn' : opts.confirmClass,
    onConfirm: function() {
      var form = document.createElement('form');
      form.method = 'post';
      form.action = opts.action;
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'csrf_token';
      input.value = opts.csrf;
      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
    }
  });
}

function testWalmartConn(id, btn) {
  btn.disabled = true;
  var fd = new FormData();
  fd.append('csrf_token', '<?php echo htmlspecialchars($data['csrf_token']); ?>');
  fetch('<?php echo URLROOT; ?>/walmart/testConnection/' + id, { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      var badge = document.getElementById('tokenBadge-' + id);
      if (res.ok) {
        if (badge) { badge.className = 'd-badge ok'; badge.textContent = 'OK'; }
        if (typeof showToast === 'function') showToast(res.message || 'Conexión verificada.', 'success');
      } else {
        if (badge) { badge.className = 'd-badge exp'; badge.textContent = 'Error'; }
        if (typeof showToast === 'function') showToast(res.error || 'Error al verificar.', 'error');
      }
    })
    .catch(function() {
      if (typeof showToast === 'function') showToast('Error de red al probar la conexión.', 'error');
    })
    .finally(function() { btn.disabled = false; });
}
</script>
