<p style="margin:0 0 16px;">Hola <strong><?php echo htmlspecialchars($first_name ?? $username); ?></strong>,</p>

<p style="margin:0 0 16px;">Se ha creado una cuenta para ti en <strong><?php echo htmlspecialchars($sitename); ?></strong>.</p>

<p style="margin:0 0 8px;font-size:13px;color:#6b7280;">Tu usuario es:</p>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
<tr>
<td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 20px;font-size:15px;font-family:monospace;color:#111827;letter-spacing:0.03em;">
  <?php echo htmlspecialchars($username); ?>
</td>
</tr>
</table>

<p style="margin:0 0 20px;">Usa el siguiente bot&oacute;n para establecer tu contrase&ntilde;a (v&aacute;lido por <strong>1 hora</strong>):</p>

<!-- CTA Button -->
<table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:0 0 24px;">
<tr>
<td style="background-color:#0284c7;border-radius:8px;text-align:center;">
  <a href="<?php echo htmlspecialchars($reset_url); ?>"
     style="display:inline-block;padding:12px 28px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">
    Establecer contrase&ntilde;a
  </a>
</td>
</tr>
</table>

<p style="margin:0 0 16px;font-size:13px;color:#6b7280;word-break:break-all;">
  O copia este enlace en tu navegador:<br>
  <a href="<?php echo htmlspecialchars($reset_url); ?>" style="color:#0284c7;"><?php echo htmlspecialchars($reset_url); ?></a>
</p>

<p style="margin:0 0 0;font-size:13px;color:#6b7280;">Si no solicitaste esta cuenta, ignora este correo.</p>
