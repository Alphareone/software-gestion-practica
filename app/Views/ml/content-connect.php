<div class="connect-page">

  <?php if (empty($data['ml_configured'])): ?>
    <div class="alert-config">
      <?php echo icon('info', ['size' => 18]); ?>
      <span>Antes de conectar, configura las credenciales de MercadoLibre en Configuraci&oacute;n &rarr; MercadoLibre, y agrega esta URL en el devcenter de MercadoLibre: <strong><?php echo htmlspecialchars(ML_REDIRECT_URI); ?></strong></span>
    </div>
  <?php endif; ?>

  <div class="connect-card">
    <div class="connect-card-header">
      <span class="connect-card-icon-large" aria-hidden="true"><?php echo icon('link-2', ['size' => 48]); ?></span>
      <h3 class="connect-card-title">
        <?php if (!empty($data['reauthorize'])): ?>
          Reconectar tienda
        <?php else: ?>
          Conectar tienda de MercadoLibre
        <?php endif; ?>
      </h3>
      <p class="connect-card-desc">Copia este enlace y sigue los pasos para vincular tu tienda.</p>
    </div>

    <div class="connect-copy-field">
      <input
        type="text"
        id="authUrl"
        class="connect-copy-input"
        value="<?php echo htmlspecialchars($data['auth_url']); ?>"
        readonly
        aria-label="Enlace de autorización de MercadoLibre"
      >
      <button type="button" class="connect-copy-icon-btn" id="btnCopyIcon" aria-label="Copiar enlace" title="Copiar enlace">
        <span class="connect-copy-icon connect-copy-icon-copy" aria-hidden="true"><?php echo icon('copy', ['size' => 16]); ?></span>
        <span class="connect-copy-icon connect-copy-icon-check" aria-hidden="true"><?php echo icon('circle-check', ['size' => 16]); ?></span>
      </button>
    </div>

    <div class="connect-steps">
      <div class="connect-step">
        <span class="connect-step-num">1</span>
        <p>Copia el enlace de arriba y pégalo en tu navegador. Si tienes otra sesión de ML activa, usa una ventana privada.</p>
      </div>
      <div class="connect-step">
        <span class="connect-step-num">2</span>
        <p>Inicia sesión con tu cuenta de MercadoLibre y autoriza la aplicación.</p>
      </div>
      <div class="connect-step">
        <span class="connect-step-num">3</span>
        <p>Serás redirigido automáticamente y la tienda quedará conectada.</p>
      </div>
    </div>

    <div class="connect-help">
      <a href="<?php echo URLROOT; ?>/connections/adminstores" class="connect-help-link">
        <?php echo icon('arrow-left', ['size' => 14]); ?>
        Volver
      </a>
    </div>
  </div>

</div>

<script>
(function() {
  var authUrl = document.getElementById('authUrl');
  var btnCopyIcon = document.getElementById('btnCopyIcon');
  if (!authUrl || !btnCopyIcon) return;

  function copyLink() {
    var text = authUrl.value;
    var onCopied = function() {
      btnCopyIcon.classList.add('is-copied');
      setTimeout(function() {
        btnCopyIcon.classList.remove('is-copied');
      }, 2000);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(onCopied).catch(function() {
        authUrl.select();
        authUrl.setSelectionRange(0, 99999);
        document.execCommand('copy');
        onCopied();
      });
      return;
    }

    authUrl.select();
    authUrl.setSelectionRange(0, 99999);
    document.execCommand('copy');
    onCopied();
  }

  btnCopyIcon.addEventListener('click', copyLink);
})();
</script>
