<?php use App\Core\{Helpers, App}; ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>فاتورة <?= Helpers::e($order['order_no']) ?></title>
  <link rel="stylesheet" href="/assets/css/receipt.css">
</head>
<body class="width-<?= (int)$settings['receipt_width'] ?>mm">
  <div class="receipt">
    <div class="header">
      <?php if ($settings['show_logo']): ?>
        <div class="brand"><?= nl2br(Helpers::e($settings['receipt_header'])) ?></div>
      <?php endif; ?>
      <?php if ($settings['show_tax'] && $settings['tax_number']): ?>
        <div class="tax">رقم الضريبة: <?= Helpers::e($settings['tax_number']) ?></div>
      <?php endif; ?>
    </div>

    <div class="meta">
      <div><span>رقم الفاتورة:</span> <strong><?= Helpers::e($order['order_no']) ?></strong></div>
      <div><span>التاريخ:</span> <?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></div>
      <?php if ($order['table_name']): ?>
        <div><span>الطاولة:</span> طاولة <?= Helpers::cleanName($order['table_name']) ?></div>
      <?php endif; ?>
      <div><span>نوع الطلب:</span>
        <?php
        $types = ['dine_in'=>'صالة','takeaway'=>'سفري','delivery'=>'توصيل','online'=>'إلكتروني'];
        echo $types[$order['order_type']] ?? $order['order_type'];
        ?>
      </div>
      <?php if ($settings['cashier_name'] && $order['cashier']): ?>
        <div><span>الكاشير:</span> <?= Helpers::e($order['cashier']) ?></div>
      <?php endif; ?>
    </div>

    <div class="divider">──────────────</div>

    <table class="items">
      <thead>
        <tr><th>الصنف</th><th class="num">الع</th><th class="num">السعر</th><th class="num">المجموع</th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
        <tr>
          <td><?= Helpers::e($it['name']) ?></td>
          <td class="num"><?= number_format((float)$it['qty'], ($it['qty'] == (int)$it['qty'] ? 0 : 2)) ?></td>
          <td class="num"><?= number_format((float)$it['unit_price']) ?></td>
          <td class="num"><?= number_format((float)$it['total']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="divider">──────────────</div>

    <table class="totals">
      <tr><td>المجموع</td><td class="num"><?= number_format((float)$order['subtotal']) ?> <?= Helpers::e(App::config('currency')) ?></td></tr>
      <?php if ($order['discount'] > 0): ?>
      <tr><td>الخصم</td><td class="num">- <?= number_format((float)$order['discount']) ?> <?= Helpers::e(App::config('currency')) ?></td></tr>
      <?php endif; ?>
      <?php if ($order['delivery_fee'] > 0): ?>
      <tr><td>أجر التوصيل</td><td class="num">+ <?= number_format((float)$order['delivery_fee']) ?> <?= Helpers::e(App::config('currency')) ?></td></tr>
      <?php endif; ?>
      <tr class="grand"><td>الإجمالي</td><td class="num"><?= number_format((float)$order['total']) ?> <?= Helpers::e(App::config('currency')) ?></td></tr>
      <?php if ($payments): ?>
        <?php foreach ($payments as $p): ?>
        <tr><td>المدفوع (<?= $p['method'] === 'cash' ? 'نقدي' : ($p['method'] === 'card' ? 'بطاقة' : 'إلكتروني') ?>)</td><td class="num"><?= number_format((float)$p['amount']) ?> <?= Helpers::e(App::config('currency')) ?></td></tr>
        <?php endforeach; ?>
        <?php
        $paid = array_sum(array_column($payments, 'amount'));
        $remain = (float)$order['total'] - $paid;
        if ($remain > 0): ?>
        <tr><td>المتبقي</td><td class="num"><?= number_format($remain) ?> <?= Helpers::e(App::config('currency')) ?></td></tr>
        <?php endif; ?>
      <?php endif; ?>
    </table>

    <div class="divider">──────────────</div>

    <div class="footer">
      <?= nl2br(Helpers::e($settings['receipt_footer'])) ?>
    </div>
  </div>

  <?php if ($settings['auto_print']): ?>
  <script>
    window.onload = function() {
      setTimeout(function() { window.print(); }, 300);
    };
  </script>
  <?php endif; ?>
</body>
</html>
