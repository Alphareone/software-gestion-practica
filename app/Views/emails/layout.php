<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($subject ?? ''); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f0f4f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f4f8;">
<tr>
<td align="center" style="padding:32px 16px;">

  <!-- Card -->
  <table role="presentation" width="100%" style="max-width:520px;background-color:#ffffff;border-radius:12px;overflow:hidden;">
  <tr>
  <td style="padding:0;">

    <!-- Brand -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr>
    <td style="padding:32px 32px 16px;">
      <table role="presentation" cellpadding="0" cellspacing="0">
      <tr>
        <?php if (!empty($logo_url)): ?>
        <td style="vertical-align:middle;padding-right:12px;">
          <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($brand_name); ?>" width="48" height="48" style="display:block;border-radius:8px;">
        </td>
        <?php endif; ?>
        <td style="vertical-align:middle;">
          <div style="font-size:16px;font-weight:600;color:#111827;letter-spacing:-0.02em;line-height:1.2;"><?php echo htmlspecialchars($brand_name); ?></div>
          <div style="font-size:12px;color:#6b7280;line-height:1.3;"><?php echo htmlspecialchars($brand_company); ?></div>
        </td>
      </tr>
      </table>
    </td>
    </tr>
    </table>

    <!-- Separator -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr>
    <td style="padding:0 32px;">
      <div style="height:1px;background-color:#e2e8f0;"></div>
    </td>
    </tr>
    </table>

    <!-- Content -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr>
    <td style="padding:24px 32px 32px;font-size:14px;line-height:1.6;color:#374151;">
      <?php echo $content ?? ''; ?>
    </td>
    </tr>
    </table>

  </td>
  </tr>
  </table>

  <!-- Footer -->
  <table role="presentation" width="100%" style="max-width:520px;">
  <tr>
  <td style="padding:16px 0 0;text-align:center;font-size:10px;text-transform:uppercase;letter-spacing:0.04em;color:#9ca3af;">
    <?php echo htmlspecialchars($brand_company); ?>
    &middot;
    <?php echo htmlspecialchars($app_email); ?>
  </td>
  </tr>
  </table>

</td>
</tr>
</table>
</body>
</html>
