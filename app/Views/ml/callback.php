<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isSuccess ? 'Conexión Exitosa' : 'Error de Conexión'; ?> | <?php echo htmlspecialchars($brand); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .callback-card {
            background: #fff;
            border-radius: 20px;
            padding: 48px 40px;
            max-width: 440px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08), 0 8px 24px rgba(0,0,0,0.04);
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .icon-wrap {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: <?php echo $accentBg; ?>;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }
        .callback-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        .callback-message {
            font-size: 1rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        .callback-nickname {
            font-size: 1.1rem;
            font-weight: 600;
            color: <?php echo $accentColor; ?>;
            margin-bottom: 32px;
        }
        .callback-hint {
            font-size: 0.9rem;
            color: #94a3b8;
            line-height: 1.5;
        }
        .brand-footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #f1f5f9;
            font-size: 0.75rem;
            color: #cbd5e1;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="callback-card">
        <div class="icon-wrap"><?php echo $icon; ?></div>
        <h1 class="callback-title"><?php echo $isSuccess ? '¡Conexión Exitosa!' : 'Error de Conexión'; ?></h1>
        <p class="callback-message"><?php echo htmlspecialchars($message); ?></p>
        <?php if ($isSuccess && $nickname): ?>
            <p class="callback-nickname"><?php echo htmlspecialchars($nickname); ?></p>
        <?php endif; ?>
        <p class="callback-hint">Ya puedes cerrar esta ventana y revisar la tienda en el panel.</p>
        <div class="brand-footer"><?php echo htmlspecialchars($brand); ?></div>
    </div>
</body>
</html>
