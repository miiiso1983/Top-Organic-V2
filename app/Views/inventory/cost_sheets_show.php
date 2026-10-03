<?php use App\Core\Helpers; ?>
<div class="card">
  <div class="form-inline" style="justify-content:space-between; align-items:center;">
    <h3 style="margin:0;">قالب تكلفة: <?= Helpers::e($item['name']) ?></h3>
    <div>
      <?php if (\App\Core\Auth::can('inventory.manage')): ?>
      <a href="/inventory/cost-sheets/<?= (int)$item['id'] ?>/edit" class="btn btn-success">تحرير</a>
      <?php endif; ?>
      <a href="/inventory/cost-sheets/<?= (int)$item['id'] ?>/export-pdf" class="btn btn-secondary">تصدير PDF</a>
      <a href="/inventory/cost-sheets" class="btn btn-sm">عودة</a>
    </div>
  </div>
</div>

<div class="card">
  <h3>ملخص التكلفة</h3>
  <div class="grid grid-4">
    <div><strong>الوحدة:</strong> <?= Helpers::e($sheet['yield_unit_name'] ?? $item['unit_name'] ?? '-') ?></div>
    <div><strong>كمية الناتج:</strong> <?= (float)($sheet['yield_qty'] ?? 1) ?></div>
    <div><strong>هدر القسم:</strong> <?= number_format((float)($sheet['waste_rate'] ?? 0) * 100, 2) ?>%</div>
    <div><strong>إجمالي الكلفة:</strong> <?= Helpers::money($totalCost) ?></div>
    <div><strong>كلفة الوحدة:</strong> <?= Helpers::money($costPerUnit) ?></div>
    <div><strong>سعر البيع:</strong> <?= Helpers::money($sheet['sale_price'] ?? 0) ?></div>
    <div><strong>الربح الإجمالي:</strong> <span style="color:<?= $profit >= 0 ? '#16a34a' : '#dc2626' ?>"><?= Helpers::money($profit) ?></span></div>
    <div><strong>نسبة تكلفة الغذاء:</strong> <?= number_format(($foodCostPct ?? 0) * 100, 2) ?>%</div>
    <div><strong>هامش الربح من البيع:</strong> <span style="color:<?= $profit >= 0 ? '#16a34a' : '#dc2626' ?>"><?= number_format(($grossMarginPct ?? 0) * 100, 2) ?>%</span></div>
    <div><strong>الربح على الكلفة:</strong> <span style="color:<?= $profit >= 0 ? '#16a34a' : '#dc2626' ?>"><?= number_format($marginPct * 100, 2) ?>%</span></div>
    <div><strong>الكلفة المحدثة:</strong> <?= Helpers::money($item['cost_price'] ?? 0) ?></div>
  </div>
  <?php if ($sheet['notes']): ?>
  <div style="margin-top:12px;"><strong>ملاحظات:</strong> <?= nl2br(Helpers::e($sheet['notes']), false) ?></div>
  <?php endif; ?>
</div>

<div class="card">
  <h3>المكونات والأوزان</h3>
  <table>
    <tr>
      <th>#</th>
      <th>المادة</th>
      <th>الكمية المدخلة</th>
      <th>وحدة المدخل</th>
      <th>هدر المادة</th>
      <th>زيادة الناتج</th>
      <th>الكمية الصافية</th>
      <th>الكمية الإجمالية</th>
      <th>كلفة الوحدة (أساسية)</th>
      <th>الكلفة</th>
    </tr>
    <?php foreach ($lines as $idx => $line): ?>
    <tr>
      <td><?= $idx + 1 ?></td>
      <td><?= Helpers::e($line['ingredient_name']) ?></td>
      <td><?= (float)$line['qty'] ?></td>
      <td><?= Helpers::e($line['line_unit_name'] ?? '-') ?></td>
      <td><?= number_format((float)$line['waste_rate'] * 100, 2) ?>%</td>
      <td><?= number_format((float)($line['yield_gain_rate'] ?? 0) * 100, 2) ?>%</td>
      <td><?= number_format((float)($line['net_qty'] ?? 0), 4) ?> <?= Helpers::e($line['line_unit_name'] ?? '-') ?></td>
      <td><?= number_format((float)($line['gross_qty'] ?? 0), 4) ?> <?= Helpers::e($line['line_unit_name'] ?? '-') ?></td>
      <td><?= Helpers::money($line['unit_cost'] ?? $line['ingredient_cost'] ?? 0) ?></td>
      <td><?= Helpers::money($line['line_cost'] ?? 0) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$lines): ?>
    <tr><td colspan="10">لا توجد مكونات</td></tr>
    <?php endif; ?>
  </table>
</div>
