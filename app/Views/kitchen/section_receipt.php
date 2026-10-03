<?php use App\Core\Helpers; ?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>بون <?= Helpers::e($section['name']) ?></title>
<style>
body { font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif; font-size: 14px; margin: 0; padding: 16px; }
.receipt { max-width: 300px; margin: 0 auto; border: 1px dashed #999; padding: 12px; }
h2 { margin: 0 0 8px; font-size: 18px; text-align: center; }
.meta { font-size: 12px; color: #555; margin-bottom: 10px; text-align: center; }
.order { border-top: 1px dashed #ccc; padding-top: 8px; margin-top: 8px; }
.order-title { font-weight: bold; font-size: 13px; margin-bottom: 4px; }
.order-location { font-size: 11px; color: #666; margin-bottom: 6px; }
.item { display: flex; justify-content: space-between; margin-bottom: 4px; }
.item-name { flex: 1; }
.item-qty { font-weight: bold; }
.notes { font-size: 11px; color: #92400e; margin-top: 2px; font-style: italic; }
.footer { margin-top: 12px; font-size: 11px; color: #666; text-align: center; }
@media print { body { padding: 0; } .no-print { display: none; } }
</style>
</head>
<body>
<div class="receipt">
  <h2><?= Helpers::e($section['name']) ?></h2>
  <div class="meta"><?= date('Y/m/d H:i') ?></div>

  <?php foreach ($orders as $o): ?>
  <div class="order">
    <div class="order-title">طلب: <?= Helpers::e($o['order_no']) ?></div>
    <div class="order-location">
      <?php
      $parts = [];
      if ($o['table_hall']) $parts[] = $o['table_hall'];
      if ($o['table_floor'] && !$o['table_hall']) $parts[] = 'طابق ' . $o['table_floor'];
      if ($o['table_name']) $parts[] = 'طاولة ' . $o['table_name'];
      if ($o['order_type'] === 'takeaway') $parts[] = 'سفري';
      if ($o['order_type'] === 'delivery') $parts[] = 'توصيل';
      if ($o['order_type'] === 'online') $parts[] = 'إلكتروني';
      echo Helpers::e(implode(' – ', $parts) ?: 'داخلي');
      ?>
    </div>
    <?php foreach ($o['items'] as $it): ?>
    <div class="item">
      <span class="item-name"><?= Helpers::e($it['name']) ?></span>
      <span class="item-qty">×<?= (float)$it['qty'] ?></span>
    </div>
    <?php if ($it['notes']): ?><div class="notes"><i class="fa-solid fa-pencil"></i> <?= Helpers::e($it['notes']) ?></div><?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>

  <?php if (!$orders): ?>
  <div class="order" style="text-align:center">لا توجد طلبات حالياً</div>
  <?php endif; ?>

  <div class="footer no-print">
    <button onclick="window.print()">طباعة</button>
  </div>
</div>
<script>window.onload = () => { window.print(); };</script>
</body>
</html>
