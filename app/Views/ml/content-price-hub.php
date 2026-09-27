<?php
$metrics = $data['metrics'] ?? [];
$recentBatches = $data['recent_batches'] ?? [];
$lastBatch = $data['last_batch'] ?? null;
$totalBatches = (int) ($metrics['total_batches'] ?? 0);
$syncedCount = (int) ($metrics['synced_products'] ?? 0);

$lastUpdateLabel = 'Sin registros';
if (!empty($metrics['last_price_update'])) {
    $lastUpdateLabel = date('d/m/Y H:i', strtotime($metrics['last_price_update'] . ' UTC'));
}

$successRate = $metrics['success_rate'];
$successRateLabel = $successRate !== null ? (int) $successRate . '%' : '—';
$successRateClass = '';
if ($successRate !== null) {
    $r = (int) $successRate;
    if ($r >= 90) {
        $successRateClass = 'phub-rate-ok';
    } elseif ($r >= 70) {
        $successRateClass = 'phub-rate-warn';
    } else {
        $successRateClass = 'phub-rate-err';
    }
}

$bulkMeta = 'Sin ejecuciones registradas';
if ($lastBatch) {
    $bulkMeta = 'Última: ' . date('d/m/Y H:i', strtotime($lastBatch['started_at'] . ' UTC'))
        . ' · ' . number_format($lastBatch['total'], 0, ',', '.') . ' items';
}

$historyMeta = $totalBatches > 0
    ? number_format($totalBatches, 0, ',', '.') . ' ejecuciones'
    : 'Sin ejecuciones registradas';
?>
<?php if (!empty($data['price_disabled'])): ?>
<div class="phub-banner-disabled">
  <?php echo icon('ban', ['size' => 18]); ?>
  <span>Las modificaciones de precios están deshabilitadas. Solo puedes consultar el historial.</span>
</div>
<?php endif; ?>
<div class="phub">

  <!-- Stats -->
  <div class="phub-stats">
    <div class="phub-stat">
      <div class="phub-stat-icon" class="phub-stat-icon is-success">
        <?php echo icon('database', ['size' => 20]); ?>
      </div>
      <div class="phub-stat-body">
        <?php if ($syncedCount === 0): ?>
          <span class="phub-stat-value is-empty">—</span>
          <span class="phub-stat-hint">Sin datos aún</span>
        <?php else: ?>
          <span class="phub-stat-value"><?php echo number_format($syncedCount, 0, ',', '.'); ?></span>
        <?php endif; ?>
        <span class="phub-stat-label">Productos sincronizados</span>
      </div>
    </div>
    <div class="phub-stat">
      <div class="phub-stat-icon" class="phub-stat-icon is-accent">
        <?php echo icon('clock-3', ['size' => 20]); ?>
      </div>
      <div class="phub-stat-body">
        <span class="phub-stat-value phub-stat-value--sm"><?php echo htmlspecialchars($lastUpdateLabel); ?></span>
        <span class="phub-stat-label">Última actualización</span>
      </div>
    </div>
    <div class="phub-stat">
      <div class="phub-stat-icon" class="phub-stat-icon is-warning">
        <?php echo icon('badge-check', ['size' => 20]); ?>
      </div>
      <div class="phub-stat-body">
        <span class="phub-stat-value <?php echo $successRateClass; ?>"><?php echo htmlspecialchars($successRateLabel); ?></span>
        <span class="phub-stat-label">Tasa de éxito</span>
      </div>
    </div>
    <div class="phub-stat">
      <div class="phub-stat-icon" class="phub-stat-icon is-indigo">
        <?php echo icon('calendar', ['size' => 20]); ?>
      </div>
      <div class="phub-stat-body">
        <span class="phub-stat-value"><?php echo number_format((int) ($metrics['executions_month'] ?? 0), 0, ',', '.'); ?></span>
        <span class="phub-stat-label">Ejecuciones este mes</span>
      </div>
    </div>
  </div>

  <!-- Action cards -->
  <div class="phub-actions">
    <div class="phub-action phub-action--disabled" aria-disabled="true">
      <div class="phub-action-icon" class="phub-stat-icon is-success">
        <?php echo icon('upload', ['size' => 24]); ?>
      </div>
      <div class="phub-action-body">
        <span class="phub-action-title">Cambio masivo</span>
        <span class="phub-action-desc">Deshabilitado</span>
        <span class="phub-action-meta"><?php echo htmlspecialchars($bulkMeta); ?></span>
      </div>
    </div>
    <a href="<?php echo URLROOT; ?>/connections/priceHistory" class="phub-action">
      <div class="phub-action-icon" class="phub-stat-icon is-cyan">
        <?php echo icon('clock-3', ['size' => 24]); ?>
      </div>
      <div class="phub-action-body">
        <span class="phub-action-title">Historial</span>
        <span class="phub-action-desc">Revisa ejecuciones y descarga reportes</span>
        <span class="phub-action-meta"><?php echo htmlspecialchars($historyMeta); ?></span>
      </div>
      <span class="phub-action-arrow"><?php echo icon('chevron-right', ['size' => 16]); ?></span>
    </a>
    <div class="phub-action phub-action--soon" aria-disabled="true">
      <div class="phub-action-icon" class="phub-stat-icon is-muted">
        <?php echo icon('dollar-sign', ['size' => 24]); ?>
      </div>
      <div class="phub-action-body">
        <span class="phub-action-title">Precio unitario</span>
        <span class="phub-action-desc">Actualiza el precio de un solo producto</span>
      </div>
    </div>
  </div>

  <!-- Recent batches -->
  <?php if (!empty($recentBatches)): ?>
  <section class="phub-recent">
    <h4 class="phub-recent-title">Últimas ejecuciones</h4>
    <div class="phub-recent-list">
      <?php foreach ($recentBatches as $batch):
        $isFullOk = ((int) ($batch['error_count'] ?? 0)) === 0;
        $isFailed = ((int) ($batch['success_count'] ?? 0)) === 0;
        $batchTotal = (int) ($batch['total'] ?? 0);
        $icon = $isFullOk ? 'circle-check' : ($isFailed ? 'circle-x' : 'triangle-alert');
        $label = $isFullOk ? 'Completado' : ($isFailed ? 'Fallido' : 'Parcial');
        $color = $isFullOk ? '#22c55e' : ($isFailed ? '#ef4444' : '#f59e0b');
      ?>
      <div class="phub-row">
        <span class="phub-row-icon" style="color:<?php echo $color; ?>"><?php echo icon($icon, ['size' => 14]); ?></span>
        <span class="phub-row-label" style="color:<?php echo $color; ?>"><?php echo $label; ?></span>
        <span class="phub-row-date"><?php echo date('d/m/Y H:i', strtotime($batch['started_at'] . ' UTC')); ?></span>
        <span class="phub-row-items"><?php echo $batchTotal; ?> items</span>
        <span class="phub-row-rate" style="color:<?php echo $color; ?>"><?php echo (int) ($batch['success_rate'] ?? 0); ?>%</span>
      </div>
      <?php endforeach; ?>
    </div>
    <a href="<?php echo URLROOT; ?>/connections/priceHistory" class="phub-recent-link">Ver historial completo →</a>
  </section>
  <?php endif; ?>
</div>

<script src="<?php echo URLROOT; ?>/assets/js/ml/price-hub.js"></script>
