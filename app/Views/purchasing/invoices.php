<?php use App\Core\Helpers; ?>
<?php $statusLabels = ['draft'=>'مسودة','sent_to_accounting'=>'مرسلة للحسابات','matched'=>'مطابقة','approved'=>'معتمدة','paid'=>'مدفوعة'];
      $payLabels = ['unpaid'=>'غير مدفوعة','partial'=>'جزئية','paid'=>'مدفوعة']; ?>
<style>
.mr-table { width: 100%; border-collapse: collapse; }
.mr-table th, .mr-table td { border: 1px solid #e5e7eb; padding: 10px; text-align: right; }
.mr-table th { background: #f3f4f6; }
</style>

<h2>فواتير الشراء</h2>
<table class="mr-table">
  <tr><th>الرقم</th><th>أمر الشراء</th><th>المورد</th><th>المبلغ</th><th>الحالة</th><th>الدفع</th><th>المتبقي</th><th>التاريخ</th><th>إجراء</th></tr>
  <?php foreach ($invoices as $inv): ?>
  <tr>
    <td><?= Helpers::e($inv['invoice_no']) ?></td>
    <td><?= Helpers::e($inv['po_no']) ?></td>
    <td><?= Helpers::e($inv['supplier_name']) ?></td>
    <td><?= Helpers::money($inv['total_amount']) ?></td>
    <td><span class="badge badge-<?= $inv['status']==='paid'?'success':($inv['status']==='sent_to_accounting'?'info':'warning') ?>"><?= $statusLabels[$inv['status']] ?? $inv['status'] ?></span></td>
    <td><span class="badge badge-<?= $inv['payment_status']==='paid'?'success':($inv['payment_status']==='partial'?'warning':'danger') ?>"><?= $payLabels[$inv['payment_status']] ?? $inv['payment_status'] ?></span></td>
    <td><?= Helpers::money($inv['remaining_amount']) ?></td>
    <td><?= date('m/d H:i', strtotime($inv['created_at'])) ?></td>
    <td><a href="/purchasing/invoices/<?= $inv['id'] ?>" class="btn btn-sm btn-primary">عرض</a></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$invoices): ?><tr><td colspan="9">لا توجد فواتير شراء</td></tr><?php endif; ?>
</table>
