<?php use App\Core\{Helpers, Auth}; ?>
<div class="grid grid-4">
  <?php if (Auth::can('sales.view')): ?>
  <div class="stat"><div class="value"><?= Helpers::money($sales['total']) ?></div><div class="label">مبيعات اليوم</div></div>
  <div class="stat"><div class="value"><?= (int)$sales['orders_count'] ?></div><div class="label">طلبات اليوم</div></div>
  <div class="stat"><div class="value"><?= (int)$activeOrders ?></div><div class="label">طلبات قيد التنفيذ</div></div>
  <?php endif; ?>
  <?php if (Auth::can('reservations.manage')): ?>
  <div class="stat"><div class="value"><?= (int)$reservationsToday ?></div><div class="label">حجوزات اليوم</div></div>
  <?php endif; ?>
</div>

<?php if (Auth::can('sales.view')): ?>
<div class="grid grid-2" style="margin-top:20px">
  <div class="card">
    <h3>الطلبات حسب النوع (اليوم)</h3>
    <table>
      <tr><th>النوع</th><th>العدد</th></tr>
      <?php $types = ['dine_in'=>'صالة','takeaway'=>'سفري','delivery'=>'توصيل','online'=>'إلكتروني']; ?>
      <?php foreach ($byType as $t): ?>
        <tr><td><?= $types[$t['order_type']] ?? $t['order_type'] ?></td><td><?= (int)$t['c'] ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$byType): ?><tr><td colspan="2">لا توجد طلبات اليوم</td></tr><?php endif; ?>
    </table>
    <?php if (Auth::can('finance.view')): ?>
    <p style="margin-top:12px;color:#6b7280">مصروفات اليوم: <strong><?= Helpers::money($expensesToday) ?></strong></p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>الأصناف الأكثر مبيعاً (اليوم)</h3>
    <table>
      <tr><th>الصنف</th><th>الكمية</th></tr>
      <?php foreach ($topItems as $item): ?>
        <tr><td><?= Helpers::e($item['name']) ?></td><td><?= (float)$item['sold'] ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$topItems): ?><tr><td colspan="2">لا توجد بيانات</td></tr><?php endif; ?>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if (Auth::can('inventory.view') || Auth::can('approvals.decide')): ?>
<div class="grid grid-2" style="margin-top:20px">
  <?php if (Auth::can('procurement.dashboard') || Auth::can('material_request.view')): ?>
  <div class="card">
    <h3><i class="fa-solid fa-boxes-stacked"></i> المشتريات والمواد</h3>
    <table>
      <tr><th>المؤشر</th><th>القيمة</th></tr>
      <tr><td>طلبات مواد بانتظار الموافقة</td><td><?= (int)($procurement['pending_mr'] ?? 0) ?></td></tr>
      <tr><td>طلبات شراء معلقة</td><td><?= (int)($procurement['pending_pr'] ?? 0) ?></td></tr>
      <tr><td>أوامر شراء مفتوحة</td><td><?= (int)($procurement['open_po'] ?? 0) ?></td></tr>
      <tr><td>استلامات اليوم</td><td><?= Helpers::money($procurement['today_receipts'] ?? 0) ?></td></tr>
      <tr><td>فواتير غير مدفوعة</td><td><?= Helpers::money($procurement['unpaid_invoices'] ?? 0) ?></td></tr>
    </table>
  </div>
  <?php endif; ?>

  <?php if (Auth::can('inventory.view')): ?>
  <div class="card">
    <h3><i class="fa-solid fa-triangle-exclamation"></i> تنبيهات النواقص</h3>
    <table>
      <tr><th>المادة</th><th>الرصيد</th><th>حد التنبيه</th></tr>
      <?php foreach (array_slice($lowStock, 0, 8) as $ls): ?>
        <tr>
          <td><?= Helpers::e($ls['name']) ?></td>
          <td style="color:#dc2626"><?= (float)$ls['total_qty'] ?> <?= Helpers::e($ls['unit']) ?></td>
          <td><?= (float)$ls['min_qty'] ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$lowStock): ?><tr><td colspan="3"><i class="fa-solid fa-check"></i> لا توجد نواقص</td></tr><?php endif; ?>
    </table>
  </div>
  <?php endif; ?>

  <?php if (Auth::can('approvals.decide')): ?>
  <div class="card">
    <h3><i class="fa-solid fa-pen-to-square"></i> موافقات بانتظارك</h3>
    <table>
      <tr><th>النوع</th><th>مقدم الطلب</th><th>المبلغ</th><th></th></tr>
      <?php foreach (array_slice($pendingApprovals, 0, 8) as $ap): ?>
        <tr>
          <td><?= Helpers::e($ap['workflow_name']) ?></td>
          <td><?= Helpers::e($ap['requester'] ?? '-') ?></td>
          <td><?= Helpers::money($ap['amount']) ?></td>
          <td><a href="/approvals" class="btn btn-sm">عرض</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$pendingApprovals): ?><tr><td colspan="4"><i class="fa-solid fa-check"></i> لا توجد موافقات معلقة</td></tr><?php endif; ?>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

