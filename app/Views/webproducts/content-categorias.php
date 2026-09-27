<div class="settings-page max-w-full">
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <div class="lg:col-span-5">
      <div class="settings-section">
        <h3 class="settings-heading flex items-center gap-2">
          <?php echo icon('layers', ['size' => 18]); ?>
          Agregar categor&iacute;a
        </h3>
        <form method="post" action="<?php echo URLROOT; ?>/webproducts/categorias">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
          <div class="mb-4">
            <label class="block text-sm font-medium text-muted mb-1.5">Nivel <span class="text-red-500">*</span></label>
            <select name="nivel" class="input">
              <option value="1">1 &mdash; Principal</option>
              <option value="2" selected>2 &mdash; Secundaria</option>
              <option value="3">3 &mdash; Filtro</option>
            </select>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-muted mb-1.5">Principal <span class="text-red-500">*</span></label>
            <input type="text" name="principal" class="input" required placeholder="Ej: Mujer, Hombre">
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-muted mb-1.5">Secundaria</label>
            <input type="text" name="secundaria" class="input" placeholder="Ej: Calzones, Camisetas">
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-muted mb-1.5">Filtro</label>
            <input type="text" name="filtro" class="input" placeholder="Ej: Algod&oacute;n, Deportivo">
          </div>
          <button type="submit" class="btn-primary w-full">
            <?php echo icon('circle-plus', ['size' => 16]); ?>
            Agregar categor&iacute;a
          </button>
        </form>
      </div>

      <div class="settings-section">
        <h3 class="settings-heading flex items-center gap-2">
          <?php echo icon('upload', ['size' => 18]); ?>
          Importar desde archivo
        </h3>
        <form method="post" action="<?php echo URLROOT; ?>/webproducts/importCategorias" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
          <div class="mb-2">
            <label class="block text-sm font-medium text-muted mb-1.5">Archivo XLSX o XLS</label>
            <div class="flex gap-2">
              <input type="file" name="categorias_file" class="input text-sm" accept=".xlsx,.xls" required>
              <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold bg-accent text-white hover:opacity-90 transition-all cursor-pointer border-0 whitespace-nowrap">
                <?php echo icon('upload', ['size' => 14]); ?>
                Importar
              </button>
            </div>
          </div>
          <div class="mt-2">
            <a href="<?php echo URLROOT; ?>/webproducts/descargarPlantillaCategorias" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg border border-border text-muted hover:bg-border/20 transition-all cursor-pointer bg-transparent no-underline">
              <?php echo icon('download', ['size' => 14]); ?>
              Descargar plantilla
            </a>
          </div>
          <p class="text-muted text-xs mb-0 mt-2">Columnas: <code class="text-xs bg-muted/10 px-1 rounded">nivel</code>, <code class="text-xs bg-muted/10 px-1 rounded">principal</code>, <code class="text-xs bg-muted/10 px-1 rounded">secundaria</code>, <code class="text-xs bg-muted/10 px-1 rounded">filtro</code>.</p>
        </form>
      </div>
    </div>

    <div class="lg:col-span-7">
      <div class="settings-section">
        <h3 class="settings-heading flex items-center gap-2">
          <?php echo icon('database', ['size' => 18]); ?>
          <?php echo count($data['categorias']); ?> registro(s)
        </h3>
        <?php if (empty($data['categorias'])): ?>
        <div class="text-center py-12 text-muted">
          <?php echo icon('database', ['size' => 48]); ?>
          <p class="text-sm mt-2">No hay categor&iacute;as registradas.</p>
        </div>
        <?php else: ?>
        <div class="border border-border rounded-xl overflow-hidden">
          <table class="w-full text-sm border-collapse">
            <thead class="bg-surface/50">
              <tr>
                <th class="w-[60px] text-left text-xs font-medium text-muted uppercase tracking-wider p-3 border-b border-border">Nivel</th>
                <th class="text-left text-xs font-medium text-muted uppercase tracking-wider p-3 border-b border-border">Ruta completa</th>
                <th class="w-[50px] border-b border-border"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($data['categorias'] as $c): ?>
              <tr class="hover:bg-muted/5 transition-colors">
                <td class="p-3 border-b border-border">
                  <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full text-white <?php echo $c->nivel == 1 ? 'bg-blue-600' : ($c->nivel == 2 ? 'bg-gray-500' : 'bg-sky-500'); ?>">
                    <?php echo (int)$c->nivel; ?>
                  </span>
                </td>
                <td class="p-3 border-b border-border">
                  <div class="flex items-center gap-1.5 flex-wrap text-muted">
                    <?php
                    $parts = explode(' > ', htmlspecialchars($c->full_path));
                    foreach ($parts as $pi => $part):
                      echo '<span>' . $part . '</span>';
                      if ($pi < count($parts) - 1) echo '<span class="text-muted text-xs opacity-50">›</span>';
                    endforeach;
                    ?>
                  </div>
                </td>
                <td class="p-3 border-b border-border">
                  <form method="post" action="<?php echo URLROOT; ?>/webproducts/deleteCategoria/<?php echo $c->id; ?>" onsubmit="return confirm('&iquest;Eliminar &laquo;<?php echo htmlspecialchars($c->full_path); ?>&raquo;?')">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
                    <button type="submit" class="inline-flex items-center justify-center w-7 h-7 rounded border border-border text-muted hover:bg-red-500/10 hover:text-red-500 hover:border-red-400/30 transition-all cursor-pointer bg-transparent text-xs" title="Eliminar">
                      <?php echo icon('trash-2', ['size' => 13]); ?>
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>