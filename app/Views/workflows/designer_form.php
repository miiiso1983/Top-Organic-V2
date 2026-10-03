<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2><?= $def ? 'تعديل تعريف' : 'تعريف Workflow جديد' ?></h2>
  <a href="/workflow-engine" class="btn btn-secondary">رجوع</a>
</div>

<div class="card">
  <form method="post" action="<?= $def ? '/workflow-engine/' . $def['id'] . '/update' : '/workflow-engine' ?>" class="form-inline">
    <div class="form-row"><label>الكود (إنجليزي)</label>
      <input name="code" value="<?= Helpers::e($def['code'] ?? '') ?>" placeholder="purchase_request_main" required>
    </div>
    <div class="form-row"><label>الاسم</label>
      <input name="name" value="<?= Helpers::e($def['name'] ?? '') ?>" placeholder="مسار موافقات طلبات الشراء" required>
    </div>
    <div class="form-row" style="min-width:100%"><label>الوصف</label>
      <textarea name="description" rows="2"><?= Helpers::e($def['description'] ?? '') ?></textarea>
    </div>
    <div class="form-row"><label>القالب المرتبط</label>
      <select name="template_id" id="templateSel">
        <option value="">— بدون ربط —</option>
        <?php foreach ($templates as $t): ?>
        <option value="<?= $t['id'] ?>" data-code="<?= Helpers::e($t['code']) ?>"
          <?= (int)($def['template_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>>
          <?= Helpers::e($t['name']) ?> (<?= Helpers::e($t['code']) ?>)
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>نوع الطلب</label>
      <input name="request_type" id="reqType" value="<?= Helpers::e($def['request_type'] ?? '') ?>" placeholder="يُملأ تلقائياً من القالب">
    </div>
    <div class="form-row" style="min-width:100%"><label>الفروع المطبقة (فارغ = الكل)</label>
      <?php foreach ($branches as $b): ?>
      <label style="display:inline; margin-left:12px">
        <input type="checkbox" name="branch_ids[]" value="<?= $b['id'] ?>"
          <?= in_array((int)$b['id'], json_decode($def['branch_ids'] ?? '[]', true) ?: [], true) ? 'checked' : '' ?>>
        <?= Helpers::e($b['name']) ?>
      </label>
      <?php endforeach; ?>
    </div>
    <div class="form-row"><label>الأولوية (الأصغر أولى)</label>
      <input type="number" name="priority" value="<?= (int)($def['priority'] ?? 100) ?>">
    </div>
    <div class="form-row"><label>صالح من</label>
      <input type="date" name="valid_from" value="<?= Helpers::e($def['valid_from'] ?? '') ?>">
    </div>
    <div class="form-row"><label>صالح إلى</label>
      <input type="date" name="valid_to" value="<?= Helpers::e($def['valid_to'] ?? '') ?>">
    </div>
    <div class="form-row"><label>الحالة</label>
      <label><input type="checkbox" name="is_active" value="1" <?= ($def['is_active'] ?? 1) ? 'checked' : '' ?>> فعال</label>
    </div>
    <button class="btn btn-success"><?= $def ? 'تحديث' : 'إنشاء وفتح المصمم' ?></button>
  </form>
</div>

<script>
document.getElementById('templateSel').addEventListener('change', function () {
  const opt = this.options[this.selectedIndex];
  const code = opt.getAttribute('data-code') || '';
  const req = document.getElementById('reqType');
  if (code && !req.value) req.value = code;
});
</script>
