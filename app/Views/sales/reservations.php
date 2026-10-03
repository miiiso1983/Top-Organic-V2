<?php use App\Core\Helpers; ?>
<?php
$statusLabels = ['pending'=>'بانتظار التأكيد','confirmed'=>'مؤكد','seated'=>'تم الجلوس','cancelled'=>'ملغي','no_show'=>'لم يحضر','waiting'=>'قائمة انتظار','rejected'=>'مرفوض','completed'=>'مكتمل'];
$statusBadge = ['pending'=>'warning','confirmed'=>'success','seated'=>'primary','cancelled'=>'danger','no_show'=>'secondary','waiting'=>'info','rejected'=>'dark','completed'=>'success'];
$sourceLabels = ['web'=>'ويب','call_center'=>'كول سنتر','whatsapp'=>'واتساب','walk_in'=>'حضوري','app'=>'تطبيق','pos'=>'نقاط بيع'];
$typeLabels = ['dine_in'=>'طعام في المطعم','private'=>'قاعة خاصة','event'=>'فعالية','birthday'=>'عيد ميلاد','business'=>'عمل','delivery'=>'توصيل','takeaway'=>'takeaway'];
?>

<a href="/reservations/tomorrow-reminders" class="card" style="display:block; text-decoration:none; <?= (int)($tomorrow['large_groups'] ?? 0) ? 'border-color:#f97316; background:#fff7ed;' : '' ?>">
  <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
      <h3 style="margin:0 0 6px; font-size:16px"><i class="fa-solid fa-phone" style="<?= (int)($tomorrow['large_groups'] ?? 0) ? 'color:#f97316;' : '' ?>"></i> التأكيد والتذكير — حجوزات الغد</h3>
      <div style="color:#6b7280; font-size:13px">
        <?= (int)($tomorrow['total'] ?? 0) ?> حجز ليوم الغد
        <?php if ((int)($tomorrow['large_groups'] ?? 0)): ?>
          <span style="color:#f97316; font-weight:600">(<?= (int)($tomorrow['large_groups'] ?? 0) ?> حجز يتطلب اتصال)</span>
        <?php endif; ?>
      </div>
      <?php if (!empty($tomorrow['error'])): ?>
        <div style="color:#dc2626; font-size:13px; margin-top:6px"><i class="fa-solid fa-circle-exclamation"></i> <?= Helpers::e($tomorrow['error']) ?></div>
      <?php endif; ?>
    </div>
    <span class="btn btn-sm btn-primary">عرض</span>
  </div>
</a>

<div class="card">
  <h3>حجز جديد</h3>
  <form method="post" action="/reservations" class="form-inline" id="reservationForm">
    <div class="form-row"><label>اسم العميل</label><input name="customer_name" required placeholder="اسم العميل الكامل"></div>
    <div class="form-row"><label>الهاتف</label><input name="customer_phone" placeholder="05xxxxxxxx"></div>
    <div class="form-row"><label>نوع الحجز</label>
      <select name="reservation_type">
        <?php foreach ($typeLabels as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>عدد الضيوف</label><input type="number" name="guests" id="guestsInput" value="2" min="1" required></div>
    <div class="form-row">
      <label>الموعد</label>
      <div class="reservation-datetime-wrap">
        <span class="input-icon"><i class="fa-solid fa-calendar-days"></i></span>
        <input type="text" class="reservation-datetime" name="reserved_at" id="reservedAt" required
               value="<?= date('Y-m-d H:i', strtotime('+1 hour', strtotime(date('Y-m-d H:00')))) ?>"
               placeholder="اختر التاريخ والوقت">
      </div>
    </div>
    <div class="form-row"><label>المدة (دقيقة)</label><input type="number" name="duration_minutes" id="durationInput" value="90" min="15" step="15"></div>
    <div class="form-row"><label>الطاولة</label>
      <select name="table_id" id="reservation-table">
        <option value="" data-capacity="0">اختر الطاولة</option>
        <?php foreach ($tables as $t): ?>
          <?php $cap = (int)($t['max_guests'] ?? $t['capacity'] ?? $t['seats'] ?? 0); ?>
          <option value="<?= $t['id'] ?>" data-capacity="<?= $cap ?>">
            <?= Helpers::e($t['name']) ?> • <?= $cap ?> كرسي
            <?= $t['floor'] ? '(طابق ' . Helpers::e($t['floor']) . ')' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small id="capacity-hint" style="color:#6b7280;font-size:12px"></small>
    </div>
    <div class="form-row"><label>المصدر</label>
      <select name="source">
        <?php foreach ($sourceLabels as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>ملاحظات العميل</label><textarea name="customer_notes" rows="2"></textarea></div>
    <div class="form-row"><label>ملاحظات داخلية</label><textarea name="notes" rows="2"></textarea></div>
    <button class="btn btn-success">حفظ الحجز</button>
  </form>
  <p style="margin-top:12px;color:#6b7280;font-size:13px">رابط الحجز العام للعملاء: <code>/book</code> | <a href="/reservations/floor-plan">مخطط الطاولات</a></p>
</div>

<div class="card">
  <h3>الحجوزات</h3>
  <form method="get" action="/reservations" class="form-inline" style="margin-bottom:15px">
    <div class="form-row"><label>الحالة</label>
      <select name="status">
        <option value="">الكل</option>
        <?php foreach ($statusLabels as $k => $v): ?>
          <option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>من</label><input type="date" name="date_from" value="<?= $filters['date_from'] ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="date_to" value="<?= $filters['date_to'] ?>"></div>
    <div class="form-row"><label>البحث</label><input name="q" value="<?= Helpers::e($filters['q']) ?>" placeholder="اسم/هاتف/رقم حجز"></div>
    <button class="btn btn-sm btn-primary">عرض</button>
    <a href="/reservations" class="btn btn-sm btn-secondary">إعادة التعيين</a>
  </form>
  <table>
    <tr><th>رقم الحجز</th><th>العميل</th><th>الهاتف</th><th>النوع</th><th>الضيوف</th><th>الموعد</th><th>الطاولة</th><th>المصدر</th><th>الحالة</th><th>إجراء</th></tr>
    <?php foreach ($reservations as $r): ?>
    <tr>
      <td><a href="/reservations/<?= $r['id'] ?>"><?= Helpers::e($r['reservation_no'] ?? '#' . $r['id']) ?></a></td>
      <td><?= Helpers::e($r['customer_name']) ?></td>
      <td><?= Helpers::e($r['customer_phone']) ?></td>
      <td><?= $typeLabels[$r['reservation_type']] ?? $r['reservation_type'] ?></td>
      <td><?= $r['guests'] ?></td>
      <td>
        <div style="font-weight:600"><?= date('H:i', strtotime($r['reserved_at'])) ?></div>
        <div style="font-size:12px;color:#6b7280"><?= date('Y/m/d', strtotime($r['reserved_at'])) ?></div>
      </td>
      <td><?= Helpers::e($r['table_name'] ?? '-') ?></td>
      <td><?= $sourceLabels[$r['source']] ?? $r['source'] ?></td>
      <td><span class="badge badge-<?= $statusBadge[$r['status']] ?>"><?= $statusLabels[$r['status']] ?></span></td>
      <td>
        <a href="/reservations/<?= $r['id'] ?>" class="btn btn-sm btn-primary">عرض</a>
        <a href="/reservations/<?= $r['id'] ?>/edit" class="btn btn-sm btn-secondary">تعديل</a>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$reservations): ?><tr><td colspan="10">لا توجد حجوزات</td></tr><?php endif; ?>
  </table>
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
      tableSelect.style.borderColor = '#dc2626';
    } else if (capacity > 0 && guests > 0) {
      hint.textContent = 'تناسب: الطاولة تتسع لـ ' + capacity + ' ضيف';
      hint.style.color = '#16a34a';
      tableSelect.style.borderColor = '#16a34a';
    } else {
      hint.textContent = '';
      tableSelect.style.borderColor = '';
    }
  }
  if (guestsInput) guestsInput.addEventListener('input', checkCapacity);
  if (tableSelect) tableSelect.addEventListener('change', checkCapacity);
  checkCapacity();
})();
</script>
