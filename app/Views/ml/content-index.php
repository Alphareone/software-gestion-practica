<!-- Empty state when no connections -->
<?php $connections = $data['connections'] ?? []; ?>
<?php $activeId = $data['active_store_id'] ?? null; ?>

<div class="ml-header">
  <p class="ml-subtitle">Conecta tus tiendas de MercadoLibre para gestionar productos, precios y ventas desde un solo lugar.</p>
  <a href="<?php echo URLROOT; ?>/connections/connect" class="btn btn-connect">
    <?php echo icon('globe', ['size' => 16]); ?>
    Conectar nueva tienda
  </a>
</div>

<?php if (empty($data['ml_configured'])): ?>
  <div class="alert-config">
    <?php echo icon('info', ['size' => 18]); ?>
    <span>Antes de conectar, configura las credenciales de MercadoLibre en ML Credenciales, y agrega esta URL en el devcenter de MercadoLibre: <strong><?php echo htmlspecialchars(ML_REDIRECT_URI); ?></strong></span>
  </div>
<?php endif; ?>

<?php if (empty($connections)): ?>
  <div class="empty-state">
    <?php echo icon('layout-grid', ['size' => 48]); ?>
    <h3>Ninguna tienda conectada</h3>
    <p>Conecta tu primera tienda de MercadoLibre para comenzar a gestionar productos.</p>
  </div>
<?php else: ?>
  <div class="stores-grid">
    <?php foreach ($connections as $c): ?>
      <?php $isActive = (int)$activeId === (int)$c->id; ?>
      <?php $enabled = (bool)$c->is_active; ?>
      <div class="store-card <?php echo $isActive ? 'active' : ''; ?> <?php echo !$enabled ? 'disabled' : ''; ?>">
        <div class="store-top">
          <div class="store-icon">
            <?php echo icon('globe', ['size' => 22]); ?>
          </div>
          <div class="store-info">
            <div class="store-name-row">
              <strong><?php echo htmlspecialchars($c->store_name ?: 'Sin nombre'); ?></strong>
              <button type="button" class="btn-rename" onclick="openRename(<?php echo (int)$c->id; ?>, '<?php echo htmlspecialchars(addslashes($c->store_name ?: 'Sin nombre')); ?>')" title="Renombrar"><?php echo icon('pencil', ['size' => 14]); ?></button>
            </div>
            <span class="store-email"><?php echo htmlspecialchars($c->ml_email ?: $c->ml_nickname ?: ''); ?></span>
          </div>
          <span class="store-country"><?php echo htmlspecialchars($c->country ?? ''); ?></span>
        </div>

        <div class="store-meta">
          <span class="meta-item">
            <?php echo icon('clock-3', ['size' => 14]); ?>
            ID: <?php echo (int)$c->ml_user_id; ?>
          </span>
          <span class="meta-item">
            <?php echo icon('calendar', ['size' => 14]); ?>
            <?php echo date('d/m/Y', strtotime($c->created_at . ' UTC')); ?>
          </span>
        </div>

        <?php $tokenExpired = $enabled && empty($c->token_ok); ?>
        <div class="store-token-status">
          <?php if (!$enabled): ?>
            <span class="token-badge inactive">Inactiva</span>
          <?php elseif (!empty($c->token_ok)): ?>
            <span class="token-badge ok">Token vigente</span>
          <?php else: ?>
            <span class="token-badge exp">Token vencido</span>
          <?php endif; ?>
        </div>

        <div class="store-actions">
          <?php if ($enabled): ?>
            <?php if ($isActive): ?>
              <span class="badge-active">Tienda activa</span>
            <?php else: ?>
              <form method="post" action="<?php echo URLROOT; ?>/connections/selectstore/<?php echo (int)$c->id; ?>" class="form-inline">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
                <button type="submit" class="btn btn-select">Seleccionar</button>
              </form>
            <?php endif; ?>
            <?php if ($tokenExpired): ?>
              <form method="post" action="<?php echo URLROOT; ?>/connections/reauthorize/<?php echo (int)$c->id; ?>" class="form-inline">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
                <button type="submit" class="btn btn-refresh">Reconectar</button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?php echo URLROOT; ?>/connections/disconnect/<?php echo (int)$c->id; ?>" class="form-inline">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
              <button type="submit" class="btn btn-disconnect">Desactivar</button>
            </form>
          <?php else: ?>
            <form method="post" action="<?php echo URLROOT; ?>/connections/reactivate/<?php echo (int)$c->id; ?>" class="form-inline">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
              <button type="submit" class="btn btn-reactivate">Reactivar</button>
            </form>
            <form method="post" action="<?php echo URLROOT; ?>/connections/disconnect/<?php echo (int)$c->id; ?>" class="form-inline" onsubmit="return confirm('¿Eliminar permanentemente esta conexión?');">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
              <button type="submit" class="btn btn-delete">Eliminar</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div id="renameModal" class="modal-overlay" style="display:none" onclick="closeRename(event)">
  <div class="modal-card" onclick="event.stopPropagation()">
    <h3>Renombrar tienda</h3>
    <form method="post" action="" id="renameForm">
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
  if (e.key === 'Escape') closeRename({target:document, currentTarget:document});
});
</script>

