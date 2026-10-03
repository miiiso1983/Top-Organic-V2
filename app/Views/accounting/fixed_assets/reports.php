<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h2>تقارير الإهلاك والقيمة الدفترية</h2>
  <form method="get" class="form-inline">
    <input type="month" name="period" value="<?= Helpers::e($period) ?>">
    <button class="btn btn-secondary">عرض</button>
  </form>
</div>
<div class="card">
  <table>
    <tr><th>الرقم</th><th>الاسم</th><th>الطريقة</th><th>القيمة الشرائية</th><th>المتراكم</th><th>الدفترية</th><th>إهلاك الفترة</th></tr>
    <?php $totalCost = $totalAccum = $totalBook = $totalPeriod = 0; ?>
    <?php foreach ($rows as $r): ?>
    <?php $totalCost += (float)$r['purchase_cost']; $totalAccum += (float)$r['accumulated_depreciation']; $totalBook += (float)$r['book_value']; $totalPeriod += (float)$r['period_amount']; ?>
    <tr>
      <td><?= Helpers::e($r['asset_no']) ?></td>
      <td><?= Helpers::e($r['name']) ?></td>
      <td><?= App\Core\FixedAssetService::METHODS[$r['depreciation_method']] ?? $r['depreciation_method'] ?></td>
      <td><?= Helpers::money($r['purchase_cost']) ?></td>
      <td><?= Helpers::money($r['accumulated_depreciation']) ?></td>
      <td><?= Helpers::money($r['book_value']) ?></td>
      <td><?= Helpers::money($r['period_amount']) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr style="font-weight:bold">
      <td colspan="3">المجموع</td>
      <td><?= Helpers::money($totalCost) ?></td>
      <td><?= Helpers::money($totalAccum) ?></td>
      <td><?= Helpers::money($totalBook) ?></td>
      <td><?= Helpers::money($totalPeriod) ?></td>
    </tr>
  </table>
</div>
