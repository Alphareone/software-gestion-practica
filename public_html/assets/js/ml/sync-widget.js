/* ═══════════════════════════════════════════════
   Sync Widget — Floating status indicator
   Informativo: muestra estado del sync automático
   ═══════════════════════════════════════════════ */
(function () {
  'use strict';

  var cfg = window.ML_SYNC_CONFIG;
  if (!cfg) return;

  var baseUrl = cfg.baseUrl || '';
  var pollInterval = null;
  var countdownInterval = null;
  var expanded = false;
  var isSyncing = false;
  var nextSyncAt = null;

  function startPolling(fast) {
    clearInterval(pollInterval);
    var ms = fast ? 5000 : 30000;
    pollInterval = setInterval(fetchStatus, ms);
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function timeAgo(dateStr) {
    if (!dateStr) return 'Nunca';
    var diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60) return 'Hace menos de 1 min';
    if (diff < 3600) return 'Hace ' + Math.floor(diff / 60) + ' min';
    if (diff < 86400) return 'Hace ' + Math.floor(diff / 3600) + ' h';
    return 'Hace ' + Math.floor(diff / 86400) + ' d';
  }

  function formatCountdown(seconds) {
    if (seconds <= 0) return null;
    var m = Math.floor(seconds / 60);
    var s = seconds % 60;
    var mm = m < 10 ? '0' + m : '' + m;
    var ss = s < 10 ? '0' + s : '' + s;
    return mm + ':' + ss;
  }

  function getEarliestNextSync(statuses) {
    var earliest = null;
    for (var i = 0; i < statuses.length; i++) {
      if (statuses[i].status === 'syncing') return null;
      if (!statuses[i].next_sync_at) continue;
      var ts = new Date(statuses[i].next_sync_at).getTime();
      if (!earliest || ts < earliest) earliest = ts;
    }
    return earliest;
  }

  function statusIcon(status) {
    switch (status) {
      case 'syncing': return '<span class="sync-dot syncing"></span>';
      case 'error': return '<span class="sync-dot error"></span>';
      default: return '<span class="sync-dot idle"></span>';
    }
  }

  function statusText(s) {
    if (s.status === 'syncing') {
      var pct = s.total_products > 0 ? Math.round((s.synced_products / s.total_products) * 100) : 0;
      return 'Sincronizando autom\u00e1ticamente... ' + pct + '%';
    }
    if (s.status === 'error') return 'Error: ' + (s.last_error || 'Desconocido');
    if (s.total_products === 0) return 'Sin datos — primer sync pendiente';
    return 'Actualizado ' + timeAgo(s.last_sync_at);
  }

  function updateCountdown() {
    var el = document.getElementById('syncNextSync');
    if (!el || !nextSyncAt) return;

    var remaining = Math.ceil((nextSyncAt - Date.now()) / 1000);
    if (remaining <= 0) {
      el.textContent = '';
      el.style.display = 'none';
      clearInterval(countdownInterval);
      countdownInterval = null;
      return;
    }

    var text = formatCountdown(remaining);
    if (text) {
      el.textContent = 'Pr\u00f3ximo sync en ' + text;
      el.style.display = 'inline';
    }
  }

  function startCountdown(timestamp) {
    clearInterval(countdownInterval);
    nextSyncAt = timestamp;
    updateCountdown();
    countdownInterval = setInterval(updateCountdown, 1000);
  }

  function stopCountdown() {
    clearInterval(countdownInterval);
    countdownInterval = null;
    nextSyncAt = null;
    var el = document.getElementById('syncNextSync');
    if (el) {
      el.textContent = '';
      el.style.display = 'none';
    }
  }

  function renderWidget(statuses) {
    var body = document.getElementById('syncWidgetBody');
    if (!body) return;

    if (!statuses || statuses.length === 0) {
      body.innerHTML = '<div class="sync-widget-empty">No hay tiendas activas</div>';
      stopCountdown();
      return;
    }

    var html = '';
    for (var i = 0; i < statuses.length; i++) {
      var s = statuses[i];
      html += '<div class="sync-widget-store">';
      html += '<div class="sync-widget-store-header">';
      html += statusIcon(s.status);
      html += '<span class="sync-widget-store-name">' + esc(s.store_name) + '</span>';
      html += '</div>';
      html += '<div class="sync-widget-store-meta">';
      html += '<span class="sync-widget-status">' + statusText(s) + '</span>';
      if (s.total_products > 0) {
        html += '<span class="sync-widget-count">' + s.total_products.toLocaleString() + ' productos</span>';
      }
      html += '</div>';
      if (s.status === 'syncing' && s.total_products > 0) {
        var pct = Math.round((s.synced_products / s.total_products) * 100);
        html += '<div class="sync-widget-progress"><div class="sync-widget-progress-bar" style="width:' + pct + '%"></div></div>';
      }
      html += '</div>';
    }

    body.innerHTML = html;

    // Manejar countdown
    var earliest = getEarliestNextSync(statuses);
    if (earliest) {
      startCountdown(earliest);
    } else {
      stopCountdown();
    }
  }

  function fetchStatus() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', baseUrl + '/connections/syncStatus', true);
    xhr.onload = function () {
      if (xhr.status === 200) {
        try {
          var data = JSON.parse(xhr.responseText);
          renderWidget(data);
          updateBadge(data);
          var wasSyncing = isSyncing;
          isSyncing = false;
          for (var i = 0; i < data.length; i++) {
            if (data[i].status === 'syncing') { isSyncing = true; break; }
          }
          if (wasSyncing !== isSyncing) {
            startPolling(isSyncing);
          }
        } catch (e) { }
      }
    };
    xhr.send();
  }

  function updateBadge(statuses) {
    var badge = document.getElementById('syncBadge');
    if (!badge) return;
    var syncing = false;
    var hasError = false;
    for (var i = 0; i < statuses.length; i++) {
      if (statuses[i].status === 'syncing') { syncing = true; }
      if (statuses[i].status === 'error') { hasError = true; }
    }
    badge.style.display = syncing ? 'block' : 'none';

    // Update toggle color based on aggregate status
    var toggle = document.getElementById('syncWidgetToggle');
    if (!toggle) return;
    toggle.classList.remove('status-idle', 'status-syncing', 'status-error');
    if (syncing) {
      toggle.classList.add('status-syncing');
    } else if (hasError) {
      toggle.classList.add('status-error');
    } else {
      toggle.classList.add('status-idle');
    }

    // Health dot on toggle
    var dot = toggle.querySelector('.sync-toggle-dot');
    if (dot) dot.remove();
    if (syncing) return; // no dot while syncing (badge shows instead)
    dot = document.createElement('span');
    dot.className = 'sync-toggle-dot' + (hasError ? ' error' : '');
    toggle.appendChild(dot);
  }

  function toggleWidget() {
    // Close user dropdown if open
    var userMenu = document.getElementById('userDropdownMenu');
    if (userMenu) userMenu.classList.remove('show');

    var panel = document.getElementById('syncPanel');
    if (!panel) return;
    expanded = !expanded;
    panel.style.display = expanded ? 'block' : 'none';

    var el = document.getElementById('syncWidgetToggle');
    if (el) el.classList.add('spinning');
  }

  // Init
  var toggle = document.getElementById('syncWidgetToggle');
  if (toggle) {
    toggle.addEventListener('click', toggleWidget);
    toggle.addEventListener('animationend', function () {
      this.classList.remove('spinning');
    });
  }

  fetchStatus();
  startPolling(false);

  window.SyncWidget = { refresh: fetchStatus, toggle: toggleWidget };
})();
