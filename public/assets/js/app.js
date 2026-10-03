/**
 * سكربت عام للتطبيق: PWA، حالة الاتصال، القائمة الجانبية، الوضع الليلي
 */

// تطبيق الوضع الليلي قبل العرض لتجنب الوميض
(function() {
  const theme = localStorage.getItem('theme') || 'light';
  if (theme === 'dark') {
    document.documentElement.classList.add('dark');
  }
})();

// تسجيل Service Worker لدعم PWA والعمل دون اتصال
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/service-worker.js').catch((err) => {
      console.warn('فشل تسجيل Service Worker:', err);
    });
  });
}

// مؤشر بصري لحالة الاتصال بالشبكة
function updateConnectionBadge() {
  let badge = document.getElementById('conn-badge');
  if (!badge) {
    badge = document.createElement('div');
    badge.id = 'conn-badge';
    badge.style.cssText = 'position:fixed;bottom:12px;left:12px;padding:6px 14px;'
      + 'border-radius:20px;font-size:13px;font-family:Tahoma,Arial,sans-serif;'
      + 'z-index:9999;box-shadow:0 2px 8px rgba(0,0,0,.2);transition:opacity .3s;';
    document.body.appendChild(badge);
  }
  if (navigator.onLine) {
    badge.textContent = 'متصل';
    badge.style.background = '#16a34a';
    badge.style.color = '#fff';
    setTimeout(() => { badge.style.opacity = '0'; }, 2000);
  } else {
    badge.textContent = 'غير متصل - يعمل محلياً';
    badge.style.background = '#dc2626';
    badge.style.color = '#fff';
    badge.style.opacity = '1';
  }
}

window.addEventListener('online', updateConnectionBadge);
window.addEventListener('offline', updateConnectionBadge);
document.addEventListener('DOMContentLoaded', updateConnectionBadge);

// زر تثبيت التطبيق (Add to Home Screen)
let deferredPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  const btn = document.getElementById('install-app-btn');
  if (btn) btn.style.display = 'inline-block';
});

document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('install-app-btn');
  if (btn) {
    btn.addEventListener('click', async () => {
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      await deferredPrompt.userChoice;
      deferredPrompt = null;
      btn.style.display = 'none';
    });
  }
});

// القائمة الجانبية والوضع الليلي
document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('sidebar-toggle');
  const themeToggle = document.getElementById('theme-toggle');
  if (!sidebar) return;

  // --- الوضع الليلي ---
  if (themeToggle) {
    const icon = themeToggle.querySelector('i');
    const updateThemeIcon = () => {
      const isDark = document.documentElement.classList.contains('dark');
      if (icon) icon.className = isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
      themeToggle.title = isDark ? 'تبديل إلى الوضع النهاري' : 'تبديل إلى الوضع الليلي';
    };
    updateThemeIcon();
    themeToggle.addEventListener('click', () => {
      document.documentElement.classList.toggle('dark');
      localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
      updateThemeIcon();
    });
  }

  // --- طبقة خلفية للموبايل ---
  let backdrop = document.querySelector('.sidebar-backdrop');
  if (!backdrop) {
    backdrop = document.createElement('div');
    backdrop.className = 'sidebar-backdrop';
    document.body.appendChild(backdrop);
  }

  const isMobile = () => window.innerWidth <= 768;

  const applySidebarState = (collapsed) => {
    if (isMobile()) {
      sidebar.classList.toggle('open', collapsed);
      backdrop.classList.toggle('active', collapsed);
    } else {
      sidebar.classList.toggle('collapsed', collapsed);
    }
  };

  // الحالة الابتدائية
  if (!isMobile()) {
    applySidebarState(localStorage.getItem('sidebarCollapsed') === '1');
  }

  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      if (isMobile()) {
        const open = !sidebar.classList.contains('open');
        applySidebarState(open);
      } else {
        const collapsed = !sidebar.classList.contains('collapsed');
        applySidebarState(collapsed);
        localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
      }
    });
  }

  backdrop.addEventListener('click', () => {
    sidebar.classList.remove('open');
    backdrop.classList.remove('active');
  });

  // عند تغيير حجم النافذة نحاول منع الخلط بين الموبايل والديسكتوب
  window.addEventListener('resize', () => {
    if (!isMobile()) {
      sidebar.classList.remove('open');
      backdrop.classList.remove('active');
      applySidebarState(localStorage.getItem('sidebarCollapsed') === '1');
    } else {
      sidebar.classList.remove('collapsed');
    }
  });
});

// تفعيل الرابط الحالي في القائمة الجانبية
document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('sidebar');
  if (!sidebar) return;
  const currentPath = window.location.pathname;
  const activeLink = sidebar.querySelector('nav a[href="' + currentPath + '"]');
  if (activeLink) {
    activeLink.classList.add('active');
  }
});
