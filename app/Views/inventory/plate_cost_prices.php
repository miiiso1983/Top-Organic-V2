<?php use App\Core\Helpers; use App\Core\PlateCost\UnitMapper; ?>
<div class="card">
  <h2><i class="fa-solid fa-cash-register"></i> الأسعار والوحدات — <?= Helpers::e($run['source_filename']) ?></h2>
  <p><a href="/inventory/plate-cost-imports/<?= (int) $run['id'] ?>" class="btn btn-sm">← العودة للتقرير</a></p>
</div>

<div class="card" style="margin-top:16px">
  <h3><i class="fa-solid fa-receipt"></i> أسعار الشراء والتحويلات</h3>
  <div style="overflow:auto">
    <table>
      <tr>
        <th>المادة</th><th>الصنف</th><th>الوحدة المستوردة</th><th>الوحدة في النظام</th><th>نوع الوحدة</th><th>المعامل</th><th>الوحدة الأساسية</th><th>السعر/وحدة مستوردة</th><th>السعر/وحدة أساسية</th><th>الهدر الخام %</th><th>حفظ</th>
      </tr>
      <?php foreach ($prices as $p):
        $unitId = UnitMapper::resolveUnitId($p['unit'] ?? null);
        $unit = $unitId ? UnitMapper::getUnit($unitId) : null;
        $baseUnitId = $unitId ? UnitMapper::baseUnitId($unitId) : null;
        $baseUnit = $baseUnitId ? UnitMapper::getUnit($baseUnitId) : null;
        $factor = $unit ? (float) ($unit['factor'] ?? 1) : 1;
        $baseFactor = $factor != 0 ? 1 / $factor : 1;
        $pricePerBase = $unitId ? (float) $p['unit_price'] * $baseFactor : (float) $p['unit_price'];
      ?>
      <tr>
        <form method="post" action="/inventory/plate-cost-imports/<?= (int) $run['id'] ?>/prices" style="display: contents;">
          <input type="hidden" name="price_id" value="<?= (int) $p['id'] ?>">
          <td><?= Helpers::e($p['material_name']) ?></td>
          <td><?= Helpers::e($p['material_category'] ?? '-') ?></td>
          <td><?= Helpers::e($p['unit'] ?? '-') ?></td>
          <td><?= $unit ? Helpers::e($unit['name'] ?? '') : '-' ?></td>
          <td><?= $unitId ? Helpers::e(UnitMapper::unitTypeLabel($unitId)) : '-' ?></td>
          <td><?= $unit ? number_format($factor, 6) : '-' ?></td>
          <td><?= $baseUnit ? Helpers::e($baseUnit['name'] ?? '') : '-' ?></td>
          <td><input type="number" name="unit_price" step="0.0001" value="<?= number_format((float) $p['unit_price'], 4) ?>" class="form-control" style="width:110px"></td>
          <td><?= number_format($pricePerBase, 4) ?> <?= $baseUnit ? Helpers::e($baseUnit['name'] ?? '') : '' ?></td>
          <td><input type="number" name="raw_waste_rate" step="0.01" value="<?= number_format((float) $p['raw_waste_rate'] * 100, 2) ?>" class="form-control" style="width:90px"></td>
          <td><button class="btn btn-sm btn-primary">حفظ</button></td>
        </form>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
