<?php use App\Core\Helpers; ?>
<?php $typeLabels = ['asset'=>'أصول','liability'=>'التزامات','equity'=>'حقوق ملكية','revenue'=>'إيرادات','expense'=>'مصروفات']; ?>
<div class="stats-grid">
  <div class="stat-card"><div class="stat-value" style="color:#16a34a"><?= Helpers::money($monthSales) ?></div><div class="stat-label">إيرادات الشهر (مبيعات)</div></div>
  <div class="stat-card"><div class="stat-value" style="color:#dc2626"><?= Helpers::money($monthExpenses) ?></div><div class="stat-label">مصروفات الشهر</div></div>
  <div class="stat-card"><div class="stat-value"><?= Helpers::money($monthSales - $monthExpenses) ?></div><div class="stat-label">الصافي التقريبي</div></div>
</div>

<div class="card">
  <h3>شجرة الحسابات وأرصدتها</h3>
  <table>
    <tr><th>الكود</th><th>الحساب</th><th>النوع</th><th>مدين</th><th>دائن</th><th>الرصيد</th></tr>
    <?php foreach ($accounts as $a): ?>
    <?php $bal = (float)$a['total_debit'] - (float)$a['total_credit']; ?>
    <tr>
      <td><?= Helpers::e($a['code']) ?></td>
      <td><?= Helpers::e($a['name']) ?></td>
      <td><span class="badge badge-info"><?= $typeLabels[$a['type']] ?? $a['type'] ?></span></td>
      <td><?= Helpers::money($a['total_debit']) ?></td>
      <td><?= Helpers::money($a['total_credit']) ?></td>
      <td style="font-weight:bold;color:<?= $bal >= 0 ? '#16a34a' : '#dc2626' ?>"><?= Helpers::money(abs($bal)) ?> <?= $bal >= 0 ? 'مدين' : 'دائن' ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$accounts): ?><tr><td colspan="6">لا توجد حسابات — شغّل ملف البيانات الأولية</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h3>آخر القيود المحاسبية</h3>
  <table>
    <tr><th>الرقم</th><th>التاريخ</th><th>البيان</th><th>مركز الكلفة</th></tr>
    <?php foreach ($entries as $je): ?>
    <tr>
      <td><?= Helpers::e($je['entry_no']) ?></td>
      <td><?= $je['entry_date'] ?></td>
      <td><?= Helpers::e($je['description'] ?? '-') ?></td>
      <td><?= Helpers::e($je['cost_center_name'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$entries): ?><tr><td colspan="4">لا توجد قيود بعد</td></tr><?php endif; ?>
  </table>
</div>
