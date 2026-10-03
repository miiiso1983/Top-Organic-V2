<?php use App\Core\Helpers; ?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head><meta charset="utf-8"><title>ميزان المراجعة</title>
<link rel="stylesheet" href="/assets/css/app.css">
<style>body{padding:20px} th{text-align:right}</style>
</head>
<body>
<h3>ميزان المراجعة حتى <?= Helpers::e($to) ?></h3>
<table>
  <tr><th>الكود</th><th>الاسم</th><th>النوع</th><th>المدين</th><th>الدائن</th><th>الرصيد</th></tr>
  <?php
    $tdr = $tcr = $tbal = 0;
    foreach ($accounts as $a):
      $bal = $a['balance'];
      $dr = in_array($a['type'], ['asset','expense']) ? max(0, $bal) : max(0, -$bal);
      $cr = in_array($a['type'], ['asset','expense']) ? max(0, -$bal) : max(0, $bal);
      $tdr += $dr; $tcr += $cr;
  ?>
  <tr>
    <td><?= Helpers::e($a['code']) ?></td>
    <td><?= Helpers::e($a['name']) ?></td>
    <td><?= Helpers::e($a['type']) ?></td>
    <td><?= Helpers::money($dr) ?></td>
    <td><?= Helpers::money($cr) ?></td>
    <td><?= Helpers::money($bal) ?></td>
  </tr>
  <?php endforeach; ?>
  <tr style="font-weight:bold"><td colspan="3">المجموع</td><td><?= Helpers::money($tdr) ?></td><td><?= Helpers::money($tcr) ?></td><td></td></tr>
</table>
<p style="margin-top:20px">
<a href="?to=<?= Helpers::e($to) ?>&format=excel" class="btn btn-sm">تصدير Excel</a>
<a href="?to=<?= Helpers::e($to) ?>&format=pdf" class="btn btn-sm">تصدير PDF</a>
<button onclick="window.print()" class="btn btn-sm">طباعة</button>
</p>
</body>
</html>
