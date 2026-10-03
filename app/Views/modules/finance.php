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
  <h2 style="margin:0"><i class="fa-solid fa-sack-dollar"></i> وحدة المالية</h2>
</div>

<div class="res-grid">
  <a href="/finance/cash" class="res-card">
    <div class="icon"><i class="fa-solid fa-cash-register"></i></div>
    <div class="title">الصندوق</div>
    <div class="desc">حركات الصندوق اليومية</div>
  </a>

  <a href="/finance/expenses" class="res-card">
    <div class="icon"><i class="fa-solid fa-money-bill-wave"></i></div>
    <div class="title">المصروفات</div>
    <div class="desc">تسجيل وتصنيف المصروفات</div>
  </a>

  <a href="/finance/payments" class="res-card">
    <div class="icon"><i class="fa-solid fa-calculator"></i></div>
    <div class="title">الدفعات والتحصيل</div>
    <div class="desc">دفعات العملاء والموردين</div>
  </a>

  <a href="/finance/accounts" class="res-card">
    <div class="icon"><i class="fa-solid fa-book"></i></div>
    <div class="title">الحسابات</div>
    <div class="desc">إدارة حسابات المالية</div>
  </a>

  <a href="/finance/banking" class="res-card">
    <div class="icon"><i class="fa-solid fa-building-columns"></i></div>
    <div class="title">البنوك والصندوق</div>
    <div class="desc">حسابات البنوك والتحويلات</div>
  </a>

  <a href="/finance/cost-centers" class="res-card">
    <div class="icon"><i class="fa-solid fa-sitemap"></i></div>
    <div class="title">مراكز الكلفة</div>
    <div class="desc">تتبع التكاليف حسب المركز</div>
  </a>
</div>
