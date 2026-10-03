<?php use App\Core\Helpers; ?>
<?php use App\Core\Auth; ?>

<div class="mail-layout">
  <?php include __DIR__ . '/_announcements_sidebar.php'; ?>

  <div class="mail-main">
    <div id="mailComposeContainer">
    <?php if (Auth::can('hr.notifications.send')): ?>
    <div class="card">
      <h3>إرسال تعميم / تبليغ داخلي</h3>
      <div id="resendBanner" class="mail-resend-banner" style="display:none">
        <span><i class="fa-solid fa-rotate-left"></i> تم تعبئة الحقول من تعميم سابق — يمكنك التعديل قبل الإرسال</span>
        <a href="#" onclick="cancelResend(); return false;">إلغاء</a>
      </div>
      <form method="post" action="/hr/announcements">
        <input type="hidden" name="reminder_id" id="announceReminderId" value="">
        <div class="form-row"><label>العنوان</label><input type="text" name="title" id="announceTitle" required></div>
        <div class="form-row"><label>النص</label><textarea name="body" id="announceBody" rows="4" required></textarea></div>
        <div class="form-row" style="display:none"><label>رابط (اختياري)</label><input type="text" name="link" id="announceLink" placeholder="/hr/..."></div>
        <div class="form-row"><label>المستلمين</label>
          <select name="target_type" id="target_type" onchange="toggleTarget(this.value)">
            <option value="all">الجميع</option>
            <option value="level">مستوى إداري</option>
            <option value="role">دور محدد</option>
            <option value="branch">فرع محدد</option>
            <option value="department">قسم محدد</option>
            <option value="user">موظف محدد</option>
          </select>
        </div>
        <div class="form-row target-row" id="row_level" style="display:none">
          <label>أدنى مستوى إداري</label>
          <input type="number" name="target_level" id="announceTargetLevel" value="1" min="0">
        </div>
        <div class="form-row target-row" id="row_role" style="display:none">
          <label>الدور</label>
          <select name="target_role_id" id="announceTargetRole"><option value="">—</option><?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>"><?= Helpers::e($r['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-row target-row" id="row_branch" style="display:none">
          <label>الفرع</label>
          <select name="target_branch_id" id="announceTargetBranch"><option value="">—</option><?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= Helpers::e($b['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-row target-row" id="row_department" style="display:none">
          <label>القسم</label>
          <select name="target_department_id" id="announceTargetDepartment"><option value="">—</option><?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= Helpers::e($d['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-row target-row" id="row_user" style="display:none">
          <label>الموظف (يمكن اختيار أكثر من موظف)</label>
          <select name="target_user_id[]" id="announceTargetUser" multiple data-placeholder="اختر موظفًا أو أكثر..."><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= Helpers::e($u['full_name']) ?></option><?php endforeach; ?></select>
        </div>

        <?php if (Auth::can('hr.notifications.reminders')): ?>
        <div class="form-row">
          <label><input type="checkbox" name="enable_reminder" value="1" onchange="document.getElementById('reminderFields').style.display = this.checked ? 'block' : 'none'"> تفعيل تذكير بهذا التعميم</label>
        </div>
        <div id="reminderFields" class="mail-reminder-fields">
          <div class="form-row">
            <label>موعد التذكير</label>
            <input type="datetime-local" name="remind_at">
          </div>
          <div class="form-row">
            <label>التكرار</label>
            <select name="recurrence_type">
              <option value="once">مرة واحدة</option>
              <option value="daily">يومي (روتيني)</option>
              <option value="weekly">أسبوعي (روتيني)</option>
              <option value="monthly">شهري (روتيني)</option>
            </select>
          </div>
        </div>
        <?php endif; ?>

        <button class="btn btn-success">إرسال</button>
      </form>
    </div>
    <?php endif; ?>
    </div>
    <div id="mailDetailContainer" style="display:none"></div>
  </div>
</div>

<style>
.mail-resend-banner { display:flex; justify-content:space-between; align-items:center; gap:10px; background:#fff7e6; border:1px solid #ffe0a3; color:#8a6416; border-radius:8px; padding:8px 12px; margin-bottom:12px; font-size:13px; }
.mail-resend-banner a { color:#8a6416; text-decoration:underline; }
</style>
<script>
function toggleTarget(v) {
  document.querySelectorAll('.target-row').forEach(function(r) { r.style.display = 'none'; });
  document.getElementById('row_' + v).style.display = 'flex';
}

/** تعبئة نموذج الإرسال ببيانات تعميم سابق أو تذكير دوري مجدول للتعديل وإعادة الإرسال */
function showResendForm(data, isReminderEdit) {
  var composeContainer = document.getElementById('mailComposeContainer');
  if (!composeContainer) return false;
  var detailContainer = document.getElementById('mailDetailContainer');
  if (detailContainer) { detailContainer.style.display = 'none'; detailContainer.innerHTML = ''; }
  composeContainer.style.display = '';

  var $ = window.jQuery;
  var hasSelect2 = !!($ && $.fn && $.fn.select2);
  var titleEl = document.getElementById('announceTitle');
  var bodyEl = document.getElementById('announceBody');
  var linkEl = document.getElementById('announceLink');
  var levelEl = document.getElementById('announceTargetLevel');
  if (titleEl) titleEl.value = data.title || '';
  if (bodyEl) bodyEl.value = data.body || '';
  if (linkEl) linkEl.value = data.link || '';
  if (levelEl) levelEl.value = data.target_level || 1;

  var targetType = data.target_type || 'all';
  if (hasSelect2) {
    $('#target_type').val(targetType).trigger('change');
    if (data.target_role_id) $('#announceTargetRole').val(String(data.target_role_id)).trigger('change');
    if (data.target_branch_id) $('#announceTargetBranch').val(String(data.target_branch_id)).trigger('change');
    if (data.target_department_id) $('#announceTargetDepartment').val(String(data.target_department_id)).trigger('change');
    if (data.target_user_ids && data.target_user_ids.length) {
      $('#announceTargetUser').val(data.target_user_ids.map(String)).trigger('change');
    }
  } else {
    document.getElementById('target_type').value = targetType;
    toggleTarget(targetType);
  }

  var reminderIdInput = document.getElementById('announceReminderId');
  if (reminderIdInput) reminderIdInput.value = (isReminderEdit && data.reminder_id) ? data.reminder_id : '';

  if (isReminderEdit) {
    var enableCb = document.querySelector('input[name="enable_reminder"]');
    var remindAtEl = document.querySelector('input[name="remind_at"]');
    var recurrenceEl = document.querySelector('select[name="recurrence_type"]');
    var reminderFields = document.getElementById('reminderFields');
    if (enableCb) enableCb.checked = true;
    if (remindAtEl) remindAtEl.value = data.remind_at || '';
    if (recurrenceEl) recurrenceEl.value = data.recurrence_type || 'once';
    if (reminderFields) reminderFields.style.display = 'block';
  }

  var banner = document.getElementById('resendBanner');
  if (banner) {
    banner.style.display = 'flex';
    var bannerText = banner.querySelector('span');
    if (bannerText) {
      bannerText.innerHTML = isReminderEdit
        ? '<i class="fa-solid fa-rotate-left"></i> تم تعبئة الحقول من تذكير دوري مجدول — الحفظ سيحدّث نفس التذكير ويرسل التعميم فوراً'
        : '<i class="fa-solid fa-rotate-left"></i> تم تعبئة الحقول من تعميم سابق — يمكنك التعديل قبل الإرسال';
    }
  }
  composeContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
  return true;
}

function cancelResend() {
  var form = document.querySelector('#mailComposeContainer form');
  if (form) form.reset();
  var reminderIdInput = document.getElementById('announceReminderId');
  if (reminderIdInput) reminderIdInput.value = '';
  var reminderFields = document.getElementById('reminderFields');
  if (reminderFields) reminderFields.style.display = 'none';
  var $ = window.jQuery;
  if ($ && $.fn && $.fn.select2) {
    $('#target_type').val('all').trigger('change');
    $('#announceTargetRole, #announceTargetBranch, #announceTargetDepartment, #announceTargetUser').val(null).trigger('change');
  } else {
    toggleTarget('all');
  }
  var banner = document.getElementById('resendBanner');
  if (banner) banner.style.display = 'none';
  if (window.history && window.history.pushState) {
    window.history.pushState({}, '', '/hr/announcements');
  }
}

document.addEventListener('click', function(e) {
  var link = e.target.closest('[data-resend-announcement]');
  if (!link) return;
  if (!document.getElementById('mailComposeContainer')) return; // ليس بهذه الصفحة: اترك التنقل الطبيعي
  e.preventDefault();
  try {
    var data = JSON.parse(link.getAttribute('data-resend') || '{}');
    showResendForm(data);
    if (window.history && window.history.pushState) {
      window.history.pushState({}, '', '/hr/announcements?resend=' + (data.id || ''));
    }
  } catch (err) {}
});

document.addEventListener('click', function(e) {
  var link = e.target.closest('[data-edit-reminder]');
  if (!link) return;
  if (!document.getElementById('mailComposeContainer')) return;
  e.preventDefault();
  try {
    var data = JSON.parse(link.getAttribute('data-edit') || '{}');
    showResendForm(data, true);
  } catch (err) {}
});

// دعم فتح نموذج التعديل مباشرة عبر رابط /hr/announcements?resend=ID
(function() {
  var params = new URLSearchParams(window.location.search);
  var resendId = params.get('resend');
  if (!resendId) return;
  fetch('/hr/notifications-history/' + resendId + '?ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function(res) { if (!res.ok) throw new Error('failed'); return res.text(); })
    .then(function(html) {
      var tmp = document.createElement('div');
      tmp.innerHTML = html;
      var btn = tmp.querySelector('[data-resend-announcement]');
      if (!btn) return;
      var data = JSON.parse(btn.getAttribute('data-resend') || '{}');
      showResendForm(data);
    })
    .catch(function() {});
})();
</script>
