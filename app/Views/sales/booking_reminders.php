<?php use App\Core\{Helpers, Auth}; ?>
<style>
.booking-reminder-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; margin-bottom:12px; }
.booking-reminder-card.high-priority { background:#fff7ed; border-color:#f97316; }
.booking-reminder-card.high-priority .guests-badge { background:#f97316; color:#fff; }
.booking-reminder-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.booking-reminder-name { font-size:16px; font-weight:700; }
.booking-reminder-phone { color:#2563eb; font-size:14px; direction:ltr; }
.guests-badge { background:#10b981; color:#fff; padding:4px 10px; border-radius:20px; font-size:13px; font-weight:600; }
.booking-reminder-meta { color:#6b7280; font-size:13px; margin-top:8px; }
.booking-reminder-alert { color:#f97316; font-size:13px; font-weight:600; margin-top:6px; }
.booking-reminder-alert i { margin-left:4px; }
.booking-reminder-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:12px; }
.status-form { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.status-form select { min-width:140px; }
.status-form .reason-input { display:none; min-width:180px; }
.status-form .save-btn { display:none; }
.status-form.show-reason .reason-input { display:inline-block; }
.status-form.show-reason .save-btn { display:inline-block; }
.empty-state { text-align:center; padding:40px 20px; color:#6b7280; }
</style>

<div class="page-head">
  <h2><i class="fa-solid fa-phone"></i> التأكيد والتذكير</h2>
  <span class="badge badge-info"><?= (int)$count ?> حجز ليوم الغد</span>
</div>

<?php if ($highPriority): ?>
<div class="alert alert-warning" style="margin-bottom:16px">
  <i class="fa-solid fa-triangle-exclamation"></i>
  يوجد <?= count($highPriority) ?> حجز يتطلب اتصال هاتفي (أكثر من 9 أشخاص).
</div>
<?php endif; ?>

<?php if (!$reservations): ?>
<div class="card empty-state">
  <div style="font-size:48px;margin-bottom:12px"><i class="fa-solid fa-calendar-check"></i></div>
  <div style="font-size:18px;font-weight:600">لا توجد حجوزات ليوم الغد</div>
  <div style="margin-top:8px">سيتم عرض الحجوزات تلقائياً هنا عند توفرها.</div>
</div>
<?php else: ?>
  <?php foreach ($reservations as $r): ?>
    <?php $isHigh = (int)$r['guests'] > 9; ?>
    <div class="booking-reminder-card <?= $isHigh ? 'high-priority' : '' ?>">
      <div class="booking-reminder-head">
        <div>
          <div class="booking-reminder-name">
            <?= Helpers::e($r['customer_name'] ?: '—') ?>
            <?php if ($isHigh): ?>
              <i class="fa-solid fa-phone-volume" style="color:#f97316" title="اتصال هاتفي مطلوب"></i>
            <?php endif; ?>
          </div>
          <div class="booking-reminder-phone">
            <i class="fa-solid fa-phone"></i> <?= Helpers::e($r['customer_phone'] ?: '—') ?>
          </div>
        </div>
        <div class="guests-badge"><?= (int)$r['guests'] ?> أشخاص</div>
      </div>
      <div class="booking-reminder-meta">
        <i class="fa-regular fa-clock"></i> <?= date('H:i', strtotime($r['reserved_at'])) ?>
        <?php if ($r['table_name']): ?>
          — <i class="fa-solid fa-chair"></i> <?= Helpers::e($r['table_name']) ?>
          <?php if ($r['table_floor']): ?> (<?= Helpers::e($r['table_floor']) ?>)<?php endif; ?>
        <?php endif; ?>
        <?php if ($r['reservation_no']): ?>
          — <span style="font-family:monospace">#<?= Helpers::e($r['reservation_no']) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($isHigh): ?>
        <div class="booking-reminder-alert">
          <i class="fa-solid fa-triangle-exclamation"></i> يتطلب تأكيد هاتفي — عدد الأشخاص أكثر من 9
        </div>
      <?php endif; ?>
      <?php if ($r['notes']): ?>
        <div class="booking-reminder-meta" style="margin-top:6px">
          <i class="fa-solid fa-note-sticky"></i> <?= Helpers::e($r['notes']) ?>
        </div>
      <?php endif; ?>

      <div class="booking-reminder-actions">
        <span class="badge badge-<?= $statusBadge[$r['status']] ?? 'secondary' ?>"><?= $statusLabels[$r['status']] ?? $r['status'] ?></span>

        <?php if (Auth::can('reservations.manage')): ?>
        <form method="post" action="/reservations/<?= (int)$r['id'] ?>/status" class="status-form" data-current="<?= Helpers::e($r['status']) ?>">
          <input type="hidden" name="return_to" value="/reservations/tomorrow-reminders">
          <select name="status" class="form-input status-select" aria-label="تغيير الحالة">
            <?php foreach ($statusLabels as $k => $v): ?>
              <option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="reason" class="form-input reason-input" placeholder="سبب الإلغاء/الرفض">
          <button type="submit" class="btn btn-sm btn-primary save-btn">حفظ</button>
        </form>
        <?php endif; ?>

        <a href="/reservations/<?= (int)$r['id'] ?>" class="btn btn-sm btn-secondary">عرض</a>
        <a href="tel:<?= Helpers::e($r['customer_phone']) ?>" class="btn btn-sm btn-success"><i class="fa-solid fa-phone"></i> اتصال</a>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<script>
(function () {
  document.querySelectorAll('.status-form').forEach(function (form) {
    const select = form.querySelector('.status-select');
    const reason = form.querySelector('.reason-input');
    const saveBtn = form.querySelector('.save-btn');
    const needsReason = ['cancelled', 'rejected'];

    function update() {
      if (needsReason.includes(select.value)) {
        form.classList.add('show-reason');
        reason.required = true;
      } else {
        form.classList.remove('show-reason');
        reason.required = false;
      }
    }

    select.addEventListener('change', function () {
      if (select.value === form.dataset.current) {
        saveBtn.style.display = 'none';
      } else {
        saveBtn.style.display = 'inline-block';
      }
      update();
    });

    form.addEventListener('submit', function (e) {
      if (needsReason.includes(select.value) && !reason.value.trim()) {
        e.preventDefault();
        alert('يرجى إدخال سبب الإلغاء/الرفض');
        reason.focus();
      }
    });

    update();
  });
})();
</script>
