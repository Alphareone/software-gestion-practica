<?php
$summary = $data['summary'] ?? [];
$recurring = $data['recurring'] ?? [];
$typeLabels = ['price' => 'Precios', 'stock' => 'Stock'];
?>
<div class="ahub">

  <div class="audit-breadcrumb">
    <a href="<?php echo URLROOT; ?>/reports" class="audit-back">
      <?php echo icon('arrow-left', ['size' => 14]); ?>
      Reportes
    </a>
  </div>

  <h2 class="text-base font-semibold text-primary mb-4">Resumen de auditorías</h2>

  <?php if (empty($summary)): ?>
    <div class="audit-batch-empty"><p>Todavía no se ha corrido ninguna auditoría.</p></div>
  <?php else: ?>
  <div class="card mb-6" style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
      <thead>
        <tr style="text-align:left;color:var(--text-secondary);border-bottom:1px solid var(--border);">
          <th style="padding:0.6rem 0.75rem;">Canal</th>
          <th style="padding:0.6rem 0.75rem;">Tipo</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Batches</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">SKUs auditados</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Coincidencias</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Diferencias</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Tasa de acierto</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Última auditoría</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($summary as $r): ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:0.6rem 0.75rem;">
            <span class="badge-<?php echo $r['channel'] === 'ML' ? 'ok' : 'err'; ?>" style="opacity:0.85;"><?php echo $r['channel']; ?></span>
          </td>
          <td style="padding:0.6rem 0.75rem;"><?php echo $typeLabels[$r['audit_type']] ?? htmlspecialchars($r['audit_type']); ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;"><?php echo number_format($r['total_batches']); ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;"><?php echo number_format($r['total_skus']); ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;color:var(--success, #10b981);"><?php echo number_format($r['matches']); ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;color:<?php echo $r['mismatches'] > 0 ? 'var(--danger, #ef4444)' : 'inherit'; ?>;"><?php echo number_format($r['mismatches']); ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;font-weight:600;"><?php echo $r['match_rate'] !== null ? $r['match_rate'] . '%' : '—'; ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;color:var(--text-secondary);font-size:0.8rem;">
            <?php echo $r['last_audit'] ? date('d/m/Y H:i', strtotime($r['last_audit'])) : '—'; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <h3 class="text-sm font-semibold text-primary mb-2">SKUs con diferencias recurrentes</h3>
  <p class="text-xs text-muted mb-3">SKUs que aparecieron con diferencia en más de una auditoría — sugiere un problema estructural (ej. plantilla desactualizada) más que un error puntual.</p>

  <?php if (empty($recurring)): ?>
    <div class="audit-batch-empty"><p>Sin diferencias recurrentes detectadas todavía. Buena señal.</p></div>
  <?php else: ?>
  <div class="card" style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
      <thead>
        <tr style="text-align:left;color:var(--text-secondary);border-bottom:1px solid var(--border);">
          <th style="padding:0.6rem 0.75rem;">Canal</th>
          <th style="padding:0.6rem 0.75rem;">SKU</th>
          <th style="padding:0.6rem 0.75rem;">Tipo</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Veces con diferencia</th>
          <th style="padding:0.6rem 0.75rem;text-align:right;">Última vez</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recurring as $r): ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:0.6rem 0.75rem;">
            <span class="badge-<?php echo $r['channel'] === 'ML' ? 'ok' : 'err'; ?>" style="opacity:0.85;"><?php echo $r['channel']; ?></span>
          </td>
          <td style="padding:0.6rem 0.75rem;font-family:monospace;"><?php echo htmlspecialchars($r['sku']); ?></td>
          <td style="padding:0.6rem 0.75rem;"><?php echo $typeLabels[$r['audit_type']] ?? htmlspecialchars($r['audit_type']); ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;font-weight:600;color:var(--danger, #ef4444);"><?php echo $r['times_seen']; ?></td>
          <td style="padding:0.6rem 0.75rem;text-align:right;color:var(--text-secondary);font-size:0.8rem;">
            <?php echo $r['last_seen'] ? date('d/m/Y', strtotime($r['last_seen'])) : '—'; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
