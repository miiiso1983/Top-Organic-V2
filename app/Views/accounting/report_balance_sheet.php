<?php use App\Core\Helpers; ?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head><meta charset="utf-8"><title>الميزانية العمومية</title>
<link rel="stylesheet" href="/assets/css/app.css">
<style>body{padding:20px} th{text-align:right}</style>
</head>
<body>
<h3>الميزانية العمومية حتى <?= Helpers::e($to) ?></h3>
<table>
  <tr><th>البند</th><th>المبلغ</th></tr>
  <tr><td>الأصول</td><td><?= Helpers::money($assets) ?></td></tr>
  <tr><td>الخصوم</td><td>(<?= Helpers::money($liabilities) ?>)</td></tr>
  <tr><td>حقوق الملكية</td><td>(<?= Helpers::money($equity) ?>)</td></tr>
  <tr style="font-weight:bold;font-size:1.1em"><td>مجموع الخصوم + حقوق الملكية</td><td><?= Helpers::money($liabilities + $equity) ?></td></tr>
</table>
<p>فحص الميزانية: <?= Helpers::money($assets - ($liabilities + $equity)) ?></p>
<p style="margin-top:20px">
<a href="?to=<?= Helpers::e($to) ?>&format=excel" class="btn btn-sm">تصدير Excel</a>
<a href="?to=<?= Helpers::e($to) ?>&format=pdf" class="btn btn-sm">تصدير PDF</a>
<button onclick="window.print()" class="btn btn-sm">طباعة</button>
</p>
</body>
</html>
