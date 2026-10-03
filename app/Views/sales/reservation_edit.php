<?php use App\Core\Helpers; ?>
<?php
$sourceLabels = ['web'=>'ويب','call_center'=>'كول سنتر','whatsapp'=>'واتساب','walk_in'=>'حضوري','app'=>'تطبيق','pos'=>'نقاط بيع'];
$typeLabels = ['dine_in'=>'طعام في المطعم','private'=>'قاعة خاصة','event'=>'فعالية','birthday'=>'عيد ميلاد','business'=>'عمل','delivery'=>'توصيل','takeaway'=>'takeaway'];
$currentTableId = $res['table_id'] ? (int)$res['table_id'] : 0;
?>
<div class="card">
  <h3>تعديل حجز <?= Helpers::e($res['reservation_no'] ?? '#' . $res['id']) ?></h3>
  <form method="post" action="/reservations/<?= $res['id'] ?>" class="form-inline" id="editForm">
    <div class="form-row"><label>اسم العميل</label><input name="customer_name" value="<?= Helpers::e($res['customer_name']) ?>" required></div>
    <div class="form-row"><label>الهاتف</label><input name="customer_phone" value="<?= Helpers::e($res['customer_phone'] ?? '') ?>"></div>
    <div class="form-row"><label>البريد</label><input name="customer_email" type="email" value="<?= Helpers::e($res['customer_email'] ?? '') ?>"></div>
    <div class="form-row"><label>الفرع</label>
      <select name="branch_id">
        <option value="">اختر الفرع</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $res['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>نوع الحجز</label>
      <select name="reservation_type">
        <?php foreach ($typeLabels as $k => $v): ?>
          <option value="<?= $k ?>" <?= $res['reservation_type'] === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>عدد الضيوف</label><input type="number" name="guests" id="guestsInput" value="<?= (int)$res['guests'] ?>" min="1" required></div>
    <div class="form-row">
      <label>الموعد</label>
      <input type="text" class="reservation-datetime" name="reserved_at" value="<?= date('Y-m-d H:i', strtotime($res['reserved_at'])) ?>" required>
    </div>
    <div class="form-row"><label>المدة (دقيقة)</label><input type="number" name="duration_minutes" value="<?= (int)$res['duration_minutes'] ?>" min="15" step="15"></div>
    <div class="form-row"><label>الطاولة</label>
      <select name="table_id" id="reservation-table">
        <option value="" data-capacity="0">بدون طاولة</option>
        <?php foreach ($tables as $t): ?>
          <?php $cap = (int)($t['max_guests'] ?? $t['capacity'] ?? $t['seats'] ?? 0); ?>
          <option value="<?= $t['id'] ?>" data-capacity="<?= $cap ?>" <?= $currentTableId === (int)$t['id'] ? 'selected' : '' ?>>
            <?= Helpers::e($t['name']) ?> • <?= $cap ?> كرسي
            <?= $t['floor'] ? '(طابق ' . Helpers::e($t['floor']) . ')' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small id="capacity-hint" style="color:#6b7280;font-size:12px"></small>
    </div>
    <div class="form-row"><label>المصدر</label>
      <select name="source">
        <?php foreach ($sourceLabels as $k => $v): ?>
          <option value="<?= $k ?>" <?= $res['source'] === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>ملاحظات العميل</label><textarea name="customer_notes" rows="2"><?= Helpers::e($res['customer_notes'] ?? '') ?></textarea></div>
    <div class="form-row"><label>ملاحظات داخلية</label><textarea name="notes" rows="2"><?= Helpers::e($res['notes'] ?? '') ?></textarea></div>
    <div class="form-row"><label>الحالة</label>
      <select name="status" id="statusSelect">
        <?php foreach ($statusLabels as $k => $v): ?>
          <option value="<?= $k ?>" <?= $res['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row" id="reasonRow" style="display:none">
      <label>السبب</label>
      <input type="text" name="reason" id="reasonInput" value="" placeholder="سبب الإلغاء/الرفض">
    </div>
    <button class="btn btn-success">حفظ التعديلات</button>
    <a href="/reservations/<?= $res['id'] ?>" class="btn btn-secondary">إلغاء</a>
  </form>
</div>

<script>
(function () {
  const guestsInput = document.getElementById('guestsInput');
  const tableSelect = document.getElementById('reservation-table');
  const hint = document.getElementById('capacity-hint');
  function checkCapacity() {
    if (!tableSelect || !guestsInput || !hint) return;
    const guests = parseInt(guestsInput.value, 10) || 0;
    const option = tableSelect.options[tableSelect.selectedIndex];
    const capacity = parseInt(option.dataset.capacity || '0', 10) || 0;
    if (capacity > 0 && guests > capacity) {
      hint.textContent = 'تنبيه: عدد الضيوف أكبر من سعة الطاولة (' + capacity + ' كرسي)';
      hint.style.color = '#dc2626';
    } else if (capacity > 0 && guests > 0) {
      hint.textContent = 'تناسب: الطاولة تتسع لـ ' + capacity + ' ضيف';
      hint.style.color = '#16a34a';
    } else {
      hint.textContent = '';
    }
  }
  if (guestsInput) guestsInput.addEventListener('input', checkCapacity);
  if (tableSelect) tableSelect.addEventListener('change', checkCapacity);
  checkCapacity();

  const statusSelect = document.getElementById('statusSelect');
  const reasonRow = document.getElementById('reasonRow');
  const reasonInput = document.getElementById('reasonInput');
  function toggleReason() {
    if (!statusSelect || !reasonRow) return;
    if (['cancelled','rejected'].includes(statusSelect.value)) {
      reasonRow.style.display = 'block';
      reasonInput.required = true;
    } else {
      reasonRow.style.display = 'none';
      reasonInput.required = false;
      reasonInput.value = '';
    }
  }
  if (statusSelect) statusSelect.addEventListener('change', toggleReason);
  toggleReason();
})();
</script>
