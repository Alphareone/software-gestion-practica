<p style="margin:0 0 16px;">Hola <strong>Bodega / Adquisiciones</strong>,</p>

<p style="margin:0 0 20px;">Adjuntamos el informe consolidado de productos con <strong>Alto Stock (&ge; 50 unidades)</strong> generado desde el M&oacute;dulo Cencosud.</p>

<!-- Summary card -->
<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 24px;">
<tr>
<td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;">
  <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;">
  <tr>
    <td style="padding:4px 16px 4px 0;font-size:13px;color:#6b7280;white-space:nowrap;vertical-align:top;">Tienda Cencosud:</td>
    <td style="padding:4px 0;font-size:13px;font-weight:600;color:#111827;"><?php echo htmlspecialchars($store_name ?? 'Cencosud'); ?></td>
  </tr>
  <tr>
    <td style="padding:4px 16px 4px 0;font-size:13px;color:#6b7280;white-space:nowrap;vertical-align:top;">Fecha de Generaci&oacute;n:</td>
    <td style="padding:4px 0;font-size:13px;color:#111827;"><?php echo htmlspecialchars($generated_date ?? date('d/m/Y H:i')); ?></td>
  </tr>
  <tr>
    <td style="padding:4px 16px 4px 0;font-size:13px;color:#6b7280;white-space:nowrap;vertical-align:top;">Total SKUs con Alto Stock:</td>
    <td style="padding:4px 0;font-size:13px;font-weight:600;color:#059669;"><?php echo htmlspecialchars($total_skus ?? 0); ?> productos</td>
  </tr>
  <tr>
    <td style="padding:4px 16px 4px 0;font-size:13px;color:#6b7280;white-space:nowrap;vertical-align:top;">Total Unidades Consolidadas:</td>
    <td style="padding:4px 0;font-size:13px;font-weight:600;color:#1d4ed8;"><?php echo number_format((int)($total_units ?? 0), 0, ',', '.'); ?> unidades</td>
  </tr>
  </table>
</td>
</tr>
</table>

<p style="margin:0 0 20px;font-size:13px;color:#4b5563;">
  El archivo Excel <code>.xlsx</code> adjunto contiene la ficha t&eacute;cnica completa de cada producto: SKU, marca, categor&iacute;a, descripci&oacute;n, stock de bodega, stock Cencosud, precios y medidas de despacho (peso, alto, ancho y largo).
</p>

<?php if (!empty($download_url)): ?>
<!-- Download button alternative -->
<table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:0 0 24px;">
<tr>
<td style="background-color:#10b981;border-radius:8px;text-align:center;">
  <a href="<?php echo htmlspecialchars($download_url); ?>"
     style="display:inline-block;padding:12px 28px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">
    Descargar Planilla Excel
  </a>
</td>
</tr>
</table>
<?php endif; ?>

<p style="margin:0 0 0;font-size:12px;color:#9ca3af;">
  Reporte automatizado generado por el usuario <strong><?php echo htmlspecialchars($sender_name ?? 'Sistema'); ?></strong>.
</p>
