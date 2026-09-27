<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inconveniente en el Servidor - 500</title>
    <!-- Tipografía de Alta Calidad -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@200;300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #09090b;
            --text-primary: #fafafa;
            --text-muted: #71717a;
            --border-color: #27272a;
            --accent-color: #f4f4f5;
            --accent-hover: #18181b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .container {
            max-width: 420px;
            width: 100%;
            text-align: left;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Icono Ultra-Limpio */
        .icon-wrapper {
            margin-bottom: 2rem;
        }

        svg {
            width: 32px;
            height: 32px;
            stroke: var(--text-muted);
            fill: none;
            stroke-width: 1.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* Tipografía Refinada y Espaciada */
        .error-code {
            font-size: 1.1rem;
            font-weight: 400;
            color: var(--text-muted);
            letter-spacing: 0.15em;
            text-transform: uppercase;
            margin-bottom: 0.75rem;
        }

        h1 {
            font-size: 2.25rem;
            font-weight: 300;
            letter-spacing: -0.03em;
            line-height: 1.2;
            margin-bottom: 1.25rem;
            color: var(--text-primary);
        }

        p {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 2.5rem;
            font-weight: 300;
        }

        /* Botón de Contorno Minimalista */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.75rem;
            font-size: 0.9rem;
            font-weight: 400;
            color: var(--text-primary);
            background-color: transparent;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
        }

        .btn:hover {
            background-color: var(--accent-color);
            color: var(--bg-color);
            border-color: var(--accent-color);
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Icono de Línea Fina sin Animaciones Flashy -->
        <div class="icon-wrapper">
            <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
        </div>

        <div class="error-code">Error 500</div>
        <h1>Inconveniente temporal en el servidor.</h1>
        <p>No se pudo completar tu solicitud debido a una anomalía interna. El incidente ha sido registrado de forma segura. Por favor, inténtalo de nuevo más tarde.</p>
        
        <a href="<?php echo URLROOT; ?>/dashboard" class="btn">Volver al inicio</a>
    </div>

</body>
</html>
