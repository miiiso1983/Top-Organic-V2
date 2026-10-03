<?php use App\Core\Helpers; ?>
<?php $type = $type ?? []; ?>
<div class="form-grid">
  <div class="form-row">
    <label>الرمز (code)</label>
    <input name="code" value="<?= Helpers::e($type['code'] ?? '') ?>" <?= !empty($type['id']) ? 'readonly' : '' ?> required placeholder="vip">
  </div>
  <div class="form-row">
    <label>الاسم بالعربية</label>
    <input name="name_ar" value="<?= Helpers::e($type['name_ar'] ?? '') ?>" required>
  </div>
  <div class="form-row">
    <label>الاسم بالإنجليزية</label>
    <input name="name_en" value="<?= Helpers::e($type['name_en'] ?? '') ?>">
  </div>
  <div class="form-row">
    <label>أيقونة</label>
    <input name="icon" value='<?= ($type['icon'] ?? '<i class="fa-solid fa-utensils"></i>') ?>'>
  </div>
  <div class="form-row">
    <label>اللون</label>
    <input type="color" name="color" value="<?= Helpers::e($type['color'] ?? '#10914d') ?>">
  </div>
  <div class="form-row">
    <label>الحد الأدنى للضيوف</label>
    <input type="number" name="min_guests" value="<?= (int)($type['min_guests'] ?? 1) ?>" min="1">
  </div>
  <div class="form-row">
    <label>الحد الأقصى للضيوف</label>
    <input type="number" name="max_guests" value="<?= (int)($type['max_guests'] ?? 50) ?>" min="1">
  </div>
  <div class="form-row">
    <label>العدد الافتراضي</label>
    <input type="number" name="default_guests" value="<?= (int)($type['default_guests'] ?? 2) ?>" min="1">
  </div>
  <div class="form-row">
    <label>المدة (دقيقة)</label>
    <input type="number" name="duration_minutes" value="<?= (int)($type['duration_minutes'] ?? 90) ?>" min="15">
  </div>
  <div class="form-row">
    <label>الحجز المسبق (ساعات)</label>
    <input type="number" name="min_advance_hours" value="<?= (int)($type['min_advance_hours'] ?? 0) ?>" min="0">
  </div>
  <div class="form-row">
    <label>أقصى حجز مسبق (أيام)</label>
    <input type="number" name="max_advance_days" value="<?= (int)($type['max_advance_days'] ?? 30) ?>" min="1">
  </div>
  <div class="form-row">
    <label>السعر للشخص</label>
    <input type="number" step="0.01" name="price_per_person" value="<?= Helpers::e($type['price_per_person'] ?? '') ?>">
  </div>
  <div class="form-row">
    <label>قيمة العربون</label>
    <input type="number" step="0.01" name="deposit_amount" value="<?= Helpers::e($type['deposit_amount'] ?? '') ?>">
  </div>
  <div class="form-row">
    <label>الترتيب</label>
    <input type="number" name="sort_order" value="<?= (int)($type['sort_order'] ?? 0) ?>">
  </div>
  <div class="form-row">
    <label>قالب Workflow</label>
    <select name="workflow_template_id">
      <option value="">—</option>
      <?php foreach ($templates as $tpl): ?>
        <option value="<?= $tpl['id'] ?>" <?= ($type['workflow_template_id'] ?? 0) == $tpl['id'] ? 'selected' : '' ?>><?= Helpers::e($tpl['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-row">
    <label>صلاحية الإدارة</label>
    <input name="permission_code" value="<?= Helpers::e($type['permission_code'] ?? '') ?>" placeholder="reservations.vip.manage">
  </div>
</div>
<div style="margin-top:12px">
  <label><input type="checkbox" name="requires_approval" value="1" <?= !empty($type['requires_approval']) ? 'checked' : '' ?>> يتطلب موافقة Workflow</label><br>
  <label><input type="checkbox" name="deposit_required" value="1" <?= !empty($type['deposit_required']) ? 'checked' : '' ?>> يتطلب عربون</label><br>
  <label><input type="checkbox" name="requires_contract" value="1" <?= !empty($type['requires_contract']) ? 'checked' : '' ?>> يتطلب عقد</label><br>
  <label><input type="checkbox" name="requires_manager_confirmation" value="1" <?= !empty($type['requires_manager_confirmation']) ? 'checked' : '' ?>> يتطلب تأكيد مدير</label><br>
  <label><input type="checkbox" name="allows_partial_payment" value="1" <?= !empty($type['allows_partial_payment']) ? 'checked' : '' ?>> يسمح بالدفع الجزئي</label><br>
  <label><input type="checkbox" name="is_active" value="1" <?= !isset($type['is_active']) || $type['is_active'] ? 'checked' : '' ?>> مفعل</label>
</div>
<div class="form-grid" style="margin-top:12px">
  <div class="form-row" style="grid-column:1/-1">
    <label>متطلبات / شروط</label>
    <textarea name="requirements" rows="2"><?= Helpers::e($type['requirements'] ?? '') ?></textarea>
  </div>
  <div class="form-row" style="grid-column:1/-1">
    <label>قالب رسالة التأكيد</label>
    <textarea name="message_template_confirm" rows="2"><?= Helpers::e($type['message_template_confirm'] ?? '') ?></textarea>
  </div>
  <div class="form-row" style="grid-column:1/-1">
    <label>قالب رسالة التذكير</label>
    <textarea name="message_template_reminder" rows="2"><?= Helpers::e($type['message_template_reminder'] ?? '') ?></textarea>
  </div>
  <div class="form-row" style="grid-column:1/-1">
    <label>قالب رسالة الإلغاء</label>
    <textarea name="message_template_cancel" rows="2"><?= Helpers::e($type['message_template_cancel'] ?? '') ?></textarea>
  </div>
</div>
<div style="margin-top:16px">
  <button class="btn btn-primary">حفظ</button>
</div>
