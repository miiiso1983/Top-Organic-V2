<?php use App\Core\Auth; ?>
<style>
.res-module-header { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.res-module-header form { display: flex; gap: 8px; align-items: center; }
.res-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 24px; }
.res-stat { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; text-align: center; }
.res-stat .value { font-size: 22px; font-weight: 700; color: #111827; }
.res-stat .label { font-size: 12px; color: #6b7280; margin-top: 4px; }
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
  <h2 style="margin:0"><i class="fa-solid fa-cart-shopping"></i> وحدة المشتريات</h2>
</div>

<div class="res-grid">
  <?php if (Auth::can('material_request.view')): ?>
  <a href="/material-requests" class="res-card">
    <div class="icon"><i class="fa-solid fa-box-open"></i></div>
    <div class="title">طلبات المواد</div>
    <div class="desc">طلب احتياجات المخزون</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('purchasing.view')): ?>
  <a href="/purchasing/requests" class="res-card">
    <div class="icon"><i class="fa-solid fa-file-signature"></i></div>
    <div class="title">طلبات الشراء</div>
    <div class="desc">إدارة طلبات الشراء الداخلية</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('purchasing.manage')): ?>
  <a href="/purchasing/purchase-request-items" class="res-card">
    <div class="icon"><i class="fa-solid fa-list-check"></i></div>
    <div class="title">أصناف طلبات الشراء</div>
    <div class="desc">إدارة الأصناف التي تظهر في تطبيق الموبايل</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('purchasing.view')): ?>
  <a href="/purchasing/rfqs" class="res-card">
    <div class="icon"><i class="fa-solid fa-envelope"></i></div>
    <div class="title">طلبات عروض الأسعار</div>
    <div class="desc">طلب عروض من الموردين</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('purchasing.view')): ?>
  <a href="/purchasing/orders" class="res-card">
    <div class="icon"><i class="fa-solid fa-cart-shopping"></i></div>
    <div class="title">أوامر الشراء</div>
    <div class="desc">إصدار أوامر شراء رسمية</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('purchasing.view')): ?>
  <a href="/purchasing/receipts" class="res-card">
    <div class="icon"><i class="fa-solid fa-dolly"></i></div>
    <div class="title">الاستلام والفحص</div>
    <div class="desc">استلام البضاعة وفحصها</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('purchasing.view')): ?>
  <a href="/purchasing/invoices" class="res-card">
    <div class="icon"><i class="fa-solid fa-file-invoice"></i></div>
    <div class="title">فواتير الشراء</div>
    <div class="desc">فواتير الموردين</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('suppliers.manage')): ?>
  <a href="/suppliers" class="res-card">
    <div class="icon"><i class="fa-solid fa-handshake"></i></div>
    <div class="title">الموردون</div>
    <div class="desc">إدارة بيانات الموردين</div>
  </a>
  <?php endif; ?>
</div>
