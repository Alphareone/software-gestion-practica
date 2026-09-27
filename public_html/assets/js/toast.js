/* ═══════════════════════════════════════════════
   TOAST NOTIFICATIONS — Drive/ML style
   Usage:
     showToast(message, type='success', durationMs=5000)
     showToast({title, message, type, duration, actions})
     showDownloadToast(filename, durationMs=8000)
   ═══════════════════════════════════════════════ */
(function(global){
    'use strict';

    var ICONS = {
        success: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        error: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        warning: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        download: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>'
    };

    function ensureContainer(){
        var id = 'app-toast-container';
        var container = document.getElementById(id);
        if (!container){
            container = document.createElement('div');
            container.id = id;
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    function createToast(options){
        var message, type, duration, title, actions;

        if (typeof options === 'string') {
            message = options;
            type = arguments[1] || 'info';
            duration = arguments[2];
        } else {
            message = options.message || options.text || '';
            title = options.title || '';
            type = options.type || 'info';
            duration = options.duration;
            actions = options.actions;
        }

        type = type || 'info';
        duration = (typeof duration === 'number') ? duration : 5000;

        var container = ensureContainer();
        var item = document.createElement('div');
        item.className = 'toast-item';
        item.setAttribute('data-type', type);

        // Accent bar (left)
        var accentBar = document.createElement('div');
        accentBar.className = 'toast-accent-bar';

        // Body (wraps icon + content + close)
        var body = document.createElement('div');
        body.className = 'toast-body';

        // Icon
        var iconWrap = document.createElement('div');
        iconWrap.className = 'toast-icon';
        iconWrap.innerHTML = ICONS[type] || ICONS.info;
        body.appendChild(iconWrap);

        // Content wrapper (title + message + actions)
        var content = document.createElement('div');
        content.className = 'toast-content';

        if (title) {
            var titleEl = document.createElement('div');
            titleEl.className = 'toast-title';
            titleEl.textContent = title;
            content.appendChild(titleEl);

            var msgEl = document.createElement('div');
            msgEl.className = 'toast-message';
            msgEl.textContent = message;
            content.appendChild(msgEl);
        } else {
            var msgEl = document.createElement('div');
            msgEl.className = 'toast-title';
            msgEl.textContent = message;
            content.appendChild(msgEl);
        }

        // Actions
        if (actions && actions.length) {
            var actionsWrap = document.createElement('div');
            actionsWrap.className = 'toast-actions';
            actions.forEach(function(action) {
                var btn = document.createElement(action.href ? 'a' : 'button');
                btn.className = 'toast-action';
                btn.textContent = action.text || action.label;
                if (action.href) btn.href = action.href;
                if (action.onclick) btn.addEventListener('click', action.onclick);
                actionsWrap.appendChild(btn);
            });
            content.appendChild(actionsWrap);
        }

        body.appendChild(content);

        // Close button
        var closeBtn = document.createElement('button');
        closeBtn.className = 'toast-close';
        closeBtn.setAttribute('aria-label', 'Cerrar');
        closeBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        closeBtn.addEventListener('click', function(){
            hideToast(item);
        });
        body.appendChild(closeBtn);

        // Progress bar
        var progress = null;
        if (duration > 0) {
            progress = document.createElement('div');
            progress.className = 'toast-progress';
            item.appendChild(progress);
        }

        item.appendChild(accentBar);
        item.appendChild(body);
        container.appendChild(item);

        // Animate in
        window.getComputedStyle(item).opacity;
        item.classList.add('show');

        // Progress bar via CSS animation
        if (progress && duration > 0) {
            item.style.setProperty('--toast-duration', duration + 'ms');
            progress.style.animation = 'none';
            window.getComputedStyle(progress).opacity;
            progress.style.animation = '';
        }

        // Auto dismiss
        var dismissTimeout;
        if (duration > 0) {
            dismissTimeout = setTimeout(function(){ hideToast(item); }, duration);
        }

        item._toastTimeout = dismissTimeout;

        return item;
    }

    function hideToast(item){
        if (!item) return;
        if (item._toastTimeout) clearTimeout(item._toastTimeout);
        item.classList.remove('show');

        function removeItem(){
            if (item.parentNode) item.parentNode.removeChild(item);
            item.removeEventListener('transitionend', removeItem);
        }
        var timeoutId = setTimeout(removeItem, 350);
        item.addEventListener('transitionend', function(){
            clearTimeout(timeoutId);
            removeItem();
        });
    }

    function showToast(message, type, duration){
        if (!message) return null;
        return createToast(message, type, duration);
    }

    // Download-specific toast
    function showDownloadToast(filename, duration){
        return createToast({
            message: 'Archivo descargado: ' + (filename || 'Archivo XLSX'),
            type: 'download',
            duration: duration || 6000
        });
    }

    global.showToast = showToast;
    global.showDownloadToast = showDownloadToast;
    global.hideToast = hideToast;
})(window);
