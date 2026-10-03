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
  <h2 style="margin:0"><i class="fa-solid fa-screwdriver-wrench"></i> وحدة الإدارة</h2>
</div>

<div class="res-grid">
  <?php if (Auth::canAny(['approvals.decide', 'mobile.approvals'])): ?>
  <a href="/approvals" class="res-card">
    <div class="icon"><i class="fa-solid fa-pen-to-square"></i></div>
    <div class="title">الموافقات</div>
    <div class="desc">طلبات الموافقة</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('workflow.create')): ?>
  <a href="/workflows" class="res-card">
    <div class="icon"><i class="fa-solid fa-arrows-spin"></i></div>
    <div class="title">سير العمل</div>
    <div class="desc">تصميم مسارات العمل</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('workflow.manage')): ?>
  <a href="/workflow-engine" class="res-card">
    <div class="icon"><i class="fa-solid fa-diagram-project"></i></div>
    <div class="title">محرك سير العمل</div>
    <div class="desc">مصمم Workflow الديناميكي بالسحب والإفلات</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::canAny(['ptt.use', 'ptt.manage'])): ?>
  <a href="/ptt" class="res-card">
    <div class="icon"><i class="fa-solid fa-tower-broadcast"></i></div>
    <div class="title">PTT — الاتصال الفوري</div>
    <div class="desc">قنوات صوتية ورسائل Push-to-Talk</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('ptt.manage')): ?>
  <a href="/ptt/admin" class="res-card">
    <div class="icon"><i class="fa-solid fa-gear"></i></div>
    <div class="title">إدارة قنوات PTT</div>
    <div class="desc">إنشاء وتعديل قنوات الاتصال</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('reports.view')): ?>
  <a href="/reports" class="res-card">
    <div class="icon"><i class="fa-solid fa-chart-line"></i></div>
    <div class="title">التقارير</div>
    <div class="desc">تقارير النظام</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('reports.view')): ?>
  <a href="/reports/procurement" class="res-card">
    <div class="icon"><i class="fa-solid fa-boxes-stacked"></i></div>
    <div class="title">تقارير المشتريات والمخزون</div>
    <div class="desc">تقارير المشتريات والمخزون</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('kitchen.manage')): ?>
  <a href="/admin/preparation-kitchens" class="res-card">
    <div class="icon"><i class="fa-solid fa-utensils"></i></div>
    <div class="title">مراكز التحضير</div>
    <div class="desc">إعدادات مراكز التحضير</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('admin.users')): ?>
  <a href="/admin/users" class="res-card">
    <div class="icon"><i class="fa-solid fa-users-gear"></i></div>
    <div class="title">المستخدمون والصلاحيات</div>
    <div class="desc">إدارة المستخدمين</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('admin.settings')): ?>
  <a href="/admin/printer" class="res-card">
    <div class="icon"><i class="fa-solid fa-print"></i></div>
    <div class="title">إعدادات الطابعات</div>
    <div class="desc">إعدادات الطباعة</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('admin.settings')): ?>
  <a href="/admin/app-types" class="res-card">
    <div class="icon"><i class="fa-solid fa-mobile-screen"></i></div>
    <div class="title">أنواع التطبيقات</div>
    <div class="desc">إعدادات التطبيقات</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('admin.settings')): ?>
  <a href="/admin/sync" class="res-card">
    <div class="icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
    <div class="title">المزامنة السحابية</div>
    <div class="desc">إعدادات المزامنة</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::canAny(['hr.manage', 'admin.settings'])): ?>
  <a href="/admin/branches" class="res-card">
    <div class="icon"><i class="fa-solid fa-location-dot"></i></div>
    <div class="title">الفروع</div>
    <div class="desc">إضافة، تعديل وحذف الفروع</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('admin.settings')): ?>
  <a href="/admin/push-settings" class="res-card">
    <div class="icon"><i class="fa-solid fa-bell"></i></div>
    <div class="title">إعدادات Push</div>
    <div class="desc">FCM وWeb Push ومفاتيح VAPID</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('admin.settings')): ?>
  <a href="/admin/face-recognition-settings" class="res-card">
    <div class="icon"><i class="fa-solid fa-face-smile"></i></div>
    <div class="title">التعرف على الوجه</div>
    <div class="desc">إعدادات AWS Rekognition</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('admin.settings')): ?>
  <a href="/admin/migrate" class="res-card">
    <div class="icon"><i class="fa-solid fa-database"></i></div>
    <div class="title">ترحيلات قاعدة البيانات</div>
    <div class="desc">تشغيل ومراجعة تحديثات المخطط</div>
  </a>
  <?php endif; ?>
</div>
