<?php use App\Core\Helpers; ?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>بون <?= $kitchen ? Helpers::e($kitchen['name']) : 'المطبخ المركزي' ?></title>
<link rel="stylesheet" href="/assets/css/receipt.css">
<style>
body { font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif; font-size: 14px; margin: 0; padding: 16px; }
.receipt { max-width: 300px; margin: 0 auto; border: 1px dashed #999; padding: 12px; }
h2 { margin: 0 0 8px; font-size: 18px; text-align: center; }
.meta { font-size: 12px; color: #555; margin-bottom: 10px; text-align: center; }
.order { border-top: 1px dashed #ccc; padding-top: 8px; margin-top: 8px; }
.order-title { font-weight: bold; font-size: 13px; margin-bottom: 4px; }
.order-location { font-size: 11px; color: #666; margin-bottom: 6px; }
.item { display: flex; justify-content: space-between; margin-bottom: 4px; border-bottom: 1px dashed #eee; padding-bottom: 4px; }
.item-name { flex: 1; }
.item-qty { font-weight: bold; margin-inline-start: 8px; }
.notes { font-size: 11px; color: #92400e; margin-top: 2px; font-style: italic; }
.printer { font-size: 11px; color: #666; text-align: center; margin-bottom: 8px; }
.footer { margin-top: 12px; font-size: 11px; color: #666; text-align: center; }
@media print { body { padding: 0; } .no-print { display: none; } }
</style>
</head>
<body class="width-<?= (int)$settings['receipt_width'] ?>mm">
<div class="receipt">
  <h2><?= $kitchen ? Helpers::e($kitchen['name']) : 'المطبخ المركزي' ?></h2>
  <?php if ($kitchen && $kitchen['printer_name']): ?>
  <div class="printer"><i class="fa-solid fa-print"></i> <?= Helpers::e($kitchen['printer_name']) ?></div>
  <?php elseif (!$kitchen && $settings['kitchen_printer']): ?>
  <div class="printer"><i class="fa-solid fa-print"></i> <?= Helpers::e($settings['kitchen_printer']) ?></div>
  <?php endif; ?>

  <div class="meta">
    <div><strong>رقم الطلب:</strong> <?= Helpers::e($order['order_no']) ?></div>
    <div><?= date('Y/m/d H:i', strtotime($order['created_at'])) ?></div>
    <?php
    $parts = [];
    if ($order['table_hall']) $parts[] = $order['table_hall'];
    if ($order['table_floor'] && !$order['table_hall']) $parts[] = 'طابق ' . $order['table_floor'];
    if ($order['table_name']) $parts[] = 'طاولة ' . $order['table_name'];
    if ($order['order_type'] === 'takeaway') $parts[] = 'سفري';
    if ($order['order_type'] === 'delivery') $parts[] = 'توصيل';
    if ($order['order_type'] === 'online') $parts[] = 'إلكتروني';
    echo '<div>' . Helpers::e(implode(' – ', $parts) ?: 'داخلي') . '</div>';
    ?>
    <?php if ($order['notes']): ?>
    <div style="margin-top:4px;color:#92400e"><i class="fa-solid fa-pencil"></i> <?= Helpers::e($order['notes']) ?></div>
    <?php endif; ?>
  </div>

  <?php foreach ($items as $it): ?>
  <div class="item">
    <span class="item-name"><?= Helpers::e($it['name']) ?></span>
    <span class="item-qty">×<?= number_format((float)$it['qty'], ($it['qty'] == (int)$it['qty'] ? 0 : 2)) ?></span>
  </div>
  <?php if ($it['notes']): ?><div class="notes"><i class="fa-solid fa-pencil"></i> <?= Helpers::e($it['notes']) ?></div><?php endif; ?>
  <?php endforeach; ?>

  <?php if (!$items): ?>
  <div style="text-align:center">لا توجد أصناف للطباعة</div>
  <?php endif; ?>

  <div class="footer no-print">
    <button onclick="window.print()">طباعة</button>
  </div>
</div>
<script>window.onload = () => { window.print(); };</script>
</body>
</html>
