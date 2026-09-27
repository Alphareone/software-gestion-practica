<?php
$dataBatch = $data['data_batch'] ?? '';
$filledBatch = $data['filled_batch'] ?? '';
$hasData = !empty($dataBatch);
$hasFilled = !empty($filledBatch);
$hasReview = $hasFilled && !empty($data['review_issues']);

$uploadIssues = $data['upload_issues'] ?? [];
$templateWarnings = $data['template_warnings'] ?? [];
$hasIssues = !empty($uploadIssues) || !empty($templateWarnings);

$initialStep = $hasFilled ? 4 : 1;

$isProcessing = isset($_SESSION['web_processing']) && $_SESSION['web_processing'];
unset($_SESSION['web_processing']);
?>
<div class="wp-wrap" id="wpApp" data-initial-step="<?php echo $initialStep; ?>">

  <!-- ═══════════ Skip Link ═══════════ -->
  <a href="#wp-step-upload" class="wp-skip-link">Saltar al contenido principal</a>

  <!-- ═══════════ Progress Stepper ═══════════ -->
  <div class="pw-steps" aria-label="Progreso de creación web" id="wpStepper">
    <div class="pw-step" data-step="1"><span class="pw-step-circle">1</span><span class="pw-step-label">Subir</span></div>
    <div class="pw-step-connector" data-connector="1"></div>
    <div class="pw-step" data-step="2"><span class="pw-step-circle">2</span><span class="pw-step-label">Revisar</span></div>
    <div class="pw-step-connector" data-connector="2"></div>
    <div class="pw-step" data-step="3"><span class="pw-step-circle">3</span><span class="pw-step-label">Generar</span></div>
    <div class="pw-step-connector" data-connector="3"></div>
    <div class="pw-step" data-step="4"><span class="pw-step-circle">4</span><span class="pw-step-label">Descargar</span></div>
  </div>

  <!-- ═══════════ Live Region ═══════════ -->
  <div aria-live="polite" aria-atomic="true" class="wp-sr-only" id="wpLiveRegion"></div>

  <!-- ═══════════ Manual de uso ═══════════ -->
  <div class="mb-6" id="wpManualWrap">
    <button type="button" class="flex items-center gap-2.5 w-full px-4 py-3 rounded-lg border border-border text-sm font-semibold text-primary bg-surface/30 hover:bg-surface/60 transition-all cursor-pointer" onclick="wpToggleManual()" aria-expanded="false">
      <span class="flex text-muted"><?php echo icon('book-open', ['size' => 16]); ?></span>
      <span>Manual de uso</span>
      <span class="wp-manual-arrow ml-auto transition-transform text-xs text-muted"><?php echo icon('chevron-down', ['size' => 14]); ?></span>
    </button>
    <div class="wp-manual-body hidden p-4 pt-3 border border-t-0 border-border rounded-b-lg text-sm leading-relaxed text-muted bg-surface/10">
      <ol class="m-0 ps-4">
        <li class="mb-1.5"><strong>Descargar plantilla</strong> &mdash; Haz clic en &laquo;Descargar plantilla Excel&raquo; para obtener un archivo con las columnas necesarias. Llena los datos de tus productos en ese archivo.</li>
        <li class="mb-1.5"><strong>Subir datos</strong> &mdash; Sube el archivo llenado (o crea productos manualmente con &laquo;+ Nuevo producto&raquo;).</li>
        <li class="mb-1.5"><strong>Revisar advertencias</strong> &mdash; Si hay productos con datos incompletos se mostrarán en la tabla de abajo. Corrígelos antes de continuar.</li>
        <li class="mb-1.5"><strong>Subir plantilla WooCommerce</strong> &mdash; Descarga la plantilla WooCommerce en blanco desde tu tienda y súbela aqu&iacute;. El sistema la llenar&aacute; autom&aacute;ticamente.</li>
        <li class="mb-1.5"><strong>Descargar resultado</strong> &mdash; Una vez generada, descarga el archivo como Excel o CSV e imp&oacute;rtalo en WooCommerce.</li>
        <li><strong>Corregir plantillas existentes</strong> &mdash; Si ya tienes una plantilla llena con datos, s&uacute;bela en &laquo;Correcci&oacute;n de errores&raquo;. El sistema detectar&aacute; problemas y te ofrecer&aacute; corregirlos autom&aacute;ticamente.</li>
      </ol>
    </div>
  </div>
  <script>
  function wpToggleManual() {
    var wrap = document.getElementById('wpManualWrap');
    var body = wrap.querySelector('.wp-manual-body');
    var arrow = wrap.querySelector('.wp-manual-arrow');
    var btn = wrap.querySelector('button');
    var open = body.classList.toggle('hidden');
    btn.setAttribute('aria-expanded', !open);
    arrow.style.transform = open ? '' : 'rotate(180deg)';
    btn.classList.toggle('rounded-b-none', !open);
  }
  </script>

  <!-- ═══════════ Step 1: Upload ═══════════ -->
  <div class="pw-hero-upload step-panel" data-step="1" id="wp-step-upload">
    <?php if ($hasData): ?>
    <div class="pw-card max-w-[640px] mx-auto text-center">
      <div class="flex items-center gap-3 justify-center">
        <span class="flex text-emerald-500"><?php echo icon('circle-check', ['size' => 24]); ?></span>
        <div>
          <strong class="block text-sm"><?php echo (int)($data['data_count'] ?? 0); ?> productos cargados</strong>
          <span class="text-xs text-muted">Archivo procesado correctamente</span>
        </div>
      </div>
      <div class="flex gap-2 justify-center mt-3">
        <button type="button" class="pw-btn pw-btn-primary" onclick="wpShowStep(2)"><?php echo icon('arrow-right', ['size' => 14]); ?> Avanzar a revisión</button>
        <form method="post" action="<?php echo URLROOT; ?>/webproducts/resetData">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
          <button type="submit" class="pw-btn pw-btn-outline" data-confirm="¿Reiniciar datos? Se perderán los productos cargados.">
            <?php echo icon('refresh-cw', ['size' => 14]); ?> Reiniciar
          </button>
        </form>
      </div>
    </div>
    <?php else: ?>
    <h2 class="pw-hero-title">Subir archivo de datos</h2>
    <p class="pw-hero-desc">Formatos aceptados: <strong>.xlsx</strong> o <strong>.xls</strong>. Máximo <strong>10 MB</strong> (aprox. 5000 filas).</p>

    <form method="post" action="<?php echo URLROOT; ?>/webproducts/uploadData" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
      <div class="pw-drop" id="wpDataDropzone">
        <input type="file" name="data_file" id="dataFileInput" class="pw-file-input" accept=".xlsx,.xls" required autocomplete="off">
        <label for="dataFileInput" class="pw-file-label" style="cursor:pointer;">
          <?php echo icon('upload', ['size' => 36]); ?>
          <span class="pw-file-text">Arrastra tu archivo Excel aquí o haz clic para seleccionar</span>
          <span class="pw-file-name" id="wpDataFileName"></span>
          <span class="pw-file-badges">
            <span class="pw-file-badge"><?php echo icon('file-text', ['size' => 12]); ?> XLSX</span>
            <span class="pw-file-badge"><?php echo icon('file-text', ['size' => 12]); ?> XLS</span>
          </span>
        </label>
      </div>
      <button type="submit" class="pw-btn pw-btn-primary pw-btn-full">
        <?php echo icon('upload', ['size' => 16]); ?> Subir datos
      </button>
    </form>

    <div class="pw-template-link">
      <a href="<?php echo URLROOT; ?>/webproducts/downloadSkeleton">
        <?php echo icon('download', ['size' => 14]); ?>
        Descargar plantilla Excel
      </a>
    </div>

    <div class="max-w-[640px] mx-auto mt-6 pt-4 border-t border-border">
      <div class="flex items-center gap-3">
        <div class="flex-1 min-w-0 text-left">
          <strong class="block text-sm">Crear producto manual</strong>
          <span class="block text-xs text-muted">Añade productos individualmente sin usar Excel</span>
        </div>
        <button type="button" class="pw-btn pw-btn-outline" data-bs-toggle="modal" data-bs-target="#productModal">
          + Nuevo producto
        </button>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ═══════════ Step 2: Review ═══════════ -->
  <?php if (!empty($data['data_preview'])):
    $issuesBySku = [];
    foreach ($uploadIssues as $iss) {
      $sku = $iss['sku'] ?? '';
      if ($sku) $issuesBySku[$sku] = ['type' => 'error', 'tags' => $iss['problemas'] ?? []];
    }
    foreach ($templateWarnings as $w) {
      $sku = $w['sku'] ?? '';
      if ($sku) {
        if (!isset($issuesBySku[$sku])) {
          $issuesBySku[$sku] = ['type' => 'warning', 'tags' => []];
        }
        $issuesBySku[$sku]['tags'][] = $w['tipo'] ?? $w['mensaje'] ?? '';
      }
    }
    $totalObs = count($issuesBySku);
  ?>
  <div class="step-panel" data-step="2">
    <section class="wp-section" id="wp-step-review">
      <h2 class="wp-section-heading">
        <span class="wp-section-icon"><?php echo icon('search', ['size' => 20]); ?></span>
        <span>Revisar productos</span>
        <span class="wp-badge <?php echo $totalObs > 0 ? 'wp-badge-amber' : 'wp-badge-green'; ?>">
          <?php echo (int)($data['data_count'] ?? 0); ?> productos
        </span>
      </h2>

      <div class="wp-section-content">
        <?php if ($totalObs > 0): ?>
        <div class="wp-alert wp-alert-warning" role="alert">
          <span class="wp-alert-icon"><?php echo icon('triangle-alert', ['size' => 18]); ?></span>
          <span class="wp-alert-message"><?php echo $totalObs; ?> producto(s) con observaciones que requieren atención.</span>
        </div>
        <?php endif; ?>

        <div class="wp-table-controls">
          <div class="wp-search-input">
            <?php echo icon('search', ['size' => 16]); ?>
            <input type="text" id="wpTableSearch" placeholder="Buscar por SKU o nombre…" autocomplete="off" aria-label="Buscar productos">
            <button type="button" class="wp-search-clear hidden" id="wpSearchClear" aria-label="Limpiar búsqueda">
              <?php echo icon('x', ['size' => 14]); ?>
            </button>
          </div>
          <div class="wp-table-filters">
            <button type="button" class="wp-btn wp-btn-outline wp-btn-sm" id="wpFilterErrors" aria-pressed="false">
              <?php echo icon('triangle-alert', ['size' => 14]); ?> Con errores
            </button>
          </div>
        </div>

        <div class="wp-table-skeleton hidden" id="wpTableSkeleton">
          <div class="wp-skeleton-row">
            <div class="wp-skeleton-cell w-8"></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text"></div></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text wp-skeleton-text-lg"></div></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text"></div></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text"></div></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text"></div></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text"></div></div>
          </div>
          <div class="wp-skeleton-row">
            <div class="wp-skeleton-cell w-8"></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text"></div></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text wp-skeleton-text-lg"></div></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text"></div></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text"></div></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text"></div></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text"></div></div>
          </div>
          <div class="wp-skeleton-row">
            <div class="wp-skeleton-cell w-8"></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text"></div></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text wp-skeleton-text-lg"></div></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text"></div></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text"></div></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text"></div></div>
            <div class="wp-skeleton-cell"><div class="wp-skeleton-text"></div></div>
          </div>
        </div>

        <div class="wp-table-wrap">
          <table class="wp-table" role="grid" aria-label="Tabla de productos cargados">
            <thead>
              <tr>
                <th scope="col" class="w-8" aria-label="Estado"></th>
                <th scope="col">SKU</th>
                <th scope="col">Nombre</th>
                <th scope="col">Tallas</th>
                <th scope="col">Color</th>
                <th scope="col" class="wp-cell-num">Stock</th>
                <?php if ($totalObs > 0): ?><th scope="col">Obs.</th><?php endif; ?>
                <th scope="col" class="w-12" aria-label="Acciones"></th>
                <th scope="col" class="w-10" aria-label="Enlace"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($data['data_preview'] as $p):
                $sku = $p->sku ?? '';
                $ri = $issuesBySku[$sku] ?? null;
                $rowClass = $ri ? ($ri['type'] === 'error' ? 'has-errors' : 'has-issues') : '';
                $tags = $ri ? array_slice($ri['tags'], 0, 3) : [];
                $rowId = 'product-' . htmlspecialchars($sku);
              ?>
              <tr class="<?php echo $rowClass; ?>" id="<?php echo $rowId; ?>">
                <td class="text-center">
                  <?php if ($ri): ?>
                  <span class="wp-status-icon <?php echo $ri['type'] === 'error' ? 'wp-status-error' : 'wp-status-warning'; ?>" aria-label="<?php echo $ri['type'] === 'error' ? 'Error' : 'Advertencia'; ?>">
                    <?php echo icon($ri['type'] === 'error' ? 'circle-x' : 'triangle-alert', ['size' => 14]); ?>
                  </span>
                  <?php else: ?>
                  <span class="wp-status-icon wp-status-ok" aria-label="Correcto">
                    <?php echo icon('circle-check', ['size' => 14]); ?>
                  </span>
                  <?php endif; ?>
                </td>
                <td><code><?php echo htmlspecialchars($sku); ?></code></td>
                <td><span class="wp-table-truncate" title="<?php echo htmlspecialchars($p->nombre ?? ''); ?>"><?php echo htmlspecialchars(mb_substr($p->nombre ?? '', 0, 50)); ?></span></td>
                <td><?php echo htmlspecialchars($p->talla ?? ''); ?></td>
                <td><?php echo htmlspecialchars($p->color ?? ''); ?></td>
                <td class="wp-cell-num"><?php echo (int)($p->stock ?? 0); ?></td>
                <?php if ($totalObs > 0): ?>
                <td class="max-w-[150px]">
                  <?php if (!empty($tags)): ?>
                    <?php foreach ($tags as $t): ?>
                    <span class="wp-issue-tag"><?php echo htmlspecialchars(mb_substr($t, 0, 15)); ?></span>
                    <?php endforeach; ?>
                    <?php if (count($ri['tags']) > 3): ?><span class="wp-issue-more-inline">+<?php echo count($ri['tags']) - 3; ?></span><?php endif; ?>
                  <?php endif; ?>
                </td>
                <?php endif; ?>
                <td class="text-center">
                  <form method="post" action="<?php echo URLROOT; ?>/webproducts/deleteProduct/<?php echo (int)($p->id ?? 0); ?>" data-confirm="¿Eliminar este producto?">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
                    <button type="submit" class="wp-btn wp-btn-ghost wp-btn-icon" aria-label="Eliminar producto <?php echo htmlspecialchars($sku); ?>">
                      <?php echo icon('trash-2', ['size' => 16]); ?>
                    </button>
                  </form>
                </td>
                <td class="w-10 text-center">
                  <button type="button" class="wp-btn wp-btn-ghost wp-btn-icon" aria-label="Copiar enlace a este producto" onclick="wpCopyProductLink('<?php echo htmlspecialchars($sku); ?>')" title="Copiar enlace">
                    <?php echo icon('link-2', ['size' => 14]); ?>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ((int)($data['data_count'] ?? 0) > count($data['data_preview'])): ?>
        <p class="wp-table-info">Mostrando los primeros <?php echo count($data['data_preview']); ?> de <?php echo (int)($data['data_count'] ?? 0); ?> productos.</p>
        <?php endif; ?>
      </div>
    </section>
    <div class="flex gap-3 justify-center mt-4">
      <button type="button" class="pw-btn pw-btn-outline" onclick="wpShowStep(1)"><?php echo icon('arrow-left', ['size' => 14]); ?> Volver</button>
      <button type="button" class="pw-btn pw-btn-primary" onclick="wpShowStep(3)"><?php echo icon('arrow-right', ['size' => 14]); ?> Generar plantilla</button>
    </div>
  </div>
  <?php endif; ?>

  <!-- ═══════════ Step 3: Generate ═══════════ -->
  <?php if ($hasData): ?>
  <div class="step-panel" data-step="3">
    <section class="wp-section" id="wp-step-generate">
      <h2 class="wp-section-heading">
        <span class="wp-section-icon"><?php echo icon('file-text', ['size' => 20]); ?></span>
        <span>Generar plantilla WooCommerce</span>
      </h2>

      <div class="wp-section-content">
        <?php if ($hasFilled): ?>
        <div class="pw-card text-center">
          <div class="flex items-center gap-3 justify-center mb-4">
            <span class="flex text-emerald-500"><?php echo icon('circle-check', ['size' => 24]); ?></span>
            <div class="text-left">
              <strong class="block text-sm">Plantilla generada correctamente</strong>
              <span class="text-xs text-muted">Lista para descargar en formato Excel o CSV</span>
            </div>
          </div>
          <div class="flex gap-2 justify-center flex-wrap">
            <a href="<?php echo URLROOT; ?>/webproducts/download/xlsx/<?php echo $filledBatch; ?>" class="pw-btn pw-btn-success" data-no-fade>
              <?php echo icon('download', ['size' => 16]); ?> Descargar Excel
            </a>
            <a href="<?php echo URLROOT; ?>/webproducts/download/csv/<?php echo $filledBatch; ?>" class="pw-btn pw-btn-outline" data-no-fade>
              <?php echo icon('file-text', ['size' => 14]); ?> Descargar CSV
            </a>
          </div>
        </div>
        <?php else: ?>
        <p class="mb-4 text-sm text-muted">Sube la plantilla WooCommerce en blanco. El sistema la llenará automáticamente con tus datos.</p>
        <form method="post" action="<?php echo URLROOT; ?>/webproducts/uploadTemplate" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
          <div class="pw-drop mb-3" id="wpTemplateDropzone">
            <input type="file" name="template_file" id="templateFileInput" class="pw-file-input" accept=".xlsx,.xls" required autocomplete="off">
            <label for="templateFileInput" class="pw-file-label" style="cursor:pointer;">
              <?php echo icon('upload', ['size' => 24]); ?>
              <span class="pw-file-text">Suelta la <strong>plantilla WooCommerce</strong> aquí o haz clic para buscar</span>
              <span class="pw-file-name" id="wpTemplateFileName"></span>
              <span class="pw-file-badges">
                <span class="pw-file-badge"><?php echo icon('file-text', ['size' => 12]); ?> XLSX</span>
                <span class="pw-file-badge"><?php echo icon('file-text', ['size' => 12]); ?> XLS</span>
              </span>
            </label>
          </div>
          <button type="submit" class="pw-btn pw-btn-primary pw-btn-full">
            <?php echo icon('upload', ['size' => 16]); ?> Subir y generar
          </button>
        </form>
        <?php endif; ?>
      </div>
    </section>
    <div class="flex gap-3 justify-center mt-4">
      <button type="button" class="pw-btn pw-btn-outline" onclick="wpShowStep(2)"><?php echo icon('arrow-left', ['size' => 14]); ?> Volver a revisión</button>
      <?php if ($hasFilled): ?>
      <button type="button" class="pw-btn pw-btn-primary" onclick="wpShowStep(4)"><?php echo icon('arrow-right', ['size' => 14]); ?> Ver descargas</button>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ═══════════ Step 4: Download ═══════════ -->
  <?php if ($hasFilled): ?>
  <div class="step-panel" data-step="4">
    <section class="wp-section" id="wp-step-download">
      <h2 class="wp-section-heading">
        <span class="wp-section-icon"><?php echo icon('download', ['size' => 20]); ?></span>
        <span>Descargar plantilla</span>
      </h2>

      <div class="wp-section-content">
        <?php if ($hasReview):
          $filas = $data['review_filas'] ?? [];
          $totalErrores = count($data['review_issues']);
          $fixable = count(array_filter($data['review_issues'], fn($i) => $i['arreglable']));
        ?>
        <div class="wp-quality-gate" role="region" aria-label="Revisión de plantilla">
          <div class="wp-quality-gate-header">
            <h3><?php echo icon('circle-check', ['size' => 18]); ?> Revisión de calidad</h3>
            <div class="wp-quality-gate-actions">
              <form method="post" action="<?php echo URLROOT; ?>/webproducts/fixTemplate" data-confirm="¿Aplicar correcciones automáticas?">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
                <button type="submit" class="wp-btn wp-btn-amber wp-btn-sm"><?php echo icon('wrench', ['size' => 14]); ?> Corregir</button>
              </form>
              <form method="post" action="<?php echo URLROOT; ?>/webproducts/reviewTemplate">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
                <button type="submit" class="wp-btn wp-btn-outline wp-btn-sm"><?php echo icon('refresh-cw', ['size' => 14]); ?> Re-evaluar</button>
              </form>
            </div>
          </div>
          <div class="wp-quality-gate-legend">
            <span class="wp-legend-ok"><?php echo icon('circle-check', ['size' => 12]); ?> Correcto</span>
            <span class="wp-legend-err"><?php echo icon('circle-x', ['size' => 12]); ?> Error</span>
            <span class="wp-legend-sys">sistema</span>
          </div>
          <?php if (!empty($filas)):
            $shown = 0; $maxShow = 20;
            foreach ($filas as $fi):
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
            <span><?php echo count($filas); ?> revisados, <?php echo $totalErrores; ?> incidencias, <?php echo $fixable; ?> corregibles.</span>
          </div>
        </div>
        <?php else: ?>
        <div class="pw-card text-center">
          <div class="flex items-center gap-2 justify-center">
            <span class="flex text-emerald-500"><?php echo icon('circle-check', ['size' => 20]); ?></span>
            <strong class="text-primary">Plantilla sin incidencias</strong>
          </div>
          <form method="post" action="<?php echo URLROOT; ?>/webproducts/reviewTemplate" class="mt-2">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
            <button type="submit" class="pw-btn pw-btn-outline"><?php echo icon('search', ['size' => 14]); ?> Revisar plantilla</button>
          </form>
        </div>
        <?php endif; ?>

        <div class="pw-card text-center mt-4">
          <h3 class="mb-3 text-sm font-bold text-primary">Archivos disponibles</h3>
          <div class="flex gap-2 justify-center flex-wrap">
            <a href="<?php echo URLROOT; ?>/webproducts/download/xlsx/<?php echo $filledBatch; ?>" class="pw-btn pw-btn-success" data-no-fade>
              <?php echo icon('download', ['size' => 16]); ?> Descargar Excel
            </a>
            <a href="<?php echo URLROOT; ?>/webproducts/download/csv/<?php echo $filledBatch; ?>" class="pw-btn pw-btn-outline" data-no-fade>
              <?php echo icon('file-text', ['size' => 14]); ?> Descargar CSV
            </a>
          </div>
          <form method="post" action="<?php echo URLROOT; ?>/webproducts/resetData" data-confirm="¿Iniciar una nueva ejecución? Se perderán los datos actuales." class="mt-3 pt-3 border-t border-border">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
            <button type="submit" class="pw-btn pw-btn-outline"><?php echo icon('refresh-cw', ['size' => 14]); ?> Nueva ejecución</button>
          </form>
        </div>
      </div>
    </section>
    <div class="flex gap-3 justify-center mt-4">
      <button type="button" class="pw-btn pw-btn-outline" onclick="wpShowStep(3)"><?php echo icon('arrow-left', ['size' => 14]); ?> Volver</button>
    </div>
  </div>
  <?php endif; ?>



</div>

<!-- ═══════════ Tools: Categorías, Medidas & Review ═══════════ -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 mb-6">
  <div class="card border-accent">
    <div class="flex items-center gap-4 p-5">
      <div class="flex-shrink-0 text-accent text-3xl"><?php echo icon('layers', ['size' => 32]); ?></div>
      <div class="flex-1 min-w-0">
        <h5 class="font-semibold mb-1 text-primary">Gestionar Categor&iacute;as</h5>
        <p class="text-sm text-muted mb-2">Jerarqu&iacute;a de categor&iacute;as para la clasificaci&oacute;n autom&aacute;tica de productos.</p>
        <a href="<?php echo URLROOT; ?>/webproducts/categorias" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg border border-accent/30 text-accent hover:bg-accent/10 transition-all cursor-pointer bg-transparent no-underline">Ir a Categor&iacute;as</a>
      </div>
    </div>
  </div>
  <div class="card border-emerald-500/30">
    <div class="flex items-center gap-4 p-5">
      <div class="flex-shrink-0 text-emerald-500 text-3xl"><?php echo icon('package', ['size' => 32]); ?></div>
      <div class="flex-1 min-w-0">
        <h5 class="font-semibold mb-1 text-primary">Gestionar Medidas</h5>
        <p class="text-sm text-muted mb-2">Pesos y dimensiones por categor&iacute;a para la plantilla WooCommerce.</p>
        <a href="<?php echo URLROOT; ?>/webproducts/medidas" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg border border-emerald-500/30 text-emerald-600 hover:bg-emerald-500/10 transition-all cursor-pointer bg-transparent no-underline">Ir a Medidas</a>
      </div>
    </div>
  </div>
  <div class="card border-red-400/30">
    <div class="flex items-center gap-4 p-5">
      <div class="flex-shrink-0 text-red-500 text-3xl"><?php echo icon('search', ['size' => 32]); ?></div>
      <div class="flex-1 min-w-0">
        <h5 class="font-semibold mb-1 text-primary">Correcci&oacute;n de errores</h5>
        <p class="text-sm text-muted mb-2">Sube una plantilla WooCommerce ya completada para detectar y corregir errores.</p>
        <a href="<?php echo URLROOT; ?>/webproducts/review" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg border border-red-400/40 text-red-500 hover:bg-red-500/10 transition-all cursor-pointer bg-transparent no-underline">Ir a Correcci&oacute;n</a>
      </div>
    </div>
  </div>
</div>


<!-- ═══════════ Modal: Create Product ═══════════ -->
<div class="wp-modal fixed inset-0 z-[1055] items-center justify-center hidden" id="productModal" role="dialog" aria-labelledby="productModalLabel" aria-modal="true">
  <div class="relative w-full max-w-3xl mx-4">
    <form method="post" action="<?php echo URLROOT; ?>/webproducts/addManualProduct" novalidate id="productForm">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
      <div class="bg-card border border-border rounded-xl shadow-lg overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-border">
          <h5 class="text-lg font-semibold text-primary mb-0" id="productModalLabel">Crear producto manual</h5>
          <button type="button" class="flex items-center justify-center w-8 h-8 rounded-lg hover:bg-muted/10 transition-colors cursor-pointer border-0 bg-transparent text-muted" data-bs-dismiss="modal" aria-label="Cerrar modal"><?php echo icon('x', ['size' => 18]); ?></button>
        </div>
        <div class="px-6 py-5">
          <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-6">
              <label for="modalSku" class="block text-sm font-medium text-muted mb-1.5">SKU <span class="text-red-500" aria-label="requerido">*</span></label>
              <input type="text" name="sku" id="modalSku" class="input" required autocomplete="off" spellcheck="false" autocapitalize="characters" pattern="^[A-Z0-9\-/]+$" aria-describedby="modalSkuHelp">
              <div class="text-xs text-muted mt-1" id="modalSkuHelp">Código único del producto. Solo letras, números, guiones y barras.</div>
              <div class="wp-form-error" role="alert" aria-live="polite"></div>
            </div>
            <div class="md:col-span-6">
              <label for="modalNombre" class="block text-sm font-medium text-muted mb-1.5">Nombre <span class="text-red-500" aria-label="requerido">*</span></label>
              <input type="text" name="nombre" id="modalNombre" class="input" required autocomplete="off" minlength="3" maxlength="200" aria-describedby="modalNombreHelp">
              <div class="text-xs text-muted mt-1" id="modalNombreHelp">Nombre completo del producto (3–200 caracteres)</div>
              <div class="wp-form-error" role="alert" aria-live="polite"></div>
            </div>
            <div class="md:col-span-12">
              <label for="modalDesc" class="block text-sm font-medium text-muted mb-1.5">Descripción</label>
              <textarea name="descripcion" id="modalDesc" class="input min-h-[80px]" rows="3" autocomplete="off" maxlength="2000" aria-describedby="modalDescHelp"></textarea>
              <div class="text-xs text-muted mt-1" id="modalDescHelp">Máximo 2000 caracteres. Se extraerá la primera frase como descripción corta.</div>
              <div class="wp-form-error" role="alert" aria-live="polite"></div>
            </div>
            <div class="md:col-span-4">
              <label for="modalMarca" class="block text-sm font-medium text-muted mb-1.5">Marca</label>
              <input type="text" name="marca" id="modalMarca" class="input" autocomplete="off" maxlength="50">
              <div class="wp-form-error" role="alert" aria-live="polite"></div>
            </div>
            <div class="md:col-span-4">
              <label for="modalTalla" class="block text-sm font-medium text-muted mb-1.5">Tallas</label>
              <input type="text" name="talla" id="modalTalla" class="input" placeholder="Ej: S, M, L" autocomplete="off" aria-describedby="modalTallaHelp">
              <div class="text-xs text-muted mt-1" id="modalTallaHelp">Separar con comas si son varias</div>
              <div class="wp-form-error" role="alert" aria-live="polite"></div>
            </div>
            <div class="md:col-span-4">
              <label for="modalColor" class="block text-sm font-medium text-muted mb-1.5">Color</label>
              <input type="text" name="color" id="modalColor" class="input" placeholder="Ej: Rojo, Azul" autocomplete="off" aria-describedby="modalColorHelp">
              <div class="text-xs text-muted mt-1" id="modalColorHelp">Separar con comas si son varios</div>
              <div class="wp-form-error" role="alert" aria-live="polite"></div>
            </div>
            <div class="md:col-span-4">
              <label for="modalStock" class="block text-sm font-medium text-muted mb-1.5">Inventario</label>
              <input type="number" name="stock" id="modalStock" class="input" value="0" min="0" max="999999" autocomplete="off" inputmode="numeric" aria-describedby="modalStockHelp">
              <div class="text-xs text-muted mt-1" id="modalStockHelp">Cantidad disponible en stock</div>
              <div class="wp-form-error" role="alert" aria-live="polite"></div>
            </div>
          </div>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-border">
          <button type="button" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold text-muted border border-border bg-transparent hover:border-muted hover:text-primary transition-all cursor-pointer" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn-primary" id="productSubmitBtn">
            <?php echo icon('plus', ['size' => 14]); ?> <span>Crear producto</span>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="<?php echo URLROOT; ?>/assets/js/webproducts/index.js"></script>
