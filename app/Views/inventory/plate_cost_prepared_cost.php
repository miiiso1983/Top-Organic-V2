<?php use App\Core\Helpers; use App\Core\PlateCost\UnitMapper; ?>
<div class="card">
  <h2><i class="fa-solid fa-bowl-food"></i> تكلفة المواد المحضرة — <?= Helpers::e($run['source_filename']) ?></h2>
  <p>
    <a href="/inventory/plate-cost-imports/<?= (int) $run['id'] ?>" class="btn btn-sm">← العودة للتقرير</a>
  </p>
  <form method="get" action="/inventory/plate-cost-imports/<?= (int) $run['id'] ?>/prepared-cost" class="form-inline">
    <div class="form-row">
      <label>اختر المادة المحضرة</label>
      <select name="prepared" class="form-control" onchange="this.form.submit()">
        <option value="">— الكل —</option>
        <?php foreach ($preparedList as $name): ?>
        <option value="<?= Helpers::e($name) ?>" <?= ($preparedName === $name) ? 'selected' : '' ?>><?= Helpers::e($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>

<div class="card" style="margin-top:16px">
  <h3><i class="fa-solid fa-calculator"></i> المكونات</h3>
  <div style="overflow:auto">
    <table>
      <tr>
        <th>المحضر</th><th>المادة</th><th>الوحدة</th><th>نوع الوحدة</th><th>الوزن الإجمالي (Gross)</th><th>الهدر %</th><th>الوزن الصافي (Net)</th><th>كمية الهدر</th><th>سعر الشراء/وحدة</th><th>الكلفة الإجمالية</th>
      </tr>
      <?php foreach ($lines as $l):
        $gross = (float) $l['actual_consumed_quantity'];
        $net = (float) $l['base_quantity'];
        $waste = $gross - $net;
        $unitId = UnitMapper::resolveUnitId($l['unit']);
        $unit = $unitId ? UnitMapper::getUnit($unitId) : null;
      ?>
      <tr>
        <td><?= Helpers::e($l['prepared_name']) ?></td>
        <td><?= Helpers::e($l['ingredient_name']) ?></td>
        <td><?= Helpers::e($l['unit'] ?? '-') ?> <?= $unit ? '(' . Helpers::e($unit['name'] ?? '') . ')' : '' ?></td>
        <td><?= $unitId ? Helpers::e(UnitMapper::unitTypeLabel($unitId)) : '-' ?></td>
        <td><?= number_format($gross, 4) ?></td>
        <td>
          <form method="post" action="/inventory/plate-cost-imports/<?= (int) $run['id'] ?>/prepared-cost" class="form-inline">
            <input type="hidden" name="line_id" value="<?= (int) $l['id'] ?>">
            <input type="hidden" name="prepared_name" value="<?= Helpers::e($l['prepared_name']) ?>">
            <input type="number" name="department_waste_rate" step="0.01" value="<?= number_format((float) $l['department_waste_rate'] * 100, 2) ?>" class="form-control" style="width:90px">
            <span>%</span>
            <button class="btn btn-sm btn-primary">حفظ</button>
          </form>
        </td>
        <td><?= number_format($net, 4) ?></td>
        <td><?= number_format($waste, 4) ?></td>
        <td><?= number_format((float) $l['unit_price'], 4) ?></td>
        <td><?= number_format((float) $l['line_cost'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
