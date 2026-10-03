<?php use App\Core\Helpers; ?>
<div class="card">
  <h3><i class="fa-solid fa-building"></i> الأقسام</h3>
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <a href="/hr/employees" class="btn btn-sm btn-secondary">← الموظفون</a>
    <a href="/hr/departments/export?format=excel" class="btn btn-sm btn-primary"><i class="fa-solid fa-file-excel"></i> Excel</a>
    <a href="/hr/departments/export?format=pdf" class="btn btn-sm btn-danger"><i class="fa-solid fa-file-pdf"></i> PDF</a>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <h4>إضافة قسم</h4>
  <form method="post" action="/hr/departments" class="form-inline">
    <div class="form-row"><label>الاسم</label><input name="name" required></div>
    <div class="form-row"><label>الرمز</label><input name="code"></div>
    <button class="btn btn-success">إضافة</button>
  </form>
</div>

<div class="card" style="margin-top:16px">
  <h4><i class="fa-solid fa-file-excel"></i> استيراد من Excel</h4>
  <p style="font-size:13px;color:#6b7280">
    <a href="/hr/departments/template" class="btn btn-sm btn-secondary">تحميل نموذج Excel</a>
  </p>
  <form method="post" action="/hr/departments/import" enctype="multipart/form-data" class="form-inline">
    <div class="form-row"><label>ملف Excel</label><input type="file" name="import_file" accept=".xlsx,.xls,.csv" required></div>
    <button class="btn btn-success">استيراد</button>
  </form>
</div>

<div class="card" style="margin-top:16px">
  <h4>الأقسام</h4>
  <div style="overflow:auto">
    <table>
      <tr><th>#</th><th>الاسم</th><th>الرمز</th><th>حفظ</th><th>حذف</th></tr>
      <?php foreach ($departments as $d): ?>
      <tr>
        <td><?= $d['id'] ?></td>
        <td><input name="name" form="dept-form-<?= $d['id'] ?>" value="<?= Helpers::e($d['name']) ?>" required></td>
        <td><input name="code" form="dept-form-<?= $d['id'] ?>" value="<?= Helpers::e($d['code'] ?? '') ?>"></td>
        <td>
          <input type="hidden" name="is_active" form="dept-form-<?= $d['id'] ?>" value="<?= $d['is_active'] ? 1 : 0 ?>">
          <form id="dept-form-<?= $d['id'] ?>" method="post" action="/hr/departments/<?= $d['id'] ?>" style="display:inline">
            <button class="btn btn-sm btn-primary">حفظ</button>
          </form>
        </td>
        <td>
          <form method="post" action="/hr/departments/<?= $d['id'] ?>/delete" style="display:inline" onsubmit="return confirm('هل تريد حذف القسم؟ سيتم إلغاء ربط الموظفين والوظائف به.')">
            <button class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$departments): ?><tr><td colspan="5">لا توجد أقسام</td></tr><?php endif; ?>
    </table>
  </div>
</div>
