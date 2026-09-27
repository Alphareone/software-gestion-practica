<?php
$product = $data['product'] ?? null;
$siblings = $data['siblings'] ?? [];
$family = $data['family'] ?? null;
$storeName = $data['store_name'] ?? '';
$connectionId = $data['connection_id'] ?? 0;

if (!$product): ?>
  <div class="ahub">
    <div class="audit-batch-empty">
      <p>Producto no encontrado.</p>
    </div>
  </div>
<?php return; endif;

$statusMap = [
    'PUBLISHED'   => ['label' => 'Publicado',   'cls' => 'status-active'],
    'UNPUBLISHED' => ['label' => 'Despublicado', 'cls' => 'status-paused'],
    'STAGE'       => ['label' => 'En revisión',  'cls' => 'status-review'],
    'SYSTEM'      => ['label' => 'Rechazado',    'cls' => 'status-closed'],
];
$st = $statusMap[$product->published_status] ?? ['label' => $product->published_status ?: '-', 'cls' => 'status-other'];

$lifecycleLabels = ['ACTIVE' => 'Activo', 'ARCHIVED' => 'Archivado', 'RETIRED' => 'Retirado'];
$lifecycleLabel = $lifecycleLabels[$product->lifecycle_status] ?? ($product->lifecycle_status ?: '-');

$price = number_format((float) $product->price, 0, ',', '.');
$currency = $product->currency ?? 'CLP';

$syncedAt = $product->synced_at ?? '';
$timeAgo = '';
if ($syncedAt) {
    $date = new DateTime($syncedAt, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
    $now = new DateTime();
    $diff = $now->diff($date);
    if ($diff->days === 0 && $diff->h === 0 && $diff->i === 0) $timeAgo = 'Ahora';
    elseif ($diff->days === 0 && $diff->h === 0) $timeAgo = 'Hace ' . $diff->i . ' min';
    elseif ($diff->days === 0) $timeAgo = 'Hace ' . $diff->h . ' horas';
    elseif ($diff->days === 1) $timeAgo = 'Ayer';
    elseif ($diff->days < 7) $timeAgo = 'Hace ' . $diff->days . ' días';
    else $timeAgo = $date->format('d/m/Y');
}

// ── Extra data (mart, shelf, etc.) ──
$extraData = null;
$mart = '';
if (!empty($product->extra_data)) {
    $extraData = json_decode($product->extra_data);
    if ($extraData) {
        $mart = $extraData->mart ?? '';
    }
}

$sku = htmlspecialchars($product->sku);
$wpid = htmlspecialchars($product->wpid ?? '');
$title = htmlspecialchars($product->title);
$productType = htmlspecialchars($product->product_type_es ?? $product->product_type ?? '-');
$stock = $product->available_quantity;
$lowStock = $stock !== null && (int) $stock <= 5;

$typeLabels = ['individual' => 'Individual', 'pack' => 'Pack', 'tripack' => 'Tripack'];
?>
<div class="ahub">
  <div class="audit-breadcrumb">
    <a href="<?php echo URLROOT; ?>/walmart/products" class="audit-back">
      <?php echo icon('arrow-left', ['size' => 14]); ?>
      Productos Walmart
    </a>
  </div>

  <!-- Hero -->
  <div class="pd-hero">
    <div class="pd-hero-img" style="position:relative;">
      <div class="pd-hero-fallback"><?php echo icon('package', ['size' => 36]); ?></div>
    </div>
    <div class="pd-hero-body">
      <div class="pd-hero-title"><?php echo $title; ?></div>
    </div>
    <div class="pd-hero-actions">
      <?php if ($storeName): ?>
        <span class="text-xs text-muted"><?php echo htmlspecialchars($storeName); ?></span>
      <?php endif; ?>
      <?php if ($timeAgo): ?>
        <span class="text-xs text-muted">Sincronizado: <?php echo $timeAgo; ?></span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Información del producto -->
  <div class="pd-section">
    <div class="pd-section-title">Información del producto</div>
    <div class="pd-info">
      <div class="pd-info-row">
        <span class="pd-info-label">SKU</span>
        <span class="pd-info-value"><?php echo $sku; ?></span>
      </div>
      <div class="pd-info-row">
        <span class="pd-info-label">WPID</span>
        <span class="pd-info-value"><?php echo $wpid ?: '-'; ?></span>
      </div>
      <?php if (!empty($product->gtin)): ?>
      <div class="pd-info-row">
        <span class="pd-info-label">GTIN</span>
        <span class="pd-info-value"><?php echo htmlspecialchars($product->gtin); ?></span>
      </div>
      <?php endif; ?>
      <div class="pd-info-row">
        <span class="pd-info-label">Tipo de producto</span>
        <span class="pd-info-value"><?php echo $productType; ?></span>
      </div>
      <div class="pd-info-row">
        <span class="pd-info-label">Precio</span>
        <span class="pd-info-value"><?php echo $currency; ?> $<?php echo $price; ?></span>
      </div>
      <div class="pd-info-row">
        <span class="pd-info-label">Stock</span>
        <span class="pd-info-value" style="<?php echo $lowStock ? 'color:var(--warning,#d97706);font-weight:600;' : ''; ?>">
          <?php echo $stock === null ? 'Sin consultar' : (int) $stock . ' unidades'; ?>
        </span>
      </div>
      <div class="pd-info-row">
        <span class="pd-info-label">Estado de publicación</span>
        <span class="pd-info-value"><span class="status-badge <?php echo $st['cls']; ?>"><?php echo $st['label']; ?></span></span>
      </div>
      <div class="pd-info-row">
        <span class="pd-info-label">Ciclo de vida</span>
        <span class="pd-info-value"><?php echo htmlspecialchars($lifecycleLabel); ?></span>
      </div>
      <?php if ($mart): ?>
      <div class="pd-info-row">
        <span class="pd-info-label">Mart</span>
        <span class="pd-info-value"><?php echo htmlspecialchars($mart); ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Familia del SKU (individual / pack / tripack) -->
  <?php if ($family): ?>
    <div class="pd-section">
      <div class="pd-section-title">Familia del producto (<?php echo count($family['members']); ?>)</div>
      <p class="text-xs text-muted" style="margin-top:-0.4rem;margin-bottom:0.6rem;">Mismas piezas vendidas como individual, pack o tripack — código base <?php echo htmlspecialchars($family['base_sku']); ?>.</p>
      <div style="border:1px solid var(--border);border-radius:10px;overflow:hidden;">
        <table class="pd-var-table">
          <thead><tr><th>SKU</th><th>Presentación</th></tr></thead>
          <tbody>
            <?php foreach ($family['members'] as $m): ?>
              <tr <?php echo $m->sku !== $sku ? 'onclick="window.location.href=\'' . URLROOT . '/walmart/productDetail/' . $connectionId . '/' . urlencode($m->sku) . '\'"' : ''; ?> style="<?php echo $m->sku === $sku ? 'opacity:0.6;' : ''; ?>">
                <td class="pd-var-sku"><?php echo htmlspecialchars($m->sku); ?><?php echo $m->sku === $sku ? ' <span class="text-xs text-muted">(este)</span>' : ''; ?></td>
                <td><?php echo $typeLabels[$m->variant_type] ?? $m->variant_type; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <!-- Otras tallas/colores del mismo producto -->
  <?php if (!empty($siblings)): ?>
    <div class="pd-section">
      <div class="pd-section-title">Otras publicaciones de este producto (<?php echo count($siblings); ?>)</div>
      <div style="border:1px solid var(--border);border-radius:10px;overflow:hidden;">
        <table class="pd-var-table">
          <thead>
            <tr>
              <th>SKU</th>
              <th class="pd-right">Precio</th>
              <th class="pd-right">Stock</th>
              <th class="pd-center">Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($siblings as $sib):
              $sStatus = $statusMap[$sib->published_status] ?? ['label' => $sib->published_status ?: '-', 'cls' => 'status-other'];
              $sPrice = number_format((float) $sib->price, 0, ',', '.');
            ?>
              <tr onclick="window.location.href='<?php echo URLROOT; ?>/walmart/productDetail/<?php echo $connectionId; ?>/<?php echo urlencode($sib->sku); ?>'">
                <td class="pd-var-sku"><?php echo htmlspecialchars($sib->sku); ?></td>
                <td class="pd-var-price"><?php echo $currency; ?> $<?php echo $sPrice; ?></td>
                <td class="pd-var-stock"><?php echo $sib->available_quantity === null ? '—' : (int) $sib->available_quantity . ' unidades'; ?></td>
                <td class="pd-var-status"><span class="status-badge <?php echo $sStatus['cls']; ?>" style="font-size:0.68rem;"><?php echo $sStatus['label']; ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
