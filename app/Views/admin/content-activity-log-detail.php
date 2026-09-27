<?php
$log = $data['log'] ?? null;
$actionLabel = $data['action_label'] ?? '';
$timeAgo = $data['time_ago'] ?? '';
$formattedDate = $data['formatted_date'] ?? '';

if (!$log): ?>
  <div class="ahub">
    <div class="audit-batch-empty">
      <p>Registro no encontrado.</p>
    </div>
  </div>
<?php return; endif;

// Determine dot color based on action type
$action = strtolower($log->action ?? '');
$dotColor = '#94a3b8';
if (str_contains($action, 'failed') || str_contains($action, 'delete') ||
    str_contains($action, 'error') || str_contains($action, 'purge'))
    $dotColor = '#ef4444';
elseif (str_contains($action, 'export') || str_contains($action, 'audit') ||
        str_contains($action, 'disconnect') || str_contains($action, 'settings'))
    $dotColor = '#f59e0b';
elseif (str_contains($action, 'create') || str_contains($action, 'update') ||
        str_contains($action, 'rename') || str_contains($action, 'reactivate') ||
        str_contains($action, 'price') || str_contains($action, 'password') ||
        str_contains($action, 'changed') || str_contains($action, 'upload'))
    $dotColor = '#3b82f6';
elseif (str_contains($action, 'login') || str_contains($action, 'logout') ||
        str_contains($action, 'select') || str_contains($action, 'success'))
    $dotColor = '#10b981';

$username = htmlspecialchars($log->username ?? 'Sistema');
$description = htmlspecialchars($log->description ?? '');
$ip = htmlspecialchars($log->ip_address ?? '-');
$logId = (int) ($log->id ?? 0);
?>
<div class="ahub">
  <div class="audit-breadcrumb">
    <a href="<?php echo URLROOT; ?>/admin/activityLog" class="audit-back">
      <?php echo icon('arrow-left', ['size' => 14]); ?>
      Registro de actividad
    </a>
  </div>

  <div class="audit-batch-header-info">
    <div class="audit-card-icon" style="background:color-mix(in srgb, <?php echo $dotColor; ?> 15%, transparent);color:<?php echo $dotColor; ?>;">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
      </svg>
    </div>
    <div>
      <h3><?php echo htmlspecialchars($actionLabel); ?></h3>
      <p>
        <?php echo $username; ?>
        · <?php echo $formattedDate; ?>
        · <?php echo $timeAgo; ?>
      </p>
    </div>
  </div>

  <div class="pd-section" style="margin-top:1.25rem;">
    <div class="pd-info">
      <div class="pd-info-row">
        <span class="pd-info-label">Usuario</span>
        <span class="pd-info-value"><?php echo $username; ?></span>
      </div>
      <div class="pd-info-row">
        <span class="pd-info-label">Acción</span>
        <span class="pd-info-value"><?php echo htmlspecialchars($actionLabel); ?></span>
      </div>
      <div class="pd-info-row">
        <span class="pd-info-label">Descripción</span>
        <span class="pd-info-value"><?php echo $description ?: '-'; ?></span>
      </div>
      <div class="pd-info-row">
        <span class="pd-info-label">Dirección IP</span>
        <span class="pd-info-value" style="font-family:monospace;font-size:0.78rem;"><?php echo $ip; ?></span>
      </div>
      <div class="pd-info-row">
        <span class="pd-info-label">Fecha</span>
        <span class="pd-info-value"><?php echo $formattedDate; ?></span>
      </div>
      <div class="pd-info-row">
        <span class="pd-info-label">N° de registro</span>
        <span class="pd-info-value" style="font-size:0.78rem;color:var(--text-secondary);"><?php echo $logId; ?></span>
      </div>
    </div>
  </div>
</div>
