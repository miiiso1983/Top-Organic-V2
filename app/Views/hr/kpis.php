<?php use App\Core\Helpers; ?>
<script>
var KPI_JOBS = <?= json_encode(array_map(fn($j) => [
    'id' => (int)$j['id'],
    'title' => (string)$j['title'],
    'department_id' => $j['department_id'] ? (int)$j['department_id'] : null,
], $jobs), JSON_UNESCAPED_UNICODE) ?>;
var KPI_DEPARTMENTS = <?= json_encode(array_map(fn($d) => [
    'id' => (int)$d['id'],
    'name' => (string)$d['name'],
], $departments), JSON_UNESCAPED_UNICODE) ?>;
var KPI_LIST = <?= json_encode(array_map(fn($k) => [
    'id' => (int)$k['id'],
    'weight' => (float)$k['weight'],
    'is_active' => (bool)$k['is_active'],
    'job_ids' => array_values(array_filter(array_map('intval', explode(',', (string)($k['job_ids'] ?? ''))))),
], $kpis), JSON_UNESCAPED_UNICODE) ?>;
</script>

<div class="card">
  <h3>عرض مؤشرات الأداء حسب الوظيفة</h3>
  <div class="form-inline">
    <div class="form-row"><label>1. الوظيفة</label>
      <select id="view_job" class="kpi-job-select" data-dept-target="#view_dept_display" data-placeholder="اختر الوظيفة">
        <option value=""></option>
        <?php foreach ($jobs as $j): ?>
        <option value="<?= $j['id'] ?>" data-department-id="<?= (int)($j['department_id'] ?? 0) ?>"><?= Helpers::e($j['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>2. القسم (تلقائي)</label>
      <input type="text" id="view_dept_display" class="kpi-dept-display" readonly placeholder="يظهر تلقائياً بعد اختيار الوظيفة">
    </div>
    <div class="form-row" style="flex:0 0 auto">
      <button type="button" id="view_kpis_btn" class="btn btn-primary">عرض</button>
      <button type="button" id="view_kpis_reset" class="btn btn-secondary">إظهار الكل</button>
    </div>
  </div>
  <div id="view_summary" style="display:none;margin-top:10px" class="badge badge-info"></div>
</div>

<div class="card" style="margin-top:16px">
  <h3>إضافة مؤشر أداء جديد</h3>
  <form method="post" action="/hr/kpis" class="form-inline" id="add-kpi-form">
    <div class="form-row"><label>1. الوظيفة المرتبطة</label>
      <div>
        <select name="job_id[]" id="add_kpi_jobs" class="kpi-job-select" multiple required data-placeholder="اختر وظيفة واحدة أو أكثر" data-dept-target="#add_kpi_dept_display">
          <?php foreach ($jobs as $j): ?>
          <option value="<?= $j['id'] ?>" data-department-id="<?= (int)($j['department_id'] ?? 0) ?>"><?= Helpers::e($j['title']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-sm btn-secondary kpi-select-all" data-target="#add_kpi_jobs">اختيار الكل</button>
      </div>
    </div>
    <div class="form-row"><label>2. القسم (تلقائي)</label>
      <input type="text" id="add_kpi_dept_display" class="kpi-dept-display" readonly placeholder="يظهر تلقائياً حسب الوظيفة المختارة">
    </div>
    <div class="form-row"><label>3. الاسم</label><input name="name" required maxlength="150"></div>
    <div class="form-row"><label>4. الوصف</label><textarea name="description" rows="2"></textarea></div>
    <div class="form-row"><label>5. الوزن النسبي (%)</label><input type="number" name="weight" id="add_kpi_weight" min="0" max="100" step="0.01" value="0"></div>
    <div class="form-row" style="flex-basis:100%;margin-top:-4px">
      <span id="add_kpi_weight_info" class="badge"></span>
    </div>
    <button class="btn btn-success" id="add_kpi_submit">إضافة</button>
  </form>
</div>

<div class="card" style="margin-top:16px">
  <h4>قائمة مؤشرات الأداء</h4>
  <div style="overflow:auto">
    <table>
      <tr><th>الوظيفة المرتبطة</th><th>القسم</th><th>الاسم</th><th>الوصف</th><th>الوزن (%)</th><th>حفظ</th><th></th><th>حذف</th></tr>
      <?php foreach ($kpis as $k):
        $rowJobIds = array_filter(array_map('intval', explode(',', (string)($k['job_ids'] ?? ''))));
      ?>
      <tr class="kpi-row" data-kpi-id="<?= $k['id'] ?>" data-job-ids="<?= Helpers::e(implode(',', $rowJobIds)) ?>">
        <td>
          <select name="job_id[]" id="jobs_edit_<?= $k['id'] ?>" class="kpi-job-select" form="kpi-form-<?= $k['id'] ?>" multiple required data-placeholder="اختر وظيفة واحدة أو أكثر" data-dept-target="#dept_display_<?= $k['id'] ?>">
            <?php foreach ($jobs as $j): ?>
            <option value="<?= $j['id'] ?>" data-department-id="<?= (int)($j['department_id'] ?? 0) ?>" <?= in_array((int)$j['id'], $rowJobIds, true) ? 'selected' : '' ?>><?= Helpers::e($j['title']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-sm btn-secondary kpi-select-all" data-target="#jobs_edit_<?= $k['id'] ?>">اختيار الكل</button>
        </td>
        <td>
          <input type="text" id="dept_display_<?= $k['id'] ?>" class="kpi-dept-display" readonly>
        </td>
        <td><input name="name" form="kpi-form-<?= $k['id'] ?>" value="<?= Helpers::e($k['name']) ?>" required maxlength="150"></td>
        <td><input name="description" form="kpi-form-<?= $k['id'] ?>" value="<?= Helpers::e($k['description'] ?? '') ?>"></td>
        <td>
          <input type="number" name="weight" id="weight_edit_<?= $k['id'] ?>" class="kpi-weight-input" data-kpi-id="<?= $k['id'] ?>" form="kpi-form-<?= $k['id'] ?>" min="0" max="100" step="0.01" value="<?= $k['weight'] ?>" style="width:80px">
          <div><span id="weight_info_<?= $k['id'] ?>" class="badge" style="margin-top:4px"></span></div>
        </td>
        <td>
          <form id="kpi-form-<?= $k['id'] ?>" method="post" action="/hr/kpis/<?= $k['id'] ?>" style="display:inline">
            <button class="btn btn-sm btn-primary">حفظ</button>
          </form>
        </td>
        <td>
          <form method="post" action="/hr/kpis/<?= $k['id'] ?>/toggle" style="display:inline">
            <button class="btn btn-sm btn-<?= $k['is_active'] ? 'warning' : 'success' ?>"><?= $k['is_active'] ? 'إيقاف' : 'تفعيل' ?></button>
          </form>
        </td>
        <td>
          <form method="post" action="/hr/kpis/<?= $k['id'] ?>/delete" style="display:inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا المؤشر؟');">
            <button type="submit" class="btn btn-sm btn-danger">حذف</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$kpis): ?><tr><td colspan="8">لا توجد مؤشرات</td></tr><?php endif; ?>
    </table>
  </div>
</div>

<script>
(function(){
  function departmentNamesForJobSelect(jobSelect) {
    var deptIds = [];
    Array.prototype.forEach.call(jobSelect.selectedOptions, function(o) {
      var id = o.getAttribute('data-department-id');
      if (id && id !== '0' && deptIds.indexOf(id) === -1) deptIds.push(id);
    });
    if (!deptIds.length) return '';
    return deptIds.map(function(id) {
      var d = KPI_DEPARTMENTS.find(function(x) { return String(x.id) === String(id); });
      return d ? d.name : '';
    }).filter(Boolean).join('، ');
  }

  function updateDeptDisplay(jobSelect) {
    var targetSel = jobSelect.getAttribute('data-dept-target');
    var target = targetSel ? document.querySelector(targetSel) : null;
    if (!target) return;
    target.value = departmentNamesForJobSelect(jobSelect);
  }

  document.querySelectorAll('.kpi-job-select').forEach(function(jobSelect) {
    updateDeptDisplay(jobSelect);
    if (window.jQuery) {
      jQuery(jobSelect).on('change', function() { updateDeptDisplay(jobSelect); });
    } else {
      jobSelect.addEventListener('change', function() { updateDeptDisplay(jobSelect); });
    }
  });

  document.querySelectorAll('.kpi-select-all').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var target = document.querySelector(btn.getAttribute('data-target'));
      if (!target) return;
      Array.prototype.forEach.call(target.options, function(o) { if (!o.disabled) o.selected = true; });
      if (window.jQuery) jQuery(target).trigger('change');
      updateDeptDisplay(target);
    });
  });

  // ===== التحقق من مجموع الأوزان (لا يتجاوز 100% لأي وظيفة) =====
  function selectedJobIds(selectEl) {
    if (!selectEl) return [];
    return Array.prototype.map.call(selectEl.selectedOptions, function(o) { return parseInt(o.value, 10); })
      .filter(function(n) { return !isNaN(n); });
  }

  function jobTitle(id) {
    var j = KPI_JOBS.find(function(x) { return x.id === id; });
    return j ? j.title : 'محددة';
  }

  // يحسب أعلى مجموع متوقع بين الوظائف المختارة بعد إضافة newWeight، باحتساب مستقل لكل وظيفة
  // (لا يُحتسب إلا المؤشرات المرتبطة صراحةً بالوظيفة نفسها، دون أي احتساب عام مشترك بين الوظائف)
  function maxExpectedSum(jobIds, excludeKpiId, newWeight) {
    var affected = jobIds.length ? jobIds : KPI_JOBS.map(function(j) { return j.id; });
    var maxSum = 0, maxJobId = jobIds.length ? jobIds[0] : null;
    affected.forEach(function(jid) {
      var sum = newWeight;
      KPI_LIST.forEach(function(k) {
        if (!k.is_active || k.id === excludeKpiId) return;
        if (k.job_ids.indexOf(jid) !== -1) sum += k.weight;
      });
      if (sum > maxSum) { maxSum = sum; maxJobId = jid; }
    });
    return { sum: maxSum, jobId: maxJobId };
  }

  function setWeightBadge(el, sum) {
    if (!el) return;
    el.textContent = 'الإجمالي المتوقع: ' + sum.toFixed(2) + '% من 100%';
    el.className = 'badge ' + (sum > 100.0001 ? 'badge-danger' : 'badge-success');
  }

  // -- نموذج الإضافة --
  var addJobsSel = document.getElementById('add_kpi_jobs');
  var addWeightInput = document.getElementById('add_kpi_weight');
  var addWeightInfo = document.getElementById('add_kpi_weight_info');
  function refreshAddWeightInfo() {
    var jobIds = selectedJobIds(addJobsSel);
    var newWeight = parseFloat(addWeightInput.value) || 0;
    var res = maxExpectedSum(jobIds, 0, newWeight);
    setWeightBadge(addWeightInfo, res.sum);
  }
  if (addJobsSel && addWeightInput && addWeightInfo) {
    addJobsSel.addEventListener('change', refreshAddWeightInfo);
    if (window.jQuery) jQuery(addJobsSel).on('change', refreshAddWeightInfo);
    addWeightInput.addEventListener('input', refreshAddWeightInfo);
    refreshAddWeightInfo();
  }
  var addForm = document.getElementById('add-kpi-form');
  if (addForm) {
    addForm.addEventListener('submit', function(e) {
      var jobIds = selectedJobIds(addJobsSel);
      var newWeight = parseFloat(addWeightInput.value) || 0;
      var res = maxExpectedSum(jobIds, 0, newWeight);
      if (res.sum > 100.0001) {
        e.preventDefault();
        alert('لا يمكن الحفظ: مجموع أوزان المؤشرات لوظيفة "' + jobTitle(res.jobId) + '" سيصبح ' + res.sum.toFixed(2) + '% ويتجاوز الحد المسموح 100%.');
      }
    });
  }

  // -- صفوف الجدول (تعديل) --
  document.querySelectorAll('.kpi-weight-input').forEach(function(input) {
    var kpiId = parseInt(input.getAttribute('data-kpi-id'), 10);
    var infoEl = document.getElementById('weight_info_' + kpiId);
    var jobSel = document.getElementById('jobs_edit_' + kpiId);
    function refresh() {
      var jobIds = selectedJobIds(jobSel);
      var newWeight = parseFloat(input.value) || 0;
      var res = maxExpectedSum(jobIds, kpiId, newWeight);
      setWeightBadge(infoEl, res.sum);
    }
    input.addEventListener('input', refresh);
    if (jobSel) {
      jobSel.addEventListener('change', refresh);
      if (window.jQuery) jQuery(jobSel).on('change', refresh);
    }
    refresh();
    var formEl = document.getElementById('kpi-form-' + kpiId);
    if (formEl) {
      formEl.addEventListener('submit', function(e) {
        var jobIds = selectedJobIds(jobSel);
        var newWeight = parseFloat(input.value) || 0;
        var res = maxExpectedSum(jobIds, kpiId, newWeight);
        if (res.sum > 100.0001) {
          e.preventDefault();
          alert('لا يمكن الحفظ: مجموع أوزان المؤشرات لوظيفة "' + jobTitle(res.jobId) + '" سيصبح ' + res.sum.toFixed(2) + '% ويتجاوز الحد المسموح 100%.');
        }
      });
    }
  });

  // ===== عرض مؤشرات الأداء حسب الوظيفة المحددة =====
  var viewJobSel = document.getElementById('view_job');
  var viewBtn = document.getElementById('view_kpis_btn');
  var resetBtn = document.getElementById('view_kpis_reset');
  var summaryEl = document.getElementById('view_summary');

  function applyJobFilter() {
    var jobId = viewJobSel && viewJobSel.value ? parseInt(viewJobSel.value, 10) : null;
    var rows = document.querySelectorAll('.kpi-row');
    var count = 0;
    var totalWeight = 0;
    var totalWeightActive = 0;
    rows.forEach(function(row) {
      if (!jobId) { row.style.display = ''; count++; return; }
      var idsAttr = row.getAttribute('data-job-ids') || '';
      var ids = idsAttr.split(',').filter(Boolean).map(Number);
      var matches = ids.indexOf(jobId) !== -1; // فقط المؤشرات المرتبطة صراحةً بهذه الوظيفة
      row.style.display = matches ? '' : 'none';
      if (matches) {
        count++;
        var k = KPI_LIST.find(function(x) { return String(x.id) === row.getAttribute('data-kpi-id'); });
        if (k) {
          totalWeight += k.weight;
          if (k.is_active) totalWeightActive += k.weight;
        }
      }
    });
    if (jobId) {
      summaryEl.style.display = '';
      summaryEl.className = 'badge ' + (totalWeightActive > 100.0001 ? 'badge-danger' : 'badge-info');
      summaryEl.textContent = 'وظيفة "' + jobTitle(jobId) + '" — عدد المؤشرات المرتبطة: ' + count
        + ' | إجمالي الوزن (المؤشرات المفعّلة فقط): ' + totalWeightActive.toFixed(2) + '% من 100%';
    } else {
      summaryEl.style.display = 'none';
    }
  }

  if (viewBtn) viewBtn.addEventListener('click', applyJobFilter);
  if (resetBtn) resetBtn.addEventListener('click', function() {
    if (viewJobSel) {
      viewJobSel.value = '';
      if (window.jQuery) jQuery(viewJobSel).val('').trigger('change');
      updateDeptDisplay(viewJobSel);
    }
    applyJobFilter();
  });
})();
</script>
