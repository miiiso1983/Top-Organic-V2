<?php use App\Core\{Helpers, ImportHelper, Auth}; ?>
<?php $importErrors = ImportHelper::getErrors(); ?>
<?php
$exportQuery = $_SERVER['QUERY_STRING'] ?? '';
$exportQueryExcel = $exportQuery ? $exportQuery . '&format=excel' : 'format=excel';
$exportQueryPdf = $exportQuery ? $exportQuery . '&format=pdf' : 'format=pdf';
?>
<?php if ($importErrors): ?>
<div class="alert alert-danger">
  <strong>أخطاء الاستيراد:</strong>
  <ul><?php foreach ($importErrors as $err): ?><li><?= Helpers::e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px">
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <a href="/module/hr" class="btn btn-secondary" title="العودة">
      <i class="fa-solid fa-arrow-right"></i> الموارد البشرية
    </a>
    <?php if (Auth::can('hr.manage') || Auth::can('hr.employee.create')): ?>
    <a href="/hr/employees/create" class="btn btn-success">
      <i class="fa-solid fa-folder-plus"></i> فتح ملف
    </a>
    <?php endif; ?>
    <a href="/hr/employees/logs" class="btn btn-secondary">
      <i class="fa-solid fa-clock-rotate-left"></i> سجل التغييرات
    </a>
    <a href="/hr/movements" class="btn btn-secondary">
      <i class="fa-solid fa-right-left"></i> المباشرات والإغلاقات
    </a>
    <a href="/hr/employees/export?<?= Helpers::e($exportQueryExcel) ?>" class="btn btn-primary">
      <i class="fa-solid fa-file-excel"></i> Excel
    </a>
    <a href="/hr/employees/export?<?= Helpers::e($exportQueryPdf) ?>" class="btn btn-danger">
      <i class="fa-solid fa-file-pdf"></i> PDF
    </a>
    <?php if (Auth::can('hr.manage')): ?>
    <form method="post" action="/hr/employees/sync-accounts" style="display:inline" onsubmit="return confirm('سيتم إنشاء حسابات مالية لكل موظف لا يملك حساباً. هل تريد المتابعة؟')">
      <button type="submit" class="btn btn-warning"><i class="fa-solid fa-rotate"></i> مزامنة الحسابات</button>
    </form>
    <a href="/hr/employees/template" class="btn btn-secondary" title="قالب فارغ لإضافة موظفين جدد">
      <i class="fa-solid fa-file-arrow-down"></i> قالب استيراد
    </a>
    <form method="post" action="/hr/employees/import" enctype="multipart/form-data" style="display:inline-flex;gap:6px;align-items:center" onsubmit="return confirm('سيتم تحديث بيانات الموظفين الموجودين وإضافة الجدد وفق بيانات الملف المرفوع. هل تريد المتابعة؟')">
      <input type="file" name="import_file" accept=".xlsx,.xls,.csv" required style="max-width:220px">
      <button type="submit" class="btn btn-primary" title="يقبل ملف التصدير الشامل بعد التعديل، أو القالب الفارغ لإضافة موظفين جدد"><i class="fa-solid fa-file-import"></i> استيراد Excel</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h3><i class="fa-solid fa-magnifying-glass"></i> البحث الذكي في الموظفين</h3>
  <form method="get" action="/hr/employees" class="form-inline" id="employee-search-form">
    <div class="form-row"><label>كلمة البحث</label>
      <select name="search" id="search-keyword" class="select2-search" data-placeholder="اختر موظف أو اكتب كلمة بحث..." data-tags="true" style="width:100%">
        <option value=""></option>
        <?php foreach ($employeeOptions as $e): ?>
        <option value="<?= Helpers::e($e['full_name']) ?>" <?= ($filters['search'] ?? '') === $e['full_name'] ? 'selected' : '' ?>><?= Helpers::e($e['full_name']) ?> (<?= Helpers::e($e['emp_code']) ?>)</option>
        <?php endforeach; ?>
        <?php if (!empty($filters['search']) && !in_array($filters['search'], array_column($employeeOptions, 'full_name'), true)): ?>
        <option value="<?= Helpers::e($filters['search']) ?>" selected><?= Helpers::e($filters['search']) ?></option>
        <?php endif; ?>
      </select>
    </div>
    <div class="form-row"><label>القسم</label>
      <select name="department_id" id="filter-department" class="select2-search-filter" data-placeholder="اختر القسم" style="width:100%">
        <option value="">الكل</option>
        <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= (string)($filters['department_id'] ?? '') === (string)$d['id'] ? 'selected' : '' ?>><?= Helpers::e($d['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الوظيفة</label>
      <select name="job_id" id="filter-job" class="select2-search-filter" data-placeholder="اختر الوظيفة" style="width:100%">
        <option value="">الكل</option>
        <?php foreach ($jobs as $j): ?><option value="<?= $j['id'] ?>" data-department="<?= (int)($j['department_id'] ?? 0) ?>" <?= (string)($filters['job_id'] ?? '') === (string)$j['id'] ? 'selected' : '' ?>><?= Helpers::e($j['title']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>المدير المباشر</label>
      <select name="manager_id" id="filter-manager" class="select2-search-filter" data-placeholder="اختر المدير" style="width:100%">
        <option value="">الكل</option>
        <?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>" data-department="<?= (int)($m['department_id'] ?? 0) ?>" <?= (string)($filters['manager_id'] ?? '') === (string)$m['id'] ? 'selected' : '' ?>><?= Helpers::e($m['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>حالة الموظف</label>
      <select name="status">
        <option value="">الكل</option>
        <?php $statuses = ['active'=>'نشط','vacation'=>'إجازة','suspended'=>'موقوف','terminated'=>'منتهي الخدمة','resigned'=>'مستقيل']; ?>
        <?php foreach ($statuses as $k => $v): ?><option value="<?= $k ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>حالة الملف</label>
      <select name="file_status">
        <option value="">الكل</option>
        <?php $fileStatuses = ['open'=>'مفتوح','closed'=>'مغلق','archived'=>'مأرشف']; ?>
        <?php foreach ($fileStatuses as $k => $v): ?><option value="<?= $k ?>" <?= ($filters['file_status'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الفرع</label>
      <select name="branch_id">
        <option value="">الكل</option>
        <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= (string)($filters['branch_id'] ?? '') === (string)$b['id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary">بحث</button>
    <a href="/hr/employees" class="btn btn-secondary">إعادة تعيين</a>
  </form>
</div>

<div class="card" style="margin-top:16px">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px">
    <h3 style="margin:0">الموظفون (<?= (int)($total ?? 0) ?>)</h3>
    <small style="color:var(--text-muted)">استخدم صندوق البحث أسفل كل عمود للتصفية، واضغط على رأس العمود للفرز</small>
  </div>
  <table id="employees-table" class="display nowrap" style="width:100%">
    <thead>
      <tr>
        <th>الرقم</th>
        <th>الاسم</th>
        <th>الوظيفة</th>
        <th>القسم</th>
        <th>المدير</th>
        <?php if ($canViewSalary): ?><th>الراتب</th><?php endif; ?>
        <th>التعيين</th>
        <th>الحالة</th>
        <th>الملف</th>
        <th>الفرع</th>
        <th>حساب</th>
        <th>الإجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($employees as $e): ?>
      <tr>
        <td data-order="<?= Helpers::e($e['emp_code']) ?>"><?= Helpers::e($e['emp_code']) ?></td>
        <td><?= Helpers::e($e['full_name']) ?></td>
        <td><?= Helpers::e($e['job_title'] ?? $e['position'] ?? '-') ?></td>
        <td><?= Helpers::e($e['department_name'] ?? $e['department'] ?? '-') ?></td>
        <td><?= Helpers::e($e['manager_name'] ?? '-') ?></td>
        <?php if ($canViewSalary): ?>
        <td data-order="<?= (float)($e['base_salary'] ?? 0) ?>"><?= Helpers::money($e['base_salary']) ?></td>
        <?php endif; ?>
        <td data-order="<?= Helpers::e($e['hire_date'] ?? '') ?>"><?= $e['hire_date'] ?? '-' ?></td>
        <td data-order="<?= Helpers::e($e['status']) ?>">
          <?php $empStatus = ['active'=>'نشط','vacation'=>'إجازة','suspended'=>'موقوف','terminated'=>'منتهي الخدمة','resigned'=>'مستقيل']; ?>
          <?php $statusClass = ['active'=>'success','vacation'=>'warning','suspended'=>'secondary','terminated'=>'danger','resigned'=>'info']; ?>
          <span class="badge badge-<?= $statusClass[$e['status']] ?? 'info' ?>"><?= $empStatus[$e['status']] ?? $e['status'] ?></span>
        </td>
        <td data-order="<?= Helpers::e($e['file_status']) ?>">
          <?php $fsClass = ['open'=>'success','closed'=>'danger','archived'=>'secondary']; $fsLabel = ['open'=>'مفتوح','closed'=>'مغلق','archived'=>'مأرشف']; ?>
          <span class="badge badge-<?= $fsClass[$e['file_status']] ?>"><?= $fsLabel[$e['file_status']] ?></span>
        </td>
        <td><?= Helpers::e($e['branch_name'] ?? '-') ?></td>
        <td><?= Helpers::e($e['username'] ?? '-') ?></td>
        <td>
          <button type="button" class="btn btn-sm btn-success" onclick="openEmployeeModal(<?= $e['id'] ?>)" title="الملف الشخصي">
            <i class="fa-solid fa-user"></i> الملف الشخصي
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$employees): ?><div class="alert alert-info" style="margin-top:12px">لا توجد نتائج مطابقة</div><?php endif; ?>
  <?php if (count($employees) >= 1000): ?><div class="alert alert-warning" style="margin-top:12px">تم عرض أول 1000 نتيجة فقط. استخدم البحث أو الفلاتر لتحديد النتائج.</div><?php endif; ?>
</div>

<?php if (!empty($_GET['iframe_close'])): ?>
<script>
(function(){
  if (window.parent && window.parent !== window && window.parent.closeEmployeeModal) {
    window.parent.closeEmployeeModal();
  }
})();
</script>
<?php endif; ?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" />
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css" />
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script>
$(function(){
  // Cascading selects: department → job + manager
  (function(){
    var deptSelect = $('#filter-department');
    var jobSelect = $('#filter-job');
    var managerSelect = $('#filter-manager');
    var jobsData = <?= json_encode(array_values(array_map(fn($j) => ['id' => $j['id'], 'title' => $j['title'], 'department_id' => (int)($j['department_id'] ?? 0)], $jobs))) ?>;
    var managersData = <?= json_encode(array_values(array_map(fn($m) => ['id' => $m['id'], 'full_name' => $m['full_name'], 'department_id' => (int)($m['department_id'] ?? 0)], $managers))) ?>;
    var selectedJob = '<?= (string)($filters['job_id'] ?? '') ?>';
    var selectedManager = '<?= (string)($filters['manager_id'] ?? '') ?>';

    function setOptions(select, items, deptId, selectedId) {
      select.empty().append(new Option('الكل', '', false, selectedId === ''));
      items.forEach(function(item) {
        if (!deptId || String(item.department_id) === String(deptId)) {
          select.append(new Option(item.title || item.full_name, item.id, false, String(item.id) === String(selectedId)));
        }
      });
      if (typeof window.reinitSelect2 === 'function') window.reinitSelect2(select[0]);
      select.trigger('change');
    }

    function syncByDepartment() {
      var deptId = deptSelect.val();
      setOptions(jobSelect, jobsData, deptId, selectedJob);
      setOptions(managerSelect, managersData, deptId, selectedManager);
    }

    deptSelect.on('change', function() {
      selectedJob = '';
      selectedManager = '';
      syncByDepartment();
    });

    syncByDepartment();
  })();

  // الضغط على Enter داخل أي قائمة (Select2 أو عادية) يرسل نموذج البحث مباشرة
  (function() {
    var $form = $('#employee-search-form');

    // القوائم العادية: عند الضغط على Enter أثناء التركيز على <select>
    $form.on('keydown', 'select', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        $form.submit();
      }
    });

    // Select2: عند الضغط على Enter داخل حقل البحث المنبثق للقائمة
    $(document).on('keydown', '.select2-search__field', function(e) {
      if (e.key === 'Enter' || e.which === 13) {
        var $closestForm = $(this).closest('form');
        if ($closestForm.is($form)) {
          // نترك Select2 يختار الخيار أولاً ثم نرسل النموذج
          setTimeout(function() { $closestForm.submit(); }, 10);
        }
      }
    });

    // Select2: عندما يكون التركيز على عنصر القائمة المغلق والضغط على Enter
    $form.on('keydown', '.select2-selection', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        $form.submit();
      }
    });
  })();

  // DataTables: sorting + filtering per column
  var table = $('#employees-table').DataTable({
    responsive: {
      details: { type: 'inline' }
    },
    columnDefs: [
      { targets: -1, className: 'all' }
    ],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/ar.json' },
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'الكل']],
    order: [[1, 'asc']],
    initComplete: function() {
      this.api().columns().every(function() {
        var column = this;
        var header = $(column.header());
        var title = header.text();
        if (title === 'الإجراءات') return;
        var input = $('<input type="text" placeholder="تصفية..." style="width:100%;margin-top:4px;font-size:12px;padding:2px 4px;border:1px solid var(--border);border-radius:4px">')
          .on('keyup change', function() {
            if (column.search() !== this.value) {
              column.search(this.value).draw();
            }
          });
        header.append($('<div></div>').append(input));
      });
    }
  });
});
</script>

<!-- Employee profile uses the global app-window-manager -->
