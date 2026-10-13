<?php use App\Core\Auth; ?>
<style>
.res-module-header { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.res-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
.res-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 20px; text-align: center; text-decoration: none; color: #111827; transition: transform .15s, box-shadow .15s; }
.res-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
.res-card .icon { font-size: 36px; margin-bottom: 10px; }
.res-card .title { font-size: 16px; font-weight: 700; }
.res-card .desc { font-size: 12px; color: #6b7280; margin-top: 6px; }
@media (max-width: 768px) {
  .res-module-header { flex-direction: column; align-items: stretch; }
  .res-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="res-module-header">
  <h2 style="margin:0"><i class="fa-solid fa-users-gear"></i> وحدة الموارد البشرية</h2>
</div>

<div class="res-grid">
  <a href="/hr/employees" class="res-card">
    <div class="icon"><i class="fa-solid fa-users"></i></div>
    <div class="title">الموظفون</div>
    <div class="desc">سجل الموظفين</div>
  </a>

  <a href="/hr/movements" class="res-card">
    <div class="icon"><i class="fa-solid fa-right-left"></i></div>
    <div class="title">المباشرات والإغلاقات</div>
    <div class="desc">إدارة حركات المباشرة والإغلاق</div>
  </a>

  <?php if (Auth::can('hr.department.manage')): ?>
  <a href="/hr/departments" class="res-card">
    <div class="icon"><i class="fa-solid fa-building"></i></div>
    <div class="title">الأقسام</div>
    <div class="desc">تنظيم الأقسام</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('hr.job.manage')): ?>
  <a href="/hr/jobs" class="res-card">
    <div class="icon"><i class="fa-solid fa-briefcase"></i></div>
    <div class="title">الوظائف</div>
    <div class="desc">الوظائف والمهام</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('hr.manage')): ?>
  <a href="/hr/shifts" class="res-card">
    <div class="icon"><i class="fa-solid fa-clock"></i></div>
    <div class="title">الورديات</div>
    <div class="desc">إدارة الورديات</div>
  </a>
  <?php endif; ?>

  <a href="/hr/orgchart" class="res-card">
    <div class="icon"><i class="fa-solid fa-sitemap"></i></div>
    <div class="title">الهيكل التنظيمي</div>
    <div class="desc">الهيكل الإداري</div>
  </a>

  <a href="/hr/attendance" class="res-card">
    <div class="icon"><i class="fa-solid fa-clock"></i></div>
    <div class="title">الحضور والانصراف</div>
    <div class="desc">سجلات الحضور</div>
  </a>

  <?php if (Auth::can('hr.alerts.manage')): ?>
  <a href="/hr/attendance/alerts" class="res-card">
    <div class="icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <div class="title">تنبيهات الحضور</div>
    <div class="desc">تنبيهات التأخير والغياب</div>
  </a>
  <?php endif; ?>

  <a href="/hr/leaves" class="res-card">
    <div class="icon"><i class="fa-solid fa-plane"></i></div>
    <div class="title">الطلبات والإجازات</div>
    <div class="desc">إدارة الإجازات</div>
  </a>

  <a href="/accounting/advances" class="res-card">
    <div class="icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
    <div class="title">السلف</div>
    <div class="desc">طلبات السلف</div>
  </a>

  <a href="/hr/overtime" class="res-card">
    <div class="icon"><i class="fa-solid fa-stopwatch"></i></div>
    <div class="title">العمل الإضافي</div>
    <div class="desc">طلبات الـ Overtime</div>
  </a>

  <?php
  $exceptionalAdvanceTemplateId = (int)(App\Core\Database::query("SELECT id FROM approval_templates WHERE code = 'hr_exceptional_advance' AND is_active = 1 LIMIT 1")->fetchColumn() ?? 0);
  ?>
  <?php if ($exceptionalAdvanceTemplateId && (Auth::can('workflow.create') || Auth::can('mobile.approvals'))): ?>
  <a href="/workflows/create/<?= (int)$exceptionalAdvanceTemplateId ?>" class="res-card">
    <div class="icon"><i class="fa-solid fa-bolt"></i></div>
    <div class="title">سلفة استثنائية</div>
    <div class="desc">طلب سلفة استثنائية أو طويلة الأجل</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('hr.leave_balance')): ?>
  <a href="/hr/leave-balances" class="res-card">
    <div class="icon"><i class="fa-solid fa-scale-balanced"></i></div>
    <div class="title">رصيد الإجازات</div>
    <div class="desc">أرصدة الإجازات</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('hr.notification_rules')): ?>
  <a href="/hr/notification-rules" class="res-card">
    <div class="icon"><i class="fa-solid fa-bell"></i></div>
    <div class="title">قواعد التبليغات</div>
    <div class="desc">إعدادات التنبيهات</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('hr.notifications.view') || Auth::can('hr.notifications.send')): ?>
  <a href="/hr/announcements" class="res-card">
    <div class="icon"><i class="fa-solid fa-bullhorn"></i></div>
    <div class="title">التعميمات</div>
    <div class="desc">إرسال التعميمات</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('hr.notifications.history')): ?>
  <a href="/hr/notifications-history" class="res-card">
    <div class="icon"><i class="fa-solid fa-envelope"></i></div>
    <div class="title">سجل التبليغات</div>
    <div class="desc">سجل الإشعارات المرسلة</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('hr.adjustments.manage')): ?>
  <a href="/hr/adjustments" class="res-card">
    <div class="icon"><i class="fa-solid fa-money-bill-trend-up"></i></div>
    <div class="title">التسويات المالية</div>
    <div class="desc">تسويات الراتب</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('hr.payroll.manage') || Auth::can('payroll.manage')): ?>
  <a href="/hr/payroll" class="res-card">
    <div class="icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
    <div class="title">الرواتب</div>
    <div class="desc">إدارة الرواتب</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('hr.evaluation.view') || Auth::can('hr.evaluation.manage')): ?>
  <a href="/hr/evaluations" class="res-card">
    <div class="icon"><i class="fa-solid fa-chart-line"></i></div>
    <div class="title">تقييم الأداء</div>
    <div class="desc">تقييمات الموظفين</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('hr.evaluation.manage')): ?>
  <a href="/hr/kpis" class="res-card">
    <div class="icon"><i class="fa-solid fa-bullseye"></i></div>
    <div class="title">مؤشرات الأداء</div>
    <div class="desc">KPIs</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('hr.goals.manage')): ?>
  <a href="/hr/goals" class="res-card">
    <div class="icon"><i class="fa-solid fa-bullseye"></i></div>
    <div class="title">أهداف الموظفين</div>
    <div class="desc">إدارة الأهداف</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('hr.accounting.settings')): ?>
  <a href="/hr/accounting-settings" class="res-card">
    <div class="icon"><i class="fa-solid fa-gear"></i></div>
    <div class="title">محاسبة الموارد البشرية</div>
    <div class="desc">إعدادات الرواتب المحاسبية</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('hr.reports.view')): ?>
  <a href="/hr/reports" class="res-card">
    <div class="icon"><i class="fa-solid fa-chart-column"></i></div>
    <div class="title">تقارير الموارد البشرية</div>
    <div class="desc">تقارير الموظفين</div>
  </a>

  <a href="/hr/reports?type=achievement" class="res-card">
    <div class="icon"><i class="fa-solid fa-trophy"></i></div>
    <div class="title">تقرير الإنجاز</div>
    <div class="desc">الإنجاز والراتب المستحق</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('hr.manage')): ?>
  <a href="/hr/holidays" class="res-card">
    <div class="icon"><i class="fa-solid fa-umbrella-beach"></i></div>
    <div class="title">إدارة العطل الرسمية</div>
    <div class="desc">تحديد أيام العطل للتقارير</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('hr.workflow.view')): ?>
  <a href="/workflows" class="res-card">
    <div class="icon"><i class="fa-solid fa-arrows-spin"></i></div>
    <div class="title">مسارات الموافقات</div>
    <div class="desc">مسارات العمل</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('hr.housing.manage')): ?>
  <a href="/hr/housing" class="res-card">
    <div class="icon"><i class="fa-solid fa-house-chimney-user"></i></div>
    <div class="title">إدارة السكن</div>
    <div class="desc">سكن الموظفين والشقق والغرف</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('hr.custody.view')): ?>
  <a href="/hr/custody" class="res-card">
    <div class="icon"><i class="fa-solid fa-lock"></i></div>
    <div class="title">إدارة العهد</div>
    <div class="desc">تتبع العهدة</div>
  </a>

  <a href="/hr/custody/alerts" class="res-card">
    <div class="icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <div class="title">تنبيهات العهد</div>
    <div class="desc">تنبيهات العهدة</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::canAny(['tasks.self', 'mobile.tasks'])): ?>
  <a href="/hr/tasks" class="res-card">
    <div class="icon"><i class="fa-solid fa-check"></i></div>
    <div class="title">المهام</div>
    <div class="desc">إدارة المهام</div>
  </a>
  <?php endif; ?>
</div>

<script>
// فتح جميع أقسام الموارد البشرية كنوافذ ديناميكية متعددة (مع شريط مهام قابل للتصغير/الاستعادة)
// بدلاً من التنقل الكامل بين الصفحات، حتى تبقى النوافذ المفتوحة/المصغّرة متاحة أثناء التنقل بين الأقسام.
(function() {
  function tagCards() {
    if (typeof window.openAppWindow !== 'function') {
      // إذا لم يتم تحميل مدير النوافذ بعد، أعد المحاولة بعد 100 مللي ثانية
      setTimeout(tagCards, 100);
      return;
    }
    document.querySelectorAll('.res-grid .res-card[href]').forEach(function(card) {
      var titleEl = card.querySelector('.title');
      var iconEl = card.querySelector('.icon i');
      card.setAttribute('data-app-window', '1');
      if (titleEl) card.setAttribute('data-title', titleEl.textContent.trim());
      if (iconEl) card.setAttribute('data-icon', iconEl.className);
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', tagCards);
  } else {
    tagCards();
  }
})();
</script>
