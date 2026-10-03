<?php use App\Core\Helpers; use App\Core\PlateCost\UnitMapper; ?>
<div class="card">
  <h2><i class="fa-solid fa-utensils"></i> وصفات الأطباق — <?= Helpers::e($run['source_filename']) ?></h2>
  <p><a href="/inventory/plate-cost-imports/<?= (int) $run['id'] ?>" class="btn btn-sm">← العودة للتقرير</a></p>
</div>

<?php foreach ($dishes as $dishName => $lines): ?>
<div class="card" style="margin-top:16px">
  <h3><?= Helpers::e($dishName) ?></h3>
  <div style="overflow:auto">
    <table>
      <tr>
        <th>#</th><th>المادة</th><th>النوع</th><th>الوحدة</th><th>نوع الوحدة</th><th>الوزن/الكمية</th><th>الكمية بالوحدة الأساسية</th>
      </tr>
      <?php foreach ($lines as $i => $l):
        $unitId = UnitMapper::resolveUnitId($l['unit']);
        $unit = $unitId ? UnitMapper::getUnit($unitId) : null;
        $baseUnitId = $unitId ? UnitMapper::baseUnitId($unitId) : null;
        $baseUnit = $baseUnitId ? UnitMapper::getUnit($baseUnitId) : null;
        $baseQty = $unitId ? UnitMapper::convertToBase((float) $l['base_quantity'], $unitId) : null;
      ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><?= Helpers::e($l['material_name']) ?></td>
        <td><?= Helpers::e($l['material_type']) ?></td>
        <td><?= Helpers::e($l['unit'] ?? '-') ?> <?= $unit ? '(' . Helpers::e($unit['name'] ?? '') . ')' : '' ?></td>
        <td><?= $unitId ? Helpers::e(UnitMapper::unitTypeLabel($unitId)) : '-' ?></td>
        <td><?= number_format((float) $l['base_quantity'], 2) ?></td>
        <td><?= $unitId ? number_format($baseQty, 4) . ' ' . Helpers::e($baseUnit['name'] ?? '') : '-' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php endforeach; ?>
