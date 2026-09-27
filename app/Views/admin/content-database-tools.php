<div class="settings-page">

  <p style="margin:0 0 1.25rem;font-size:0.85rem;color:var(--text-secondary);line-height:1.6;">
    Acciones de mantenimiento para limpiar registros acumulados. Todas requieren confirmaci&oacute;n escrita.
  </p>

  <div class="settings-section">
    <div class="setting-row">
      <div class="setting-info">
        <span class="setting-label">Notificaciones</span>
        <span class="setting-hint"><?php echo (int)$data['notif_count']; ?> registros. Notificaciones de stock, tokens, etc.</span>
      </div>
      <div class="setting-control">
        <button type="button" class="dt-purge-btn" data-table="notifications">Purgar todo</button>
      </div>
    </div>

    <div class="setting-row">
      <div class="setting-info">
        <span class="setting-label">Registro de actividad</span>
        <span class="setting-hint"><?php echo (int)$data['activity_count']; ?> registros. Acciones de usuarios y administradores.</span>
      </div>
      <div class="setting-control">
        <button type="button" class="dt-purge-btn" data-table="activity">Purgar todo</button>
      </div>
    </div>

    <div class="setting-row">
      <div class="setting-info">
        <span class="setting-label">Registro de correos</span>
        <span class="setting-hint"><?php echo (int)$data['email_count']; ?> registros. Correos enviados por el sistema.</span>
      </div>
      <div class="setting-control">
        <button type="button" class="dt-purge-btn" data-table="email">Purgar todo</button>
      </div>
    </div>

    <div class="setting-row setting-row--last">
      <div class="setting-info">
        <span class="setting-label">Auditor&iacute;as</span>
        <span class="setting-hint"><?php echo (int)$data['audit_count']; ?> registros. Resultados de auditor&iacute;as de precios y stock.</span>
      </div>
      <div class="setting-control">
        <button type="button" class="dt-purge-btn" data-table="audit">Purgar todo</button>
      </div>
    </div>
  </div>
</div>

<style>
.dt-purge-btn {
  padding: 0.45rem 1.1rem;
  font-size: 0.82rem;
  font-weight: 600;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  background: #dc2626;
  color: #fff;
  transition: background 0.15s;
}
.dt-purge-btn:hover { background: #b91c1c; }
.dt-purge-btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>

<script>
(function() {
  var csrf = '<?php echo htmlspecialchars($data['csrf_token'], ENT_QUOTES, "UTF-8"); ?>';
  var urlroot = '<?php echo URLROOT; ?>';

  var endpoints = {
    notifications: { url: urlroot + '/notifications/deleteAllUsers', label: 'notificaciones' },
    activity: { url: urlroot + '/admin/purgeAllLogs', label: 'registros de actividad' },
    email: { url: urlroot + '/admin/purgeEmailLogs', label: 'registros de correos' },
    audit: { url: urlroot + '/connections/purgeAudits', label: 'auditor\u00edas' },
  };

  document.querySelectorAll('.dt-purge-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var table = this.getAttribute('data-table');
      var ep = endpoints[table];
      if (!ep) return;

      openConfirm({
        title: 'Purgar ' + ep.label,
        message: '\u00bfEliminar permanentemente todos los ' + ep.label + '? Esta acci\u00f3n no se puede deshacer.<br><br>Escribe <strong>"Si, deseo borrar los registros"</strong> para confirmar.',
        confirmText: 'Purgar todo',
        confirmClass: 'btn-danger',
        requirePhrase: 'Si, deseo borrar los registros',
        onConfirm: function() {
          btn.disabled = true;
          btn.textContent = 'Purgando...';

          var form = new FormData();
          form.set('csrf_token', csrf);

          fetch(ep.url, { method: 'POST', body: form, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
              btn.disabled = false;
              btn.textContent = 'Purgar todo';
              if (d.ok || d.success) {
                var count = d.deleted || 0;
                showToast(ep.label + ' eliminados: ' + count, 'success', 4000);
                setTimeout(function() { location.reload(); }, 1500);
              } else {
                showToast(d.error || 'Error al purgar.', 'error', 6000);
              }
            })
            .catch(function() {
              btn.disabled = false;
              btn.textContent = 'Purgar todo';
              showToast('Error de conexi\u00f3n.', 'error', 6000);
            });
        }
      });
    });
  });
})();
</script>