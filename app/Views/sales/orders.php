<?php use App\Core\Helpers; ?>
<?php
$statusLabels = ['pending'=>'معلق','confirmed'=>'مؤكد','preparing'=>'قيد التحضير','ready'=>'جاهز',
                 'served'=>'مقدم','delivering'=>'قيد التوصيل','completed'=>'مكتمل','cancelled'=>'ملغي'];
$typeLabels = ['dine_in'=>'صالة','takeaway'=>'سفري','delivery'=>'توصيل','online'=>'إلكتروني'];
?>
<div class="page-head">
  <h2>الطلبات</h2>
  <form class="form-inline" method="get" action="/orders">
    <div class="form-row">
      <select name="type" onchange="this.form.submit()">
        <option value="">كل الأنواع</option>
        <?php foreach ($typeLabels as $k => $v): ?>
          <option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <select name="status" onchange="this.form.submit()">
        <option value="">كل الحالات</option>
        <?php foreach ($statusLabels as $k => $v): ?>
          <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <label>من</label>
      <input type="date" name="from" value="<?= Helpers::e($from ?? '') ?>">
    </div>
    <div class="form-row">
      <label>إلى</label>
      <input type="date" name="to" value="<?= Helpers::e($to ?? '') ?>">
    </div>
    <div class="form-row">
      <button type="submit" class="btn">تصفية</button>
      <?php if (($from ?? '') || ($to ?? '')): ?>
        <a href="/orders" class="btn btn-sm">إلغاء التصفية</a>
      <?php endif; ?>
    </div>
    <div class="form-row">
      <button type="submit" formaction="/orders/export?format=excel" class="btn">تصدير Excel</button>
      <button type="submit" formaction="/orders/export?format=pdf" class="btn">تصدير PDF</button>
    </div>
  </form>
</div>

<div class="card">
  <table>
    <tr>
      <th>رقم الطلب</th><th>النوع</th><th>الطاولة</th><th>الحالة</th>
      <th>الإجمالي</th><th>الدفع</th><th>الكاشير</th><th>الوقت</th><th></th>
    </tr>
    <?php foreach ($orders as $o): ?>
    <tr>
      <td><?= Helpers::e($o['order_no']) ?></td>
      <td><?= $typeLabels[$o['order_type']] ?? $o['order_type'] ?></td>
      <td><?= $o['table_name'] ? 'طاولة ' . Helpers::cleanName($o['table_name']) : '-' ?></td>
      <td><span class="badge badge-<?= $o['status'] ?>"><?= $statusLabels[$o['status']] ?? $o['status'] ?></span></td>
      <td><?= Helpers::money($o['total']) ?></td>
      <td><span class="badge badge-<?= $o['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
          <?= ['unpaid'=>'غير مدفوع','partial'=>'جزئي','paid'=>'مدفوع','refunded'=>'مسترجع'][$o['payment_status']] ?></span></td>
      <td><?= Helpers::e($o['cashier'] ?? '-') ?></td>
      <td><?= date('H:i m/d', strtotime($o['created_at'])) ?></td>
      <td><a class="btn btn-sm" href="/orders/<?= $o['id'] ?>">عرض</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="9">لا توجد طلبات</td></tr><?php endif; ?>
  </table>
</div>
