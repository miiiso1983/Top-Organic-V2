<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>الميزانية العمومية</h3>
  <form method="get" class="form-inline">
    <div class="form-row"><label>حتى تاريخ</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">تحديث</button>
  </form>
</div>
<div class="card">
  <table>
    <tr><th>البند</th><th>المبلغ</th></tr>
    <tr><td>الأصول</td><td><?= Helpers::money($data['assets']) ?></td></tr>
    <tr><td>الخصوم</td><td><?= Helpers::money($data['liabilities']) ?></td></tr>
    <tr><td>حقوق الملكية</td><td><?= Helpers::money($data['equity']) ?></td></tr>
    <tr style="font-weight:bold"><td>الخصوم + حقوق الملكية</td><td><?= Helpers::money($data['totalLiabilitiesEquity']) ?></td></tr>
  </table>
</div>
