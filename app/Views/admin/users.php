<?php use App\Core\Helpers; ?>
<style>
.modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center; }
.modal-overlay.active { display:flex; }
.modal-content { background:#fff; border-radius:8px; width:92%; max-width:800px; max-height:86vh; overflow-y:auto; box-shadow:0 10px 25px rgba(0,0,0,0.2); }
.modal-header { display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid #e5e7eb; }
.modal-header h4 { margin:0; }
.modal-close { cursor:pointer; font-size:20px; color:#6b7280; }
.modal-body { padding:16px; }
</style>
<div class="card">
  <h3>إنشاء مستخدم جديد</h3>
  <form method="post" action="/admin/users" class="form-inline">
    <div class="form-row"><label>اسم المستخدم</label><input name="username" required></div>
    <div class="form-row"><label>كلمة المرور</label><input type="password" name="password" required></div>
    <div class="form-row"><label>الاسم الكامل</label><input name="full_name" required></div>
    <div class="form-row"><label>الدور</label>
      <select name="role_id" required>
        <?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>"><?= Helpers::e($r['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الفرع</label>
      <select name="branch_id">
        <option value="">— كل الفروع —</option>
        <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= Helpers::e($b['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-success">إنشاء</button>
  </form>
</div>

<div class="card">
  <h3>المستخدمون</h3>
  <table>
    <tr><th>اسم المستخدم</th><th>الاسم</th><th>الدور</th><th>الفرع</th><th>نشط</th><th>إجراءات</th></tr>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?= Helpers::e($u['username']) ?></td>
      <td><?= Helpers::e($u['full_name']) ?></td>
      <td><span class="badge badge-info"><?= Helpers::e($u['role_name']) ?></span></td>
      <td><?= Helpers::e($u['branch_name'] ?? 'الكل') ?></td>
      <td><?= $u['is_active'] ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-xmark"></i>' ?></td>
      <td>
        <div style="display:flex; gap:6px; flex-wrap:wrap;">
          <a href="/admin/users/<?= (int)$u['id'] ?>/edit" class="btn btn-sm btn-secondary">تعديل</a>
          <form method="post" action="/admin/users/<?= (int)$u['id'] ?>/delete" style="margin:0"
                onsubmit="return confirm('تأكيد حذف المستخدم <?= Helpers::e($u['full_name']) ?>؟')">
            <button class="btn btn-sm btn-danger">حذف</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="card">
  <h3>إنشاء دور جديد</h3>
  <form method="post" action="/admin/roles" class="form-inline" style="gap:8px;">
    <div class="form-row" style="margin:0"><label>اسم الدور</label><input type="text" name="name" required placeholder="مثال: كابتن"></div>
    <div class="form-row" style="margin:0"><label>المستوى</label><input type="number" name="level" value="0" style="width:80px"></div>
    <div class="form-row" style="margin:0"><label>الوصف (اختياري)</label><input type="text" name="description" placeholder="وصف مختصر للدور"></div>
    <button class="btn btn-success" style="align-self:flex-end">إنشاء دور</button>
  </form>
</div>

<div class="card">
  <h3>الأدوار والصلاحيات</h3>
  <?php
    $byModule = [];
    foreach ($permissions as $p) { $byModule[$p['module']][] = $p; }
  ?>
  <table>
    <tr><th>الاسم</th><th>المستوى</th><th>الوصف</th><th>عدد الصلاحيات</th><th>إجراءات</th></tr>
    <?php foreach ($roles as $r): ?>
    <tr>
      <td><?= Helpers::e($r['name']) ?></td>
      <td><?= (int)$r['level'] ?></td>
      <td><?= Helpers::e($r['description'] ?? '') ?></td>
      <td><?= count($rolePerms[$r['id']] ?? []) ?></td>
      <td>
        <div style="display:flex; gap:6px; flex-wrap:wrap;">
          <button type="button" class="btn btn-sm btn-primary" onclick="openRoleModal(<?= $r['id'] ?>)">تحديد الصلاحيات</button>
          <form method="post" action="/admin/roles/<?= $r['id'] ?>/delete" style="margin:0"
                onsubmit="return confirm('تأكيد حذف الدور <?= Helpers::e($r['name']) ?>؟')">
            <button class="btn btn-sm btn-danger" type="submit">حذف</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php foreach ($roles as $r): ?>
<div id="roleModal<?= $r['id'] ?>" class="modal-overlay" onclick="if(event.target.id === 'roleModal<?= $r['id'] ?>') closeRoleModal(<?= $r['id'] ?>)">
  <div class="modal-content">
    <div class="modal-header">
      <h4>تعديل الدور وصلاحياته: <?= Helpers::e($r['name']) ?></h4>
      <span class="modal-close" onclick="closeRoleModal(<?= $r['id'] ?>)">&times;</span>
    </div>
    <div class="modal-body">
      <form method="post" action="/admin/roles/<?= $r['id'] ?>" class="form-inline" style="gap:8px; margin:0 0 12px;">
        <div class="form-row" style="margin:0"><label>الاسم</label><input type="text" name="name" value="<?= Helpers::e($r['name']) ?>" required></div>
        <div class="form-row" style="margin:0"><label>المستوى</label><input type="number" name="level" value="<?= (int)$r['level'] ?>" style="width:80px"></div>
        <div class="form-row" style="margin:0"><label>الوصف</label><input type="text" name="description" value="<?= Helpers::e($r['description'] ?? '') ?>"></div>
        <button class="btn btn-sm btn-secondary" type="submit">حفظ بيانات الدور</button>
      </form>
      <form method="post" action="/admin/roles/<?= $r['id'] ?>/permissions">
        <?php foreach ($byModule as $module => $perms): ?>
        <fieldset style="border:1px solid #e5e7eb;border-radius:8px;margin-bottom:8px;padding:8px">
          <legend style="font-weight:bold"><?= Helpers::e($module) ?></legend>
          <?php foreach ($perms as $p): ?>
          <label style="display:inline-block;margin-inline-end:14px">
            <input type="checkbox" name="perm_ids[]" value="<?= $p['id'] ?>"
              <?= in_array($p['id'], $rolePerms[$r['id']] ?? []) ? 'checked' : '' ?>>
            <?= Helpers::e($p['name']) ?>
          </label>
          <?php endforeach; ?>
        </fieldset>
        <?php endforeach; ?>

        <?php if (!empty($workflowTemplates)): ?>
        <fieldset style="border:1px solid #e5e7eb;border-radius:8px;margin-bottom:8px;padding:8px">
          <legend style="font-weight:bold">قوالب سير العمل (أنواع الطلبات)</legend>
          <p class="text-muted" style="margin-bottom:8px;font-size:13px">
            تحدد أي أنواع الطلبات يستطيع هذا الدور <strong>إنشاءها</strong> أو <strong>عرض جميع طلباتها</strong> في التطبيق والويب.
            ملاحظة: طلب الإجازة (hr_leave) وطلب السلفة (hr_advance) يظهران دائمًا في الموبايل بغض النظر عن هذه الإعدادات.
          </p>
          <table style="width:100%;font-size:14px">
            <tr><th style="text-align:right">القالب</th><th style="width:100px">إنشاء</th><th style="width:100px">عرض الكل</th></tr>
            <?php foreach ($workflowTemplates as $t): ?>
            <?php $tr = $templateRolePerms[$r['id']][$t['id']] ?? null; ?>
            <tr>
              <td><?= Helpers::e($t['name']) ?> <small class="text-muted">(<?= Helpers::e($t['code']) ?>)</small></td>
              <td>
                <input type="checkbox" name="template_perms[<?= $t['id'] ?>][can_create]" value="1"
                  <?= !empty($tr['can_create']) ? 'checked' : '' ?>>
              </td>
              <td>
                <input type="checkbox" name="template_perms[<?= $t['id'] ?>][can_view_all]" value="1"
                  <?= !empty($tr['can_view_all']) ? 'checked' : '' ?>>
              </td>
            </tr>
            <?php endforeach; ?>
          </table>
        </fieldset>
        <?php endif; ?>

        <button class="btn btn-success">حفظ صلاحيات <?= Helpers::e($r['name']) ?></button>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script>
function openRoleModal(id) {
  var el = document.getElementById('roleModal' + id);
  if (el) el.classList.add('active');
}
function closeRoleModal(id) {
  var el = document.getElementById('roleModal' + id);
  if (el) el.classList.remove('active');
}
(function () {
  var params = new URLSearchParams(window.location.search);
  var openRoleId = params.get('open_role');
  if (openRoleId) openRoleModal(parseInt(openRoleId, 10));
})();
</script>
