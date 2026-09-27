<?php
$recentAudits = $data['recent_audits'] ?? [];
$auditTypeLabels = [
    'prices' => ['label' => 'Precios', 'color' => '#3483fa', 'bg' => 'rgba(52,131,250,0.15)', 'icon' => 'dollar-sign'],
    'stock' => ['label' => 'Stock', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.15)', 'icon' => 'package'],
];
?>

<div class="ahub">

  <!-- Section header -->
  <div class="flex items-center gap-2 mb-5 mt-1">
    <span class="w-1.5 h-5 rounded-full bg-accent"></span>
    <h2 class="text-sm font-semibold text-primary uppercase tracking-wider">Herramientas</h2>
  </div>

  <!-- Grid de acciones -->
  <div class="ahub-grid">
    <a href="<?php echo URLROOT; ?>/connections/auditPrices" class="ahub-tile">
      <div class="ahub-tile-icon phub-stat-icon is-ml-blue">
        <?php echo icon('dollar-sign', ['size' => 20]); ?>
      </div>
      <div class="ahub-tile-text">
        <span class="ahub-tile-label">Precios</span>
        <span class="ahub-tile-desc">Contrasta precios actuales vs. tu Excel</span>
      </div>
    </a>

    <a href="<?php echo URLROOT; ?>/connections/auditStock" class="ahub-tile">
      <div class="ahub-tile-icon phub-stat-icon is-success">
        <?php echo icon('package', ['size' => 20]); ?>
      </div>
      <div class="ahub-tile-text">
        <span class="ahub-tile-label">Stock</span>
        <span class="ahub-tile-desc">Verifica el inventario publicado en ML</span>
      </div>
    </a>

    <a href="<?php echo URLROOT; ?>/connections/auditHistory" class="ahub-tile">
      <div class="ahub-tile-icon phub-stat-icon is-muted">
        <?php echo icon('clock-3', ['size' => 20]); ?>
      </div>
      <div class="ahub-tile-text">
        <span class="ahub-tile-label">Historial</span>
        <span class="ahub-tile-desc">Todas las auditorías ejecutadas</span>
      </div>
    </a>
  </div>

</div>

<!-- Actividad reciente (full-width) -->
<div class="ahub-activity">
  <div class="ahub-activity-header">
    <h3>Actividad reciente</h3>
    <a href="<?php echo URLROOT; ?>/connections/auditHistory" class="ahub-activity-link">Ver todo <?php echo icon('arrow-right', ['size' => 14]); ?></a>
  </div>

  <?php if (empty($recentAudits)): ?>
    <div class="ahub-activity-empty">
      <?php echo icon('search', ['size' => 18]); ?>
      <span>Sin actividad reciente</span>
    </div>
  <?php else: ?>
    <div class="ahub-activity-feed">
      <?php foreach ($recentAudits as $ra):
        $typeInfo = $auditTypeLabels[$ra->audit_type] ?? ['label' => $ra->audit_type, 'color' => '#94a3b8', 'bg' => 'rgba(148,163,184,0.15)', 'icon' => 'circle'];
        $mismatches = (int) $ra->mismatches;
        $notFound = (int) $ra->not_found;
        $totalSkus = (int) $ra->total_skus;
        $hasIssues = $mismatches > 0 || $notFound > 0;

        $date = new DateTime($ra->audit_date, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $diff = (new DateTime())->diff($date);
        if ($diff->days === 0) $timeAgo = 'Hoy';
        elseif ($diff->days === 1) $timeAgo = 'Ayer';
        elseif ($diff->days < 7) $timeAgo = 'Hace ' . $diff->days . ' días';
        else $timeAgo = $date->format('d/m/Y');
      ?>
        <a href="<?php echo URLROOT; ?>/connections/auditBatchDetail/<?php echo htmlspecialchars($ra->batch_id); ?>" class="ahub-activity-item">
          <div class="ahub-activity-icon" style="background:<?php echo $typeInfo['bg']; ?>;color:<?php echo $typeInfo['color']; ?>">
            <?php echo icon($typeInfo['icon'], ['size' => 14]); ?>
          </div>
          <div class="ahub-activity-body">
            <div class="ahub-activity-top">
              <span class="ahub-activity-type"><?php echo $typeInfo['label']; ?></span>
              <?php if (!empty($ra->store_name)): ?>
                <span class="ahub-activity-store-inline">&middot; <?php echo htmlspecialchars($ra->store_name); ?></span>
              <?php endif; ?>
              <span class="ahub-activity-sku"><?php echo $totalSkus; ?> SKUs</span>
            </div>
            <div class="ahub-activity-status">
              <?php if ($hasIssues): ?>
                <?php if ($mismatches > 0): ?>
                  <span class="<?php echo $mismatches >= $totalSkus ? 'ahub-activity-issue' : 'ahub-activity-warn'; ?>"><?php echo $mismatches; ?> descuadres</span>
                <?php endif; ?>
                <?php if ($notFound > 0): ?>
                  <span class="<?php echo $notFound >= $totalSkus ? 'ahub-activity-issue' : 'ahub-activity-warn'; ?>"><?php echo $notFound; ?> no encontrados</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="ahub-activity-ok">Perfecto</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="ahub-activity-meta">
            <span class="ahub-activity-time"><?php echo $timeAgo; ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
