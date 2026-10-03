<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>تقرير مراكز الكلفة</h3>
  <form method="get" class="form-inline">
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">تحديث</button>
  </form>
</div>
<div class="card">
  <table>
    <tr><th>المركز</th><th>الكود</th><th>الإيرادات</th><th>المصروفات</th><th>الربحية</th></tr>
    <?php foreach ($data as $c): ?>
    <tr>
      <td><?= Helpers::e($c['name']) ?></td>
      <td><?= Helpers::e($c['code'] ?? '-') ?></td>
      <td><?= Helpers::money($c['revenue']) ?></td>
      <td><?= Helpers::money($c['expenses']) ?></td>
      <td style="color:<?= $c['profit'] >= 0 ? '#16a34a' : '#c0392b' ?>"><?= Helpers::money($c['profit']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
