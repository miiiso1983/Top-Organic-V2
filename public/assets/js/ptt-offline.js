/* PTT Offline Queue — حفظ الرسائل فاشلة الإرسال في IndexedDB وإعادة إرسالها عند عودة الاتصال */
(function () {
  'use strict';
  const DB_NAME = 'ptt_offline', STORE = 'queue';
  let db = null;

  function open() {
    if (db) return Promise.resolve(db);
    return new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, 1);
      req.onupgradeneeded = e => e.target.result.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
      req.onsuccess = e => { db = e.target.result; resolve(db); };
      req.onerror = () => reject(req.error);
    });
  }
  const tx = (mode, fn) => open().then(d => new Promise((resolve, reject) => {
    const t = d.transaction(STORE, mode);
    const res = fn(t.objectStore(STORE));
    t.oncomplete = () => resolve(res);
    t.onerror = () => reject(t.error);
  }));

  window.PttOffline = {
    supported: () => 'indexedDB' in window,

    /** حفظ رسالة فاشلة: {blob, fields:{channel_id, priority,...}, at} */
    save(item) {
      return tx('readwrite', s => s.add({ ...item, at: Date.now() }));
    },

    count() {
      return tx('readonly', s => new Promise(res => {
        const r = s.count(); r.onsuccess = () => res(r.result);
      }));
    },

    /** إعادة إرسال كل المنتظر — يُستدعى عند عودة الاتصال وعند فتح الصفحة */
    async flush(uploadFn) {
      if (!navigator.onLine) return 0;
      const items = await tx('readonly', s => new Promise(res => {
        const r = s.getAll(); r.onsuccess = () => res(r.result || []);
      }));
      let sent = 0;
      for (const item of items) {
        try {
          await uploadFn(item.blob, item.fields);
          await tx('readwrite', s => s.delete(item.id));
          sent++;
        } catch (e) { break; } // ما زال الاتصال ضعيفاً — أبقِ الباقي
      }
      return sent;
    },
  };

  // إشعار بصري بعدد المنتظر
  async function refreshBadge() {
    if (!window.PttOffline.supported()) return;
    const n = await window.PttOffline.count().catch(() => 0);
    let badge = document.getElementById('ptt-offline-badge');
    if (n > 0 && !badge) {
      badge = document.createElement('div');
      badge.id = 'ptt-offline-badge';
      badge.style.cssText = 'position:fixed;bottom:88px;left:26px;z-index:998;background:#d97706;color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;cursor:pointer';
      badge.title = 'رسائل بانتظار الإرسال — اضغط لإعادة المحاولة';
      badge.addEventListener('click', () => document.dispatchEvent(new Event('ptt-flush')));
      document.body.appendChild(badge);
    }
    if (badge) {
      badge.textContent = '📤 ' + n + ' بانتظار الإرسال';
      badge.style.display = n > 0 ? 'block' : 'none';
    }
  }
  window.PttOffline.refreshBadge = refreshBadge;
  document.addEventListener('DOMContentLoaded', refreshBadge);
})();
