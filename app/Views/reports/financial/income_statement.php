<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>قائمة الدخل</h3>
  <form method="get" class="form-inline">
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">تحديث</button>
  </form>
</div>
<div class="card">
  <table>
    <tr><th>البند</th><th>المبلغ</th></tr>
    <tr><td>الإيرادات</td><td><?= Helpers::money($data['revenue']) ?></td></tr>
    <tr><td>تكلفة البضاعة المباعة</td><td><?= Helpers::money($data['cogs']) ?></td></tr>
    <tr><td>المصروفات</td><td><?= Helpers::money($data['expenses']) ?></td></tr>
    <tr style="font-weight:bold; font-size:18px"><td>صافي الدخل</td><td style="color:<?= $data['netIncome'] >= 0 ? '#16a34a' : '#c0392b' ?>"><?= Helpers::money($data['netIncome']) ?></td></tr>
  </table>
</div>
