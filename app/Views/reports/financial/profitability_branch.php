<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>الربحية حسب الفرع</h3>
  <form method="get" class="form-inline">
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">تحديث</button>
  </form>
</div>
<div class="card">
  <table>
    <tr><th>الفرع</th><th>الإيرادات</th><th>المصروفات</th><th>الربحية</th></tr>
    <?php foreach ($data as $b): ?>
    <tr>
      <td><?= Helpers::e($b['name']) ?></td>
      <td><?= Helpers::money($b['revenue']) ?></td>
      <td><?= Helpers::money($b['expenses']) ?></td>
      <td style="color:<?= ($b['revenue'] - $b['expenses']) >= 0 ? '#16a34a' : '#c0392b' ?>"><?= Helpers::money($b['revenue'] - $b['expenses']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
