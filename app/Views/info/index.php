<?php require_once __DIR__ . '/../../Helpers/IconHelper.php'; ?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informaciones | <?php echo SITENAME; ?></title>

    <script>
    (function(){
        var t = localStorage.getItem('theme') || 'auto';
        if (t === 'auto') {
            t = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
        }
        document.documentElement.setAttribute('data-theme', t);
        document.documentElement.classList.toggle('dark', t === 'dark');
    })();
    </script>

    <link rel="stylesheet" href="<?php echo URLROOT; ?>/assets/css/dist/tailwind.css?v=<?php echo CSS_VERSION; ?>">
</head>
<body class="info-page">

    <div class="info-nav">
        <div class="info-nav-inner">
            <a href="<?php echo URLROOT; ?>/dashboard" class="info-back">
                <?php echo icon('arrow-left', ['size' => 16]); ?>
                <span>Volver al dashboard</span>
            </a>
            <button type="button" class="info-theme-toggle" id="themeToggle" aria-label="Cambiar tema">
                <span class="theme-icon-auto"><?php echo icon('monitor', ['size' => 16]); ?></span>
                <span class="theme-icon-light"><?php echo icon('sun', ['size' => 16]); ?></span>
                <span class="theme-icon-dark"><?php echo icon('moon', ['size' => 16]); ?></span>
            </button>
        </div>
    </div>

    <div class="info-wrapper">

        <div class="info-hero">
            <h1 class="info-hero-title">Informaciones</h1>
            <p class="info-hero-subtitle">
                Todo lo que necesitas saber sobre el sistema, su funcionamiento y soporte.
            </p>
        </div>

        <div class="info-body">

        <div class="info-layout">

            <nav class="info-sidenav">
                <div class="sidenav-sticky">
                    <span class="sidenav-label">En esta página</span>
                    <ul class="sidenav-list">
                        <li><a href="#about" class="sidenav-link active">Acerca de</a></li>
                        <li><a href="#terms" class="sidenav-link">Términos y Condiciones</a></li>
                        <li><a href="#privacy" class="sidenav-link">Privacidad</a></li>
                        <li><a href="#contact" class="sidenav-link">Contacto</a></li>
                        <li><a href="#docs" class="sidenav-link">Documentación</a></li>
                    </ul>
                </div>
            </nav>

            <div class="info-content">

            <!-- Acerca del Sistema -->
            <section class="info-section active" id="about">
                <div class="info-section-header">
                    <span class="info-section-icon"><?php echo icon('info', ['size' => 20]); ?></span>
                    <h2 class="info-section-title">Acerca del Sistema</h2>
                </div>
                <div class="info-section-body">
                    <div class="info-table">
                        <div class="info-table-row">
                            <span class="info-table-label">Versión</span>
                            <span class="info-table-value"><?php echo htmlspecialchars($data['version']); ?></span>
                        </div>
                        <div class="info-table-row">
                            <span class="info-table-label">Año</span>
                            <span class="info-table-value"><?php echo htmlspecialchars($data['year']); ?></span>
                        </div>
                        <div class="info-table-row">
                            <span class="info-table-label">Empresa</span>
                            <span class="info-table-value"><?php echo htmlspecialchars($data['company']); ?></span>
                        </div>
                        <div class="info-table-row">
                            <span class="info-table-label">Ubicación</span>
                            <span class="info-table-value"><?php echo htmlspecialchars($data['location']); ?></span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Términos y Condiciones -->
            <section class="info-section" id="terms">
                <div class="info-section-header">
                    <span class="info-section-icon"><?php echo icon('file-text', ['size' => 20]); ?></span>
                    <h2 class="info-section-title">Términos y Condiciones</h2>
                </div>
                <div class="info-section-body">
                    <div class="prose">
                        <h3>1. Aceptación de los Términos</h3>
                        <p>
                            Al acceder y utilizar <strong><?php echo BRAND_NAME; ?></strong>, usted acepta
                            cumplir con estos términos y condiciones. Si no está de acuerdo con alguna parte,
                            no debe utilizar el sistema.
                        </p>

                        <h3>2. Uso del Sistema</h3>
                        <p>
                            El sistema está destinado exclusivamente para uso interno del equipo de
                            <?php echo htmlspecialchars($data['company']); ?>. Cada usuario es responsable
                            de mantener la confidencialidad de sus credenciales de acceso y de todas las
                            actividades que ocurran bajo su cuenta.
                        </p>
                        <ul>
                            <li>No utilizar el sistema para fines ilegales o no autorizados.</li>
                            <li>No intentar vulnerar la seguridad del sistema ni acceder a datos de otros usuarios.</li>
                            <li>No realizar modificaciones no autorizadas en la configuración del sistema.</li>
                            <li>Reportar inmediatamente cualquier fallo de seguridad o uso no autorizado.</li>
                        </ul>

                        <h3>3. Conexiones con MercadoLibre</h3>
                        <p>
                            El sistema se conecta a MercadoLibre mediante OAuth 2.0. Al vincular una tienda,
                            usted autoriza a <?php echo BRAND_NAME; ?> a gestionar productos, precios y stock
                            en nombre de su cuenta de MercadoLibre. Las operaciones realizadas a través del
                            sistema son de su exclusiva responsabilidad.
                        </p>

                        <h3>4. Propiedad Intelectual</h3>
                        <p>
                            Todo el código fuente, diseño y contenido del sistema es propiedad intelectual de
                            <?php echo htmlspecialchars($data['company']); ?>. No está permitida la
                            reproducción, distribución o modificación del software sin autorización expresa.
                        </p>

                        <h3>5. Limitación de Responsabilidad</h3>
                        <p>
                            <?php echo BRAND_NAME; ?> se proporciona "tal cual", sin garantías de ningún tipo.
                            <?php echo htmlspecialchars($data['company']); ?> no será responsable por daños
                            directos o indirectos derivados del uso o la imposibilidad de uso del sistema,
                            incluyendo pérdida de datos, pérdida de ventas o interrupción del negocio.
                        </p>

                        <h3>6. Modificaciones</h3>
                        <p>
                            Nos reservamos el derecho de modificar estos términos en cualquier momento. Los
                            cambios serán notificados a través del sistema. El uso continuado del sistema
                            después de dichas modificaciones constituye su aceptación de los nuevos términos.
                        </p>

                        <p style="margin-top:1.5rem;font-size:0.8rem;;">
                            Última actualización: <?php echo $data['year']; ?>.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Privacidad -->
            <section class="info-section" id="privacy">
                <div class="info-section-header">
                    <span class="info-section-icon"><?php echo icon('shield-check', ['size' => 20]); ?></span>
                    <h2 class="info-section-title">Privacidad</h2>
                </div>
                <div class="info-section-body">
                    <div class="prose">
                        <h3>Recopilación de Datos</h3>
                        <p>
                            <strong><?php echo BRAND_NAME; ?></strong> recopila únicamente los datos necesarios
                            para su funcionamiento:
                        </p>
                        <ul>
                            <li>Información de la cuenta de usuario (nombre, email, rol).</li>
                            <li>Tokens de acceso OAuth para integración con MercadoLibre.</li>
                            <li>Datos de productos, precios y stock gestionados a través del sistema.</li>
                            <li>Registros de actividad y auditoría de cambios.</li>
                        </ul>

                        <h3>Almacenamiento y Seguridad</h3>
                        <p>
                            Los datos se almacenan en servidores seguros con acceso restringido. Los tokens
                            de MercadoLibre se cifran mediante AES-256-CBC antes de ser almacenados en la
                            base de datos. Las contraseñas se almacenan utilizando hash bcrypt.
                        </p>
                        <ul>
                            <li>Cifrado de tokens: AES-256-CBC con clave de 256 bits.</li>
                            <li>Hash de contraseñas: bcrypt con costo 12.</li>
                            <li>Comunicaciones vía HTTPS obligatorio en producción.</li>
                            <li>Protección contra CSRF en todas las mutaciones.</li>
                        </ul>

                        <h3>Uso de la Información</h3>
                        <p>
                            La información recopilada se utiliza exclusivamente para:
                        </p>
                        <ul>
                            <li>Operar y mantener el sistema de gestión.</li>
                            <li>Sincronizar datos con MercadoLibre según las acciones del usuario.</li>
                            <li>Generar reportes y auditorías internas.</li>
                            <li>Mejorar la funcionalidad y experiencia del sistema.</li>
                        </ul>

                        <h3>Retención y Eliminación</h3>
                        <p>
                            Los datos se conservan mientras la cuenta del usuario esté activa. Al desvincular
                            una tienda o eliminar una cuenta, los datos asociados se eliminan o anonimizan
                            según corresponda. Los registros de auditoría pueden conservarse por razones
                            legales y operativas.
                        </p>

                        <h3>Contacto</h3>
                        <p>
                            Para consultas sobre privacidad y tratamiento de datos, contacte al administrador
                            del sistema a través de los canales indicados en la sección de
                            <a href="#" onclick="var l=document.querySelector('.sidenav-link[href=\\'#contact\\']');if(l){l.click();}return false;">Contacto</a>.
                        </p>

                        <p style="margin-top:1.5rem;font-size:0.8rem;;">
                            Última actualización: <?php echo $data['year']; ?>.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Contacto -->
            <section class="info-section" id="contact">
                <div class="info-section-header">
                    <span class="info-section-icon"><?php echo icon('mail', ['size' => 20]); ?></span>
                    <h2 class="info-section-title">Contacto</h2>
                </div>
                <div class="info-section-body">
                    <div class="contact-grid">
                        <div class="contact-card">
                            <div class="contact-card-icon">
                                <?php echo icon('user', ['size' => 20]); ?>
                            </div>
                            <div class="contact-card-body">
                                <span class="contact-card-label">Administrador del Sistema</span>
                                <span class="contact-card-value"><?php echo htmlspecialchars(APP_EMAIL); ?></span>
                            </div>
                        </div>
                        <div class="contact-card">
                            <div class="contact-card-icon">
                                <?php echo icon('mail', ['size' => 20]); ?>
                            </div>
                            <div class="contact-card-body">
                                <span class="contact-card-label">Desarrollador</span>
                                <span class="contact-card-value">matiasbaxman@gmail.com</span>
                            </div>
                        </div>
                        <div class="contact-card">
                            <div class="contact-card-icon">
                                <?php echo icon('globe', ['size' => 20]); ?>
                            </div>
                            <div class="contact-card-body">
                                <span class="contact-card-label">Centro de Pr&aacute;cticas</span>
                                <span class="contact-card-value"><?php echo htmlspecialchars($data['company']); ?></span>
                            </div>
                        </div>
                        <div class="contact-card">
                            <div class="contact-card-icon">
                                <?php echo icon('link-2', ['size' => 20]); ?>
                            </div>
                            <div class="contact-card-body">
                                <span class="contact-card-label">Ubicaci&oacute;n</span>
                                <span class="contact-card-value"><?php echo htmlspecialchars($data['location']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Documentación -->
            <section class="info-section" id="docs">
                <div class="info-section-header">
                    <span class="info-section-icon"><?php echo icon('book-open', ['size' => 20]); ?></span>
                    <h2 class="info-section-title">Documentación y Recursos</h2>
                </div>
                <div class="info-section-body">
                    <div class="docs-grid">
                        <a href="https://developers.mercadolibre.cl/es_ar/api-docs-es" target="_blank" rel="noopener noreferrer" class="doc-card">
                            <span class="doc-card-icon"><?php echo icon('external-link', ['size' => 18]); ?></span>
                            <div class="doc-card-body">
                                <span class="doc-card-title">API MercadoLibre</span>
                                <span class="doc-card-desc">Documentaci&oacute;n oficial de la API</span>
                            </div>
                        </a>
                        <a href="https://github.com/matiasbaxman/e-practicas-software" target="_blank" rel="noopener noreferrer" class="doc-card">
                            <span class="doc-card-icon"><?php echo icon('external-link', ['size' => 18]); ?></span>
                            <div class="doc-card-body">
                                <span class="doc-card-title">Repositorio</span>
                                <span class="doc-card-desc">C&oacute;digo fuente en GitHub</span>
                            </div>
                        </a>
                        <a href="https://github.com/matiasbaxman" target="_blank" rel="noopener noreferrer" class="doc-card">
                            <span class="doc-card-icon"><?php echo icon('external-link', ['size' => 18]); ?></span>
                            <div class="doc-card-body">
                                <span class="doc-card-title">Contribuci&oacute;n</span>
                                <span class="doc-card-desc">Mat&iacute;as Baxman — Desarrollo full-stack</span>
                            </div>
                        </a>
                    </div>
                </div>
            </section>

        </div>

        </div>

        </div>

        <div class="info-footer">
            <p class="info-copyright">
                &copy; <?php echo $data['year']; ?> <?php echo htmlspecialchars($data['company']); ?>. 
                Todos los derechos reservados.
            </p>
            <p class="info-credit">
                <?php echo BRAND_NAME; ?> <?php echo BRAND_VERSION; ?>
            </p>
        </div>

    </div>

<script>
(function() {
    var toggle = document.getElementById('themeToggle');
    if (!toggle) return;

    var current = localStorage.getItem('theme') || 'auto';
    toggle.setAttribute('data-current-theme', current);

    function setTheme(t) {
        localStorage.setItem('theme', t);
        if (t === 'auto') {
            t = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
        }
        document.documentElement.setAttribute('data-theme', t);
        document.documentElement.classList.toggle('dark', t === 'dark');
        toggle.setAttribute('data-current-theme', localStorage.getItem('theme') || 'auto');
    }

    toggle.addEventListener('click', function() {
        var order = ['auto', 'light', 'dark'];
        var cur = localStorage.getItem('theme') || 'auto';
        var idx = order.indexOf(cur);
        var next = order[(idx + 1) % order.length];
        setTheme(next);
    });
})();
</script>

<script>
(function() {
    var links = document.querySelectorAll('.sidenav-link');
    var sections = document.querySelectorAll('.info-section');

    function showSection(id) {
        sections.forEach(function(s) {
            s.classList.toggle('active', s.id === id);
        });
        links.forEach(function(l) {
            l.classList.toggle('active', l.getAttribute('href') === '#' + id);
        });
        history.replaceState(null, '', '#' + id);
    }

    links.forEach(function(l) {
        l.addEventListener('click', function(e) {
            e.preventDefault();
            var id = this.getAttribute('href').substring(1);
            showSection(id);
        });
    });

    var hash = window.location.hash.substring(1);
    if (hash && document.getElementById(hash)) {
        showSection(hash);
    }
})();
</script>

</body>
</html>
