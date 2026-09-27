<?php
$product = $data['product'] ?? null;
$parentProduct = $data['parent_product'] ?? null;
$variations = $data['variations'] ?? [];
$storeName = $data['store_name'] ?? '';

if (!$product): ?>
  <div class="ahub">
    <div class="audit-batch-empty">
      <p>Producto no encontrado.</p>
    </div>
  </div>
<?php return; endif;

$statusMap = [
    'active'       => ['label' => 'Activo',   'cls' => 'status-active'],
    'paused'       => ['label' => 'Pausado',  'cls' => 'status-paused'],
    'closed'       => ['label' => 'Cerrado',  'cls' => 'status-closed'],
    'under_review' => ['label' => 'Revisión', 'cls' => 'status-review'],
];
$st = $statusMap[$product->status] ?? ['label' => $product->status ?: '-', 'cls' => 'status-other'];

$price = number_format((float) $product->price, 0, ',', '.');
$currency = $product->currency_id ?? 'CLP';
$syncedAt = $product->synced_at ?? '';
$timeAgo = '';
if ($syncedAt) {
    $date = new DateTime($syncedAt, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
    $now = new DateTime();
    $diff = $now->diff($date);
    if ($diff->days === 0 && $diff->h === 0 && $diff->i === 0) {
        $timeAgo = 'Ahora';
    } elseif ($diff->days === 0 && $diff->h === 0) {
        $timeAgo = 'Hace ' . $diff->i . ' min';
    } elseif ($diff->days === 0) {
        $timeAgo = 'Hace ' . $diff->h . ' horas';
    } elseif ($diff->days === 1) {
        $timeAgo = 'Ayer';
    } elseif ($diff->days < 7) {
        $timeAgo = 'Hace ' . $diff->days . ' días';
    } else {
        $timeAgo = $date->format('d/m/Y');
    }
}

$listingTypeLabels = [
    'gold_special' => 'Gold Special',
    'gold_premium' => 'Gold Premium',
    'gold_pro'     => 'Gold Pro',
    'silver'       => 'Silver',
    'free'         => 'Gratuita',
];
$listingLabel = $listingTypeLabels[$product->listing_type_id] ?? $product->listing_type_id;

$conditionLabels = [
    'new'           => 'Nuevo',
    'used'          => 'Usado',
    'not_specified' => 'Sin especificar',
];
$conditionLabel = $conditionLabels[$product->condition] ?? $product->condition;

// ── Extra data ──
$extraData = null;
$shippingFree = false;
$warranty = '';
$pictureHd = '';
$soldSinceSync = 0;
if (!empty($product->extra_data)) {
    $extraData = json_decode($product->extra_data);
    if ($extraData) {
        $shippingFree = !empty($extraData->shipping->free_shipping);
        $warranty = htmlspecialchars($extraData->warranty ?? '');
        $pictureHd = !empty($extraData->picture_hd) ? str_replace('http://', 'https://', htmlspecialchars($extraData->picture_hd)) : '';
        $soldSinceSync = (int) ($extraData->sold_since_sync ?? 0);
    }
}

// ── Hero image: prefer HD, fallback thumbnail, fallback icon ──
$imgUrl = $pictureHd ?: (!empty($product->thumbnail) ? str_replace('http://', 'https://', htmlspecialchars($product->thumbnail)) : '');

// ── Discount ──
$originalPrice = !empty($product->original_price) ? (float) $product->original_price : null;
$hasDiscount = $originalPrice && $originalPrice > (float) $product->price;
$discountPercent = $hasDiscount ? round((1 - (float) $product->price / $originalPrice) * 100) : 0;
$originalFormatted = $hasDiscount ? number_format($originalPrice, 0, ',', '.') : '';

// ── Start time ──
$startTimeAgo = '';
if (!empty($product->start_time)) {
    try {
        $stDate = new DateTime($product->start_time, new DateTimeZone('UTC'));
        $stDate->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $now = new DateTime();
        $diff = $now->diff($stDate);
        if ($diff->days === 0 && $diff->h === 0 && $diff->i === 0) {
            $startTimeAgo = 'Ahora';
        } elseif ($diff->days === 0 && $diff->h === 0) {
            $startTimeAgo = 'Hace ' . $diff->i . ' min';
        } elseif ($diff->days === 0) {
            $startTimeAgo = 'Hace ' . $diff->h . ' horas';
        } elseif ($diff->days === 1) {
            $startTimeAgo = 'Ayer';
        } elseif ($diff->days < 30) {
            $startTimeAgo = 'Hace ' . $diff->days . ' días';
        } elseif ($diff->days < 365) {
            $months = floor($diff->days / 30);
            $startTimeAgo = 'Hace ' . $months . ' mes' . ($months > 1 ? 'es' : '');
        } else {
            $years = floor($diff->days / 365);
            $startTimeAgo = 'Hace ' . $years . ' año' . ($years > 1 ? 's' : '');
        }
    } catch (\Exception $e) {}
}

$soldQty = (int) ($product->sold_quantity ?? 0);
$mlItemId = htmlspecialchars($product->ml_item_id);
$sku = htmlspecialchars($product->sku ?? '');
$title = htmlspecialchars($product->title);
$categoryLabel = !empty($product->category_name) ? htmlspecialchars($product->category_name) : (!empty($product->category_id) ? htmlspecialchars($product->category_id) : '-');
?>
<div class="ahub">
  <div class="audit-breadcrumb">
    <a href="<?php echo URLROOT; ?>/connections/products" class="audit-back">
      <?php echo icon('arrow-left', ['size' => 14]); ?>
      Productos
    </a>
  </div>

  <!-- Hero -->
  <div class="pd-hero">
    <div class="pd-hero-img" onclick="<?php echo $imgUrl ? "openImagePreview('{$imgUrl}')" : ''; ?>" style="cursor:<?php echo $imgUrl ? 'zoom-in' : 'default' ?>;position:relative;">
      <?php if ($imgUrl): ?>
        <img src="<?php echo $imgUrl; ?>" alt="" onerror="this.style.display='none';this.parentNode.querySelector('.pd-hero-fallback').style.display='flex';this.parentNode.querySelector('.pd-img-zoom-btn').style.display='none';this.parentNode.onclick='';this.parentNode.style.cursor='default'">
        <div class="pd-hero-fallback" style="display:none"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="3" x2="21" y2="21"/></svg></div>
        <div class="pd-img-zoom-btn"><?php echo icon('maximize', ['size' => 13]); ?></div>
      <?php else: ?>
        <div class="pd-hero-fallback"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="3" x2="21" y2="21"/></svg></div>
      <?php endif; ?>
    </div>
    <div class="pd-hero-body">
      <div class="pd-hero-title">
        <?php echo $title; ?>
        <?php if (!empty($product->parent_ml_item_id)): ?>
          <span class="text-xs" style="background:color-mix(in srgb,var(--accent) 12%,transparent);color:var(--accent);padding:0.15rem 0.5rem;border-radius:999px;font-weight:500;">Variación</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="pd-hero-actions">
      <?php if (!empty($product->permalink)): ?>
        <a href="<?php echo htmlspecialchars($product->permalink); ?>" target="_blank" class="pd-ml-btn">
          <?php echo icon('external-link', ['size' => 13]); ?>
          Ver en ML
        </a>
      <?php endif; ?>
      <?php if ($timeAgo): ?>
        <span class="text-xs text-muted">Sincronizado: <?php echo $timeAgo; ?></span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Métricas -->
  <div class="pd-section">
    <div class="pd-metrics">
      <div class="pd-metric pd-metric-sold">
        <div class="pd-metric-icon"><?php echo icon('shopping-bag', ['size' => 16]); ?></div>
        <div class="pd-metric-body">
          <?php if ($soldSinceSync > 0): ?>
            <span class="pd-metric-value">+<?php echo number_format($soldSinceSync, 0, ',', '.'); ?></span>
            <span class="pd-metric-label">Desde último sync</span>
          <?php elseif ($soldQty > 0): ?>
            <span class="pd-metric-value"><?php echo number_format($soldQty, 0, ',', '.'); ?></span>
            <span class="pd-metric-label">Vendidos totales</span>
          <?php else: ?>
            <span class="pd-metric-value" style="font-weight:500;color:var(--text-secondary);">Sin ventas</span>
            <span class="pd-metric-label">—</span>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($hasDiscount): ?>
      <div class="pd-metric pd-metric-discount">
        <div class="pd-metric-icon"><?php echo icon('banknote', ['size' => 16]); ?></div>
        <div class="pd-metric-body">
          <span class="pd-metric-value">-<?php echo $discountPercent; ?>%</span>
          <span class="pd-metric-label"><?php echo $currency; ?> $<?php echo $originalFormatted; ?></span>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($shippingFree): ?>
      <div class="pd-metric pd-metric-shipping">
        <div class="pd-metric-icon"><?php echo icon('truck', ['size' => 16]); ?></div>
        <div class="pd-metric-body">
          <span class="pd-metric-value">Gratis</span>
          <span class="pd-metric-label">Envío</span>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($startTimeAgo): ?>
      <div class="pd-metric pd-metric-time">
        <div class="pd-metric-icon"><?php echo icon('calendar', ['size' => 16]); ?></div>
        <div class="pd-metric-body">
          <span class="pd-metric-value"><?php echo $startTimeAgo; ?></span>
          <span class="pd-metric-label">Publicado</span>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Información del producto -->
  <div class="pd-section">
    <div class="pd-section-title">Información del producto</div>
    <div class="pd-info">
      <div class="pd-info-row">
        <span class="pd-info-label">SKU</span>
        <span class="pd-info-value"><?php echo $sku ?: '-'; ?></span>
      </div>
      <div class="pd-info-row">
        <span class="pd-info-label">ID ML</span>
        <span class="pd-info-value"><?php echo $mlItemId; ?></span>
      </div>
      <div class="pd-info-row">
        <span class="pd-info-label">Categoría</span>
        <span class="pd-info-value"><?php echo $categoryLabel; ?></span>
      </div>
      <div class="pd-info-row">
        <span class="pd-info-label">Tipo</span>
        <span class="pd-info-value"><?php echo $listingLabel; ?></span>
      </div>
      <div class="pd-info-row">
        <span class="pd-info-label">Precio</span>
        <span class="pd-info-value"><?php echo $currency; ?> $<?php echo $price; ?><?php if ($hasDiscount): ?> <span class="pd-discount-badge">-<?php echo $discountPercent; ?>%</span><?php endif; ?></span>
      </div>
      <div class="pd-info-row">
        <span class="pd-info-label">Stock</span>
        <span class="pd-info-value"><?php echo (int) $product->available_quantity; ?> uds</span>
      </div>
      <div class="pd-info-row">
        <span class="pd-info-label">Condición</span>
        <span class="pd-info-value"><?php echo $conditionLabel; ?></span>
      </div>
      <div class="pd-info-row">
        <span class="pd-info-label">Estado</span>
        <span class="pd-info-value"><span class="status-badge <?php echo $st['cls']; ?>"><?php echo $st['label']; ?></span></span>
      </div>
      <?php if ($warranty): ?>
      <div class="pd-info-row">
        <span class="pd-info-label">Garantía</span>
        <span class="pd-info-value"><?php echo $warranty; ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Parent product -->
  <?php if ($parentProduct): ?>
    <div class="pd-section">
      <div class="pd-parent-card">
        <span class="pd-parent-label">Producto padre:</span>
        <a href="<?php echo URLROOT; ?>/connections/productDetail/<?php echo htmlspecialchars($parentProduct->ml_item_id); ?>">
          <?php echo htmlspecialchars($parentProduct->title); ?>
        </a>
        <?php echo icon('chevron-right', ['size' => 13, 'class' => 'text-muted ml-auto flex-shrink-0']); ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Variaciones -->
  <?php if (!empty($variations)): ?>
    <div class="pd-section">
      <div class="pd-section-title">Variaciones (<?php echo count($variations); ?>)</div>
      <div style="border:1px solid var(--border);border-radius:10px;overflow:hidden;">
        <table class="pd-var-table">
          <thead>
            <tr>
              <th style="width:38px;"></th>
              <th>Producto</th>
              <th>SKU</th>
              <th class="pd-right">Precio</th>
              <th class="pd-right">Stock</th>
              <th class="pd-center">Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($variations as $v):
              $vStatus = $statusMap[$v->status] ?? ['label' => $v->status ?: '-', 'cls' => 'status-other'];
              $vPrice = number_format((float) $v->price, 0, ',', '.');
              $vImg = !empty($v->thumbnail) ? str_replace('http://', 'https://', htmlspecialchars($v->thumbnail)) : '';
            ?>
              <tr onclick="window.location.href='<?php echo URLROOT; ?>/connections/productDetail/<?php echo htmlspecialchars($v->ml_item_id); ?>'">
                <td>
                  <div class="pd-var-img">
                    <?php if ($vImg): ?>
                      <img src="<?php echo $vImg; ?>" alt="" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none';this.parentNode.querySelector('.pd-var-fallback').style.display='flex'">
                      <span class="pd-var-fallback" style="display:none"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="3" x2="21" y2="21"/></svg></span>
                    <?php else: ?>
                      <span class="pd-var-fallback"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="3" x2="21" y2="21"/></svg></span>
                    <?php endif; ?>
                  </div>
                </td>
                <td><span class="pd-var-title"><?php echo htmlspecialchars($v->title); ?></span></td>
                <td class="pd-var-sku"><?php echo $v->sku ? htmlspecialchars($v->sku) : '-'; ?></td>
                <td class="pd-var-price"><?php echo $currency; ?> $<?php echo $vPrice; ?></td>
                <td class="pd-var-stock"><?php echo (int) $v->available_quantity; ?> uds</td>
                <td class="pd-var-status"><span class="status-badge <?php echo $vStatus['cls']; ?>" style="font-size:0.68rem;"><?php echo $vStatus['label']; ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Image preview overlay -->
<div id="pdImageOverlay" onclick="closeImagePreview()" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.7);cursor:zoom-out;align-items:center;justify-content:center;opacity:0;transition:opacity 0.2s ease;">
  <button onclick="event.stopPropagation();closeImagePreview()" style="position:absolute;top:0.75rem;right:0.75rem;width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background 0.15s;line-height:1;" onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">✕</button>
  <img id="pdImagePreview" src="" alt="" style="max-width:90vw;max-height:85vh;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,0.4);transform:scale(0.92);transition:transform 0.25s ease;">
</div>

<style>
#pdImageOverlay.show { display:flex !important; opacity:1 !important; }
#pdImageOverlay.show img { transform:scale(1) !important; }
.pd-hero-img .pd-img-zoom-btn {
  position: absolute;
  bottom: 6px;
  right: 6px;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: rgba(0,0,0,0.45);
  color: rgba(255,255,255,0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
  transition: background 0.15s;
}
.pd-hero-img:hover .pd-img-zoom-btn { background: rgba(0,0,0,0.65); }
</style>

<script>
function openImagePreview(url) {
  if (!url) return;
  var overlay = document.getElementById('pdImageOverlay');
  var img = document.getElementById('pdImagePreview');
  img.src = url;
  overlay.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  requestAnimationFrame(function() {
    overlay.classList.add('show');
  });
}
function closeImagePreview() {
  var overlay = document.getElementById('pdImageOverlay');
  overlay.classList.remove('show');
  setTimeout(function() {
    overlay.style.display = 'none';
    document.body.style.overflow = '';
  }, 200);
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeImagePreview();
});
</script>
