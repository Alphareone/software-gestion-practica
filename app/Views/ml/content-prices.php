<?php $activeStore = $data['active_store'] ?? null; ?>
<?php $results = $data['results'] ?? null; ?>
<?php $lastBatchId = $data['last_batch_id'] ?? null; ?>
<?php $validationErrors = $data['validation_errors'] ?? []; ?>
<?php $incompleteBatch = $data['incomplete_batch'] ?? null; ?>

<?php if (!empty($data['price_disabled'])): ?>
<div class="phub-banner-disabled">
  <?php echo icon('ban', ['size' => 18]); ?>
  <span>Las modificaciones de precios están deshabilitadas.</span>
</div>
<?php endif; ?>

<?php if (!$activeStore): ?>
  <div class="empty-state">
    <?php echo icon('monitor', ['size' => 48]); ?>
    <h3>Ninguna tienda seleccionada</h3>
    <p>Selecciona una tienda activa desde Conexiones primero.</p>
    <a href="<?php echo URLROOT; ?>/connections/dashboard" class="btn btn-primary-tab">Ir al Dashboard</a>
  </div>

<?php elseif ($incompleteBatch): ?>
  <div class="pw-centered">
    <div class="pw-steps">
      <div class="pw-step completed"><span class="pw-step-circle">1</span><span class="pw-step-label">Subir archivo</span></div>
      <div class="pw-step-connector done"></div>
      <div class="pw-step completed"><span class="pw-step-circle">2</span><span class="pw-step-label">Revisión</span></div>
      <div class="pw-step-connector done"></div>
      <div class="pw-step completed"><span class="pw-step-circle">3</span><span class="pw-step-label">Procesando</span></div>
      <div class="pw-step-connector done"></div>
      <div class="pw-step active"><span class="pw-step-circle">4</span><span class="pw-step-label">Terminado</span></div>
    </div>

    <div class="incomplete-banner">
      <?php echo icon('info', ['size' => 20]); ?>
      <div class="banner-text">
        <strong>Proceso anterior incompleto</strong>
        <p>Se procesaron <?php echo $incompleteBatch['processed']; ?> de <?php echo $incompleteBatch['total']; ?> productos.
        <?php if ($incompleteBatch['results_count'] > 0): ?>
          Quedaron <?php echo $incompleteBatch['results_count']; ?> resultados guardados.
        <?php endif; ?>
        Puedes reanudar desde donde quedó o empezar de nuevo.</p>
      </div>
      <div class="banner-actions">
          <button class="btn btn-resume" id="btnResume" data-total="<?php echo (int) $incompleteBatch['total']; ?>">Reanudar</button>
        <a href="<?php echo URLROOT; ?>/connections/prices?reset=1" class="btn btn-reset">Empezar de nuevo</a>
      </div>
    </div>

    <div id="stepProcessingResume" class="pw-card" style="display:none">
      <h2 class="pw-card-title">Reanudando proceso...</h2>
        <div class="pw-progress">
          <div class="pw-progress-counter" id="progressCounter"><span><?php echo $incompleteBatch['processed']; ?> <span class="pw-pct">de <?php echo $incompleteBatch['total']; ?></span></span><span class="pw-percent"><?php echo $incompleteBatch['total'] > 0 ? round(($incompleteBatch['processed'] / $incompleteBatch['total']) * 100) : 0; ?>%</span></div>
          <div class="pw-progress-track">
            <div class="pw-progress-fill" id="progressFill" style="width:<?php echo $incompleteBatch['total'] > 0 ? round(($incompleteBatch['processed'] / $incompleteBatch['total']) * 100) : 0; ?>%"></div>
          </div>
          <div class="pw-progress-text" id="progressText"><?php echo $incompleteBatch['processed']; ?> de <?php echo $incompleteBatch['total']; ?></div>
        </div>
        <div class="pw-logs" id="processLogs"></div>
    </div>
  </div>

<?php elseif ($results !== null): ?>
  <?php $ok = count(array_filter($results, function($r) { return $r['status'] === 'success'; })); ?>
  <?php $err = count($results) - $ok; ?>
  <?php $rate = $ok > 0 ? round(($ok / count($results)) * 100) : 0; ?>

  <div class="pw-centered">
    <div class="pw-steps">
      <div class="pw-step completed"><span class="pw-step-circle">1</span><span class="pw-step-label">Subir archivo</span></div>
      <div class="pw-step-connector done"></div>
      <div class="pw-step completed"><span class="pw-step-circle">2</span><span class="pw-step-label">Revisión</span></div>
      <div class="pw-step-connector done"></div>
      <div class="pw-step completed"><span class="pw-step-circle">3</span><span class="pw-step-label">Procesando</span></div>
      <div class="pw-step-connector done"></div>
      <div class="pw-step completed active"><span class="pw-step-circle">4</span><span class="pw-step-label">Terminado</span></div>
    </div>

    <div class="pw-hero-step">
      <h2 class="pw-hero-title is-success">¡Proceso completado!</h2>
      <div class="pw-result-body">
        <div class="pw-result-chart">
          <div id="resultDonut"></div>
          <div class="pw-result-labels">
            <span class="pw-lbl ok"><strong><?php echo $ok; ?></strong> completados</span>
            <span class="pw-lbl err"><strong><?php echo $err; ?></strong> errores</span>
          </div>
        </div>
        <div class="pw-result-stats">
          <div class="pw-stat"><span class="pw-stat-label">Total procesados</span><span class="pw-stat-val"><?php echo count($results); ?></span></div>
          <div class="pw-stat ok"><span class="pw-stat-label">Completados</span><span class="pw-stat-val"><?php echo $ok; ?></span></div>
          <div class="pw-stat <?php echo $err > 0 ? 'err' : ''; ?>"><span class="pw-stat-label">Errores</span><span class="pw-stat-val"><?php echo $err; ?></span></div>
          <div class="pw-stat"><span class="pw-stat-label">Tasa de éxito</span><span class="pw-stat-val"><?php echo $rate; ?>%</span></div>
          <?php if ($activeStore): ?>
          <div class="pw-stat"><span class="pw-stat-label">Tienda</span><span class="pw-stat-val"><?php echo htmlspecialchars($activeStore->store_name ?: $activeStore->ml_nickname); ?></span></div>
          <?php endif; ?>
          <?php if ($lastBatchId): ?>
          <div class="pw-stat"><span class="pw-stat-label">Batch ID</span><span class="pw-stat-val pw-stat-batchid"><?php echo htmlspecialchars($lastBatchId); ?></span></div>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($validationErrors)): ?>
        <div class="pw-valid-errors">
          <strong>Errores de validación:</strong>
          <ul><?php foreach ($validationErrors as $e): ?>
            <li><?php echo htmlspecialchars($e); ?></li>
          <?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <div class="pw-result-actions">
        <a href="<?php echo URLROOT; ?>/connections/exportPriceResults" class="pw-btn pw-btn-success" id="btnExportXlsx" data-throttle="true">
          <?php echo icon('download', ['size' => 16]); ?>
          Descargar XLSX
        </a>
        <a href="<?php echo URLROOT; ?>/connections/prices" class="pw-btn pw-btn-secondary">Nueva ejecución</a>
      </div>
    </div>
  </div>

  <script>
  window.RESULT_DONUT_CONFIG = { totalOk: <?php echo (int) $ok; ?>, totalErr: <?php echo (int) $err; ?> };
  </script>

<?php else: ?>
<div class="pw-centered">

  <div class="pw-steps">
    <div class="pw-step active" id="stepIndicator1"><span class="pw-step-circle">1</span><span class="pw-step-label">Subir archivo</span></div>
    <div class="pw-step-connector" id="stepConnector1"></div>
    <div class="pw-step" id="stepIndicator2"><span class="pw-step-circle">2</span><span class="pw-step-label">Revisión</span></div>
    <div class="pw-step-connector" id="stepConnector2"></div>
    <div class="pw-step" id="stepIndicator3"><span class="pw-step-circle">3</span><span class="pw-step-label">Procesando</span></div>
    <div class="pw-step-connector" id="stepConnector3"></div>
    <div class="pw-step" id="stepIndicator4"><span class="pw-step-circle">4</span><span class="pw-step-label">Terminado</span></div>
  </div>

  <!-- STEP 1: Upload -->
  <div id="stepUpload" class="pw-hero-upload">
    <h2 class="pw-hero-title">Subir archivo de precios</h2>
    <p class="pw-hero-desc">Formatos aceptados: <strong>.xlsx</strong> o <strong>.csv</strong> con columna SKU y columna Precio.</p>
    
    <div class="rate-limit-notice">
      <?php echo icon('info', ['size' => 18]); ?>
      <div class="rate-limit-notice-body">
        <strong>Límite de procesamiento:</strong>
        <span class="rln-text"> Máximo <strong>10.000 productos</strong> por lote. El sistema procesa a <strong>~300 consultas por minuto</strong> para evitar bloqueos de MercadoLibre.</span>
      </div>
    </div>

    <form id="uploadForm">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

      <div class="pw-drop" id="fileDrop">
        <input type="file" name="prices_file" id="pricesFile" accept=".xlsx,.csv" required class="pw-file-input">
        <label for="pricesFile" class="pw-file-label">
          <?php echo icon('upload', ['size' => 36]); ?>
          <span class="pw-file-text">Arrastra tu archivo aquí o haz clic para seleccionar</span>
          <span class="pw-file-name" id="fileName"></span>
          <span class="pw-file-badges">
            <span class="pw-file-badge"><?php echo icon('file-text', ['size' => 12]); ?> XLSX</span>
            <span class="pw-file-badge"><?php echo icon('file-text', ['size' => 12]); ?> CSV</span>
          </span>
        </label>
      </div>

      <div id="fileValidation" class="pw-validation" style="display:none"></div>

      <button type="submit" class="pw-btn pw-btn-primary pw-btn-full" id="uploadBtn" disabled>
        Analizar archivo
      </button>
    </form>

    <div class="pw-template-link">
      <a href="<?php echo URLROOT; ?>/connections/exportPriceTemplate" data-throttle="true">
        <?php echo icon('download', ['size' => 14]); ?>
        Descargar plantilla de ejemplo
      </a>
    </div>
  </div>

  <!-- STEP 2: Preview -->
  <div id="stepPreview" class="pw-hero-step" style="display:none">
    <h2 class="pw-hero-title">Revisión de datos</h2>

    <div class="pw-review-grid">
      <div class="pw-review-left">
        <h3 class="pw-review-section-title">Resumen</h3>

        <div class="pw-review-summary">
          <div class="pw-review-item">
            <span class="pw-ri-label">SKU</span>
            <span class="pw-ri-value" id="previewColSku">—</span>
          </div>
          <div class="pw-review-item">
            <span class="pw-ri-label">Precio</span>
            <span class="pw-ri-value" id="previewColPrice">—</span>
          </div>
          <div class="pw-review-item">
            <span class="pw-ri-label">Items</span>
            <span class="pw-ri-value" id="previewTotal">0</span>
          </div>
        </div>
      </div>

      <div class="pw-review-right">
        <h3 class="pw-review-section-title">Vista previa</h3>

        <div id="previewTableWrap" class="pw-preview-table-wrap" style="display:none">
          <table class="pw-preview-table">
            <thead>
              <tr>
                <th id="previewThSku">SKU</th>
                <th id="previewThPrice">Precio</th>
              </tr>
            </thead>
            <tbody id="previewTbody"></tbody>
          </table>
          <div class="pw-preview-table-more" id="previewMore"></div>
        </div>
      </div>
    </div>

    <div class="pw-preview-warning">
      <?php echo icon('triangle-alert', ['size' => 16]); ?>
      <span>Los precios se actualizarán directamente en MercadoLibre al ejecutar. Esta acción no se puede deshacer.</span>
    </div>

    <div class="pw-preview-actions">
      <button class="pw-btn pw-btn-outline" id="btnBackUpload">
        <?php echo icon('arrow-left', ['size' => 14]); ?>
        Volver
      </button>
      <button class="pw-btn pw-btn-primary" id="btnExecute">
        <?php echo icon('circle-check', ['size' => 16]); ?>
        Ejecutar cambios
      </button>
    </div>
  </div>

  <!-- STEP 3: Processing -->
  <div id="stepProcessing" class="pw-hero-step" style="display:none">
    <h2 class="pw-hero-title">Procesando cambios</h2>
    <p class="pw-hero-desc">No cierres esta página mientras se procesan los precios.</p>

    <div class="pw-progress">
      <div class="pw-progress-counter" id="progressCounter"><span><span id="progressCountDisplay">0</span> <span class="pw-pct">de <span id="progressTotalDisplay">0</span></span></span><span class="pw-percent" id="progressPercent">0%</span></div>
      <div class="pw-progress-track">
        <div class="pw-progress-fill" id="progressFill"></div>
      </div>
      <div class="pw-progress-text" id="progressText">Iniciando…</div>
    </div>

    <div class="pw-eta" id="processEta">Tiempo estimado: calculando...</div>

    <div class="pw-logs" id="processLogs"></div>
  </div>

  <!-- CONFIRMATION MODAL -->
  <div class="modal-overlay" id="pricesConfirmModal">
    <div class="modal-box">
      <div class="modal-icon warning">
        <?php echo icon('triangle-alert', ['size' => 36]); ?>
      </div>
      <h3>Confirmar cambio masivo</h3>
      <p class="modal-desc">Se actualizarán los precios de <strong id="modalTotal">0</strong> productos en la tienda <strong id="modalStore">—</strong>.</p>
      <p class="modal-eta" id="modalEta"></p>
      <div class="modal-warning">
        <?php echo icon('triangle-alert', ['size' => 16]); ?>
        <span>Esta acción no se puede deshacer.</span>
      </div>
      <div class="modal-actions">
        <button class="btn btn-cancel" id="btnCancelConfirm">Cancelar</button>
        <button class="btn btn-confirm" id="btnConfirmStart">Sí, actualizar precios</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
window.PRICE_CONFIG = {
  urlRoot: <?php echo json_encode(URLROOT); ?>,
  csrf: <?php echo json_encode($data['csrf_token']); ?>,
  storeName: <?php $as = $data['active_store']; echo json_encode($as ? ($as->store_name ?: $as->ml_nickname) : ''); ?>
};
</script>
<script src="<?php echo URLROOT; ?>/assets/js/apexcharts.min.js"></script>
<script src="<?php echo URLROOT; ?>/assets/js/prices.js?v=3"></script>
