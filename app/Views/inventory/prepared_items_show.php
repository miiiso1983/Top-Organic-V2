<?php use App\Core\Helpers; ?>
<style>
.bom-table { width:100%; border-collapse:collapse; font-size:13px; margin-top:12px; }
.bom-table th, .bom-table td { padding:10px; border-bottom:1px solid #e5e7eb; text-align:right; }
.bom-table th { background:#f9fafb; color:#374151; }
.cost-summary { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-top:24px; }
.cost-box { background:#f0fdf4; padding:16px; border-radius:8px; text-align:center; border:1px solid #bbf7d0; }
.cost-box .value { font-size:24px; font-weight:700; color:#166534; }
.cost-box .label { color:#6b7280; font-size:13px; margin-top:4px; }
</style>

<div class="card">
  <div class="item-card-header">
    <div>
      <div class="item-card-title"><?= Helpers::e($item['name']) ?></div>
      <div class="item-card-subtitle"><?= Helpers::e($item['category_name']) ?> · <?= Helpers::e($item['unit_name'] ?? '-') ?> · <?= Helpers::e($item['barcode'] ?? '-') ?></div>
    </div>
    <div class="item-card-badges">
      <span class="badge <?= ($item['item_type'] ?? 'raw') === 'prepared' ? 'badge-success' : 'badge-info' ?>"><?= Helpers::e($item['category_type'] ?? '-') ?></span>
      <span class="badge badge-info">التكلفة: <?= Helpers::e(number_format($item['standard_cost'] ?? $item['cost_price'] ?? 0, 2)) ?></span>
    </div>
  </div>
</div>

<div class="card">
  <h4>مكونات الوصفة (BOM)</h4>
  <?php if ($sheet): ?>
  <table class="bom-table">
    <tr>
      <th>#</th>
      <th>المادة</th>
      <th>الكمية</th>
      <th>الوحدة</th>
      <th>هدر المادة</th>
      <th>تكلفة السطر</th>
    </tr>
    <?php foreach ($lines as $idx => $line): ?>
    <tr>
      <td><?= $idx + 1 ?></td>
      <td><strong><?= Helpers::e($line['ingredient_name'] ?? '—') ?></strong></td>
      <td><?= Helpers::e(number_format($line['qty'], 4)) ?></td>
      <td><?= Helpers::e($line['line_unit_name'] ?? $line['ingredient_unit_name'] ?? '-') ?></td>
      <td><?= Helpers::e(number_format(($line['waste_rate'] ?? 0) * 100, 2)) ?>%</td>
      <td><?= Helpers::e(number_format($line['line_cost'] ?? 0, 4)) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <div class="cost-summary">
    <div class="cost-box">
      <div class="value"><?= Helpers::e(number_format($totalCost, 4)) ?></div>
      <div class="label">إجمالي تكلفة المكونات</div>
    </div>
    <div class="cost-box">
      <div class="value"><?= Helpers::e(number_format($sheet['yield_qty'] ?? 1, 2)) ?> <?= Helpers::e($item['unit_name'] ?? '') ?></div>
      <div class="label">كمية الناتج</div>
    </div>
    <div class="cost-box">
      <div class="value"><?= Helpers::e(number_format($costPerUnit, 4)) ?></div>
      <div class="label">التكلفة للوحدة</div>
    </div>
  </div>
  <?php else: ?>
  <p>لا يوجد قالب تكلفة مفعل لهذا الصنف.</p>
  <?php endif; ?>

  <div style="margin-top:24px; display:flex; gap:8px; flex-wrap:wrap;">
    <?php if ($sheet): ?>
    <a href="/inventory/cost-sheets/<?= (int)$sheet['id'] ?>/edit" class="btn btn-success"><i class="fa-solid fa-pencil"></i> تعديل BOM</a>
    <?php endif; ?>
    <?php if (\App\Core\Auth::can('inventory.manage')): ?>
    <form method="post" action="/inventory/prepared-items/<?= (int)$item['id'] ?>/recalculate">
      <button class="btn btn-warning"><i class="fa-solid fa-arrow-right-arrow-left"></i> إعادة حساب التكلفة</button>
    </form>
    <?php endif; ?>
    <a href="/inventory/prepared-items" class="btn">العودة للقائمة</a>
  </div>
</div>
