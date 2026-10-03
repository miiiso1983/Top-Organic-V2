<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>تقرير المصروفات</h3>
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
    <tr><th>الرقم</th><th>التصنيف</th><th>المبلغ</th><th>مركز الكلفة</th><th>التاريخ</th><th>الحالة</th><th>بواسطة</th></tr>
    <?php foreach ($data as $e): ?>
    <tr>
      <td><?= Helpers::e($e['expense_no']) ?></td>
      <td><?= Helpers::e($e['category']) ?></td>
      <td><?= Helpers::money($e['amount']) ?></td>
      <td><?= Helpers::e($e['cost_center_name'] ?? '-') ?></td>
      <td><?= $e['expense_date'] ?></td>
      <td><?= Helpers::e($e['status']) ?></td>
      <td><?= Helpers::e($e['full_name'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
