<?php
$summary = $data['summary'] ?? null;
$rows = $data['rows'] ?? [];

$typeLabels = [
    'prices'       => ['label' => 'Precios',  'color' => '#3483fa', 'bg' => 'rgba(52,131,250,0.15)', 'icon' => 'dollar-sign'],
    'stock'        => ['label' => 'Stock',    'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.15)', 'icon' => 'package'],
];

$typeInfo = $typeLabels[$summary->audit_type] ?? ['label' => $summary->audit_type, 'color' => '#94a3b8', 'bg' => 'rgba(148,163,184,0.15)', 'icon' => 'circle'];

$date = new DateTime($summary->audit_date, new DateTimeZone('UTC'));
$date->setTimezone(new DateTimeZone(date_default_timezone_get()));

$isPrice = $summary->audit_type === 'prices';
$isStock = $summary->audit_type === 'stock';
$dlFilename = 'auditoria_' . $summary->audit_type . '_' . date('Ymd_His');

$rowsJson = json_encode($rows);
?>

<link rel="stylesheet" href="<?php echo URLROOT; ?>/assets/css/tabulator_midnight.min.css">

<div class="audit-container">

  <div class="audit-breadcrumb">
    <a href="<?php echo URLROOT; ?>/connections/auditHistory" class="audit-back">
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
        <?php if ((int) $summary->mismatches > 0): ?>
          · <?php echo (int) $summary->mismatches; ?> diferencias
        <?php endif; ?>
        <?php if ((int) $summary->not_found > 0): ?>
          · <?php echo (int) $summary->not_found; ?> no encontrados
        <?php endif; ?>
        <?php if (!empty($summary->performed_by)): ?>
          · Ejecutado por <strong><?php echo htmlspecialchars($summary->performed_by); ?></strong>
        <?php endif; ?>
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
  <?php endif; ?>
</div>

<script src="<?php echo URLROOT; ?>/assets/js/tabulator.min.js"></script>
<script>
(function() {
  var rows = <?php echo $rowsJson; ?>;
  var isPrice = <?php echo $isPrice ? 'true' : 'false'; ?>;
  var isStock = <?php echo $isStock ? 'true' : 'false'; ?>;
  var downloadBtnUrl = '<?php echo URLROOT; ?>/connections/auditBatchDownload/<?php echo htmlspecialchars($summary->batch_id); ?>';
  var downloadFileName = '<?php echo htmlspecialchars($dlFilename); ?>';

  function fmtCurrency(cell) {
    var v = parseFloat(cell.getValue());
    if (isNaN(v)) return '<span class="audit-diff-none">—</span>';
    return "$" + v.toLocaleString("es-AR", {maximumFractionDigits: 0});
  }

  function fmtDiff(cell) {
    var v = parseFloat(cell.getValue());
    if (isNaN(v) || cell.getValue() === null) return '<span class="audit-diff-none">—</span>';
    var cls = v > 0 ? "audit-diff-pos" : v < 0 ? "audit-diff-neg" : "audit-diff-zero";
    var prefix = v > 0 ? "+" : v < 0 ? "-" : "";
    var suffix = isPrice ? "$" : "";
    var abs = isPrice ? Math.abs(v).toLocaleString("es-AR", {maximumFractionDigits: 0}) : Math.abs(v);
    return '<span class="audit-diff ' + cls + '">' + prefix + suffix + abs + '</span>';
  }

  function fmtPercent(cell) {
    var v = parseFloat(cell.getValue());
    if (isNaN(v) || cell.getValue() === null) return '<span class="audit-diff-none">—</span>';
    var cls = v > 0 ? "audit-diff-pos" : v < 0 ? "audit-diff-neg" : "audit-diff-zero";
    return '<span class="audit-diff ' + cls + '">' + (v > 0 ? "+" : "") + v.toFixed(1) + '%</span>';
  }

  function fmtResult(cell) {
    var d = cell.getRow().getData();
    if (d.ml_item_id === "-" || d.ml_item_id === "") return '<span class="badge-err">No encontrado</span>';
    var diff = parseFloat(d.diff_amount);
    if (isNaN(diff) || diff === 0) return '<span class="badge-ok">Coincide</span>';
    return '<span class="badge-err">Diferencia</span>';
  }

  var columns = [
    {title: "SKU", field: "sku", width: 140, headerSort: true},
    {title: "Item ML", field: "ml_item_id", width: 160, formatter: "link",
     formatterParams: {urlPrefix: "https://articulo.mercadolibre.com/", target: "_blank"}},
  ];

  if (isPrice) {
    columns.push(
      {title: "Subido", field: "prev_ml_price", hozAlign: "right", formatter: fmtCurrency},
      {title: "ML", field: "ml_price", hozAlign: "right", formatter: fmtCurrency},
      {title: "Diff $", field: "diff_amount", hozAlign: "right", formatter: fmtDiff},
      {title: "Diff %", field: "diff_percent", hozAlign: "right", formatter: fmtPercent},
      {title: "Resultado", width: 110, hozAlign: "center", formatter: fmtResult}
    );
  } else if (isStock) {
    columns.push(
      {title: "Stock Excel", field: "prev_ml_price", hozAlign: "right",
       formatter: function(c) { var v = parseInt(c.getValue()); return isNaN(v) ? '<span class="audit-diff-none">—</span>' : v; }},
      {title: "Stock ML", field: "ml_price", hozAlign: "right",
       formatter: function(c) { var v = parseInt(c.getValue()); return isNaN(v) ? '<span class="audit-diff-none">—</span>' : v; }},
      {title: "Diff", field: "diff_amount", hozAlign: "right", formatter: fmtDiff},
      {title: "Resultado", width: 110, hozAlign: "center", formatter: fmtResult}
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

      if (d.ml_item_id === "-" || d.ml_item_id === "") {
        el.classList.add("audit-row-notfound");
        return;
      }

      var diff = parseFloat(d.diff_amount);
      if (!isNaN(diff) && diff !== 0) {
        el.classList.add(diff > 0 ? "audit-row-diff-pos" : "audit-row-diff-neg");
      }
    },
  });

  // Download toast feedback
  document.getElementById('btnDownload').addEventListener('click', function() {
    showDownloadToast(downloadFileName, 4000);

    var a = document.createElement('a');
    a.href = downloadBtnUrl;
    a.download = '';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    a.remove();
  });

  var deleteBtn = document.getElementById('btnDeleteBatch');
  if (deleteBtn) {
    deleteBtn.addEventListener('click', function() {
      openConfirm({
        title: 'Eliminar lote de auditor\u00eda',
        message: '\u00bfEliminar permanentemente este lote de auditor\u00eda? Esta acci\u00f3n no se puede deshacer.<br><br>Escribe <strong>"Si, deseo borrar los registros"</strong> para confirmar.',
        confirmText: 'Eliminar',
        confirmClass: 'btn-danger',
        requirePhrase: 'Si, deseo borrar los registros',
        onConfirm: function() {
          var batchId = <?php echo json_encode($summary->batch_id); ?>;
          var form = new FormData();
          form.set('csrf_token', '<?php echo htmlspecialchars($data['csrf_token'], ENT_QUOTES, "UTF-8"); ?>');
          fetch('<?php echo URLROOT; ?>/connections/deleteAuditBatch/' + encodeURIComponent(batchId), {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
          })
          .then(function(r) { return r.json(); })
          .then(function(d) {
            if (d.ok) {
              showToast('Lote eliminado: ' + d.deleted + ' registros.', 'success', 4000);
              setTimeout(function() { window.location.href = '<?php echo URLROOT; ?>/connections/auditHistory'; }, 1200);
            } else {
              showToast(d.error || 'Error al eliminar.', 'error', 6000);
            }
          })
          .catch(function() {
            showToast('Error de conexi\u00f3n.', 'error', 6000);
          });
        }
      });
    });
  }
})();
</script>
