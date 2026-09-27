<?php $preview = $data['preview'] ?? []; $total = $data['total'] ?? 0; ?>
<div class="ahub">

  <div class="audit-breadcrumb">
    <a href="<?php echo URLROOT; ?>/reports" class="audit-back">
      <?php echo icon('arrow-left', ['size' => 14]); ?>
      Reportes
    </a>
  </div>

  <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
    <div>
      <h2 class="text-base font-semibold text-primary">Snapshot de precios</h2>
      <p class="text-xs text-muted"><?php echo number_format($total); ?> productos activos en total, ambos canales. Mostrando los primeros 50 en pantalla — descarga el Excel completo.</p>
    </div>
    <button type="button" class="btn-primary text-xs report-download-btn" data-url="<?php echo URLROOT; ?>/reports/exportPrices" data-name="reporte_precios">
      <?php echo icon('download', ['size' => 14]); ?>
      Descargar Excel completo
    </button>
  </div>

  <?php if (empty($preview)): ?>
    <div class="audit-batch-empty"><p>No hay productos en caché todavía.</p></div>
  <?php else: ?>
  <div class="card" style="overflow-x:auto;max-height:600px;overflow-y:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
      <thead style="position:sticky;top:0;background:var(--card-bg, #0f0f14);">
        <tr style="text-align:left;color:var(--text-secondary);border-bottom:1px solid var(--border);">
          <th style="padding:0.6rem 0.75rem;">Canal</th>
          <th style="padding:0.6rem 0.75rem;">Tienda</th>
          <th style="padding:0.6rem 0.75rem;">SKU</th>
          <th style="padding:0.6rem 0.75rem;">Título</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Precio</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Stock</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($preview as $r): ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:0.5rem 0.75rem;">
            <span class="badge-<?php echo $r['channel'] === 'ML' ? 'ok' : 'err'; ?>" style="opacity:0.85;"><?php echo $r['channel']; ?></span>
          </td>
          <td style="padding:0.5rem 0.75rem;"><?php echo htmlspecialchars($r['store_name']); ?></td>
          <td style="padding:0.5rem 0.75rem;font-family:monospace;"><?php echo htmlspecialchars($r['sku'] ?? '—'); ?></td>
          <td style="padding:0.5rem 0.75rem;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($r['title'] ?? ''); ?></td>
          <td style="padding:0.5rem 0.75rem;text-align:right;">$<?php echo number_format((float) $r['price']); ?></td>
          <td style="padding:0.5rem 0.75rem;text-align:right;"><?php echo (int) $r['stock']; ?></td>
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
