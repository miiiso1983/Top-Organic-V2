<?php use App\Core\Helpers; ?>
<?php $statusLabels = ['draft'=>'مسودة','sent_to_accounting'=>'مرسلة للحسابات','matched'=>'مطابقة','approved'=>'معتمدة','paid'=>'مدفوعة'];
      $payLabels = ['unpaid'=>'غير مدفوعة','partial'=>'جزئية','paid'=>'مدفوعة']; ?>
<style>
.mr-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
.mr-table { width: 100%; border-collapse: collapse; }
.mr-table th, .mr-table td { border: 1px solid #e5e7eb; padding: 10px; text-align: right; }
.mr-table th { background: #f3f4f6; }
</style>

<h2>فاتورة شراء <?= Helpers::e($invoice['invoice_no']) ?></h2>
<div class="mr-card">
  <p><strong>المورد:</strong> <?= Helpers::e($invoice['supplier_name']) ?></p>
  <p><strong>أمر الشراء:</strong> <?= Helpers::e($invoice['po_no']) ?></p>
  <p><strong>سند الاستلام:</strong> <?= Helpers::e($invoice['receipt_no']) ?></p>
  <p><strong>الحالة:</strong> <?= $statusLabels[$invoice['status']] ?? $invoice['status'] ?></p>
  <p><strong>حالة الدفع:</strong> <?= $payLabels[$invoice['payment_status']] ?? $invoice['payment_status'] ?></p>
  <p><strong>المبلغ:</strong> <?= Helpers::money($invoice['total_amount']) ?></p>
  <p><strong>المدفوع:</strong> <?= Helpers::money($invoice['paid_amount']) ?></p>
  <p><strong>المتبقي:</strong> <?= Helpers::money($invoice['remaining_amount']) ?></p>
</div>

<div class="mr-card">
  <h3>البنود</h3>
  <table class="mr-table">
    <tr><th>المادة</th><th>الكمية</th><th>سعر الوحدة</th><th>المجموع</th></tr>
    <?php foreach ($items as $it): ?>
    <tr>
      <td><?= Helpers::e($it['item_name']) ?></td>
      <td><?= number_format((float)$it['qty'], 2) ?></td>
      <td><?= Helpers::money($it['unit_price']) ?></td>
      <td><?= Helpers::money($it['total']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if ($invoice['status'] !== 'paid' && Auth::can('finance.manage')): ?>
<div class="mr-card">
  <h3>تسجيل دفعة</h3>
  <form method="POST" action="/purchasing/invoices/<?= $invoice['id'] ?>/payment" class="form-inline">
    <div class="form-row"><label>المبلغ</label><input type="number" name="amount" step="0.01" min="0.01" max="<?= $invoice['remaining_amount'] ?>" required></div>
    <div class="form-row"><label>الطريقة</label>
      <select name="method">
        <option value="cash">نقد</option>
        <option value="transfer">تحويل</option>
        <option value="card">بطاقة</option>
      </select>
    </div>
    <div class="form-row"><label>الرقم المرجعي</label><input type="text" name="reference" placeholder="رقم العملية"></div>
    <div class="form-row"><label>ملاحظات</label><input type="text" name="notes" placeholder="ملاحظات الدفعة"></div>
    <button class="btn btn-success">تسجيل دفعة</button>
  </form>
</div>
<?php endif; ?>

<div class="mr-card">
  <h3>المستندات المؤرشفة</h3>
  <table class="mr-table">
    <tr><th>نوع المستند</th><th>المسار</th><th>ملاحظات</th><th>التاريخ</th></tr>
    <?php foreach ($documents as $d): ?>
    <tr><td><?= Helpers::e($d['document_type']) ?></td><td><?= Helpers::e($d['file_path']) ?></td><td><?= Helpers::e($d['notes'] ?? '-') ?></td><td><?= $d['created_at'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$documents): ?><tr><td colspan="4">لا توجد مستندات مؤرشفة</td></tr><?php endif; ?>
  </table>
  <?php if (Auth::can('purchasing.manage')): ?>
  <form method="POST" action="/purchasing/invoices/<?= $invoice['id'] ?>/archive" class="form-inline" style="margin-top:10px">
    <div class="form-row"><label>نوع المستند</label>
      <select name="document_type">
        <option value="invoice">فاتورة</option>
        <option value="receipt">سند استلام</option>
        <option value="payment_proof">إثبات دفع</option>
      </select>
    </div>
    <div class="form-row"><label>مسار الملف</label><input type="text" name="file_path" placeholder="/uploads/invoices/..." required></div>
    <div class="form-row"><label>ملاحظات</label><input type="text" name="notes" placeholder="ملاحظات"></div>
    <button class="btn btn-primary">أرشفة</button>
  </form>
  <?php endif; ?>
</div>
