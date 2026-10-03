<?php use App\Core\Helpers; ?>
<style>
.mr-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
.mr-table { width: 100%; border-collapse: collapse; }
.mr-table th, .mr-table td { border: 1px solid #e5e7eb; padding: 10px; text-align: right; }
.mr-table th { background: #f3f4f6; }
</style>

<h2>أمر شراء <?= Helpers::e($po['po_no']) ?></h2>
<p>المورد: <?= Helpers::e($po['supplier_name']) ?> — الحالة: <?= Helpers::e($po['status']) ?> — الشحن: <?= Helpers::e($po['shipping_status']) ?></p>
<?php if ($po['request_id']): ?><p>طلب الشراء: <?= Helpers::e($po['pr_no']) ?></p><?php endif; ?>
<?php if ($po['rfq_id']): ?><p>RFQ: <?= Helpers::e($po['rfq_no']) ?></p><?php endif; ?>

<div class="mr-card">
  <h3>البنود</h3>
  <table class="mr-table">
    <tr><th>المادة</th><th>الكمية</th><th>سعر الوحدة</th><th>المستلم</th></tr>
    <?php foreach ($items as $it): ?>
    <tr>
      <td><?= Helpers::e($it['item_name']) ?></td>
      <td><?= number_format((float)$it['qty'], 2) ?></td>
      <td><?= number_format((float)$it['unit_price'], 2) ?></td>
      <td><?= number_format((float)$it['received_qty'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if ($po['status'] === 'draft' && Auth::can('purchasing.manage')): ?>
<div class="mr-card">
  <form method="POST" action="/purchasing/orders/<?= $po['id'] ?>/approve" onsubmit="return confirm('اعتماد أمر الشراء؟')">
    <button type="submit" class="btn btn-success">اعتماد وإرسال للمورد</button>
  </form>
</div>
<?php endif; ?>

<div class="mr-card">
  <h3>تتبع التوريد</h3>
  <table class="mr-table">
    <tr><th>الحالة</th><th>الموقع</th><th>الملاحظات</th><th>بواسطة</th><th>التاريخ</th></tr>
    <?php foreach ($tracking as $t): ?>
    <tr>
      <td><?= Helpers::e($t['status']) ?></td>
      <td><?= Helpers::e($t['location'] ?? '-') ?></td>
      <td><?= Helpers::e($t['notes'] ?? '-') ?></td>
      <td><?= Helpers::e($t['created_by_name'] ?? '-') ?></td>
      <td><?= $t['created_at'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (Auth::can('purchasing.manage')): ?>
  <form method="POST" action="/purchasing/orders/<?= $po['id'] ?>/tracking" class="form-row" style="margin-top:10px">
    <select name="status" class="form-input" required>
      <option value="processing">قيد المعالجة</option>
      <option value="shipped">تم الشحن</option>
      <option value="in_transit">في الطريق</option>
      <option value="customs">في الجمارك</option>
      <option value="delivered_to_warehouse">وصل للمخزن</option>
    </select>
    <input type="text" name="location" class="form-input" placeholder="الموقع">
    <input type="text" name="notes" class="form-input" placeholder="ملاحظات">
    <button type="submit" class="btn btn-primary">تحديث</button>
  </form>
  <?php endif; ?>
</div>

<div class="mr-card">
  <h3>الشحنات</h3>
  <table class="mr-table">
    <tr><th>رقم التتبع</th><th>الناقل</th><th>تاريخ الشحن</th><th>الوصول المتوقع</th><th>الوصول الفعلي</th><th>الحالة</th><th>استلام</th></tr>
    <?php foreach ($shipments as $s): ?>
    <tr>
      <td><?= Helpers::e($s['tracking_no'] ?? '-') ?></td>
      <td><?= Helpers::e($s['carrier'] ?? '-') ?></td>
      <td><?= $s['shipping_date'] ?? '-' ?></td>
      <td><?= $s['expected_arrival'] ?? '-' ?></td>
      <td><?= $s['actual_arrival'] ?? '-' ?></td>
      <td><?= Helpers::e($s['status']) ?></td>
      <td>
        <?php if ($s['status'] !== 'received' && Auth::can('purchasing.manage')): ?>
        <form method="POST" action="/purchasing/shipments/<?= $s['id'] ?>/receive" style="display:inline">
          <select name="warehouse_id" class="form-input" required>
            <?php foreach ($warehouses as $w): ?>
            <option value="<?= $w['id'] ?>"><?= Helpers::e($w['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm btn-success">استلام</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (Auth::can('purchasing.manage')): ?>
  <form method="POST" action="/purchasing/orders/<?= $po['id'] ?>/shipments" class="form-row" style="margin-top:10px">
    <input type="text" name="tracking_no" class="form-input" placeholder="رقم التتبع" required>
    <input type="text" name="carrier" class="form-input" placeholder="الناقل">
    <input type="date" name="shipping_date" class="form-input">
    <input type="date" name="expected_arrival" class="form-input">
    <select name="status" class="form-input">
      <option value="draft">مسودة</option>
      <option value="shipped">تم الشحن</option>
    </select>
    <input type="text" name="notes" class="form-input" placeholder="ملاحظات">
    <button type="submit" class="btn btn-primary">إضافة شحنة</button>
  </form>
  <?php endif; ?>
</div>
