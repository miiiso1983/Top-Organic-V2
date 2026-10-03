<?php use App\Core\Helpers; ?>
<?php use App\Core\Auth; ?>
<?php use App\Core\App; ?>
<?php
$isModal = !empty($_GET['modal']);
$modalQuery = $isModal ? '?modal=1' : '';
$empStatus = ['active'=>'نشط','vacation'=>'إجازة','suspended'=>'موقوف','terminated'=>'منتهي الخدمة','resigned'=>'مستقيل'];
$statusClass = ['active'=>'success','vacation'=>'warning','suspended'=>'secondary','terminated'=>'danger','resigned'=>'info'];
$fileStatus = ['open'=>'مفتوح','closed'=>'مغلق','archived'=>'مأرشف'];
$fileStatusClass = ['open'=>'success','closed'=>'danger','archived'=>'secondary'];
?>

<?php if (!empty($_GET['close'])): ?>
<script>
(function(){
  try {
    if (window.parent && window.parent !== window && typeof window.parent.closeEmployeeModal === 'function') {
      window.parent.closeEmployeeModal();
      return;
    }
  } catch (e) {}
  try { window.close(); } catch (e) {}
  // Fallback: navigate back to employees list if closing fails
  if (window.location.search.indexOf('close=1') !== -1) {
    window.location.href = '/hr/employees';
  }
})();
</script>
<?php endif; ?>

<script>
function employeeWindowAction(action) {
  try {
    if (window.parent && window.parent !== window && typeof window.parent[action] === 'function') {
      window.parent[action]();
      return false;
    }
  } catch (e) {}
  if (action === 'closeEmployeeModal') {
    try { window.close(); } catch (e) {}
  }
  return false;
}
</script>

<div class="page-head">
  <div>
    <h2 style="margin:0"><i class="fa-solid fa-user"></i> <?= Helpers::e($employee['full_name']) ?> <small style="color:var(--text-muted);font-weight:normal">(<?= Helpers::e($employee['emp_code']) ?>)</small></h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
      <span class="badge badge-<?= $statusClass[$employee['status']] ?? 'info' ?>"><?= $empStatus[$employee['status']] ?? $employee['status'] ?></span>
      <span class="badge badge-<?= $fileStatusClass[$employee['file_status']] ?? 'secondary' ?>"><?= $fileStatus[$employee['file_status']] ?? $employee['file_status'] ?></span>
    </div>
  </div>
  <div class="toolbar">
    <?php if ($isModal): ?>
    <button type="button" class="btn btn-sm btn-secondary" onclick="employeeWindowAction('minimizeEmployeeModal')">
      <i class="fa-solid fa-minus"></i> تصغير
    </button>
    <button type="button" class="btn btn-sm btn-danger" onclick="employeeWindowAction('closeEmployeeModal')">
      <i class="fa-solid fa-xmark"></i> إغلاق
    </button>
    <?php else: ?>
    <a href="/hr/employees" class="btn btn-sm btn-secondary"><i class="fa-solid fa-arrow-right"></i> الموظفون</a>
    <?php endif; ?>
    <a href="/hr/employees/<?= $employee['id'] ?><?= $modalQuery ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-user"></i> الملف الشخصي</a>
    <a href="/hr/employees/<?= $employee['id'] ?>/documents<?= $modalQuery ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-paperclip"></i> المستندات</a>
    <a href="/hr/employees/<?= $employee['id'] ?>/audit<?= $modalQuery ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-magnifying-glass"></i> التدقيق</a>
    <?php if (Auth::can('hr.custody.view')): ?>
    <a href="/hr/custody/employee/<?= $employee['id'] ?><?= $modalQuery ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-box"></i> العهدة</a>
    <?php endif; ?>
    <button type="button" class="btn btn-sm btn-success" onclick="window.print()"><i class="fa-solid fa-print"></i> طباعة</button>
  </div>
</div>

<?php if (Auth::can('hr.manage')): ?>
<div class="card">
  <div class="toolbar" style="justify-content:space-between;align-items:center">
    <form method="post" action="/hr/employees/<?= $employee['id'] ?>/toggle<?= $modalQuery ?>" class="form-inline" onsubmit="return confirm('تغيير حالة الملف؟')">
      <span style="font-size:14px">حالة الملف:</span>
      <select name="file_status" style="width:auto">
        <option value="open" <?= $employee['file_status'] === 'open' ? 'selected' : '' ?>>مفتوح</option>
        <option value="closed" <?= $employee['file_status'] === 'closed' ? 'selected' : '' ?>>مغلق</option>
        <option value="archived" <?= $employee['file_status'] === 'archived' ? 'selected' : '' ?>>مأرشف</option>
      </select>
      <input type="text" name="reason" placeholder="السبب (اختياري)" style="width:160px">
      <button class="btn btn-sm btn-primary">حفظ</button>
    </form>
    <form method="post" action="/hr/employees/<?= $employee['id'] ?>/delete<?= $modalQuery ?>" style="display:inline" onsubmit="return confirm('هل أنت متأكد من حذف الموظف <?= Helpers::e($employee['full_name']) ?>؟ لا يمكن التراجع.')">
      <button class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i> حذف الموظف</button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h4 class="section-title"><i class="fa-solid fa-face-smile"></i> الصورة المرجعية للوجه</h4>
  <?php if (!empty($employee['reference_photo'])): ?>
    <div style="margin-bottom:12px">
      <a href="<?= $employee['reference_photo'] ?>" target="_blank">
        <img src="<?= $employee['reference_photo'] ?>" style="max-width:220px;max-height:220px;border-radius:8px;border:1px solid var(--border-light)">
      </a>
    </div>
    <?php if (Auth::can('hr.manage')): ?>
    <form method="post" action="/hr/employees/<?= $employee['id'] ?>/reference-photo/delete<?= $modalQuery ?>" style="display:inline" onsubmit="return confirm('هل تريد حذف الصورة المرجعية وطلب صورة جديدة من الموظف؟')">
      <button class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i> حذف وطلب صورة جديدة</button>
    </form>
    <?php endif; ?>
  <?php else: ?>
    <div class="alert alert-info">لا توجد صورة مرجعية للوجه. سيطلب التطبيق من الموظف رفع صورة عند أول بصمة.</div>
  <?php endif; ?>
</div>

<form method="post" action="/hr/employees/<?= $employee['id'] ?>/edit<?= $modalQuery ?>" enctype="multipart/form-data" id="employee-edit-form">
  <div class="card">
    <h4 class="section-title"><i class="fa-solid fa-address-card"></i> البيانات الأساسية</h4>
    <div class="info-grid edit-grid">
      <div class="info-item edit-item">
        <span class="info-label">الاسم الكامل</span>
        <span class="info-value"><input name="full_name" value="<?= Helpers::e($employee['full_name']) ?>" required></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">الهاتف</span>
        <span class="info-value"><input name="phone" value="<?= Helpers::e($employee['phone'] ?? '') ?>"></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">الجنسية</span>
        <span class="info-value"><input name="nationality" value="<?= Helpers::e($employee['nationality'] ?? '') ?>"></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">السكن</span>
        <span class="info-value"><input name="residence" value="<?= Helpers::e($employee['residence'] ?? '') ?>"></span>
      </div>
      <div class="info-item edit-item full-width">
        <span class="info-label">الصورة المرجعية للوجه</span>
        <span class="info-value">
          <input type="file" name="reference_photo" accept="image/*" capture="user">
          <small>تُستخدم لمقارنة السيلفي عند البصمة عبر AWS Rekognition.</small>
        </span>
      </div>
    </div>
  </div>

  <div class="card">
    <h4 class="section-title"><i class="fa-solid fa-briefcase"></i> بيانات العمل</h4>
    <div class="info-grid edit-grid">
      <div class="info-item edit-item">
        <span class="info-label">القسم</span>
        <span class="info-value">
          <select name="department_id" id="emp-edit-department">
            <option value="">—</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= ((int)($employee['department_id'] ?? 0) === (int)$d['id']) ? 'selected' : '' ?>><?= Helpers::e($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">الوظيفة</span>
        <span class="info-value">
          <select name="job_id" id="emp-edit-job">
            <option value="">—</option>
          </select>
        </span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">المدير المباشر</span>
        <span class="info-value">
          <select name="manager_id" id="emp-edit-manager">
            <option value="">—</option>
            <?php foreach ($managers as $m): ?>
            <option value="<?= $m['id'] ?>" data-department="<?= (int)($m['department_id'] ?? 0) ?>" <?= ((int)($employee['manager_id'] ?? 0) === (int)$m['id']) ? 'selected' : '' ?>><?= Helpers::e($m['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">الدور / الصلاحية</span>
        <span class="info-value">
          <select name="role_id" id="emp-edit-role">
            <option value="0">بدون حساب مستخدم</option>
            <?php
            $selectedRoleId = (int)($employee['user_role_id'] ?? 0);
            if (!$selectedRoleId && (int)$employee['admin_level'] > 0) {
                foreach ($roles as $r) {
                    if ((int)$r['level'] === (int)$employee['admin_level']) {
                        $selectedRoleId = (int)$r['id'];
                        break;
                    }
                }
            }
            ?>
            <?php foreach ($roles as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $selectedRoleId === (int)$r['id'] ? 'selected' : '' ?>><?= Helpers::e($r['name']) ?> (مستوى <?= $r['level'] ?>)</option>
            <?php endforeach; ?>
          </select>
          <div id="emp-edit-role-info" class="text-muted" style="font-size:12px;margin-top:4px;"></div>
        </span>
      </div>
    </div>
  </div>

  <div class="card">
    <h4 class="section-title"><i class="fa-solid fa-key"></i> حساب الدخول والاتصال</h4>
    <div class="info-grid edit-grid">
      <div class="info-item edit-item full-width">
        <span class="info-label">حساب الدخول</span>
        <span class="info-value">
          <select name="user_id" id="emp-edit-user-id">
            <option value="">— تلقائي (حسب كود الموظف) —</option>
            <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>" data-username="<?= Helpers::e($u['username']) ?>" <?= ((int)($employee['user_id'] ?? 0) === (int)$u['id']) ? 'selected' : '' ?>><?= Helpers::e($u['username']) ?> — <?= Helpers::e($u['full_name']) ?> (<?= Helpers::e($u['role_name'] ?? '—') ?>)</option>
            <?php endforeach; ?>
          </select>
          <small>إذا كان الموظف يدخل بحساب موجود مسبقاً، اختره هنا. وإلا اتركه فارغاً لينشئ النظام حساباً باسم كود الموظف.</small>
        </span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">اسم المستخدم</span>
        <span class="info-value"><input name="username" id="emp-edit-username" value="<?= Helpers::e($employee['user_username'] ?? '') ?>" placeholder="مثال: ali.hassan"></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">كلمة المرور <small>(اختياري)</small></span>
        <span class="info-value" style="display:flex;gap:6px;align-items:stretch">
          <input type="password" name="password" id="emp-edit-password" placeholder="••••••••" style="flex:1">
          <button type="button" class="btn btn-sm btn-secondary" id="emp-edit-toggle-password" title="إظهار/إخفاء كلمة المرور" style="white-space:nowrap">
            <i class="fa-solid fa-eye"></i>
          </button>
        </span>
      </div>
      <div class="info-item edit-item full-width">
        <span class="info-label">قنوات PTT المقترحة للقسم</span>
        <span class="info-value">
          <select name="ptt_channel_ids[]" id="emp-edit-ptt" class="select2-ptt" multiple data-placeholder="اختر القنوات (يمكنك تحديد أكثر من قناة)" style="width:100%">
            <?php foreach ($pttChannels as $c): ?>
            <option value="<?= $c['id'] ?>" data-department="<?= Helpers::e($c['department'] ?? '') ?>" <?= in_array((int)$c['id'], $selectedPttChannels, true) ? 'selected' : '' ?>><?= Helpers::e($c['name']) ?> (<?= Helpers::e($c['code']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <small>تظهر جميع القنوات؛ يمكنك تحديد أكثر من قناة ومن أكثر من قسم.</small>
        </span>
      </div>
    </div>
  </div>

  <div class="card">
    <h4 class="section-title"><i class="fa-solid fa-money-bill-wave"></i> الراتب والدوام</h4>
    <div class="info-grid edit-grid">
      <div class="info-item edit-item">
        <span class="info-label">الراتب الأساسي</span>
        <span class="info-value"><input type="number" name="base_salary" step="0.01" value="<?= $employee['base_salary'] ?? 0 ?>"></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">تاريخ التعيين</span>
        <span class="info-value"><input type="date" name="hire_date" value="<?= $employee['hire_date'] ?? date('Y-m-d') ?>"></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">حالة الموظف</span>
        <span class="info-value">
          <select name="status" id="emp-edit-status">
            <?php $statuses = ['active'=>'نشط','vacation'=>'إجازة','suspended'=>'موقوف','terminated'=>'منتهي الخدمة','resigned'=>'مستقيل']; ?>
            <?php foreach ($statuses as $k => $v): ?>
            <option value="<?= $k ?>" <?= ($employee['status'] ?? 'active') === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">نظام احتساب الدوام</span>
        <span class="info-value">
          <select name="attendance_type" id="emp-edit-attendance-type">
            <option value="shift" <?= ($employee['attendance_type'] ?? 'shift') === 'shift' ? 'selected' : '' ?>>الوردية الثابتة</option>
            <option value="flexible" <?= ($employee['attendance_type'] ?? 'shift') === 'flexible' ? 'selected' : '' ?>>ساعات العمل المرنة</option>
          </select>
        </span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">التحقق من الموقع الجغرافي</span>
        <span class="info-value">
          <label style="display:flex;align-items:center;gap:6px;font-weight:normal;cursor:pointer">
            <input type="checkbox" name="geofence_exempt" value="1" <?= !empty($employee['geofence_exempt']) ? 'checked' : '' ?>>
            إعفاء الموظف من اشتراط الموقع الجغرافي عند تسجيل الحضور
          </label>
          <small style="display:block;color:#888;margin-top:4px">فعّل هذا الخيار للموظفين الذين لا يشترط دوامهم في الموقع المكتبي (مثل المندوبين والموظفين الميدانيين)</small>
        </span>
      </div>
      <div class="info-item edit-item" id="emp-edit-required-hours-row" style="display:none">
        <span class="info-label">عدد الساعات المطلوبة يومياً</span>
        <span class="info-value"><input type="number" name="required_hours" id="emp-edit-required-hours" step="0.01" min="0" max="24" value="<?= (float)($employee['required_hours'] ?: 11) ?>"></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">الوردية الثابتة</span>
        <span class="info-value" style="display:flex;gap:8px;align-items:flex-start">
          <select name="shift_id" id="emp-edit-shift-id" class="select2-shift" data-placeholder="اختر الوردية الثابتة" style="flex:1;min-width:0">
            <option value="">— الافتراضية العامة —</option>
            <?php foreach ($shifts as $s): ?>
            <option value="<?= $s['id'] ?>" <?= ((int)($employee['shift_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>><?= Helpers::e($s['name']) ?> (<?= Helpers::e($s['start_time']) ?> - <?= Helpers::e($s['end_time']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-sm btn-secondary" id="btn-create-shift-popup" title="إنشاء وردية جديدة">
            <i class="fa-solid fa-plus"></i> وردية
          </button>
        </span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">يوم العطلة الأسبوعية</span>
        <span class="info-value">
          <select name="weekly_off_day">
            <option value="" <?= (($employee['weekly_off_day'] ?? '') === '') ? 'selected' : '' ?>>— الافتراضي (حسب العطل العامة) —</option>
            <?php $weekDays = ['الأحد','الإثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت']; ?>
            <?php foreach ($weekDays as $idx => $dn): ?>
            <option value="<?= $idx ?>" <?= (string)($employee['weekly_off_day'] ?? '') === (string)$idx ? 'selected' : '' ?>><?= $dn ?></option>
            <?php endforeach; ?>
          </select>
        </span>
      </div>
    </div>
  </div>

  <div class="card">
    <h4 class="section-title"><i class="fa-solid fa-building"></i> الارتباطات التنظيمية والمحاسبية</h4>
    <div class="info-grid edit-grid">
      <div class="info-item edit-item">
        <span class="info-label">الفرع</span>
        <span class="info-value">
          <select name="branch_id">
            <option value="">—</option>
            <?php foreach ($branches as $b): ?>
            <option value="<?= $b['id'] ?>" <?= ((int)($employee['branch_id'] ?? 0) === (int)$b['id']) ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">مركز الكلفة</span>
        <span class="info-value">
          <select name="cost_center_id">
            <option value="">—</option>
            <?php foreach ($costCenters as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ((int)($employee['cost_center_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>><?= Helpers::e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">الحساب المحاسبي</span>
        <span class="info-value">
          <select name="account_id">
            <option value="">—</option>
            <?php foreach ($accounts as $a): ?>
            <option value="<?= $a['id'] ?>" <?= ((int)($employee['account_id'] ?? 0) === (int)$a['id']) ? 'selected' : '' ?>><?= Helpers::e($a['code'] . ' - ' . $a['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </span>
      </div>
    </div>
  </div>

  <div class="card">
    <h4 class="section-title"><i class="fa-solid fa-house-chimney-user"></i> خدمات السكن</h4>
    <div class="info-grid edit-grid">
      <div class="info-item edit-item">
        <span class="info-label">نوع السكن</span>
        <span class="info-value">
          <select name="housing_type">
            <option value="" <?= ($employee['housing_type'] ?? '') === '' ? 'selected' : '' ?>>— بدون —</option>
            <?php $housingTypes = ['yarmouk'=>'سكن اليرموك','sebti'=>'سكن سبتي','qadisiya'=>'سكن القادسية','internal'=>'سكن الداخلي']; ?>
            <?php if (($employee['housing_type'] ?? '') === 'external'): ?>
            <option value="external" selected>سكن خارجي</option>
            <?php endif; ?>
            <?php foreach ($housingTypes as $hk => $hv): ?>
            <option value="<?= $hk ?>" <?= ($employee['housing_type'] ?? '') === $hk ? 'selected' : '' ?>><?= $hv ?></option>
            <?php endforeach; ?>
          </select>
        </span>
      </div>
      <input type="hidden" name="housing_monthly_deduction" value="<?= Helpers::e($employee['housing_monthly_deduction'] ?? 0) ?>">
      <div class="info-item edit-item">
        <span class="info-label">إعفاء من استقطاع خدمات السكن</span>
        <span class="info-value">
          <label style="display:flex;align-items:center;gap:6px;font-weight:normal;cursor:pointer">
            <input type="checkbox" name="housing_exempt" value="1" <?= !empty($employee['housing_exempt']) ? 'checked' : '' ?>>
            إعفاء الموظف من استقطاع خدمات السكن (لا يُستقطع شيءٌ في الراتب طالما الإعفاء فعّالاً، ويعود الاستقطاع تلقائياً بالمبلغ المحدد عند رفع الإعفاء)
          </label>
        </span>
      </div>
      <div class="info-item edit-item full-width">
        <span class="info-value" style="padding:0;background:none"><small><i class="fa-solid fa-circle-info"></i> مبلغ استقطاع السكن الشهري يُحدّد تلقائياً من وحدة إدارة السكن عند تسكين الموظف (موقع/شقة/غرفة)، ولا يُعدّل يدوياً من هذا النموذج.</small></span>
      </div>
    </div>
  </div>

  <div class="card">
    <h4 class="section-title"><i class="fa-solid fa-shield-halved"></i> إدارة تأمين المستحقات</h4>
    <div class="info-grid edit-grid">
      <div class="info-item edit-item">
        <span class="info-label">نسبة التأمين الشهري %</span>
        <span class="info-value"><input type="number" name="insurance_percentage" step="0.01" min="0" max="100" value="<?= Helpers::e($employee['insurance_percentage'] ?? 0) ?>"></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">عدد مرات استقطاع التأمين</span>
        <span class="info-value"><input type="number" name="insurance_occurrences" step="1" min="0" value="<?= Helpers::e($employee['insurance_occurrences'] ?? 0) ?>"></span>
      </div>
      <div class="info-item edit-item full-width">
        <span class="info-value" style="padding:0;background:none"><small><i class="fa-solid fa-circle-info"></i> يُستقطع تأمين المستحقات تلقائياً كل شهر بنسبة % من الراتب الأساسي حتى الوصول لعدد المرات المحدد، ويُعتبر أمانة تُرد بالكامل للموظف عند الاستقالة/المخالصة.</small></span>
      </div>
    </div>
  </div>

  <div class="card" id="resignation-section" style="display:none">
    <h4 class="section-title" style="justify-content:space-between">
      <span><i class="fa-solid fa-file-signature"></i> نموذج الاستقالة والمخالصة</span>
      <button type="button" class="btn btn-sm btn-primary" onclick="printResignationClearance()">
        <i class="fa-solid fa-print"></i> طباعة المخالصة
      </button>
    </h4>

    <div class="info-grid edit-grid">
      <div class="info-item edit-item">
        <span class="info-label">تاريخ الاستقالة</span>
        <span class="info-value"><input type="date" name="resignation_date" id="resignation_date" value="<?= Helpers::e($resignation['resignation_date'] ?? date('Y-m-d')) ?>"></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">عدد أيام العمل <small>(مستندة على البصمات)</small></span>
        <span class="info-value"><input type="number" name="days_worked" id="days_worked" step="0.01" min="0" max="31" value="<?= Helpers::e($resignation['days_worked'] ?? $workedDays) ?>"></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">الراتب اليومي</span>
        <span class="info-value"><input type="number" name="daily_salary" id="daily_salary" step="0.01" value="<?= Helpers::e($resignation['daily_salary'] ?? round(($employee['base_salary'] ?? 0) / 30, 2)) ?>" readonly></span>
      </div>
      <div class="info-item edit-item full-width">
        <span class="info-value" style="padding:0;background:none"><small><i class="fa-solid fa-fingerprint"></i> عدد أيام العمل يُحسب تلقائياً من سجلات البصمة (<?= (int)($workedDays ?? 0) ?> يوم في الشهر الحالي) ويمكن تعديله يدوياً عند الحاجة.</small></span>
      </div>
    </div>

    <h4 class="section-title" style="margin-top:18px"><i class="fa-solid fa-calculator"></i> حساب الاستحقاق المالي</h4>
    <div class="info-grid edit-grid">
      <div class="info-item edit-item">
        <span class="info-label">الراتب</span>
        <span class="info-value"><input type="number" name="salary_amount" id="salary_amount" step="0.01" value="<?= Helpers::e($breakdown['salary_amount'] ?? ($employee['base_salary'] ?? 0)) ?>"></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">أيام الغياب</span>
        <span class="info-value"><input type="number" name="absence_days" id="absence_days" step="0.01" value="<?= Helpers::e($breakdown['absence_days'] ?? 0) ?>"></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">استقطاع الغياب</span>
        <span class="info-value"><input type="number" name="absence_deduction" id="absence_deduction" step="0.01" value="<?= Helpers::e($breakdown['absence_deduction'] ?? 0) ?>"></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">مبلغ الإضافي</span>
        <span class="info-value"><input type="number" name="overtime_amount" id="overtime_amount" step="0.01" value="<?= Helpers::e($breakdown['overtime_amount'] ?? 0) ?>"></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">السلف</span>
        <span class="info-value"><input type="number" name="advances_amount" id="advances_amount" step="0.01" value="<?= Helpers::e($breakdown['advances_amount'] ?? 0) ?>"></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">استقطاعات السكن</span>
        <span class="info-value"><input type="number" name="housing_deduction" id="housing_deduction" step="0.01" value="<?= Helpers::e($breakdown['housing_deduction'] ?? 0) ?>"></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">الخصومات</span>
        <span class="info-value"><input type="number" name="other_deductions" id="other_deductions" step="0.01" value="<?= Helpers::e($breakdown['other_deductions'] ?? 0) ?>"></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">استرداد التأمين المستقطع <small>(أمانة تُرد بالكامل)</small></span>
        <span class="info-value"><input type="number" name="insurance_refund" id="insurance_refund" step="0.01" value="<?= Helpers::e($breakdown['insurance_refund'] ?? 0) ?>"></span>
      </div>
      <div class="info-item edit-item">
        <span class="info-label">صافي الاستحقاق <small>(تلقائي)</small></span>
        <span class="info-value"><input type="number" name="total_due" id="total_due" step="0.01" value="<?= Helpers::e($breakdown['total_due'] ?? ($resignation['total_due'] ?? 0)) ?>" readonly style="font-weight:bold"></span>
      </div>
    </div>
    <div class="info-item edit-item full-width" style="margin-top:12px;background:none;padding:0">
      <span class="info-value" style="padding:0;background:none"><small><i class="fa-solid fa-circle-info"></i> المعادلة: صافي الاستحقاق = الراتب - استقطاع الغياب + الإضافي - السلف - استقطاعات السكن - الخصومات + استرداد التأمين المستقطع. القيم المستندة على البيانات تُحسب تلقائياً ويمكن تعديلها يدوياً.</small></span>
    </div>

    <div class="info-grid edit-grid" style="margin-top:16px">
      <div class="info-item edit-item full-width">
        <span class="info-label">سبب الاستقالة</span>
        <span class="info-value"><textarea name="resignation_reason" id="resignation_reason" rows="3" placeholder="اكتب سبب الاستقالة هنا..."><?= Helpers::e($resignation['reason'] ?? '') ?></textarea></span>
      </div>
      <div class="info-item edit-item full-width">
        <span class="info-label">ملاحظات المخالصة</span>
        <span class="info-value"><textarea name="resignation_notes" id="resignation_notes" rows="2" placeholder="أي ملاحظات إضافية على المخالصة"><?= Helpers::e($resignation['notes'] ?? '') ?></textarea></span>
      </div>
    </div>

    <h4 class="section-title" style="margin-top:18px"><i class="fa-solid fa-box-open"></i> تسليم العهدة</h4>
    <?php if (!empty($custody)): ?>
    <div style="overflow:auto">
      <table class="resignation-custody-table">
        <tr><th>الصنف</th><th>الباركود</th><th>المستودع</th><th>الكمية</th><th>القيمة</th><th>السيريال</th><th>تم التسليم؟</th></tr>
        <?php foreach ($custody as $c): ?>
        <tr>
          <td><?= Helpers::e($c['item_name']) ?></td>
          <td><?= Helpers::e($c['barcode'] ?? '-') ?></td>
          <td><?= Helpers::e($c['warehouse_name'] ?? '-') ?></td>
          <td><?= $c['qty'] ?></td>
          <td><?= Helpers::money($c['total_value']) ?></td>
          <td><?= Helpers::e($c['serial_number'] ?? '-') ?></td>
          <td style="text-align:center"><input type="checkbox" name="custody_returned[]" value="<?= (int)$c['id'] ?>" class="custody-returned" data-item="<?= Helpers::e($c['item_name']) ?>"></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php else: ?>
    <div class="alert alert-info">لا توجد عهدة نشطة لهذا الموظف.</div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="form-actions-bar">
      <button type="submit" class="btn btn-primary" id="employee-save-btn"><i class="fa-solid fa-check"></i> حفظ التعديلات</button>
      <?php if ($isModal): ?>
      <button type="button" class="btn btn-secondary" onclick="employeeWindowAction('closeEmployeeModal')"><i class="fa-solid fa-xmark"></i> إلغاء</button>
      <?php else: ?>
      <a href="/hr/employees" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> إلغاء</a>
      <?php endif; ?>
    </div>
  </div>
</form>

<!-- نافذة منبثقة لإنشاء وردية جديدة -->
<div class="modal" id="createShiftModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;align-items:center;justify-content:center">
  <div class="modal-box" style="background:#fff;border-radius:12px;padding:20px;width:90%;max-width:460px;max-height:90vh;overflow:auto">
    <h4 style="margin-top:0"><i class="fa-solid fa-clock"></i> إنشاء وردية جديدة</h4>
    <form id="create-shift-form" method="post" action="/hr/shifts">
      <div class="form-row" style="margin-bottom:10px">
        <label>اسم الوردية</label>
        <input type="text" name="name" required placeholder="مثال: وردية صباحية">
      </div>
      <div style="display:flex;gap:10px;margin-bottom:10px">
        <div class="form-row" style="flex:1;margin:0">
          <label>وقت البدء</label>
          <input type="time" name="start_time" value="08:00" required>
        </div>
        <div class="form-row" style="flex:1;margin:0">
          <label>وقت الانتهاء</label>
          <input type="time" name="end_time" value="17:00" required>
        </div>
      </div>
      <div class="form-row" style="margin-bottom:10px">
        <label>أيام العمل</label>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <?php $dayNames = ['الأحد','الإثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت']; ?>
          <?php foreach ($dayNames as $idx => $dn): ?>
          <label style="display:flex;align-items:center;gap:4px;font-weight:normal;font-size:13px">
            <input type="checkbox" name="work_days[]" value="<?= $idx ?>" checked>
            <?= $dn ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <input type="hidden" name="ajax" value="1">
      <div id="create-shift-message" style="margin-bottom:10px"></div>
      <div class="modal-actions" style="display:flex;gap:8px;justify-content:flex-end">
        <button type="button" class="btn btn-secondary" id="btn-cancel-shift-popup">إلغاء</button>
        <button type="submit" class="btn btn-success">حفظ</button>
      </div>
    </form>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <h3><i class="fa-solid fa-shield"></i> العهدة المسلمة</h3>
  <p>
    <a href="/hr/custody/employee/<?= $employee['id'] ?>" class="btn btn-sm btn-secondary">إدارة العهدة</a>
  </p>
  <?php if (!empty($custody)): ?>
  <div style="overflow:auto">
    <table>
      <tr><th>الصنف</th><th>الباركود</th><th>المستودع</th><th>الكمية</th><th>القيمة الإجمالية</th><th>السيريال</th><th>تاريخ التسليم</th><th>تاريخ الاستحقاق</th><th>الحالة</th><th>الموظف المسلم</th></tr>
      <?php foreach ($custody as $c): ?>
      <tr>
        <td><?= Helpers::e($c['item_name']) ?></td>
        <td><?= Helpers::e($c['barcode'] ?? '-') ?></td>
        <td><?= Helpers::e($c['warehouse_name'] ?? '-') ?></td>
        <td><?= $c['qty'] ?></td>
        <td><?= Helpers::money($c['total_value']) ?></td>
        <td><?= Helpers::e($c['serial_number'] ?? '-') ?></td>
        <td><?= $c['issued_at'] ? date('Y-m-d', strtotime($c['issued_at'])) : '-' ?></td>
        <td><?= $c['due_date'] ?? '-' ?></td>
        <td>
          <?php $custodyStatus = ['active'=>'نشطة','returned'=>'مُرجعة','damaged'=>'تالفة','lost'=>'مفقودة']; ?>
          <span class="badge badge-info"><?= $custodyStatus[$c['status']] ?? $c['status'] ?></span>
        </td>
        <td><?= Helpers::e($c['issued_by_name'] ?? '-') ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php else: ?>
  <div class="alert alert-info">لا توجد عهدة نشطة لهذا الموظف.</div>
  <?php endif; ?>
</div>

<!-- Print Clearance Form -->
<div class="print-clearance" id="print-clearance">
  <div class="company-header">
    <h2>Top Organic Restaurant</h2>
    <p>نموذج مخالصة نهائية للموظف</p>
  </div>
  <h3>نموذج الاستقالة والمخالصة</h3>
  <table class="info-table">
    <tr>
      <td class="label">اسم الموظف</td><td id="print-emp-name"><?= Helpers::e($employee['full_name']) ?></td>
      <td class="label">الكود الوظيفي</td><td id="print-emp-code"><?= Helpers::e($employee['emp_code']) ?></td>
    </tr>
    <tr>
      <td class="label">القسم</td><td id="print-emp-dept"><?= Helpers::e($employee['department_name'] ?? $employee['department'] ?? '-') ?></td>
      <td class="label">الوظيفة</td><td id="print-emp-job"><?= Helpers::e($employee['job_title'] ?? $employee['position'] ?? '-') ?></td>
    </tr>
    <tr>
      <td class="label">تاريخ الاستقالة</td><td id="print-resignation-date"><?= Helpers::e($resignation['resignation_date'] ?? date('Y-m-d')) ?></td>
      <td class="label">عدد أيام العمل</td><td id="print-days-worked"><?= Helpers::e($resignation['days_worked'] ?? $workedDays) ?></td>
    </tr>
    <tr>
      <td class="label">الراتب اليومي</td><td id="print-daily-salary"><?= Helpers::money($resignation['daily_salary'] ?? round(($employee['base_salary'] ?? 0) / 30, 2)) ?></td>
      <td class="label">سبب الاستقالة</td><td id="print-reason"><?= Helpers::e($resignation['reason'] ?? '') ?></td>
    </tr>
  </table>
  <h4>تفاصيل حساب الاستحقاق المالي</h4>
  <table class="custody-print">
    <thead>
      <tr><th>البند</th><th>المبلغ</th></tr>
    </thead>
    <tbody>
      <tr><td>الراتب</td><td id="print-salary-amount"><?= Helpers::money($breakdown['salary_amount'] ?? ($employee['base_salary'] ?? 0)) ?></td></tr>
      <tr><td>استقطاع الغياب (<?= Helpers::e($breakdown['absence_days'] ?? 0) ?> يوم)</td><td id="print-absence-deduction"><?= Helpers::money($breakdown['absence_deduction'] ?? 0) ?></td></tr>
      <tr><td>مبلغ الإضافي</td><td id="print-overtime-amount"><?= Helpers::money($breakdown['overtime_amount'] ?? 0) ?></td></tr>
      <tr><td>السلف</td><td id="print-advances-amount"><?= Helpers::money($breakdown['advances_amount'] ?? 0) ?></td></tr>
      <tr><td>استقطاعات السكن</td><td id="print-housing-deduction"><?= Helpers::money($breakdown['housing_deduction'] ?? 0) ?></td></tr>
      <tr><td>الخصومات</td><td id="print-other-deductions"><?= Helpers::money($breakdown['other_deductions'] ?? 0) ?></td></tr>
      <tr><td>استرداد التأمين المستقطع (أمانة)</td><td id="print-insurance-refund"><?= Helpers::money($breakdown['insurance_refund'] ?? 0) ?></td></tr>
      <tr style="font-weight:bold;background:#f0f0f0"><td>صافي الاستحقاق</td><td id="print-total-due"><?= Helpers::money($breakdown['total_due'] ?? ($resignation['total_due'] ?? 0)) ?></td></tr>
    </tbody>
  </table>
  <h4>قائمة العهد والأدوات المسجلة باسم الموظف</h4>
  <table class="custody-print">
    <thead>
      <tr><th>#</th><th>الصنف</th><th>الباركود</th><th>المستودع</th><th>الكمية</th><th>القيمة</th><th>السيريال</th><th>حالة التسليم</th></tr>
    </thead>
    <tbody id="print-custody-body">
      <?php if (!empty($custody)): ?>
      <?php $i = 1; foreach ($custody as $c): ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= Helpers::e($c['item_name']) ?></td>
        <td><?= Helpers::e($c['barcode'] ?? '-') ?></td>
        <td><?= Helpers::e($c['warehouse_name'] ?? '-') ?></td>
        <td><?= $c['qty'] ?></td>
        <td><?= Helpers::money($c['total_value']) ?></td>
        <td><?= Helpers::e($c['serial_number'] ?? '-') ?></td>
        <td>□ لم يُسلّم / □ تم التسليم</td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
  <p><strong>ملاحظات المخالصة:</strong> <span id="print-notes"><?= Helpers::e($resignation['notes'] ?? '') ?></span></p>
  <div class="signatures">
    <div class="signature">
      <div class="line">توقيع الموظف</div>
      <p>الاسم: <?= Helpers::e($employee['full_name']) ?></p>
    </div>
    <div class="signature">
      <div class="line">مدير الموارد البشرية</div>
      <p>الاسم / التوقيع</p>
    </div>
    <div class="signature">
      <div class="line">المحاسب</div>
      <p>الاسم / التوقيع</p>
    </div>
  </div>
  <div class="footer-note">
    تم إعداد هذا النموذج إلكترونياً عبر نظام Top Organic HR. يُعتبر هذا النموذج سنداً للمخالصة النهائية بين الموظف والشركة.
  </div>
</div>

<style>
/* Page header consistent with the rest of the system */
.page-head h2 small { font-size: 16px; }

/* Card section titles consistent with profile page and other HR pages */
.card h4.section-title,
.card h3 {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 14px;
  padding-bottom: 10px;
  color: var(--text);
  border-bottom: 1px solid var(--border-light);
}

/* Info grid matching the profile page */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
}
.info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px;
  background: var(--surface-alt);
  border-radius: 8px;
}
.info-label { font-size: 12px; color: var(--text-muted); }
.info-value { font-size: 15px; font-weight: 600; }

/* Edit variants: stretch inputs inside info-value */
.edit-grid .info-value { font-weight: normal; }
.edit-grid .info-value input,
.edit-grid .info-value select,
.edit-grid .info-value textarea {
  width: 100%;
  box-sizing: border-box;
  margin: 0;
}
.edit-grid .info-value input:focus,
.edit-grid .info-value select:focus,
.edit-grid .info-value textarea:focus {
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(46, 125, 50, .12);
}
.select2-container--default .select2-selection--multiple,
.select2-container--default .select2-selection--single {
  border-color: var(--border);
  border-radius: 8px;
  min-height: 38px;
  background: var(--surface);
}
.select2-container--default.select2-container--focus .select2-selection--multiple,
.select2-container--default.select2-container--focus .select2-selection--single {
  border-color: var(--brand);
}
.select2-container--default .select2-results__option--highlighted[aria-selected] { background: var(--brand); }
.edit-grid .info-value small {
  display: block;
  margin-top: 6px;
  color: var(--text-muted);
  font-size: 12px;
  line-height: 1.5;
  font-weight: normal;
}
.edit-grid .full-width { grid-column: 1 / -1; }

/* Actions bar */
.form-actions-bar { display: flex; gap: 12px; justify-content: flex-end; flex-wrap: wrap; }

/* Resignation custody table */
.resignation-custody-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
.resignation-custody-table th, .resignation-custody-table td { border: 1px solid var(--border-light); padding: 8px; text-align: center; }
.resignation-custody-table th { background: var(--surface-alt); color: var(--text); }
.resignation-custody-table tr:nth-child(even) { background: var(--surface); }

/* Print Clearance Form */
.print-clearance { display: none; }

@media print {
  @page {
    size: A4 portrait;
    margin: 8mm;
  }

  html, body {
    margin: 0;
    padding: 0;
    height: auto;
    background: #fff;
  }

  /* Collapse everything except the clearance form so it does not create extra pages */
  body *:not(.print-clearance):not(.print-clearance *) {
    visibility: hidden;
    height: 0 !important;
    min-height: 0 !important;
    max-height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    border: none !important;
    line-height: 0 !important;
  }

  .print-clearance {
    display: block;
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    padding: 0;
    margin: 0;
    box-sizing: border-box;
    visibility: visible;
    font-family: 'Arial', sans-serif;
    direction: rtl;
    font-size: 12px;
    line-height: 1.35;
  }

  .print-clearance,
  .print-clearance * {
    visibility: visible;
    height: auto;
    min-height: auto;
    max-height: none;
    line-height: normal;
  }

  /* Compact layout for a single A4 page */
  .print-clearance .company-header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 10px; }
  .print-clearance .company-header h2 { margin: 0; font-size: 17px; }
  .print-clearance .company-header p { margin: 2px 0 0; color: #555; font-size: 12px; }
  .print-clearance h3 { text-align: center; text-decoration: underline; margin: 8px 0; font-size: 14px; }
  .print-clearance h4 { font-size: 12px; margin: 6px 0; }
  .print-clearance .info-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
  .print-clearance .info-table td { border: 1px solid #333; padding: 4px 6px; width: 50%; font-size: 11px; }
  .print-clearance .info-table td.label { background: #f0f0f0; font-weight: bold; }
  .print-clearance .amount-box { border: 1px solid #333; padding: 8px; text-align: center; margin: 8px 0; background: #fafafa; }
  .print-clearance .amount-box strong { font-size: 14px; }
  .print-clearance .custody-print { width: 100%; border-collapse: collapse; margin: 6px 0; }
  .print-clearance .custody-print th, .print-clearance .custody-print td { border: 1px solid #333; padding: 3px 4px; text-align: center; font-size: 11px; }
  .print-clearance .custody-print th { background: #f0f0f0; }
  .print-clearance .signatures { display: flex; justify-content: space-between; margin-top: 18px; }
  .print-clearance .signature { width: 30%; text-align: center; font-size: 11px; }
  .print-clearance .signature .line { border-top: 1px solid #333; margin-top: 22px; padding-top: 4px; }
  .print-clearance .footer-note { margin-top: 12px; font-size: 10px; color: #666; text-align: center; }

  /* Avoid splitting the signature block and footer */
  .print-clearance .signatures, .print-clearance .footer-note { page-break-inside: avoid; }
}
</style>

<script>
(function(){
  var deptMap = <?= json_encode(array_column($departments, 'name', 'id')) ?>;
  var roleLevels = <?= json_encode(array_column($roles, 'level', 'id')) ?>;
  var jobsByDept = <?= json_encode(array_values(array_map(fn($j) => ['id' => $j['id'], 'title' => $j['title'], 'department_id' => $j['department_id'], 'role_id' => $j['role_id'] ?? null], $jobs))) ?>;
  var managersByDept = <?= json_encode(array_values(array_map(fn($m) => ['id' => $m['id'], 'full_name' => $m['full_name'], 'department_id' => (int)($m['department_id'] ?? 0)], $managers))) ?>;
  var roleInfo = <?php
    $permsById = array_column($permissions, null, 'id');
    $roleInfoData = [];
    foreach ($roles as $r) {
        $ids = $rolePerms[$r['id']] ?? [];
        $names = [];
        foreach ($ids as $pid) { if (isset($permsById[$pid])) $names[] = $permsById[$pid]['name']; }
        $roleInfoData[$r['id']] = ['level' => (int)$r['level'], 'perms' => $names];
    }
    echo json_encode($roleInfoData, JSON_UNESCAPED_UNICODE);
  ?>;
  var editDept = document.getElementById('emp-edit-department');
  var editJob = document.getElementById('emp-edit-job');
  var editManager = document.getElementById('emp-edit-manager');
  var editRole = document.getElementById('emp-edit-role');
  var editRoleInfo = document.getElementById('emp-edit-role-info');
  function renderRoleInfo(roleId, infoEl) {
    if (!infoEl) return;
    var info = roleInfo[roleId];
    if (!info) { infoEl.textContent = ''; return; }
    var permsText = info.perms.length ? info.perms.join('، ') : 'لا توجد صلاحيات محددة لهذا الدور';
    infoEl.textContent = 'المستوى: ' + info.level + ' — الصلاحيات: ' + permsText;
  }
  if (editRole) {
    renderRoleInfo(editRole.value, editRoleInfo);
    editRole.addEventListener('change', function(){ renderRoleInfo(editRole.value, editRoleInfo); });
  }
  var editPtt = document.getElementById('emp-edit-ptt');
  var currentJobId = '<?= (int)($employee['job_id'] ?? 0) ?>';
  var currentManagerId = '<?= (int)($employee['manager_id'] ?? 0) ?>';

  function fillJobs(select, deptId, selectedJobId) {
    selectedJobId = String(selectedJobId || '');
    select.innerHTML = '<option value="">—</option>';
    if (jobsByDept.length === 0) {
      select.innerHTML = '<option value="">— لا توجد وظائف في النظام —</option>';
      console.warn('jobsByDept is empty: jobs table has no active jobs');
      return;
    }
    var matched = [];
    if (!deptId) {
      matched = jobsByDept;
    } else {
      matched = jobsByDept.filter(function(j) { return String(j.department_id) === String(deptId); });
    }
    // إظهار وظائف القسم المحدد فقط (دون رجوع لكل الوظائف)
    // نبقي الوظيفة الحالية للموظف ضمن الخيارات حتى لا تُفقد عند تغير قسمها
    if (selectedJobId && !matched.some(function(j){ return String(j.id) === selectedJobId; })) {
      var currentJob = jobsByDept.find(function(j){ return String(j.id) === selectedJobId; });
      if (currentJob) matched.unshift(currentJob);
    }
    matched.forEach(function(j) {
      var opt = document.createElement('option');
      opt.value = j.id;
      opt.textContent = j.title;
      if (String(j.id) === selectedJobId) opt.selected = true;
      select.appendChild(opt);
    });
  }
  function fillManagers(select, deptId, selectedManagerId) {
    // موحّد مع صفحة فتح الملف: يظهر جميع الموظفين دائماً كخيارات للمدير المباشر، بغض النظر عن القسم
    selectedManagerId = String(selectedManagerId || '');
    select.innerHTML = '<option value="">—</option>';
    managersByDept.forEach(function(m) {
      var opt = document.createElement('option');
      opt.value = m.id;
      opt.textContent = m.full_name;
      if (String(m.id) === selectedManagerId) opt.selected = true;
      select.appendChild(opt);
    });
  }
  function setRoleByJob(jobId, roleSelect, adminLevelSelect) {
    var job = jobsByDept.find(function(j){ return String(j.id) === String(jobId); });
    if (!job || !job.role_id) return;
    if (roleSelect) {
      roleSelect.value = job.role_id;
      if (typeof window.refreshSelect2 === 'function') window.refreshSelect2(roleSelect);
      if (roleSelect === editRole) renderRoleInfo(roleSelect.value, editRoleInfo);
    }
    if (adminLevelSelect && roleLevels[job.role_id]) adminLevelSelect.value = roleLevels[job.role_id];
  }
  function updateDepartmentByJob(jobId) {
    var job = jobsByDept.find(function(j){ return String(j.id) === String(jobId); });
    if (job && job.department_id) {
      editDept.value = job.department_id;
      if (typeof window.refreshSelect2 === 'function') window.refreshSelect2(editDept);
      fillManagers(editManager, job.department_id, currentManagerId);
      if (typeof window.refreshSelect2 === 'function') window.refreshSelect2(editManager);
    }
  }
  function filterPtt(pttSelect, deptName) {
    deptName = String(deptName || '');
    var $sel = $(pttSelect);
    var full = $sel.data('fullOptions');
    if (!full) {
      full = [];
      $sel.find('option').each(function(){
        var $o = $(this);
        full.push({value: $o.val(), text: $o.text(), dept: $o.data('department') || ''});
      });
      $sel.data('fullOptions', full);
    }
    var selected = $sel.val() || [];
    if (!Array.isArray(selected)) selected = [selected];
    $sel.empty();
    full.forEach(function(o){
      var isSelected = selected.indexOf(o.value) !== -1;
      var show = true;
      if (!show) return;
      var opt = new Option(o.text, o.value, false, isSelected);
      $(opt).data('department', o.dept);
      $sel.append(opt);
    });
    if ($sel.hasClass('select2-hidden-accessible')) {
      $sel.select2('destroy');
    }
    if (typeof window.reinitSelect2 === 'function') window.reinitSelect2(pttSelect);
    else $sel.select2({ placeholder: 'اختر القنوات (يمكنك تحديد أكثر من قناة)', allowClear: true, dir: 'rtl' });
  }

  if (editDept && editJob) {
    fillJobs(editJob, editDept.value, currentJobId);
    fillManagers(editManager, editDept.value, currentManagerId);
    filterPtt(editPtt, deptMap[editDept.value] || '');
    editDept.addEventListener('change', function() {
      fillJobs(editJob, editDept.value, '');
      fillManagers(editManager, editDept.value, '');
      filterPtt(editPtt, deptMap[editDept.value] || '');
      if (typeof window.refreshSelect2 === 'function') {
        window.refreshSelect2(editJob);
        window.refreshSelect2(editManager);
      }
    });
    editJob.addEventListener('change', function() {
      setRoleByJob(editJob.value, editRole, null);
      updateDepartmentByJob(editJob.value);
    });
  }

  var editUserId = document.getElementById('emp-edit-user-id');
  var editUsername = document.getElementById('emp-edit-username');
  if (editUserId && editUsername) {
    editUserId.addEventListener('change', function() {
      var opt = editUserId.options[editUserId.selectedIndex];
      var username = opt.getAttribute('data-username') || '';
      editUsername.value = username;
    });
  }

  (function(){
    var passwordInput = document.getElementById('emp-edit-password');
    var toggleBtn = document.getElementById('emp-edit-toggle-password');
    if (passwordInput && toggleBtn) {
      toggleBtn.addEventListener('click', function() {
        var icon = toggleBtn.querySelector('i');
        if (passwordInput.type === 'password') {
          passwordInput.type = 'text';
          if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
          toggleBtn.title = 'إخفاء كلمة المرور';
        } else {
          passwordInput.type = 'password';
          if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
          toggleBtn.title = 'إظهار كلمة المرور';
        }
      });
    }
  })();

  (function(){
    var typeSelect = document.getElementById('emp-edit-attendance-type');
    var hoursRow = document.getElementById('emp-edit-required-hours-row');
    var hoursInput = document.getElementById('emp-edit-required-hours');
    var defaultHours = 11;
    var oldDefaults = ['', '0', '8'];
    function toggleHours() {
      if (typeSelect.value === 'flexible') {
        hoursRow.style.display = '';
        hoursInput.disabled = false;
        hoursInput.setAttribute('min', '0.5');
        if (oldDefaults.indexOf(hoursInput.value) !== -1) hoursInput.value = String(defaultHours);
      } else {
        hoursRow.style.display = 'none';
        hoursInput.disabled = true;
        hoursInput.removeAttribute('min');
        hoursInput.value = '0';
      }
    }
    if (typeSelect) {
      typeSelect.addEventListener('change', toggleHours);
      toggleHours();
    }
  })();

  (function(){
    var statusSelect = document.querySelector('select[name="status"]');
    var resignationSection = document.getElementById('resignation-section');
    var resignationDate = document.getElementById('resignation_date');
    var daysWorked = document.getElementById('days_worked');
    var dailySalary = document.getElementById('daily_salary');
    var totalDue = document.getElementById('total_due');
    var salaryAmount = document.getElementById('salary_amount');
    var absenceDays = document.getElementById('absence_days');
    var absenceDeduction = document.getElementById('absence_deduction');
    var overtimeAmount = document.getElementById('overtime_amount');
    var advancesAmount = document.getElementById('advances_amount');
    var housingDeduction = document.getElementById('housing_deduction');
    var otherDeductions = document.getElementById('other_deductions');
    var insuranceRefund = document.getElementById('insurance_refund');
    var baseSalaryInput = document.querySelector('input[name="base_salary"]');

    function getBaseSalary() {
      return parseFloat(baseSalaryInput && baseSalaryInput.value) || 0;
    }
    function calcDailySalary() {
      return Math.round((getBaseSalary() / 30) * 100) / 100;
    }
    function calcAbsence() {
      var days = parseFloat(daysWorked && daysWorked.value) || 0;
      var absence = Math.max(0, 30 - days);
      return { days: absence, deduction: Math.round(absence * calcDailySalary() * 100) / 100 };
    }
    function calcTotalDue() {
      var salary = parseFloat(salaryAmount && salaryAmount.value) || 0;
      var absence = parseFloat(absenceDeduction && absenceDeduction.value) || 0;
      var overtime = parseFloat(overtimeAmount && overtimeAmount.value) || 0;
      var advances = parseFloat(advancesAmount && advancesAmount.value) || 0;
      var housing = parseFloat(housingDeduction && housingDeduction.value) || 0;
      var other = parseFloat(otherDeductions && otherDeductions.value) || 0;
      var insurance = parseFloat(insuranceRefund && insuranceRefund.value) || 0;
      return Math.round((salary - absence + overtime - advances - housing - other + insurance) * 100) / 100;
    }
    function updateResignationCalculations() {
      if (dailySalary) dailySalary.value = calcDailySalary().toFixed(2);
      var absence = calcAbsence();
      if (absenceDays && !absenceDays.dataset.manual) absenceDays.value = absence.days.toFixed(2);
      if (absenceDeduction && !absenceDeduction.dataset.manual) absenceDeduction.value = absence.deduction.toFixed(2);
      if (totalDue) totalDue.value = calcTotalDue().toFixed(2);
    }
    function updateTotalOnly() {
      if (totalDue) totalDue.value = calcTotalDue().toFixed(2);
    }
    function markManual(e) {
      e.target.dataset.manual = '1';
    }
    var fileStatusSelect = document.querySelector('select[name="file_status"]');

    function syncFileStatus() {
      if (!statusSelect || !fileStatusSelect) return;
      var status = statusSelect.value;
      if (status === 'terminated' || status === 'resigned') {
        fileStatusSelect.value = 'closed';
      } else if (status === 'active') {
        fileStatusSelect.value = 'open';
      }
      if (typeof window.refreshSelect2 === 'function') window.refreshSelect2(fileStatusSelect);
    }

    function toggleResignationSection() {
      if (!statusSelect || !resignationSection) return;
      if (statusSelect.value === 'resigned') {
        resignationSection.style.display = 'block';
        if (!resignationDate || !resignationDate.value) {
          var today = new Date();
          resignationDate.value = today.toISOString().split('T')[0];
        }
        updateResignationCalculations();
      } else {
        resignationSection.style.display = 'none';
      }
    }
    if (statusSelect) {
      statusSelect.addEventListener('change', function() { toggleResignationSection(); syncFileStatus(); });
      toggleResignationSection();
      syncFileStatus();
    }
    if (daysWorked) {
      daysWorked.addEventListener('input', function() {
        if (absenceDays) absenceDays.dataset.manual = '';
        if (absenceDeduction) absenceDeduction.dataset.manual = '';
        updateResignationCalculations();
      });
    }
    if (baseSalaryInput) {
      baseSalaryInput.addEventListener('input', function() {
        if (statusSelect && statusSelect.value === 'resigned') updateResignationCalculations();
      });
    }
    [salaryAmount, overtimeAmount, advancesAmount, housingDeduction, otherDeductions, insuranceRefund].forEach(function(el) {
      if (el) el.addEventListener('input', updateTotalOnly);
    });
    if (absenceDays) absenceDays.addEventListener('input', function() { markManual({target: absenceDays}); updateTotalOnly(); });
    if (absenceDeduction) absenceDeduction.addEventListener('input', function() { markManual({target: absenceDeduction}); updateTotalOnly(); });
  })();
})();

function printResignationClearance() {
  var currencyLabel = '<?= Helpers::e(App::config('currency')) ?>';
  function fmtMoney(val) { return parseFloat(val || 0).toFixed(2) + ' ' + currencyLabel; }
  function setText(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; }
  var printDate = document.getElementById('resignation_date');
  var printDays = document.getElementById('days_worked');
  var printDaily = document.getElementById('daily_salary');
  var printReason = document.getElementById('resignation_reason');
  var printNotes = document.getElementById('resignation_notes');
  setText('print-resignation-date', printDate && printDate.value || '-');
  setText('print-days-worked', printDays && printDays.value || '-');
  setText('print-daily-salary', printDaily && fmtMoney(printDaily.value));
  setText('print-reason', printReason && printReason.value || '-');
  setText('print-notes', printNotes && printNotes.value || '-');

  var salaryAmount = document.getElementById('salary_amount');
  var absenceDays = document.getElementById('absence_days');
  var absenceDeduction = document.getElementById('absence_deduction');
  var overtimeAmount = document.getElementById('overtime_amount');
  var advancesAmount = document.getElementById('advances_amount');
  var housingDeduction = document.getElementById('housing_deduction');
  var otherDeductions = document.getElementById('other_deductions');
  var insuranceRefund = document.getElementById('insurance_refund');
  var totalDue = document.getElementById('total_due');
  setText('print-salary-amount', salaryAmount && fmtMoney(salaryAmount.value));
  var absenceCell = document.getElementById('print-absence-deduction');
  if (absenceCell) absenceCell.textContent = fmtMoney(absenceDeduction && absenceDeduction.value);
  var absenceLabelCell = absenceCell && absenceCell.previousElementSibling;
  if (absenceLabelCell) absenceLabelCell.textContent = 'استقطاع الغياب (' + (absenceDays && absenceDays.value || 0) + ' يوم)';
  setText('print-overtime-amount', overtimeAmount && fmtMoney(overtimeAmount.value));
  setText('print-advances-amount', advancesAmount && fmtMoney(advancesAmount.value));
  setText('print-housing-deduction', housingDeduction && fmtMoney(housingDeduction.value));
  setText('print-other-deductions', otherDeductions && fmtMoney(otherDeductions.value));
  setText('print-insurance-refund', insuranceRefund && fmtMoney(insuranceRefund.value));
  setText('print-total-due', totalDue && fmtMoney(totalDue.value));

  var rows = document.querySelectorAll('#print-custody-body tr');
  rows.forEach(function(row) {
    var itemCell = row.cells[1];
    if (!itemCell) return;
    var itemName = itemCell.textContent.trim();
    var checkbox = document.querySelector('input[data-item="' + itemName.replace(/"/g, '\\"') + '"]');
    var statusCell = row.cells[7];
    if (statusCell) statusCell.textContent = (checkbox && checkbox.checked) ? '☑ تم التسليم' : '☐ لم يُسلّم';
  });

  window.print();
}
</script>

<script>
(function() {
  var modal = document.getElementById('createShiftModal');
  var openBtn = document.getElementById('btn-create-shift-popup');
  var closeBtn = document.getElementById('btn-cancel-shift-popup');
  var form = document.getElementById('create-shift-form');
  var message = document.getElementById('create-shift-message');
  var shiftSelect = document.getElementById('emp-edit-shift-id');

  function openModal() {
    if (!modal) return;
    modal.style.display = 'flex';
    if (form) form.reset();
    if (message) message.innerHTML = '';
  }
  function closeModal() {
    if (!modal) return;
    modal.style.display = 'none';
  }

  if (openBtn) openBtn.addEventListener('click', openModal);
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === modal) closeModal();
    });
  }

  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      var data = new FormData(form);
      if (message) message.innerHTML = '<span style="color:#6b7280">جاري الحفظ...</span>';
      fetch('/hr/shifts', {
        method: 'POST',
        body: data,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(function(res) {
        if (!res.ok) throw new Error('رد الخادم: ' + res.status);
        return res.json();
      })
      .then(function(body) {
        if (body && body.ok && body.shift) {
          var shift = body.shift;
          var label = (shift.name || '') + ' (' + (shift.start_time || '') + ' - ' + (shift.end_time || '') + ')';
          if (shiftSelect) {
            var option = document.createElement('option');
            option.value = shift.id;
            option.textContent = label;
            shiftSelect.appendChild(option);
            shiftSelect.value = shift.id;
            // تحديث Select2 إذا كان مُفعّلاً
            try {
              if (window.jQuery && jQuery(shiftSelect).data('select2')) {
                jQuery(shiftSelect).trigger('change');
              }
            } catch (err) {}
          }
          if (message) message.innerHTML = '<span style="color:#10b981">تم إنشاء الوردية بنجاح.</span>';
          setTimeout(closeModal, 700);
        } else {
          if (message) message.innerHTML = '<span style="color:#ef4444">' + (body && body.error ? body.error : 'فشل إنشاء الوردية') + '</span>';
        }
      })
      .catch(function(err) {
        if (message) message.innerHTML = '<span style="color:#ef4444">حدث خطأ: ' + (err && err.message ? err.message : 'غير معروف') + '</span>';
      });
      return false;
    });
  }
})();
</script>