<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>إدارة أنواع التطبيقات</h3>
  <p class="text-muted">أضف التطبيقات المتصلة بمبيعات المطعم (HungerStation, Jahez, Mrsool...).</p>

  <form method="post" action="/admin/app-types" class="form-inline" style="margin-bottom:16px; gap:8px;">
    <div class="form-row" style="margin:0">
      <label>اسم التطبيق</label>
      <input type="text" name="name" required placeholder="مثال: HungerStation">
    </div>
    <div class="form-row" style="margin:0">
      <label>الكود (اختياري)</label>
      <input type="text" name="code" placeholder="مثال: hungerstation">
    </div>
    <button class="btn btn-success" style="align-self:flex-end">إضافة</button>
  </form>

  <table>
    <tr><th>التطبيق</th><th>الكود</th><th>الحالة</th><th>إجراء</th></tr>
    <?php foreach ($appTypes as $at): ?>
    <form id="form-update-<?= $at['id'] ?>" method="post" action="/admin/app-types/<?= $at['id'] ?>" style="display:none"></form>
    <tr>
      <td><input type="text" name="name" form="form-update-<?= $at['id'] ?>" value="<?= Helpers::e($at['name']) ?>" required style="width:100%"></td>
      <td><input type="text" name="code" form="form-update-<?= $at['id'] ?>" value="<?= Helpers::e($at['code'] ?? '') ?>" style="width:100%"></td>
      <td>
        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; justify-content:center;">
          <input type="checkbox" name="is_active" form="form-update-<?= $at['id'] ?>" value="1" <?= $at['is_active'] ? 'checked' : '' ?>> فعال
        </label>
      </td>
      <td>
        <div style="display:flex; gap:6px;">
          <button class="btn btn-sm btn-success" type="submit" form="form-update-<?= $at['id'] ?>">حفظ</button>
          <form method="post" action="/admin/app-types/<?= $at['id'] ?>/delete" onsubmit="return confirm('تأكيد الحذف أو التعطيل؟')">
            <button class="btn btn-sm btn-danger">حذف</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$appTypes): ?><tr><td colspan="4">لا يوجد تطبيقات</td></tr><?php endif; ?>
  </table>
</div>
