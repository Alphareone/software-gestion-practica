/**
 * Reemplazo vanilla JS para comportamientos de Bootstrap
 * Collapse + Modal. Cargar DESPUES de que el DOM este listo.
 */
(function() {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
  }

  ready(function() {
    // ─── Collapse: data-bs-toggle="collapse" ───
    document.addEventListener('click', function(e) {
      var toggle = e.target.closest('[data-bs-toggle="collapse"]');
      if (!toggle) return;
      e.preventDefault();
      var targetId = toggle.getAttribute('href') || toggle.getAttribute('data-bs-target');
      if (!targetId) return;
      var target = document.querySelector(targetId);
      if (target) target.classList.toggle('show');
    });

    // ─── Modal: data-bs-toggle="modal" ───
    document.addEventListener('click', function(e) {
      var toggle = e.target.closest('[data-bs-toggle="modal"]');
      if (!toggle) return;
      var targetId = toggle.getAttribute('data-bs-target');
      if (!targetId) return;
      var modal = document.querySelector(targetId);
      if (!modal) return;
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      document.body.style.overflow = 'hidden';

      // Crear backdrop si no existe
      var backdrop = modal.querySelector('.modal-backdrop-custom');
      if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop-custom fixed inset-0 bg-black/45 z-[-1]';
        modal.prepend(backdrop);
        backdrop.addEventListener('click', function() { closeModal(modal); });
      }
    });

    // ─── Modal Dismiss: data-bs-dismiss="modal" ───
    document.addEventListener('click', function(e) {
      var btn = e.target.closest('[data-bs-dismiss="modal"]');
      if (!btn) return;
      var modal = btn.closest('.wp-modal, [id$="Modal"], [role="dialog"]');
      if (modal) closeModal(modal);
    });

    function closeModal(modal) {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      document.body.style.overflow = '';
    }

    // Cerrar modal con Escape
    document.addEventListener('keydown', function(e) {
      if (e.key !== 'Escape') return;
      var modal = document.querySelector('.wp-modal:not(.hidden), [id$="Modal"]:not(.hidden)');
      if (modal) closeModal(modal);
    });
  });
})();
