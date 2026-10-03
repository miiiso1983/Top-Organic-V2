<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>تقرير الإهلاكات</h3>
  <form method="get" class="form-inline">
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">تحديث</button>
  </form>
</div>
<div class="card">
  <table>
    <tr><th>التاريخ</th><th>الأصل</th><th>المبلغ</th></tr>
    <?php foreach ($data as $d): ?>
    <tr>
      <td><?= $d['entry_date'] ?></td>
      <td><?= Helpers::e($d['asset_name']) ?> (<?= Helpers::e($d['asset_no']) ?>)</td>
      <td><?= Helpers::money($d['amount']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
