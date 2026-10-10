<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-pen"></i> رسالة بريد داخلية جديدة</h2>
  <a href="/mail" class="btn btn-secondary"><i class="fa-solid fa-arrow-right"></i> رجوع للوارد</a>
</div>

<div class="card" style="max-width:720px">
  <form method="post" action="/mail/compose">
    <div class="form-group">
      <label>المستلمون *</label>
      <select name="recipients[]" multiple required data-placeholder="اختر مستلماً واحداً أو أكثر" style="width:100%">
        <?php foreach ($users as $u): ?>
        <option value="<?= (int)$u['id'] ?>" <?= $prefillTo === (int)$u['id'] ? 'selected' : '' ?>><?= Helpers::e($u['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>الموضوع *</label>
      <input type="text" name="subject" class="form-control" required value="<?= Helpers::e($prefillSubject) ?>">
    </div>
    <div class="form-group">
      <label>نص الرسالة *</label>
      <textarea name="body" class="form-control" rows="8" required></textarea>
    </div>
    <div style="display:flex;gap:8px;margin-top:16px">
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> إرسال</button>
      <a href="/mail" class="btn btn-secondary">إلغاء</a>
    </div>
  </form>
</div>
