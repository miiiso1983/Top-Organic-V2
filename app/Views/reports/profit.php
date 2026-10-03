<?php use App\Core\Helpers; ?>
<?php
$grossProfit = $sales - $cogs;
$netProfit = $grossProfit - $expenses - $payroll;
?>
<div class="card">
  <form method="get" action="/reports/profit" class="form-inline">
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">عرض</button>
    <a href="?from=<?= Helpers::e($from) ?>&to=<?= Helpers::e($to) ?>&format=excel" class="btn btn-secondary">تصدير Excel</a>
    <a href="?from=<?= Helpers::e($from) ?>&to=<?= Helpers::e($to) ?>&format=pdf" class="btn btn-secondary">تصدير PDF</a>
    <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i> طباعة</button>
  </form>
</div>

<div class="card">
  <h3>قائمة الأرباح والخسائر (<?= Helpers::e($from) ?> → <?= Helpers::e($to) ?>)</h3>
  <table>
    <tr><td>إجمالي المبيعات</td><td style="color:#16a34a;font-weight:bold"><?= Helpers::money($sales) ?></td></tr>
    <tr><td>− كلفة البضاعة المباعة (حسب الوصفات)</td><td style="color:#dc2626"><?= Helpers::money($cogs) ?></td></tr>
    <tr style="font-weight:bold;background:#f9fafb"><td>= مجمل الربح</td><td><?= Helpers::money($grossProfit) ?></td></tr>
    <tr><td>− المصروفات التشغيلية المعتمدة</td><td style="color:#dc2626"><?= Helpers::money($expenses) ?></td></tr>
    <tr><td>− الرواتب المدفوعة</td><td style="color:#dc2626"><?= Helpers::money($payroll) ?></td></tr>
    <tr style="font-weight:bold;font-size:1.2em;background:<?= $netProfit >= 0 ? '#f0fdf4' : '#fef2f2' ?>">
      <td>= صافي الربح</td>
      <td style="color:<?= $netProfit >= 0 ? '#16a34a' : '#dc2626' ?>"><?= Helpers::money($netProfit) ?></td>
    </tr>
  </table>
  <?php if ($sales > 0): ?>
  <p style="margin-top:10px;color:#6b7280">هامش مجمل الربح: <?= round($grossProfit / $sales * 100, 1) ?>% —
     هامش صافي الربح: <?= round($netProfit / $sales * 100, 1) ?>%</p>
  <?php endif; ?>
</div>
