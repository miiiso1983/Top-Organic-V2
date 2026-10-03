<?php use App\Core\Helpers; ?>
<style>
.mr-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
.mr-table { width: 100%; border-collapse: collapse; }
.mr-table th, .mr-table td { border: 1px solid #e5e7eb; padding: 10px; text-align: right; }
.mr-table th { background: #f3f4f6; }
</style>

<h2>RFQ <?= Helpers::e($rfq['rfq_no']) ?></h2>
<p>طلب الشراء: <?= Helpers::e($rfq['pr_no']) ?> — الحالة: <?= Helpers::e($rfq['status']) ?></p>

<div class="mr-card">
  <h3>البنود المطلوبة</h3>
  <table class="mr-table">
    <tr><th>المادة</th><th>الكمية</th><th>الوحدة</th></tr>
    <?php foreach ($items as $it): ?>
    <tr>
      <td><?= Helpers::e($it['item_name']) ?></td>
      <td><?= number_format((float)$it['qty'], 2) ?></td>
      <td><?= Helpers::e($it['unit_name'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="mr-card">
  <h3>عروض الموردين</h3>
  <?php if ($quotes): ?>
  <table class="mr-table">
    <tr><th>المورد</th><th>التقييم</th><th>الإجمالي</th><th>الحالة</th><th>إجراء</th></tr>
    <?php foreach ($quotes as $q): ?>
    <tr>
      <td><?= Helpers::e($q['supplier_name']) ?></td>
      <td><?= (int)$q['rating'] ?>/5</td>
      <td><?= number_format((float)$q['total'], 2) ?></td>
      <td><?= Helpers::e($q['status']) ?></td>
      <td>
        <?php if ($q['status'] === 'received' && Auth::can('purchasing.manage')): ?>
        <form method="POST" action="/purchasing/quotes/<?= $q['id'] ?>/select" style="display:inline" onsubmit="return confirm('اختيار هذا المورد؟')">
          <button type="submit" class="btn btn-sm btn-success">اختيار</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <p>لا توجد عروض بعد.</p>
  <?php endif; ?>
</div>

<?php if ($rfq['status'] !== 'closed' && Auth::can('purchasing.manage')): ?>
<div class="mr-card">
  <h3>إضافة عرض سعر</h3>
  <form method="POST" action="/purchasing/rfqs/<?= $rfq['id'] ?>/quotes">
    <div class="form-group">
      <label>المورد</label>
      <select name="supplier_id" class="form-input" required>
        <?php foreach ($suppliers as $s): ?>
        <option value="<?= $s['id'] ?>"><?= Helpers::e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>صالح حتى</label>
      <input type="date" name="valid_until" class="form-input">
    </div>
    <div class="form-group">
      <label>ملاحظات</label>
      <textarea name="notes" class="form-input" rows="2"></textarea>
    </div>
    <h4>أسعار البنود</h4>
    <?php foreach ($items as $idx => $it): ?>
    <div class="form-row">
      <input type="hidden" name="lines[<?= $idx ?>][item_id]" value="<?= $it['item_id'] ?>">
      <input type="hidden" name="lines[<?= $idx ?>][unit_id]" value="<?= $it['unit_id'] ?>">
      <input type="hidden" name="lines[<?= $idx ?>][qty]" value="<?= $it['qty'] ?>">
      <div><?= Helpers::e($it['item_name']) ?> × <?= number_format((float)$it['qty'], 2) ?> <?= Helpers::e($it['unit_name'] ?? '') ?></div>
      <input type="number" step="0.01" name="lines[<?= $idx ?>][unit_price]" class="form-input" placeholder="سعر الوحدة" required>
    </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary">حفظ العرض</button>
  </form>
</div>
<?php endif; ?>
