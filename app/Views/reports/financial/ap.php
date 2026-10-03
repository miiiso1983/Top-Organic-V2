<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>تقرير الذمم الدائنة</h3>
  <form method="get" class="form-inline">
    <div class="form-row"><label>حتى تاريخ</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">تحديث</button>
  </form>
</div>
<div class="card">
  <table>
    <tr><th>المورد</th><th>الهاتف</th><th>الفواتير</th><th>المدفوعات</th><th>المتبقي</th></tr>
    <?php foreach ($data as $r): ?>
    <tr>
      <td><?= Helpers::e($r['name']) ?></td>
      <td><?= Helpers::e($r['phone'] ?? '-') ?></td>
      <td><?= Helpers::money($r['invoiced']) ?></td>
      <td><?= Helpers::money($r['paid']) ?></td>
      <td><?= Helpers::money($r['balance']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
