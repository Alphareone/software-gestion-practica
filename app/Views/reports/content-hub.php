<div class="ahub">

  <div class="flex items-center gap-2 mb-5 mt-1">
    <span class="w-1.5 h-5 rounded-full bg-accent"></span>
    <h2 class="text-sm font-semibold text-primary uppercase tracking-wider">Reportes</h2>
  </div>

  <div class="ahub-grid">
    <a href="<?php echo URLROOT; ?>/reports/inventory" class="ahub-tile">
      <div class="ahub-tile-icon phub-stat-icon is-success">
        <?php echo icon('boxes', ['size' => 20]); ?>
      </div>
      <div class="ahub-tile-text">
        <span class="ahub-tile-label">Inventario</span>
        <span class="ahub-tile-desc">Estado del catálogo por tienda, ML y Walmart</span>
      </div>
    </a>

    <a href="<?php echo URLROOT; ?>/reports/audits" class="ahub-tile">
      <div class="ahub-tile-icon phub-stat-icon is-ml-blue">
        <?php echo icon('shield-check', ['size' => 20]); ?>
      </div>
      <div class="ahub-tile-text">
        <span class="ahub-tile-label">Auditorías</span>
        <span class="ahub-tile-desc">Histórico y SKUs con diferencias recurrentes</span>
      </div>
    </a>

    <a href="<?php echo URLROOT; ?>/reports/prices" class="ahub-tile">
      <div class="ahub-tile-icon phub-stat-icon is-muted">
        <?php echo icon('dollar-sign', ['size' => 20]); ?>
      </div>
      <div class="ahub-tile-text">
        <span class="ahub-tile-label">Precios</span>
        <span class="ahub-tile-desc">Snapshot de precios actuales, ambos canales</span>
      </div>
    </a>
    <a href="<?php echo URLROOT; ?>/reports/sales" class="ahub-tile">
      <div class="ahub-tile-icon phub-stat-icon is-ml-blue">
        <?php echo icon('trending-up', ['size' => 20]); ?>
      </div>
      <div class="ahub-tile-text">
        <span class="ahub-tile-label">Ventas</span>
        <span class="ahub-tile-desc">Ventas Walmart por tienda, producto y día</span>
      </div>
    </a>
  </div>

</div>
