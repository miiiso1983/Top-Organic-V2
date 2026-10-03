<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2>قوالب سير العمل</h2>
  <a href="/workflows" class="btn btn-secondary">رجوع</a>
</div>

<div class="card">
  <h3>قالب جديد</h3>
  <form method="post" action="/workflows/templates" class="form-inline">
    <div class="form-row"><label>الكود</label><input name="code" placeholder="purchase_request" required></div>
    <div class="form-row"><label>اسم القالب</label><input name="name" placeholder="طلب شراء" required></div>
    <div class="form-row"><label>الأيقونة</label><input name="icon" value='<i class="fa-solid fa-check"></i>'></div>
    <div class="form-row" style="min-width:100%"><label>الوصف</label><textarea name="description" rows="2"></textarea></div>
    <div class="form-row"><label>حقل المبلغ</label>
      <select name="amount_field"><option value="">لا يوجد</option></select>
    </div>
    <div class="form-row" style="min-width:100%"><label>الأدوار المسموح بإنشاء الطلب</label>
      <?php foreach ($roles as $role): ?>
      <label style="display:inline; margin-left:12px"><input type="checkbox" name="allowed_roles[<?= $role['id'] ?>][role_id]" value="<?= $role['id'] ?>"> <?= Helpers::e($role['name']) ?></label>
      <?php endforeach; ?>
    </div>
    <div id="fields-box" style="min-width:100%; margin-top:10px"></div>
    <button type="button" class="btn btn-secondary" onclick="addField()">+ إضافة حقل</button>
    <button class="btn btn-success">حفظ القالب</button>
  </form>
</div>

<div class="card">
  <h3>القوالب الموجودة</h3>
  <table>
    <tr><th>الاسم</th><th>الكود</th><th>الحالة</th><th>الخطوات</th><th></th></tr>
    <?php foreach ($templates as $t): ?>
    <tr>
      <td><?= Helpers::e($t['name']) ?></td>
      <td><?= Helpers::e($t['code']) ?></td>
      <td><?= $t['is_active'] ? 'فعال' : 'معطل' ?></td>
      <td><a href="/workflows/templates/<?= $t['id'] ?>/steps" class="btn btn-sm btn-secondary">الخطوات</a></td>
      <td>
        <form method="post" action="/workflows/templates/<?= $t['id'] ?>/delete" style="display:inline" onsubmit="return confirm('تأكيد الحذف؟')">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<script>
let fieldIndex = 0;
function updateAmountField() {
  const sel = document.querySelector('select[name=amount_field]');
  const current = sel.value;
  const names = Array.from(document.querySelectorAll('input[name="field_name[]"]')).map(i => i.value).filter(v => v);
  sel.innerHTML = '<option value="">لا يوجد</option>';
  names.forEach(n => {
    const opt = document.createElement('option');
    opt.value = n; opt.textContent = n;
    if (n === current) opt.selected = true;
    sel.appendChild(opt);
  });
}
function addField() {
  fieldIndex++;
  const div = document.createElement('div');
  div.className = 'form-inline';
  div.style.cssText = 'gap:8px; margin-bottom:8px; align-items:start';
  div.innerHTML = `
    <input name="field_name[]" placeholder="اسم الحقل" required style="min-width:120px" oninput="updateAmountField()">
    <input name="field_label[]" placeholder="الوصف العربي" required style="min-width:140px">
    <select name="field_type[]"><?php foreach (['text','number','date','select','textarea','checkbox','file'] as $type): ?><option value="<?= $type ?>"><?= $type ?></option><?php endforeach; ?></select>
    <label><input type="checkbox" name="field_required[]" value="1"> مطلوب</label>
    <input name="field_options[]" placeholder="خيارات مفصولة بفاصلة" style="min-width:160px">
    <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove(); updateAmountField()">حذف</button>
  `;
  document.getElementById('fields-box').appendChild(div);
  updateAmountField();
}
addField();
</script>
