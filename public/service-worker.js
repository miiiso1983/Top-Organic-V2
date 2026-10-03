/**
 * Service Worker - نظام إدارة المطاعم (PWA)
 * إستراتيجية: cache-first للأصول الثابتة، network-first للصفحات مع رجوع لصفحة أوفلاين،
 * وعدم تخزين أي طلبات API أو طلبات POST مطلقاً.
 */
const CACHE_VERSION = 'rms-cache-v5';
const STATIC_CACHE = CACHE_VERSION + '-static';

const PRECACHE_URLS = [
  '/offline.html',
  '/manifest.json',
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.filter((k) => k.startsWith('rms-cache-') && k !== STATIC_CACHE)
            .map((k) => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

function isStaticAsset(url) {
  return url.pathname.startsWith('/assets/');
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // لا تتدخل إطلاقاً في طلبات API أو أي طلب غير GET
  if (req.method !== 'GET' || url.pathname.startsWith('/api/')) {
    return;
  }

  // الأصول الثابتة: cache-first
  if (isStaticAsset(url)) {
    event.respondWith(
      caches.match(req).then((cached) => cached || fetch(req).then((res) => {
        const copy = res.clone();
        caches.open(STATIC_CACHE).then((cache) => cache.put(req, copy));
        return res;
      }))
    );
    return;
  }

  // طلبات التنقل بين الصفحات: network-first مع رجوع لصفحة أوفلاين
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() => caches.match('/offline.html'))
    );
    return;
  }

  // أي شيء آخر: حاول الشبكة ثم الكاش
  event.respondWith(
    fetch(req).catch(() => caches.match(req))
  );
});

// ===== Web Push: استقبال الإشعارات والتطبيق مغلق =====
self.addEventListener('push', (event) => {
  let data = { title: 'إشعار جديد', body: '', link: '/notifications' };
  try {
    if (event.data) data = { ...data, ...event.data.json() };
  } catch (e) {}
  event.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: data.icon || '/assets/icons/icon-192.png',
      badge: '/assets/icons/icon-192.png',
      dir: 'rtl',
      lang: 'ar',
      data: { link: data.link || '/notifications' },
      requireInteraction: (data.priority === 'emergency'),
      vibrate: [200, 100, 200],
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const link = (event.notification.data && event.notification.data.link) || '/notifications';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const client of list) {
        if (client.url.includes(link) && 'focus' in client) return client.focus();
      }
      return clients.openWindow(link);
    })
  );
});
