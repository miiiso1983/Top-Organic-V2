<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>الربحية حسب المنتج</h3>
  <form method="get" class="form-inline">
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">تحديث</button>
  </form>
</div>
<div class="card">
  <table>
    <tr><th>المنتج</th><th>الكمية</th><th>الإيراد</th><th>التكلفة</th><th>الربح</th></tr>
    <?php foreach ($data as $p): ?>
    <tr>
      <td><?= Helpers::e($p['name']) ?></td>
      <td><?= Helpers::money($p['qty']) ?></td>
      <td><?= Helpers::money($p['revenue']) ?></td>
      <td><?= Helpers::money($p['cost']) ?></td>
      <td style="color:<?= ($p['revenue'] - $p['cost']) >= 0 ? '#16a34a' : '#c0392b' ?>"><?= Helpers::money($p['revenue'] - $p['cost']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
