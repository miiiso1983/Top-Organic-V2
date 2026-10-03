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
  <h2 style="margin:0"><i class="fa-solid fa-chart-pie"></i> وحدة التقارير المالية</h2>
</div>

<div class="res-grid">
  <?php if (Auth::can('reports.view')): ?>
  <a href="/reports/financial/dashboard" class="res-card">
    <div class="icon"><i class="fa-solid fa-gauge-high"></i></div>
    <div class="title">لوحة المؤشرات</div>
    <div class="desc">نظرة شاملة على الأداء المالي</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('reports.view')): ?>
  <a href="/reports/financial/trial-balance" class="res-card">
    <div class="icon"><i class="fa-solid fa-scale-balanced"></i></div>
    <div class="title">ميزان المراجعة</div>
    <div class="desc">ميزان مراجعة الحسابات</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('reports.view')): ?>
  <a href="/reports/financial/income-statement" class="res-card">
    <div class="icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
    <div class="title">قائمة الدخل</div>
    <div class="desc">الإيرادات والمصروفات</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('reports.view')): ?>
  <a href="/reports/financial/balance-sheet" class="res-card">
    <div class="icon"><i class="fa-solid fa-building-columns"></i></div>
    <div class="title">الميزانية العمومية</div>
    <div class="desc">الأصول والخصوم وحقوق الملكية</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('reports.view')): ?>
  <a href="/reports/financial/cash-flow" class="res-card">
    <div class="icon"><i class="fa-solid fa-money-bill-transfer"></i></div>
    <div class="title">التدفقات النقدية</div>
    <div class="desc">حركة النقد خلال فترة</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('reports.view')): ?>
  <a href="/reports/financial/general-ledger" class="res-card">
    <div class="icon"><i class="fa-solid fa-book-open"></i></div>
    <div class="title">الأستاذ العام</div>
    <div class="desc">تفاصيل الحسابات</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('reports.view')): ?>
  <a href="/reports/financial/ar" class="res-card">
    <div class="icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
    <div class="title">الذمم المدينة</div>
    <div class="desc">مستحقات العملاء</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('reports.view')): ?>
  <a href="/reports/financial/ap" class="res-card">
    <div class="icon"><i class="fa-solid fa-money-bill-wave"></i></div>
    <div class="title">الذمم الدائنة</div>
    <div class="desc">مستحقات الموردين</div>
  </a>
  <?php endif; ?>
</div>
