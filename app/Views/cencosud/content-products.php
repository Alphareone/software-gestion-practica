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
      <p>Conecta una tienda y sincroniza su catálogo para explorar los productos.</p>
    </div>
  <?php else: ?>

    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem;">
      <select id="csStoreSelect" class="rename-input" style="max-width:240px;">
        <?php foreach ($connections as $c): ?>
          <option value="<?php echo (int)$c->id; ?>"><?php echo htmlspecialchars($c->store_name ?: 'Sin nombre'); ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" id="csSearch" class="rename-input" style="flex:1;min-width:200px;" placeholder="Buscar por título o SKU...">
      <select id="csStatusFilter" class="rename-input" style="max-width:180px;">
        <option value="">Todos los estados</option>
        <option value="PUBLISHED">Publicados</option>
        <option value="UNPUBLISHED">No publicados</option>
      </select>
      <select id="csLimitSelect" class="rename-input" style="max-width:140px;" title="Productos por página">
        <option value="20">20 por pág.</option>
        <option value="50" selected>50 por pág.</option>
        <option value="100">100 por pág.</option>
      </select>
      <button type="button" class="ut-btn" onclick="openTemplateModal()" style="background:#10b981; border-color:#10b981; color:white; display:inline-flex; align-items:center; gap:0.5rem;">
        <?php echo icon('plus', ['size' => 14]); ?>
        Traer Plantilla
      </button>
    </div>

    <div class="ut-table-wrap" style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; border: 1px solid var(--border,#ddd); border-radius: 6px;">
      <table class="ut-table" style="min-width: 1450px; width: 100%; border-collapse: collapse;">
        <thead>
          <tr style="background: var(--bg-secondary,#f3f4f6); border-bottom: 2px solid var(--border,#e5e7eb);">
            <th style="min-width:150px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb);">SKU / Foto</th>
            <th style="min-width:110px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb);">Marca</th>
            <th style="min-width:220px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb);">Producto</th>
            <th style="min-width:140px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb);">Categoría</th>
            <th style="min-width:90px;  padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb); text-align: center;">Tipo</th>
            <th style="min-width:160px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb);">Atributos / Variante</th>
            <th style="min-width:100px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb); text-align: right;">Stock</th>
            <th style="min-width:110px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb); text-align: right;">Precio</th>
            <th style="min-width:140px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb); text-align: center;">Medidas</th>
            <th style="min-width:110px; padding: 10px 12px; font-weight: 600; border-right: 1px solid var(--border,#e5e7eb); text-align: center;">Estado</th>
            <th style="min-width:130px; padding: 10px 12px; font-weight: 600; text-align: center;">Sincronizado</th>
          </tr>
        </thead>
        <tbody id="csProductsBody">
          <tr><td colspan="11" style="text-align:center;padding:2rem;color:var(--text-secondary);">Cargando…</td></tr>
        </tbody>
      </table>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.75rem;">
      <span style="font-size:0.78rem;color:var(--text-secondary);" id="csPagingInfo"></span>
      <div style="display:flex;gap:0.5rem;">
        <button type="button" class="ut-btn" id="csPrevBtn" onclick="csGoPage(-1)">Anterior</button>
        <button type="button" class="ut-btn" id="csNextBtn" onclick="csGoPage(1)">Siguiente</button>
      </div>
    </div>

  <?php endif; ?>
</div>

<?php if (!empty($connections)): ?>
<script>
(function() {
  var BASE = '<?php echo URLROOT; ?>/cencosud';
  var state = { page: 1, totalPages: 1, limit: 50, q: '', status: '' };
  var searchTimer = null;

  function esc(s) {
    var div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
  }

  function formatPrice(p, currency) {
    try {
      return new Intl.NumberFormat('es-CL', { style: 'currency', currency: currency || 'CLP', maximumFractionDigits: 0 }).format(p);
    } catch (e) {
      return (currency || 'CLP') + ' ' + p;
    }
  }

  function load() {
    var store = document.getElementById('csStoreSelect').value;
    var limitVal = document.getElementById('csLimitSelect') ? document.getElementById('csLimitSelect').value : state.limit;
    var url = BASE + '/productsData?store=' + store + '&page=' + state.page + '&limit=' + limitVal
      + '&q=' + encodeURIComponent(state.q) + '&status=' + encodeURIComponent(state.status);
    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(res) {
        var body = document.getElementById('csProductsBody');
        if (res.error) {
          body.innerHTML = '<tr><td colspan="11" style="text-align:center;padding:2rem;">' + esc(res.error) + '</td></tr>';
          return;
        }
        if (!res.items || res.items.length === 0) {
          body.innerHTML = '<tr><td colspan="11" style="text-align:center;padding:2rem;color:var(--text-secondary);">Sin productos. Sincroniza la tienda desde el Panel Cencosud.</td></tr>';
          document.getElementById('csPagingInfo').textContent = '';
          state.totalPages = 1;
          updateButtons();
          return;
        }
        var rows = res.items.map(function(it) {
          var statusBadge = it.published_status === 'PUBLISHED'
            ? '<span class="d-badge ok">Publicado</span>'
            : '<span class="d-badge exp">' + esc(it.published_status || '—') + '</span>';
          var stock = it.available_quantity === null ? '—' : it.available_quantity;
          var stockStyle = (it.available_quantity !== null && it.available_quantity <= 5) ? 'color:var(--warning,#d97706);font-weight:600;' : '';

          // Tipo
          var skuUpper = esc(it.sku).toUpperCase();
          var tipo = 'Unidad';
          if (skuUpper.indexOf('TRIPACK-') === 0) {
            tipo = '<span class="d-badge country" style="margin:0; background:#faf5ff; color:#6b21a8;">Tripack</span>';
          } else if (skuUpper.indexOf('PACK-') === 0) {
            tipo = '<span class="d-badge country" style="margin:0; background:#e0f2fe; color:#0369a1;">Pack</span>';
          } else {
            tipo = '<span class="d-badge" style="margin:0; background:#f3f4f6; color:#4b5563;">Unidad</span>';
          }

          // Thumbnail
          var thumbHtml = '';
          if (it.thumbnail) {
            var secureThumb = it.thumbnail.replace('http://', 'https://');
            thumbHtml = '<div style="width:36px;height:36px;border-radius:4px;overflow:hidden;border:1px solid var(--border,#e5e7eb);background:#f9fafb;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
              + '<img src="' + esc(secureThumb) + '" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display=\'none\';">'
              + '</div>';
          } else {
            thumbHtml = '<div style="width:36px;height:36px;border-radius:4px;background:#f3f4f6;color:#9ca3af;display:flex;align-items:center;justify-content:center;font-size:0.65rem;border:1px solid #e5e7eb;flex-shrink:0;font-weight:600;" title="Sin imagen">No foto</div>';
          }

          var photoLink = '';
          if (it.thumbnail) {
            photoLink = '<a href="' + esc(it.thumbnail) + '" target="_blank" style="font-size:0.65rem;color:#3b82f6;text-decoration:none;display:block;margin-top:2px;" class="hover:underline">Link foto</a>';
          }

          var skuCell = '<div style="display:flex;align-items:center;gap:0.5rem;padding:4px 0;">'
            + thumbHtml
            + '<div style="display:flex;flex-direction:column;">'
            + '<code style="font-family:monospace;font-weight:600;font-size:0.78rem;">' + esc(it.sku) + '</code>'
            + photoLink
            + '</div>'
            + '</div>';

          // Atributos de Variante (Color, Talla, Género)
          var attrsList = [];
          if (it.master_color) attrsList.push('<b>Color:</b> ' + esc(it.master_color));
          if (it.master_size) attrsList.push('<b>Talla:</b> ' + esc(it.master_size));
          if (it.master_gender) attrsList.push('<b>Género:</b> ' + esc(it.master_gender));
          var attrsHtml = attrsList.length > 0 
            ? '<div style="font-size:0.75rem;line-height:1.2;display:flex;flex-direction:column;gap:1px;">' + attrsList.join('<br>') + '</div>'
            : '<span style="color:var(--text-secondary);font-size:0.75rem;">—</span>';

          // Medidas Ficha Técnica (Alto x Ancho x Profundidad | Peso + Cálculo Despacho Volumétrico)
          var profundidad = it.profundidad || it.largo;
          var measuresHtml = '';
          var hasMeasures = it.peso || it.alto || it.ancho || profundidad;
          if (hasMeasures) {
            var alt = parseFloat(it.alto) || 0;
            var anc = parseFloat(it.ancho) || 0;
            var prof = parseFloat(profundidad) || 0;
            var volWeight = (alt > 0 && anc > 0 && prof > 0) ? ((alt * anc * prof) / 4000) : 0;
            var pReal = parseFloat(it.peso) || 0;
            var pCobro = Math.max(pReal, volWeight);

            measuresHtml = '<div style="font-size:0.75rem;line-height:1.25;display:flex;flex-direction:column;gap:2px;text-align:left;">'
              + '<span><b>Peso Real:</b> ' + esc(it.peso || '—') + ' kg</span>'
              + '<span style="color:var(--text-secondary,#4b5563);white-space:nowrap;" title="Alto x Ancho x Profundidad"><b>Medidas:</b> ' + esc(it.alto || '—') + 'x' + esc(it.ancho || '—') + 'x' + esc(profundidad || '—') + ' cm</span>'
              + (volWeight > 0 ? '<span style="font-size:0.7rem;color:#0284c7;font-weight:600;" title="Peso tarifado por Courier (Envíame/Paris) para cálculo de costo de envío">📦 Despacho: ' + pCobro.toFixed(2) + ' kg (Vol)</span>' : '')
              + '</div>';
          } else {
            measuresHtml = '<span class="d-badge" style="background:#fef3c7;color:#d97706;border:1px solid #fde68a;font-size:0.68rem;padding:2px 4px;margin:0;display:inline-block;" title="Dimensiones (Alto, Ancho, Profundidad) y peso requeridos por Cencosud">⚠️ Incompleto</span>';
          }

          return '<tr class="ut-tr">'
            + '<td>' + skuCell + '</td>'
            + '<td style="font-size:0.78rem;font-weight:500;">' + esc(it.master_brand || '—') + '</td>'
            + '<td style="max-width:320px;" title="' + esc(it.master_description || '') + '">' + esc(it.title) + '</td>'
            + '<td style="font-size:0.78rem;">' + esc(it.master_category || '—') + '</td>'
            + '<td style="text-align:center;">' + tipo + '</td>'
            + '<td>' + attrsHtml + '</td>'
            + '<td style="text-align:right;' + stockStyle + '">' + stock + '</td>'
            + '<td style="text-align:right;font-weight:600;">' + formatPrice(it.price, it.currency) + '</td>'
            + '<td style="text-align:center;">' + measuresHtml + '</td>'
            + '<td style="text-align:center;">' + statusBadge + '</td>'
            + '<td style="text-align:center;font-size:0.72rem;color:var(--text-secondary);">' + esc(it.synced_at || '') + '</td>'
            + '</tr>';
        });
        body.innerHTML = rows.join('');
        state.totalPages = res.paging.total_pages;
        state.page = res.paging.page;
        document.getElementById('csPagingInfo').textContent =
          res.paging.total + ' productos — página ' + res.paging.page + ' de ' + res.paging.total_pages;
        updateButtons();
      })
      .catch(function() {
        document.getElementById('csProductsBody').innerHTML =
          '<tr><td colspan="9" style="text-align:center;padding:2rem;">Error de red.</td></tr>';
      });
  }

  function updateButtons() {
    document.getElementById('csPrevBtn').disabled = state.page <= 1;
    document.getElementById('csNextBtn').disabled = state.page >= state.totalPages;
  }

  window.csGoPage = function(delta) {
    var next = state.page + delta;
    if (next < 1 || next > state.totalPages) return;
    state.page = next;
    load();
  };

  document.getElementById('csStoreSelect').addEventListener('change', function() { state.page = 1; load(); });
  document.getElementById('csSearch').addEventListener('input', function() {
    var val = this.value;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() {
      state.q = val;
      state.page = 1;
      load();
    }, 300);
  });
  document.getElementById('csStatusFilter').addEventListener('change', function() {
    state.status = this.value;
    state.page = 1;
    load();
  });
  if (document.getElementById('csLimitSelect')) {
    document.getElementById('csLimitSelect').addEventListener('change', function() {
      state.limit = parseInt(this.value);
      state.page = 1;
      load();
    });
  }
  var currentFoundProduct = null;

  window.openTemplateModal = function() {
    document.getElementById('csTemplateModal').style.display = 'flex';
    document.getElementById('csModalSearchSku').value = '';
    document.getElementById('csModalSearchFeedback').textContent = '';
    document.getElementById('csModalSearchResults').style.display = 'none';
    document.getElementById('csTemplateForm').style.display = 'none';
    document.getElementById('csModalSubmitBtn').disabled = true;
    currentFoundProduct = null;
  };

  window.closeTemplateModal = function() {
    document.getElementById('csTemplateModal').style.display = 'none';
  };

  window.searchSkuInMaster = function() {
    var skuInput = document.getElementById('csModalSearchSku').value.trim();
    var feedback = document.getElementById('csModalSearchFeedback');
    var resultsArea = document.getElementById('csModalSearchResults');
    var form = document.getElementById('csTemplateForm');
    
    if (!skuInput) {
      feedback.style.color = 'var(--warning,#d97706)';
      feedback.textContent = 'Por favor escribe un SKU a buscar.';
      return;
    }

    feedback.style.color = 'var(--text-secondary,#4b5563)';
    feedback.textContent = 'Buscando en bodega...';
    resultsArea.style.display = 'none';
    form.style.display = 'none';
    document.getElementById('csModalSubmitBtn').disabled = true;

    fetch(BASE + '/searchMasterProduct?sku=' + encodeURIComponent(skuInput))
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (res.error) {
          feedback.style.color = 'var(--warning,#b91c1c)';
          feedback.textContent = res.error;
          return;
        }

        feedback.style.color = 'var(--success,#15803d)';
        feedback.textContent = '¡Producto encontrado!';
        
        currentFoundProduct = res.product;
        
        // Mostrar vista previa
        document.getElementById('csModalResultTitle').textContent = res.product.title;
        document.getElementById('csModalResultSku').textContent = 'SKU: ' + res.product.sku;
        
        var imgDiv = document.getElementById('csModalResultImg');
        if (res.product.thumbnail) {
          imgDiv.innerHTML = '<img src="' + esc(res.product.thumbnail.replace('http://', 'https://')) + '" style="width:100%;height:100%;object-fit:cover;">';
        } else {
          imgDiv.innerHTML = '<span style="font-size:0.6rem;color:#9ca3af;font-weight:600;">No foto</span>';
        }

        resultsArea.style.display = 'flex';
      })
      .catch(function() {
        feedback.style.color = 'var(--warning,#b91c1c)';
        feedback.textContent = 'Error al consultar el servidor.';
      });
  };

  window.loadTemplateIntoForm = function() {
    if (!currentFoundProduct) return;
    
    var form = document.getElementById('csTemplateForm');
    
    // Auto-llenar campos
    document.getElementById('csFormSku').value = currentFoundProduct.sku;
    document.getElementById('csDispSku').value = currentFoundProduct.sku;
    document.getElementById('csFormTitle').value = currentFoundProduct.title || '';
    document.getElementById('csFormBrand').value = currentFoundProduct.brand || '';
    document.getElementById('csFormCategory').value = currentFoundProduct.category || '';
    document.getElementById('csFormThumbnail').value = currentFoundProduct.thumbnail || '';
    document.getElementById('csFormDescription').value = currentFoundProduct.description || '';
    
    // Auto-llenar medidas pre-calculadas en la plantilla
    document.getElementById('csFormPeso').value = currentFoundProduct.peso || '';
    document.getElementById('csFormAlto').value = currentFoundProduct.alto || '';
    document.getElementById('csFormAncho').value = currentFoundProduct.ancho || '';
    document.getElementById('csFormLargo').value = currentFoundProduct.largo || '';
    
    form.style.display = 'flex';
    document.getElementById('csModalSubmitBtn').disabled = false;
  };

  window.saveAndSyncProduct = function() {
    var store = document.getElementById('csStoreSelect').value;
    var sku = document.getElementById('csFormSku').value;
    var title = document.getElementById('csFormTitle').value.trim();
    var brand = document.getElementById('csFormBrand').value.trim();
    var category = document.getElementById('csFormCategory').value.trim();
    var thumbnail = document.getElementById('csFormThumbnail').value.trim();
    var description = document.getElementById('csFormDescription').value.trim();
    
    var peso = document.getElementById('csFormPeso').value.trim();
    var alto = document.getElementById('csFormAlto').value.trim();
    var ancho = document.getElementById('csFormAncho').value.trim();
    var largo = document.getElementById('csFormLargo').value.trim();

    if (!title || !brand || !category) {
      alert('Por favor rellena todos los campos obligatorios.');
      return;
    }

    var submitBtn = document.getElementById('csModalSubmitBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Sincronizando...';

    var fd = new FormData();
    fd.append('store', store);
    fd.append('sku', sku);
    fd.append('title', title);
    fd.append('brand', brand);
    fd.append('category', category);
    fd.append('thumbnail', thumbnail);
    fd.append('description', description);
    fd.append('peso', peso);
    fd.append('alto', alto);
    fd.append('ancho', ancho);
    fd.append('largo', largo);
    fd.append('csrf_token', '<?php echo htmlspecialchars($data['csrf_token']); ?>');

    fetch(BASE + '/saveAndSyncProduct', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Guardar y publicar en Cencosud';
        if (res.ok) {
          if (typeof showToast === 'function') showToast('Plantilla guardada y producto publicado en Cencosud.', 'success');
          closeTemplateModal();
          load(); // Refrescar lista de productos
        } else {
          alert(res.error || 'Error al guardar/sincronizar.');
        }
      })
      .catch(function() {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Guardar y publicar en Cencosud';
        alert('Error de conexión al servidor.');
      });
  };

  load();
})();
</script>

<!-- Modal "Traer Plantilla" -->
<div id="csTemplateModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
  <div style="background:var(--bg-primary,#ffffff); width:90%; max-width:650px; border-radius:8px; overflow:hidden; box-shadow:0 10px 25px -5px rgba(0,0,0,0.2); display:flex; flex-direction:column; max-height:90vh;">
    <!-- Modal Header -->
    <div style="padding:1rem; border-bottom:1px solid var(--border,#e5e7eb); display:flex; justify-content:space-between; align-items:center; background:var(--bg-secondary,#f9fafb);">
      <h3 style="margin:0; font-size:1.1rem; font-weight:600; color:var(--text-primary,#111827);">Traer Plantilla y Sincronizar Producto</h3>
      <button type="button" onclick="closeTemplateModal()" style="background:none; border:none; font-size:1.5rem; line-height:1; cursor:pointer; color:var(--text-secondary,#6b7280);">&times;</button>
    </div>
    
    <!-- Modal Body -->
    <div style="padding:1.25rem; overflow-y:auto; flex:1; display:flex; flex-direction:column; gap:1.25rem;">
      <!-- SKU Search Bar -->
      <div>
        <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.5rem; color:var(--text-primary);">1. Buscar SKU en Bodega Física</label>
        <div style="display:flex; gap:0.5rem;">
          <input type="text" id="csModalSearchSku" class="rename-input" style="flex:1;" placeholder="Escribe el SKU del producto maestro (ej: A-201-ROJ-S)" onkeydown="if(event.key==='Enter'){event.preventDefault(); searchSkuInMaster();}">
          <button type="button" class="ut-btn" onclick="searchSkuInMaster()" style="background:#3b82f6; border-color:#3b82f6; color:white;">Buscar</button>
        </div>
        <div id="csModalSearchFeedback" style="font-size:0.75rem; margin-top:4px;"></div>
      </div>
      
      <!-- Search Results Area -->
      <div id="csModalSearchResults" style="display:none; padding:0.75rem; border:1px solid #d1d5db; border-radius:6px; background:#f9fafb; align-items:center; justify-content:space-between;">
        <div style="display:flex; align-items:center; gap:0.75rem; min-width:0;">
          <div id="csModalResultImg" style="width:40px; height:40px; border-radius:4px; overflow:hidden; border:1px solid #e5e7eb; background:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0;"></div>
          <div style="min-width:0; display:flex; flex-direction:column;">
            <span id="csModalResultTitle" style="font-weight:600; font-size:0.85rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:320px;"></span>
            <span id="csModalResultSku" style="font-family:monospace; font-size:0.75rem; color:var(--text-secondary);"></span>
          </div>
        </div>
        <button type="button" class="ut-btn" onclick="loadTemplateIntoForm()" style="background:#10b981; border-color:#10b981; color:white; font-size:0.78rem; padding:4px 8px;">Cargar plantilla</button>
      </div>

      <!-- Form -->
      <form id="csTemplateForm" style="display:none; flex-direction:column; gap:1rem; border-top:1px solid #e5e7eb; padding-top:1rem;" onsubmit="event.preventDefault();">
        <input type="hidden" id="csFormSku">
        
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
          <div>
            <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-secondary); margin-bottom:3px;">SKU a sincronizar</label>
            <input type="text" id="csDispSku" class="rename-input" disabled style="background:#f3f4f6; cursor:not-allowed;">
          </div>
          <div>
            <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-secondary); margin-bottom:3px;">Marca</label>
            <input type="text" id="csFormBrand" class="rename-input" required placeholder="Marca del producto">
          </div>
        </div>

        <div>
          <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-secondary); margin-bottom:3px;">Título / Nombre del Producto</label>
          <input type="text" id="csFormTitle" class="rename-input" required placeholder="Título">
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
          <div>
            <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-secondary); margin-bottom:3px;">Categoría</label>
            <input type="text" id="csFormCategory" class="rename-input" required placeholder="Categoría">
          </div>
          <div>
            <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-secondary); margin-bottom:3px;">URL Imagen / Foto</label>
            <input type="text" id="csFormThumbnail" class="rename-input" placeholder="https://...">
          </div>
        </div>

        <div>
          <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-secondary); margin-bottom:3px;">Descripción</label>
          <textarea id="csFormDescription" class="rename-input" style="height:60px; font-size:0.8rem; resize:vertical;" placeholder="Escribe una descripción descriptiva..."></textarea>
        </div>

        <!-- Dimensions Ficha -->
        <div style="padding:0.75rem; border:1px solid #fde68a; background:#fffbeb; border-radius:6px; display:flex; flex-direction:column; gap:0.5rem;">
          <span style="font-weight:600; font-size:0.8rem; color:#b45309; display:inline-flex; align-items:center; gap:4px;">
            Ficha de Medidas (Obligatorias Cencosud)
          </span>
          <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:0.5rem;">
            <div>
              <label style="display:block; font-size:0.7rem; font-weight:600; margin-bottom:2px;">Peso (Kg)</label>
              <input type="text" id="csFormPeso" class="rename-input" placeholder="0.2">
            </div>
            <div>
              <label style="display:block; font-size:0.7rem; font-weight:600; margin-bottom:2px;">Alto (cm)</label>
              <input type="text" id="csFormAlto" class="rename-input" placeholder="2">
            </div>
            <div>
              <label style="display:block; font-size:0.7rem; font-weight:600; margin-bottom:2px;">Ancho (cm)</label>
              <input type="text" id="csFormAncho" class="rename-input" placeholder="20">
            </div>
            <div>
              <label style="display:block; font-size:0.7rem; font-weight:600; margin-bottom:2px;">Largo (cm)</label>
              <input type="text" id="csFormLargo" class="rename-input" placeholder="30">
            </div>
          </div>
        </div>
      </form>
    </div>
    
    <!-- Modal Footer -->
    <div style="padding:1rem; border-top:1px solid var(--border,#e5e7eb); display:flex; justify-content:end; gap:0.5rem; background:var(--bg-secondary,#f9fafb);">
      <button type="button" class="ut-btn" onclick="closeTemplateModal()">Cancelar</button>
      <button type="button" id="csModalSubmitBtn" class="ut-btn" onclick="saveAndSyncProduct()" disabled style="background:#10b981; border-color:#10b981; color:white;">Guardar y publicar en Cencosud</button>
    </div>
  </div>
</div>

<?php endif; ?>
