<?php use App\Core\{Helpers, Auth}; ?>
<style>
.modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center; }
.modal-overlay.active { display:flex; }
.modal-content { background:#fff; border-radius:8px; width:92%; max-width:700px; max-height:86vh; overflow-y:auto; box-shadow:0 10px 25px rgba(0,0,0,0.2); }
.modal-header { display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid #e5e7eb; }
.modal-header h4 { margin:0; }
.modal-close { cursor:pointer; font-size:20px; color:#6b7280; }
.modal-body { padding:16px; }
</style>
<div class="card">
  <h3><i class="fa-solid fa-briefcase"></i> الوظائف</h3>
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <a href="/hr/employees" class="btn btn-sm btn-secondary">← الموظفون</a>
    <a href="/hr/jobs/export?format=excel" class="btn btn-sm btn-primary"><i class="fa-solid fa-file-excel"></i> Excel</a>
    <a href="/hr/jobs/export?format=pdf" class="btn btn-sm btn-danger"><i class="fa-solid fa-file-pdf"></i> PDF</a>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <h4>إضافة وظيفة</h4>
  <form method="post" action="/hr/jobs" class="form-inline">
    <div class="form-row"><label>المسمى الوظيفي</label><input name="title" required></div>
    <div class="form-row"><label>القسم</label>
      <select name="department_id">
        <option value="">—</option>
        <?php foreach ($departments as $d): ?>
        <option value="<?= $d['id'] ?>"><?= Helpers::e($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الدور/الصلاحية</label>
      <select name="role_id" id="job-add-role">
        <option value="">—</option>
        <?php foreach ($roles as $r): ?>
        <option value="<?= $r['id'] ?>"><?= Helpers::e($r['name']) ?> (مستوى <?= $r['level'] ?>)</option>
        <?php endforeach; ?>
      </select>
      <?php if (Auth::can('admin.users')): ?>
      <button type="button" class="btn btn-sm btn-secondary" onclick="openRoleModalFromSelect('job-add-role')">تحديد الصلاحيات</button>
      <?php endif; ?>
    </div>
    <div class="form-row"><label>الرمز</label><input name="code"></div>
    <button class="btn btn-success">إضافة</button>
  </form>
</div>

<div class="card" style="margin-top:16px">
  <h4><i class="fa-solid fa-file-excel"></i> استيراد من Excel</h4>
  <p style="font-size:13px;color:#6b7280">
    يتوقع الملف عمود <strong>القسم</strong> ثم أعمدة الوظائف الخاصة به.
    <a href="/hr/jobs/template" class="btn btn-sm btn-secondary">تحميل نموذج Excel</a>
  </p>
  <form method="post" action="/hr/jobs/import" enctype="multipart/form-data" class="form-inline">
    <div class="form-row"><label>ملف Excel</label><input type="file" name="import_file" accept=".xlsx,.xls,.csv" required></div>
    <button class="btn btn-success">استيراد</button>
  </form>
</div>

<div class="card" style="margin-top:16px">
  <h4>الوظائف</h4>
  <form method="get" action="/hr/jobs" class="form-inline">
    <div class="form-row"><label>الفترة (للعدد المخطط)</label><input type="month" name="period" value="<?= Helpers::e($period) ?>"></div>
    <button class="btn">عرض</button>
  </form>
  <div style="overflow:auto">
    <table>
      <tr><th>#</th><th>المسمى الوظيفي</th><th>القسم</th><th>الدور</th><th>الرمز</th><th>العدد الفعلي</th><th>العدد المخطط له</th><th>حفظ</th><th>حذف</th></tr>
      <?php foreach ($jobs as $j): ?>
      <tr>
        <td><?= $j['id'] ?></td>
        <td><input name="title" form="job-form-<?= $j['id'] ?>" value="<?= Helpers::e($j['title']) ?>" required></td>
        <td>
          <select name="department_id" form="job-form-<?= $j['id'] ?>">
            <option value="">—</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= ((int)($j['department_id'] ?? 0) === (int)$d['id']) ? 'selected' : '' ?>><?= Helpers::e($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td>
          <select name="role_id" id="job-role-<?= $j['id'] ?>" form="job-form-<?= $j['id'] ?>">
            <option value="">—</option>
            <?php foreach ($roles as $r): ?>
            <option value="<?= $r['id'] ?>" <?= ((int)($j['role_id'] ?? 0) === (int)$r['id']) ? 'selected' : '' ?>><?= Helpers::e($r['name']) ?> (مستوى <?= $r['level'] ?>)</option>
            <?php endforeach; ?>
          </select>
          <?php if (Auth::can('admin.users')): ?>
          <button type="button" class="btn btn-sm btn-secondary" onclick="openRoleModalFromSelect('job-role-<?= $j['id'] ?>')">تحديد الصلاحيات</button>
          <?php endif; ?>
        </td>
        <td><input name="code" form="job-form-<?= $j['id'] ?>" value="<?= Helpers::e($j['code'] ?? '') ?>"></td>
        <td style="text-align:center"><?= (int)($j['actual_count'] ?? 0) ?></td>
        <td style="text-align:center"><?= (int)($j['planned_count'] ?? 0) ?></td>
        <td>
          <form id="job-form-<?= $j['id'] ?>" method="post" action="/hr/jobs/<?= $j['id'] ?>" style="display:inline">
            <input type="hidden" name="is_active" value="<?= $j['is_active'] ? 1 : 0 ?>">
            <button class="btn btn-sm btn-primary">حفظ</button>
          </form>
        </td>
        <td>
          <form method="post" action="/hr/jobs/<?= $j['id'] ?>/delete" style="display:inline" onsubmit="return confirm('هل تريد حذف الوظيفة؟ سيتم إلغاء ربط الموظفين بها.')">
            <button class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$jobs): ?><tr><td colspan="9">لا توجد وظائف</td></tr><?php endif; ?>
    </table>
  </div>
</div>

<?php if (Auth::can('admin.users')): ?>
<?php
  $byModule = [];
  foreach ($permissions as $p) { $byModule[$p['module']][] = $p; }
?>
<?php foreach ($roles as $r): ?>
<div id="roleModal<?= $r['id'] ?>" class="modal-overlay" onclick="if(event.target.id === 'roleModal<?= $r['id'] ?>') closeRoleModal(<?= $r['id'] ?>)">
  <div class="modal-content">
    <div class="modal-header">
      <h4>تحديد صلاحيات الدور: <?= Helpers::e($r['name']) ?></h4>
      <span class="modal-close" onclick="closeRoleModal(<?= $r['id'] ?>)">&times;</span>
    </div>
    <div class="modal-body">
      <form method="post" action="/admin/roles/<?= $r['id'] ?>/permissions">
        <input type="hidden" name="return_url" value="/hr/jobs?period=<?= urlencode($period) ?>">
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
  if (!id) { alert('اختر دوراً أولاً'); return; }
  var el = document.getElementById('roleModal' + id);
  if (el) el.classList.add('active');
}
function closeRoleModal(id) {
  var el = document.getElementById('roleModal' + id);
  if (el) el.classList.remove('active');
}
function openRoleModalFromSelect(selectId) {
  var select = document.getElementById(selectId);
  openRoleModal(select ? select.value : null);
}
</script>
<?php endif; ?>
