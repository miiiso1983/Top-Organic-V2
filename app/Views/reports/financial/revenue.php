<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>تقرير الإيرادات</h3>
  <form method="get" class="form-inline">
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <div class="form-row"><label>مركز الكلفة</label>
      <select name="cost_center_id">
        <option value="">الكل</option>
        <?php foreach ($costCenters as $c): ?><option value="<?= $c['id'] ?>" <?= $costCenterId == $c['id'] ? 'selected' : '' ?>><?= Helpers::e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <button class="btn">تحديث</button>
  </form>
</div>
<div class="card">
  <table>
    <tr><th>الطلب</th><th>التاريخ</th><th>العميل</th><th>النوع</th><th>مركز الكلفة</th><th>المبلغ</th></tr>
    <?php foreach ($data as $o): ?>
    <tr>
      <td><?= Helpers::e($o['order_no']) ?></td>
      <td><?= $o['created_at'] ?></td>
      <td><?= Helpers::e($o['customer_name'] ?? '-') ?></td>
      <td><?= Helpers::e($o['order_type']) ?></td>
      <td><?= Helpers::e($o['cost_center_name'] ?? '-') ?></td>
      <td><?= Helpers::money($o['total']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
