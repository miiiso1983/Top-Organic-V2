<?php use App\Core\Auth; ?><!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= htmlspecialchars($title ?? 'Top Organic') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="/assets/css/app.css?v=12">
<link rel="stylesheet" href="/assets/css/app-window-manager.css?v=5">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/themes/material_green.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
// مهيأ مؤقت للاستدعاءات المبكرة من صفحات المحتوى
window._select2ReinitQueue = [];
window.reinitSelect2 = function(selector) { window._select2ReinitQueue.push(selector); };
</script>
<!-- PWA -->
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#1B5E20">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Top Organic">
<link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
<link rel="icon" type="image/png" sizes="192x192" href="/assets/icons/icon-192.png">
</head>
<?php $isEmbeddedWindow = !empty($_GET['modal']); ?>
<body<?= $isEmbeddedWindow ? ' class="embedded-window"' : '' ?>>
<div class="app">
  <?php if (!$isEmbeddedWindow): ?>
  <aside class="sidebar" id="sidebar">
    <div class="brand"><span class="brand-logo-wrap"><img src="/assets/images/logo.webp" alt="Top Organic" class="brand-logo"></span><span class="brand-text">Top Organic</span></div>
    <nav>
      <a href="/hr/team"><span class="nav-icon"><i class="fa-solid fa-users"></i></span><span class="nav-label">الموظفون وسجلي الوظيفي</span></a>
      <?php if (\App\Core\HousingAccess::canView()): ?><a href="/hr/housing"><span class="nav-icon"><i class="fa-solid fa-house"></i></span><span class="nav-label">المقيمون في السكن</span></a><?php endif; ?>
      <a href="/dashboard"><span class="nav-icon"><i class="fa-solid fa-house"></i></span> <span class="nav-label">الرئيسية</span></a>

      <?php if (Auth::can('reservations.manage') || Auth::can('reservations.types.manage') || Auth::can('call_center.manage') || Auth::can('reception.manage') || Auth::can('waiting_list.manage') || Auth::can('ai_assistant.view') || Auth::can('ai_assistant.manage')): ?>
      <a href="/reservations-module"><span class="nav-icon"><i class="fa-solid fa-utensils"></i></span> <span class="nav-label">الحجوزات والاستقبال</span></a>
      <?php endif; ?>

      <?php if (Auth::canAny(['sales.view', 'sales.create', 'pos.use'])): ?>
      <a href="/module/sales"><span class="nav-icon"><i class="fa-solid fa-cash-register"></i></span> <span class="nav-label">المبيعات</span></a>
      <?php endif; ?>
      <?php if (Auth::canAny(['kitchen.view', 'kitchen.manage'])): ?>
      <a href="/module/kitchen"><span class="nav-icon"><i class="fa-solid fa-fire-burner"></i></span> <span class="nav-label">المطبخ</span></a>
      <?php endif; ?>
      <?php if (Auth::canAny(['inventory.view', 'inventory.manage'])): ?>
      <a href="/module/inventory"><span class="nav-icon"><i class="fa-solid fa-boxes-stacked"></i></span> <span class="nav-label">المخزون</span></a>
      <?php endif; ?>
      <?php if (Auth::canAny(['purchasing.view', 'purchasing.manage', 'material_request.view'])): ?>
      <a href="/module/purchasing"><span class="nav-icon"><i class="fa-solid fa-cart-shopping"></i></span> <span class="nav-label">المشتريات</span></a>
      <?php endif; ?>
      <?php if (Auth::can('finance.view')): ?>
      <a href="/module/finance"><span class="nav-icon"><i class="fa-solid fa-sack-dollar"></i></span> <span class="nav-label">المالية</span></a>
      <?php endif; ?>
      <?php if (Auth::can('reports.view')): ?>
      <a href="/module/financial-reports"><span class="nav-icon"><i class="fa-solid fa-chart-pie"></i></span> <span class="nav-label">التقارير المالية</span></a>
      <?php endif; ?>
      <?php if (Auth::can('accounting.view')): ?>
      <a href="/module/accounting"><span class="nav-icon"><i class="fa-solid fa-chart-pie"></i></span> <span class="nav-label">المحاسبة</span></a>
      <?php endif; ?>
      <?php if (Auth::canAny(['hr.clock', 'mobile.attendance', 'attendance.self', 'tasks.self', 'mobile.tasks'])): ?>
      <a href="/module/employee"><span class="nav-icon"><i class="fa-solid fa-user"></i></span> <span class="nav-label">الموظف</span></a>
      <?php endif; ?>

      <?php if (Auth::can('hr.view')): ?>
      <a href="/module/hr"><span class="nav-icon"><i class="fa-solid fa-users-gear"></i></span> <span class="nav-label">الموارد البشرية</span></a>
      <?php endif; ?>
      <?php if (Auth::canAny(['approvals.decide', 'mobile.approvals', 'workflow.create', 'reports.view', 'admin.users', 'admin.settings', 'kitchen.manage'])): ?>
      <a href="/module/management"><span class="nav-icon"><i class="fa-solid fa-screwdriver-wrench"></i></span> <span class="nav-label">الإدارة</span></a>
      <?php endif; ?>
    </nav>
  </aside>
  <?php endif; ?>
  <div class="main">
    <?php if (!$isEmbeddedWindow): ?>
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px">
        <button id="sidebar-toggle" class="btn btn-sm btn-secondary" title="طي/فتح القائمة الجانبية"><i class="fa-solid fa-bars"></i></button>
        <a href="<?= htmlspecialchars((!empty($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] !== $_SERVER['REQUEST_URI']) ? $_SERVER['HTTP_REFERER'] : '/dashboard') ?>" class="btn btn-sm btn-secondary" title="العودة للصفحة السابقة" onclick="if (window.history.length > 1) { window.history.back(); return false; }">
          <i class="fa-solid fa-arrow-right"></i> عودة
        </a>
        <span><?= htmlspecialchars($title ?? '') ?></span>
      </div>
      <div class="user-info">
        <a href="/mail" id="mail-bell" class="btn btn-sm btn-secondary" title="البريد الداخلي" style="position:relative">
          <i class="fa-solid fa-envelope"></i>
          <?php $mailUnread = \App\Controllers\MailController::unreadCount((int)Auth::id()); ?>
          <span id="mail-badge" style="display:<?= $mailUnread ? 'inline-flex' : 'none' ?>;position:absolute;top:-6px;left:-6px;background:#dc2626;color:#fff;border-radius:12px;font-size:10px;min-width:17px;height:17px;align-items:center;justify-content:center;padding:0 4px"><?= $mailUnread > 99 ? '99+' : $mailUnread ?></span>
        </a>
        <a href="/notifications" id="ntf-bell" class="btn btn-sm btn-secondary" title="الإشعارات" style="position:relative">
          <i class="fa-solid fa-bell"></i>
          <?php $ntfUnread = \App\Core\Helpers::unreadCount((int)Auth::id()); ?>
          <span id="ntf-badge" style="display:<?= $ntfUnread ? 'inline-flex' : 'none' ?>;position:absolute;top:-6px;left:-6px;background:#dc2626;color:#fff;border-radius:12px;font-size:10px;min-width:17px;height:17px;align-items:center;justify-content:center;padding:0 4px"><?= $ntfUnread > 99 ? '99+' : $ntfUnread ?></span>
        </a>
        <button id="theme-toggle" class="btn btn-sm btn-secondary" title="تبديل الوضع الليلي/النهاري"><i class="fa-solid fa-moon"></i></button>
        <button id="install-app-btn" class="btn btn-sm" style="display:none"><i class="fa-solid fa-mobile-screen-button"></i> تثبيت التطبيق</button>
        <span><?= htmlspecialchars(Auth::name()) ?> (<?= htmlspecialchars(Auth::role()) ?>)</span>
        <a href="/logout" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد من تسجيل الخروج؟')">خروج</a>
      </div>
    </header>
    <?php endif; ?>
    <main class="content">
      <?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
        <div class="alert alert-<?= $f['type'] ?>"><?= htmlspecialchars($f['message']) ?></div>
      <?php endif; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<?php if (!$isEmbeddedWindow && Auth::can('ptt.use')): ?>
<a href="/ptt" id="ptt-fab" title="PTT — اتصال صوتي فوري"><i class="fa-solid fa-tower-broadcast"></i></a>
<style>
#ptt-fab { position:fixed; bottom:22px; left:22px; z-index:998; width:56px; height:56px; border-radius:50%;
  background:#2563eb; color:#fff; display:flex; align-items:center; justify-content:center; font-size:22px;
  box-shadow:0 4px 14px rgba(37,99,235,.45); text-decoration:none; transition:transform .15s; }
#ptt-fab:hover { transform:scale(1.08); color:#fff; }
</style>
<?php endif; ?>
<script>
// تحديث عدّاد الإشعارات دورياً كل 60 ثانية
(function(){
  const badge = document.getElementById('ntf-badge');
  if (!badge) return;
  async function tick(){
    try {
      const r = await fetch('/notifications/count');
      const j = await r.json();
      if (!j.ok) return;
      const n = j.unread || 0;
      badge.textContent = n > 99 ? '99+' : n;
      badge.style.display = n > 0 ? 'inline-flex' : 'none';
    } catch(e){}
  }
  setInterval(tick, 60000);
  document.addEventListener('ntf-refresh', tick); // تحديث فوري من تطبيق الموبايل
})();

// تحديث عدّاد البريد الداخلي غير المقروء دورياً كل 60 ثانية
(function(){
  const badge = document.getElementById('mail-badge');
  if (!badge) return;
  async function tick(){
    try {
      const r = await fetch('/mail/count');
      const j = await r.json();
      if (!j.ok) return;
      const n = j.unread || 0;
      badge.textContent = n > 99 ? '99+' : n;
      badge.style.display = n > 0 ? 'inline-flex' : 'none';
    } catch(e){}
  }
  setInterval(tick, 60000);
  document.addEventListener('ntf-refresh', tick);
})();

// اشتراك Web Push تلقائياً (مرة واحدة بعد منح الإذن)
(function(){
  const VAPID = <?= json_encode(\App\Core\Push\WebPushService::vapidPublic()) ?>;
  if (!VAPID || !('serviceWorker' in navigator) || !('PushManager' in window)) return;
  const b64 = s => Uint8Array.from(atob(s.replace(/-/g,'+').replace(/_/g,'/')), c => c.charCodeAt(0));
  async function subscribe(){
    try {
      if (Notification.permission === 'default') {
        const p = await Notification.requestPermission();
        if (p !== 'granted') return;
      }
      if (Notification.permission !== 'granted') return;
      // مهم: استخدم .ready بدل getRegistration() لتفادي تسابق التوقيت مع تسجيل
      // الـ Service Worker في app.js (الذي يُسجَّل أيضاً عند حدث load) — .ready
      // تنتظر فعلياً حتى يصبح هناك Service Worker نشط بدل إرجاع undefined فوراً.
      const reg = await navigator.serviceWorker.ready;
      let sub = await reg.pushManager.getSubscription();
      if (!sub) {
        sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64(VAPID) });
      }
      const json = sub.toJSON();
      await fetch('/push/subscribe', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ endpoint: json.endpoint, keys: json.keys })
      });
    } catch(e) { /* رفض الإذن أو متصفح غير داعم — تجاهل */ }
  }
  if (document.readyState === 'complete') subscribe();
  else window.addEventListener('load', subscribe);
})();

<?php if (!$isEmbeddedWindow): ?>
// تأكيد إغلاق/تحديث صفحة المتصفح (لا يظهر عند التنقل الطبيعي داخل النظام)
(function(){
  var allowUnload = false;
  function arm(){ allowUnload = true; setTimeout(function(){ allowUnload = false; }, 1000); }
  document.addEventListener('click', function(e){
    var a = e.target.closest('a[href]');
    if (a && a.getAttribute('href') && !a.getAttribute('href').startsWith('#') && a.target !== '_blank') arm();
  }, true);
  document.addEventListener('submit', arm, true);
  window.addEventListener('beforeunload', function(e){
    if (allowUnload) return;
    e.preventDefault();
    e.returnValue = '';
    return '';
  });
})();
<?php endif; ?>
</script>
<script src="/assets/js/native-bridge.js?v=1"></script>
<script src="/assets/js/app.js?v=4"></script>
<script src="/assets/js/app-window-manager.js?v=5"></script>
<script src="/assets/js/shortcuts.js?v=1"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/ar.js"></script>
<script src="/assets/js/reservation-picker.js?v=2"></script>
<style>
/* ===== توحيد تصميم Select2 مع النظام ===== */
.select2-container { direction: rtl; }
.select2-container--default .select2-selection--single,
.select2-container--default .select2-selection--multiple {
  border: 1px solid var(--border);
  border-radius: 8px;
  min-height: 38px;
  background: var(--surface);
  padding: 2px 8px;
}
.select2-container--default.select2-container--focus .select2-selection--single,
.select2-container--default.select2-container--focus .select2-selection--multiple {
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(46, 125, 50, .12);
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
  color: var(--text);
  line-height: 34px;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
  height: 34px;
}
.select2-container--default .select2-selection--single .select2-selection__clear {
  margin-left: 6px;
}
.select2-container--default .select2-results__option[aria-selected="true"] {
  background: rgba(46, 125, 50, .12);
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
  background: var(--brand) !important;
  color: #fff !important;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice {
  background: var(--brand);
  color: #fff;
  border: none;
  border-radius: 4px;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
  color: #fff;
  margin-left: 4px;
}
.select2-dropdown { background: var(--surface); color: var(--text); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 4px 14px rgba(0,0,0,.1); }
.select2-results__option { color: var(--text); }
.select2-container--default .select2-results__option--disabled { color: var(--text-muted); }
.select2-container--default .select2-results__group { color: var(--text-muted); }
.select2-search--dropdown { background: var(--surface); }
.select2-search--dropdown .select2-search__field { border: 1px solid var(--border); border-radius: 6px; padding: 6px 10px; background: var(--surface); color: var(--text); }
</style>
<script>
/**
 * مهيأ عام لجميع القوائم المنسدلة في النظام (Searchable Select)
 * يحوّل كل <select> إلى قائمة قابلة للبحث تلقائياً.
 *
 * خيارات:
 *   class="no-select2"    → تجاهل (يبقى select عادي)
 *   data-tags="true"       → يمكن كتابة قيم جديدة غير موجودة
 *   multiple               → متعدد الاختيار
 *   data-placeholder="..."  → نص placeholder مخصص
 */
(function(){
  function initSelect2(el) {
    if (!window.jQuery || !jQuery.fn.select2) return;
    var $el = jQuery(el);
    if ($el.hasClass('no-select2') || $el.data('no-select2') === true || $el.data('noSelect2') === true) return;
    if ($el.hasClass('select2-hidden-accessible')) return; // مهيأ مسبقاً
    var opts = {
      dir: 'rtl',
      width: $el.attr('data-width') || ($el.css('width') === 'auto' ? 'auto' : '100%'),
      allowClear: !$el.attr('required'),
      placeholder: $el.data('placeholder') || 'اختر...',
      language: {
        noResults: function() { return 'لا توجد نتائج'; },
        searching: function() { return 'جاري البحث...'; },
        loadingMore: function() { return 'جاري التحميل...'; }
      }
    };
    if ($el.attr('data-tags') === 'true') {
      opts.tags = true;
      opts.createTag = function(params) { return { id: params.term, text: params.term }; };
    }
    $el.select2(opts);

    // جسر التوافق مع السلوك الأصلي: Select2 يُطلق حدث change عبر jQuery فقط،
    // فلا تصل الأحداث إلى المستمعين المسجّلين عبر addEventListener('change').
    // نعيد بثّ الحدث كحدث أصلي (Native) مرة واحدة مع حارس ضد التكرار الحلقي.
    $el.off('change.select2NativeBridge').on('change.select2NativeBridge', function() {
      if (el.__s2BridgeGuard || el.__s2SilentRefresh) return;
      el.__s2BridgeGuard = true;
      try {
        el.dispatchEvent(new Event('change', { bubbles: true }));
      } finally {
        el.__s2BridgeGuard = false;
      }
    });
  }

  function initAllSelects() {
    if (!window.jQuery || !jQuery.fn.select2) return;
    jQuery('select').each(function() { initSelect2(this); });
  }

  // التهيئة عند تحميل الصفحة
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAllSelects);
  } else {
    initAllSelects();
  }

  // دعم المحتوى الديناميكي (AJAX / modals / إلخ)
  if (typeof MutationObserver !== 'undefined') {
    var observer = new MutationObserver(function(mutations) {
      var shouldInit = false;
      mutations.forEach(function(m) {
        if (m.type === 'childList') {
          m.addedNodes.forEach(function(node) {
            if (node.nodeType === 1) {
              if (node.tagName === 'SELECT' || node.querySelectorAll('select').length > 0) {
                shouldInit = true;
              }
            }
          });
        }
      });
      if (shouldInit) initAllSelects();
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  // دالة عامة لإعادة تهيئة عنصر معين أو الكل (تحل محل المهيأ المؤقت)
  window.reinitSelect2 = function(selector) {
    if (selector) {
      jQuery(selector).each(function() {
        var $el = jQuery(this);
        if ($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');
        initSelect2(this);
      });
    } else {
      jQuery('.select2-hidden-accessible').select2('destroy');
      initAllSelects();
    }
  };

  // تحديث عرض القائمة بعد تغيير قيمتها برمجياً (select.value = ...)
  // دون إطلاق مستمعات change (مطابق لسلوك <select> الأصلي عند التعيين البرمجي)
  window.refreshSelect2 = function(el) {
    if (!window.jQuery || !jQuery.fn.select2 || !el) return;
    var $el = jQuery(el);
    if (!$el.hasClass('select2-hidden-accessible')) return;
    el.__s2SilentRefresh = true;
    try { $el.trigger('change'); } finally { el.__s2SilentRefresh = false; }
  };

  // معالجة الاستدعاءات التي جاءت قبل تحميل هذا السكريبت
  if (Array.isArray(window._select2ReinitQueue)) {
    window._select2ReinitQueue.forEach(function(sel) { window.reinitSelect2(sel); });
    window._select2ReinitQueue = [];
  }
})();
</script>
</body>
</html>
