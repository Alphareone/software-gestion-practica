<div class="web-products">

  <div class="flex items-center justify-between flex-wrap gap-2 mb-6">
    <div>
      <p class="text-muted text-sm mb-0">Descarga la plantilla, complétala con tus productos y súbela para generar las variantes.</p>
    </div>
    <a href="<?php echo URLROOT; ?>/productcreator/clearSession" class="btn-secondary text-xs px-3 py-1.5">Limpiar sesión</a>
    <form method="post" action="<?php echo URLROOT; ?>/productcreator/cleanProducts" class="inline" onsubmit="return confirm('¿Eliminar todos los productos de la base de datos? Esta acción no se puede deshacer.');">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
      <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg border border-red-400/40 text-red-500 hover:bg-red-500/10 transition-all cursor-pointer bg-transparent"><?php echo icon('trash-2'); ?> Limpiar productos</button>
    </form>
  </div>

  <?php if (!empty($_SESSION['pc_success'])): ?>
    <div class="relative flex items-start gap-3 p-4 pr-12 mb-4 rounded-lg text-sm bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200/50 dark:border-emerald-800/50 text-emerald-700 dark:text-emerald-300" role="alert">
      <span><?php echo htmlspecialchars($_SESSION['pc_success']); unset($_SESSION['pc_success']); ?></span>
      <button type="button" class="absolute top-2 right-2 w-6 h-6 flex items-center justify-center rounded hover:bg-emerald-200/50 dark:hover:bg-emerald-800/50 transition-colors cursor-pointer border-0 bg-transparent text-emerald-500" onclick="this.closest('[role=alert]').remove()">&times;</button>
    </div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['pc_error'])): ?>
    <div class="relative flex items-start gap-3 p-4 pr-12 mb-4 rounded-lg text-sm bg-red-50 dark:bg-red-950/30 border border-red-200/50 dark:border-red-800/50 text-red-700 dark:text-red-300" role="alert">
      <span><?php echo htmlspecialchars($_SESSION['pc_error']); unset($_SESSION['pc_error']); ?></span>
      <button type="button" class="absolute top-2 right-2 w-6 h-6 flex items-center justify-center rounded hover:bg-red-200/50 dark:hover:bg-red-800/50 transition-colors cursor-pointer border-0 bg-transparent text-red-500" onclick="this.closest('[role=alert]').remove()">&times;</button>
    </div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['pc_warning'])): ?>
    <div class="relative flex items-start gap-3 p-4 pr-12 mb-4 rounded-lg text-sm bg-amber-50 dark:bg-amber-950/30 border border-amber-200/50 dark:border-amber-800/50 text-amber-700 dark:text-amber-300" role="alert">
      <span><?php echo htmlspecialchars($_SESSION['pc_warning']); unset($_SESSION['pc_warning']); ?></span>
      <button type="button" class="absolute top-2 right-2 w-6 h-6 flex items-center justify-center rounded hover:bg-amber-200/50 dark:hover:bg-amber-800/50 transition-colors cursor-pointer border-0 bg-transparent text-amber-500" onclick="this.closest('[role=alert]').remove()">&times;</button>
    </div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['pc_info'])): ?>
    <div class="relative flex items-start gap-3 p-4 pr-12 mb-4 rounded-lg text-sm bg-sky-50 dark:bg-sky-950/30 border border-sky-200/50 dark:border-sky-800/50 text-sky-700 dark:text-sky-300" role="alert">
      <span><?php echo htmlspecialchars($_SESSION['pc_info']); unset($_SESSION['pc_info']); ?></span>
      <button type="button" class="absolute top-2 right-2 w-6 h-6 flex items-center justify-center rounded hover:bg-sky-200/50 dark:hover:bg-sky-800/50 transition-colors cursor-pointer border-0 bg-transparent text-sky-500" onclick="this.closest('[role=alert]').remove()">&times;</button>
    </div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['pc_prompt_clean'])): ?>
    <div class="flex items-center justify-between gap-3 py-2 px-4 mb-4 rounded-lg text-sm bg-sky-100 dark:bg-sky-900/30 border border-sky-200/50 dark:border-sky-700/50 text-sky-700 dark:text-sky-300">
      <span><?php echo icon('info'); ?> La plantilla ya fue descargada. ¿Quieres limpiar la tabla de productos?</span>
      <span class="flex items-center gap-2">
        <form method="post" action="<?php echo URLROOT; ?>/productcreator/cleanProducts" class="inline" onsubmit="return confirm('¿Eliminar todos los productos? Esta acción no se puede deshacer.');">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
          <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg bg-red-600 text-white hover:bg-red-700 transition-all cursor-pointer border-0">Limpiar ahora</button>
        </form>
        <a href="<?php echo URLROOT; ?>/productcreator/clearSession" class="btn-secondary text-xs px-3 py-1.5 ml-1">Ignorar</a>
      </span>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

    <div class="card border-accent">
      <div class="p-5">
        <div class="flex items-start justify-between mb-4">
          <h5 class="text-lg font-semibold text-primary mb-0">1. Importar productos desde Excel</h5>
          <a class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg border border-border text-muted hover:bg-border/20 transition-all cursor-pointer no-underline" data-bs-toggle="collapse" href="#manualImport" role="button"><?php echo icon('book-open'); ?> Manual</a>
        </div>
        <div class="collapse mb-4" id="manualImport">
          <div class="text-sm border border-border rounded-lg p-4 text-primary" class="pc-dark-bg">
            <p class="mb-2"><strong>¿Qué hace esta sección?</strong><br>
            Permite cargar tus productos desde un archivo Excel para que el sistema genere automáticamente todas las variantes (tallas y colores).</p>
            <p class="mb-1"><strong>Paso a paso:</strong></p>
            <ol class="mb-1 ps-4">
              <li>Haz clic en <strong>"Descargar plantilla"</strong> y guarda el archivo XLSX.</li>
              <li>Abre el archivo con Excel o LibreOffice. Completa cada columna:
                <ul class="mb-0">
                  <li><strong>SKU</strong> — Código único del producto padre (ej: POL-NEG).</li>
                  <li><strong>Título</strong> — Nombre del producto.</li>
                  <li><strong>Descripción</strong> — Descripción detallada.</li>
                  <li><strong>Stock</strong> — Cantidad disponible.</li>
                  <li><strong>Género</strong> — <em>Hombre</em>, <em>Mujer</em>, <em>Unisex</em>.</li>
                  <li><strong>Marca</strong> — Nombre de la marca.</li>
                  <li><strong>Categoría</strong> — Ej: <em>polerón</em>, <em>polera</em>, <em>pantalón</em>.</li>
                  <li><strong>Colores</strong> — Separados por coma si hay varios (ej: <em>Negro, Blanco</em>).</li>
                  <li><strong>Tallas</strong> — Separadas por coma (ej: <em>S, M, L</em>).</li>
                  <li><strong>Precio Final</strong> — Precio de venta sin puntos.</li>
                </ul>
              </li>
              <li>Guarda el archivo y vuelve a esta pantalla.</li>
              <li>Selecciona el archivo en <strong>"Plantilla llena (XLSX)"</strong> y haz clic en <strong>"Subir y generar variantes"</strong>.</li>
              <li>Se mostrará una <strong>vista previa</strong> con todas las variantes generadas. Revisa que los datos sean correctos.</li>
              <li>Haz clic en <strong>"Confirmar importación"</strong> para guardar los productos en la base de datos.</li>
            </ol>
            <p class="mb-0 mt-1"><strong>Importar desde cualquier Excel:</strong> si ya tienes un Excel con tus productos (sin usar la plantilla), usa la opción colapsable <em>"Importar desde cualquier Excel (auto-detect)"</em> más abajo. El sistema intentará detectar las columnas automáticamente.</p>
          </div>
        </div>
        <p class="text-muted text-sm mb-4">Descarga la plantilla, completa los productos PADRE (con tallas y colores separados por coma), y súbela. El sistema genera automáticamente las variantes (hijos).</p>

        <div class="grid gap-2 mb-4">
          <a href="<?php echo URLROOT; ?>/productcreator/downloadVariantsTemplate" class="inline-flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-lg text-sm font-semibold bg-amber-500 hover:bg-amber-600 text-white transition-all cursor-pointer border-0 no-underline">
            <?php echo icon('download'); ?> Descargar plantilla
          </a>
        </div>

        <form method="post" action="<?php echo URLROOT; ?>/productcreator/uploadVariants" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
          <div class="mb-4">
            <label class="block text-sm font-medium text-muted mb-1.5">Plantilla llena (XLSX)</label>
            <input type="file" name="variants_file" class="input" accept=".xlsx" required>
          </div>
          <button type="submit" class="btn-primary w-full"><?php echo icon('upload'); ?> Subir y generar variantes</button>
        </form>

        <hr class="my-4 border-border">

        <p class="text-sm text-muted mb-1">
          <a class="no-underline text-muted hover:text-primary transition-colors cursor-pointer" data-bs-toggle="collapse" href="#autoDetectCollapse" role="button">
            <?php echo icon('chevron-down'); ?> Importar desde cualquier Excel (auto-detect)
          </a>
        </p>
        <div class="collapse" id="autoDetectCollapse">
          <form method="post" action="<?php echo URLROOT; ?>/productcreator/uploadSource" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
            <div class="mb-2">
              <input type="file" name="source_file" class="input text-sm" accept=".xlsx" required>
            </div>
            <button type="submit" class="inline-flex items-center justify-center gap-2 w-full px-3 py-1.5 text-sm rounded-lg border border-accent/30 text-accent hover:bg-accent/10 transition-all cursor-pointer bg-transparent">Procesar archivo</button>
          </form>
        </div>
      </div>
    </div>

    <div class="card border-emerald-500/30">
      <div class="p-5">
        <div class="flex items-start justify-between mb-4">
          <h5 class="text-lg font-semibold text-primary mb-0">2. Llenar plantilla MercadoLibre (opcional)</h5>
          <a class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg border border-border text-muted hover:bg-border/20 transition-all cursor-pointer no-underline" data-bs-toggle="collapse" href="#manualMeli" role="button"><?php echo icon('book-open'); ?> Manual</a>
        </div>
        <div class="collapse mb-4" id="manualMeli">
          <div class="text-sm border border-border rounded-lg p-4 text-primary" class="pc-dark-bg">              <p class="mb-2"><strong>¿Qué hace esta sección?</strong><br>
            Toma los productos importados en el paso anterior y llena automáticamente una plantilla de MercadoLibre con todos los datos: SKU, título, precio, stock, color, talla, EAN-13, código de guía de tallas y medidas físicas.</p>
            <p class="mb-1"><strong>Paso a paso:</strong></p>
            <ol class="mb-1 ps-4">
              <li>Descarga la plantilla de publicación masiva desde tu cuenta de MercadoLibre (módulo "Publicaciones" → "Publicar por lote").</li>
              <li>Selecciona la plantilla descargada (.xlsx) en <strong>"Plantilla MeLi (XLSX)"</strong> y haz clic en <strong>"Analizar plantilla"</strong>.</li>
              <li>El sistema mostrará las <strong>pestañas detectadas</strong> con sus columnas. Revisa que la detección sea correcta.</li>
              <li>Si la plantilla contiene una hoja llamada <strong>"Guías de tallas"</strong>, el sistema cargará automáticamente los códigos de guía para hacer coincidir por género y talla.</li>
              <li>Haz clic en <strong>"Llenar y descargar plantilla"</strong>. El sistema procesará todos los productos y generará el archivo listo para subir a MeLi.</li>
              <li>Al finalizar, aparecerá el botón <strong>"Descargar plantilla llenada"</strong>. Guarda el archivo y súbelo a MercadoLibre.</li>
            </ol>
            <p class="mb-0 mt-1"><strong>Importante:</strong> Antes de llenar la plantilla, asegúrate de tener las <strong>medidas físicas</strong> cargadas en la sección "Gestión de medidas" (más abajo). Las medidas se asignan automáticamente según la categoría del producto.</p>
          </div>
        </div>
        <p class="text-muted text-sm mb-4">Una vez importados los productos, sube la plantilla .xlsx de MercadoLibre para llenarla automáticamente.</p>
        <div class="grid grid-cols-1 gap-4">
          <div class="col-span-full">
            <form method="post" action="<?php echo URLROOT; ?>/productcreator/uploadTemplate" enctype="multipart/form-data" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
              <div class="sm:col-span-4">
                <label class="block text-sm font-medium text-muted mb-1.5">Plantilla MeLi (XLSX)</label>
                <input type="file" name="template_file" class="input" accept=".xlsx" required>
              </div>
              <div class="sm:col-span-2">
                <button type="submit" class="inline-flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-lg text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white transition-all cursor-pointer border-0">Analizar plantilla</button>
              </div>
            </form>
          </div>
          <?php if (!empty($data['templateMapping'])): ?>
          <div class="col-span-full">
            <h6 class="text-sm font-semibold text-primary mb-2">Pestañas detectadas (<?php echo count($data['templateMapping']); ?>)</h6>
            <div class="overflow-y-auto border border-border rounded max-h-56 p-1">
              <table class="w-full text-sm border-collapse">
                <thead><tr><th class="text-left font-medium text-muted p-2 border-b border-border">Pestaña</th><th class="text-left font-medium text-muted p-2 border-b border-border">Fila datos</th><th class="text-left font-medium text-muted p-2 border-b border-border">Columnas</th></tr></thead>
                <tbody>
                  <?php foreach ($data['templateMapping'] as $sName => $info): ?>
                  <tr>
                    <td class="p-2 border-b border-border"><strong class="text-primary"><?php echo htmlspecialchars($sName); ?></strong></td>
                    <td class="p-2 border-b border-border text-muted"><?php echo $info['dataStartRow']; ?></td>
                    <td class="p-2 border-b border-border text-muted"><?php echo implode(', ', array_map(fn($c) => $c['header'], $info['cols'])); ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <form method="post" action="<?php echo URLROOT; ?>/productcreator/fillTemplate" class="mt-3">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
              <button type="submit" class="inline-flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-lg text-sm font-semibold bg-amber-500 hover:bg-amber-600 text-white transition-all cursor-pointer border-0">Llenar y descargar plantilla</button>
            </form>
          </div>
          <?php endif; ?>
          <?php if (!empty($_SESSION['pc_download_ready'])): ?>
          <div class="sm:col-span-6">
            <a href="<?php echo URLROOT; ?>/productcreator/download" class="inline-flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-lg text-sm font-semibold bg-sky-600 hover:bg-sky-700 text-white transition-all cursor-pointer border-0 no-underline">Descargar plantilla llenada</a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>

  <?php if (!empty($_SESSION['pc_variants'])): $variants = $_SESSION['pc_variants']; $meta = $_SESSION['pc_variants_meta'] ?? []; ?>
  <div class="card border-accent mb-8">
    <div class="flex items-center justify-between px-5 py-3 border-b border-border">
      <strong class="text-sm text-primary">Vista previa — <?php echo $meta['parents'] ?? 0; ?> padre(s), <?php echo count($variants); ?> variante(s)</strong>
    </div>
    <div class="p-0">
      <div class="overflow-x-auto" style="max-height:400px">
        <table class="w-full text-sm border-collapse">
          <thead class="bg-muted/10">
            <tr>
              <th class="text-left font-medium text-muted p-2 border-b border-border">SKU Padre</th>
              <th class="text-left font-medium text-muted p-2 border-b border-border">SKU</th>
              <th class="text-left font-medium text-muted p-2 border-b border-border">Título</th>
              <th class="text-right font-medium text-muted p-2 border-b border-border">Precio</th>
              <th class="text-center font-medium text-muted p-2 border-b border-border">Stock</th>
              <th class="text-left font-medium text-muted p-2 border-b border-border">Género</th>
              <th class="text-left font-medium text-muted p-2 border-b border-border">Marca</th>
              <th class="text-left font-medium text-muted p-2 border-b border-border">Categoría</th>
              <th class="text-left font-medium text-muted p-2 border-b border-border">Color</th>
              <th class="text-left font-medium text-muted p-2 border-b border-border">Talla</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($variants, 0, 100) as $v): ?>
            <tr class="hover:bg-muted/5 transition-colors">
              <td class="font-mono text-xs p-2 border-b border-border text-muted"><?php echo htmlspecialchars($v[7] ?? ''); ?></td>
              <td class="font-mono text-xs p-2 border-b border-border text-muted"><?php echo htmlspecialchars($v[0] ?? ''); ?></td>
              <td class="p-2 border-b border-border text-primary"><?php echo htmlspecialchars(mb_substr($v[1] ?? '', 0, 80)); ?></td>
              <td class="text-right p-2 border-b border-border text-primary font-mono">$<?php echo number_format((int)($v[3] ?? 0)); ?></td>
              <td class="text-center p-2 border-b border-border text-muted"><?php echo htmlspecialchars($v[10] ?? ''); ?></td>
              <td class="p-2 border-b border-border text-muted"><?php echo htmlspecialchars($v[4] ?? ''); ?></td>
              <td class="p-2 border-b border-border text-muted"><?php echo htmlspecialchars($v[5] ?? ''); ?></td>
              <td class="p-2 border-b border-border text-muted"><?php echo htmlspecialchars($v[6] ?? ''); ?></td>
              <td class="p-2 border-b border-border text-muted"><?php echo htmlspecialchars($v[8] ?? ''); ?></td>
              <td class="p-2 border-b border-border text-muted"><?php echo htmlspecialchars($v[9] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (count($variants) > 100): ?>
            <tr><td colspan="10" class="text-muted text-sm text-center p-2 border-b border-border">... y <?php echo count($variants) - 100; ?> variante(s) más</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="px-5 py-3 border-t border-border">
      <form method="post" action="<?php echo URLROOT; ?>/productcreator/confirmVariants">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
        <button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white transition-all cursor-pointer border-0">Confirmar importación a products_master</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="card border-muted mb-8">
    <div class="flex items-center justify-between px-5 py-3 border-b border-border cursor-pointer" data-bs-toggle="collapse" href="#productCountCollapse" role="button">
      <strong class="text-sm text-primary flex items-center gap-2"><?php echo icon('database'); ?> Productos en base de datos: <?php echo $data['prodCount']; ?></strong>
      <span class="text-muted text-sm"><?php echo icon('chevron-down'); ?></span>
    </div>
    <div class="collapse" id="productCountCollapse">
      <div class="px-5 py-2">
        <?php if ($data['prodCount'] > 0 && !empty($data['prodCategories'])): ?>
          <table class="text-sm border-collapse" style="width:auto">
            <tbody>
              <?php foreach ($data['prodCategories'] as $pc): ?>
              <tr>
                <td class="font-semibold text-primary p-1 pr-4"><?php echo htmlspecialchars($pc->category); ?></td>
                <td class="text-right p-1 text-muted"><?php echo $pc->cnt; ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="text-muted text-sm mb-0">No hay productos importados aún.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card border-sky-500/30 mt-6">
    <div class="flex items-center justify-between px-5 py-3 border-b border-border">
      <strong class="text-sm text-primary flex items-center gap-2"><?php echo icon('package'); ?> Gestión de medidas</strong>
      <span class="flex items-center gap-2">
        <a class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg border border-border text-muted hover:bg-border/20 transition-all cursor-pointer no-underline" data-bs-toggle="collapse" href="#manualMedidas" role="button"><?php echo icon('book-open'); ?> Manual</a>
        <span class="text-muted text-xs">Pesos y dimensiones usados al llenar la plantilla MeLi</span>
      </span>
    </div>
    <div class="collapse" id="manualMedidas">
      <div class="text-sm border-b border-border p-4 text-primary" class="pc-dark-bg">
        <p class="mb-2"><strong>¿Qué hace esta sección?</strong><br>
        Administra las medidas físicas (peso, alto, ancho, grueso) que se asignan automáticamente a los productos al llenar la plantilla de MercadoLibre.</p>
        <p class="mb-1"><strong>Cómo funciona:</strong></p>
        <ul class="mb-1 ps-4">
          <li>Cada producto en <strong>products_master</strong> tiene una categoría. El sistema busca una medida cuya categoría <strong>coincida como substring</strong> dentro del título del producto.</li>
          <li>Por ejemplo: si creas una medida con categoría <em>"poleron"</em>, todos los productos que contengan "poleron" en su título recibirán esas medidas.</li>
          <li>La <strong>variante</strong> permite distinguir entre presentaciones (Individual, Pack, etc.).</li>
        </ul>
        <p class="mb-1"><strong>Cómo agregar una medida manualmente:</strong></p>
        <ol class="mb-1 ps-4">
          <li>Escribe el nombre de la <strong>categoría</strong> (ej: poleron, polera, pantalón).</li>
          <li>Completa <strong>Peso (kg)</strong>, <strong>Alto (cm)</strong>, <strong>Ancho (cm)</strong> y <strong>Grueso (cm)</strong>.</li>
          <li>Selecciona la <strong>Variante</strong> (Individual por defecto).</li>
          <li>Haz clic en <strong>"Agregar medida"</strong>.</li>
        </ol>
        <p class="mb-0"><strong>Importación masiva:</strong> Descarga la plantilla, completa varias filas y súbela con el botón "Importar medidas". Las columnas deben ser: categoria, peso_kg, alto_cm, ancho_cm, grueso_cm, variante.</p>
      </div>
    </div>
    <div class="p-5">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-5">
          <form method="post" action="<?php echo URLROOT; ?>/productcreator/creadorMedidas">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
            <div class="mb-2">
              <label class="block text-sm font-medium text-muted mb-1">Categoría <span class="text-red-500">*</span></label>
              <input type="text" name="categoria" class="input" required placeholder="Ej: poleron">
            </div>
            <div class="grid grid-cols-4 gap-1 mb-2">
              <div>
                <label class="block text-sm font-medium text-muted mb-1">Peso (kg)</label>
                <input type="text" name="peso_kg" class="input" placeholder="0.3">
              </div>
              <div>
                <label class="block text-sm font-medium text-muted mb-1">Alto (cm)</label>
                <input type="text" name="alto_cm" class="input" placeholder="10">
              </div>
              <div>
                <label class="block text-sm font-medium text-muted mb-1">Ancho (cm)</label>
                <input type="text" name="ancho_cm" class="input" placeholder="15">
              </div>
              <div>
                <label class="block text-sm font-medium text-muted mb-1">Grueso (cm)</label>
                <input type="text" name="grueso_cm" class="input" placeholder="1">
              </div>
            </div>
            <div class="mb-2">
              <label class="block text-sm font-medium text-muted mb-1">Variante</label>
              <input type="text" name="variante" class="input" value="Individual">
            </div>
            <button type="submit" class="btn-primary w-full text-sm">Agregar medida</button>
          </form>
        </div>
        <div class="lg:col-span-7">
          <?php if (empty($data['medidas'])): ?>
            <p class="text-muted text-sm mb-0">No hay medidas registradas. Agrega una a la izquierda.</p>
          <?php else: ?>
            <div class="mb-2">
              <input type="text" id="medidasSearch" class="input text-sm" placeholder="Buscar categoría..." oninput="filtrarMedidas(this.value)">
            </div>
            <div class="overflow-x-auto" style="max-height:300px">
              <table class="w-full text-sm border-collapse" id="medidasTable">
                <thead class="bg-muted/10">
                  <tr>
                    <th class="text-left font-medium text-muted p-2 border-b border-border">Categoría</th>
                    <th class="text-left font-medium text-muted p-2 border-b border-border">Peso</th>
                    <th class="text-left font-medium text-muted p-2 border-b border-border">Alto</th>
                    <th class="text-left font-medium text-muted p-2 border-b border-border">Ancho</th>
                    <th class="text-left font-medium text-muted p-2 border-b border-border">Grueso</th>
                    <th class="text-left font-medium text-muted p-2 border-b border-border">Variante</th>
                    <th class="text-left font-medium text-muted p-2 border-b border-border" style="width:40px"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($data['medidas'] as $m): ?>
                  <tr class="hover:bg-muted/5 transition-colors">
                    <td class="p-2 border-b border-border"><strong class="text-primary"><?php echo htmlspecialchars(ucfirst($m->categoria)); ?></strong></td>
                    <td class="p-2 border-b border-border text-muted"><?php echo htmlspecialchars($m->peso_kg ?? '-'); ?> kg</td>
                    <td class="p-2 border-b border-border text-muted"><?php echo htmlspecialchars($m->alto_cm ?? '-'); ?> cm</td>
                    <td class="p-2 border-b border-border text-muted"><?php echo htmlspecialchars($m->ancho_cm ?? '-'); ?> cm</td>
                    <td class="p-2 border-b border-border text-muted"><?php echo htmlspecialchars($m->grueso_cm ?? '-'); ?> cm</td>
                    <td class="p-2 border-b border-border text-muted"><?php echo htmlspecialchars($m->variante ?? '-'); ?></td>
                    <td class="p-2 border-b border-border">
                      <a href="<?php echo URLROOT; ?>/productcreator/creadorDeleteMedida/<?php echo $m->id; ?>" class="inline-flex items-center justify-center w-6 h-6 rounded border border-red-400/40 text-red-500 hover:bg-red-500/10 transition-all cursor-pointer bg-transparent text-sm no-underline" onclick="return confirm('¿Eliminar medida para «<?php echo htmlspecialchars($m->categoria); ?>»?')">&times;</a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <script>
            function filtrarMedidas(val) {
              var filter = val.toLowerCase();
              var rows = document.querySelectorAll('#medidasTable tbody tr');
              for (var i = 0; i < rows.length; i++) {
                var cell = rows[i].querySelector('td strong');
                if (cell) {
                  rows[i].style.display = cell.textContent.toLowerCase().indexOf(filter) > -1 ? '' : 'none';
                }
              }
            }
            </script>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="px-5 py-3 border-t border-border">
      <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
        <div class="sm:col-auto">
          <a href="<?php echo URLROOT; ?>/productcreator/downloadMedidasTemplate" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg border border-sky-500/30 text-sky-600 hover:bg-sky-500/10 transition-all cursor-pointer bg-transparent no-underline"><?php echo icon('download'); ?> Descargar plantilla</a>
        </div>
        <div class="sm:col-span-5">
          <form method="post" action="<?php echo URLROOT; ?>/productcreator/uploadMedidas" enctype="multipart/form-data" class="flex gap-2 items-end">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
            <div class="flex-1">
              <input type="file" name="medidas_file" class="input text-sm" accept=".xlsx,.xls" required>
            </div>
            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs rounded-lg bg-accent text-white hover:opacity-90 transition-all cursor-pointer border-0"><?php echo icon('upload'); ?> Importar medidas</button>
          </form>
        </div>
        <div class="text-muted text-xs text-right sm:col-auto ml-auto">Columnas: categoria, peso_kg, alto_cm, ancho_cm, grueso_cm, variante</div>
      </div>
    </div>
  </div>
</div>