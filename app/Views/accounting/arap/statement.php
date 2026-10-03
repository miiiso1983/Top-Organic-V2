<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h2><?= $type === 'customer' ? 'كشف حساب عميل' : 'كشف حساب مورد' ?>: <?= Helpers::e($party['name']) ?></h2>
  <div class="grid grid-3" style="margin-top:12px">
    <div><strong>إجمالي الفواتير:</strong> <?= Helpers::money($balance['invoiced']) ?></div>
    <div><strong>إجمالي المدفوعات:</strong> <?= Helpers::money($balance['paid']) ?></div>
    <div><strong>التسويات:</strong> <?= Helpers::money($balance['adjustments']) ?></div>
    <div><strong>الرصيد المستحق:</strong> <?= Helpers::money($balance['balance']) ?></div>
  </div>
</div>
<div class="grid grid-2">
  <div class="card">
    <h3>الحركات</h3>
    <table>
      <tr><th>التاريخ</th><th>النوع</th><th>الرقم</th><th>المبلغ</th><th>الحالة</th></tr>
      <?php foreach ($lines as $l): ?>
      <tr>
        <td><?= $l['date'] ?></td>
        <td><?= $l['type'] === 'invoice' ? 'فاتورة' : ($l['type'] === 'payment' ? 'سند' : 'تسوية') ?></td>
        <td><?= Helpers::e($l['ref']) ?></td>
        <td><?= Helpers::money($l['amount']) ?></td>
        <td><?= Helpers::e($l['status'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div class="card">
    <h3><?= $type === 'customer' ? 'تحصيل' : 'دفع' ?> مع تخصيص</h3>
    <form method="post" action="/accounting/arap/payments">
      <input type="hidden" name="party_type" value="<?= $type ?>">
      <input type="hidden" name="party_id" value="<?= $party['id'] ?>">
      <div class="form-row"><label>المبلغ الكلي</label><input type="number" name="amount" step="0.01" required></div>
      <div class="form-row"><label>التاريخ</label><input type="date" name="date" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-row"><label>الطريقة</label>
        <select name="method"><option value="cash">نقد</option><option value="card">بطاقة</option><option value="transfer">تحويل</option></select>
      </div>
      <div class="form-row"><label>الرقم المرجعي</label><input type="text" name="reference"></div>
      <div class="form-row"><label>البيان</label><textarea name="description" rows="2"></textarea></div>
      <?php if ($openOrders): ?>
      <h4>تخصيص للفواتير المفتوحة</h4>
      <?php foreach ($openOrders as $o): ?>
      <div class="form-row"><label><?= Helpers::e($o['ref']) ?> (متبقي: <?= Helpers::money($o['remaining']) ?>)</label>
        <input type="number" name="allocations[<?= $type === 'customer' ? 'order_' . $o['id'] : 'po_' . $o['id'] ?>]" step="0.01" value="0">
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
      <button class="btn btn-success"><?= $type === 'customer' ? 'تحصيل' : 'دفع' ?></button>
    </form>
    <hr style="margin:20px 0">
    <h3>تسوية / خصم</h3>
    <form method="post" action="/accounting/arap/adjustments">
      <input type="hidden" name="party_type" value="<?= $type ?>">
      <input type="hidden" name="party_id" value="<?= $party['id'] ?>">
      <div class="form-row"><label>المبلغ</label><input type="number" name="amount" step="0.01" required></div>
      <div class="form-row"><label>التاريخ</label><input type="date" name="date" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-row"><label>نوع التسوية</label>
        <select name="adjustment_type">
          <option value="discount">خصم</option>
          <option value="write_off">شطب</option>
          <option value="return">مردود</option>
          <option value="other">أخرى</option>
        </select>
      </div>
      <div class="form-row"><label>البيان</label><textarea name="description" rows="2"></textarea></div>
      <button class="btn btn-warning">تسجيل التسوية</button>
    </form>
  </div>
</div>
