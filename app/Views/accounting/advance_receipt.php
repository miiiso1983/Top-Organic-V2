<?php use App\Core\Helpers; ?>
<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>سند صرف سلفة ADV-<?= (int)$advance['id'] ?></title>
<style>body{font-family:Tahoma,Arial,sans-serif;margin:40px;color:#172b3a}main{max-width:760px;margin:auto;border:1px solid #aaa;padding:30px}h1{text-align:center}table{width:100%;border-collapse:collapse}td{padding:14px;border-bottom:1px solid #ddd}.stamp{color:#176441;text-align:center;font-size:26px;font-weight:bold}.signatures{display:flex;justify-content:space-between;margin-top:70px}@media print{button{display:none}body{margin:0}}</style>
<main><h1>سند صرف سلفة</h1><p class="stamp">تم الصرف</p><table>
<tr><td>رقم السند</td><td>ADV-<?= (int)$advance['id'] ?></td></tr>
<tr><td>الموظف</td><td><?= Helpers::e($advance['full_name']) ?> — <?= Helpers::e($advance['emp_code']) ?></td></tr>
<tr><td>المبلغ المستلم</td><td><?= Helpers::money($advance['amount']) ?></td></tr>
<tr><td>تاريخ التسليم</td><td><?= Helpers::e($advance['paid_at']) ?></td></tr>
<tr><td>طريقة التسليم</td><td><?= $advance['payment_method']==='bank' ? 'بنك' : 'نقدي' ?></td></tr>
<tr><td>مسؤول التسليم</td><td><?= Helpers::e($advance['paid_by_name'] ?? '') ?></td></tr>
<tr><td>السبب</td><td><?= Helpers::e($advance['reason'] ?? '') ?></td></tr>
</table><div class="signatures"><p>توقيع المستلم: __________________</p><p>توقيع مسؤول التسليم: __________________</p></div>
<button onclick="window.print()">طباعة سند الصرف</button></main></html>
