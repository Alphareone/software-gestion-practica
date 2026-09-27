<?php
$connections = $data['connections'] ?? [];
$cencoConnections = $data['cenco_connections'] ?? [];
$productMetrics = $data['product_metrics'] ?? ['total' => 0, 'active' => 0, 'low_stock' => 0, 'stock_ranges' => []];
$recentActivity = $data['recent_activity'] ?? [];
$recentBatches = $data['recent_batches'] ?? [];
$recentAudits = $data['recent_audits'] ?? [];
$tableCounts = $data['table_counts'] ?? [];
$connectedNow = $data['connected_now'] ?? 0;
$todayActivity = $data['today_activity'] ?? 0;
$topAction = $data['top_action'] ?? null;
$totalUsers = $data['total_users'] ?? 0;
$latestUsers = $data['latest_users'] ?? [];
$isAdmin = !empty($data['is_admin']);
$selectedStoreId = $data['selected_store_id'] ?? 'all';
$selectedStoreName = $data['selected_store_name'] ?? '';

$totalConnectionsCount = count($connections) + count($cencoConnections);
$filteredConnCount = $selectedStoreId !== 'all' ? 1 : $totalConnectionsCount;

$mlAllOk = true;
$mlHasIssues = false;
$latestSyncTs = 0;
$latestSyncStore = '';
foreach ($connections as $c) {
    if (empty($c->token_ok)) { $mlAllOk = false; $mlHasIssues = true; }
    $ts = !empty($c->last_sync_at) ? strtotime($c->last_sync_at . ' UTC') : 0;
    if ($ts > $latestSyncTs) { $latestSyncTs = $ts; $latestSyncStore = $c->store_name ?? ''; }
}
foreach ($cencoConnections as $c) {
    if (empty($c->token_ok)) { $mlAllOk = false; $mlHasIssues = true; }
    $ts = !empty($c->last_sync_at) ? strtotime($c->last_sync_at . ' UTC') : 0;
    if ($ts > $latestSyncTs) { $latestSyncTs = $ts; $latestSyncStore = $c->store_name ?? ''; }
}
$totalDbRecords = array_sum($tableCounts);
$dbTableCount = count($tableCounts);
?>

<!-- ════════ Sección 1: Visión general ════════ -->
<div class="flex items-center gap-2 mb-5 mt-1">
  <span class="w-1.5 h-5 rounded-full bg-accent"></span>
  <h2 class="text-sm font-semibold text-primary uppercase tracking-wider">Visión general</h2>
  <div class="ml-auto">
    <select onchange="window.location.href='?store='+this.value" class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2.5 py-1.5 focus:outline-none focus:border-accent cursor-pointer">
      <option value="all" <?php echo $selectedStoreId === 'all' ? 'selected' : ''; ?>>Todas las tiendas</option>
      <optgroup label="Mercado Libre">
        <?php foreach ($connections as $c): ?>
          <option value="ml_<?php echo (int)$c->id; ?>" <?php echo ($selectedStoreId === 'ml_' . $c->id || $selectedStoreId === (string)$c->id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c->store_name ?? 'Sin nombre'); ?></option>
        <?php endforeach; ?>
      </optgroup>
      <optgroup label="Cencosud">
        <?php foreach ($cencoConnections as $c): ?>
          <option value="cenco_<?php echo (int)$c->id; ?>" <?php echo $selectedStoreId === 'cenco_' . $c->id ? 'selected' : ''; ?>><?php echo htmlspecialchars($c->store_name ?? 'Sin nombre'); ?></option>
        <?php endforeach; ?>
      </optgroup>
    </select>
  </div>
</div>


<!-- KPIs -->
<div class="grid grid-cols-2 sm:grid-cols-3 <?php echo $isAdmin ? 'lg:grid-cols-5' : 'lg:grid-cols-4'; ?> gap-4 mb-6" id="kpiRow">
  <div class="card flex flex-col gap-2 relative overflow-hidden">
    <div class="absolute top-0 right-0 w-16 h-16 bg-accent/5 rounded-bl-full"></div>
    <span class="text-muted text-xs font-medium tracking-wide uppercase flex items-center gap-1.5"><?php echo icon('globe', ['size' => 14]); ?> Tiendas</span>
    <span class="text-2xl font-bold text-primary tabular-nums kpi-value" data-count="<?php echo $filteredConnCount; ?>">0</span>
    <span class="text-xs text-muted">conectadas</span>
  </div>
  <div class="card flex flex-col gap-2 relative overflow-hidden">
    <div class="absolute top-0 right-0 w-16 h-16 bg-accent/5 rounded-bl-full"></div>
    <span class="text-muted text-xs font-medium tracking-wide uppercase flex items-center gap-1.5"><?php echo icon('package', ['size' => 14]); ?> Productos</span>
    <span class="text-2xl font-bold text-primary tabular-nums kpi-value" data-count="<?php echo $productMetrics['total']; ?>">0</span>
    <span class="text-xs text-muted">en total</span>
  </div>
  <div class="card flex flex-col gap-2 relative overflow-hidden">
    <div class="absolute top-0 right-0 w-16 h-16 bg-success/5 rounded-bl-full"></div>
    <span class="text-muted text-xs font-medium tracking-wide uppercase flex items-center gap-1.5"><?php echo icon('badge-check', ['size' => 14]); ?> Activos</span>
    <span class="text-2xl font-bold text-success tabular-nums kpi-value" data-count="<?php echo $productMetrics['active']; ?>">0</span>
    <span class="text-xs text-muted">publicados en ML</span>
  </div>
  <div class="card flex flex-col gap-2 relative overflow-hidden">
    <div class="absolute top-0 right-0 w-16 h-16 bg-danger/5 rounded-bl-full"></div>
    <span class="text-muted text-xs font-medium tracking-wide uppercase flex items-center gap-1.5"><?php echo icon('triangle-alert', ['size' => 14]); ?> Stock bajo</span>
    <span class="text-2xl font-bold text-danger tabular-nums kpi-value" data-count="<?php echo $productMetrics['low_stock']; ?>">0</span>
    <span class="text-xs text-muted">productos por reponer</span>
  </div>
  <?php if ($isAdmin): ?>
  <div class="card flex flex-col gap-2 relative overflow-hidden">
    <div class="absolute top-0 right-0 w-16 h-16 bg-primary/5 rounded-bl-full"></div>
    <span class="text-muted text-xs font-medium tracking-wide uppercase flex items-center gap-1.5"><?php echo icon('clock-3', ['size' => 14]); ?> Actividad hoy</span>
    <span class="text-2xl font-bold text-primary tabular-nums kpi-value" data-count="<?php echo $todayActivity; ?>">0</span>
    <span class="text-xs text-muted">eventos registrados</span>
  </div>
  <?php endif; ?>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
  <div class="card">
    <div class="flex items-center gap-2 mb-3">
      <?php echo icon('chart-bar', ['size' => 16]); ?>
      <h3 class="text-sm font-semibold text-primary">Distribución de stock</h3>
    </div>
    <div id="stockChart" style="height:220px;">
      <div class="flex items-center justify-center h-full text-muted text-sm">Cargando gráfico...</div>
    </div>
  </div>
  <div class="card">
    <div class="flex items-center gap-2 mb-3">
      <?php echo icon('layers', ['size' => 16]); ?>
      <h3 class="text-sm font-semibold text-primary">Productos: activos vs inactivos</h3>
    </div>
    <div id="donutChart" style="height:220px;">
      <div class="flex items-center justify-center h-full text-muted text-sm">Cargando gráfico...</div>
    </div>
  </div>
</div>

<!-- ════════ Sección 2: Tiendas y operaciones ════════ -->
<div class="flex items-center gap-2 mb-5 mt-8">
  <span class="w-1.5 h-5 rounded-full bg-accent"></span>
  <h2 class="text-sm font-semibold text-primary uppercase tracking-wider">Tiendas y operaciones</h2>
</div>

<!-- Estado de tiendas -->
<div class="card mb-6">
  <div class="flex items-center gap-2 mb-4">
    <?php echo icon('globe', ['size' => 16]); ?>
    <h3 class="text-sm font-semibold text-primary">Estado de tiendas</h3>
    <?php if (count($connections) === 0 && count($cencoConnections) === 0): ?>
      <span class="text-xs text-muted ml-auto">Sin tiendas conectadas</span>
    <?php endif; ?>
  </div>
  <?php if (count($connections) > 0 || count($cencoConnections) > 0): ?>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-muted text-xs uppercase tracking-wide border-b border-border">
          <th class="text-left py-2 pr-3 font-medium">Tienda</th>
          <th class="text-left py-2 pr-3 font-medium">Token</th>
          <th class="text-left py-2 pr-3 font-medium">Sincronización</th>
          <th class="text-left py-2 pr-3 font-medium">Productos</th>
          <th class="text-left py-2 pr-3 font-medium">Stock bajo</th>
          <th class="text-right py-2 font-medium">Última sync</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($connections as $c): ?>
          <?php
            if ($selectedStoreId !== 'all' && $selectedStoreId !== 'ml_' . $c->id && $selectedStoreId !== (string)$c->id) continue;
            $status = $c->sync_status ?? 'idle';
            $ls = (int)($c->cache_low_stock ?? 0);
            $ts = !empty($c->last_sync_at) ? strtotime($c->last_sync_at . ' UTC') : 0;
          ?>
          <tr class="border-b border-border/50 hover:bg-surface/30 transition-colors">
            <td class="py-2.5 pr-3">
              <span class="font-medium text-primary"><?php echo htmlspecialchars($c->store_name ?: 'Sin nombre'); ?></span>
              <span class="text-muted text-xs ml-1"><?php echo htmlspecialchars($c->country ?? ''); ?></span>
            </td>
            <td class="py-2.5 pr-3">
              <?php if (!empty($c->token_ok)): ?>
                <span class="text-success text-xs font-medium">OK</span>
              <?php else: ?>
                <span class="text-danger text-xs font-medium">Expirado</span>
              <?php endif; ?>
            </td>
            <td class="py-2.5 pr-3">
              <?php if ($status === 'idle'): ?>
                <span class="text-success text-xs font-medium">Inactivo</span>
              <?php elseif ($status === 'syncing'): ?>
                <span class="text-accent text-xs font-medium">Sincronizando</span>
                <?php if (($c->total_products_sync ?? 0) > 0): ?>
                  <span class="text-muted text-xs"> (<?php echo (int)($c->synced_products ?? 0); ?>/<?php echo (int)$c->total_products_sync; ?>)</span>
                <?php endif; ?>
              <?php elseif ($status === 'error'): ?>
                <span class="text-danger text-xs font-medium">Error</span>
              <?php else: ?>
                <span class="text-muted text-xs font-medium"><?php echo htmlspecialchars($status); ?></span>
              <?php endif; ?>
            </td>
            <td class="py-2.5 pr-3">
              <span class="text-primary font-medium tabular-nums"><?php echo (int)($c->cache_total ?? 0); ?></span>
              <span class="text-muted text-xs"> / <?php echo (int)($c->cache_active ?? 0); ?> activos</span>
            </td>
            <td class="py-2.5 pr-3">
              <span class="<?php echo $ls > 0 ? 'text-danger' : 'text-muted'; ?> font-medium tabular-nums"><?php echo $ls; ?></span>
            </td>
            <td class="py-2.5 text-left">
              <?php if ($ts > 0): ?>
                <span class="text-primary text-xs font-medium" title="<?php echo htmlspecialchars($c->last_sync_at); ?>">
                  <?php
                    $diff = time() - $ts;
                    $mins = floor($diff / 60);
                    $hours = floor($diff / 3600);
                    $days = floor($diff / 86400);
                    if ($mins < 60) echo 'Hace ' . $mins . ' min';
                    elseif ($hours < 24) echo 'Hace ' . $hours . 'h';
                    elseif ($days < 30) echo 'Hace ' . $days . ' día' . ($days !== 1 ? 's' : '');
                    elseif ($days < 365) echo 'Hace ' . floor($days / 30) . ' mes' . (floor($days / 30) !== 1 ? 'es' : '');
                    else echo 'Hace ' . floor($days / 365) . ' año' . (floor($days / 365) !== 1 ? 's' : '');
                  ?>
                </span>
              <?php else: ?>
                <span class="text-muted text-xs">&mdash;</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php foreach ($cencoConnections as $c): ?>
          <?php
            if ($selectedStoreId !== 'all' && $selectedStoreId !== 'cenco_' . $c->id) continue;
            $status = $c->sync_status ?? 'idle';
            $ls = (int)($c->cache_low_stock ?? 0);
            $ts = !empty($c->last_sync_at) ? strtotime($c->last_sync_at . ' UTC') : 0;
          ?>
          <tr class="border-b border-border/50 hover:bg-surface/30 transition-colors">
            <td class="py-2.5 pr-3">
              <span class="font-medium text-primary"><?php echo htmlspecialchars($c->store_name ?: 'Sin nombre'); ?></span>
              <span class="text-xs px-1.5 py-0.5 rounded bg-accent/10 text-accent font-medium ml-1">Cencosud</span>
            </td>
            <td class="py-2.5 pr-3">
              <?php if (!empty($c->token_ok)): ?>
                <span class="text-success text-xs font-medium">OK</span>
              <?php else: ?>
                <span class="text-danger text-xs font-medium">Expirado</span>
              <?php endif; ?>
            </td>
            <td class="py-2.5 pr-3">
              <?php if ($status === 'idle'): ?>
                <span class="text-success text-xs font-medium">Inactivo</span>
              <?php elseif ($status === 'syncing'): ?>
                <span class="text-accent text-xs font-medium">Sincronizando</span>
                <?php if (($c->total_products_sync ?? 0) > 0): ?>
                  <span class="text-muted text-xs"> (<?php echo (int)($c->synced_products ?? 0); ?>/<?php echo (int)$c->total_products_sync; ?>)</span>
                <?php endif; ?>
              <?php elseif ($status === 'error'): ?>
                <span class="text-danger text-xs font-medium">Error</span>
              <?php else: ?>
                <span class="text-muted text-xs font-medium"><?php echo htmlspecialchars($status); ?></span>
              <?php endif; ?>
            </td>
            <td class="py-2.5 pr-3">
              <span class="text-primary font-medium tabular-nums"><?php echo (int)($c->cache_total ?? 0); ?></span>
              <span class="text-muted text-xs"> / <?php echo (int)($c->cache_active ?? 0); ?> activos</span>
            </td>
            <td class="py-2.5 pr-3">
              <span class="<?php echo $ls > 0 ? 'text-danger' : 'text-muted'; ?> font-medium tabular-nums"><?php echo $ls; ?></span>
            </td>
            <td class="py-2.5 text-left">
              <?php if ($ts > 0): ?>
                <span class="text-primary text-xs font-medium" title="<?php echo htmlspecialchars($c->last_sync_at); ?>">
                  <?php
                    $diff = time() - $ts;
                    $mins = floor($diff / 60);
                    $hours = floor($diff / 3600);
                    $days = floor($diff / 86400);
                    if ($mins < 60) echo 'Hace ' . $mins . ' min';
                    elseif ($hours < 24) echo 'Hace ' . $hours . 'h';
                    elseif ($days < 30) echo 'Hace ' . $days . ' día' . ($days !== 1 ? 's' : '');
                    elseif ($days < 365) echo 'Hace ' . floor($days / 30) . ' mes' . (floor($days / 30) !== 1 ? 'es' : '');
                    else echo 'Hace ' . floor($days / 365) . ' año' . (floor($days / 365) !== 1 ? 's' : '');
                  ?>
                </span>
              <?php else: ?>
                <span class="text-muted text-xs">&mdash;</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

  </div>
  <?php else: ?>
    <div class="text-center py-8 text-muted text-sm">
      <p>No tienes tiendas conectadas. Contacta a un administrador para agregar una.</p>
    </div>
  <?php endif; ?>
</div>

<!-- Auditorias + Precios -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
  <div class="card">
    <div class="flex items-center gap-2 mb-4">
      <?php echo icon('search-check', ['size' => 16]); ?>
      <h3 class="text-sm font-semibold text-primary">Últimas auditorías</h3>
      <a href="<?php echo URLROOT; ?>/connections/auditHub" class="ml-auto text-xs text-accent hover:underline">Ir &rarr;</a>
    </div>
    <?php if (count($recentAudits) > 0): ?>
      <div class="space-y-0.5">
        <?php foreach ($recentAudits as $aud): ?>
          <?php $mismatches = (int)($aud->mismatches ?? 0); $notFound = (int)($aud->not_found ?? 0); $total = (int)($aud->total_skus ?? 0); $correct = $total - $mismatches - $notFound; ?>
          <?php $audIcon = ($aud->audit_type ?? '') === 'prices' ? 'dollar-sign' : (($aud->audit_type ?? '') === 'stock' ? 'box' : 'file-text'); ?>
          <a href="<?php echo URLROOT; ?>/connections/auditBatchDetail/<?php echo htmlspecialchars($aud->batch_id); ?>" class="flex items-center gap-3 py-2 border-b border-border/30 last:border-0 hover:bg-surface/30 transition-colors no-underline">
            <span class="text-muted shrink-0"><?php echo icon($audIcon, ['size' => 14]); ?></span>
            <div class="flex items-center justify-between flex-1 min-w-0 gap-2">
              <div class="min-w-0">
                <p class="text-sm text-primary truncate">
                  <span class="capitalize font-medium"><?php echo htmlspecialchars($aud->audit_type ?? ''); ?></span>
                  <span class="text-muted">&middot; <?php echo htmlspecialchars($aud->store_name ?? '&mdash;'); ?></span>
                </p>
                <p class="text-xs text-muted">
                  <?php echo $total; ?> SKUs
                  &middot; <span class="text-success"><?php echo $correct; ?> correctos</span>
                  <?php if ($mismatches > 0): ?>
                    &middot; <span class="text-danger"><?php echo $mismatches; ?> discrepancias</span>
                  <?php endif; ?>
                  <?php if ($notFound > 0): ?>
                    &middot; <span class="text-accent"><?php echo $notFound; ?> no encontrados</span>
                  <?php endif; ?>
                </p>
              </div>
              <span class="text-xs text-muted shrink-0">
                <?php if (!empty($aud->audit_date)): $adiff = time() - strtotime($aud->audit_date . ' UTC'); $amins = floor($adiff / 60); $ahours = floor($adiff / 3600); $adays = floor($adiff / 86400); if ($amins < 60) echo 'Hace ' . $amins . ' min'; elseif ($ahours < 24) echo 'Hace ' . $ahours . 'h'; elseif ($adays < 30) echo 'Hace ' . $adays . ' día' . ($adays !== 1 ? 's' : ''); elseif ($adays < 365) echo 'Hace ' . floor($adays / 30) . ' mes' . (floor($adays / 30) !== 1 ? 'es' : ''); else echo 'Hace ' . floor($adays / 365) . ' año' . (floor($adays / 365) !== 1 ? 's' : ''); endif; ?>
              </span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-sm text-muted text-center py-6"><?php echo $selectedStoreId !== 'all' ? 'Esta tienda no ha tenido auditorías' : 'No han habido auditorías'; ?></p>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="flex items-center gap-2 mb-4">
      <?php echo icon('dollar-sign', ['size' => 16]); ?>
      <h3 class="text-sm font-semibold text-primary">Cambios de precio</h3>
      <a href="<?php echo URLROOT; ?>/connections/priceHistory" class="ml-auto text-xs text-accent hover:underline">Ir &rarr;</a>
    </div>
    <?php if (count($recentBatches) > 0): ?>
      <div class="space-y-0.5">
        <?php foreach ($recentBatches as $batch): ?>
          <?php $errs = (int)($batch['error_count'] ?? 0); ?>
          <div class="flex items-center gap-3 py-2 border-b border-border/30 last:border-0">
            <span class="text-muted shrink-0"><?php echo icon('banknote', ['size' => 14]); ?></span>
            <div class="flex items-center justify-between flex-1 min-w-0 gap-2">
              <div class="min-w-0">
                <p class="text-sm text-primary truncate font-medium">Lote <?php echo htmlspecialchars($batch['batch_id_short'] ?? ''); ?></p>
                <p class="text-xs text-muted">
                  <?php echo (int)($batch['total'] ?? 0); ?> items
                  <?php if ($errs > 0): ?>
                    &middot; <span class="text-danger"><?php echo $errs; ?> errores</span>
                  <?php else: ?>
                    &middot; <span class="text-success">100% OK</span>
                  <?php endif; ?>
                </p>
              </div>
              <span class="text-xs text-muted shrink-0"><?php echo (int)($batch['success_rate'] ?? 0); ?>%</span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-sm text-muted text-center py-6"><?php echo $selectedStoreId !== 'all' ? 'Esta tienda no ha tenido cambios de precio' : 'No han habido cambios de precio'; ?></p>
    <?php endif; ?>
  </div>
</div>

<!-- ════════ Sección 3: Administración (solo admin) ════════ -->
<?php if ($isAdmin): ?>
<div class="flex items-center gap-2 mb-5 mt-8">
  <span class="w-1.5 h-5 rounded-full bg-accent"></span>
  <h2 class="text-sm font-semibold text-primary uppercase tracking-wider">Administración</h2>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
  <div class="card">
    <div class="flex items-center gap-2 mb-4">
      <?php echo icon('clock-3', ['size' => 16]); ?>
      <h3 class="text-sm font-semibold text-primary">Actividad reciente</h3>
      <?php if ($topAction): ?>
        <span class="text-xs text-muted ml-auto">Frecuente: <?php echo htmlspecialchars($topAction); ?></span>
      <?php endif; ?>
    </div>
    <?php if (count($recentActivity) > 0): ?>
      <div class="space-y-0.5">
        <?php foreach ($recentActivity as $act): ?>
          <?php
            $actTs = !empty($act->created_at) ? strtotime($act->created_at . ' UTC') : 0;
          ?>
          <div class="flex items-start gap-3 py-2 border-b border-border/30 last:border-0">
            <div class="flex items-start justify-between flex-1 min-w-0 gap-2">
              <div class="min-w-0">
                <p class="text-sm text-primary truncate"><?php echo htmlspecialchars($act->description ?? ''); ?></p>
                <p class="text-xs text-muted">
                  <span class="capitalize"><?php echo htmlspecialchars($act->action ?? ''); ?></span>
                  <?php if (!empty($act->username)): ?>
                    &middot; <?php echo htmlspecialchars($act->username); ?>
                  <?php endif; ?>
                </p>
              </div>
              <span class="text-xs text-muted shrink-0 mt-0.5">
                <?php
                  if ($actTs > 0) {
                    $diff = time() - $actTs;
                    $mins = floor($diff / 60); $hours = floor($diff / 3600); $days = floor($diff / 86400);
                    if ($mins < 60) echo 'Hace ' . $mins . ' min';
                    elseif ($hours < 24) echo 'Hace ' . $hours . 'h';
                    elseif ($days < 30) echo 'Hace ' . $days . ' día' . ($days !== 1 ? 's' : '');
                    elseif ($days < 365) echo 'Hace ' . floor($days / 30) . ' mes' . (floor($days / 30) !== 1 ? 'es' : '');
                    else echo 'Hace ' . floor($days / 365) . ' año' . (floor($days / 365) !== 1 ? 's' : '');
                  }
                ?>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <a href="<?php echo URLROOT; ?>/admin/activityLog" class="inline-block mt-3 text-xs text-accent hover:underline">Ver toda la actividad &rarr;</a>
    <?php else: ?>
      <p class="text-sm text-muted text-center py-6">Sin actividad reciente</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="flex items-center gap-2 mb-4">
      <?php echo icon('users', ['size' => 16]); ?>
      <h3 class="text-sm font-semibold text-primary">Usuarios</h3>
      <span class="ml-auto text-xs text-muted">Total: <?php echo $totalUsers; ?></span>
    </div>
    <?php if (count($latestUsers) > 0): ?>
      <div class="space-y-0.5">
        <?php foreach ($latestUsers as $u): ?>
          <div class="flex items-center gap-3 py-2 border-b border-border/30 last:border-0">
            <span class="text-muted shrink-0"><?php echo icon('circle-user', ['size' => 16]); ?></span>
            <div class="flex-1 min-w-0">
              <p class="text-sm text-primary truncate font-medium"><?php echo htmlspecialchars($u->username ?? ''); ?></p>
              <p class="text-xs text-muted truncate"><?php echo htmlspecialchars($u->email ?? ''); ?></p>
            </div>
            <span class="text-xs text-muted shrink-0">
              <?php echo !empty($u->created_at) ? date('d/m/Y', strtotime($u->created_at . ' UTC')) : ''; ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
      <a href="<?php echo URLROOT; ?>/users" class="inline-block mt-3 text-xs text-accent hover:underline">Gestionar usuarios &rarr;</a>
    <?php else: ?>
      <p class="text-sm text-muted text-center py-6">Sin usuarios registrados</p>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-6">
  <div class="flex items-center gap-2 mb-4">
    <?php echo icon('shield-check', ['size' => 16]); ?>
    <h3 class="text-sm font-semibold text-primary">Seguridad</h3>
    <span class="ml-auto text-xs text-muted">Resumen</span>
  </div>
  <div class="w-full overflow-x-auto">
    <table class="w-full text-sm">
      <tbody>
        <tr class="border-b border-border/30">
          <td class="text-primary py-2.5 pl-0">Usuarios registrados</td>
          <td class="text-right font-semibold text-primary tabular-nums py-2.5 pr-0"><?php echo $totalUsers; ?></td>
        </tr>
        <tr class="border-b border-border/30">
          <td class="text-primary py-2.5 pl-0">Actividad hoy</td>
          <td class="text-right font-semibold text-primary tabular-nums py-2.5 pr-0"><?php echo $todayActivity; ?></td>
        </tr>
        <?php if ($topAction): ?>
        <tr class="border-b border-border/30">
          <td class="text-primary py-2.5 pl-0">Acci&oacute;n m&aacute;s frecuente</td>
          <td class="text-right font-semibold text-primary py-2.5 pr-0"><?php echo htmlspecialchars($topAction); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
          <td class="text-primary py-2.5 pl-0">Usuarios conectados ahora</td>
          <td class="text-right font-semibold text-success tabular-nums py-2.5 pr-0"><?php echo $connectedNow; ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ════════ Sección 4: Sistema ════════ -->
<div class="flex items-center gap-2 mb-5 mt-8">
  <span class="w-1.5 h-5 rounded-full bg-accent"></span>
  <h2 class="text-sm font-semibold text-primary uppercase tracking-wider">Sistema</h2>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
  <!-- MercadoLibre -->
  <div class="card card-system flex items-start gap-2 relative overflow-hidden">
    <div class="absolute top-0 right-0 w-20 h-20 bg-accent/5 rounded-bl-full"></div>
    <span class="w-9 h-9 rounded-xl bg-accent/10 text-accent flex items-center justify-center shrink-0"><?php echo icon('shopping-bag', ['size' => 18]); ?></span>
    <div class="flex-1 min-w-0">
      <span class="text-sm font-semibold text-primary">MercadoLibre</span>
      <p class="text-xs text-muted"><?php echo $mlAllOk ? 'Operativo' : 'Con problemas'; ?></p>
      <p class="text-xs text-muted mt-0.5"><?php echo count($connections); ?> conexion<?php echo count($connections) !== 1 ? 'es' : ''; ?> activa<?php echo count($connections) !== 1 ? 's' : ''; ?></p>
    </div>
  </div>

  <!-- Sincronización -->
  <div class="card card-system flex items-start gap-2 relative overflow-hidden">
    <div class="absolute top-0 right-0 w-20 h-20 bg-accent/5 rounded-bl-full"></div>
    <span class="w-9 h-9 rounded-xl bg-accent/10 text-accent flex items-center justify-center shrink-0"><?php echo icon('refresh-cw', ['size' => 18]); ?></span>
    <div class="flex-1 min-w-0">
      <span class="text-sm font-semibold text-primary">Sincronización</span>
      <?php if ($latestSyncTs > 0): ?>
        <?php $syncDiff = time() - $latestSyncTs; ?>
        <p class="text-xs text-muted">
          <?php
            if ($syncDiff < 60) echo 'Ahora mismo';
            elseif ($syncDiff < 3600) echo 'Hace ' . floor($syncDiff / 60) . ' min';
            elseif ($syncDiff < 86400) echo 'Hace ' . floor($syncDiff / 3600) . 'h';
            else echo 'Hace ' . floor($syncDiff / 86400) . ' días';
          ?>
        </p>
        <p class="text-xs text-muted mt-0.5 truncate"><?php echo htmlspecialchars($latestSyncStore); ?></p>
      <?php else: ?>
        <p class="text-xs text-muted">Sin sincronizar</p>
        <p class="text-xs text-muted mt-0.5">&mdash;</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Base de datos -->
  <div class="card card-system flex items-start gap-2 relative overflow-hidden">
    <div class="absolute top-0 right-0 w-20 h-20 bg-accent/5 rounded-bl-full"></div>
    <span class="w-9 h-9 rounded-xl bg-accent/10 text-accent flex items-center justify-center shrink-0"><?php echo icon('database', ['size' => 18]); ?></span>
    <div class="flex-1 min-w-0">
      <span class="text-sm font-semibold text-primary">Base de datos</span>
      <p class="text-xs text-muted"><?php echo number_format($totalDbRecords); ?> registros</p>
      <p class="text-xs text-muted mt-0.5"><?php echo $dbTableCount; ?> tablas</p>
    </div>
  </div>
</div>

<div class="card mb-6" id="sysDetails">
  <div class="flex items-center gap-2 mb-4 text-sm font-medium text-primary">
    <span>Detalles técnicos</span>
  </div>
  <div class="border-t border-border pt-4">
    <?php if (count($tableCounts) > 0): ?>
      <?php $maxCount = max($tableCounts) ?: 1; ?>
      <div class="space-y-2">
        <?php foreach ($tableCounts as $table => $count): ?>
          <?php $pct = round(($count / $maxCount) * 100); ?>
          <div class="flex items-center gap-3">
            <span class="text-xs text-muted w-36 shrink-0 truncate" title="<?php echo htmlspecialchars($table); ?>"><?php echo htmlspecialchars($table); ?></span>
            <div class="flex-1 h-2 rounded-full bg-surface/50 overflow-hidden">
              <div class="h-full rounded-full bg-accent/40 transition-all" style="width:<?php echo $pct; ?>%"></div>
            </div>
            <span class="text-xs font-semibold text-primary tabular-nums w-16 text-right shrink-0"><?php echo number_format($count); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-sm text-muted text-center py-4">No se pudieron obtener los conteos</p>
    <?php endif; ?>
  </div>
</div>


<style>
#kpiRow .card { min-height: 120px; }
@media (min-width: 640px) { #kpiRow .card { min-height: 140px; } }
</style>
<script>
window.DASHBOARD_DATA = {
  stockRanges: <?php echo json_encode($productMetrics['stock_ranges'] ?? []); ?>,
  active: <?php echo (int)($productMetrics['active'] ?? 0); ?>,
  total: <?php echo (int)($productMetrics['total'] ?? 0); ?>
};
</script>
<script src="<?php echo URLROOT; ?>/assets/js/apexcharts.min.js"></script>
<script src="<?php echo URLROOT; ?>/assets/js/dashboard.js"></script>
