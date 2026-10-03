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
  <h2 style="margin:0"><i class="fa-solid fa-boxes-stacked"></i> وحدة المخزون</h2>
</div>

<div class="res-grid">
  <?php if (Auth::can('inventory.view')): ?>
  <a href="/inventory" class="res-card">
    <div class="icon"><i class="fa-solid fa-box"></i></div>
    <div class="title">الأصناف والأرصدة</div>
    <div class="desc">عرض الأصناف والكميات المتاحة</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('inventory.view')): ?>
  <a href="/inventory/movements" class="res-card">
    <div class="icon"><i class="fa-solid fa-arrow-right-arrow-left"></i></div>
    <div class="title">الحركات</div>
    <div class="desc">حركات الإدخال والإخراج</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('inventory.transfer')): ?>
  <a href="/inventory/transfers" class="res-card">
    <div class="icon"><i class="fa-solid fa-truck"></i></div>
    <div class="title">المناقلات</div>
    <div class="desc">نقل المواد بين المخازن</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('inventory.stocktake')): ?>
  <a href="/inventory/stocktakes" class="res-card">
    <div class="icon"><i class="fa-solid fa-clipboard-check"></i></div>
    <div class="title">الجرد</div>
    <div class="desc">عمليات الجرد والتسوية</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('inventory.view')): ?>
  <a href="/warehouses" class="res-card">
    <div class="icon"><i class="fa-solid fa-warehouse"></i></div>
    <div class="title">المخازن والأقسام</div>
    <div class="desc">إدارة مواقع التخزين</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('inventory.view')): ?>
  <a href="/warehouses/items" class="res-card">
    <div class="icon"><i class="fa-solid fa-list"></i></div>
    <div class="title">الأصناف حسب المخزن</div>
    <div class="desc">توزيع الأصناف على المخازن</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('inventory.view')): ?>
  <a href="/inventory/item-cards" class="res-card">
    <div class="icon"><i class="fa-solid fa-id-card"></i></div>
    <div class="title">بطاقات تعريف الأصناف</div>
    <div class="desc">بيانات تفصيلية لكل صنف</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('inventory.view')): ?>
  <a href="/inventory/cost-sheets" class="res-card">
    <div class="icon"><i class="fa-solid fa-coins"></i></div>
    <div class="title">قوالب تكلفة الأصناف</div>
    <div class="desc">حساب تكلفة المواد</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('inventory.manage')): ?>
  <a href="/inventory/cost-sheets/import" class="res-card">
    <div class="icon"><i class="fa-solid fa-file-import"></i></div>
    <div class="title">استيراد قوالب التكلفة</div>
    <div class="desc">استيراد من ملف</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('inventory.view')): ?>
  <a href="/inventory/prepared-items" class="res-card">
    <div class="icon"><i class="fa-solid fa-bowl-food"></i></div>
    <div class="title">الأصناف المحضرة</div>
    <div class="desc">أصناف تحتاج تحضير</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('inventory.view')): ?>
  <a href="/inventory/prices" class="res-card">
    <div class="icon"><i class="fa-solid fa-tags"></i></div>
    <div class="title">تحديث أسعار المواد</div>
    <div class="desc">تعديل أسعار التكلفة</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('inventory.view')): ?>
  <a href="/inventory/plate-costs" class="res-card">
    <div class="icon"><i class="fa-solid fa-utensils"></i></div>
    <div class="title">تكاليف الأطباق</div>
    <div class="desc">حساب تكلفة الأطباق</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('inventory.view')): ?>
  <a href="/inventory/plate-cost-imports" class="res-card">
    <div class="icon"><i class="fa-solid fa-file-import"></i></div>
    <div class="title">استيراد تكلفة الأطباق</div>
    <div class="desc">استيراد تكلفة الأطباق</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('custody.manage')): ?>
  <a href="/custody" class="res-card">
    <div class="icon"><i class="fa-solid fa-lock"></i></div>
    <div class="title">العهدة</div>
    <div class="desc">تتبع العهدة والأصول</div>
  </a>
  <?php endif; ?>
</div>
