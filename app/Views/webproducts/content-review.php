<div class="wp-wrap">

  <div class="pw-card" id="wp-step-review-upload">
    <div class="pw-card-review-head">
      <?php echo icon('search-check', ['size' => 18]); ?>
      <span>Revisar plantilla existente</span>
    </div>
    <p class="pw-card-review-desc">Sube una plantilla WooCommerce ya completada para que el sistema detecte errores automáticamente.</p>

    <?php
    $reviewUploadedBatch = $data['review_uploaded_batch'] ?? '';
    $hasUploadReview = !empty($reviewUploadedBatch);
    $rfilas = $data['review_uploaded_filas'] ?? [];
    $rissues = $data['review_uploaded_issues'] ?? [];
    $rfixable = count(array_filter($rissues, fn($i) => $i['arreglable']));
    ?>

    <?php if ($hasUploadReview): ?>
    <div class="wp-quality-gate" role="region" aria-label="Resultado de revisión">
      <div class="wp-quality-gate-header">
        <h3>
          <?php echo icon('triangle-alert', ['size' => 18]); ?> 
          Resultado de revisión
        </h3>
        <div class="wp-quality-gate-actions">
          <form method="post" action="<?php echo URLROOT; ?>/webproducts/fixUploadedTemplate" data-confirm="¿Aplicar correcciones automáticas?">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
            <button type="submit" class="pw-btn pw-btn-amber">
              <?php echo icon('wrench', ['size' => 14]); ?> Corregir
            </button>
          </form>
          <form method="post" action="<?php echo URLROOT; ?>/webproducts/resetReviewUpload">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
            <button type="submit" class="pw-btn pw-btn-outline">
              <?php echo icon('refresh-cw', ['size' => 14]); ?> Reiniciar
            </button>
          </form>
        </div>
      </div>
      <div class="wp-quality-gate-legend">
        <span class="wp-legend-ok"><?php echo icon('circle-check', ['size' => 12]); ?> Correcto</span>
        <span class="wp-legend-err"><?php echo icon('circle-x', ['size' => 12]); ?> Error</span>
        <span class="wp-legend-sys">sistema</span>
      </div>
      <?php if (!empty($rfilas)):
        $shown = 0; $maxShow = 20;
        foreach ($rfilas as $fi):
          $errCols = array_filter($fi['cols'], fn($c) => $c['e'] !== null);
          if (empty($errCols)) continue;
          $shown++;
          if ($shown > $maxShow) continue;
      ?>
      <div class="wp-issue-row">
        <span class="wp-issue-row-icon"><?php echo icon('circle-x', ['size' => 12]); ?></span>
        <span class="wp-issue-row-meta">Fila <?php echo $fi['fila']; ?></span>
        <code><?php echo htmlspecialchars($fi['sku'] ?? ''); ?></code>
        <span class="wp-issue-name"><?php echo htmlspecialchars(mb_substr($fi['nombre'] ?? '', 0, 30)); ?></span>
        <?php foreach (array_slice($errCols, 0, 3) as $ec): ?>
        <span class="wp-issue-tag" title="<?php echo htmlspecialchars($ec['e']); ?>"><?php echo htmlspecialchars($ec['h']); ?></span>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
      <?php if ($shown > $maxShow): ?>
      <div class="wp-issue-more">y <?php echo $shown - $maxShow; ?> filas más con errores</div>
      <?php endif; ?>
      <?php endif; ?>
      <div class="wp-quality-gate-footer">
        <span><?php echo count($rfilas); ?> revisados, <?php echo count($rissues); ?> incidencias, <?php echo $rfixable; ?> corregibles.</span>
        <div class="wp-btn-group">
          <a href="<?php echo URLROOT; ?>/webproducts/download/xlsx/<?php echo $reviewUploadedBatch; ?>" class="pw-btn pw-btn-success pw-btn-sm" data-no-fade>
            <?php echo icon('download', ['size' => 14]); ?> Descargar
          </a>
        </div>
      </div>
    </div>
    <?php else: ?>
    <form method="post" action="<?php echo URLROOT; ?>/webproducts/uploadForReview" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
      <div class="pw-drop" id="wpReviewDropzone">
        <input type="file" name="review_file" id="reviewFileInput" class="pw-file-input" accept=".xlsx,.xls" required autocomplete="off">
        <label for="reviewFileInput" class="pw-file-label" style="cursor:pointer;">
          <?php echo icon('upload', ['size' => 24]); ?>
          <span class="pw-file-text">Suelta tu <strong>plantilla WooCommerce</strong> aquí o haz clic para buscar</span>
          <span class="pw-file-name" id="wpReviewFileName"></span>
          <span class="pw-file-badges">
            <span class="pw-file-badge"><?php echo icon('file-text', ['size' => 12]); ?> XLSX</span>
            <span class="pw-file-badge"><?php echo icon('file-text', ['size' => 12]); ?> XLS</span>
          </span>
        </label>
      </div>
      <button type="submit" class="pw-btn pw-btn-primary pw-btn-full">
        <?php echo icon('upload', ['size' => 16]); ?> Subir y revisar
      </button>
    </form>
    <?php endif; ?>
  </div>

</div>

<script src="<?php echo URLROOT; ?>/assets/js/webproducts/index.js"></script>