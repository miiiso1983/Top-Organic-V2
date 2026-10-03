/**
 * Generic app window manager (iframe-based multi-window desktop-like experience).
 * Used for HR module and can be extended to other modules.
 */
(function() {
  'use strict';

  // إذا كانت هذه الصفحة مُحمّلة داخل نافذة أخرى (iframe) تابعة لمدير النوافذ،
  // لا تُنشئ شريط مهام/طبقة نوافذ خاصة بها؛ بل حوّل كل عمليات فتح/إغلاق/تصغير
  // النوافذ إلى النافذة الرئيسية العليا (window.top) حتى يبقى شريط مهام واحد
  // موحّد فقط مهما تعدّدت مستويات التنقل المتداخل.
  if (window.top !== window) {
    var topWin = window.top;
    function proxyToTop(name) {
      return function() {
        try {
          if (topWin && typeof topWin[name] === 'function') return topWin[name].apply(topWin, arguments);
        } catch (ignore) {}
      };
    }
    window.openAppWindow = proxyToTop('openAppWindow');
    window.closeAppWindow = proxyToTop('closeAppWindow');
    window.minimizeAppWindow = proxyToTop('minimizeAppWindow');
    window.maximizeAppWindow = proxyToTop('maximizeAppWindow');
    window.reloadAppWindow = proxyToTop('reloadAppWindow');
    window.getActiveAppWindow = proxyToTop('getActiveAppWindow');
    window.getAppWindowKeys = proxyToTop('getAppWindowKeys');
    window.focusAppWindow = proxyToTop('focusAppWindow');
    window.activateNextAppWindow = proxyToTop('activateNextAppWindow');

    window.openEmployeeModal = function(id) {
      window.openAppWindow('/hr/employees/' + encodeURIComponent(id), 'الملف الشخصي', 'fa-solid fa-user');
    };
    window.closeEmployeeModal = function() { window.closeAppWindow(); };
    window.minimizeEmployeeModal = function() { window.minimizeAppWindow(); };
    window.maximizeEmployeeModal = function() { window.maximizeAppWindow(); };
    window.reloadEmployeeModal = function() { window.reloadAppWindow(); };

    document.addEventListener('click', function(e) {
      var link = e.target.closest('a[data-app-window], button[data-app-window]');
      if (!link) return;
      var url = link.getAttribute('href') || link.dataset.url;
      if (!url) return;
      e.preventDefault();
      window.openAppWindow(url, link.dataset.title || link.textContent.trim(), link.dataset.icon || 'fa-solid fa-window-maximize');
    });

    return;
  }

  function init() {
  var layer = document.getElementById('app-window-layer');
  var taskbar = document.getElementById('app-window-taskbar');
  var taskbarItems = document.getElementById('app-taskbar-items');
  if (!layer) {
    layer = document.createElement('div');
    layer.id = 'app-window-layer';
    document.body.appendChild(layer);
  }
  if (!taskbar) {
    taskbar = document.createElement('div');
    taskbar.id = 'app-window-taskbar';
    taskbar.className = 'app-window-taskbar';
    taskbar.innerHTML = '<span class="app-taskbar-label"><i class="fa-solid fa-window-restore"></i> النوافذ المفتوحة</span><div id="app-taskbar-items" class="app-taskbar-items"></div>';
    document.body.appendChild(taskbar);
    taskbarItems = document.getElementById('app-taskbar-items');
  }

  var windows = Object.create(null);
  var activeKey = null;
  var zIndex = 3000;

  function makeKey(url) {
    var base = String(url).replace(/[?#].*/, '').replace(/[^a-zA-Z0-9_-]/g, '_');
    return base;
  }

  function updateBodyLock() {
    document.body.style.overflow = activeKey !== null ? 'hidden' : '';
  }

  function updateTaskbar() {
    if (!taskbarItems) return;
    taskbarItems.innerHTML = '';
    var windowCount = 0;
    Object.keys(windows).forEach(function(key) {
      var state = windows[key];
      windowCount++;
      var item = document.createElement('button');
      item.type = 'button';
      item.className = 'app-taskbar-item' + (key === activeKey ? ' active' : '');
      item.title = (state.minimized ? 'استعادة ' : 'تنشيط ') + state.title;
      item.dataset.key = key;
      item.innerHTML = '<i class="' + (state.icon || 'fa-solid fa-window-maximize') + '"></i><span></span><i class="fa-solid fa-xmark taskbar-close" title="إغلاق"></i>';
      item.querySelector('span').textContent = state.title;
      item.addEventListener('click', function(e) {
        if (e.target.closest('.taskbar-close')) {
          e.stopPropagation();
          closeAppWindow(key);
          return;
        }
        activateAppWindow(key);
      });
      taskbarItems.appendChild(item);
    });
    taskbar.classList.toggle('visible', windowCount > 0);
    document.body.classList.toggle('app-taskbar-visible', windowCount > 0);
  }

  function setTitle(state, title) {
    if (!title) return;
    state.title = title;
    state.header.textContent = '';
    if (state.icon) {
      var icon = document.createElement('i');
      icon.className = state.icon;
      state.header.appendChild(icon);
      state.header.appendChild(document.createTextNode(' '));
    }
    state.header.appendChild(document.createTextNode(title));
    updateTaskbar();
  }

  function activateAppWindow(key) {
    var state = windows[key];
    if (!state) return;
    Object.keys(windows).forEach(function(otherKey) {
      if (otherKey === key) return;
      windows[otherKey].element.classList.remove('active', 'maximized');
      windows[otherKey].minimized = true;
    });
    state.minimized = false;
    state.element.classList.add('active');
    if (state.maximized) state.element.classList.add('maximized');
    // Ensure window z-index never exceeds taskbar (999999)
    if (++zIndex >= 999998) zIndex = 3000;
    state.element.style.zIndex = zIndex;
    activeKey = key;
    updateTaskbar();
    updateBodyLock();
  }

  function minimizeAppWindow(key) {
    var state = windows[key];
    if (!state) return;
    state.minimized = true;
    state.maximized = state.element.classList.contains('maximized');
    state.element.classList.remove('active', 'maximized');
    if (activeKey === key) activeKey = null;
    updateTaskbar();
    updateBodyLock();
  }

  function closeAppWindow(key) {
    var state = windows[key];
    if (!state) return;
    state.element.remove();
    delete windows[key];
    if (activeKey === key) activeKey = null;
    updateTaskbar();
    updateBodyLock();
  }

  function createAppWindow(url, title, icon) {
    var key = makeKey(url);
    var element = document.createElement('div');
    element.className = 'app-window maximized';
    element.dataset.appWindowUrl = url;
    element.innerHTML = '<div class="app-window-backdrop"></div>' +
      '<div class="app-window-dialog" role="dialog" aria-modal="true">' +
      '<div class="app-window-header"><h4></h4><div class="app-window-controls">' +
      '<button type="button" class="btn btn-sm app-window-reload" title="تحديث"><i class="fa-solid fa-rotate"></i></button>' +
      '<button type="button" class="btn btn-sm app-window-minimize" title="تصغير"><i class="fa-solid fa-minus"></i></button>' +
      '<button type="button" class="btn btn-sm app-window-maximize" title="تكبير/تصغير"><i class="fa-solid fa-expand"></i><i class="fa-solid fa-compress"></i></button>' +
      '<button type="button" class="btn btn-sm btn-danger app-window-close" title="إغلاق"><i class="fa-solid fa-xmark"></i> إغلاق</button>' +
      '</div></div><div class="app-window-body"><iframe title="نافذة النظام"></iframe></div></div>';
    layer.appendChild(element);
    var state = {
      url: url, key: key, element: element, iframe: element.querySelector('iframe'),
      header: element.querySelector('h4'), title: title || 'نافذة', icon: icon || 'fa-solid fa-window-maximize',
      minimized: false, maximized: true
    };
    windows[key] = state;
    setTitle(state, state.title);
    state.iframe.addEventListener('load', function() {
      try {
        var doc = state.iframe.contentDocument;
        var heading = doc.querySelector('.page-head h2');
        if (heading && heading.textContent.trim()) setTitle(state, heading.textContent.trim());
        if (doc && !doc.__appShortcutForwarder) {
          doc.__appShortcutForwarder = true;
          doc.addEventListener('keydown', function(e) {
            var el = doc.activeElement;
            var typing = !!(el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT' || el.isContentEditable));
            window.dispatchEvent(new CustomEvent('appwindow-keydown', {
              bubbles: true, cancelable: true,
              detail: { key: e.key, code: e.code, ctrlKey: e.ctrlKey, altKey: e.altKey, shiftKey: e.shiftKey, metaKey: e.metaKey, typing: typing, originalEvent: e }
            }));
          });
        }
      } catch (ignore) {}
    });
    element.querySelector('.app-window-backdrop').addEventListener('click', function() { closeAppWindow(key); });
    element.querySelector('.app-window-close').addEventListener('click', function() { closeAppWindow(key); });
    element.querySelector('.app-window-minimize').addEventListener('click', function() { minimizeAppWindow(key); });
    element.querySelector('.app-window-maximize').addEventListener('click', function() {
      if (state.minimized) return;
      element.classList.toggle('maximized');
      state.maximized = element.classList.contains('maximized');
    });
    element.querySelector('.app-window-reload').addEventListener('click', function() {
      try { state.iframe.contentWindow.location.reload(); } catch (ignore) { state.iframe.src = state.iframe.src; }
    });
    return state;
  }

  window.openAppWindow = function(url, title, icon) {
    if (!url) return;
    var separator = url.indexOf('?') !== -1 ? '&' : '?';
    var fullUrl = url + separator + 'modal=1&t=' + Date.now();
    var key = makeKey(url);
    var state = windows[key];
    if (!state) {
      state = createAppWindow(url, title || 'نافذة', icon || 'fa-solid fa-window-maximize');
      state.iframe.src = fullUrl;
    } else if (!state.iframe.src || state.iframe.src === 'about:blank') {
      state.iframe.src = fullUrl;
    }
    activateAppWindow(key);
  };

  // Ensure taskbar items are always clickable by using event delegation
  if (taskbarItems) {
    taskbarItems.addEventListener('click', function(e) {
      var item = e.target.closest('.app-taskbar-item');
      if (!item) return;
      if (e.target.closest('.taskbar-close')) {
        e.stopPropagation();
        var key = item.dataset.key;
        if (key) closeAppWindow(key);
        return;
      }
      var key = item.dataset.key;
      if (key) activateAppWindow(key);
    });
  }
  window.closeAppWindow = function(key) { if (key !== undefined) closeAppWindow(key); else if (activeKey !== null) closeAppWindow(activeKey); };
  window.minimizeAppWindow = function(key) { if (key !== undefined) minimizeAppWindow(key); else if (activeKey !== null) minimizeAppWindow(activeKey); };
  window.maximizeAppWindow = function(key) {
    var target = key !== undefined ? windows[key] : (activeKey !== null ? windows[activeKey] : null);
    if (target) target.element.classList.toggle('maximized');
  };
  window.reloadAppWindow = function(key) {
    var target = key !== undefined ? windows[key] : (activeKey !== null ? windows[activeKey] : null);
    if (target) { try { target.iframe.contentWindow.location.reload(); } catch (ignore) { target.iframe.src = target.iframe.src; } }
  };
  window.getActiveAppWindow = function() { return activeKey !== null ? windows[activeKey] : null; };
  window.getAppWindowKeys = function() { return Object.keys(windows); };
  window.focusAppWindow = function(key) { activateAppWindow(key); };
  window.activateNextAppWindow = function() {
    var keys = Object.keys(windows);
    if (!keys.length) return;
    var idx = keys.indexOf(activeKey);
    var next = keys[(idx + 1) % keys.length];
    activateAppWindow(next);
  };

  // Compatibility aliases for legacy employee modal functions used inside iframes
  window.openEmployeeModal = function(id) {
    window.openAppWindow('/hr/employees/' + encodeURIComponent(id), 'الملف الشخصي', 'fa-solid fa-user');
  };
  window.closeEmployeeModal = function() { window.closeAppWindow(); };
  window.minimizeEmployeeModal = function() { window.minimizeAppWindow(); };
  window.maximizeEmployeeModal = function() { window.maximizeAppWindow(); };
  window.reloadEmployeeModal = function() { window.reloadAppWindow(); };

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && activeKey !== null) closeAppWindow(activeKey);
  });

  // Auto-convert links with data-app-window attribute
  document.addEventListener('click', function(e) {
    var link = e.target.closest('a[data-app-window], button[data-app-window]');
    if (!link) return;
    var url = link.getAttribute('href') || link.dataset.url;
    if (!url) return;
    e.preventDefault();
    window.openAppWindow(url, link.dataset.title || link.textContent.trim(), link.dataset.icon || 'fa-solid fa-window-maximize');
  });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
