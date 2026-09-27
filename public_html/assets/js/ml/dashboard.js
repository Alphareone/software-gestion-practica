/* ═══════════════════════════════════════════════
   Connections Dashboard — Metrics, UI
   ═══════════════════════════════════════════════ */
(function () {
  'use strict';

  var cfg = window.ML_DASHBOARD_CONFIG;
  if (!cfg) return;

  var metricsUrl = cfg.metricsUrl || '/connections/metrics';

  // ── DOM refs ──
  var elPublished = document.getElementById('metricPublished');
  var elActive = document.getElementById('metricActive');
  var elLastSync = document.getElementById('metricLastSync');
  var elError = document.getElementById('kpiError');
  var chartsSection = document.getElementById('chartsSection');

  // ── Fetch state ──
  var _fetchController = null;

  // ── Count-up animation ──
  function animateValue(el, target) {
    if (!el) return;
    var duration = 600;
    var start = 0;
    var startTime = null;

    function step(timestamp) {
      if (!startTime) startTime = timestamp;
      var progress = Math.min((timestamp - startTime) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      var current = Math.floor(eased * target);
      el.textContent = current;
      if (progress < 1) {
        window.requestAnimationFrame(step);
      } else {
        el.textContent = target;
      }
    }
    window.requestAnimationFrame(step);
  }

  // ── Format relative time ──
  function timeAgo(isoString) {
    if (!isoString) return 'Nunca';
    var now = new Date();
    var date = new Date(isoString.replace(' ', 'T') + 'Z');
    var diffMs = now - date;
    if (diffMs < 0) return 'Ahora';
    var diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 1) return 'Ahora';
    if (diffMin < 60) return 'Hace ' + diffMin + ' min';
    var diffHr = Math.floor(diffMin / 60);
    if (diffHr < 24) return 'Hace ' + diffHr + ' h' + (diffHr > 1 ? '' : '');
    var diffDays = Math.floor(diffHr / 24);
    if (diffDays < 7) return 'Hace ' + diffDays + ' d' + (diffDays > 1 ? '' : '');
    return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
  }

  function syncAgeClass(isoString) {
    if (!isoString) return '';
    var now = new Date();
    var date = new Date(isoString.replace(' ', 'T') + 'Z');
    var diffMs = now - date;
    if (diffMs < 86400000) return '';
    return 'metric-card--warn';
  }

  // ── Finish loading state ──
  function finishLoading() {
    if (chartsSection) chartsSection.classList.remove('is-loading');
  }

  function setError(msg) {
    if (elError) {
      elError.textContent = msg;
      elError.style.display = 'block';
    }
    if (elPublished) elPublished.textContent = '-';
    if (elActive) elActive.textContent = '-';
    if (elLastSync) elLastSync.textContent = '-';
  }

  // ── Fetch metrics and update UI ──
  function loadMetrics() {
    _fetchController = new AbortController();
    var timeout = setTimeout(function () { _fetchController.abort(); }, 30000);

    fetch(metricsUrl, { credentials: 'same-origin', signal: _fetchController.signal })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        clearTimeout(timeout);
        _fetchController = null;
        if (data.error) {
          finishLoading();
          setError(data.error);
          return;
        }

        animateValue(elPublished, data.total || 0);
        animateValue(elActive, data.active || 0);
        if (elLastSync) elLastSync.textContent = timeAgo(data.last_sync_at);

        var lastSyncCard = document.getElementById('metricLastSyncCard');
        if (lastSyncCard) {
          lastSyncCard.className = 'metric-card' + (syncAgeClass(data.last_sync_at) ? ' metric-card--warn' : '');
        }

        finishLoading();
      })
      .catch(function () {
        clearTimeout(timeout);
        _fetchController = null;
        finishLoading();
        setError('Error al cargar métricas. Recarga la página.');
      });
  }

  // ── Abort fetch on navigation ──
  function abortPendingFetch() {
    if (_fetchController) {
      _fetchController.abort();
      _fetchController = null;
    }
  }
  window.addEventListener('beforeunload', abortPendingFetch);
  window.addEventListener('pagehide', abortPendingFetch);

  loadMetrics();
})();
