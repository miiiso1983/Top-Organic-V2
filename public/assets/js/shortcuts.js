(function() {
  'use strict';
  const STORAGE_KEY = 'shortcuts_enabled';
  let enabled = localStorage.getItem(STORAGE_KEY) !== 'false';

  function showToast(message) {
    const t = document.createElement('div');
    t.textContent = message;
    t.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#1B5E20;color:#fff;padding:8px 16px;border-radius:4px;z-index:10000;font-family:inherit;white-space:nowrap;';
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2000);
  }

  function getActiveScope() {
    const win = (typeof window.getActiveAppWindow === 'function') && window.getActiveAppWindow();
    if (win && win.iframe && win.iframe.contentDocument) return win.iframe.contentDocument;
    return document;
  }

  function isTypingIn(doc) {
    const el = (doc || document).activeElement;
    if (!el) return false;
    const tag = el.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
  }
  function isTyping() { return isTypingIn(getActiveScope()); }

  function findVisible(selectors, scope) {
    scope = scope || getActiveScope();
    if (typeof selectors === 'string') selectors = [selectors];
    for (const s of selectors) {
      const el = scope.querySelector(s);
      if (el && el.offsetParent !== null) return el;
    }
    return null;
  }

  function clickFirst(selectors, scope) {
    const el = findVisible(selectors, scope);
    if (el) { el.click(); return true; }
    return false;
  }

  function submitForm(scope) {
    scope = scope || getActiveScope();
    const forms = Array.from(scope.querySelectorAll('form')).filter(f => {
      if (f.getAttribute('data-no-shortcut') !== null) return false;
      return (f.method || 'GET').toUpperCase() === 'POST';
    });
    const form = forms[0] || scope.querySelector('form');
    if (!form) { showToast('لا يوجد نموذج للحفظ'); return; }
    const btn = form.querySelector('button[type=submit], input[type=submit], [data-shortcut="save"], .btn-success, .btn-primary');
    if (btn && btn.offsetParent !== null) btn.click();
    else form.submit();
  }

  function focusSearch(scope) {
    scope = scope || getActiveScope();
    const input = findVisible(['input[type=search]', 'input[name="search"]', 'input[name="q"]', 'input[placeholder*="بحث"]', 'input[placeholder*="search"]'], scope);
    if (input) input.focus();
    else showToast('لا يوجد حقل بحث');
  }

  function openCalculator() {
    let calc = document.getElementById('shortcut-calculator');
    if (!calc) {
      calc = document.createElement('div');
      calc.id = 'shortcut-calculator';
      calc.innerHTML = `
        <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;" onclick="if(event.target===this)this.style.display='none'">
          <div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.2);direction:rtl;min-width:260px;" onclick="event.stopPropagation()">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
              <strong>الحاسبة</strong>
              <button onclick="document.getElementById('shortcut-calculator').style.display='none'" style="border:none;background:transparent;cursor:pointer;font-size:18px">&times;</button>
            </div>
            <input id="calc-display" type="text" readonly style="width:100%;padding:10px;font-size:18px;text-align:left;margin-bottom:10px;border:1px solid #ccc;border-radius:4px">
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px">
              ${['C','/','*','-','7','8','9','+','4','5','6','=','1','2','3','0'].map(k => `<button onclick="calcKey('${k}')" style="padding:12px;font-size:16px;cursor:pointer;border:1px solid #ddd;border-radius:4px;background:#f8f9fa">${k}</button>`).join('')}
            </div>
          </div>
        </div>`;
      document.body.appendChild(calc);
      window.calcKey = function(k) {
        const d = document.getElementById('calc-display');
        if (k === 'C') d.value = '';
        else if (k === '=') {
          try { d.value = Function('"use strict";return (' + d.value + ')')(); } catch { d.value = 'خطأ'; }
        }
        else d.value += k;
      };
    }
    calc.style.display = 'flex';
    document.getElementById('calc-display').focus();
  }

  function showShortcuts() {
    let modal = document.getElementById('shortcuts-modal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'shortcuts-modal';
      modal.innerHTML = `
        <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;" onclick="if(event.target===this)this.style.display='none'">
          <div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.2);direction:rtl;max-width:500px;max-height:80vh;overflow:auto;" onclick="event.stopPropagation()">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
              <strong>اختصارات لوحة المفاتيح</strong>
              <button onclick="document.getElementById('shortcuts-modal').style.display='none'" style="border:none;background:transparent;cursor:pointer;font-size:18px">&times;</button>
            </div>
            <p style="margin:0 0 10px 0;color:#666;font-size:13px">الاختصارات تعمل في النافذة النشطة (نافذة النظام أو الصفحة الحالية).</p>
            <table style="width:100%;border-collapse:collapse">
              <tr><th style="text-align:right;border-bottom:1px solid #ddd;padding:6px">الاختصار</th><th style="text-align:right;border-bottom:1px solid #ddd;padding:6px">العملية</th></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">Ctrl+S</td><td style="padding:6px;border-bottom:1px solid #eee">حفظ (في النافذة النشطة)</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">F2</td><td style="padding:6px;border-bottom:1px solid #eee">تعديل</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">Ctrl+Z</td><td style="padding:6px;border-bottom:1px solid #eee">حذف</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">F3</td><td style="padding:6px;border-bottom:1px solid #eee">إلغاء الأمر</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">Ctrl+PgDn</td><td style="padding:6px;border-bottom:1px solid #eee">الأول</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">Ctrl+PgUp</td><td style="padding:6px;border-bottom:1px solid #eee">الأخير</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">PgDn</td><td style="padding:6px;border-bottom:1px solid #eee">السابق</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">PgUp</td><td style="padding:6px;border-bottom:1px solid #eee">التالي</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">Ctrl+P</td><td style="padding:6px;border-bottom:1px solid #eee">طباعة</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">F4</td><td style="padding:6px;border-bottom:1px solid #eee">بحث</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">F9</td><td style="padding:6px;border-bottom:1px solid #eee">الحاسبة</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">F11</td><td style="padding:6px;border-bottom:1px solid #eee">وضع ملء الشاشة</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">Ctrl+ط</td><td style="padding:6px;border-bottom:1px solid #eee">تكرار المعلومات</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">Ctrl+O</td><td style="padding:6px;border-bottom:1px solid #eee">صندوق الكاشير</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">Ctrl+N</td><td style="padding:6px;border-bottom:1px solid #eee">نافذة جديدة للصفحة الحالية</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">Ctrl+W</td><td style="padding:6px;border-bottom:1px solid #eee">إغلاق النافذة النشطة</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">Alt+Tab</td><td style="padding:6px;border-bottom:1px solid #eee">التبديل بين النوافذ</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">Ctrl+D</td><td style="padding:6px;border-bottom:1px solid #eee">التنقل بين الفواتير</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">Ctrl+F2</td><td style="padding:6px;border-bottom:1px solid #eee">عرض الاختصارات</td></tr>
              <tr><td style="padding:6px;border-bottom:1px solid #eee">Alt+F2</td><td style="padding:6px;border-bottom:1px solid #eee">تفعيل/إيقاف الاختصارات</td></tr>
            </table>
          </div>
        </div>`;
      document.body.appendChild(modal);
    }
    modal.style.display = 'flex';
  }

  function toggleEnabled() {
    enabled = !enabled;
    localStorage.setItem(STORAGE_KEY, enabled);
    showToast(enabled ? 'تم تفعيل الاختصارات' : 'تم إيقاف الاختصارات');
  }

  function navigate(dir, scope) {
    scope = scope || getActiveScope();
    const map = {
      first: 'a[href*="page="]:first-of-type, a[href*="page=1"]',
      last: 'a[href*="page="]:last-of-type',
      next: 'a[href*="page="][aria-label="Next"], a[href*="page="]:nth-last-of-type(2)',
      prev: 'a[href*="page="][aria-label="Previous"], a[href*="page="]:nth-of-type(2)'
    };
    const link = findVisible(map[dir], scope);
    if (link) link.click();
    else showToast('لا يوجد تنقل متاح');
  }

  function duplicateInfo(scope) {
    if (!clickFirst(['[data-shortcut="duplicate"]', '.btn-duplicate', 'a[href*="duplicate"]'], scope)) {
      showToast('لا يوجد زر تكرار في هذه الصفحة');
    }
  }

  function cancelOrder(scope) {
    if (!clickFirst(['[data-shortcut="cancel"]', '.btn-cancel', 'a[href*="cancel"]'], scope)) {
      try { scope.defaultView.history.back(); } catch (e) {}
    }
  }

  function deleteRecord(scope) {
    if (!clickFirst(['[data-shortcut="delete"]', '.btn-danger', 'form[action*="delete"] button', 'a[href*="delete"]'], scope)) {
      showToast('لا يوجد زر حذف في هذه الصفحة');
    }
  }

  function editRecord(scope) {
    if (!clickFirst(['[data-shortcut="edit"]', '.btn-edit', 'a[href*="edit"]'], scope)) {
      showToast('لا يوجد زر تعديل في هذه الصفحة');
    }
  }

  function newWindow() {
    const url = window.location.pathname + (window.location.search ? window.location.search : '');
    if (typeof window.openAppWindow === 'function') {
      window.openAppWindow(url, document.title || 'نافذة جديدة', 'fa-solid fa-window-restore');
    } else {
      window.open(url, '_blank');
    }
  }

  function closeActiveWindow() {
    if (typeof window.closeAppWindow === 'function') window.closeAppWindow();
  }

  function cycleWindows() {
    if (typeof window.activateNextAppWindow === 'function') window.activateNextAppWindow();
  }

  function handleShortcut(data, evt, fromIframe) {
    if (!enabled) {
      if (data.altKey && data.key === 'F2') { evt.preventDefault(); toggleEnabled(); }
      return;
    }
    const key = data.key;
    const ctrl = data.ctrlKey;
    const alt = data.altKey;
    const scope = getActiveScope();
    const typing = fromIframe ? data.typing : isTypingIn(scope);

    if (ctrl && (key === 's' || key === 'S')) { evt.preventDefault(); submitForm(scope); return; }
    if (ctrl && (key === 'p' || key === 'P')) { evt.preventDefault(); window.print(); return; }
    if (key === 'F4') { evt.preventDefault(); focusSearch(scope); return; }
    if (key === 'F9') { evt.preventDefault(); openCalculator(); return; }
    if (key === 'F11') {
      evt.preventDefault();
      if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(()=>{});
      else document.exitFullscreen();
      return;
    }
    if (ctrl && key === 'F2') { evt.preventDefault(); showShortcuts(); return; }
    if (alt && key === 'F2') { evt.preventDefault(); toggleEnabled(); return; }
    if (ctrl && (key === 'ط' || key === 'ظ' || key === 't' || key === 'T')) { evt.preventDefault(); duplicateInfo(scope); return; }
    if (ctrl && (key === 'o' || key === 'O')) { evt.preventDefault(); window.location.href = '/pos'; return; }
    if (ctrl && (key === 'n' || key === 'N')) { evt.preventDefault(); newWindow(); return; }
    if (ctrl && (key === 'w' || key === 'W')) { evt.preventDefault(); closeActiveWindow(); return; }
    if (alt && key === 'Tab') { evt.preventDefault(); cycleWindows(); return; }

    if (typing) return;

    if (ctrl && (key === 'z' || key === 'Z')) { evt.preventDefault(); deleteRecord(scope); return; }
    if (key === 'F2') { evt.preventDefault(); editRecord(scope); return; }
    if (key === 'F3') { evt.preventDefault(); cancelOrder(scope); return; }
    if (ctrl && key === 'PageDown') { evt.preventDefault(); navigate('first', scope); return; }
    if (ctrl && key === 'PageUp') { evt.preventDefault(); navigate('last', scope); return; }
    if (key === 'PageDown') { evt.preventDefault(); navigate('prev', scope); return; }
    if (key === 'PageUp') { evt.preventDefault(); navigate('next', scope); return; }
    if (ctrl && (key === 'd' || key === 'D')) { evt.preventDefault(); navigate('next', scope); return; }
  }

  document.addEventListener('keydown', function(e) { handleShortcut(e, e, false); }, true);
  document.addEventListener('appwindow-keydown', function(e) {
    if (e.detail) handleShortcut(e.detail, e.detail.originalEvent || e, true);
  }, true);
})();
