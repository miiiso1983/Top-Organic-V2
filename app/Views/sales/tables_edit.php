<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2>تعديل طاولة: <?= Helpers::e($table['name']) ?></h2>
  <a href="/tables" class="btn btn-secondary">رجوع للطاولات</a>
</div>

<div class="card" style="max-width:600px">
  <form method="post" action="/tables/<?= (int)$table['id'] ?>/edit">
    <div class="form-row"><label>اسم الطاولة</label><input name="name" value="<?= Helpers::e($table['name']) ?>" required></div>
    <div class="form-row"><label>الصالة/القاعة</label><input name="hall" value="<?= Helpers::e($table['hall'] ?? '') ?>" placeholder="مثال: قاعة الإطلال"></div>
    <div class="form-row"><label>عدد الكراسي</label><input type="number" name="capacity" value="<?= (int)($table['capacity'] ?? $table['seats']) ?>" min="1" required></div>
    <div class="form-row"><label>الطابق</label>
      <select name="floor_id">
        <option value="">بدون تصنيف</option>
        <?php foreach ($floors as $f): ?>
          <option value="<?= $f['id'] ?>" <?= ($table['floor_id'] ?? null) == $f['id'] ? 'selected' : '' ?>><?= Helpers::e($f['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-success">حفظ التعديلات</button>
  </form>
</div>
