<?php
$summary = $data['summary'] ?? ['by_store' => [], 'top_products' => [], 'daily' => []];
$syncStatus = $data['sync_status'] ?? [];
$dateFrom = $data['date_from'] ?? '';
$dateTo = $data['date_to'] ?? '';
$csrfToken = $data['csrf_token'] ?? '';

$totalRevenue = 0;
$totalOrders = 0;
$totalUnits = 0;
foreach ($summary['by_store'] as $s) {
    $totalRevenue += (float) $s->total_revenue;
    $totalOrders += (int) $s->total_orders;
    $totalUnits += (int) $s->total_units;
}

$neverSynced = array_filter($syncStatus, function ($s) { return $s['count'] === 0; });
?>
<div class="ahub">

  <div class="audit-breadcrumb">
    <a href="<?php echo URLROOT; ?>/reports" class="audit-back">
      <?php echo icon('arrow-left', ['size' => 14]); ?>
      Reportes
    </a>
  </div>

  <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
    <div>
      <h2 class="text-base font-semibold text-primary">Ventas Walmart</h2>
      <p class="text-xs text-muted">Datos obtenidos desde la API de órdenes de Walmart.</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <form id="dateRangeForm" class="flex items-center gap-2">
        <input type="date" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2 py-1.5">
        <span class="text-xs text-muted">a</span>
        <input type="date" name="to" value="<?php echo htmlspecialchars($dateTo); ?>" class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2 py-1.5">
        <button type="submit" class="btn-secondary text-xs">Aplicar</button>
      </form>
      <button type="button" id="syncSalesBtn" class="btn-primary text-xs">
        <?php echo icon('refresh-cw', ['size' => 14]); ?>
        Actualizar ventas
      </button>
    </div>
  </div>

  <?php if (!empty($neverSynced)): ?>
    <div class="flex items-center gap-3 p-4 mb-4 rounded-xl text-sm bg-amber-500/5 border border-amber-500/20 text-muted">
      <?php echo icon('info', ['size' => 18]); ?>
      <span>
        <?php echo count($neverSynced); ?> tienda(s) todavía no tienen ninguna orden sincronizada:
        <strong><?php echo implode(', ', array_map(function ($s) { return $s['store_name']; }, $neverSynced)); ?></strong>.
        Haz clic en "Actualizar ventas" para traer los datos por primera vez.
      </span>
    </div>
  <?php endif; ?>

  <div id="syncResultBox" class="hidden mb-4"></div>

  <!-- Totales del período -->
  <div class="ahub-grid mb-5">
    <div class="ahub-tile" style="cursor:default;">
      <div class="ahub-tile-icon phub-stat-icon is-success"><?php echo icon('dollar-sign', ['size' => 20]); ?></div>
      <div class="ahub-tile-text">
        <span class="ahub-tile-label">$<?php echo number_format($totalRevenue, 0, ',', '.'); ?></span>
        <span class="ahub-tile-desc">Ingresos del período</span>
      </div>
    </div>
    <div class="ahub-tile" style="cursor:default;">
      <div class="ahub-tile-icon phub-stat-icon is-ml-blue"><?php echo icon('shopping-cart', ['size' => 20]); ?></div>
      <div class="ahub-tile-text">
        <span class="ahub-tile-label"><?php echo number_format($totalOrders); ?></span>
        <span class="ahub-tile-desc">Órdenes</span>
      </div>
    </div>
    <div class="ahub-tile" style="cursor:default;">
      <div class="ahub-tile-icon phub-stat-icon is-muted"><?php echo icon('package', ['size' => 20]); ?></div>
      <div class="ahub-tile-text">
        <span class="ahub-tile-label"><?php echo number_format($totalUnits); ?></span>
        <span class="ahub-tile-desc">Unidades vendidas</span>
      </div>
    </div>
  </div>

  <!-- Por tienda -->
  <h3 class="text-sm font-semibold text-primary mb-2">Por tienda</h3>
  <?php if (empty($summary['by_store'])): ?>
    <div class="audit-batch-empty"><p>Sin ventas registradas en este período.</p></div>
  <?php else: ?>
  <div class="card mb-6" style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
      <thead>
        <tr style="text-align:left;color:var(--text-secondary);border-bottom:1px solid var(--border);">
          <th style="padding:0.6rem 0.75rem;">Tienda</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Órdenes</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Unidades</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Ingresos</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($summary['by_store'] as $s): ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:0.6rem 0.75rem;"><?php echo htmlspecialchars($s->store_name); ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;"><?php echo number_format((int) $s->total_orders); ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;"><?php echo number_format((int) $s->total_units); ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;font-weight:600;">$<?php echo number_format((float) $s->total_revenue, 0, ',', '.'); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Productos más vendidos -->
  <h3 class="text-sm font-semibold text-primary mb-2">Productos más vendidos</h3>
  <?php if (empty($summary['top_products'])): ?>
    <div class="audit-batch-empty"><p>Sin datos en este período.</p></div>
  <?php else: ?>
  <div class="card mb-6" style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
      <thead>
        <tr style="text-align:left;color:var(--text-secondary);border-bottom:1px solid var(--border);">
          <th style="padding:0.6rem 0.75rem;">SKU</th>
          <th style="padding:0.6rem 0.75rem;">Producto</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Unidades</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Ingresos</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($summary['top_products'] as $p): ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:0.6rem 0.75rem;font-family:monospace;"><?php echo htmlspecialchars($p->sku ?? '—'); ?></td>
          <td style="padding:0.6rem 0.75rem;max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($p->product_name ?? '—'); ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;"><?php echo number_format((int) $p->total_units); ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;font-weight:600;">$<?php echo number_format((float) $p->total_revenue, 0, ',', '.'); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Ventas por día -->
  <h3 class="text-sm font-semibold text-primary mb-2">Ventas por día</h3>
  <?php if (empty($summary['daily'])): ?>
    <div class="audit-batch-empty"><p>Sin datos en este período.</p></div>
  <?php else: ?>
  <div class="card" style="overflow-x:auto;max-height:400px;overflow-y:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
      <thead style="position:sticky;top:0;background:var(--card-bg, #0f0f14);">
        <tr style="text-align:left;color:var(--text-secondary);border-bottom:1px solid var(--border);">
          <th style="padding:0.6rem 0.75rem;">Día</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Órdenes</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Ingresos</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_reverse($summary['daily']) as $d): ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:0.5rem 0.75rem;"><?php echo date('d/m/Y', strtotime($d->day)); ?></td>
          <td style="padding:0.5rem 0.75rem;text-align:right;"><?php echo number_format((int) $d->total_orders); ?></td>
          <td style="padding:0.5rem 0.75rem;text-align:right;">$<?php echo number_format((float) $d->total_revenue, 0, ',', '.'); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
(function() {
  document.getElementById('dateRangeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var from = this.from.value;
    var to = this.to.value;
    window.location.href = '<?php echo URLROOT; ?>/reports/sales?from=' + from + '&to=' + to;
  });

  var syncBtn = document.getElementById('syncSalesBtn');
  syncBtn.addEventListener('click', function() {
    syncBtn.disabled = true;
    var original = syncBtn.innerHTML;
    syncBtn.innerHTML = 'Sincronizando…';

    var fd = new FormData();
    fd.append('csrf_token', <?php echo json_encode($csrfToken); ?>);

    fetch('<?php echo URLROOT; ?>/reports/syncSales', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        var box = document.getElementById('syncResultBox');
        box.classList.remove('hidden');
        if (d.ok) {
          var html = '<div class="flex flex-col gap-1 p-4 rounded-xl text-sm bg-success/5 border border-success/20 text-muted">';
          d.results.forEach(function(r) {
            html += '<div>' + (r.ok ? '✓' : '✗') + ' ' + r.store + ': ' + r.synced + ' órdenes' + (r.failed > 0 ? ', ' + r.failed + ' con error' : '') + (r.error ? ' — ' + r.error : '') + '</div>';
          });
          html += '</div>';
          box.innerHTML = html;
          if (typeof showToast === 'function') showToast('Sincronización de ventas completada', 'success', 4000);
          setTimeout(function() { window.location.reload(); }, 1500);
        } else {
          box.innerHTML = '<div class="p-4 rounded-xl text-sm bg-red-500/5 border border-red-500/20 text-muted">Error: ' + (d.error || 'desconocido') + '</div>';
        }
      })
      .catch(function() {
        if (typeof showToast === 'function') showToast('Error de conexión al sincronizar.', 'error', 5000);
      })
      .finally(function() {
        syncBtn.disabled = false;
        syncBtn.innerHTML = original;
      });
  });
})();
</script>
