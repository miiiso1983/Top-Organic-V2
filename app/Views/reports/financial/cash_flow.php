<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>التدفقات النقدية</h3>
  <form method="get" class="form-inline">
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">تحديث</button>
  </form>
</div>
<div class="card">
  <table>
    <tr><th>البند</th><th>النوع</th><th>المبلغ</th></tr>
    <tr><td>الصندوق</td><td>وارد</td><td><?= Helpers::money($data['cashIn']) ?></td></tr>
    <tr><td>الصندوق</td><td>صادر</td><td><?= Helpers::money($data['cashOut']) ?></td></tr>
    <tr><td>البنك</td><td>وارد</td><td><?= Helpers::money($data['bankIn']) ?></td></tr>
    <tr><td>البنك</td><td>صادر</td><td><?= Helpers::money($data['bankOut']) ?></td></tr>
    <tr style="font-weight:bold"><td colspan="2">صافي الصندوق</td><td><?= Helpers::money($data['netCash']) ?></td></tr>
    <tr style="font-weight:bold"><td colspan="2">صافي البنك</td><td><?= Helpers::money($data['netBank']) ?></td></tr>
    <tr style="font-weight:bold; font-size:18px"><td colspan="2">الصافي الكلي</td><td><?= Helpers::money($data['netTotal']) ?></td></tr>
  </table>
</div>
