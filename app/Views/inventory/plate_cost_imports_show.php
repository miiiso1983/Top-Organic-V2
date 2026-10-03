<?php use App\Core\Helpers; ?>
<div class="card">
  <h2><i class="fa-solid fa-chart-column"></i> تقرير الاستيراد #<?= (int) $run['id'] ?></h2>
  <p><strong>الملف:</strong> <?= Helpers::e($run['source_filename']) ?> — <strong>الحالة:</strong>
    <?php if ($run['status'] === 'success'): ?><span class="badge badge-success">نجاح</span>
    <?php elseif ($run['status'] === 'partial'): ?><span class="badge badge-warning">جزئي</span>
    <?php elseif ($run['status'] === 'error'): ?><span class="badge badge-danger">خطأ</span>
    <?php else: ?><span class="badge"><?= Helpers::e($run['status']) ?></span>
    <?php endif; ?>
  </p>
  <a href="/inventory/plate-cost-imports" class="btn btn-sm">← العودة للسجل</a>
  <a href="/inventory/plate-cost-imports/<?= (int) $run['id'] ?>/recipes" class="btn btn-sm"><i class="fa-solid fa-utensils"></i> الوصفات</a>
  <a href="/inventory/plate-cost-imports/<?= (int) $run['id'] ?>/plate-cost" class="btn btn-sm"><i class="fa-solid fa-coins"></i> تكلفة الأطباق</a>
  <a href="/inventory/plate-cost-imports/<?= (int) $run['id'] ?>/prepared-cost" class="btn btn-sm"><i class="fa-solid fa-bowl-food"></i> تكلفة المواد المحضرة</a>
  <a href="/inventory/plate-cost-imports/<?= (int) $run['id'] ?>/prices" class="btn btn-sm"><i class="fa-solid fa-cash-register"></i> الأسعار والوحدات</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" style="margin-top:16px">
  <h4><i class="fa-solid fa-xmark"></i> أخطاء</h4>
  <ul><?php foreach ($errors as $e): ?><li><?= Helpers::e($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if (!empty($warnings)): ?>
<div class="alert alert-warning" style="margin-top:16px">
  <h4><i class="fa-solid fa-triangle-exclamation"></i> تحذيرات</h4>
  <ul><?php foreach ($warnings as $w): ?><li><?= Helpers::e($w) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if (!empty($dishes)): ?>
<div class="card" style="margin-top:16px">
  <h3><i class="fa-solid fa-utensils"></i> ملخص الأطباق</h3>
  <div style="overflow:auto">
    <table>
      <tr>
        <th>الطبق</th><th>الرمز</th><th>الصنف</th><th>الوحدة</th><th>تكلفة الطبق</th><th>سعر البيع</th><th>الربح</th><th>النسبة</th>
      </tr>
      <?php foreach ($dishes as $d): ?>
      <tr>
        <td><?= Helpers::e($d['dish_name']) ?></td>
        <td><?= Helpers::e($d['dish_code'] ?? '-') ?></td>
        <td><?= Helpers::e($d['dish_category'] ?? '-') ?></td>
        <td><?= Helpers::e($d['sale_unit'] ?? '-') ?></td>
        <td><?= number_format((float) $d['dish_cost'], 2) ?></td>
        <td><?= number_format((float) $d['sale_price'], 2) ?></td>
        <td><?= number_format((float) $d['gross_profit'], 2) ?></td>
        <td><?= number_format((float) $d['markup_on_cost'] * 100, 2) ?>%</td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <h3><i class="fa-solid fa-calculator"></i> مواد الأطباق</h3>
  <div style="overflow:auto">
    <table>
      <tr>
        <th>الطبق</th><th>المادة</th><th>النوع</th><th>الكمية</th><th>الوحدة</th><th>التكلفة/وحدة</th><th>الكمية الفعلية</th><th>الكلفة</th>
      </tr>
      <?php foreach ($materials as $m): ?>
      <tr>
        <td><?= Helpers::e($m['dish_name']) ?></td>
        <td><?= Helpers::e($m['material_name']) ?></td>
        <td><?= Helpers::e($m['material_type']) ?></td>
        <td><?= number_format((float) $m['base_quantity'], 2) ?></td>
        <td><?= Helpers::e($m['unit'] ?? '-') ?></td>
        <td><?= number_format((float) $m['resolved_unit_cost'], 2) ?></td>
        <td><?= number_format((float) $m['actual_consumed_quantity'], 2) ?></td>
        <td><?= number_format((float) $m['line_cost'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <h3><i class="fa-solid fa-bowl-food"></i> مكونات المحضر</h3>
  <div style="overflow:auto">
    <table>
      <tr>
        <th>المحضر</th><th>المادة</th><th>الكمية</th><th>الوحدة</th><th>الكمية الفعلية</th><th>الكلفة</th>
      </tr>
      <?php foreach ($prepared as $p): ?>
      <tr>
        <td><?= Helpers::e($p['prepared_name']) ?></td>
        <td><?= Helpers::e($p['ingredient_name']) ?></td>
        <td><?= number_format((float) $p['base_quantity'], 2) ?></td>
        <td><?= Helpers::e($p['unit'] ?? '-') ?></td>
        <td><?= number_format((float) $p['actual_consumed_quantity'], 2) ?></td>
        <td><?= number_format((float) $p['line_cost'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php endif; ?>
