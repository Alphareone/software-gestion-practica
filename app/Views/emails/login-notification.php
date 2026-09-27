<p style="margin:0 0 16px;">Hola <strong><?php echo htmlspecialchars($first_name ?? $username); ?></strong>,</p>

<p style="margin:0 0 20px;">Detectamos un inicio de sesi&oacute;n en tu cuenta de <?php echo htmlspecialchars($sitename); ?>.</p>

<!-- Details card -->
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
<tr>
<td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;">
  <table role="presentation" cellpadding="0" cellspacing="0">
  <tr>
    <td style="padding:4px 16px 4px 0;font-size:13px;color:#6b7280;white-space:nowrap;vertical-align:top;">Fecha</td>
    <td style="padding:4px 0;font-size:13px;color:#111827;"><?php echo htmlspecialchars($login_date); ?> a las <?php echo htmlspecialchars($login_time); ?></td>
  </tr>
  <tr>
    <td style="padding:4px 16px 4px 0;font-size:13px;color:#6b7280;white-space:nowrap;vertical-align:top;">Direcci&oacute;n IP</td>
    <td style="padding:4px 0;font-size:13px;font-family:monospace;color:#111827;"><?php echo htmlspecialchars($login_ip); ?></td>
  </tr>
  <tr>
    <td style="padding:4px 16px 4px 0;font-size:13px;color:#6b7280;white-space:nowrap;vertical-align:top;">Dispositivo</td>
    <td style="padding:4px 0;font-size:13px;color:#111827;"><?php echo htmlspecialchars($login_device); ?></td>
  </tr>
  <tr>
    <td style="padding:4px 16px 4px 0;font-size:13px;color:#6b7280;white-space:nowrap;vertical-align:top;">Ubicaci&oacute;n</td>
    <td style="padding:4px 0;font-size:13px;color:#111827;"><?php echo htmlspecialchars($login_location); ?></td>
  </tr>
  </table>
</td>
</tr>
</table>

<p style="margin:0 0 20px;font-size:13px;color:#6b7280;">
  Si fuiste t&uacute;, ignora este correo. Si <strong>no</strong> fuiste t&uacute;, tu contrase&ntilde;a podr&iacute;a estar comprometida.
</p>

<!-- CTA Button -->
<table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:0 0 24px;">
<tr>
<td style="background-color:#dc2626;border-radius:8px;text-align:center;">
  <a href="<?php echo htmlspecialchars($protect_url); ?>"
     style="display:inline-block;padding:12px 28px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">
    Proteger mi cuenta
  </a>
</td>
</tr>
</table>

<p style="margin:0 0 0;font-size:13px;color:#6b7280;word-break:break-all;">
  O copia este enlace en tu navegador:<br>
  <a href="<?php echo htmlspecialchars($protect_url); ?>" style="color:#0284c7;"><?php echo htmlspecialchars($protect_url); ?></a>
</p>
