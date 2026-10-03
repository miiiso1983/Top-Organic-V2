<?php use App\Core\Helpers; ?>
<?php $employees = $employees ?? []; ?>
<?php $isEdit = !empty($request); ?>
<?php
$leaveFlow = false;
foreach ($template['fields'] as $f) {
    if ($f['name'] === 'request_type') {
        $leaveFlow = true;
        break;
    }
}
$currentUserId = $_SESSION['user_id'] ?? 0;
$currentEmployee = App\Core\Database::query("SELECT id, full_name FROM employees WHERE user_id = ? LIMIT 1", [$currentUserId])->fetch();
$currentEmployeeId = (int)($currentEmployee['id'] ?? 0);
$currentEmployeeName = $currentEmployee['full_name'] ?? '';
$autoEmployee = $currentEmployeeId > 0 && !$isEdit && ($template['code'] ?? '') === 'hr_leave';
?>
<div class="page-head">
  <h2><?= $isEdit ? 'تعديل ' : 'إنشاء ' ?><?= Helpers::e($template['name']) ?></h2>
  <a href="/workflows" class="btn btn-secondary">رجوع</a>
</div>

<div class="card">
  <?php
    if ($isEdit) {
        if ($request['status'] === 'draft') {
            $action = '/workflows/requests/' . $request['id'] . '/submit';
        } elseif ($request['status'] === 'returned_for_edit') {
            $action = '/workflows/requests/' . $request['id'] . '/resubmit';
        } else {
            $action = '/workflows/requests/' . $request['id'] . '/edit';
        }
    } else {
        $action = '/workflows/requests';
    }
  ?>
  <form method="post" action="<?= $action ?>" enctype="multipart/form-data">
    <?php if (!$isEdit): ?><input type="hidden" name="template_id" value="<?= $template['id'] ?>"><?php endif; ?>
    <div class="form-row">
      <label>عنوان الطلب</label>
      <input name="title" value="<?= Helpers::e($request['title'] ?? ($template['name'] . ' - ' . date('Y-m-d'))) ?>" required>
    </div>
    <?php if ($leaveFlow): ?>
    <div class="form-row">
      <label>نوع الطلب *</label>
      <select id="request_category" required>
        <option value="">— اختر نوع الطلب —</option>
        <option value="daily_leave">إجازة يومية</option>
        <option value="hourly_leave">إجازة زمنية</option>
        <option value="exit">إذن خروج</option>
        <option value="delay">تأخير</option>
        <option value="work_resumption">مباشرة عمل</option>
      </select>
    </div>
    <div class="form-row" id="daily_leave_subtype_row" style="display:none">
      <label>نوع الإجازة اليومية *</label>
      <select id="daily_leave_subtype">
        <option value="annual">سنوية</option>
        <option value="sick">مرضية</option>
        <option value="unpaid">بدون راتب</option>
        <option value="emergency">استثنائية</option>
      </select>
    </div>
    <?php endif; ?>
    <?php foreach ($template['fields'] as $f): ?>
    <?php $name = $f['name']; $val = $request['data'][$name] ?? ''; ?>
    <?php if ($name === 'employee_id' && ($f['type'] ?? '') !== 'employee'): ?>
      <input type="hidden" name="employee_id" id="employee_id" value="<?= (int)($val ?: ($autoEmployee ? $currentEmployeeId : 0)) ?>">
      <?php continue; ?>
    <?php endif; ?>
    <?php if ($name === 'employee_name'): ?>
    <?php $selectedEmpName = $val ?: $currentEmployeeName; ?>
    <div class="form-row" data-field="employee_name" <?= $autoEmployee ? 'style="display:none"' : '' ?>>
      <label><?= Helpers::e($f['label']) ?><?= !empty($f['required']) ? ' *' : '' ?></label>
      <select name="employee_name" id="employee_name" class="select2-employee" data-target="#employee_id" <?= !empty($f['required']) ? 'required' : '' ?> style="width:100%">
        <option value="">— اختر موظف —</option>
        <?php foreach ($employees as $emp): ?>
        <option value="<?= Helpers::e($emp['full_name']) ?>" data-id="<?= (int)$emp['id'] ?>" <?= (string)$selectedEmpName === (string)$emp['full_name'] ? 'selected' : '' ?>><?= Helpers::e($emp['full_name']) ?> (<?= Helpers::e($emp['emp_code']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
      <?php continue; ?>
    <?php endif; ?>
    <div class="form-row" data-field="<?= $name ?>">
      <label><?= Helpers::e($f['label']) ?><?= !empty($f['required']) ? ' *' : '' ?></label>
      <?php if ($f['type'] === 'textarea'): ?>
        <textarea name="<?= $name ?>" <?= !empty($f['required']) ? 'required' : '' ?> rows="4"><?= Helpers::e((string)$val) ?></textarea>
      <?php elseif ($f['type'] === 'select'): ?>
        <select name="<?= $name ?>" <?= !empty($f['required']) ? 'required' : '' ?>>
          <?php if ($name === 'department_id'): ?>
          <option value="">— اختر القسم —</option>
          <?php foreach ($departments ?? [] as $d): ?>
          <option value="<?= (int)$d['id'] ?>" <?= (string)$val === (string)$d['id'] ? 'selected' : '' ?>><?= Helpers::e($d['name']) ?></option>
          <?php endforeach; ?>
          <?php else: ?>
          <option value="">—</option>
          <?php foreach ($f['options'] ?? [] as $opt): ?>
          <option value="<?= Helpers::e($opt) ?>" <?= (string)$val === (string)$opt ? 'selected' : '' ?>><?= Helpers::e($opt) ?></option>
          <?php endforeach; ?>
          <?php endif; ?>
        </select>
      <?php elseif ($f['type'] === 'checkbox'): ?>
        <label><input type="checkbox" name="<?= $name ?>" value="1" <?= $val ? 'checked' : '' ?>> <?= Helpers::e($f['label']) ?></label>
      <?php elseif ($f['type'] === 'file'): ?>
        <input type="file" name="<?= $name ?>" <?= !empty($f['required']) && !$val ? 'required' : '' ?>>
      <?php elseif ($f['type'] === 'number' || $name === 'amount'): ?>
        <input type="number" name="<?= $name ?>" value="<?= Helpers::e((string)$val) ?>" <?= !empty($f['required']) ? 'required' : '' ?> step="0.01">
        <?php if ($name === 'amount' && in_array($template['code'] ?? '', ['hr_advance', 'hr_exceptional_advance'], true)): ?>
        <small class="advance-limit-info" id="advance-limit-info">
          <?php if (!empty($advanceLimit) && ($advanceLimit['salary'] ?? 0) > 0): ?>
          الراتب: <?= number_format((float)($advanceLimit['salary'] ?? 0), 2) ?> | الحد الأقصى (25%): <?= number_format((float)($advanceLimit['max'] ?? 0), 2) ?> | المتبقي: <?= number_format((float)($advanceLimit['remaining'] ?? 0), 2) ?>
          <?php elseif (!empty($advanceLimit['error'])): ?>
          <span class="text-danger"><?= Helpers::e($advanceLimit['error']) ?></span>
          <?php else: ?>
          اختر موظف لعرض الحد المسموح
          <?php endif; ?>
        </small>
        <?php endif; ?>
      <?php elseif ($f['type'] === 'employee'): ?>
        <input type="hidden" name="<?= $name ?>" id="<?= $name ?>" value="<?= (int)$val ?>">
        <select class="select2-employee" data-target="#<?= $name ?>" style="width:100%">
          <option value="">— اختر موظف —</option>
          <?php foreach ($employees as $emp): ?>
          <option value="<?= Helpers::e($emp['full_name']) ?>" data-id="<?= (int)$emp['id'] ?>" <?= (int)$val === (int)$emp['id'] ? 'selected' : '' ?>><?= Helpers::e($emp['full_name']) ?> (<?= Helpers::e($emp['emp_code']) ?>)</option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <input type="<?= $f['type'] ?>" name="<?= $name ?>" value="<?= Helpers::e((string)$val) ?>" <?= !empty($f['required']) ? 'required' : '' ?>>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if ($isEdit): ?>
    <div class="form-row">
      <label>ملاحظات إعادة الإرسال</label>
      <textarea name="notes" rows="2" placeholder="ما الذي تم تعديله..."></textarea>
    </div>
    <?php endif; ?>
    <button class="btn btn-success">إرسال الطلب</button>
  </form>
</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2-employee').select2({
        placeholder: 'ابحث باسم الموظف أو الكود',
        allowClear: true,
        dir: 'rtl',
        width: '100%'
    });

    var exceptionalAdvance = <?= json_encode(($template['code'] ?? '') === 'hr_exceptional_advance') ?>;

    function updateAdvanceLimit(employeeId) {
        if (!exceptionalAdvance || !employeeId) {
            $('#advance-limit-info').text('اختر موظف لعرض الحد المسموح');
            return;
        }
        $('#advance-limit-info').text('جاري تحميل الحد المسموح...');
        $.getJSON('/workflows/employees/' + employeeId + '/advance-limit', function(res) {
            var d = res.data || {};
            if ((d.salary || 0) > 0) {
                $('#advance-limit-info').text(
                    'الراتب: ' + Number(d.salary).toFixed(2) +
                    ' | الحد الأقصى (25%): ' + Number(d.max).toFixed(2) +
                    ' | المتبقي: ' + Number(d.remaining).toFixed(2)
                );
            } else {
                $('#advance-limit-info').text(d.error || 'اختر موظف لعرض الحد المسموح');
            }
        }).fail(function() {
            $('#advance-limit-info').text('تعذر تحميل الحد المسموح');
        });
    }

    $('.select2-employee').on('change', function() {
        var target = $($(this).data('target') || '#employee_id');
        var id = $(this).find(':selected').data('id') || '';
        target.val(id);
        updateAdvanceLimit(id);
    }).trigger('change');
});
</script>
<?php if ($leaveFlow): ?>
<script>
(function() {
    function setCategoryFromExistingValues() {
        var requestType = $('[name="request_type"]').val();
        var leaveType = $('[name="leave_type"]').val();
        if (!requestType) return;
        var category = '';
        if (requestType === 'leave') {
            category = (leaveType === 'hourly') ? 'hourly_leave' : 'daily_leave';
        } else if (['exit', 'delay', 'work_resumption'].indexOf(requestType) !== -1) {
            category = requestType;
        }
        if (category) {
            $('#request_category').val(category);
        }
        if (category === 'daily_leave' && leaveType) {
            $('#daily_leave_subtype').val(leaveType);
        }
    }

    function updateLeaveForm() {
        var category = $('#request_category').val();
        var isDaily = category === 'daily_leave';
        var isHourly = category === 'hourly_leave';
        var showTimes = isHourly || category === 'exit' || category === 'delay' || category === 'work_resumption';

        // إخفاء حقول request_type/leave_type الأصلية
        $('[data-field="request_type"], [data-field="leave_type"]').hide();

        // إظهار/إخفاء نوع الإجازة اليومية
        $('#daily_leave_subtype_row').toggle(isDaily);

        // إظهار/إخفاء الحقول الزمنية
        $('[data-field="date_to"]').toggle(isDaily);
        $('[data-field="start_time"], [data-field="end_time"]').toggle(showTimes);

        // تعيين القيم المطلوبة للخادم
        if (isDaily) {
            $('[name="request_type"]').val('leave');
            $('[name="leave_type"]').val($('#daily_leave_subtype').val() || 'annual');
        } else if (isHourly) {
            $('[name="request_type"]').val('leave');
            $('[name="leave_type"]').val('hourly');
        } else if (category === 'exit') {
            $('[name="request_type"]').val('exit');
            $('[name="leave_type"]').val('n/a');
        } else if (category === 'delay') {
            $('[name="request_type"]').val('delay');
            $('[name="leave_type"]').val('n/a');
        } else if (category === 'work_resumption') {
            $('[name="request_type"]').val('work_resumption');
            $('[name="leave_type"]').val('n/a');
        }
    }

    $('#request_category').on('change', updateLeaveForm);
    $('#daily_leave_subtype').on('change', updateLeaveForm);

    setCategoryFromExistingValues();
    updateLeaveForm();
})();
</script>
<?php endif; ?>