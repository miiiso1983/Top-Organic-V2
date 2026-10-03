<?php use App\Core\Helpers; ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h3>تقييم المخزون الحالي</h3>
    <div>
      <a href="?format=excel" class="btn btn-sm">تصدير Excel</a>
      <a href="?format=pdf" class="btn btn-sm">تصدير PDF</a>
      <button type="button" class="btn btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i> طباعة</button>
    </div>
  </div>
  <table>
    <tr><th>المادة</th><th>الوحدة</th><th>الرصيد</th><th>سعر الكلفة</th><th>القيمة</th></tr>
    <?php $totalValue = 0; foreach ($valuation as $v): $totalValue += (float)$v['value']; ?>
    <tr>
      <td><?= Helpers::e($v['name']) ?></td>
      <td><?= Helpers::e($v['unit_name']) ?></td>
      <td><?= (float)$v['qty'] ?></td>
      <td><?= Helpers::money($v['cost_price']) ?></td>
      <td><?= Helpers::money($v['value']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if ($valuation): ?>
    <tr style="font-weight:bold"><td colspan="4">إجمالي قيمة المخزون</td><td><?= Helpers::money($totalValue) ?></td></tr>
    <?php else: ?><tr><td colspan="5">لا توجد أرصدة</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h3><i class="fa-solid fa-triangle-exclamation"></i> مواد قريبة الانتهاء (خلال 30 يوماً)</h3>
  <table>
    <tr><th>المادة</th><th>الكمية</th><th>تاريخ الانتهاء</th></tr>
    <?php foreach ($expiring as $x): ?>
    <tr>
      <td><?= Helpers::e($x['name']) ?></td>
      <td><?= (float)$x['qty'] ?></td>
      <td style="color:<?= $x['expiry_date'] < date('Y-m-d') ? '#dc2626' : '#d97706' ?>"><?= $x['expiry_date'] ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$expiring): ?><tr><td colspan="3">لا توجد مواد قريبة الانتهاء <i class="fa-solid fa-check"></i></td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h3>سجل الهدر لهذا الشهر</h3>
  <table>
    <tr><th>المادة</th><th>الكمية</th><th>السبب</th><th>التاريخ</th></tr>
    <?php foreach ($waste as $w): ?>
    <tr>
      <td><?= Helpers::e($w['item_name']) ?></td>
      <td><?= (float)$w['qty'] ?></td>
      <td><?= Helpers::e($w['reason'] ?? '-') ?></td>
      <td><?= date('m/d', strtotime($w['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$waste): ?><tr><td colspan="4">لا يوجد هدر مسجل هذا الشهر <i class="fa-solid fa-check"></i></td></tr><?php endif; ?>
  </table>
</div>
