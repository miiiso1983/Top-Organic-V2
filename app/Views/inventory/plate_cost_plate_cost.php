<?php use App\Core\Helpers; use App\Core\PlateCost\UnitMapper; ?>
<div class="card">
  <h2><i class="fa-solid fa-coins"></i> تكلفة الأطباق — <?= Helpers::e($run['source_filename']) ?></h2>
  <p>
    <a href="/inventory/plate-cost-imports/<?= (int) $run['id'] ?>" class="btn btn-sm">← العودة للتقرير</a>
    <a href="/inventory/plate-cost-imports/<?= (int) $run['id'] ?>/recipes" class="btn btn-sm">وصفات</a>
  </p>
  <form method="get" action="/inventory/plate-cost-imports/<?= (int) $run['id'] ?>/plate-cost" class="form-inline">
    <div class="form-row">
      <label>اختر الطبق</label>
      <select name="dish" class="form-control" onchange="this.form.submit()">
        <option value="">— الكل —</option>
        <?php foreach ($dishes as $d): ?>
        <option value="<?= Helpers::e($d['dish_name']) ?>" <?= ($dishName === $d['dish_name']) ? 'selected' : '' ?>><?= Helpers::e($d['dish_name']) ?> (<?= number_format((float) $d['dish_cost'], 2) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>

<?php if ($selected): ?>
<div class="card" style="margin-top:16px">
  <h3><i class="fa-solid fa-chart-column"></i> <?= Helpers::e($selected['dish_name']) ?></h3>
  <p>تكلفة الطبق: <strong><?= number_format((float) $selected['dish_cost'], 2) ?></strong> | سعر البيع: <?= number_format((float) $selected['sale_price'], 2) ?> | الربح: <?= number_format((float) $selected['gross_profit'], 2) ?> | النسبة: <?= number_format((float) $selected['markup_on_cost'] * 100, 2) ?>%</p>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
  <h3><i class="fa-solid fa-calculator"></i> مكونات الطبق</h3>
  <div style="overflow:auto">
    <table>
      <tr>
        <th>المادة</th><th>النوع</th><th>الوحدة</th><th>نوع الوحدة</th><th>الوزن الإجمالي (Gross)</th><th>الهدر %</th><th>الوزن الصافي (Net)</th><th>كمية الهدر</th><th>التكلفة/وحدة</th><th>الكلفة الإجمالية</th>
      </tr>
      <?php foreach ($lines as $l):
        $gross = (float) $l['actual_consumed_quantity'];
        $net = (float) $l['base_quantity'];
        $waste = $gross - $net;
        $unitId = UnitMapper::resolveUnitId($l['unit']);
        $unit = $unitId ? UnitMapper::getUnit($unitId) : null;
      ?>
      <tr>
        <td><?= Helpers::e($l['material_name']) ?></td>
        <td><?= Helpers::e($l['material_type']) ?></td>
        <td><?= Helpers::e($l['unit'] ?? '-') ?> <?= $unit ? '(' . Helpers::e($unit['name'] ?? '') . ')' : '' ?></td>
        <td><?= $unitId ? Helpers::e(UnitMapper::unitTypeLabel($unitId)) : '-' ?></td>
        <td><?= number_format($gross, 4) ?></td>
        <td>
          <form method="post" action="/inventory/plate-cost-imports/<?= (int) $run['id'] ?>/plate-cost" class="form-inline">
            <input type="hidden" name="line_id" value="<?= (int) $l['id'] ?>">
            <input type="hidden" name="dish_name" value="<?= Helpers::e($l['dish_name']) ?>">
            <input type="number" name="department_waste_rate" step="0.01" value="<?= number_format((float) $l['department_waste_rate'] * 100, 2) ?>" class="form-control" style="width:90px">
            <span>%</span>
            <button class="btn btn-sm btn-primary">حفظ</button>
          </form>
        </td>
        <td><?= number_format($net, 4) ?></td>
        <td><?= number_format($waste, 4) ?></td>
        <td><?= number_format((float) $l['resolved_unit_cost'], 4) ?></td>
        <td><?= number_format((float) $l['line_cost'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
