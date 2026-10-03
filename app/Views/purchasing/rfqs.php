<?php use App\Core\Helpers; ?>
<style>
.mr-table { width: 100%; border-collapse: collapse; }
.mr-table th, .mr-table td { border: 1px solid #e5e7eb; padding: 10px; text-align: right; }
.mr-table th { background: #f3f4f6; }
</style>
<h2>طلبات عروض الأسعار (RFQ)</h2>
<table class="mr-table">
  <tr><th>الرقم</th><th>طلب الشراء</th><th>الحالة</th><th>البنود</th><th>التاريخ</th><th>إجراء</th></tr>
  <?php foreach ($rfqs as $r): ?>
  <tr>
    <td><?= Helpers::e($r['rfq_no']) ?></td>
    <td><?= Helpers::e($r['pr_no']) ?></td>
    <td><?= Helpers::e($r['status']) ?></td>
    <td><?= (int)$r['items_count'] ?></td>
    <td><?= $r['created_at'] ?></td>
    <td><a href="/purchasing/rfqs/<?= $r['id'] ?>" class="btn btn-sm btn-primary">عرض</a></td>
  </tr>
  <?php endforeach; ?>
</table>
