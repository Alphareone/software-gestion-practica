<p style="margin:0 0 16px;">Hola <strong><?php echo htmlspecialchars($first_name ?? $username); ?></strong>,</p>

<p style="margin:0 0 20px;">Tu cuenta ha sido <strong style="color:#dc2626;">deshabilitada</strong>.</p>

<!-- Details card -->
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
<tr>
<td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;">
  <table role="presentation" cellpadding="0" cellspacing="0">
  <tr>
    <td style="padding:4px 16px 4px 0;font-size:13px;color:#6b7280;white-space:nowrap;vertical-align:top;">Administrador</td>
    <td style="padding:4px 0;font-size:13px;color:#111827;"><?php echo htmlspecialchars($admin_name); ?></td>
  </tr>
  <tr>
    <td style="padding:4px 16px 4px 0;font-size:13px;color:#6b7280;white-space:nowrap;vertical-align:top;">Fecha</td>
    <td style="padding:4px 0;font-size:13px;color:#111827;"><?php echo htmlspecialchars($suspended_at); ?></td>
  </tr>
  <tr>
    <td style="padding:4px 16px 4px 0;font-size:13px;color:#6b7280;white-space:nowrap;vertical-align:top;">Motivo</td>
    <td style="padding:4px 0;font-size:13px;color:#111827;"><?php echo htmlspecialchars($reason); ?></td>
  </tr>
  </table>
</td>
</tr>
</table>

<p style="margin:0 0 20px;font-size:13px;color:#6b7280;">
  Si crees que esto es un error, contacta a tu l&iacute;der de &aacute;rea o al correo
  <a href="mailto:<?php echo htmlspecialchars($app_email); ?>" style="color:#0284c7;"><?php echo htmlspecialchars($app_email); ?></a>.
</p>
