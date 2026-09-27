<?php
$batches = $data['batches'] ?? [];
$paging = $data['paging'] ?? null;
$page = (int) ($data['page'] ?? 1);
$perPage = $paging->limit ?? 20;
$st = $data['stats'] ?? null;
$totalOk = (int) ($st['total_ok'] ?? 0);
$totalItems = (int) ($st['total_items'] ?? 0);
$rate = $totalItems > 0 ? round(($totalOk / $totalItems) * 100) : 0;
$filterFrom = $data['filter_from'] ?? '';
$filterTo = $data['filter_to'] ?? '';
$filterBatch = trim($_GET['batch'] ?? '');

function phTimeAgo($datetime) {
    $now = time();
    $ts = is_numeric($datetime) ? $datetime : strtotime($datetime);
    $diff = $now - $ts;
    if ($diff < 60) return 'ahora';
    if ($diff < 3600) return floor($diff / 60) . ' min';
    if ($diff < 86400) return floor($diff / 3600) . 'h';
    if ($diff < 172800) return 'ayer';
    if ($diff < 604800) return floor($diff / 86400) . ' días';
    return date('d/m/Y', $ts);
}
function phFormatDate($datetime) {
    return date('d/m/Y H:i', is_numeric($datetime) ? $datetime : strtotime($datetime));
}
function phDuration($startStr, $endStr) {
    $s = strtotime($startStr . ' UTC');
    $e = strtotime($endStr . ' UTC');
    $diff = $e - $s;
    if ($diff < 60) return $diff . 's';
    if ($diff < 3600) return floor($diff / 60) . 'm ' . ($diff % 60) . 's';
    return floor($diff / 3600) . 'h ' . floor(($diff % 3600) / 60) . 'm';
}
?>
<div class="ph-wrap">

  <!-- Stats -->
  <?php if ($st && $totalItems > 0): ?>
  <div class="ph-stats">
    <div class="ph-stat">
      <div class="ph-stat-icon" class="phub-stat-icon is-ml-blue"><?php echo icon('scan', ['size' => 20]); ?></div>
      <div class="ph-stat-body">
        <span class="ph-stat-number"><?php echo number_format($totalItems, 0, ',', '.'); ?></span>
        <span class="ph-stat-label">Items procesados</span>
      </div>
    </div>
    <div class="ph-stat">
      <div class="ph-stat-icon" class="phub-stat-icon is-green"><?php echo icon('circle-check', ['size' => 20]); ?></div>
      <div class="ph-stat-body">
        <span class="ph-stat-number ph-stat-number--rate" data-rate="<?php echo $rate; ?>"><?php echo $rate; ?>%</span>
        <span class="ph-stat-label">Tasa de éxito</span>
      </div>
    </div>
    <div class="ph-stat">
      <div class="ph-stat-icon" class="phub-stat-icon is-purple"><?php echo icon('layers', ['size' => 20]); ?></div>
      <div class="ph-stat-body">
        <span class="ph-stat-number"><?php echo (int) ($st['total_batches'] ?? 0); ?></span>
        <span class="ph-stat-label">Ejecuciones totales</span>
      </div>
    </div>
    <div class="ph-stat">
      <div class="ph-stat-icon" class="phub-stat-icon is-cyan"><?php echo icon('calendar', ['size' => 20]); ?></div>
      <div class="ph-stat-body">
        <span class="ph-stat-number"><?php echo (int) ($st['executions_month'] ?? 0); ?></span>
        <span class="ph-stat-label">Ejecuciones este mes</span>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Search -->
  <div class="ph-search">
    <form method="get" action="<?php echo URLROOT; ?>/connections/priceHistory">
      <div class="ph-search-row">
        <div class="ph-search-group">
          <?php echo icon('search', ['size' => 16, 'class' => 'ph-search-icon']); ?>
          <input type="search" name="batch" placeholder="Buscar por Batch ID…" value="<?php echo htmlspecialchars($filterBatch); ?>" class="ph-search-input" autocomplete="off" spellcheck="false" aria-label="Buscar por Batch ID">
        </div>
      </div>
      <div class="ph-search-row ph-search-row--dates">
        <div class="ph-search-field">
          <label for="phFrom" class="ph-search-label">Desde</label>
          <input type="date" name="from" id="phFrom" value="<?php echo htmlspecialchars($filterFrom); ?>" class="ph-date-input">
        </div>
        <div class="ph-search-field">
          <label for="phTo" class="ph-search-label">Hasta</label>
          <input type="date" name="to" id="phTo" value="<?php echo htmlspecialchars($filterTo); ?>" class="ph-date-input">
        </div>
        <button type="submit" class="ph-search-btn">Filtrar</button>
        <?php if (!empty($filterBatch) || !empty($filterFrom) || !empty($filterTo)): ?>
          <a href="<?php echo URLROOT; ?>/connections/priceHistory" class="ph-search-clear"><?php echo icon('x', ['size' => 14]); ?> Limpiar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Empty state -->
  <?php if (empty($batches)):
    $hasFilters = !empty($filterBatch) || !empty($filterFrom) || !empty($filterTo);
    $hasData = ($totalItems > 0);
  ?>
  <div class="ph-empty">
    <div class="ph-empty-icon"><?php echo icon($hasFilters ? 'search' : 'clock-3', ['size' => 48]); ?></div>
    <h3><?php echo $hasFilters ? 'Sin resultados' : 'Sin ejecuciones'; ?></h3>
    <p>
      <?php if ($hasFilters && $hasData): ?>
        No se encontraron ejecuciones que coincidan con los filtros aplicados. Intenta con otros términos o fechas.
      <?php elseif ($hasFilters): ?>
        No hay datos históricos que coincidan con tu búsqueda. Aún no se han registrado ejecuciones de cambio de precios.
      <?php else: ?>
        Aún no has realizado cambios masivos de precios. Cuando ejecutes uno, aparecerá aquí.
      <?php endif; ?>
    </p>
    <?php if ($hasFilters): ?>
      <a href="<?php echo URLROOT; ?>/connections/priceHistory" class="ph-empty-btn">Limpiar filtros</a>
    <?php else: ?>
      <a href="<?php echo URLROOT; ?>/connections/bulkPrices" class="ph-empty-btn">Ir a cambio masivo</a>
    <?php endif; ?>
  </div>
  <?php else: ?>

  <!-- Execution cards -->
  <div class="ph-list">
    <?php foreach ($batches as $batch):
      $startedTs = strtotime($batch['started_at'] . ' UTC');
      $finishedTs = strtotime($batch['finished_at'] . ' UTC');
      $batchTotal = $batch['success_count'] + $batch['error_count'];
      $batchPct = $batchTotal > 0 ? round(($batch['success_count'] / $batchTotal) * 100) : 0;
      $isFullyOk = $batch['error_count'] === 0;
      $isFailed = $batch['success_count'] === 0;
      $duration = phDuration($batch['started_at'], $batch['finished_at']);
      $fullBatchId = htmlspecialchars($batch['batch_id']);
      $storeName = htmlspecialchars($batch['store_name'] ?? '');
      $username = htmlspecialchars($batch['username'] ?? '');
    ?>
    <div class="ph-batch <?php echo $isFullyOk ? 'ph-batch-ok' : ($isFailed ? 'ph-batch-err' : 'ph-batch-warn'); ?>">
      <div class="ph-batch-body">
        <div class="ph-batch-header">
          <span class="ph-batch-status">
            <span class="ph-batch-icon"><?php echo icon($isFullyOk ? 'circle-check' : ($isFailed ? 'circle-x' : 'triangle-alert'), ['size' => 18]); ?></span>
            <span class="ph-batch-status-label"><?php echo $isFullyOk ? 'Completado' : ($isFailed ? 'Fallido' : 'Parcial'); ?></span>
          </span>
          <span class="ph-batch-time"><?php echo phTimeAgo($startedTs); ?></span>
          <span class="ph-batch-date"><?php echo phFormatDate($startedTs); ?></span>
          <span class="ph-batch-duration"><?php echo $duration; ?></span>
        </div>
        <div class="ph-batch-meta">
          <span class="ph-batch-id-lbl">Batch</span>
          <code class="ph-batch-id"><?php echo $fullBatchId; ?></code>
          <button type="button" class="ph-batch-copy" data-batch="<?php echo $fullBatchId; ?>" title="Copiar Batch ID" aria-label="Copiar Batch ID">
            <?php echo icon('clipboard-copy', ['size' => 12]); ?>
          </button>
        </div>
        <div class="ph-batch-footer">
          <span class="ph-batch-stat">
            <span class="ph-batch-stat-value"><?php echo $batchTotal; ?></span>
            <span class="ph-batch-stat-label">items</span>
          </span>
          <span class="ph-batch-divider"></span>
          <span class="ph-batch-stat">
            <span class="ph-batch-stat-value ph-batch-stat-value--rate" style="color:<?php echo $batchPct >= 100 ? '#22c55e' : ($batchPct > 50 ? '#f59e0b' : '#ef4444'); ?>"><?php echo $batchPct; ?>%</span>
            <span class="ph-batch-stat-label">éxito</span>
          </span>
          <?php if ($storeName): ?>
          <span class="ph-batch-divider"></span>
          <span class="ph-batch-stat">
            <span class="ph-batch-stat-label"><?php echo htmlspecialchars($storeName); ?></span>
          </span>
          <?php endif; ?>
          <?php if ($username): ?>
          <span class="ph-batch-divider"></span>
          <span class="ph-batch-stat">
            <span class="ph-batch-stat-label" style="opacity:0.6;">por <?php echo htmlspecialchars($username); ?></span>
          </span>
          <?php endif; ?>
          <span class="ph-batch-spacer"></span>
          <a href="<?php echo URLROOT; ?>/connections/exportBatchXlsx/<?php echo urlencode($batch['batch_id']); ?>" class="ph-batch-dl" data-throttle="true" title="Descargar XLSX" aria-label="Descargar reporte XLSX">
            <?php echo icon('download', ['size' => 14]); ?>
            XLSX
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($paging && $paging->total > $perPage):
    $totalPages = (int) ceil($paging->total / $perPage);
  ?>
  <div class="ph-pagination">
    <?php if ($page > 1): ?>
      <a href="?page=<?php echo $page - 1; ?>&batch=<?php echo urlencode($filterBatch); ?>&from=<?php echo urlencode($filterFrom); ?>&to=<?php echo urlencode($filterTo); ?>" class="ph-page ph-page-prev">← Anterior</a>
    <?php endif; ?>
    <?php
      $startPage = max(1, $page - 2);
      $endPage = min($totalPages, $startPage + 4);
      if ($endPage - $startPage < 4) $startPage = max(1, $endPage - 4);
      for ($i = $startPage; $i <= $endPage; $i++):
    ?>
      <?php if ($i === 1 || $i === $totalPages || abs($i - $page) <= 2): ?>
        <a href="?page=<?php echo $i; ?>&batch=<?php echo urlencode($filterBatch); ?>&from=<?php echo urlencode($filterFrom); ?>&to=<?php echo urlencode($filterTo); ?>" class="ph-page <?php echo $i === $page ? 'current' : ''; ?>"><?php echo $i; ?></a>
      <?php elseif ($i === 2 || $i === $totalPages - 1): ?>
        <span class="ph-page" style="border:none;pointer-events:none;">…</span>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <a href="?page=<?php echo $page + 1; ?>&batch=<?php echo urlencode($filterBatch); ?>&from=<?php echo urlencode($filterFrom); ?>&to=<?php echo urlencode($filterTo); ?>" class="ph-page ph-page-next">Siguiente →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<script src="<?php echo URLROOT; ?>/assets/js/price-history.js"></script>
