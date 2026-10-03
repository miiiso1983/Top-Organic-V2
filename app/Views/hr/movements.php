<?php use App\Core\Helpers; use App\Core\Auth;
$fileStatusLabels = ['open' => 'مفتوح', 'closed' => 'مغلق', 'archived' => 'مأرشف'];
$statusLabels = ['active' => 'نشط', 'vacation' => 'إجازة', 'suspended' => 'موقوف', 'terminated' => 'منتهي الخدمة', 'resigned' => 'مستقيل'];
$canManage = Auth::can('hr.file.manage');
?>
<?php if (!empty($importErrors)): ?>
<div class="alert alert-danger">
  <strong>أخطاء الاستيراد:</strong>
  <ul><?php foreach ($importErrors as $err): ?><li><?= Helpers::e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="card">
  <h3>المباشرات والإغلاقات</h3>
  <form method="get" action="/hr/movements" class="form-inline">
    <div class="form-row"><label>الفترة</label><input type="month" name="period" value="<?= Helpers::e($period) ?>"></div>
    <button class="btn">عرض</button>
  </form>
  <p style="margin-top:10px"><a href="/hr/employees">← العودة إلى الموظفين</a></p>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h3><i class="fa-solid fa-user-plus"></i> المباشرات <?= Helpers::e($period) ?> (<?= count($starters) ?>)</h3>
    <div>
      <a href="/hr/movements/export?type=starters&period=<?= urlencode($period) ?>&format=excel" class="btn btn-sm btn-primary"><i class="fa-solid fa-file-excel"></i> Excel</a>
      <a href="/hr/movements/export?type=starters&period=<?= urlencode($period) ?>&format=pdf" class="btn btn-sm btn-danger"><i class="fa-solid fa-file-pdf"></i> PDF</a>
    </div>
  </div>
  <?php if ($canManage): ?>
  <form method="post" action="/hr/movements/import/starters" enctype="multipart/form-data" class="form-inline" style="margin:10px 0">
    <div class="form-row"><label>استيراد مباشرات (Excel)</label><input type="file" name="import_file" accept=".xlsx,.xls" required></div>
    <button class="btn btn-success"><i class="fa-solid fa-file-import"></i> استيراد</button>
    <a href="/hr/movements/template/starters" class="btn btn-outline" style="margin-inline-start:10px">تحميل قالب</a>
  </form>
  <?php endif; ?>
  <table>
    <tr><th>الكود</th><th>الاسم</th><th>الهاتف</th><th>الجنسية</th><th>السكن</th><th>القسم</th><th>الوظيفة</th><th>الفرع</th><th>تاريخ المباشرة</th><th>الراتب الأساسي</th><th>حالة الملف</th></tr>
    <?php $totalSalary = 0; foreach ($starters as $s): $totalSalary += (float)$s['base_salary']; ?>
    <tr>
      <td><?= Helpers::e($s['emp_code']) ?></td>
      <td><a href="/hr/employees/<?= (int)$s['id'] ?>"><?= Helpers::e($s['full_name']) ?></a></td>
      <td><?= Helpers::e($s['phone'] ?? '-') ?></td>
      <td><?= Helpers::e($s['nationality'] ?? '-') ?></td>
      <td><?= Helpers::e($s['residence'] ?? '-') ?></td>
      <td><?= Helpers::e($s['department'] ?? '-') ?></td>
      <td><?= Helpers::e($s['position'] ?? '-') ?></td>
      <td><?= Helpers::e($s['branch_name'] ?? '-') ?></td>
      <td><?= Helpers::e($s['hire_date']) ?></td>
      <td><?= Helpers::money($s['base_salary']) ?></td>
      <td><span class="badge badge-<?= $s['file_status'] === 'open' ? 'success' : 'secondary' ?>"><?= $fileStatusLabels[$s['file_status']] ?? $s['file_status'] ?></span></td>
    </tr>
    <?php endforeach; ?>
    <?php if ($starters): ?>
    <tr style="font-weight:bold"><td colspan="9">الإجمالي</td><td><?= Helpers::money($totalSalary) ?></td><td></td></tr>
    <?php else: ?>
    <tr><td colspan="11">لا توجد مباشرات في هذه الفترة</td></tr>
    <?php endif; ?>
  </table>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h3><i class="fa-solid fa-user-minus"></i> الإغلاقات <?= Helpers::e($period) ?> (<?= count($closures) ?>)</h3>
    <div>
      <a href="/hr/movements/export?type=closures&period=<?= urlencode($period) ?>&format=excel" class="btn btn-sm btn-primary"><i class="fa-solid fa-file-excel"></i> Excel</a>
      <a href="/hr/movements/export?type=closures&period=<?= urlencode($period) ?>&format=pdf" class="btn btn-sm btn-danger"><i class="fa-solid fa-file-pdf"></i> PDF</a>
    </div>
  </div>
  <?php if ($canManage): ?>
  <form method="post" action="/hr/movements/import/closures" enctype="multipart/form-data" class="form-inline" style="margin:10px 0" onsubmit="return confirm('سيتم إغلاق ملفات الموظفين المذكورين في الملف. هل تريد المتابعة؟')">
    <div class="form-row"><label>استيراد إغلاقات (Excel)</label><input type="file" name="import_file" accept=".xlsx,.xls" required></div>
    <button class="btn btn-warning"><i class="fa-solid fa-file-import"></i> استيراد</button>
    <a href="/hr/movements/template/closures" class="btn btn-outline" style="margin-inline-start:10px">تحميل قالب</a>
  </form>
  <?php endif; ?>
  <table>
    <tr><th>الكود</th><th>الاسم</th><th>القسم</th><th>الوظيفة</th><th>تاريخ المباشرة</th><th>تاريخ الإغلاق</th><th>حالة الملف</th><th>الحالة</th><th>السبب</th><th>بواسطة</th></tr>
    <?php foreach ($closures as $c): ?>
    <tr>
      <td><?= Helpers::e($c['emp_code']) ?></td>
      <td><a href="/hr/employees/<?= (int)$c['id'] ?>"><?= Helpers::e($c['full_name']) ?></a></td>
      <td><?= Helpers::e($c['department'] ?? '-') ?></td>
      <td><?= Helpers::e($c['position'] ?? '-') ?></td>
      <td><?= Helpers::e($c['hire_date'] ?? '-') ?></td>
      <td><?= Helpers::e($c['close_date']) ?></td>
      <td><span class="badge badge-<?= $c['new_status'] === 'archived' ? 'secondary' : 'danger' ?>"><?= $fileStatusLabels[$c['new_status']] ?? $c['new_status'] ?></span></td>
      <td><?= $statusLabels[$c['status']] ?? Helpers::e($c['status']) ?></td>
      <td><?= Helpers::e($c['reason'] ?? '-') ?></td>
      <td><?= Helpers::e($c['changed_by_name'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$closures): ?>
    <tr><td colspan="10">لا توجد إغلاقات في هذه الفترة</td></tr>
    <?php endif; ?>
  </table>
</div>

<div class="card">
  <h4>تعليمات أعمدة الاستيراد</h4>
  <p><strong>المباشرات:</strong> <code>full_name</code> و <code>hire_date</code> إلزاميان. <code>emp_code</code> اختياري (يُولَّد تلقائيًا إن تُرك فارغًا؛ إن وُجد الكود أو الهاتف مسبقًا يُحدَّث الموظف ويُعاد فتح ملفه). الأعمدة الأخرى: <code>phone</code>، <code>nationality</code>، <code>residence</code>، <code>department</code>، <code>job</code>، <code>branch_name</code>، <code>base_salary</code>.</p>
  <p><strong>الإغلاقات:</strong> <code>emp_code</code> إلزامي. <code>close_date</code> (تاريخ الإغلاق الفعلي)، <code>reason</code> (السبب)، <code>file_status</code> = closed أو archived، <code>status</code> = resigned أو terminated أو suspended. لا يُغلق ملف الموظف الذي لديه عهد غير مسددة.</p>
</div>
