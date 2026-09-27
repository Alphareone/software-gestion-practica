<?php require_once __DIR__ . '/../../Helpers/IconHelper.php'; ?>

<div class="flex items-center gap-3 mb-5 flex-wrap">
  <div class="flex items-center gap-2 flex-1 min-w-0">
    <input type="text" id="userSearch"
           placeholder="Buscar por usuario o email…"
           value="<?php echo htmlspecialchars($data['search'] ?? ''); ?>"
           class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2.5 py-1.5 focus:outline-none focus:border-accent w-full max-w-sm">
    <button id="userClearBtn"
            class="text-xs text-muted hover:text-primary transition-colors bg-transparent border-none cursor-pointer p-0"
            style="display:<?php echo !empty($data['search']) || !empty($data['role_filter']) ? 'inline-flex' : 'none'; ?>;"
            title="Limpiar filtros">
      <?php echo icon('x', ['size' => 14]); ?>
    </button>
  </div>
  <select id="userRole"
          class="text-xs rounded-lg border border-border bg-surface/50 text-primary px-2.5 py-1.5 focus:outline-none focus:border-accent cursor-pointer">
    <option value="">Todos los roles</option>
    <option value="owner" <?php echo ($data['role_filter'] ?? '') === 'owner' ? 'selected' : ''; ?>>Dueños</option>
    <option value="admin" <?php echo ($data['role_filter'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administradores</option>
    <option value="logistics" <?php echo ($data['role_filter'] ?? '') === 'logistics' ? 'selected' : ''; ?>>Logística</option>
    <option value="sales" <?php echo ($data['role_filter'] ?? '') === 'sales' ? 'selected' : ''; ?>>Ventas</option>
    <option value="products" <?php echo ($data['role_filter'] ?? '') === 'products' ? 'selected' : ''; ?>>Productos</option>
    <option value="user" <?php echo ($data['role_filter'] ?? '') === 'user' ? 'selected' : ''; ?>>Usuarios</option>
  </select>
  <div class="flex items-center gap-1.5">
    <span class="text-xs text-muted mr-0.5">Estado:</span>
    <button data-status="" class="status-pill text-xs px-2.5 py-1.5 rounded-lg border transition-colors cursor-pointer bg-transparent <?php echo empty($data['status_filter']) ? 'bg-accent/10 text-accent border-accent/30' : 'border-border text-muted hover:border-muted hover:text-primary'; ?>">Todos</button>
    <button data-status="active" class="status-pill text-xs px-2.5 py-1.5 rounded-lg border flex items-center gap-1 transition-colors cursor-pointer bg-transparent <?php echo ($data['status_filter'] ?? '') === 'active' ? 'bg-success/10 text-success border-success/30' : 'border-border text-muted hover:border-muted hover:text-primary'; ?>"><span class="w-1.5 h-1.5 rounded-full bg-success"></span>Activos</button>
    <button data-status="inactive" class="status-pill text-xs px-2.5 py-1.5 rounded-lg border flex items-center gap-1 transition-colors cursor-pointer bg-transparent <?php echo ($data['status_filter'] ?? '') === 'inactive' ? 'bg-danger/10 text-danger border-danger/30' : 'border-border text-muted hover:border-muted hover:text-primary'; ?>"><span class="w-1.5 h-1.5 rounded-full bg-danger"></span>Inactivos</button>
  </div>
  <a href="<?php echo URLROOT; ?>/users/create"
     class="ut-btn-green text-xs px-2.5 py-1.5 rounded-lg border-0 hover:opacity-90 transition-opacity flex items-center gap-1.5 ml-auto no-underline shrink-0 whitespace-nowrap">
    Nuevo usuario
  </a>
</div>

<div id="userTableWrap">
<?php if (!empty($data['usuarios'] ?? [])): ?>
<div class="ut-table-wrap">
  <table class="ut-table">
    <thead>
      <tr>
        <th class="ut-th-user">Usuario</th>
        <th class="ut-th-role">Rol</th>
        <th class="ut-th-status">Estado</th>
        <th class="ut-th-2fa hide-mobile">2FA</th>
        <th class="ut-th-activity hide-mobile">Actividad</th>
        <th class="ut-th-date hide-mobile">Registro</th>
        <th class="ut-th-actions" aria-label="Acciones"><span class="sr-only">Acciones</span></th>
      </tr>
    </thead>
    <tbody id="userTableBody">
      <?php foreach ($data['usuarios'] as $u):
        $st = $u->status ?? 'active';
        $role = $u->role ?? 'user';
        $roleLabels = ['owner'=>'Dueño','admin'=>'Administrador','logistics'=>'Logística','sales'=>'Ventas','products'=>'Productos','user'=>'Usuario'];
        $roleLabel = $roleLabels[$role] ?? 'Usuario';
        $lastActivity = $u->last_activity_at ?? null;
        $isOnline = $lastActivity ? (time() - strtotime($lastActivity . ' UTC')) < USER_ONLINE_THRESHOLD : false;
        if ($isOnline) {
          $activityLabel = 'En línea';
          $activityClass = 'online';
        } elseif ($lastActivity) {
          $diff = time() - strtotime($lastActivity . ' UTC');
          if ($diff < 86400) {
            $activityLabel = 'Hoy ' . date('H:i', strtotime($lastActivity . ' UTC'));
          } elseif ($diff < 172800) {
            $activityLabel = 'Ayer ' . date('H:i', strtotime($lastActivity . ' UTC'));
          } else {
            $activityLabel = date('d/m/Y', strtotime($lastActivity . ' UTC'));
          }
          $activityClass = 'offline';
        } else {
          $activityLabel = 'Nunca';
          $activityClass = 'offline';
        }
      ?>
      <tr class="ut-tr <?php echo $st === 'inactive' ? 'ut-tr-inactive' : ''; ?>" data-user-id="<?php echo (int)$u->id; ?>">
        <td class="ut-td-user">
          <div class="ut-user-info">
            <span class="ut-username">
              <?php echo htmlspecialchars(trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->username); ?>
            </span>
            <?php if (!empty($u->first_name)): ?>
            <span class="ut-name"><?php echo htmlspecialchars($u->username); ?></span>
            <?php endif; ?>
            <span class="ut-email"><?php echo htmlspecialchars($u->email); ?></span>
          </div>
        </td>
        <td class="ut-td-role"><?php echo htmlspecialchars($roleLabel); ?></td>
        <td class="ut-td-status">
          <div class="ut-status-cell">
<?php $isAdminTarget = !empty($u->is_admin); ?>
<?php $isSelf = (int)$u->id === (int)$_SESSION['user_id']; ?>
<?php $hasToggleRestriction = $isSelf || ($isAdminTarget && empty($_SESSION['is_owner'])); ?>
            <label class="ut-toggle<?php echo $hasToggleRestriction ? ' ut-toggle--disabled' : ''; ?>"<?php echo $hasToggleRestriction ? ' data-tooltip="' . ($isSelf ? 'No puedes deshabilitar tu propia cuenta' : 'Los administradores no pueden ser desactivados') . '"' : ' title="' . ($st === 'active' ? 'Deshabilitar' : 'Habilitar') . '"'; ?>>
              <input type="checkbox" class="ut-toggle-input" <?php echo $st === 'active' ? 'checked' : ''; ?> <?php echo $hasToggleRestriction ? 'disabled' : ''; ?>>
              <span class="ut-toggle-slider"></span>
            </label>
            <span class="ut-status-text <?php echo $st; ?><?php echo $hasToggleRestriction ? ' ut-status-text--disabled' : ''; ?>"><?php echo $st === 'active' ? 'Activo' : 'Inactivo'; ?></span>
          </div>
        </td>
        <td class="ut-td-2fa hide-mobile">
          <div class="ut-2fa-cell">
            <span class="ut-2fa-dot <?php echo $u->twofa_enabled ? 'on' : 'off'; ?>"></span>
            <?php echo $u->twofa_enabled ? 'Activo' : 'Inactivo'; ?>
          </div>
        </td>
        <td class="ut-td-activity hide-mobile">
          <div class="ut-activity-cell">
            <span class="ut-activity-dot <?php echo $activityClass; ?>"></span>
            <?php echo $activityLabel; ?>
          </div>
        </td>
        <td class="ut-td-date hide-mobile"><?php echo date('d/m/Y', strtotime($u->created_at . ' UTC')); ?></td>
        <td class="ut-td-actions">
          <?php
          $isSelf = (int)$u->id === (int)$_SESSION['user_id'];
          $isAdminTarget = !empty($u->is_admin);
          $canEdit = !$isSelf && !($isAdminTarget && empty($_SESSION['is_owner']));
          if ($canEdit):
          ?>
          <a href="<?php echo URLROOT; ?>/users/edit/<?php echo (int)$u->id; ?>" class="ut-edit-btn" title="Editar" aria-label="Editar usuario"><?php echo icon('pencil', ['size' => 14]); ?></a>
          <?php else:
          $tooltip = $isSelf ? 'No puedes editar tu propia cuenta' : 'No puedes modificar a otros administradores';
          ?>
          <span class="ut-edit-btn is-disabled" data-tooltip="<?php echo $tooltip; ?>"><?php echo icon('pencil', ['size' => 14]); ?></span>
          <?php endif; ?>
          <button type="button" class="ut-reset-btn" title="Enviar enlace de restablecimiento" aria-label="Restablecer contraseña" data-user-id="<?php echo (int)$u->id; ?>" data-username="<?php echo htmlspecialchars($u->username); ?>"><?php echo icon('refresh-cw', ['size' => 14]); ?></button>
          <?php
          $isDeleteSelf = (int)$u->id === (int)$_SESSION['user_id'];
          $isDeleteOwner = !empty($_SESSION['is_owner']);
          $canDelete = !$isDeleteSelf && ($isDeleteOwner || empty($u->is_admin));
          $deleteMsg = '';
          if ($isDeleteSelf) $deleteMsg = 'No puedes eliminarte a ti mismo';
          elseif (!empty($u->is_admin) && !$isDeleteOwner) $deleteMsg = 'No puedes eliminar a otros administradores';
          ?>
          <button type="button" class="ut-edit-btn ut-delete-btn<?php echo $canDelete ? '' : ' is-disabled'; ?>" data-can-delete="<?php echo $canDelete ? '1' : '0'; ?>" data-delete-msg="<?php echo htmlspecialchars($deleteMsg); ?>" data-user-id="<?php echo (int)$u->id; ?>" data-username="<?php echo htmlspecialchars($u->username); ?>" data-tooltip="<?php echo htmlspecialchars($deleteMsg ?: 'Eliminar usuario'); ?>" aria-label="Eliminar usuario"><?php echo icon('trash-2', ['size' => 14]); ?></button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="users-empty" id="userEmpty">
  <?php echo icon('users', ['size' => 48]); ?>
  <p>No hay usuarios que coincidan con tu búsqueda.</p>
  <?php if (!empty($data['search'])): ?>
    <a href="<?php echo URLROOT; ?>/users" class="ut-btn ut-btn-secondary">Ver todos</a>
  <?php endif; ?>
</div>
<?php endif; ?>
</div>

<div id="userLoading" style="display:none;" class="text-center py-12">
  <span class="text-xs text-muted">Cargando usuarios…</span>
</div>

<style>
.ut-reset-btn {
  background: transparent;
  color: var(--text-secondary);
  border: none;
  cursor: pointer;
  padding: 0.35rem 0.45rem;
  border-radius: 6px;
  transition: all 0.12s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.ut-reset-btn:hover {
  background: rgba(var(--accent-rgb, 59,130,246), 0.1);
  color: var(--accent, #3b82f6);
}
@media (max-width: 640px) {
  .ut-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .hide-mobile { display: none; }
  .ut-table th, .ut-table td { padding: 0.4rem 0.45rem; }
  .ut-table { font-size: 0.78rem; }
}
</style>

<script>
(function() {
  var urlParams = new URLSearchParams(window.location.search);
  var currentQuery = urlParams.get('q') || '';
  var currentRole = urlParams.get('role') || '';
  var currentStatus = urlParams.get('status') || '';
  var csrfToken = <?php echo json_encode($data['csrf_token'] ?? ''); ?>;
  var urlroot = <?php echo json_encode(URLROOT); ?>;
  var currentUserId = <?php echo (int)($_SESSION['user_id'] ?? 0); ?>;
  var searchTimer;

  // ── Helpers ──
  function escapeHtml(text) { var div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
  var roleLabels = {'owner':'Dueño','admin':'Administrador','logistics':'Logística','sales':'Ventas','products':'Productos','user':'Usuario'};

  function buildRow(u, isOwner, currentUserId) {
    var st = u.status;
    var trClass = st === 'inactive' ? 'ut-tr ut-tr-inactive' : 'ut-tr';
    var checked = st === 'active' ? ' checked' : '';
    var isSelf = u.id === currentUserId;
    var isAdminTarget = u.is_admin;
    var hasRestriction = isSelf || (isAdminTarget && !isOwner);
    var disabled = hasRestriction ? ' disabled' : '';
    var statusActive = st === 'active' ? ' active' : ' inactive';
    var statusText = st === 'active' ? 'Activo' : 'Inactivo';
    var twofaDot = u.twofa_enabled ? 'on' : 'off';
    var twofaText = u.twofa_enabled ? 'Activo' : 'Inactivo';
    var editTooltip = '';
    var canEdit = true;
    if (isSelf) { canEdit = false; editTooltip = 'No puedes editar tu propia cuenta'; }
    else if (isAdminTarget && !isOwner) { canEdit = false; editTooltip = 'No puedes modificar a otros administradores'; }

    var canDelete = !isSelf && (isOwner || !u.is_admin);
    var deleteMsg = '';
    if (isSelf) deleteMsg = 'No puedes eliminarte a ti mismo';
    else if (u.is_admin && !isOwner) deleteMsg = 'No puedes eliminar a otros administradores';
    var tooltip = '';
    if (hasRestriction) {
        tooltip = isSelf ? 'No puedes deshabilitar tu propia cuenta' : 'Los administradores no pueden ser desactivados';
    }

    return '<tr class="'+trClass+'" data-user-id="'+u.id+'">'+
      '<td class="ut-td-user"><div class="ut-user-info"><span class="ut-username">'+escapeHtml(u.full_name || u.username)+'</span>'+(u.first_name ? '<span class="ut-name">'+escapeHtml(u.username)+'</span>' : '')+'<span class="ut-email">'+escapeHtml(u.email)+'</span></div></td>'+
      '<td class="ut-td-role">'+escapeHtml(u.role_label)+'</td>'+
      '<td class="ut-td-status"><div class="ut-status-cell"><label class="ut-toggle'+(hasRestriction ? ' ut-toggle--disabled':'')+'"'+(hasRestriction ? ' data-tooltip="'+tooltip+'"':'')+'><input type="checkbox" class="ut-toggle-input"'+checked+disabled+'><span class="ut-toggle-slider"></span></label><span class="ut-status-text'+statusActive+(hasRestriction?' ut-status-text--disabled':'')+'">'+statusText+'</span></div></td>'+
      '<td class="ut-td-2fa hide-mobile"><div class="ut-2fa-cell"><span class="ut-2fa-dot '+twofaDot+'"></span>'+twofaText+'</div></td>'+
      '<td class="ut-td-activity hide-mobile"><div class="ut-activity-cell"><span class="ut-activity-dot '+u.activity_class+'"></span>'+escapeHtml(u.activity_label)+'</div></td>'+
      '<td class="ut-td-date hide-mobile">'+escapeHtml(u.created_at)+'</td>'+
      '<td class="ut-td-actions">'+
        (canEdit
          ? '<a href="'+urlroot+'/users/edit/'+u.id+'" class="ut-edit-btn" title="Editar" aria-label="Editar usuario"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="'+urlroot+'/assets/icons/sprite.svg#icon-pencil"/></svg></a>'
          : '<span class="ut-edit-btn is-disabled" data-tooltip="'+editTooltip+'"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="'+urlroot+'/assets/icons/sprite.svg#icon-pencil"/></svg></span>'
        )+
      '<button type="button" class="ut-reset-btn" title="Enviar enlace de restablecimiento" aria-label="Restablecer contraseña" data-user-id="'+u.id+'" data-username="'+escapeHtml(u.username)+'"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="'+urlroot+'/assets/icons/sprite.svg#icon-refresh-cw"/></svg></button>'+
      (canDelete
        ? '<button type="button" class="ut-edit-btn ut-delete-btn" title="Eliminar usuario" aria-label="Eliminar usuario" data-user-id="'+u.id+'" data-username="'+escapeHtml(u.username)+'"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="'+urlroot+'/assets/icons/sprite.svg#icon-trash-2"/></svg></button>'
        : '<button type="button" class="ut-edit-btn ut-delete-btn is-disabled" data-can-delete="0" data-delete-msg="'+deleteMsg+'" data-tooltip="'+deleteMsg+'"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="'+urlroot+'/assets/icons/sprite.svg#icon-trash-2"/></svg></button>'
      )+
      '</td>'+
    '</tr>';
  }

  // ── AJAX search ──
  function fetchUsers() {
    var tableWrap = document.getElementById('userTableWrap');
    var loading = document.getElementById('userLoading');
    var clearBtn = document.getElementById('userClearBtn');

    tableWrap.style.display = 'none';
    loading.style.display = 'block';

    var params = new URLSearchParams();
    if (currentQuery) params.set('q', currentQuery);
    if (currentRole) params.set('role', currentRole);
    if (currentStatus) params.set('status', currentStatus);

    fetch(urlroot + '/users/searchData?' + params.toString(), { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        loading.style.display = 'none';
        tableWrap.style.display = '';

        var html;
        if (data.users.length === 0) {
          html = '<div class="users-empty"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><use href="'+urlroot+'/assets/icons/sprite.svg#icon-users"/></svg><p>No hay usuarios que coincidan con tu busqueda.</p>'+
            (currentQuery || currentRole ? '<a href="'+urlroot+'/users" class="ut-btn ut-btn-secondary">Ver todos</a>' : '') +
            '</div>';
        } else {
          var rows = '';
          for (var i = 0; i < data.users.length; i++) {
            rows += buildRow(data.users[i], data.is_owner, currentUserId);
          }
          html = '<div class="ut-table-wrap"><table class="ut-table"><thead><tr>'+
            '<th class="ut-th-user">Usuario</th><th class="ut-th-role">Rol</th><th class="ut-th-status">Estado</th>'+
            '<th class="ut-th-2fa hide-mobile">2FA</th><th class="ut-th-activity hide-mobile">Actividad</th><th class="ut-th-date hide-mobile">Registro</th>'+
            '<th class="ut-th-actions"><span class="sr-only">Acciones</span></th></tr></thead>'+
            '<tbody id="userTableBody">'+rows+'</tbody></table></div>';
        }
        tableWrap.innerHTML = html;

        if (data.users.length > 0) {
          bindToggles();
          bindResetBtns();
          bindDeleteBtns();
        }

        clearBtn.style.display = (currentQuery || currentRole) ? 'inline-flex' : 'none';
      })
      .catch(function() {
        loading.style.display = 'none';
        tableWrap.style.display = '';
      });
  }

  // ── Send reset link ──
  function sendResetLink(userId, userName) {
    openConfirm({
      title: 'Restablecer contraseña?',
      message: 'Se enviará un enlace a <strong>' + escapeHtml(userName) + '</strong> para que restablezca su contraseña.',
      confirmText: 'Enviar',
      confirmClass: 'ut-btn-primary',
      onConfirm: function() {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', urlroot + '/users/sendResetLink/' + userId, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onload = function() {
          if (xhr.status === 200) {
            showToast('Enlace enviado a ' + userName, 'success', 4000);
          } else {
            try { var err = JSON.parse(xhr.responseText); showToast(err.error || 'Error al enviar', 'error', 5000); } catch(e) { showToast('Error al enviar el enlace', 'error', 5000); }
          }
        };
        xhr.onerror = function() { showToast('Error de conexi&oacute;n', 'error', 5000); };
        xhr.send(JSON.stringify({ csrf_token: csrfToken }));
      }
    });
  }

  function bindResetBtns() {
    document.querySelectorAll('.ut-reset-btn').forEach(function(btn) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', function() {
        sendResetLink(this.getAttribute('data-user-id'), this.getAttribute('data-username'));
      });
    });
  }

  // ── Delete user ──
  function bindDeleteBtns() {
    document.querySelectorAll('.ut-delete-btn').forEach(function(btn) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', function() {
        if (this.dataset.canDelete === '0') {
          showToast(this.dataset.deleteMsg || 'No tienes permiso', 'warning', 4000);
          return;
        }
        var userId = this.getAttribute('data-user-id');
        var userName = this.getAttribute('data-username');
        openConfirm({
          title: 'Eliminar usuario',
          message: '¿Eliminar permanentemente a <strong>' + escapeHtml(userName) + '</strong>? Esta acción no se puede deshacer.',
          confirmText: 'Eliminar',
          confirmClass: 'btn-danger',
          requirePhrase: userName,
          onConfirm: function() {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', urlroot + '/users/delete/' + userId, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
              if (xhr.status === 200) {
                showToast('Usuario eliminado.', 'success', 4000);
                fetchUsers();
              } else {
                try { var err = JSON.parse(xhr.responseText); showToast(err.error || 'Error al eliminar', 'error', 5000); } catch(e) { showToast('Error al eliminar', 'error', 5000); }
              }
            };
            xhr.onerror = function() { showToast('Error de conexi\u00f3n', 'error', 5000); };
            xhr.send('csrf_token=' + encodeURIComponent(csrfToken));
          }
        });
      });
    });
  }

  // ── Toggle status ──
  function sendToggle(input, row, text) {
    var userId = row.getAttribute('data-user-id');
    var userName = row.querySelector('.ut-username').textContent;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', urlroot + '/users/status', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onload = function() {
      if (xhr.status === 200) {
        var data = JSON.parse(xhr.responseText);
        showToast('Usuario ' + userName + ' ' + (data.status === 'active' ? 'activado' : 'desactivado') + ' correctamente', 'success', 4000);
        if (data.status === 'active') {
          row.classList.remove('ut-tr-inactive');
          text.textContent = 'Activo';
          text.className = 'ut-status-text active';
          input.checked = true;
        } else {
          row.classList.add('ut-tr-inactive');
          text.textContent = 'Inactivo';
          text.className = 'ut-status-text inactive';
          input.checked = false;
        }
      } else {
        input.checked = !input.checked;
        try { var err = JSON.parse(xhr.responseText); showToast(err.error || 'Error al cambiar estado', 'error', 5000); } catch(e) { showToast('Error al cambiar estado', 'error', 5000); }
      }
    };
    xhr.onerror = function() { input.checked = !input.checked; showToast('Error de conexi&oacute;n', 'error', 5000); };
    xhr.send(JSON.stringify({ id: parseInt(userId, 10), csrf_token: csrfToken }));
  }

  function bindToggles() {
    document.querySelectorAll('#userTableBody .ut-toggle-input').forEach(function(input) {
      if (input.dataset.bound) return;
      input.dataset.bound = '1';
      input.addEventListener('change', function() {
        var row = this.closest('.ut-tr');
        var text = row.querySelector('.ut-status-text');
        var userName = row.querySelector('.ut-username').textContent;

        if (!this.checked) {
          this.checked = true;
          openConfirm({
            title: 'Desactivar usuario?',
            message: 'El usuario <strong>' + escapeHtml(userName) + '</strong> no podra iniciar sesion hasta que vuelva a ser activado.',
            confirmText: 'Desactivar',
            confirmClass: 'btn-warn',
            onConfirm: function() { input.checked = false; sendToggle(input, row, text); }
          });
        } else {
          this.checked = false;
          openConfirm({
            title: 'Activar usuario?',
            message: 'El usuario <strong>' + escapeHtml(userName) + '</strong> podra iniciar sesion nuevamente.',
            confirmText: 'Activar',
            confirmClass: 'btn-confirm--accent',
            onConfirm: function() { input.checked = true; sendToggle(input, row, text); }
          });
        }
      });
    });
  }

  // ── Event wiring ──
  document.getElementById('userSearch').addEventListener('keydown', function(e) {
    clearTimeout(searchTimer);
    if (e.key === 'Enter') {
      e.preventDefault();
      currentQuery = this.value.trim();
      fetchUsers();
    } else {
      searchTimer = setTimeout(function() {
        currentQuery = document.getElementById('userSearch').value.trim();
        fetchUsers();
      }, 350);
    }
  });

  document.getElementById('userRole').addEventListener('change', function() {
    currentRole = this.value;
    fetchUsers();
  });

  // Status pills
  document.querySelectorAll('.status-pill').forEach(function(pill) {
    pill.addEventListener('click', function(e) {
      e.preventDefault();
      var status = this.getAttribute('data-status');
      if (status === currentStatus) return;
      currentStatus = status;
      // Reset UI
      document.querySelectorAll('.status-pill').forEach(function(p) {
        p.classList.remove('bg-accent/10', 'text-accent', 'border-accent/30', 'bg-success/10', 'text-success', 'border-success/30', 'bg-danger/10', 'text-danger', 'border-danger/30');
        p.classList.add('text-muted', 'border-border');
      });
      // Highlight clicked
      this.classList.remove('text-muted', 'border-border');
      if (status === '') this.classList.add('bg-accent/10', 'text-accent', 'border-accent/30');
      else if (status === 'active') this.classList.add('bg-success/10', 'text-success', 'border-success/30');
      else if (status === 'inactive') this.classList.add('bg-danger/10', 'text-danger', 'border-danger/30');
      fetchUsers();
      // Update URL
      var url = new URL(window.location);
      if (status) url.searchParams.set('status', status);
      else url.searchParams.delete('status');
      window.history.replaceState({}, '', url);
    });
  });

  document.getElementById('userClearBtn').addEventListener('click', function() {
    document.getElementById('userSearch').value = '';
    document.getElementById('userRole').value = '';
    currentQuery = '';
    currentRole = '';
    currentStatus = '';
    document.querySelectorAll('.status-pill').forEach(function(p) {
      p.classList.remove('bg-accent/10', 'text-accent', 'border-accent/30', 'bg-success/10', 'text-success', 'border-success/30', 'bg-danger/10', 'text-danger', 'border-danger/30');
      p.classList.add('text-muted', 'border-border');
    });
    document.querySelector('.status-pill[data-status=""]')?.classList.add('bg-accent/10', 'text-accent', 'border-accent/30');
    fetchUsers();
  });

  bindToggles();
  bindResetBtns();
  bindDeleteBtns();
})();
</script>
