<div class="help-page">
  
  <div class="help-header">
    <h1 class="help-title">Informaciones</h1>
    <p class="help-subtitle">Ayuda, información del sistema y recursos de soporte</p>
  </div>

  <div class="help-grid">
    
    <!-- Información del Sistema -->
    <div class="help-section">
      <h2 class="help-section-title">
        <?php echo icon('info', ['size' => 16]); ?>
        <span>Acerca del Sistema</span>
      </h2>
      <div class="help-list">
        <div class="help-list-item">
          <span class="help-list-label">Versión</span>
          <span class="help-list-value"><?php echo htmlspecialchars($data['version']); ?></span>
        </div>
        <div class="help-list-item">
          <span class="help-list-label">Estado</span>
          <span class="help-list-value"><span class="badge-status">Estable</span></span>
        </div>
        <div class="help-list-item">
          <span class="help-list-label">Año</span>
          <span class="help-list-value"><?php echo htmlspecialchars($data['year']); ?></span>
        </div>
        <div class="help-list-item">
          <span class="help-list-label">Empresa</span>
          <span class="help-list-value"><?php echo htmlspecialchars($data['company']); ?></span>
        </div>
        <div class="help-list-item">
          <span class="help-list-label">Ubicación</span>
          <span class="help-list-value"><?php echo htmlspecialchars($data['location']); ?></span>
        </div>
      </div>
    </div>

    <!-- Estado del Sistema -->
    <div class="help-section">
      <h2 class="help-section-title">
        <?php echo icon('activity', ['size' => 16]); ?>
        <span>Estado del Sistema</span>
      </h2>
      <div class="status-cards">
        <div class="status-card">
          <div class="status-indicator status-ok"></div>
          <div class="status-content">
            <span class="status-label">API MercadoLibre</span>
            <span class="status-value">Operativo</span>
          </div>
        </div>
        <div class="status-card">
          <div class="status-indicator status-ok"></div>
          <div class="status-content">
            <span class="status-label">Base de Datos</span>
            <span class="status-value">Operativo</span>
          </div>
        </div>
        <div class="status-card">
          <div class="status-indicator status-ok"></div>
          <div class="status-content">
            <span class="status-label">Servidor</span>
            <span class="status-value">Operativo</span>
          </div>
        </div>
      </div>
    </div>

    <!-- FAQs -->
    <div class="help-section">
      <h2 class="help-section-title">
        <?php echo icon('help-circle', ['size' => 16]); ?>
        <span>Preguntas Frecuentes</span>
      </h2>
      <div class="faq-list">
        <div class="faq-item">
          <div class="faq-question">
            <?php echo icon('chevron-right', ['size' => 14]); ?>
            <span>¿Cómo conecto una nueva tienda de MercadoLibre?</span>
          </div>
          <div class="faq-answer">
            Ve a Conexiones → Tiendas conectadas → Conectar. Copia el enlace de autorización y sigue los pasos en tu navegador.
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-question">
            <?php echo icon('chevron-right', ['size' => 14]); ?>
            <span>¿Cómo cambio precios masivamente?</span>
          </div>
          <div class="faq-answer">
            Ve a Precios → Cambio masivo. Selecciona los productos, aplica el porcentaje o monto deseado y confirma el cambio.
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-question">
            <?php echo icon('chevron-right', ['size' => 14]); ?>
            <span>¿Qué hago si el token de ML expira?</span>
          </div>
          <div class="faq-answer">
            Ve a Conexiones y vuelve a autorizar la tienda. El sistema te notificará automáticamente cuando el token esté por expirar.
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-question">
            <?php echo icon('chevron-right', ['size' => 14]); ?>
            <span>¿Cómo audito cambios de stock?</span>
          </div>
          <div class="faq-answer">
            Ve a Auditorías → Stock. El sistema mostrará un historial de todos los cambios con fecha, usuario y valores anterior/nuevo.
          </div>
        </div>
      </div>
    </div>

    <!-- Contactos -->
    <div class="help-section">
      <h2 class="help-section-title">
        <?php echo icon('mail', ['size' => 16]); ?>
        <span>Contactos de Soporte</span>
      </h2>
      <div class="contact-list">
        <div class="contact-item">
          <div class="contact-icon">
            <?php echo icon('user', ['size' => 16]); ?>
          </div>
          <div class="contact-info">
            <span class="contact-label">Administrador del Sistema</span>
            <span class="contact-value">soporte@empresa.cl</span>
          </div>
        </div>
        <div class="contact-item">
          <div class="contact-icon">
            <?php echo icon('building', ['size' => 16]); ?>
          </div>
          <div class="contact-info">
            <span class="contact-label">Centro de Prácticas</span>
            <span class="contact-value"><?php echo htmlspecialchars($data['company']); ?></span>
          </div>
        </div>
        <div class="contact-item">
          <div class="contact-icon">
            <?php echo icon('map-pin', ['size' => 16]); ?>
          </div>
          <div class="contact-info">
            <span class="contact-label">Ubicación</span>
            <span class="contact-value"><?php echo htmlspecialchars($data['location']); ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Documentación -->
    <div class="help-section">
      <h2 class="help-section-title">
        <?php echo icon('book-open', ['size' => 16]); ?>
        <span>Documentación y Recursos</span>
      </h2>
      <div class="docs-list">
        <a href="https://developers.mercadolibre.com.cl" target="_blank" rel="noopener noreferrer" class="doc-link">
          <span class="doc-icon"><?php echo icon('external-link', ['size' => 14]); ?></span>
          <span class="doc-text">API MercadoLibre - Documentación oficial</span>
        </a>
        <a href="https://developers.mercadolibre.com.cl/es_es/items" target="_blank" rel="noopener noreferrer" class="doc-link">
          <span class="doc-icon"><?php echo icon('external-link', ['size' => 14]); ?></span>
          <span class="doc-text">Gestión de Productos (Items)</span>
        </a>
        <a href="https://developers.mercadolibre.com.cl/es_es/precios" target="_blank" rel="noopener noreferrer" class="doc-link">
          <span class="doc-icon"><?php echo icon('external-link', ['size' => 14]); ?></span>
          <span class="doc-text">Gestión de Precios</span>
        </a>
        <a href="<?php echo URLROOT; ?>/dashboard" class="doc-link">
          <span class="doc-icon"><?php echo icon('arrow-right', ['size' => 14]); ?></span>
          <span class="doc-text">Volver al Dashboard</span>
        </a>
      </div>
    </div>

    <!-- Tecnologías -->
    <div class="help-section">
      <h2 class="help-section-title">
        <?php echo icon('code', ['size' => 16]); ?>
        <span>Tecnologías</span>
      </h2>
      <div class="tech-list-inline">
        <span class="tech-badge">PHP 8+</span>
        <span class="tech-badge">MySQL</span>
        <span class="tech-badge">PDO</span>
        <span class="tech-badge">Bootstrap 5</span>
        <span class="tech-badge">MercadoLibre API</span>
      </div>
    </div>

  </div>

</div>
