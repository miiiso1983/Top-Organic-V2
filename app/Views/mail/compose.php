<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-pen"></i> رسالة بريد داخلية جديدة</h2>
  <div style="display:flex;gap:8px">
    <a href="/mail/groups" class="btn btn-secondary"><i class="fa-solid fa-users-rectangle"></i> مجموعات البريد</a>
    <a href="/mail" class="btn btn-secondary"><i class="fa-solid fa-arrow-right"></i> رجوع للوارد</a>
  </div>
</div>

<div class="card" style="max-width:720px">
  <form method="post" action="/mail/compose" enctype="multipart/form-data" id="mail-compose-form">
    <div class="form-group">
      <label>المستلمون الأفراد</label>
      <select name="recipients[]" multiple data-placeholder="اختر مستلماً واحداً أو أكثر (اختياري)" style="width:100%">
        <?php foreach ($users as $u): ?>
        <option value="<?= (int)$u['id'] ?>" <?= $prefillTo === (int)$u['id'] ? 'selected' : '' ?>><?= Helpers::e($u['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>الأقسام <small style="color:#6b7280;font-weight:normal">(إرسال لجميع موظفي القسم النشطين)</small></label>
      <select name="department_ids[]" multiple data-placeholder="اختر قسماً أو أكثر (اختياري)" style="width:100%">
        <?php foreach ($departments as $d): ?>
        <option value="<?= (int)$d['id'] ?>"><?= Helpers::e($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>مجموعات البريد</label>
      <select name="group_ids[]" multiple data-placeholder="اختر مجموعة أو أكثر (اختياري)" style="width:100%">
        <?php foreach ($groups as $g): ?>
        <option value="<?= (int)$g['id'] ?>"><?= Helpers::e($g['name']) ?> (<?= (int)$g['members_count'] ?> عضو)</option>
        <?php endforeach; ?>
      </select>
      <small style="color:#6b7280">لا توجد مجموعة مناسبة؟ <a href="/mail/groups/create">أنشئ مجموعة جديدة</a></small>
    </div>
    <div class="form-group">
      <label>الموضوع *</label>
      <input type="text" name="subject" class="form-control" required value="<?= Helpers::e($prefillSubject) ?>">
    </div>
    <div class="form-group">
      <label>نص الرسالة *</label>
      <textarea name="body" class="form-control" rows="8" required></textarea>
    </div>
    <div class="form-group">
      <label>المرفقات</label>
      <input type="file" name="attachments[]" multiple class="form-control">
      <small style="color:#6b7280">PDF، صور (JPG/PNG/WEBP)، أو مستندات Word — بحد أقصى 10MB لكل ملف</small>
    </div>
    <div style="display:flex;gap:8px;margin-top:16px">
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> إرسال</button>
      <a href="/mail" class="btn btn-secondary">إلغاء</a>
    </div>
  </form>
</div>
<script>
document.getElementById('mail-compose-form').addEventListener('submit', function (e) {
  var hasRecipients = document.querySelector('select[name="recipients[]"]').selectedOptions.length > 0;
  var hasDepts = document.querySelector('select[name="department_ids[]"]').selectedOptions.length > 0;
  var hasGroups = document.querySelector('select[name="group_ids[]"]').selectedOptions.length > 0;
  if (!hasRecipients && !hasDepts && !hasGroups) {
    e.preventDefault();
    alert('يجب اختيار مستلم واحد على الأقل (فرد أو قسم أو مجموعة)');
  }
});
</script>
