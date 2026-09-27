<?php
$edit = $data['edit'] ?? null;
$isEdit = !empty($edit);
?>

<div class="settings-page" style="max-width:640px;">

  <?php if (!empty($data['dry_run'])): ?>
    <div class="d-badge exp" style="display:inline-block;margin-bottom:1rem;">Modo DRY-RUN activo: no se contacta la API real de Cencosud</div>
  <?php endif; ?>

  <div class="ut-card" style="padding:1.5rem;">
    <h3 style="margin-bottom:0.35rem;"><?php echo $isEdit ? 'Editar conexión Cencosud' : 'Conectar tienda Cencosud Chile'; ?></h3>
    <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:1.25rem;">
      Las credenciales (Client ID y Client Secret) se generan en el
      Developer Portal de Cencosud.
      El secret se guarda cifrado y se verifica contra la API de Cencosud antes de guardar.
    </p>

    <form method="post" action="<?php echo URLROOT; ?>/cencosud/saveConnection" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
      <?php if ($isEdit): ?>
        <input type="hidden" name="edit_id" value="<?php echo (int) $edit->id; ?>">
      <?php endif; ?>

      <div class="form-group" style="margin-bottom:1rem;">
        <label for="store_name" style="display:block;font-size:0.8rem;font-weight:500;margin-bottom:0.3rem;">Nombre de la tienda *</label>
        <input type="text" id="store_name" name="store_name" maxlength="100" required class="rename-input" style="width:100%;"
               value="<?php echo htmlspecialchars($edit->store_name ?? ''); ?>" placeholder="Ej: Tienda Paris principal">
      </div>

      <div class="form-group" style="margin-bottom:1rem;">
        <label for="api_key" style="display:block;font-size:0.8rem;font-weight:500;margin-bottom:0.3rem;">
          API Key <?php echo $isEdit ? '(dejar vacío para conservar la actual)' : '*'; ?>
        </label>
        <input type="password" id="api_key" name="api_key" class="rename-input" style="width:100%;"
               <?php echo $isEdit ? '' : 'required'; ?> autocomplete="new-password"
               placeholder="00000000-0000-0000-0000-000000000000">
        <span style="font-size:0.72rem;color:var(--text-muted);">
          Se obtiene en el panel de Cencosud, en Mi Cuenta &rarr; Integraciones. Se guarda cifrada.
        </span>
      </div>

      <div class="form-group" style="margin-bottom:1.5rem;">
        <label for="api_base_url" style="display:block;font-size:0.8rem;font-weight:500;margin-bottom:0.3rem;">URL base de la API</label>
        <input type="text" id="api_base_url" name="api_base_url" maxlength="191" class="rename-input" style="width:100%;"
               value="<?php echo htmlspecialchars($edit->api_base_url ?? $data['default_base_url']); ?>">
        <span style="font-size:0.72rem;color:var(--text-muted);">Producción: <?php echo htmlspecialchars($data['default_base_url']); ?></span>
      </div>

      <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
        <a href="<?php echo URLROOT; ?>/cencosud/adminstores" class="btn btn-cancel">Cancelar</a>
        <button type="submit" class="ut-btn ut-btn-primary">
          <?php echo icon('store', ['size' => 16]); ?>
          <?php echo $isEdit ? 'Guardar y verificar' : 'Conectar y verificar'; ?>
        </button>
      </div>
    </form>
  </div>
</div>
