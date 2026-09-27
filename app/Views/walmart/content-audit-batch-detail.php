<?php
$summary = $data['summary'] ?? null;
$rows = $data['rows'] ?? [];

$typeLabels = [
    'price' => ['label' => 'Precios', 'color' => '#3483fa', 'bg' => 'rgba(52,131,250,0.15)', 'icon' => 'dollar-sign'],
    'stock' => ['label' => 'Stock',   'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.15)', 'icon' => 'package'],
];
$typeInfo = $typeLabels[$summary->audit_type] ?? ['label' => $summary->audit_type, 'color' => '#94a3b8', 'bg' => 'rgba(148,163,184,0.15)', 'icon' => 'circle'];

$date = new DateTime($summary->audit_date, new DateTimeZone('UTC'));
$date->setTimezone(new DateTimeZone(date_default_timezone_get()));

$isPrice = $summary->audit_type === 'price';
$isStock = $summary->audit_type === 'stock';
$dlFilename = 'auditoria_walmart_' . $summary->audit_type . '_' . date('Ymd_His');
$connectionId = (int) $summary->walmart_connection_id;

$rowsJson = json_encode($rows);
?>

<link rel="stylesheet" href="<?php echo URLROOT; ?>/assets/css/tabulator_midnight.min.css">

<div class="audit-container">

  <div class="audit-breadcrumb">
    <a href="<?php echo URLROOT; ?>/walmart/auditHistory" class="audit-back">
      <?php echo icon('arrow-left', ['size' => 14]); ?>
      Historial
    </a>
  </div>

  <div class="audit-batch-header-info">
    <div class="audit-card-icon" style="background:<?php echo $typeInfo['bg']; ?>;color:<?php echo $typeInfo['color']; ?>">
      <?php echo icon($typeInfo['icon'], ['size' => 22]); ?>
    </div>
    <div>
      <h3><?php echo $typeInfo['label']; ?> — <?php echo htmlspecialchars($summary->store_name ?? 'Sin tienda'); ?></h3>
      <p>
        <?php echo (int) $summary->total_skus; ?> SKUs
        <?php if ((int) $summary->mismatches > 0): ?> · <?php echo (int) $summary->mismatches; ?> diferencias<?php endif; ?>
        <?php if ((int) $summary->not_found > 0): ?> · <?php echo (int) $summary->not_found; ?> no encontrados<?php endif; ?>
        <?php if (!empty($summary->performed_by)): ?> · Ejecutado por <strong><?php echo htmlspecialchars($summary->performed_by); ?></strong><?php endif; ?>
        · <?php echo $date->format('d/m/Y H:i'); ?>
      </p>
    </div>
    <div class="audit-batch-actions" style="margin-left:auto;display:flex;align-items:center;gap:0.5rem;">
      <?php if (!empty($data['is_admin'])): ?>
      <button type="button" id="btnDeleteBatch" class="btn-download-link" style="color:var(--danger);">
        <?php echo icon('trash-2', ['size' => 14]); ?>
        Borrar
      </button>
      <?php endif; ?>
      <button type="button" id="btnDownload" class="btn-download-link">
        <?php echo icon('download', ['size' => 14]); ?>
        Descargar Excel
      </button>
    </div>
  </div>

  <?php if (empty($rows)): ?>
    <div class="audit-batch-empty">
      <?php echo icon('search', ['size' => 32, 'stroke' => 1.2]); ?>
      <p>No se encontraron resultados para este batch.</p>
    </div>
  <?php else: ?>
    <div id="audit-table"></div>
    <p class="text-xs text-muted mt-2">
      <?php echo icon('info', ['size' => 12]); ?>
      Los valores de Walmart provienen de la caché local. Usa "Verificar en vivo" en una fila con diferencia para consultar la API en ese momento.
    </p>
  <?php endif; ?>
</div>

<script src="<?php echo URLROOT; ?>/assets/js/tabulator.min.js"></script>
<script>
(function() {
  var rows = <?php echo $rowsJson; ?>;
  var resultRank = { 'Ambiguo': 0, 'No encontrado': 1, 'Diferencia': 2, 'Coincide': 3 };
  rows.forEach(function(d) {
    if (d.match_method === 'ambiguous') {
      d._result = 'Ambiguo';
    } else if (!d.wpid || d.wpid === "-") {
      d._result = 'No encontrado';
    } else {
      var diff = parseFloat(d.diff_amount);
      d._result = (isNaN(diff) || diff === 0) ? 'Coincide' : 'Diferencia';
    }
    d._result_rank = resultRank[d._result];
  });

  var isPrice = <?php echo $isPrice ? 'true' : 'false'; ?>;
  var connectionId = <?php echo $connectionId; ?>;
  var downloadBtnUrl = '<?php echo URLROOT; ?>/walmart/auditBatchDownload/<?php echo htmlspecialchars($summary->batch_id); ?>';
  var downloadFileName = '<?php echo htmlspecialchars($dlFilename); ?>';
  var verifyUrl = '<?php echo URLROOT; ?>/walmart/auditVerifyLive';

  function fmtCurrency(cell) {
    var v = parseFloat(cell.getValue());
    if (isNaN(v)) return '<span class="audit-diff-none">—</span>';
    return "$" + v.toLocaleString("es-CL", {maximumFractionDigits: 0});
  }

  function fmtDiff(cell) {
    var v = parseFloat(cell.getValue());
    if (isNaN(v) || cell.getValue() === null) return '<span class="audit-diff-none">—</span>';
    var cls = v > 0 ? "audit-diff-pos" : v < 0 ? "audit-diff-neg" : "audit-diff-zero";
    var prefix = v > 0 ? "+" : v < 0 ? "-" : "";
    var suffix = isPrice ? "$" : "";
    var abs = isPrice ? Math.abs(v).toLocaleString("es-CL", {maximumFractionDigits: 0}) : Math.abs(v);
    return '<span class="audit-diff ' + cls + '">' + prefix + suffix + abs + '</span>';
  }

  function fmtPercent(cell) {
    var v = parseFloat(cell.getValue());
    if (isNaN(v) || cell.getValue() === null) return '<span class="audit-diff-none">—</span>';
    var cls = v > 0 ? "audit-diff-pos" : v < 0 ? "audit-diff-neg" : "audit-diff-zero";
    return '<span class="audit-diff ' + cls + '">' + (v > 0 ? "+" : "") + v.toFixed(1) + '%</span>';
  }

  function fmtResult(cell) {
    var v = cell.getRow().getData()._result;
    return v === 'Coincide' ? '<span class="badge-ok">Coincide</span>' : '<span class="badge-err">' + v + '</span>';
  }

  function fmtSyncedAt(cell) {
    var v = cell.getValue();
    if (!v) return '<span class="audit-diff-none">—</span>';
    var d = new Date(v.replace(' ', 'T') + 'Z');
    var diffMin = Math.round((Date.now() - d.getTime()) / 60000);
    var label;
    if (diffMin < 1) label = 'hace instantes';
    else if (diffMin < 60) label = 'hace ' + diffMin + ' min';
    else if (diffMin < 1440) label = 'hace ' + Math.floor(diffMin / 60) + ' h';
    else label = 'hace ' + Math.floor(diffMin / 1440) + ' d';
    return '<span class="text-xs text-muted">' + label + '</span>';
  }

  function fmtVerify(cell) {
    var d = cell.getRow().getData();
    if (!d.wpid || d.wpid === "-") {
      return '<button type="button" class="btn-secondary text-xs family-lookup-btn" data-sku="' + encodeURIComponent(d.sku) + '">Ver familia del SKU</button>';
    }
    var diff = parseFloat(d.diff_amount);
    if (isNaN(diff) || diff === 0) return '';
    return '<button type="button" class="btn-secondary text-xs verify-live-btn" data-sku="' + encodeURIComponent(d.sku) + '">Verificar en vivo</button>';
  }

  function fmtMethod(cell) {
    var v = cell.getValue();
    var labels = { exact: '', shortcode: 'Código corto', fuzzy: 'Aproximado', ambiguous: 'Ambiguo' };
    var label = labels[v] || v;
    if (!label) return '';
    var cls = v === 'ambiguous' ? 'audit-diff-neg' : 'text-muted';
    return '<span class="text-xs ' + cls + '">' + label + '</span>';
  }

  function fmtMatchedSku(cell) {
    var d = cell.getRow().getData();
    if (!d.matched_sku || d.matched_sku === '-' || d.matched_sku === d.sku) return '<span class="audit-diff-none">—</span>';
    return '<span class="text-xs" title="Encontrado a partir de \'' + d.sku + '\'">' + d.matched_sku + '</span>';
  }

  var columns = [
    {title: "SKU Subido", field: "sku", width: 130, headerSort: true},
    {title: "SKU Encontrado", field: "matched_sku", width: 140, formatter: fmtMatchedSku},
    {title: "WPID", field: "wpid", width: 120},
  ];

  if (isPrice) {
    columns.push(
      {title: "Subido", field: "uploaded_value", hozAlign: "right", formatter: fmtCurrency},
      {title: "Walmart (caché)", field: "walmart_value", hozAlign: "right", formatter: fmtCurrency},
      {title: "Diff $", field: "diff_amount", hozAlign: "right", formatter: fmtDiff},
      {title: "Diff %", field: "diff_percent", hozAlign: "right", formatter: fmtPercent},
      {title: "Antigüedad", field: "synced_at", hozAlign: "right", formatter: fmtSyncedAt},
      {title: "Método", field: "match_method", width: 100, formatter: fmtMethod},
      {title: "Resultado", field: "_result_rank", width: 100, hozAlign: "center", formatter: fmtResult, sorter: "number"},
      {title: "", field: "_verify", width: 130, hozAlign: "center", formatter: fmtVerify}
    );
  } else {
    columns.push(
      {title: "Stock Excel", field: "uploaded_value", hozAlign: "right"},
      {title: "Stock Walmart (caché)", field: "walmart_value", hozAlign: "right"},
      {title: "Diff", field: "diff_amount", hozAlign: "right", formatter: fmtDiff},
      {title: "Antigüedad", field: "synced_at", hozAlign: "right", formatter: fmtSyncedAt},
      {title: "Método", field: "match_method", width: 100, formatter: fmtMethod},
      {title: "Resultado", field: "_result_rank", width: 100, hozAlign: "center", formatter: fmtResult, sorter: "number"},
      {title: "", field: "_verify", width: 130, hozAlign: "center", formatter: fmtVerify}
    );
  }

  var table = new Tabulator("#audit-table", {
    data: rows,
    height: "500px",
    layout: "fitColumns",
    placeholder: "Sin resultados",
    placeholderHeaderSort: "",
    columns: columns,
    rowFormatter: function(row) {
      var d = row.getData();
      var el = row.getElement();
      if (!d.wpid || d.wpid === "-") { el.classList.add("audit-row-notfound"); return; }
      var diff = parseFloat(d.diff_amount);
      if (!isNaN(diff) && diff !== 0) el.classList.add(diff > 0 ? "audit-row-diff-pos" : "audit-row-diff-neg");
    },
  });

  document.getElementById('audit-table').addEventListener('click', function(e) {
    var familyBtn = e.target.closest('.family-lookup-btn');
    if (familyBtn) {
      var fsku = decodeURIComponent(familyBtn.getAttribute('data-sku'));
      familyBtn.disabled = true;
      familyBtn.textContent = 'Buscando…';

      fetch('<?php echo URLROOT; ?>/walmart/auditFamilyLookup?sku=' + encodeURIComponent(fsku), { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
          if (d.ok && d.members && d.members.length > 0) {
            var typeLabel = { individual: 'Individual', pack: 'Pack', tripack: 'Tripack' };
            var list = d.members.map(function(m) {
              return (typeLabel[m.variant_type] || m.variant_type) + ': ' + m.sku;
            }).join(' · ');
            familyBtn.outerHTML = '<span class="text-xs text-primary" title="' + list.replace(/"/g, '&quot;') + '">Familia (' + d.base_sku + '): ' + d.members.length + ' relacionados</span>';
            if (typeof showToast === 'function') showToast('Se encontraron ' + d.members.length + ' SKUs relacionados a ' + d.base_sku, 'success', 4000);
          } else {
            familyBtn.disabled = false;
            familyBtn.textContent = 'Sin familia registrada';
            if (typeof showToast === 'function') showToast('No hay SKUs relacionados registrados para este producto.', 'info', 4000);
          }
        })
        .catch(function() {
          familyBtn.disabled = false;
          familyBtn.textContent = 'Reintentar';
        });
      return;
    }

    var btn = e.target.closest('.verify-live-btn');
    if (!btn) return;
    var sku = decodeURIComponent(btn.getAttribute('data-sku'));
    btn.disabled = true;
    btn.textContent = 'Consultando…';

    var type = isPrice ? 'price' : 'stock';
    fetch(verifyUrl + '?sku=' + encodeURIComponent(sku) + '&type=' + type + '&connection_id=' + connectionId, { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.ok) {
          var label = isPrice ? ('$' + Number(d.value).toLocaleString('es-CL', {maximumFractionDigits: 0})) : d.value;
          btn.outerHTML = '<span class="text-xs text-primary">Valor en vivo: ' + label + '</span>';
          if (typeof showToast === 'function') showToast('Verificado en vivo contra la API de Walmart', 'success', 4000);
        } else {
          btn.disabled = false;
          btn.textContent = 'Reintentar';
          if (typeof showToast === 'function') showToast('No se pudo verificar en vivo.', 'error', 5000);
        }
      })
      .catch(function() {
        btn.disabled = false;
        btn.textContent = 'Reintentar';
        if (typeof showToast === 'function') showToast('Error de conexión al verificar.', 'error', 5000);
      });
  });

  document.getElementById('btnDownload').addEventListener('click', function() {
    showDownloadToast(downloadFileName, 4000);
    var a = document.createElement('a');
    a.href = downloadBtnUrl; a.download = ''; a.style.display = 'none';
    document.body.appendChild(a); a.click(); a.remove();
  });

  var deleteBtn = document.getElementById('btnDeleteBatch');
  if (deleteBtn) {
    deleteBtn.addEventListener('click', function() {
      openConfirm({
        title: 'Eliminar lote de auditoría',
        message: '\u00bfEliminar permanentemente este lote de auditor\u00eda Walmart? Esta acci\u00f3n no se puede deshacer.<br><br>Escribe <strong>"Si, deseo borrar los registros"</strong> para confirmar.',
        confirmText: 'Eliminar',
        confirmClass: 'btn-danger',
        requirePhrase: 'Si, deseo borrar los registros',
        onConfirm: function() {
          var batchId = <?php echo json_encode($summary->batch_id); ?>;
          var form = new FormData();
          form.set('csrf_token', '<?php echo htmlspecialchars($data['csrf_token'], ENT_QUOTES, "UTF-8"); ?>');
          fetch('<?php echo URLROOT; ?>/walmart/deleteAuditBatch/' + encodeURIComponent(batchId), {
            method: 'POST', body: form, credentials: 'same-origin'
          })
          .then(function(r) { return r.json(); })
          .then(function(d) {
            if (d.ok) {
              showToast('Lote eliminado: ' + d.deleted + ' registros.', 'success', 4000);
              setTimeout(function() { window.location.href = '<?php echo URLROOT; ?>/walmart/auditHistory'; }, 1200);
            } else {
              showToast(d.error || 'Error al eliminar.', 'error', 6000);
            }
          })
          .catch(function() { showToast('Error de conexi\u00f3n.', 'error', 6000); });
        }
      });
    });
  }
})();
</script>
