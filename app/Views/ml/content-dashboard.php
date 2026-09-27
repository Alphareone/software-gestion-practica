<?php
$connections = $data['connections'] ?? [];
$activeStoreId = $data['active_store_id'] ?? null;
$activeStore = $data['active_store'] ?? null;
$hasPendingBatch = $data['has_pending_batch'] ?? false;
$asyncMetrics = !empty($data['async_metrics']);
?>

<div class="ml-dashboard">

  <?php if ($hasPendingBatch): ?>
  <div class="dash-alert dash-alert-warning">
    <strong><?php echo icon('octagon-alert', ['size' => 18]); ?></strong>
    <span class="dash-alert-text">
      <strong>Proceso pendiente:</strong> Tienes un cambio de precios en curso. Debes finalizarlo o cancelarlo antes de cambiar de tienda.
    </span>
    <a href="<?php echo URLROOT; ?>/connections/prices" class="dash-alert-link">Ir a precios</a>
  </div>
  <?php endif; ?>

  <?php if (empty($connections)): ?>
    <div class="dash-empty">
      <h3>No hay tiendas conectadas</h3>
      <p>Actualmente no hay ninguna tienda disponible para activar. Contacta a un administrador.</p>
    </div>
  <?php else: ?>

    <!-- Store list (compact selector) -->
    <div class="dash-section">
      <div class="flex items-center gap-2 mb-5 mt-1">
        <span class="w-1.5 h-5 rounded-full bg-accent"></span>
        <h2 class="text-sm font-semibold text-primary uppercase tracking-wider">Tiendas disponibles</h2>
      </div>
      <div class="store-list">
        <?php foreach ($connections as $c):
          $isActive = (int)$activeStoreId === (int)$c->id;
          $country = strtoupper(trim($c->country ?? ''));
          $initials = '';
          $syncStatus = $c->sync_status ?? 'idle';
          $lastSync = $c->last_sync_at ?? null;
          $productCount = (int) ($c->product_count ?? 0);

          if ($syncStatus === 'syncing') {
            $syncClass = 'syncing';
            $syncLabel = 'Sincronizando…';
          } elseif ($syncStatus === 'error') {
            $syncClass = 'error';
            $syncLabel = 'Error en sync';
          } elseif ($lastSync) {
            $diff = time() - strtotime($lastSync . ' UTC');
            if ($diff < 86400) {
              $syncClass = 'ok';
              $syncLabel = 'Sincronizado';
            } else {
              $syncClass = 'stale';
              $syncLabel = 'Sync antiguo';
            }
          } else {
            $syncClass = 'none';
            $syncLabel = 'Sin sincronizar';
          }

          if ($lastSync) {
            $diff = time() - strtotime($lastSync . ' UTC');
            if ($diff < 60) $syncTime = 'Ahora';
            elseif ($diff < 3600) $syncTime = floor($diff / 60) . ' min';
            elseif ($diff < 86400) $syncTime = floor($diff / 3600) . ' h';
            else $syncTime = floor($diff / 86400) . ' d';
          } else {
            $syncTime = '—';
          }
        ?>
          <div class="store-list-row <?php echo $isActive ? 'active' : ''; ?>">
            <div class="store-list-body">
              <span class="store-list-name"><?php echo htmlspecialchars($c->store_name ?: 'Sin nombre'); ?></span>
              <span class="store-list-country"><?php echo $country; ?></span>
            </div>
            <div class="store-list-right">
              <span class="store-list-sub">
                <span class="store-list-dot store-list-dot--<?php echo $syncClass; ?>"></span>
                <?php echo $syncLabel; ?>
              </span>
              <span class="store-list-prod"><?php echo $productCount; ?> prod</span>
              <span class="store-list-time"><?php echo $syncTime; ?></span>
              <div class="store-list-end">
                <?php if ($isActive): ?>
                  <form method="post" action="<?php echo URLROOT; ?>/connections/deselect" class="form-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
                    <button type="submit" class="store-list-select store-list-select--active">Salir de esta tienda</button>
                  </form>
                <?php else: ?>
                  <?php if (!$hasPendingBatch): ?>
                    <?php if (!empty($c->token_ok)): ?>
                      <form method="post" action="<?php echo URLROOT; ?>/connections/selectstore/<?php echo (int)$c->id; ?>" class="form-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
                        <button type="submit" class="store-list-select">Seleccionar</button>
                      </form>
                    <?php else: ?>
                      <span class="store-list-token-expired" title="Esta tienda está con su token vencido, contacta a un administrador.">Token vencido</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span style="font-size:0.72rem;color:var(--text-secondary);opacity:0.5">Bloqueada</span>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Resumen unificado -->
    <?php if ($activeStore): ?>
    <div class="dash-section <?php echo $asyncMetrics ? 'is-loading' : ''; ?>" id="chartsSection">
      <div class="flex items-center gap-2 mb-5 mt-1">
        <span class="w-1.5 h-5 rounded-full bg-accent"></span>
        <h2 class="text-sm font-semibold text-primary uppercase tracking-wider">Resumen: <?php echo htmlspecialchars($activeStore->store_name ?: 'Tienda'); ?></h2>
      </div>
      <div class="resumen-card">
        <div class="metric-grid" id="metricGrid">
          <div class="metric-card">
            <span class="metric-value" id="metricPublished"><?php echo $asyncMetrics ? '<span class="kpi-spinner"></span>' : '-'; ?></span>
            <span class="metric-label">Productos publicados</span>
          </div>
          <div class="metric-card">
            <span class="metric-value" id="metricActive"><?php echo $asyncMetrics ? '<span class="kpi-spinner"></span>' : '-'; ?></span>
            <span class="metric-label">Productos activos</span>
          </div>
          <div class="metric-card" id="metricLastSyncCard">
            <span class="metric-value" id="metricLastSync"><?php echo $asyncMetrics ? '<span class="kpi-spinner"></span>' : '-'; ?></span>
            <span class="metric-label">Última sincronización</span>
          </div>
        </div>
      </div>
      <?php if ($asyncMetrics): ?>
      <div class="kpi-error" id="kpiError" style="display:none"></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  <?php endif; ?>
</div>

<?php if ($activeStore): ?>
<script>
window.ML_DASHBOARD_CONFIG = {
  metricsUrl: '<?php echo URLROOT; ?>/connections/metrics'
};
</script>
<?php endif; ?>
