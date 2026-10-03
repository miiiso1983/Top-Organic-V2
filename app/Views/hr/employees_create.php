<?php use App\Core\{Helpers, Auth}; ?>
<div class="card">
  <h3>فتح ملف موظف جديد</h3>
  <p>
    <a href="/hr/employees" class="btn btn-sm btn-secondary">← الموظفون</a>
  </p>
  <form method="post" action="/hr/employees" class="form-inline" id="emp-create-form">
    <div class="form-row"><label>الاسم الكامل</label><input name="full_name" required></div>
    <div class="form-row"><label>الهاتف</label><input name="phone"></div>
    <div class="form-row"><label>الجنسية</label><input name="nationality" placeholder="مثال: عراقي"></div>
    <input type="hidden" name="residence" value="">
    <div class="form-row"><label>نوع السكن</label>
      <select name="housing_type">
        <option value="" selected>— بدون —</option>
        <option value="yarmouk">سكن اليرموك</option>
        <option value="sebti">سكن سبتي</option>
        <option value="qadisiya">سكن القادسية</option>
        <option value="internal">سكن الداخلي</option>
      </select>
    </div>
    <div class="form-row"><label>القسم</label>
      <select name="department_id" id="emp-add-department">
        <option value="">—</option>
        <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= Helpers::e($d['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الوظيفة</label>
      <select name="job_id" id="emp-add-job">
        <option value="">—</option>
      </select>
    </div>
    <div class="form-row"><label>المدير المباشر</label>
      <select name="manager_id" id="emp-add-manager">
        <option value="">—</option>
        <?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>" data-department="<?= (int)($m['department_id'] ?? 0) ?>"><?= Helpers::e($m['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php if (Auth::can('hr.manage')): ?>
    <div class="form-row"><label>الدور / الصلاحية</label>
      <select name="role_id" id="emp-add-role">
        <option value="">— بدون دور محدد —</option>
        <?php foreach ($roles as $r):
          $isDefaultRole = ($r['name'] === 'موظف');
        ?>
        <option value="<?= $r['id'] ?>" <?= $isDefaultRole ? 'selected' : '' ?>><?= Helpers::e($r['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div id="emp-add-role-info" class="text-muted" style="font-size:12px;margin-top:4px;"></div>
    </div>
    <?php else: ?>
    <div class="form-row"><label>الدور / الصلاحية</label>
      <span class="info-value" style="display:flex;align-items:center;height:36px">موظف (افتراضي)</span>
    </div>
    <?php endif; ?>
    <div class="form-row"><label>اسم المستخدم <small>(يدوي)</small></label><input name="username" placeholder="مثال: ali.hassan" required autocomplete="off"></div>
    <div class="form-row"><label>كلمة المرور <small>(اختياري)</small></label><input type="password" name="password" placeholder="اتركها فارغة لتوليد كلمة مرور عشوائية" autocomplete="new-password"></div>
    <div class="form-row" style="min-width:100%"><small><i class="fa-solid fa-circle-info"></i> عند الحفظ، يُنشئ النظام تلقائياً حساباً مالياً للموظف تحت 1210؛ أما اسم المستخدم فيُدخل يدوياً.</small></div>
    <div class="form-row" style="min-width:100%">
      <label>قنوات PTT المقترحة للقسم</label>
      <select name="ptt_channel_ids[]" id="emp-add-ptt" class="select2-ptt" multiple data-placeholder="اختر القنوات (يمكنك تحديد أكثر من قناة)" style="width:100%">
        <?php foreach ($pttChannels as $c): ?><option value="<?= $c['id'] ?>" data-department="<?= Helpers::e($c['department'] ?? '') ?>"><?= Helpers::e($c['name']) ?> (<?= Helpers::e($c['code']) ?>)</option><?php endforeach; ?>
      </select>
      <small>تظهر جميع القنوات؛ يمكنك تحديد أكثر من قناة ومن أكثر من قسم.</small>
    </div>
    <?php if (Auth::can('hr.employee.salary.view')): ?>
    <div class="form-row"><label>الراتب الأساسي</label><input type="number" name="base_salary" step="0.01" value="0"></div>
    <div class="form-row"><label>نسبة التأمين الشهري %</label><input type="number" name="insurance_percentage" step="0.01" min="0" max="100" value="0"></div>
    <div class="form-row"><label>عدد مرات استقطاع التأمين</label><input type="number" name="insurance_occurrences" step="1" min="0" value="0"></div>
    <?php else: ?>
    <input type="hidden" name="base_salary" value="0">
    <?php endif; ?>
    <div class="form-row"><label>تاريخ التعيين</label><input type="date" name="hire_date" value="<?= date('Y-m-d') ?>"></div>
    <div class="form-row"><label>حالة الموظف</label>
      <select name="status">
        <option value="active">نشط</option>
        <option value="vacation">إجازة</option>
        <option value="suspended">موقوف</option>
        <option value="terminated">منتهي الخدمة</option>
        <option value="resigned">مستقيل</option>
      </select>
    </div>
    <div class="form-row"><label>نظام احتساب الدوام</label>
      <select name="attendance_type" id="emp-add-attendance-type">
        <option value="shift">الوردية الثابتة</option>
        <option value="flexible">ساعات العمل المرنة</option>
      </select>
    </div>
    <div class="form-row"><label>التحقق من الموقع الجغرافي</label>
      <label style="display:flex;align-items:center;gap:6px;font-weight:normal;cursor:pointer">
        <input type="checkbox" name="geofence_exempt" value="1">
        إعفاء الموظف من اشتراط الموقع الجغرافي عند تسجيل الحضور
      </label>
    </div>
    <div class="form-row" id="emp-add-required-hours-row" style="display:none">
      <label>عدد الساعات المطلوبة يومياً</label>
      <input type="number" name="required_hours" id="emp-add-required-hours" step="0.01" min="0.5" max="24" value="11">
    </div>
    <div class="form-row" style="min-width:100%">
      <label>الوردية الثابتة</label>
      <select name="shift_id" class="select2-shift" data-placeholder="اختر الوردية الثابتة" style="width:100%">
        <option value="">— الافتراضية العامة —</option>
        <?php foreach ($shifts as $s): ?>
        <option value="<?= $s['id'] ?>"><?= Helpers::e($s['name']) ?> (<?= Helpers::e($s['start_time']) ?> - <?= Helpers::e($s['end_time']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>يوم العطلة الأسبوعية</label>
      <select name="weekly_off_day">
        <option value="">— الافتراضي (حسب العطل العامة) —</option>
        <?php $weekDays = ['الأحد','الإثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت']; ?>
        <?php foreach ($weekDays as $idx => $dn): ?>
        <option value="<?= $idx ?>"><?= $dn ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الفرع</label>
      <select name="branch_id">
        <option value="">—</option>
        <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= Helpers::e($b['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>مركز الكلفة</label>
      <select name="cost_center_id">
        <option value="">—</option>
        <?php foreach ($costCenters as $c): ?><option value="<?= $c['id'] ?>"><?= Helpers::e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <button type="button" class="btn btn-success" id="btn-open-file" title="F9">فتح الملف (F9)</button>
    <a href="/hr/employees" class="btn btn-secondary">إلغاء</a>
  </form>
</div>

<script>
(function(){
  var deptMap = <?= json_encode(array_column($departments, 'name', 'id')) ?>;
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
  var addDept = document.getElementById('emp-add-department');
  var addJob = document.getElementById('emp-add-job');
  var addManager = document.getElementById('emp-add-manager');
  var addRole = document.getElementById('emp-add-role');
  var addRoleInfo = document.getElementById('emp-add-role-info');
  function renderRoleInfo(roleId, infoEl) {
    if (!infoEl) return;
    var info = roleInfo[roleId];
    if (!info) { infoEl.textContent = ''; return; }
    var permsText = info.perms.length ? info.perms.join('، ') : 'لا توجد صلاحيات محددة لهذا الدور';
    infoEl.textContent = 'المستوى: ' + info.level + ' — الصلاحيات: ' + permsText;
  }
  if (addRole) {
    renderRoleInfo(addRole.value, addRoleInfo);
    addRole.addEventListener('change', function(){ renderRoleInfo(addRole.value, addRoleInfo); });
  }
  var addPtt = document.getElementById('emp-add-ptt');

  function fillJobs(select, deptId) {
    select.innerHTML = '<option value="">—</option>';
    var matched = [];
    if (!deptId) {
      matched = jobsByDept;
    } else {
      // إظهار الوظائف التابعة للقسم المختار فقط
      matched = jobsByDept.filter(function(j) { return String(j.department_id) === String(deptId); });
    }
    matched.forEach(function(j) {
      var opt = document.createElement('option');
      opt.value = j.id;
      opt.textContent = j.title;
      select.appendChild(opt);
    });
  }
  function fillManagers(select) {
    // يظهر جميع الموظفين دائماً كخيارات للمدير المباشر، بغض النظر عن القسم المختار
    var selected = select.value;
    select.innerHTML = '<option value="">—</option>';
    managersByDept.forEach(function(m) {
      var opt = document.createElement('option');
      opt.value = m.id;
      opt.textContent = m.full_name;
      select.appendChild(opt);
    });
    if (selected) select.value = selected;
  }
  function setRoleByJob(jobId, roleSelect) {
    var job = jobsByDept.find(function(j){ return String(j.id) === String(jobId); });
    if (!job || !job.role_id) return;
    if (roleSelect) {
      roleSelect.value = job.role_id;
      if (typeof window.refreshSelect2 === 'function') window.refreshSelect2(roleSelect);
      if (roleSelect === addRole) renderRoleInfo(roleSelect.value, addRoleInfo);
    }
  }
  function filterPtt(pttSelect, deptName) {
    var $sel = $(pttSelect);
    var full = $sel.data('fullOptions');
    if (!full) {
      full = [];
      $sel.find('option').each(function(){ var $o = $(this); full.push({value: $o.val(), text: $o.text(), dept: $o.data('department') || ''}); });
      $sel.data('fullOptions', full);
    }
    var selected = $sel.val() || [];
    if (!Array.isArray(selected)) selected = [selected];
    $sel.empty();
    full.forEach(function(o){ $sel.append(new Option(o.text, o.value, false, selected.indexOf(o.value) !== -1)); });
    if (typeof window.reinitSelect2 === 'function') window.reinitSelect2(pttSelect);
    else $sel.select2({ placeholder: 'اختر القنوات (يمكنك تحديد أكثر من قناة)', allowClear: true, dir: 'rtl' });
  }
  function updateDepartmentByJob(jobId) {
    var job = jobsByDept.find(function(j){ return String(j.id) === String(jobId); });
    if (job && job.department_id) {
      addDept.value = job.department_id;
      if (typeof window.refreshSelect2 === 'function') window.refreshSelect2(addDept);
      fillManagers(addManager);
      if (typeof window.refreshSelect2 === 'function') window.refreshSelect2(addManager);
    }
  }
  if (addDept && addJob) {
    fillJobs(addJob, addDept.value);
    fillManagers(addManager);
    filterPtt(addPtt, deptMap[addDept.value] || '');
    addDept.addEventListener('change', function() {
      fillJobs(addJob, addDept.value);
      fillManagers(addManager);
      filterPtt(addPtt, deptMap[addDept.value] || '');
      if (typeof window.refreshSelect2 === 'function') {
        window.refreshSelect2(addJob);
        window.refreshSelect2(addManager);
      }
    });
    addJob.addEventListener('change', function() { setRoleByJob(addJob.value, addRole); updateDepartmentByJob(addJob.value); });
  }
  (function(){
    var typeSelect = document.getElementById('emp-add-attendance-type');
    var hoursRow = document.getElementById('emp-add-required-hours-row');
    var hoursInput = document.getElementById('emp-add-required-hours');
    var defaultHours = 11;
    var oldDefaults = ['', '0', '8'];
    function toggleHours() {
      if (typeSelect.value === 'flexible') {
        hoursRow.style.display = '';
        hoursInput.disabled = false;
        if (oldDefaults.indexOf(hoursInput.value) !== -1) hoursInput.value = String(defaultHours);
      } else {
        hoursRow.style.display = 'none';
        hoursInput.disabled = true;
        hoursInput.value = '0';
      }
    }
    if (typeSelect) { typeSelect.addEventListener('change', toggleHours); toggleHours(); }
  })();
})();

  // Submit via F9 instead of Enter
  var openFileForm = document.getElementById('emp-create-form');
  var openFileBtn = document.getElementById('btn-open-file');
  function submitOpenFile() {
    if (typeof openFileForm.requestSubmit === 'function') {
      openFileForm.requestSubmit();
    } else {
      openFileForm.submit();
    }
  }
  if (openFileForm && openFileBtn) {
    openFileBtn.addEventListener('click', function(e) { e.preventDefault(); submitOpenFile(); });
    document.addEventListener('keydown', function(e) {
      if (e.key === 'F9') {
        e.preventDefault();
        submitOpenFile();
      }
    });
  }

</script>