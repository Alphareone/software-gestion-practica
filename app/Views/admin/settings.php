
<?php require_once __DIR__ . '/../../Helpers/IconHelper.php'; ?>

<!-- PANEL: Sistema -->
<div class="settings-panel active" id="panel-sistema">
  <form method="POST" action="<?php echo URLROOT; ?>/admin/saveSettings">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <div class="setting-row">
      <div class="setting-info">
        <span class="setting-label">Retención de logs</span>
        <span class="setting-hint">Meses que se conservan los registros de cambios de precio antes de purgarse automáticamente.</span>
      </div>
      <div class="setting-control">
        <input type="number" name="logs_retention_months" class="s-input"
               value="<?php echo htmlspecialchars($data['settings']['logs_retention_months']->setting_value ?? '6'); ?>"
               min="1" max="60" required>
        <span class="s-unit">meses</span>
      </div>
    </div>
    <div class="settings-footer" style="margin-top:1rem;">
      <button type="submit" class="btn-save-settings">
        <?php echo icon('download', ['size' => 15]); ?>
        Guardar cambios
      </button>
    </div>
  </form>
</div>

<!-- PANEL: API MercadoLibre -->
<div class="settings-panel" id="panel-api">
  <form method="POST" action="<?php echo URLROOT; ?>/admin/saveSettings">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <div class="setting-row">
      <div class="setting-info">
        <span class="setting-label">Reintentos máximos</span>
        <span class="setting-hint">Número de reintentos al recibir error 429 (rate limit) de la API.</span>
      </div>
      <div class="setting-control">
        <input type="number" name="batch_retry_max" class="s-input"
               value="<?php echo htmlspecialchars($data['settings']['batch_retry_max']->setting_value ?? '3'); ?>"
               min="0" max="10" required>
        <span class="s-unit">intentos</span>
      </div>
    </div>
    <div class="setting-row">
      <div class="setting-info">
        <span class="setting-label">Intervalo entre requests</span>
        <span class="setting-hint">Milisegundos de espera entre cada actualización de precio en MercadoLibre.</span>
      </div>
      <div class="setting-control">
        <input type="number" name="batch_rate_limit_ms" class="s-input"
               value="<?php echo htmlspecialchars($data['settings']['batch_rate_limit_ms']->setting_value ?? '200'); ?>"
               min="50" max="5000" step="50" required>
        <span class="s-unit">ms</span>
      </div>
    </div>
    <div class="setting-row">
      <div class="setting-info">
        <span class="setting-label">Items por iteración</span>
        <span class="setting-hint">Cantidad de items procesados por cada ciclo de actualización de precios.</span>
      </div>
      <div class="setting-control">
        <input type="number" name="price_batch_chunk_size" class="s-input"
               value="<?php echo htmlspecialchars($data['settings']['price_batch_chunk_size']->setting_value ?? '2'); ?>"
               min="1" max="10" required>
        <span class="s-unit">items</span>
      </div>
    </div>
  </form>
</div>

<!-- PANEL: Limpiar registros -->
<div class="settings-panel" id="panel-limpiar">
  <h4 style="font-size:0.95rem;margin:0 0 0.5rem;color:var(--text-secondary);">
    🗑️ Purga de registros
  </h4>
  <p style="font-size:0.82rem;color:var(--text-secondary);margin:0 0 1rem;">
    Elimina registros de forma permanente. Los cambios no se pueden deshacer.
  </p>
  <div class="purge-grid">
    <!-- Logs de cambios de precio -->
    <div class="purge-card">
      <div class="purge-card-header">
        <span class="purge-card-title">📋 Logs de cambios de precio</span>
        <span class="purge-badge <?php echo ($data['counts']['price_change_logs'] ?? 0) > 1000 ? 'danger' : ''; ?>">
          <?php echo number_format($data['counts']['price_change_logs'] ?? 0); ?> registros
        </span>
      </div>
      <form method="POST" action="<?php echo URLROOT; ?>/admin/purgeLogs" class="purge-form"
            onsubmit="return confirm('¿Eliminar ' + this.querySelector('[name=limit]').value + ' registros de price_change_logs? Esta acción no se puede deshacer.');">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="table" value="price_change_logs">
        <label>Eliminar últimos:</label>
        <input type="number" name="limit" min="1"
               max="<?php echo max(1, $data['counts']['price_change_logs'] ?? 1); ?>"
               value="<?php echo min(100, $data['counts']['price_change_logs'] ?? 0); ?>" required>
        <button type="submit" class="btn-purge">🗑️ Purgar</button>
      </form>
    </div>

    <!-- Intentos de login fallidos -->
    <div class="purge-card">
      <div class="purge-card-header">
        <span class="purge-card-title">🔐 Intentos de login fallidos</span>
        <span class="purge-badge"><?php echo number_format($data['counts']['login_attempts'] ?? 0); ?> registros</span>
      </div>
      <form method="POST" action="<?php echo URLROOT; ?>/admin/purgeLogs" class="purge-form"
            onsubmit="return confirm('¿Eliminar ' + this.querySelector('[name=limit]').value + ' registros de login_attempts? Esta acción no se puede deshacer.');">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="table" value="login_attempts">
        <label>Eliminar últimos:</label>
        <input type="number" name="limit" min="1"
               max="<?php echo max(1, $data['counts']['login_attempts'] ?? 1); ?>"
               value="<?php echo min(100, $data['counts']['login_attempts'] ?? 0); ?>" required>
        <button type="submit" class="btn-purge">🗑️ Purgar</button>
      </form>
    </div>

    <!-- Batches pendientes -->
    <div class="purge-card">
      <div class="purge-card-header">
        <span class="purge-card-title">⏳ Batches de precios pendientes</span>
        <span class="purge-badge <?php echo ($data['counts']['price_batch_pending'] ?? 0) > 0 ? 'danger' : ''; ?>">
          <?php echo number_format($data['counts']['price_batch_pending'] ?? 0); ?> registros
        </span>
      </div>
      <form method="POST" action="<?php echo URLROOT; ?>/admin/purgeLogs" class="purge-form"
            onsubmit="return confirm('¿Eliminar ' + this.querySelector('[name=limit]').value + ' registros de price_batch_pending? Esta acción no se puede deshacer.');">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="table" value="price_batch_pending">
        <label>Eliminar últimos:</label>
        <input type="number" name="limit" min="1"
               max="<?php echo max(1, $data['counts']['price_batch_pending'] ?? 1); ?>"
               value="<?php echo max(1, $data['counts']['price_batch_pending'] ?? 0); ?>" required>
        <button type="submit" class="btn-purge">🗑️ Purgar</button>
      </form>
    </div>
  </div>
</div>