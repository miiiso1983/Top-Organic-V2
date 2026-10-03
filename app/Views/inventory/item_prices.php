<?php use App\Core\Helpers; ?>
<div class="card">
  <div class="item-card-header">
    <div>
      <div class="item-card-title">تحديث أسعار المواد</div>
      <div class="item-card-subtitle">تسجيل سعر جديد لصنف مع تاريخ نفاذ، مع إعادة حساب تكاليف الأصناف المحضرة.</div>
    </div>
  </div>
</div>

<?php if (\App\Core\Auth::can('inventory.manage')): ?>
<div class="card">
  <h3>نموذج تحديث السعر</h3>
  <form method="post" action="/inventory/prices" class="form">
    <div class="form-row">
      <label>الصنف <span style="color:#b91c1c;">*</span></label>
      <select name="item_id" class="select2-item" required data-placeholder="ابحث باسم الصنف أو الباركود" style="width:100%;">
        <option value="">اختر الصنف</option>
        <?php foreach ($items as $it): ?>
        <option value="<?= (int)$it['id'] ?>" data-unit="<?= Helpers::e($it['unit_name'] ?? '') ?>">
          <?= Helpers::e($it['name']) ?> (<?= Helpers::e($it['barcode'] ?? '-') ?>) —
          <?= Helpers::e(number_format($it['cost_price'], 2)) ?>/<?= Helpers::e($it['unit_name'] ?? '') ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <label>السعر الجديد <span style="color:#b91c1c;">*</span></label>
      <div style="display:flex; gap:8px; align-items:center;">
        <input type="number" name="new_price" step="0.0001" min="0.0001" required placeholder="0.0000" style="flex:1;">
        <span id="unit-label" class="badge badge-info">الوحدة</span>
      </div>
      <small style="color:#6b7280; display:block; margin-top:4px;">
        أدخل سعر الوحدة الأساسية للمادة (مثلاً: للتر، الكيلو، العلبة، الحبة).
      </small>
    </div>
    <div class="form-row">
      <label>تاريخ النفاذ</label>
      <input type="date" name="effective_date" value="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="form-row">
      <label>ملاحظات</label>
      <input type="text" name="notes" placeholder="مثلاً: تغيير سعر المورد">
    </div>
    <button class="btn btn-success"><i class="fa-solid fa-lock"></i> حفظ التحديث</button>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h3>سجل تحديثات الأسعار</h3>
  <table>
    <tr>
      <th>الوقت</th>
      <th>الصنف</th>
      <th>السعر الجديد</th>
      <th>تاريخ النفاذ</th>
      <th>بواسطة</th>
      <th>ملاحظات</th>
    </tr>
    <?php foreach ($history as $h): ?>
    <tr>
      <td><?= Helpers::e($h['created_at'] ?? '-') ?></td>
      <td><strong><?= Helpers::e($h['item_name']) ?></strong></td>
      <td><?= Helpers::e(number_format($h['price'], 4)) ?></td>
      <td><?= Helpers::e($h['effective_date'] ?? '-') ?></td>
      <td><?= Helpers::e($h['user_name'] ?? '-') ?></td>
      <td><?= Helpers::e($h['notes'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$history): ?>
    <tr><td colspan="6">لا توجد تحديثات مسجّلة</td></tr>
    <?php endif; ?>
  </table>
</div>

<script>
function updateUnitLabel() {
    var selected = $('.select2-item').find(':selected');
    var unit = selected.data('unit') || '';
    $('#unit-label').text(unit ? 'الوحدة: ' + unit : 'الوحدة');
}
$(document).ready(function() {
    $('.select2-item').on('change', updateUnitLabel);
    updateUnitLabel();
});
</script>
