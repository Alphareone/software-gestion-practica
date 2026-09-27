<div class="settings-page max-w-full">
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <div class="lg:col-span-5">
      <div class="settings-section">
        <h3 class="settings-heading flex items-center gap-2">
          <?php echo icon('package', ['size' => 18]); ?>
          Agregar medida
        </h3>
        <form method="post" action="<?php echo URLROOT; ?>/webproducts/medidas">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
          <div class="mb-4">
            <label class="block text-sm font-medium text-muted mb-1.5">Categor&iacute;a <span class="text-red-500">*</span></label>
            <input type="text" name="categoria" class="input" required placeholder="Ej: Camiseta">
          </div>
          <div class="grid grid-cols-2 gap-2 mb-4">
            <div>
              <label class="block text-sm font-medium text-muted mb-1.5">Peso (kg)</label>
              <input type="text" name="peso_kg" class="input" placeholder="0.3">
            </div>
            <div>
              <label class="block text-sm font-medium text-muted mb-1.5">Alto (cm)</label>
              <input type="text" name="alto_cm" class="input" placeholder="10">
            </div>
            <div>
              <label class="block text-sm font-medium text-muted mb-1.5">Ancho (cm)</label>
              <input type="text" name="ancho_cm" class="input" placeholder="15">
            </div>
            <div>
              <label class="block text-sm font-medium text-muted mb-1.5">Grueso (cm)</label>
              <input type="text" name="grueso_cm" class="input" placeholder="1">
            </div>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-muted mb-1.5">Variante</label>
            <input type="text" name="variante" class="input" value="Individual" placeholder="Individual">
          </div>
          <button type="submit" class="btn-primary w-full">
            <?php echo icon('circle-plus', ['size' => 16]); ?>
            Agregar medida
          </button>
        </form>
      </div>

      <div class="settings-section">
        <h3 class="settings-heading flex items-center gap-2">
          <?php echo icon('upload', ['size' => 18]); ?>
          Importar medidas
        </h3>
        <form method="post" action="<?php echo URLROOT; ?>/webproducts/importMedidas" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token'] ?? ''); ?>">
          <div class="mb-2">
            <label class="block text-sm font-medium text-muted mb-1.5">Archivo XLSX o XLS</label>
            <div class="flex gap-2">
              <input type="file" name="medidas_file" class="input text-sm" accept=".xlsx,.xls" required>
              <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold bg-accent text-white hover:opacity-90 transition-all cursor-pointer border-0 whitespace-nowrap">
                <?php echo icon('upload', ['size' => 14]); ?>
                Importar
              </button>
            </div>
          </div>
          <div class="mt-2">
            <a href="<?php echo URLROOT; ?>/webproducts/descargarPlantillaMedidas" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg border border-border text-muted hover:bg-border/20 transition-all cursor-pointer bg-transparent no-underline">
              <?php echo icon('download', ['size' => 14]); ?>
              Descargar plantilla
            </a>
          </div>
          <p class="text-muted text-xs mb-0 mt-2">Columnas: <code class="text-xs bg-muted/10 px-1 rounded">categoria</code>, <code class="text-xs bg-muted/10 px-1 rounded">peso_kg</code>, <code class="text-xs bg-muted/10 px-1 rounded">alto_cm</code>, <code class="text-xs bg-muted/10 px-1 rounded">ancho_cm</code>, <code class="text-xs bg-muted/10 px-1 rounded">grueso_cm</code>, <code class="text-xs bg-muted/10 px-1 rounded">variante</code>.</p>
        </form>
      </div>
    </div>

    <div class="lg:col-span-7">
      <div class="settings-section">
        <h3 class="settings-heading flex items-center gap-2">
          <?php echo icon('database', ['size' => 18]); ?>
          <?php echo count($data['medidas']); ?> registro(s)
        </h3>
        <?php if (empty($data['medidas'])): ?>
        <div class="text-center py-12 text-muted">
          <?php echo icon('package', ['size' => 48]); ?>
          <p class="text-sm mt-2">No hay medidas registradas.</p>
        </div>
        <?php else: ?>
        <div class="border border-border rounded-xl overflow-hidden">
          <table class="w-full text-sm border-collapse">
            <thead class="bg-surface/50">
              <tr>
                <th class="text-left text-xs font-medium text-muted uppercase tracking-wider p-3 border-b border-border whitespace-nowrap">Categor&iacute;a</th>
                <th class="text-right text-xs font-medium text-muted uppercase tracking-wider p-3 border-b border-border whitespace-nowrap">Peso</th>
                <th class="text-right text-xs font-medium text-muted uppercase tracking-wider p-3 border-b border-border whitespace-nowrap">Alto</th>
                <th class="text-right text-xs font-medium text-muted uppercase tracking-wider p-3 border-b border-border whitespace-nowrap">Ancho</th>
                <th class="text-right text-xs font-medium text-muted uppercase tracking-wider p-3 border-b border-border whitespace-nowrap">Grueso</th>
                <th class="text-left text-xs font-medium text-muted uppercase tracking-wider p-3 border-b border-border whitespace-nowrap">Variante</th>
                <th class="w-[50px] border-b border-border"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($data['medidas'] as $m): ?>
              <tr class="hover:bg-muted/5 transition-colors">
                <td class="p-3 border-b border-border"><strong class="text-primary"><?php echo htmlspecialchars(ucfirst($m->categoria)); ?></strong></td>
                <td class="text-right p-3 border-b border-border whitespace-nowrap text-muted">
                  <?php echo $m->peso_kg !== null ? htmlspecialchars($m->peso_kg) : '<span class="text-muted text-xs">—</span>'; ?>
                  <span class="text-xs text-muted"> kg</span>
                </td>
                <td class="text-right p-3 border-b border-border whitespace-nowrap text-muted">
                  <?php echo $m->alto_cm !== null ? htmlspecialchars($m->alto_cm) : '<span class="text-muted text-xs">—</span>'; ?>
                  <span class="text-xs text-muted"> cm</span>
                </td>
                <td class="text-right p-3 border-b border-border whitespace-nowrap text-muted">
                  <?php echo $m->ancho_cm !== null ? htmlspecialchars($m->ancho_cm) : '<span class="text-muted text-xs">—</span>'; ?>
                  <span class="text-xs text-muted"> cm</span>
                </td>
                <td class="text-right p-3 border-b border-border whitespace-nowrap text-muted">
                  <?php echo $m->grueso_cm !== null ? htmlspecialchars($m->grueso_cm) : '<span class="text-muted text-xs">—</span>'; ?>
                  <span class="text-xs text-muted"> cm</span>
                </td>
                <td class="p-3 border-b border-border text-muted"><?php echo htmlspecialchars($m->variante ?? '—'); ?></td>
                <td class="p-3 border-b border-border">
                  <form method="post" action="<?php echo URLROOT; ?>/webproducts/deleteMedida/<?php echo $m->id; ?>" onsubmit="return confirm('&iquest;Eliminar medida para &laquo;<?php echo htmlspecialchars($m->categoria); ?>&raquo;?')">
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