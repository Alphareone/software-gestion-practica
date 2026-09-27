<?php
$connections = $data['connections'] ?? [];
?>

<div class="settings-page" style="max-width:100%;">

  <div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
    <a href="<?php echo URLROOT; ?>/connections/connect" class="ut-btn ut-btn-primary">
      <?php echo icon('globe', ['size' => 16]); ?>
      Conectar nueva tienda
    </a>
  </div>

  <?php if (empty($connections)): ?>
    <div class="dash-empty" style="margin-top:8rem;padding:0;">
      <h3>No hay tiendas conectadas</h3>
      <p>Usa la opci&oacute;n <strong>"Conectar nueva tienda"</strong> desde el bot&oacute;n arriba a la derecha para agregar una nueva tienda.</p>
    </div>
  <?php else: ?>

    <div class="ut-table-wrap">
      <table class="ut-table">
        <thead>
          <tr>
            <th>Tienda</th>
            <th>ID ML</th>
            <th>País</th>
            <th>App ID</th>
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
                <div class="td-icon"><?php echo icon('globe', ['size' => 16]); ?></div>
                <div class="td-info">
                  <span style="font-weight:500;color:var(--text-primary);font-size:0.84rem;"><?php echo htmlspecialchars($c->store_name ?: 'Sin nombre'); ?></span>
                  <span style="font-size:0.72rem;color:var(--text-secondary);"><?php echo htmlspecialchars($c->ml_email ?: $c->ml_nickname ?: ''); ?></span>
                </div>
              </div>
            </td>
            <td style="font-size:0.8rem;"><?php echo (int)$c->ml_user_id; ?></td>
            <td><span class="d-badge country"><?php echo htmlspecialchars($c->country ?? ''); ?></span></td>
            <td style="font-size:0.78rem;">
              <?php if (!empty($c->config_mismatch)): ?>
                <span class="d-badge exp" title="APP ID cambiado. Reconectar tienda.">⚠ Cambiado</span>
              <?php elseif ($c->config_app_id): ?>
                <code style="font-size:0.72rem;"><?php echo htmlspecialchars($c->config_app_id); ?></code>
              <?php else: ?>
                <span style="color:var(--text-muted);font-size:0.72rem;">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($c->token_ok)): ?>
                <span class="d-badge ok">OK</span>
              <?php else: ?>
                <span class="d-badge exp">Expirado</span>
              <?php endif; ?>
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
                <button type="button" class="ut-edit-btn" onclick="openRename(<?php echo (int)$c->id; ?>, <?php echo htmlspecialchars(json_encode($c->store_name ?: 'Sin nombre'), ENT_QUOTES); ?>)" title="Renombrar">
                  <?php echo icon('pencil', ['size' => 14]); ?>
                </button>

                <?php if ($c->is_active): ?>
                  <button type="button" class="ut-edit-btn is-warning" onclick="confirmAction({
                    title: 'Desactivar tienda',
                    message: '¿Desactivar ' + <?php echo htmlspecialchars(json_encode($c->store_name ?: 'Sin nombre'), ENT_QUOTES); ?> + '? Los usuarios no podrán ver esta tienda.',
                    confirmText: 'Desactivar',
                    confirmClass: 'btn-warn',
                    action: '<?php echo URLROOT; ?>/connections/disconnect/<?php echo (int)$c->id; ?>',
                    csrf: '<?php echo htmlspecialchars($data['csrf_token']); ?>'
                  })" title="Desactivar">
                    <?php echo icon('power', ['size' => 14]); ?>
                  </button>
                <?php else: ?>
                  <button type="button" class="ut-edit-btn is-success" onclick="confirmAction({
                    title: 'Reactivar tienda',
                    message: '¿Reactivar ' + <?php echo htmlspecialchars(json_encode($c->store_name ?: 'Sin nombre'), ENT_QUOTES); ?> + '? Los usuarios podrán ver y seleccionar esta tienda.',
                    confirmText: 'Reactivar',
                    confirmClass: 'btn-success',
                    action: '<?php echo URLROOT; ?>/connections/reactivate/<?php echo (int)$c->id; ?>',
                    csrf: '<?php echo htmlspecialchars($data['csrf_token']); ?>'
                  })" title="Reactivar">
                    <?php echo icon('refresh-cw', ['size' => 14]); ?>
                  </button>
                <?php endif; ?>

                <button type="button" class="ut-edit-btn is-danger" onclick="confirmAction({
                  title: 'Eliminar tienda',
                  message: '¿Eliminar permanentemente ' + <?php echo htmlspecialchars(json_encode($c->store_name ?: 'Sin nombre'), ENT_QUOTES); ?> + '? Esta acción no se puede deshacer.',
                  confirmText: 'Eliminar',
                  confirmClass: 'btn-danger',
                  action: '<?php echo URLROOT; ?>/connections/deleteStore/<?php echo (int)$c->id; ?>',
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


<!-- Rename Modal -->
<div id="renameModal" class="modal-overlay" style="display:none" onclick="closeRename(event)">
  <div class="modal-card" onclick="event.stopPropagation()">
    <h3>Renombrar tienda</h3>
    <form method="post" action="" id="renameForm" onsubmit="closeRename({target:document, currentTarget:document})">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
      <input type="text" name="store_name" id="renameInput" maxlength="100" autocomplete="off" class="rename-input">
      <div class="modal-actions">
        <button type="button" class="btn btn-cancel" onclick="closeRename()">Cancelar</button>
        <button type="submit" class="btn btn-save">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRename(id, name) {
  document.getElementById('renameForm').action = '<?php echo URLROOT; ?>/connections/rename/' + id;
  document.getElementById('renameInput').value = name;
  document.getElementById('renameModal').style.display = 'flex';
  document.getElementById('renameInput').focus();
  document.getElementById('renameInput').select();
}
function closeRename(e) {
  if (e && e.target !== e.currentTarget) return;
  document.getElementById('renameModal').style.display = 'none';
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    var rm = document.getElementById('renameModal');
    if (rm && rm.style.display !== 'none' && rm.style.display !== '') closeRename({target:document, currentTarget:document});
  }
});

function confirmAction(opts) {
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
</script>