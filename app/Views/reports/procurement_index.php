<?php use App\Core\Helpers; ?>
<style>
.procurement-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
.procurement-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; }
.procurement-card h4 { margin: 0 0 8px; }
.procurement-card a { display: inline-block; margin-top: 8px; color: #2563eb; }
</style>
<h2>تقارير المشتريات والمواد والمخزون</h2>
<div class="procurement-grid">
  <div class="procurement-card"><h4><i class="fa-solid fa-box"></i> طلبات المواد</h4><a href="/reports/procurement/material_requests">عرض</a></div>
  <div class="procurement-card"><h4><i class="fa-solid fa-pencil"></i> طلبات الشراء</h4><a href="/reports/procurement/purchase_requests">عرض</a></div>
  <div class="procurement-card"><h4><i class="fa-solid fa-truck"></i> طلبات المناقلة</h4><a href="/reports/procurement/transfer_requests">عرض</a></div>
  <div class="procurement-card"><h4>🆕 المواد الجديدة</h4><a href="/reports/procurement/new_items">عرض</a></div>
  <div class="procurement-card"><h4>⭐ المواد الأكثر طلباً</h4><a href="/reports/procurement/top_requested">عرض</a></div>
  <div class="procurement-card"><h4>⏱️ زمن دورة الطلب</h4><a href="/reports/procurement/cycle_time">عرض</a></div>
  <div class="procurement-card"><h4><i class="fa-solid fa-cart-shopping"></i> أوامر الشراء</h4><a href="/reports/procurement/purchase_orders">عرض</a></div>
  <div class="procurement-card"><h4><i class="fa-solid fa-handshake"></i> الموردين</h4><a href="/reports/procurement/suppliers">عرض</a></div>
  <div class="procurement-card"><h4><i class="fa-solid fa-triangle-exclamation"></i> الفروقات في الاستلام</h4><a href="/reports/procurement/variances">عرض</a></div>
  <div class="procurement-card"><h4><i class="fa-solid fa-xmark"></i> المواد غير المتوفرة</h4><a href="/reports/procurement/unavailable">عرض</a></div>
  <div class="procurement-card"><h4><i class="fa-solid fa-ban"></i> المواد الراكدة</h4><a href="/reports/procurement/stagnant">عرض</a></div>
  <div class="procurement-card"><h4><i class="fa-solid fa-building"></i> استهلاك الأقسام</h4><a href="/reports/procurement/consumption">عرض</a></div>
  <div class="procurement-card"><h4><i class="fa-solid fa-coins"></i> تكاليف المشتريات</h4><a href="/reports/procurement/costs">عرض</a></div>
  <div class="procurement-card"><h4><i class="fa-solid fa-chart-column"></i> تحليل أسعار الشراء</h4><a href="/reports/procurement/price_analysis">عرض</a></div>
  <div class="procurement-card"><h4><i class="fa-solid fa-industry"></i> تقارير التصنيع</h4><a href="/reports/procurement/production">عرض</a></div>
  <div class="procurement-card"><h4><i class="fa-solid fa-arrow-right-arrow-left"></i> تقارير المناقلات</h4><a href="/reports/procurement/transfers">عرض</a></div>
  <div class="procurement-card"><h4><i class="fa-solid fa-file-import"></i> تقارير الاستلام</h4><a href="/reports/procurement/receipts">عرض</a></div>
  <div class="procurement-card"><h4><i class="fa-solid fa-check"></i> تقارير الموافقات</h4><a href="/reports/procurement/approvals">عرض</a></div>
</div>
