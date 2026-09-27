<?php
$recentAudits = $data['recent_audits'] ?? [];
$auditTypeLabels = [
    'price' => ['label' => 'Precios', 'color' => '#3483fa', 'bg' => 'rgba(52,131,250,0.15)', 'icon' => 'dollar-sign'],
    'stock' => ['label' => 'Stock', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.15)', 'icon' => 'package'],
];
?>

<div class="ahub">

  <div class="flex items-center gap-2 mb-5 mt-1">
    <span class="w-1.5 h-5 rounded-full bg-accent"></span>
    <h2 class="text-sm font-semibold text-primary uppercase tracking-wider">Herramientas</h2>
  </div>

  <div class="ahub-grid">
    <a href="<?php echo URLROOT; ?>/walmart/auditPrices" class="ahub-tile">
      <div class="ahub-tile-icon phub-stat-icon is-ml-blue">
        <?php echo icon('dollar-sign', ['size' => 20]); ?>
      </div>
      <div class="ahub-tile-text">
        <span class="ahub-tile-label">Precios</span>
        <span class="ahub-tile-desc">Contrasta precios actuales vs. tu Excel</span>
      </div>
    </a>

    <a href="<?php echo URLROOT; ?>/walmart/auditStock" class="ahub-tile">
      <div class="ahub-tile-icon phub-stat-icon is-success">
        <?php echo icon('package', ['size' => 20]); ?>
      </div>
      <div class="ahub-tile-text">
        <span class="ahub-tile-label">Stock</span>
        <span class="ahub-tile-desc">Verifica el inventario publicado en Walmart</span>
      </div>
    </a>

    <a href="<?php echo URLROOT; ?>/walmart/auditHistory" class="ahub-tile">
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

<div class="ahub-activity" style="margin-top:1.5rem;">
  <div class="ahub-activity-header">
    <h3>Exportar catálogo actual</h3>
  </div>
  <p class="text-xs text-muted mb-3">Descarga precio o stock tal como están hoy en la caché, sin comparar contra ningún archivo. Se regenera automáticamente después de cada sincronización.</p>
  <?php $exportConnections = $data['connections'] ?? []; ?>
  <?php if (!empty($exportConnections)): ?>
  <div class="flex items-center gap-3 flex-wrap">
    <select id="exportStoreSelect" class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2.5 py-1.5 focus:outline-none focus:border-accent cursor-pointer">
      <?php foreach ($exportConnections as $c): ?>
        <option value="<?php echo (int)$c->id; ?>"><?php echo htmlspecialchars($c->store_name ?: 'Sin nombre'); ?></option>
      <?php endforeach; ?>
    </select>
    <button type="button" class="btn-secondary text-xs" id="exportPriceBtn">
      <?php echo icon('download', ['size' => 14]); ?>
      Excel de precios
    </button>
    <button type="button" class="btn-secondary text-xs" id="exportStockBtn">
      <?php echo icon('download', ['size' => 14]); ?>
      Excel de stock
    </button>
  </div>
  <script>
  (function(){
    function download(type) {
      var storeId = document.getElementById('exportStoreSelect').value;
      window.location.href = '<?php echo URLROOT; ?>/walmart/exportCatalog/' + type + '?store=' + encodeURIComponent(storeId);
    }
    document.getElementById('exportPriceBtn').addEventListener('click', function(){ download('price'); });
    document.getElementById('exportStockBtn').addEventListener('click', function(){ download('stock'); });
  })();
  </script>
  <?php else: ?>
    <p class="text-xs text-muted">No hay tiendas Walmart conectadas todavía.</p>
  <?php endif; ?>
</div>

<div class="ahub-activity" style="margin-top:1.5rem;">
  <div class="ahub-activity-header">
    <h3>Actividad reciente</h3>
    <a href="<?php echo URLROOT; ?>/walmart/auditHistory" class="ahub-activity-link">Ver todo <?php echo icon('arrow-right', ['size' => 14]); ?></a>
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
        <a href="<?php echo URLROOT; ?>/walmart/auditBatchDetail/<?php echo htmlspecialchars($ra->batch_id); ?>" class="ahub-activity-item">
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
