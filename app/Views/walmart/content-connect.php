<?php
$edit = $data['edit'] ?? null;
$isEdit = !empty($edit);
?>

<div class="settings-page" style="max-width:640px;">

  <?php if (!empty($data['dry_run'])): ?>
    <div class="d-badge exp" style="display:inline-block;margin-bottom:1rem;">Modo DRY-RUN activo: no se contacta la API real de Walmart</div>
  <?php endif; ?>

  <div class="ut-card" style="padding:1.5rem;">
    <h3 style="margin-bottom:0.35rem;"><?php echo $isEdit ? 'Editar conexión Walmart' : 'Conectar tienda Walmart Chile'; ?></h3>
    <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:1.25rem;">
      Las credenciales (Client ID y Client Secret) se generan en el
      <a href="https://developer.walmart.com/cl-marketplace" target="_blank" rel="noopener">Developer Portal de Walmart</a>
      con la misma cuenta del Seller Center. El secret se guarda cifrado y se
      verifica contra Walmart antes de guardar.
    </p>

    <form method="post" action="<?php echo URLROOT; ?>/walmart/saveConnection" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
      <?php if ($isEdit): ?>
        <input type="hidden" name="edit_id" value="<?php echo (int) $edit->id; ?>">
      <?php endif; ?>

      <div class="form-group" style="margin-bottom:1rem;">
        <label for="store_name" style="display:block;font-size:0.8rem;font-weight:500;margin-bottom:0.3rem;">Nombre de la tienda *</label>
        <input type="text" id="store_name" name="store_name" maxlength="100" required class="rename-input" style="width:100%;"
               value="<?php echo htmlspecialchars($edit->store_name ?? ''); ?>" placeholder="Ej: Tienda Lider principal">
      </div>

      <div class="form-group" style="margin-bottom:1rem;">
        <label for="client_id" style="display:block;font-size:0.8rem;font-weight:500;margin-bottom:0.3rem;">Client ID *</label>
        <input type="text" id="client_id" name="client_id" maxlength="191" required class="rename-input" style="width:100%;"
               value="<?php echo htmlspecialchars($edit->client_id ?? ''); ?>">
      </div>

      <div class="form-group" style="margin-bottom:1rem;">
        <label for="client_secret" style="display:block;font-size:0.8rem;font-weight:500;margin-bottom:0.3rem;">
          Client Secret <?php echo $isEdit ? '(dejar vacío para conservar el actual)' : '*'; ?>
        </label>
        <input type="password" id="client_secret" name="client_secret" class="rename-input" style="width:100%;"
               <?php echo $isEdit ? '' : 'required'; ?> autocomplete="new-password">
      </div>

      <div class="form-group" style="margin-bottom:1rem;">
        <label for="channel_type" style="display:block;font-size:0.8rem;font-weight:500;margin-bottom:0.3rem;">Channel Type (WM_CONSUMER.CHANNEL.TYPE)</label>
        <input type="text" id="channel_type" name="channel_type" maxlength="100" class="rename-input" style="width:100%;"
               value="<?php echo htmlspecialchars($edit->channel_type ?? ''); ?>" placeholder="Opcional — entregado por Walmart en el onboarding">
      </div>

      <div class="form-group" style="margin-bottom:1.5rem;">
        <label for="api_base_url" style="display:block;font-size:0.8rem;font-weight:500;margin-bottom:0.3rem;">URL base de la API</label>
        <input type="text" id="api_base_url" name="api_base_url" maxlength="191" class="rename-input" style="width:100%;"
               value="<?php echo htmlspecialchars($edit->api_base_url ?? $data['default_base_url']); ?>">
        <span style="font-size:0.72rem;color:var(--text-muted);">Producción: <?php echo htmlspecialchars($data['default_base_url']); ?></span>
      </div>

      <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
        <a href="<?php echo URLROOT; ?>/walmart/adminstores" class="btn btn-cancel">Cancelar</a>
        <button type="submit" class="ut-btn ut-btn-primary">
          <?php echo icon('store', ['size' => 16]); ?>
          <?php echo $isEdit ? 'Guardar y verificar' : 'Conectar y verificar'; ?>
        </button>
      </div>
    </form>
  </div>
</div>
