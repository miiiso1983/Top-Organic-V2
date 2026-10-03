<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>تقرير استهلاك المواد</h3>
  <form method="get" class="form-inline">
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">تحديث</button>
  </form>
</div>
<div class="card">
  <table>
    <tr><th>المادة</th><th>الوحدة</th><th>الكمية المستهلكة</th><th>التكلفة</th></tr>
    <?php foreach ($data as $r): ?>
    <tr>
      <td><?= Helpers::e($r['item_name']) ?></td>
      <td><?= Helpers::e($r['unit_name'] ?? '-') ?></td>
      <td><?= Helpers::money($r['consumed_qty']) ?></td>
      <td><?= Helpers::money($r['consumed_cost']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
