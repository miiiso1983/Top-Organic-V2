<?php use App\Core\Helpers; ?>
<?php $statusLabels = ['draft'=>'مسودة','sent'=>'مرسل للمورد','partially_received'=>'استلام جزئي','received'=>'مستلم','cancelled'=>'ملغى'];
      $payLabels = ['unpaid'=>'غير مدفوع','partial'=>'دفع جزئي','paid'=>'مدفوع']; ?>
<div class="card">
  <h3>أمر شراء جديد</h3>
  <form method="post" action="/purchasing/orders" class="form-inline">
    <div class="form-row"><label>المورد</label>
      <select name="supplier_id" required>
        <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= Helpers::e($s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>مرتبط بطلب شراء</label>
      <select name="request_id">
        <option value="">— بدون —</option>
        <?php foreach ($approvedRequests as $r): ?><option value="<?= $r['id'] ?>"><?= Helpers::e($r['request_no']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>المادة</label>
      <select name="item_id" id="po-item" required>
        <?php foreach ($items as $it): ?>
          <option value="<?= $it['id'] ?>" data-cost="<?= $it['cost_price'] ?>"><?= Helpers::e($it['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الكمية</label><input type="number" name="qty" step="0.001" min="0.001" required></div>
    <div class="form-row"><label>سعر الوحدة</label><input type="number" name="unit_price" id="po-price" step="0.01" min="0" required></div>
    <button class="btn btn-success">إنشاء وإرسال للمورد</button>
  </form>
  <?php if (!$suppliers): ?>
    <p style="color:#d97706;margin-top:8px"><i class="fa-solid fa-triangle-exclamation"></i> أضف مورداً أولاً من صفحة الموردين</p>
  <?php endif; ?>
</div>

<div class="card">
  <h3>أوامر الشراء</h3>
  <table>
    <tr><th>الرقم</th><th>المورد</th><th>الإجمالي</th><th>الحالة</th><th>الدفع</th><th>التاريخ</th></tr>
    <?php foreach ($orders as $o): ?>
    <tr>
      <td><a href="/purchasing/orders/<?= $o['id'] ?>"><?= Helpers::e($o['po_no']) ?></a></td>
      <td><?= Helpers::e($o['supplier_name']) ?></td>
      <td><?= Helpers::money($o['total']) ?></td>
      <td><span class="badge badge-<?= $o['status'] === 'received' ? 'success' : ($o['status'] === 'cancelled' ? 'danger' : 'info') ?>">
        <?= $statusLabels[$o['status']] ?? $o['status'] ?></span></td>
      <td><span class="badge badge-<?= $o['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
        <?= $payLabels[$o['payment_status']] ?? $o['payment_status'] ?></span></td>
      <td><?= date('m/d H:i', strtotime($o['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="6">لا توجد أوامر شراء</td></tr><?php endif; ?>
  </table>
</div>

<script>
document.getElementById('po-item')?.addEventListener('change', function () {
  const cost = this.selectedOptions[0]?.dataset.cost || 0;
  document.getElementById('po-price').value = cost;
});
</script>
