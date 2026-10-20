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
  <h2 style="margin:0"><i class="fa-solid fa-chart-pie"></i> وحدة المحاسبة</h2>
</div>

<div class="res-grid">
  <?php if (Auth::can('accounting.view')): ?>
  <a href="/accounting" class="res-card">
    <div class="icon"><i class="fa-solid fa-chart-pie"></i></div>
    <div class="title">لوحة المحاسبة</div>
    <div class="desc">نظرة شاملة على النظام المحاسبي</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('accounting.view')): ?>
  <a href="/accounting/accounts" class="res-card">
    <div class="icon"><i class="fa-solid fa-sitemap"></i></div>
    <div class="title">شجرة الحسابات</div>
    <div class="desc">إدارة شجرة الحسابات</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('accounting.view')): ?>
  <a href="/accounting/journal" class="res-card">
    <div class="icon"><i class="fa-solid fa-file-pen"></i></div>
    <div class="title">القيود اليومية</div>
    <div class="desc">إدخال القيود المحاسبية</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('accounting.view')): ?>
  <a href="/accounting/fixed-assets" class="res-card">
    <div class="icon"><i class="fa-solid fa-building"></i></div>
    <div class="title">الأصول الثابتة</div>
    <div class="desc">إدارة الأصول والإهلاك</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('accounting.view')): ?>
  <a href="/accounting/receivables" class="res-card">
    <div class="icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
    <div class="title">الحسابات المدينة</div>
    <div class="desc">متابعة مستحقات العملاء</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('accounting.view')): ?>
  <a href="/accounting/payables" class="res-card">
    <div class="icon"><i class="fa-solid fa-money-bill-wave"></i></div>
    <div class="title">الحسابات الدائنة</div>
    <div class="desc">متابعة مستحقات الموردين</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('accounting.view')): ?>
  <a href="/accounting/arap/aging" class="res-card">
    <div class="icon"><i class="fa-solid fa-chart-bar"></i></div>
    <div class="title">أعمار الديون</div>
    <div class="desc">تحليل أعمار الذمم</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('accounting.view')): ?>
  <a href="/accounting/arap/overdue" class="res-card">
    <div class="icon"><i class="fa-solid fa-bell"></i></div>
    <div class="title">تنبيهات المتأخرات</div>
    <div class="desc">تنبيهات الفواتير المتأخرة</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('accounting.view')): ?>
  <a href="/accounting/ledger" class="res-card">
    <div class="icon"><i class="fa-solid fa-book-open"></i></div>
    <div class="title">كشوف الحسابات</div>
    <div class="desc">كشف حساب تفصيلي</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('accounting.view')): ?>
  <a href="/accounting/reports" class="res-card">
    <div class="icon"><i class="fa-solid fa-chart-line"></i></div>
    <div class="title">التقارير المالية</div>
    <div class="desc">تقارير محاسبية متنوعة</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('accounting.view')): ?>
  <a href="/accounting/material-consumption" class="res-card">
    <div class="icon"><i class="fa-solid fa-chart-pie"></i></div>
    <div class="title">استهلاك المواد</div>
    <div class="desc">تحليل استهلاك المخزون</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('accounting.view')): ?>
  <a href="/accounting/advances" class="res-card">
    <div class="icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
    <div class="title">السلف</div>
    <div class="desc">طلبات السلف ومسار موافقاتها</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('accounting.view') || Auth::can('accounting.manage') || Auth::can('payroll.manage')): ?>
  <a href="/accounting/advances/disbursement" class="res-card">
    <div class="icon"><i class="fa-solid fa-money-bill-transfer"></i></div>
    <div class="title">تسليم السلف</div>
    <div class="desc">السلف المعتمدة الجاهزة للتسليم</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('accounting.view')): ?>
  <a href="/accounting/loadings" class="res-card">
    <div class="icon"><i class="fa-solid fa-weight-hanging"></i></div>
    <div class="title">التحميلات الشهرية</div>
    <div class="desc">تحميلات الموظفين الشهرية</div>
  </a>
  <?php endif; ?>
</div>
