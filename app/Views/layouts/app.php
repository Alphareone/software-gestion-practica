<?php
require_once __DIR__ . '/../../Helpers/IconHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($page_title)) {
    $page_title = 'Dashboard';
}
if (!isset($page_description)) {
    $page_description = '';
}
if (!isset($current_nav)) {
    $current_nav = 'dashboard';
}
if (!isset($view_content)) {
    $view_content = '';
}
if (!isset($is_admin)) {
    $is_admin = !empty($_SESSION['is_admin']);
}

$role = $_SESSION['role'] ?? (!empty($_SESSION['is_admin']) ? 'admin' : 'user');

$_user = [
    'username' => $_SESSION['username'] ?? 'Usuario',
    'email'    => $_SESSION['email'] ?? '',
    'is_admin' => $is_admin,
    'role'     => $role,
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> | <?php echo SITENAME; ?></title>

    <script>
    (function(){
        var t = localStorage.getItem('theme') || 'auto';
        if (t === 'auto') {
            t = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
        }
        document.documentElement.setAttribute('data-theme', t);
        document.documentElement.classList.toggle('dark', t === 'dark');
        document.documentElement.classList.add('preload');
        window.addEventListener('load', function(){
            document.documentElement.classList.remove('preload');
        });
    })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo URLROOT; ?>/assets/css/dist/tailwind.css?v=<?php echo CSS_VERSION; ?>">
</head>
<body class="dashboard-app">
    <!-- Dummy input para absorber autofill del navegador (Chrome ignora autocomplete="off") -->
    <input type="text" aria-hidden="true" tabindex="-1" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;" autocomplete="off">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content" style="position: relative;">
        <header>
            <div class="header-left">
                <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Menú">
                    <?php echo icon('menu', ['size' => 20]); ?>
                </button>
                
                <div class="header-branding">
                    <div class="header-title">
                        <span class="header-app-name"><?php echo BRAND_NAME; ?></span>
                    </div>
                </div>
            </div>

            <div class="header-right">
                <?php
                $notifUnread = 0;
                if (!empty($_SESSION['user_id']) && class_exists('Database')) {
                    try {
                        $nDb = new Database();
                        $nStmt = $nDb->query('SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0');
                        $nStmt->execute([$_SESSION['user_id']]);
                        $notifUnread = (int) $nStmt->fetch()->cnt;
                    } catch (Exception $e) {}
                }
                ?>
                <button type="button" class="notif-btn" id="notifBtn" aria-label="Notificaciones">
                    <?php echo icon('bell', ['size' => 18]); ?>
                    <span class="notif-badge" id="notifBadge" style="<?php echo $notifUnread > 0 ? '' : 'display:none;'; ?>"><?php echo $notifUnread > 9 ? '9+' : $notifUnread; ?></span>
                </button>
                <div class="notif-overlay" id="notifOverlay" style="display:none;"></div>
                <div class="notif-panel" id="notifPanel">
                    <div class="notif-panel-header">
                        <span class="notif-panel-title">Notificaciones</span>
                        <button type="button" class="notif-panel-close" id="notifPanelClose"><?php echo icon('x', ['size' => 20]); ?></button>
                    </div>
                    <div class="notif-panel-tabs" id="notifPanelTabs">
                        <button type="button" class="notif-panel-tab active" data-filter="unread">No leídas</button>
                        <button type="button" class="notif-panel-tab" data-filter="all">Todas</button>
                    </div>
                    <div class="notif-panel-list" id="notifList">
                        <div class="notif-empty">Cargando...</div>
                    </div>
                    <div class="notif-panel-footer" id="notifPanelFooter" style="display:none;">
                        <button type="button" class="notif-mark-all" id="notifMarkAll">Marcar todas como le&iacute;das</button>
                    </div>
                </div>
                <div class="user-profile" id="userProfileBtn">
                    <span class="user-name"><?php echo htmlspecialchars($_user['username'] ?? 'Usuario'); ?></span>
                    <?php echo icon('chevron-down', ['size' => 16, 'class' => 'user-chevron']); ?>
                </div>
                <div class="user-dropdown-menu" id="userDropdownMenu">
                    <div class="dropdown-header">
                        <span class="dropdown-username"><?php echo htmlspecialchars($_user['username'] ?? 'Usuario'); ?></span>
                        <span class="dropdown-email"><?php echo htmlspecialchars($_user['email'] ?? ''); ?></span>
                        <span class="dropdown-role">Rol: <?php echo htmlspecialchars($roleLabel); ?></span>
                    </div>
                    <div class="dropdown-divider"></div>
                    <button type="button" class="theme-option" data-theme="light">
                        <?php echo icon('sun', ['size' => 16]); ?>
                        <span>Claro</span>
                        <span class="theme-check">✓</span>
                    </button>
                    <button type="button" class="theme-option" data-theme="dark">
                        <?php echo icon('moon', ['size' => 16]); ?>
                        <span>Oscuro</span>
                        <span class="theme-check">✓</span>
                    </button>
                    <button type="button" class="theme-option" data-theme="auto">
                        <?php echo icon('monitor', ['size' => 16]); ?>
                        <span>Sistema</span>
                        <span class="theme-check">✓</span>
                    </button>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo URLROOT; ?>/info" class="dropdown-item">
                        <?php echo icon('info', ['size' => 18]); ?>
                        <span>Informaciones</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo URLROOT; ?>/settings" class="dropdown-item">
                        <?php echo icon('sliders-horizontal', ['size' => 18]); ?>
                        <span>Configuración</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" onclick="event.preventDefault(); showLogoutModal();" class="dropdown-item dropdown-logout">
                        <?php echo icon('log-out', ['size' => 18]); ?>
                        <span>Cerrar sesión</span>
                    </a>
                </div>
            </div>
        </header>

        <div class="container" style="position: relative;">
            <?php if (!empty($page_title)): ?>
            <section class="dashboard-title">
                <div class="page-title-wrapper">
                    <?php if (!empty($page_icon)): ?>
                    <span class="page-title-icon"><?php echo $page_icon; ?></span>
                    <?php endif; ?>
                    <div class="page-title-text">
                        <h1><?php echo htmlspecialchars($page_title); ?></h1>
                        <?php if (!empty($page_description)): ?>
                        <p><?php echo htmlspecialchars($page_description); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($page_action_url)): ?>
                <a href="<?php echo htmlspecialchars($page_action_url); ?>" class="page-action">
                    <?php echo $page_action_label; ?>
                </a>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <!-- Aquí va el contenido específico de cada vista -->
            <?php 
            // Asegurar que $data esté disponible en la vista parcial
            if (!isset($data)) {
                $data = [];
            }
            ?>
            <div class="content-area">
                <?php include $view_content; ?>
            </div>
            </div>
        </div>
    </main>

    <?php
    // Mostrar mensajes de toast
    $toast_msgs = [];
    if (!empty($_SESSION['success'])) {
        $toast_msgs[] = ['msg' => (string)$_SESSION['success'], 'type' => 'success'];
        unset($_SESSION['success']);
    }
    if (!empty($_SESSION['error'])) {
        $toast_msgs[] = ['msg' => (string)$_SESSION['error'], 'type' => 'error'];
        unset($_SESSION['error']);
    }
        if (!empty($_SESSION['warning'])) {
            $toast_msgs[] = ['msg' => (string)$_SESSION['warning'], 'type' => 'warning'];
            unset($_SESSION['warning']);
        }
        if (!empty($_SESSION['info'])) {
            $toast_msgs[] = ['msg' => (string)$_SESSION['info'], 'type' => 'info'];
            unset($_SESSION['info']);
        }
        if (!empty($_COOKIE['flash_success'])) {
            $toast_msgs[] = ['msg' => (string)$_COOKIE['flash_success'], 'type' => 'success'];
            $isHttps = isHttps();
            setcookie('flash_success', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

    if (!empty($toast_msgs)) {
        echo "<script>document.addEventListener('DOMContentLoaded',function(){\n";
        foreach ($toast_msgs as $t) {
            $m = htmlspecialchars($t['msg'], ENT_QUOTES);
            $type = $t['type'];
            echo "showToast('" . $m . "', '" . $type . "', 6000);\n";
        }
        echo "});</script>";
    }
    ?>
<form method="POST" action="<?php echo URLROOT; ?>/auth/logout" id="logoutForm" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
</form>
<script src="<?php echo URLROOT; ?>/assets/js/ui-collapse.js"></script>
<script src="<?php echo URLROOT; ?>/assets/js/ui-engine.js"></script>
<script src="<?php echo URLROOT; ?>/assets/js/sidebar.js"></script>
<script src="<?php echo URLROOT; ?>/assets/js/toast.js"></script>
<?php if ($current_nav === 'connections'): ?>
<script src="<?php echo URLROOT; ?>/assets/js/ml/dashboard.js?v=26"></script>
<?php endif; ?>
<script>
document.documentElement.classList.remove('auth-fade-out');
</script>

<script>
// Throttled download toast notifications
document.addEventListener('click', function(e) {
  var link = e.target.closest('a[data-throttle="true"]');
  if (!link) return;
  var filename = link.getAttribute('download') || link.textContent.trim() || 'Archivo XLSX';
  if (typeof showDownloadToast === 'function') {
    showDownloadToast(filename);
  }
});
</script>

<script>
// Smooth fade-out on all internal navigation
document.addEventListener('click', function(e) {
  var link = e.target.closest('a[href]');
  if (!link) return;

  var href = link.getAttribute('href');
  if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || link.target === '_blank' || link.hasAttribute('download') || link.hasAttribute('data-no-fade') || link.classList.contains('nav-collapse-toggle')) return;

  if (document.documentElement.classList.contains('auth-fade-out')) return;
  if (link.href === window.location.href) return;

  e.preventDefault();
  document.documentElement.classList.add('auth-fade-out');
  setTimeout(function() { window.location.href = link.href; }, 200);
});

  // Idle session warning — toast-based
  var _urlRoot = '<?php echo URLROOT; ?>';
  var _idleTimeout = <?php echo defined('SESSION_IDLE_TIMEOUT') ? SESSION_IDLE_TIMEOUT : 180; ?>;
  var _warningAt = Math.max(0, _idleTimeout - 30);
  var _lastActivity = Date.now();
  var _toastShown = false;
  var _idleToast = null;

  function resetActivity() {
    _lastActivity = Date.now();
    if (_toastShown) {
      _toastShown = false;
      if (_idleToast) { hideToast(_idleToast); _idleToast = null; }
    }
  }

  // Ping servidor cada 60s si el usuario está activo
  setInterval(function() {
    var elapsed = Math.floor((Date.now() - _lastActivity) / 1000);
    if (elapsed < _warningAt) {
      var x = new XMLHttpRequest();
      x.open('GET', _urlRoot + '/auth/keepalive', true);
      x.send();
    }
  }, 60000);

  // Check cada 2s si estamos cerca del timeout
  setInterval(function() {
    var elapsed = Math.floor((Date.now() - _lastActivity) / 1000);
    var remaining = _idleTimeout - elapsed;

    if (remaining <= 0) {
      window.location.href = _urlRoot + '/auth?reason=idle_timeout';
    } else if (remaining <= 30 && !_toastShown) {
      _toastShown = true;
      _idleToast = showToast({
        title: 'Sesión por expirar',
        message: 'Tu sesión cerrará en menos de 30 segundos por inactividad.',
        type: 'warning',
        duration: 0,
        actions: [{
          text: 'Seguir conectado',
          onclick: function() {
            resetActivity();
            var x = new XMLHttpRequest();
            x.open('GET', _urlRoot + '/auth/keepalive', true);
            x.send();
            hideToast(_idleToast);
            _idleToast = null;
          }
        }]
      });
    }
  }, 2000);

  // Eventos de actividad del usuario
  document.addEventListener('mousemove', resetActivity);
  document.addEventListener('keydown', resetActivity);
  document.addEventListener('click', resetActivity);
  document.addEventListener('scroll', resetActivity);
  document.addEventListener('touchstart', resetActivity);

  // Notification system
  var _csrf = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>';

  var notifBtn = document.getElementById('notifBtn');
  var notifPanel = document.getElementById('notifPanel');
  var notifOverlay = document.getElementById('notifOverlay');
  var notifList = document.getElementById('notifList');
  var notifBadge = document.getElementById('notifBadge');
  var notifMarkAll = document.getElementById('notifMarkAll');
  var notifPanelClose = document.getElementById('notifPanelClose');
  var notifPanelTabs = document.getElementById('notifPanelTabs');
  var notifPanelFooter = document.getElementById('notifPanelFooter');
  var notifPage = 1;
  var notifFilter = 'all';
  var notifHasMore = false;
  var notifLoading = false;

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

  function createNotifItem(n) {
    var item = document.createElement('div');
    item.className = 'notif-item' + (n.is_read ? '' : ' notif-unread');
    item.setAttribute('data-id', n.id);
    item.innerHTML =
      '<div class="notif-dot' + (n.is_read ? '' : ' unread') + '"></div>' +
      '<div class="notif-body">' +
        '<div class="notif-title-text">' + escHtml(n.title) + '</div>' +
        '<div class="notif-msg">' + escHtml(n.message) + '</div>' +
        '<div class="notif-time">' + timeAgo(n.created_at) + '</div>' +
      '</div>' +
      '<button type="button" class="notif-delete-btn" title="Eliminar notificaci&oacute;n">' +
        '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
      '</button>';
    item.querySelector('.notif-delete-btn').addEventListener('click', function(e) {
      e.stopPropagation();
      var fd = new FormData();
      fd.append('csrf_token', _csrf);
      fd.append('id', n.id);
      fetch(_urlRoot + '/notifications/delete/' + n.id, { method: 'POST', body: fd })
        .then(function() {
          item.style.transition = 'opacity 0.2s, transform 0.2s';
          item.style.opacity = '0';
          item.style.transform = 'translateX(20px)';
          setTimeout(function() { item.remove(); }, 200);
        })
        .catch(function() {});
    });
    item.addEventListener('click', function() {
      if (!n.is_read) {
        var fd = new FormData();
        fd.append('csrf_token', _csrf);
        fd.append('id', n.id);
        fetch(_urlRoot + '/notifications/markRead', { method: 'POST', body: fd });
      }
      if (n.product_id) {
        closeNotifPanel();
        window.location.href = _urlRoot + '/products/edit/' + n.product_id;
      }
    });
    return item;
  }

  function loadNotifs(append) {
    if (notifLoading) return;
    notifLoading = true;

    if (!append) {
      notifPage = 1;
      notifList.innerHTML = '<div class="notif-empty">Cargando...</div>';
    }

    var url = _urlRoot + '/notifications/data?page=' + notifPage;
    if (notifFilter === 'unread') url += '&unread_only=1';

    fetch(url, { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        notifLoading = false;
        if (data.error) return;

        if (data.unread > 0) {
          notifBadge.style.display = '';
          notifBadge.textContent = data.unread > 9 ? '9+' : data.unread;
        } else {
          notifBadge.style.display = 'none';
        }

        if (append) {
          notifList.querySelector('.notif-load-more')?.remove();
        } else {
          notifList.innerHTML = '';
        }

        if (data.notifications.length === 0) {
          notifList.innerHTML = '<div class="notif-empty">Sin notificaciones</div>';
          notifPanelFooter.style.display = 'none';
          return;
        }

        notifPanelFooter.style.display = '';
        data.notifications.forEach(function(n) {
          notifList.appendChild(createNotifItem(n));
        });

        notifHasMore = data.has_more;
        if (notifHasMore) {
          var loader = document.createElement('div');
          loader.className = 'notif-load-more';
          loader.textContent = 'Cargando más...';
          notifList.appendChild(loader);
        }
      })
      .catch(function() {
        notifLoading = false;
        if (!append) notifList.innerHTML = '<div class="notif-empty" style="color:#ef4444;">Error al cargar notificaciones</div>';
      });
  }

  function closeNotifPanel() {
    notifPanel.classList.remove('notif-panel-open');
    notifOverlay.style.display = 'none';
  }

  if (notifBtn && notifPanel) {
    function openNotifPanel() {
      notifOverlay.style.display = '';
      notifPanel.classList.add('notif-panel-open');
      notifPage = 1;
      notifFilter = 'unread';
      notifPanelTabs.querySelectorAll('.notif-panel-tab').forEach(function(t) {
        t.classList.toggle('active', t.dataset.filter === 'unread');
      });
      loadNotifs(false);
    }

    function updateBadge(unread) {
      if (unread > 0) {
        notifBadge.style.display = '';
        notifBadge.textContent = unread > 9 ? '9+' : unread;
      } else {
        notifBadge.style.display = 'none';
      }
    }

    notifBtn.addEventListener('click', function() {
      if (notifPanel.classList.contains('notif-panel-open')) {
        closeNotifPanel();
      } else {
        openNotifPanel();
      }
    });

    notifOverlay.addEventListener('click', closeNotifPanel);
    notifPanelClose.addEventListener('click', closeNotifPanel);

    // Tab switching
    notifPanelTabs.addEventListener('click', function(e) {
      var tab = e.target.closest('.notif-panel-tab');
      if (!tab) return;
      notifPanelTabs.querySelectorAll('.notif-panel-tab').forEach(function(t) {
        t.classList.toggle('active', t === tab);
      });
      notifFilter = tab.dataset.filter;
      loadNotifs(false);
    });

    // Scroll infinite
    notifList.addEventListener('scroll', function() {
      if (!notifHasMore || notifLoading) return;
      if (notifList.scrollTop + notifList.clientHeight >= notifList.scrollHeight - 100) {
        notifPage++;
        loadNotifs(true);
      }
    });

    if (notifMarkAll) {
      notifMarkAll.addEventListener('click', function() {
        var fd = new FormData();
        fd.append('csrf_token', _csrf);
        fetch(_urlRoot + '/notifications/markAllRead', { method: 'POST', body: fd })
          .then(function() { loadNotifs(false); })
          .catch(function() {});
      });
    }

    // Poll badge every 30s
    setInterval(function() {
      fetch(_urlRoot + '/notifications/data', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.error) return;
          updateBadge(data.unread);
        })
        .catch(function() {});
    }, 15000);
  }
</script>

<div class="sync-widget" id="syncWidget">
  <div class="sync-widget-toggle" id="syncWidgetToggle">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
    </svg>
    <span class="sync-widget-badge" id="syncBadge"></span>
  </div>
  <div class="sync-widget-panel" id="syncPanel">
    <div class="sync-widget-header">
      <h4>Estado del sistema <span class="sync-widget-next-sync" id="syncNextSync" style="display:none"></span></h4>
      <span class="sync-widget-close" id="syncWidgetClose">✕</span>
    </div>
    <div class="sync-widget-body" id="syncWidgetBody">
      <div class="sync-widget-empty">Cargando...</div>
    </div>
  </div>
</div>
<script>
window.ML_SYNC_CONFIG = {
  baseUrl: '<?php echo URLROOT; ?>',
  isAdmin: true
};
</script>
<script src="<?php echo URLROOT; ?>/assets/js/ml/sync-widget.js?v=4"></script>
<script>
document.getElementById('syncWidgetClose').addEventListener('click', function() {
  document.getElementById('syncPanel').style.display = 'none';
});
</script>

<!-- Global confirmation modal -->
<div id="confirmModal" class="umodal" style="display:none;">
  <div class="umodal-backdrop"></div>
  <div class="umodal-card">
    <div class="umodal-body">
      <div class="umodal-icon" id="confirmIcon"><?php echo icon('info', ['size' => 28]); ?></div>
      <h3 class="umodal-title" id="confirmTitle">Confirmar</h3>
      <p class="umodal-text" id="confirmMessage"></p>
      <div id="confirmPhraseWrap" style="display:none;margin-top:0.75rem;">
        <input type="text" id="confirmPhraseInput" placeholder="" class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2.5 py-1.5 focus:outline-none focus:border-accent w-full" style="text-align:center;font-size:0.82rem;" autocomplete="off" spellcheck="false">
      </div>
    </div>
    <div class="umodal-actions" id="confirmActions">
      <button type="button" class="ut-btn ut-btn-secondary" id="confirmCancel">Cancelar</button>
      <button type="button" class="ut-btn" id="confirmBtn" disabled>Confirmar</button>
    </div>
  </div>
</div>

<script>
var confirmModal = document.getElementById('confirmModal');
var confirmTitle = document.getElementById('confirmTitle');
var confirmMessage = document.getElementById('confirmMessage');
var confirmBtn = document.getElementById('confirmBtn');
var confirmCancel = document.getElementById('confirmCancel');
var confirmIcon = document.getElementById('confirmIcon');
var confirmCallback = null;
var confirmPhraseWrap = document.getElementById('confirmPhraseWrap');
var confirmPhraseInput = document.getElementById('confirmPhraseInput');

function openConfirm(opts) {
  confirmTitle.textContent = opts.title || 'Confirmar';
  confirmMessage.innerHTML = opts.message || '';
  confirmBtn.textContent = opts.confirmText || 'Confirmar';
  confirmBtn.className = 'ut-btn ' + (opts.confirmClass || '');
  confirmCallback = opts.onConfirm || null;

  // Handle requirePhrase
  if (opts.requirePhrase) {
    confirmPhraseWrap.style.display = 'block';
    confirmPhraseInput.placeholder = 'Escribe "' + opts.requirePhrase + '" para confirmar';
    confirmPhraseInput.value = '';
    confirmBtn.disabled = true;
    confirmBtn.style.opacity = '0.4';
    confirmBtn.style.pointerEvents = 'none';
    confirmPhraseInput.oninput = function() {
      var match = this.value.trim() === opts.requirePhrase;
      confirmBtn.disabled = !match;
      confirmBtn.style.opacity = match ? '1' : '0.4';
      confirmBtn.style.pointerEvents = match ? 'auto' : 'none';
    };
    // Focus input after a short delay
    setTimeout(function() { confirmPhraseInput.focus(); }, 100);
  } else {
    confirmPhraseWrap.style.display = 'none';
    confirmPhraseInput.value = '';
    confirmBtn.disabled = false;
    confirmBtn.style.opacity = '';
    confirmBtn.style.pointerEvents = '';
  }

  if (opts.confirmClass === 'btn-danger') {
    confirmIcon.className = 'umodal-icon danger';
    confirmIcon.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
  } else if (opts.confirmClass === 'btn-warn') {
    confirmIcon.className = 'umodal-icon';
    confirmIcon.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
  } else {
    confirmIcon.className = 'umodal-icon success';
    confirmIcon.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
  }

  confirmModal.style.display = '';
  document.body.style.overflow = 'hidden';
}

function closeConfirm() {
  confirmModal.style.display = 'none';
  document.body.style.overflow = '';
  confirmCallback = null;
  confirmPhraseWrap.style.display = 'none';
  confirmPhraseInput.value = '';
  confirmBtn.disabled = false;
  confirmBtn.style.opacity = '';
  confirmBtn.style.pointerEvents = '';
}

confirmBtn.addEventListener('click', function() {
  if (confirmCallback) confirmCallback();
  closeConfirm();
});
confirmCancel.addEventListener('click', closeConfirm);
confirmModal.querySelector('.umodal-backdrop').addEventListener('click', closeConfirm);
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape' && confirmModal.style.display !== 'none') closeConfirm();
});

function showLogoutModal() {
  openConfirm({
    title: 'Cerrar sesión',
    message: '¿Estás seguro de que quieres cerrar la sesión?',
    confirmText: 'Cerrar sesión',
    confirmClass: 'btn-danger',
    onConfirm: function() {
      document.documentElement.classList.add('auth-fade-out');
      setTimeout(function(){ document.getElementById('logoutForm').submit(); }, 200);
    }
  });
}
</script>

</body>
</html>
