<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>تعديل مركز الكلفة: <?= Helpers::e($center['name']) ?></h3>
  <form method="post" action="/finance/cost-centers/<?= $center['id'] ?>/edit">
    <div class="form-row"><label>الاسم</label><input name="name" value="<?= Helpers::e($center['name']) ?>" required></div>
    <div class="form-row"><label>الكود</label><input name="code" value="<?= Helpers::e($center['code'] ?? '') ?>"></div>
    <div class="form-row"><label>الوصف</label><textarea name="description" rows="2"><?= Helpers::e($center['description'] ?? '') ?></textarea></div>
    <div class="form-row"><label>الأب</label>
      <select name="parent_id">
        <option value="">—</option>
        <?php foreach ($costCenters as $c): if ($c['id'] == $center['id']) continue; ?>
        <option value="<?= $c['id'] ?>" <?= ($center['parent_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= Helpers::e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>المدير</label>
      <select name="manager_id">
        <option value="">—</option>
        <?php foreach ($users as $u): ?>
        <option value="<?= $u['id'] ?>" <?= ($center['manager_id'] ?? null) == $u['id'] ? 'selected' : '' ?>><?= Helpers::e($u['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>نشط</label>
      <select name="is_active">
        <option value="1" <?= $center['is_active'] ? 'selected' : '' ?>>نعم</option>
        <option value="0" <?= !$center['is_active'] ? 'selected' : '' ?>>لا</option>
      </select>
    </div>
    <button class="btn btn-warning">تحديث</button>
    <a href="/finance/cost-centers" class="btn">إلغاء</a>
  </form>
</div>
