<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>تحديث بطاقة تعريف الصنف</h3>
  <div class="grid grid-2">
    <div><strong>الباركود:</strong> <?= Helpers::e($item['barcode'] ?? '-') ?></div>
    <div><strong>اسم الصنف:</strong> <?= Helpers::e($item['name']) ?></div>
    <div><strong>التصنيف:</strong> <?= Helpers::e($item['category_name']) ?></div>
    <div><strong>الوحدة:</strong> <?= Helpers::e($item['unit_name']) ?></div>
  </div>
</div>

<div class="card">
  <h3>بيانات البطاقة</h3>
  <form method="post" action="/inventory/item-cards/<?= (int)$item['id'] ?>">
    <div class="grid grid-2">
      <div class="form-row">
        <label>رقم البطاقة</label>
        <input name="card_no" value="<?= Helpers::e($item['card_no'] ?? '') ?>" readonly>
      </div>
      <div class="form-row">
        <label>مدة التحضير (دقيقة)</label>
        <input type="number" name="prep_time_minutes" value="<?= (int)($item['prep_time_minutes'] ?? 0) ?>" min="0">
      </div>
      <div class="form-row">
        <label>المستودع الرئيسي</label>
        <select name="main_warehouse_id">
          <option value="">-- اختر --</option>
          <?php foreach ($warehouses as $w): ?>
          <option value="<?= (int)$w['id'] ?>" <?= ($item['main_warehouse_id'] ?? null) == $w['id'] ? 'selected' : '' ?>><?= Helpers::e($w['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label>مطبخ التحضير</label>
        <select name="kitchen_section_id">
          <option value="">-- اختر --</option>
          <?php foreach ($kitchenSections as $k): ?>
          <option value="<?= (int)$k['id'] ?>" <?= ($item['kitchen_section_id'] ?? null) == $k['id'] ? 'selected' : '' ?>><?= Helpers::e($k['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <label>الوصف / المواصفات</label>
      <textarea name="description" rows="5"><?= Helpers::e($item['description'] ?? '') ?></textarea>
    </div>
    <div class="form-inline" style="gap:8px; margin-top:12px;">
      <button class="btn btn-success">حفظ</button>
      <a href="/inventory/item-cards/<?= (int)$item['id'] ?>" class="btn btn-secondary">إلغاء</a>
    </div>
  </form>
</div>
