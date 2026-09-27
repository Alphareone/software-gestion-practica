/* Dashboard - KPIs, notifications, charts */
(function () {
  'use strict';

  var ROOT = (window._urlRoot || '') + '';
  var DATA = window.DASHBOARD_DATA || {};

  // ── Theme helpers ──
  function cssVar(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
  }

  function themeMode() {
    return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
  }

  // ── Count-up animation ──
  function animateValue(el, target, suffix) {
    if (!el) return;
    suffix = suffix || '';
    var duration = 700;
    var start = 0;
    var startTime = null;
    function step(timestamp) {
      if (!startTime) startTime = timestamp;
      var progress = Math.min((timestamp - startTime) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      var current = Math.floor(eased * target);
      el.textContent = current + suffix;
      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        el.textContent = target + suffix;
      }
    }
    requestAnimationFrame(step);
  }

  function animateKPIs() {
    document.querySelectorAll('.kpi-value').forEach(function (el) {
      var target = parseInt(el.getAttribute('data-count'), 10) || 0;
      animateValue(el, target);
    });
  }

  function initKPIs() {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          animateKPIs();
          observer.disconnect();
        }
      });
    }, { threshold: 0.3 });
    var row = document.getElementById('kpiRow');
    if (row) observer.observe(row);
  }

  // ── Charts ──
  var _charts = [];

  function destroyCharts() {
    _charts.forEach(function (c) { if (c) c.destroy(); });
    _charts = [];
  }

  function renderStockChart(sr) {
    var el = document.getElementById('stockChart');
    if (!el || typeof ApexCharts === 'undefined') return;
    var ts = cssVar('--text-secondary', '#a0988e');
    var border = cssVar('--border', '#2e2a25');
    var mode = themeMode();

    var ranges = [
      { label: '0',       name: 'Sin stock',      color: '#ef4444' },
      { label: '1-2',     name: 'Muy bajo',       color: '#f97316' },
      { label: '3-5',     name: 'Bajo',           color: '#f59e0b' },
      { label: '6-10',    name: 'Medio',          color: '#eab308' },
      { label: '11-20',   name: 'Alto',           color: '#3b82f6' },
      { label: '21-50',   name: 'Muy alto',       color: '#6366f1' },
      { label: '51+',     name: 'Máximo',         color: '#10b981' },
    ];
    var values = [sr.empty||0, sr.low||0, sr.medium||0, sr.mid||0, sr.high||0, sr.vhigh||0, sr.max||0];
    var total = values.reduce(function(a,b){return a+b;}, 0);

    var series = ranges.map(function(r, i) {
      return { name: r.label, data: [values[i]] };
    });
    var colors = ranges.map(function(r) { return r.color; });
    var stockLabels = ranges.map(function(r) { return r.name + ' (' + r.label + ')'; });

    var chart = new ApexCharts(el, {
      chart: {
        type: 'bar', height: 160, stacked: true, stackType: '100%',
        toolbar: { show: false }, foreColor: ts
      },
      theme: { mode: mode },
      series: series,
      colors: colors,
      plotOptions: {
        bar: {
          horizontal: true, borderRadius: 3, borderRadiusApplication: 'around',
          barHeight: '70%', distributed: false
        }
      },
      dataLabels: {
        enabled: true,
        formatter: function (val, opt) {
          var idx = opt.seriesIndex;
          var v = values[idx];
          return v > 0 ? v : '';
        },
        offsetX: 0,
        style: { fontSize: '11px', fontWeight: 700, fontFamily: '"Plus Jakarta Sans", sans-serif', colors: ['#fff'] }
      },
      states: { hover: { filter: { type: 'none' } } },
      xaxis: { categories: [''], labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false } },
      yaxis: { show: false, labels: { show: false } },
      grid: { show: false, padding: { left: -10, right: 0, top: 0, bottom: 0 } },
      tooltip: {
        custom: function (p) {
          var idx = p.seriesIndex;
          var v = values[idx];
          var pct = total > 0 ? Math.round(v / total * 100) : 0;
          return '<div class="dash-tooltip">' +
            '<div class="dash-tooltip-label">' + escHtml(stockLabels[idx]) + '</div>' +
            '<div class="dash-tooltip-value">' +
            '<span class="dash-tooltip-dot" style="background:' + colors[idx] + '"></span>' +
            v + ' productos (' + pct + '%)</div></div>';
        }
      },
      legend: {
        position: 'bottom', fontSize: '10px', fontFamily: '"Plus Jakarta Sans", sans-serif',
        labels: { colors: ts }, itemMargin: { horizontal: 6 },
        formatter: function (seriesName, opts) {
          var idx = opts.seriesIndex;
          return seriesName + ' (' + values[idx] + ')';
        }
      },
      responsive: [{ breakpoint: 480, options: { legend: { position: 'bottom', fontSize: '9px' } } }]
    });
    chart.render();
    _charts.push(chart);
  }

  function renderDonutChart(active, total) {
    var el = document.getElementById('donutChart');
    if (!el || typeof ApexCharts === 'undefined') return;
    var ts = cssVar('--text-secondary', '#a0988e');
    var mode = themeMode();
    var inactive = Math.max(0, total - active);

    var chart = new ApexCharts(el, {
      chart: {
        type: 'donut', height: 220, toolbar: { show: false }, foreColor: ts
      },
      theme: { mode: mode },
      series: [active, inactive],
      labels: ['Activos (' + active + ')', 'Inactivos (' + inactive + ')'],
      colors: ['#34d399', '#f87171'],
      plotOptions: {
        pie: {
          donut: { size: '65%', labels: { show: true, name: { show: true }, value: { show: true, fontSize: '14px', fontFamily: '"Plus Jakarta Sans", sans-serif' }, total: { show: true, label: 'Total', fontSize: '12px', fontFamily: '"Plus Jakarta Sans", sans-serif', formatter: function () { return total; } } } }
        }
      },
      dataLabels: { enabled: false },
      states: { hover: { filter: { type: 'none' } } },
      stroke: { show: false },
      legend: {
        position: 'bottom', fontSize: '11px', fontFamily: '"Plus Jakarta Sans", sans-serif',
        labels: { colors: ts }, itemMargin: { horizontal: 8 }
      },
      tooltip: {
        y: { formatter: function (val) { return val + ' productos'; } }
      },
      responsive: [{ breakpoint: 480, options: { legend: { position: 'bottom' } } }]
    });
    chart.render();
    _charts.push(chart);
  }

  function renderCharts() {
    destroyCharts();
    var sr = DATA.stockRanges || {};
    renderStockChart(sr);
    renderDonutChart(DATA.active || 0, DATA.total || 0);
  }

  // ── Notifications ──
  function loadNotifications() {
    var list = document.getElementById('dashNotifList');
    if (!list) return;
    fetch(ROOT + '/notifications/data?page=1&unread_only=1')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error || !data.notifications || data.notifications.length === 0) {
          list.innerHTML = '<div class="text-sm text-muted text-center py-6">Sin notificaciones</div>';
          return;
        }
        var html = '';
        var max = Math.min(data.notifications.length, 5);
        for (var i = 0; i < max; i++) {
          var n = data.notifications[i];
          html += '<div class="flex items-start gap-3 py-2 border-b border-border/30 last:border-0">' +
            '<span class="mt-0.5 text-muted shrink-0">' +
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>' +
            '</span>' +
            '<div class="flex-1 min-w-0">' +
            '<p class="text-sm text-primary truncate">' + escHtml(n.title) + '</p>' +
            '<p class="text-xs text-muted">' + escHtml(n.message) + '</p>' +
            '</div>' +
            '<span class="text-xs text-muted shrink-0">' + timeAgo(n.created_at) + '</span>' +
            '</div>';
        }
        list.innerHTML = html;
      })
      .catch(function () {
        list.innerHTML = '<div class="text-sm text-muted text-center py-6">Error al cargar</div>';
      });
  }

  function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function timeAgo(dateStr) {
    var now = Date.now();
    var ts = new Date(dateStr + ' UTC').getTime();
    var diff = Math.floor((now - ts) / 1000);
    if (diff < 60) return 'ahora';
    if (diff < 3600) return Math.floor(diff / 60) + ' min';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    if (diff < 172800) return 'ayer';
    return new Date(dateStr).toLocaleDateString('es-CL');
  }

  // ── Init ──
  document.addEventListener('DOMContentLoaded', function () {
    initKPIs();
    renderCharts();
    loadNotifications();
  });

  // Re-animate KPIs and charts on theme toggle
  var origRefresh = window.refreshDashboardCharts;
  window.refreshDashboardCharts = function () {
    if (typeof origRefresh === 'function') origRefresh();
    animateKPIs();
    renderCharts();
  };
})();
