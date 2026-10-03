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
  <h2 style="margin:0"><i class="fa-solid fa-cash-register"></i> وحدة المبيعات</h2>
</div>

<div class="res-grid">
  <?php if (Auth::can('pos.use')): ?>
  <a href="/pos" class="res-card">
    <div class="icon"><i class="fa-solid fa-desktop"></i></div>
    <div class="title">نقطة البيع POS</div>
    <div class="desc">شاشة البيع السريع</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('sales.view')): ?>
  <a href="/captain" class="res-card">
    <div class="icon"><i class="fa-solid fa-wine-glass"></i></div>
    <div class="title">شاشة الكابتن</div>
    <div class="desc">إدارة طلبات الطاولات</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('sales.view')): ?>
  <a href="/orders" class="res-card">
    <div class="icon"><i class="fa-solid fa-receipt"></i></div>
    <div class="title">الطلبات</div>
    <div class="desc">عرض وإدارة الطلبات</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('sales.view')): ?>
  <a href="/tables" class="res-card">
    <div class="icon"><i class="fa-solid fa-chair"></i></div>
    <div class="title">الطاولات</div>
    <div class="desc">إدارة الطاولات والأرضيات</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('sales.view')): ?>
  <a href="/menu" class="res-card">
    <div class="icon"><i class="fa-solid fa-book-open"></i></div>
    <div class="title">قائمة الطعام</div>
    <div class="desc">إدارة أصناف المنيو</div>
  </a>
  <?php endif; ?>
</div>
