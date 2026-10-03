<?php use App\Core\Helpers; ?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head><meta charset="utf-8"><title>قائمة الدخل</title>
<link rel="stylesheet" href="/assets/css/app.css">
<style>body{padding:20px} th{text-align:right}</style>
</head>
<body>
<h3>قائمة الدخل — من <?= Helpers::e($from) ?> إلى <?= Helpers::e($to) ?></h3>
<table>
  <tr><th>البند</th><th>المبلغ</th></tr>
  <tr><td>إيرادات المبيعات</td><td><?= Helpers::money($revenue) ?></td></tr>
  <tr><td>(-) تكلفة البضاعة المباعة</td><td>(<?= Helpers::money($cogs) ?>)</td></tr>
  <tr style="font-weight:bold"><td>إجمالي الربح الأولي</td><td><?= Helpers::money($revenue - $cogs) ?></td></tr>
  <tr><td>(-) المصروفات التشغيلية</td><td>(<?= Helpers::money($expenses) ?>)</td></tr>
  <tr style="font-weight:bold;font-size:1.1em"><td>صافي الدخل</td><td><?= Helpers::money($revenue - $cogs - $expenses) ?></td></tr>
</table>
<p style="margin-top:20px">
<a href="?from=<?= Helpers::e($from) ?>&to=<?= Helpers::e($to) ?>&format=excel" class="btn btn-sm">تصدير Excel</a>
<a href="?from=<?= Helpers::e($from) ?>&to=<?= Helpers::e($to) ?>&format=pdf" class="btn btn-sm">تصدير PDF</a>
<button onclick="window.print()" class="btn btn-sm">طباعة</button>
</p>
</body>
</html>
