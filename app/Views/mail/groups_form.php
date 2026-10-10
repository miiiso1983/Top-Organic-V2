<?php use App\Core\Helpers; $isEdit = $group !== null; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-users-rectangle"></i> <?= $isEdit ? 'تعديل مجموعة' : 'مجموعة بريد جديدة' ?></h2>
  <a href="/mail/groups" class="btn btn-secondary"><i class="fa-solid fa-arrow-right"></i> رجوع</a>
</div>

<div class="card" style="max-width:600px">
  <form method="post" action="<?= $isEdit ? '/mail/groups/' . (int)$group['id'] : '/mail/groups' ?>">
    <div class="form-group">
      <label>اسم المجموعة *</label>
      <input type="text" name="name" class="form-control" required value="<?= Helpers::e($group['name'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>الأعضاء</label>
      <select name="member_ids[]" multiple data-placeholder="اختر الأعضاء" style="width:100%">
        <?php foreach ($users as $u): ?>
        <option value="<?= (int)$u['id'] ?>" <?= in_array((int)$u['id'], $memberIds, true) ? 'selected' : '' ?>><?= Helpers::e($u['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex;gap:8px;margin-top:16px">
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> حفظ</button>
      <a href="/mail/groups" class="btn btn-secondary">إلغاء</a>
    </div>
  </form>
</div>
