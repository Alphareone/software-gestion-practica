<?php $summary = $data['summary'] ?? []; ?>
<div class="ahub">

  <div class="audit-breadcrumb">
    <a href="<?php echo URLROOT; ?>/reports" class="audit-back">
      <?php echo icon('arrow-left', ['size' => 14]); ?>
      Reportes
    </a>
  </div>

  <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
    <h2 class="text-base font-semibold text-primary">Inventario por tienda</h2>
    <div class="flex items-center gap-2">
      <button type="button" class="btn-secondary text-xs report-download-btn" data-url="<?php echo URLROOT; ?>/reports/exportInventory/ML" data-name="reporte_inventario_ml">
        <?php echo icon('download', ['size' => 14]); ?>
        Excel ML
      </button>
      <button type="button" class="btn-secondary text-xs report-download-btn" data-url="<?php echo URLROOT; ?>/reports/exportInventory/Walmart" data-name="reporte_inventario_walmart">
        <?php echo icon('download', ['size' => 14]); ?>
        Excel Walmart
      </button>
      <button type="button" class="btn-primary text-xs report-download-btn" data-url="<?php echo URLROOT; ?>/reports/exportInventory/all" data-name="reporte_inventario_completo">
        <?php echo icon('download', ['size' => 14]); ?>
        Excel completo
      </button>
    </div>
  </div>

  <?php if (empty($summary)): ?>
    <div class="audit-batch-empty"><p>No hay tiendas activas con datos de inventario todavía.</p></div>
  <?php else: ?>
  <div class="card" style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
      <thead>
        <tr style="text-align:left;color:var(--text-secondary);border-bottom:1px solid var(--border);">
          <th style="padding:0.6rem 0.75rem;">Canal</th>
          <th style="padding:0.6rem 0.75rem;">Tienda</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Total SKUs</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Con stock</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Sin stock</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Precio prom.</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Últ. sync</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($summary as $r): ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:0.6rem 0.75rem;">
            <span class="badge-<?php echo $r['channel'] === 'ML' ? 'ok' : 'err'; ?>" style="opacity:0.85;"><?php echo $r['channel']; ?></span>
          </td>
          <td style="padding:0.6rem 0.75rem;"><?php echo htmlspecialchars($r['store_name']); ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;"><?php echo number_format($r['total']); ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;color:var(--success, #10b981);"><?php echo number_format($r['with_stock']); ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;color:<?php echo $r['without_stock'] > 0 ? 'var(--danger, #ef4444)' : 'inherit'; ?>;"><?php echo number_format($r['without_stock']); ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;">$<?php echo number_format($r['avg_price']); ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;color:var(--text-secondary);font-size:0.8rem;">
            <?php echo $r['last_sync'] ? date('d/m/Y H:i', strtotime($r['last_sync'])) : '—'; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
document.querySelectorAll('.report-download-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var url = btn.getAttribute('data-url');
    var baseName = btn.getAttribute('data-name');
    var original = btn.innerHTML;
    btn.disabled = true;

    fetch(url, { credentials: 'same-origin' })
      .then(function(r) {
        if (!r.ok) throw new Error('Error al generar el archivo.');
        return r.blob();
      })
      .then(function(blob) {
        var filename = baseName + '_' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + '.xlsx';
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        document.body.appendChild(a); a.click(); a.remove();
        URL.revokeObjectURL(a.href);
        if (typeof showDownloadToast === 'function') showDownloadToast(filename, 4000);
      })
      .catch(function() {
        if (typeof showToast === 'function') showToast('Error al descargar el reporte.', 'error', 5000);
      })
      .finally(function() {
        btn.disabled = false;
        btn.innerHTML = original;
      });
  });
});
</script>
