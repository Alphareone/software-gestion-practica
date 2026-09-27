<?php
$connections = $data['connections'] ?? [];
?>

<div class="settings-page" style="max-width:100%;">

  <?php if (!empty($data['dry_run'])): ?>
    <div class="d-badge exp" style="display:inline-block;margin-bottom:1rem;">Modo DRY-RUN activo: no se contacta la API real de Cencosud</div>
  <?php endif; ?>

  <?php if (empty($connections)): ?>
    <div class="dash-empty" style="margin-top:8rem;padding:0;">
      <h3>No hay tiendas Cencosud conectadas</h3>
      <p>Conecta una tienda y sincroniza su catálogo para ver los reportes.</p>
    </div>
  <?php else: ?>

    <!-- Encabezado de Controles de Reporte -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;gap:1rem;flex-wrap:wrap;">
      <div style="display:flex;gap:0.75rem;flex:1;min-width:300px;align-items:center;flex-wrap:wrap;">
        <select id="csStoreSelect" class="rename-input" style="max-width:240px;" onchange="loadReport()">
          <?php foreach ($connections as $c): ?>
            <option value="<?php echo (int)$c->id; ?>"><?php echo htmlspecialchars($c->store_name ?: 'Sin nombre'); ?></option>
          <?php endforeach; ?>
        </select>
        
        <input type="text" id="csSearch" class="rename-input" style="flex:1;min-width:200px;" placeholder="Buscar SKU o producto..." oninput="debounceSearch()">
        
        <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.85rem;color:var(--text-secondary);cursor:pointer;user-select:none;">
          <input type="checkbox" id="csDiscrepancyFilter" onchange="loadReport()" style="width:16px;height:16px;">
          Solo descuadres
        </label>

        <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.85rem;color:#b45309;font-weight:600;cursor:pointer;user-select:none;background:#fffbeb;padding:4px 8px;border-radius:4px;border:1px solid #fde68a;">
          <input type="checkbox" id="csHighStockFilter" onchange="loadReport()" style="width:16px;height:16px;">
          🔥 Alto Stock (&ge;50 u.)
        </label>
      </div>

      <!-- Botones de Acción -->
      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <button type="button" class="ut-btn" onclick="openEmailModal()" style="background:#3b82f6;border-color:#3b82f6;color:white;">
          <?php echo icon('mail', ['size' => 14]); ?>
          Enviar a Bodega / Adquisiciones
        </button>

        <button type="button" class="ut-btn ut-btn-primary" onclick="exportExcel()" style="background:var(--success,#10b981);border-color:var(--success,#10b981);">
          <?php echo icon('download', ['size' => 14]); ?>
          Exportar a Excel
        </button>
      </div>
    </div>

    <!-- Tabla Estilo Planilla Excel -->
    <div class="ut-table-wrap" style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; border: 1px solid var(--border,#ddd); border-radius: 6px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
      <table class="ut-table" style="min-width: 1600px; width: 100%; border-collapse: collapse; font-size: 0.82rem;">
        <thead>
          <tr style="background: var(--bg-secondary,#f3f4f6); border-bottom: 2px solid var(--border,#e5e7eb);">
            <th style="min-width: 150px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb);">SKU / Foto</th>
            <th style="min-width: 110px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb);">Marca</th>
            <th style="min-width: 220px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb);">Producto</th>
            <th style="min-width: 140px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb);">Categoría</th>
            <th style="min-width: 90px;  padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb); text-align: center;">Tipo</th>
            <th style="min-width: 160px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb);">Atributos / Variante</th>
            <th style="min-width: 110px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb); text-align: right;">Stock Bodega</th>
            <th style="min-width: 110px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb); text-align: right;">Stock Cencosud</th>
            <th style="min-width: 100px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb); text-align: right;">Diferencia</th>
            <th style="min-width: 110px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb); text-align: right;">Precio Bodega</th>
            <th style="min-width: 110px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb); text-align: right;">Precio Cencosud</th>
            <th style="min-width: 110px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb); text-align: center;">Estado</th>
            <th style="min-width: 140px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb); text-align: center;">Medidas</th>
            <th style="min-width: 100px; padding: 10px 12px; font-weight: 600; text-align: center;">Acciones</th>
          </tr>
        </thead>
        <tbody id="csReportsBody">
          <tr><td colspan="14" style="text-align:center;padding:2rem;color:var(--text-secondary);">Cargando reporte...</td></tr>
        </tbody>
      </table>
    </div>

  <?php endif; ?>
</div>

<!-- Modal Enviar Reporte por Correo -->
<div id="csEmailModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
  <div style="background:var(--bg-primary,#ffffff); width:90%; max-width:520px; border-radius:8px; overflow:hidden; box-shadow:0 10px 25px -5px rgba(0,0,0,0.2); display:flex; flex-direction:column;">
    <div style="padding:1rem; border-bottom:1px solid var(--border,#e5e7eb); display:flex; justify-content:space-between; align-items:center; background:var(--bg-secondary,#f9fafb);">
      <h3 style="margin:0; font-size:1.05rem; font-weight:600; color:var(--text-primary,#111827);">Enviar Reporte a Bodega / Adquisiciones</h3>
      <button type="button" onclick="closeEmailModal()" style="background:none; border:none; font-size:1.5rem; line-height:1; cursor:pointer; color:var(--text-secondary,#6b7280);">&times;</button>
    </div>
    <div style="padding:1.25rem; display:flex; flex-direction:column; gap:1rem;">
      <p style="margin:0; font-size:0.83rem; color:var(--text-secondary);">
        Genera y adjunta automáticamente una planilla Excel <code>.xlsx</code> con la ficha técnica completa y atributos de todos los productos con <strong>Alto Stock (&ge; 50 unidades)</strong>.
      </p>

      <div>
        <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--text-primary); margin-bottom:0.4rem;">Correos electrónicos de destino</label>
        <textarea id="csEmailInput" class="rename-input" style="width:100%; height:75px; font-size:0.85rem; padding:8px;" placeholder="bodega@empresa.cl, adquisiciones@empresa.cl"></textarea>
        <span style="font-size:0.72rem; color:var(--text-secondary);">Puedes ingresar varios correos separados por comas o espacios.</span>
      </div>
    </div>
    <div style="padding:1rem; border-top:1px solid var(--border,#e5e7eb); display:flex; justify-content:end; gap:0.5rem; background:var(--bg-secondary,#f9fafb);">
      <button type="button" class="ut-btn" onclick="closeEmailModal()">Cancelar</button>
      <button type="button" id="csSendMailBtn" class="ut-btn" onclick="sendEmailReport()" style="background:#3b82f6; border-color:#3b82f6; color:white;">Enviar Reporte Excel</button>
    </div>
  </div>
</div>

<?php if (!empty($connections)): ?>
<script>
(function() {
  var BASE = '<?php echo URLROOT; ?>/cencosud';
  var CSRF = '<?php echo htmlspecialchars($data['csrf_token']); ?>';
  var searchTimer = null;

  function esc(s) {
    var div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
  }

  function formatPrice(p) {
    try {
      return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(p);
    } catch (e) {
      return '$' + p;
    }
  }

  window.debounceSearch = function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadReport, 300);
  };

  window.loadReport = function() {
    var store = document.getElementById('csStoreSelect').value;
    var query = document.getElementById('csSearch').value;
    var discrepancy = document.getElementById('csDiscrepancyFilter').checked ? '1' : '0';
    var highStock = document.getElementById('csHighStockFilter').checked ? '1' : '0';

    var body = document.getElementById('csReportsBody');
    body.innerHTML = '<tr><td colspan="13" style="text-align:center;padding:2rem;color:var(--text-secondary);">Generando comparación...</td></tr>';

    var url = BASE + '/reportsData?store=' + store + '&q=' + encodeURIComponent(query) + '&discrepancy=' + discrepancy + '&high_stock=' + highStock;
    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (res.error) {
          body.innerHTML = '<tr><td colspan="13" style="text-align:center;padding:2rem;color:var(--warning);">' + esc(res.error) + '</td></tr>';
          return;
        }

        var items = res.items || [];
        if (items.length === 0) {
          body.innerHTML = '<tr><td colspan="13" style="text-align:center;padding:2rem;color:var(--text-secondary);">No se encontraron productos bajo los filtros seleccionados.</td></tr>';
          return;
        }

        var html = '';
        items.forEach(function(item) {
          var mStock = item.master_stock !== null ? parseInt(item.master_stock) : 0;
          var cStock = item.cencosud_stock !== null ? parseInt(item.cencosud_stock) : 0;
          var diff = mStock - cStock;

          var mPrice = item.master_price !== null ? parseFloat(item.master_price) : 0.0;
          var cPrice = item.cencosud_price !== null ? parseFloat(item.cencosud_price) : 0.0;

          // Estilo de celda diferencia
          var diffStyle = 'border-right: 1px solid var(--border,#e5e7eb); text-align: right;';
          var diffBadge = esc(diff);
          if (diff !== 0) {
            diffStyle += 'background-color: var(--warning-light,#fffbeb); color: var(--warning,#d97706); font-weight: 600;';
            diffBadge = '<span class="d-badge exp" style="margin:0;">' + esc(diff) + '</span>';
          }

          // Estado Promocional
          var promoBadge = '<span style="color:var(--text-secondary);">—</span>';
          if (cPrice > 0 && mPrice > 0 && cPrice < mPrice) {
            var pct = Math.round((1 - (cPrice / mPrice)) * 100);
            promoBadge = '<span class="d-badge ok" style="margin:0; background-color: #dcfce7; color: #15803d;">Oferta (' + pct + '%)</span>';
          }

          // Renderizado de SKU y Foto
          var thumbHtml = '';
          if (item.thumbnail) {
            var secureThumb = item.thumbnail.replace('http://', 'https://');
            thumbHtml = '<div style="width:36px;height:36px;border-radius:4px;overflow:hidden;border:1px solid var(--border,#e5e7eb);background:#f9fafb;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
                      + '<img src="' + esc(secureThumb) + '" style="max-width:100%;max-height:100%;object-fit:cover;">'
                      + '</div>';
          }

          var skuCell = '<div style="display:flex;align-items:center;gap:0.5rem;">'
                      + thumbHtml
                      + '<div style="display:flex;flex-direction:column;">'
                      + '<span style="font-family:monospace;font-weight:600;color:var(--text-primary);">' + esc(item.sku) + '</span>'
                      + (item.thumbnail ? '<a href="' + esc(item.thumbnail.replace('http://', 'https://')) + '" target="_blank" style="font-size:0.7rem;color:#3b82f6;text-decoration:none;">Link foto</a>' : '')
                      + '</div>'
                      + '</div>';

          // Medidas Ficha Técnica (Alto x Ancho x Profundidad | Peso + Cálculo Despacho Volumétrico)
          var profundidad = item.profundidad || item.largo;
          var measuresHtml = '<span class="d-badge exp" style="margin:0; background:#fef3c7; color:#b45309;">⚠️ Incompleto</span>';
          if (item.peso || item.alto || item.ancho || profundidad) {
            var alt = parseFloat(item.alto) || 0;
            var anc = parseFloat(item.ancho) || 0;
            var prof = parseFloat(profundidad) || 0;
            var volWeight = (alt > 0 && anc > 0 && prof > 0) ? ((alt * anc * prof) / 4000) : 0;
            var pReal = parseFloat(item.peso) || 0;
            var pCobro = Math.max(pReal, volWeight);

            measuresHtml = '<div style="font-size:0.75rem;line-height:1.25;display:flex;flex-direction:column;gap:2px;text-align:left;">'
              + '<span><b>Peso Real:</b> ' + esc(item.peso || '—') + ' kg</span>'
              + '<span style="color:var(--text-secondary,#4b5563);white-space:nowrap;" title="Alto x Ancho x Profundidad"><b>Medidas:</b> ' + esc(item.alto || '—') + 'x' + esc(item.ancho || '—') + 'x' + esc(profundidad || '—') + ' cm</span>'
              + (volWeight > 0 ? '<span style="font-size:0.7rem;color:#0284c7;font-weight:600;" title="Peso tarifado por Courier (Envíame/Paris) para cálculo de costo de envío">📦 Despacho: ' + pCobro.toFixed(2) + ' kg (Vol)</span>' : '')
              + '</div>';
          }

          // Atributos de Variante (Color, Talla, Género)
          var attrsList = [];
          if (item.master_color) attrsList.push('<b>Color:</b> ' + esc(item.master_color));
          if (item.master_size) attrsList.push('<b>Talla:</b> ' + esc(item.master_size));
          if (item.master_gender) attrsList.push('<b>Género:</b> ' + esc(item.master_gender));
          var attrsHtml = attrsList.length > 0 
            ? '<div style="font-size:0.75rem;line-height:1.2;display:flex;flex-direction:column;gap:1px;">' + attrsList.join('<br>') + '</div>'
            : '<span style="color:var(--text-secondary);font-size:0.75rem;">—</span>';

          html += '<tr style="border-bottom: 1px solid var(--border,#e5e7eb);">';
          html += '<td style="padding: 8px 12px; border-right: 1px solid var(--border,#e5e7eb);">' + skuCell + '</td>';
          html += '<td style="padding: 8px 12px; border-right: 1px solid var(--border,#e5e7eb); font-weight: 500;">' + esc(item.master_brand !== null ? item.master_brand : '—') + '</td>';
          html += '<td style="padding: 8px 12px; border-right: 1px solid var(--border,#e5e7eb); font-weight: 500;" title="' + esc(item.master_description || '') + '">' + esc(item.cencosud_title) + '</td>';
          html += '<td style="padding: 8px 12px; border-right: 1px solid var(--border,#e5e7eb); font-weight: 500;">' + esc(item.master_category || '—') + '</td>';
          html += '<td style="padding: 8px 12px; border-right: 1px solid var(--border,#e5e7eb); text-align: center;">';
          if (item.tipo === 'Pack') {
            html += '<span class="d-badge country" style="margin:0; background: #e0f2fe; color: #0369a1;">Pack</span>';
          } else if (item.tipo === 'Tripack') {
            html += '<span class="d-badge country" style="margin:0; background: #faf5ff; color: #6b21a8;">Tripack</span>';
          } else {
            html += '<span class="d-badge" style="margin:0; background: #f3f4f6; color: #4b5563;">Unidad</span>';
          }
          html += '</td>';
          html += '<td style="padding: 8px 12px; border-right: 1px solid var(--border,#e5e7eb);">' + attrsHtml + '</td>';
          html += '<td style="padding: 8px 12px; border-right: 1px solid var(--border,#e5e7eb); text-align: right; font-weight: ' + (mStock >= 50 ? '700; color:#15803d;' : 'normal;') + '">' + esc(item.master_stock !== null ? item.master_stock : '—') + '</td>';
          html += '<td style="padding: 8px 12px; border-right: 1px solid var(--border,#e5e7eb); text-align: right; font-weight: ' + (cStock >= 50 ? '700; color:#15803d;' : 'normal;') + '">' + esc(item.cencosud_stock !== null ? item.cencosud_stock : '—') + '</td>';
          html += '<td style="' + diffStyle + '">' + diffBadge + '</td>';
          html += '<td style="padding: 8px 12px; border-right: 1px solid var(--border,#e5e7eb); text-align: right; color: var(--text-secondary);">' + formatPrice(mPrice) + '</td>';
          html += '<td style="padding: 8px 12px; border-right: 1px solid var(--border,#e5e7eb); text-align: right; font-weight: 600;">' + formatPrice(cPrice) + '</td>';
          html += '<td style="padding: 8px 12px; border-right: 1px solid var(--border,#e5e7eb); text-align: center;">' + promoBadge + '</td>';
          html += '<td style="padding: 8px 12px; border-right: 1px solid var(--border,#e5e7eb); text-align: center;">' + measuresHtml + '</td>';
          
          // Acciones de Réplica
          html += '<td style="padding: 6px 12px; text-align: center; display: flex; gap: 0.35rem; justify-content: center; align-items: center; min-height: 48px;">';
          if (diff !== 0) {
            html += '<button type="button" class="ut-btn" onclick="replicate(\'' + esc(item.sku) + '\')" style="padding:2px 6px; font-size:0.72rem;" title="Igualar Cencosud a Stock Bodega">Replicar</button>';
          } else {
            html += '<span style="color:var(--text-muted); font-size:0.72rem;">Sin descuadres</span>';
          }
          html += '</td>';
          html += '</tr>';
        });

        body.innerHTML = html;
      })
      .catch(function() {
        body.innerHTML = '<tr><td colspan="14" style="text-align:center;padding:2rem;color:var(--warning);">Error al consultar el servidor.</td></tr>';
      });
  };

  window.exportExcel = function() {
    var store = document.getElementById('csStoreSelect').value;
    var query = document.getElementById('csSearch').value;
    var discrepancy = document.getElementById('csDiscrepancyFilter').checked ? '1' : '0';
    var highStock = document.getElementById('csHighStockFilter').checked ? '1' : '0';
    
    window.location.href = BASE + '/exportReportsXlsx?store=' + store + '&q=' + encodeURIComponent(query) + '&discrepancy=' + discrepancy + '&high_stock=' + highStock;
  };

  window.replicate = function(sku) {
    var store = document.getElementById('csStoreSelect').value;
    var fd = new FormData();
    fd.append('store', store);
    fd.append('sku', sku);
    fd.append('csrf_token', CSRF);

    fetch(BASE + '/replicateStock', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (res.ok) {
          if (typeof showToast === 'function') showToast('Stock replicado en Cencosud correctamente.', 'success');
          loadReport();
        } else {
          if (typeof showToast === 'function') showToast(res.error || 'Error al replicar stock.', 'error');
        }
      });
  };

  window.openEmailModal = function() {
    document.getElementById('csEmailModal').style.display = 'flex';
  };

  window.closeEmailModal = function() {
    document.getElementById('csEmailModal').style.display = 'none';
  };

  window.sendEmailReport = function() {
    var store = document.getElementById('csStoreSelect').value;
    var emails = document.getElementById('csEmailInput').value.trim();
    var btn = document.getElementById('csSendMailBtn');

    if (!emails) {
      alert('Ingresa al menos un correo electrónico de destino.');
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Enviando correo...';

    var fd = new FormData();
    fd.append('store', store);
    fd.append('emails', emails);
    fd.append('min_stock', '50');
    fd.append('csrf_token', CSRF);

    fetch(BASE + '/sendHighStockEmail', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        btn.disabled = false;
        btn.textContent = 'Enviar Reporte Excel';
        if (res.ok) {
          if (typeof showToast === 'function') showToast(res.message || 'Correo enviado con éxito.', 'success');
          closeEmailModal();
        } else {
          alert(res.error || 'Error al enviar el correo.');
        }
      })
      .catch(function() {
        btn.disabled = false;
        btn.textContent = 'Enviar Reporte Excel';
        alert('Error de conexión al servidor.');
      });
  };

  // Carga inicial y listeners de eventos
  var reportTimer = null;
  document.addEventListener('DOMContentLoaded', function() {
    loadReport();

    var searchInput = document.getElementById('csSearch');
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        clearTimeout(reportTimer);
        reportTimer = setTimeout(loadReport, 300);
      });
    }

    var discFilter = document.getElementById('csDiscrepancyFilter');
    if (discFilter) discFilter.addEventListener('change', loadReport);

    var highFilter = document.getElementById('csHighStockFilter');
    if (highFilter) highFilter.addEventListener('change', loadReport);

    var storeSelect = document.getElementById('csStoreSelect');
    if (storeSelect) storeSelect.addEventListener('change', loadReport);
  });
})();
</script>
<?php endif; ?>
