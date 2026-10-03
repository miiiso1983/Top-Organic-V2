<?php use App\Core\Helpers; ?>
<?php $inspLabels = ['accepted'=>'مقبول','partially_accepted'=>'مقبول جزئياً','rejected'=>'مرفوض']; ?>
<div class="card">
  <h3>استلام وفحص مواد (يدخل المخزن تلقائياً)</h3>
  <form method="post" action="/purchasing/receipts" class="form-inline">
    <div class="form-row"><label>أمر الشراء</label>
      <select name="po_id" required>
        <?php foreach ($openPOs as $po): ?>
          <option value="<?= $po['id'] ?>"><?= Helpers::e($po['po_no']) ?> — <?= Helpers::e($po['supplier_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>المادة</label>
      <select name="item_id" required>
        <?php foreach ($items as $it): ?><option value="<?= $it['id'] ?>"><?= Helpers::e($it['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>المخزن</label>
      <select name="warehouse_id" required>
        <?php foreach ($warehouses as $w): ?><option value="<?= $w['id'] ?>"><?= Helpers::e($w['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الكمية المستلمة فعلياً</label><input type="number" name="qty" id="actual_qty" step="0.001" min="0.001" required></div>
    <div class="form-row"><label>السعر الفعلي</label><input id="unit_price" type="number" name="unit_price" step="0.01" min="0" required></div>
    <div class="form-row"><label>آخر سعر شراء (مرجعي)</label>
      <div><span id="lastPriceHint">-</span> <small style="color:#6b7280">— من purchase_prices (إن وُجد)</small></div>
    </div>
    <div class="form-row"><label>رقم التشغيلة (Batch)</label><input type="text" name="batch_no" placeholder="Batch / رقم الدفعة"></div>
    <div class="form-row"><label>الباركود</label><input type="text" name="barcode" placeholder="مسح الباركود"></div>
    <div class="form-row"><label>QR Code</label><input type="text" name="qr_code" placeholder="قراءة QR Code"></div>
    <div class="form-row"><label>تاريخ الإنتاج</label><input type="date" name="production_date"></div>
    <div class="form-row"><label>تاريخ الانتهاء</label><input type="date" name="expiry_date"></div>
    <div class="form-row"><label>الكمية التالفة</label><input type="number" name="damaged_qty" id="damaged_qty" step="0.001" min="0" value="0"></div>
    <div class="form-row"><label>سبب التلف / الناقص</label><input type="text" name="damage_reason" placeholder="مواد تالفة أو كمية ناقصة..."></div>
    <div class="form-row"><label>نتيجة الفحص</label>
      <select name="inspection_result">
        <option value="accepted">مقبول</option>
        <option value="partially_accepted">مقبول جزئياً</option>
        <option value="rejected">مرفوض</option>
      </select>
    </div>
    <div class="form-row"><label>ملاحظات</label><input name="notes"></div>
    <button class="btn btn-success">تأكيد الاستلام</button>
  </form>
  <?php if (!$openPOs): ?>
    <p style="color:#d97706;margin-top:8px"><i class="fa-solid fa-triangle-exclamation"></i> لا توجد أوامر شراء مفتوحة للاستلام</p>
  <?php endif; ?>
</div>

<div class="card">
  <h3>سجلات الاستلام</h3>
  <table>
    <tr><th>الرقم</th><th>أمر الشراء</th><th>المخزن</th><th>المادة</th><th>المتوقع</th><th>الفعلي</th><th>المقبول</th><th>التالف</th><th>الناقص</th><th>Batch</th><th>الإنتاج</th><th>الانتهاء</th><th>الفحص</th><th>التاريخ</th></tr>
    <?php foreach ($receipts as $r): ?>
    <tr>
      <td><?= Helpers::e($r['receipt_no']) ?></td>
      <td><?= Helpers::e($r['po_no']) ?></td>
      <td><?= Helpers::e($r['warehouse_name']) ?></td>
      <td><?= Helpers::e($r['item_name'] ?? '-') ?></td>
      <td><?= number_format((float)($r['expected_qty'] ?? 0), 2) ?></td>
      <td><?= number_format((float)($r['actual_qty'] ?? 0), 2) ?></td>
      <td><?= number_format((float)($r['accepted_qty'] ?? 0), 2) ?></td>
      <td><?= number_format((float)($r['damaged_qty'] ?? 0), 2) ?></td>
      <td><?= number_format((float)($r['variance_qty'] ?? 0), 2) ?></td>
      <td><?= Helpers::e($r['batch_no'] ?? '-') ?></td>
      <td><?= $r['production_date'] ? date('Y-m-d', strtotime($r['production_date'])) : '-' ?></td>
      <td><?= $r['expiry_date'] ? date('Y-m-d', strtotime($r['expiry_date'])) : '-' ?></td>
      <td><span class="badge badge-<?= $r['inspection_result'] === 'accepted' ? 'success' : ($r['inspection_result'] === 'rejected' ? 'danger' : 'warning') ?>">
        <?= $inspLabels[$r['inspection_result']] ?? $r['inspection_result'] ?></span></td>
      <td><?= date('m/d H:i', strtotime($r['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$receipts): ?><tr><td colspan="14">لا توجد سجلات استلام</td></tr><?php endif; ?>
  </table>
</div>

<script>
(function(){
  var lastPrices = <?php echo json_encode($lastPrices ?? []); ?>;
  var sel = document.querySelector('select[name="item_id"]');
  var hint = document.getElementById('lastPriceHint');
  var unitPrice = document.getElementById('unit_price');
  function updateHint(){
    var id = sel && sel.value ? parseInt(sel.value, 10) : 0;
    var p = (id && lastPrices[id] !== undefined) ? Number(lastPrices[id]) : null;
    hint.textContent = (p && p>0) ? Math.round(p).toLocaleString('en-US') : '-';
    // لا نملأ الحقل تلقائياً لتفادي أي تغيير سلوك؛ إن رغبت، أبلغني لأفعّل ذلك اختيارياً.
  }
  if (sel && hint){ sel.addEventListener('change', updateHint); updateHint(); }
})();
</script>
