<?php use App\Core\Helpers; ?>
<?php
$types = [
    'payroll'=>'تقرير الرواتب','bonuses'=>'تقرير المكافآت','deductions'=>'تقرير الخصومات','raises'=>'تقرير الزيادات','penalties'=>'تقرير الجزاءات',
    'advances'=>'تقرير السلف','long_advances'=>'تقرير السلف طويلة الأجل','attendance'=>'تقرير الحضور','delays'=>'تقرير التأخير','absences'=>'تقرير الغياب',
    'overtime'=>'تقرير العمل الإضافي','leaves'=>'تقرير الإجازات','exits'=>'تقرير الأذونات','evaluations'=>'تقرير الأداء الوظيفي (KPI)','location'=>'تقرير مراقبة المواقع',
    'monthly_performance'=>'تقرير الأداء الشهري للمندوبين/الموظفين','achievement'=>'تقرير الإنجاز','regularity'=>'تقرير الانتظام','payroll_summary'=>'ملخص الرواتب الشهري','work_resumptions'=>'تقرير المباشرات','resignations'=>'تقرير الإغلاقات','fingerprint_forgiveness'=>'طلبات نسيان البصمة','custom'=>'تقارير مخصصة'
];
$statusLabels = ['draft'=>'مسودة','approved'=>'معتمد','paid'=>'مدفوع','pending'=>'معلّق','manager_approved'=>'موافقة مدير','fully_approved'=>'معتمد نهائياً','rejected'=>'مرفوض','settled'=>'مغلقة','active'=>'نشط','expired'=>'منتهي','terminated'=>'منتهي الخدمة','resigned'=>'مستقيل','cancelled'=>'ملغاة'];
$adjustLabels = ['bonus'=>'مكافأة','deduction'=>'خصم','raise'=>'زيادة','penalty'=>'جزاء','tips_addition'=>'إضافي تيبس','salary_reduction'=>'تخفيض راتب','work_days_settlement'=>'تسوية أيام دوام'];
$leaveLabels = ['unpaid_short'=>'إجازة قصيرة بدون راتب','unpaid_long'=>'إجازة طويلة بدون راتب','annual'=>'سنوية','sick'=>'مرضية','unpaid'=>'بدون راتب','emergency'=>'طارئة','hourly'=>'ساعية','n/a'=>'غير محدد'];
$requestLabels = ['leave'=>'إجازة','exit'=>'إذن خروج','delay'=>'تأخير','work_resumption'=>'مباشرة عمل','in'=>'نسيان بصمة دخول','out'=>'نسيان بصمة خروج','both'=>'كلاهما'];
$fileStatusLabels = ['open'=>'مفتوح','closed'=>'مغلق','archived'=>'مأرشف'];
$evalLabels = ['monthly'=>'شهري','quarterly'=>'ربع سنوي','half_yearly'=>'نصف سنوي','annual'=>'سنوي'];
$moneyCols = $report['money'] ?? [];
$columns = $report['columns'] ?? [];
$rows = $report['rows'] ?? [];
$f = $filters;
?>
<div class="card">
  <h3><i class="fa-solid fa-chart-column"></i> <?= Helpers::e($report['title']) ?></h3>
  <form method="get" action="/hr/reports" class="form-inline" style="flex-wrap:wrap">
    <div class="form-row"><label>التقرير</label>
      <select name="type" id="reportTypeSelect"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>" <?= $k === $type ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
    </div>
    <div class="form-row"><label>الفترة</label><input type="text" name="period" id="reportPeriod" value="<?= Helpers::e($f['period']) ?>" placeholder="YYYY-MM" title="عند تحديد الفترة تُتجاهل حقول من/إلى تاريخ"></div>
    <div class="form-row" id="jobFilterRow" style="<?= $type === 'evaluations' ? '' : 'display:none' ?>">
      <label>الوظيفة <?= $type === 'evaluations' ? '<span style="color:var(--danger)">*</span>' : '' ?></label>
      <select name="job_id" id="reportJobSelect" <?= $type === 'evaluations' ? 'required' : '' ?>>
        <option value="0">اختر الوظيفة</option>
        <?php foreach ($jobs as $j): ?><option value="<?= $j['id'] ?>" <?= $j['id'] == ($f['job_id'] ?? 0) ? 'selected' : '' ?>><?= Helpers::e($j['title']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row rpt-generic"><label>من</label><input type="date" name="date_from" id="reportDateFrom" value="<?= Helpers::e($f['date_from']) ?>"></div>
    <div class="form-row rpt-generic"><label>إلى</label><input type="date" name="date_to" id="reportDateTo" value="<?= Helpers::e($f['date_to']) ?>"></div>
    <div class="form-row rpt-generic" style="align-self:flex-end"><small class="text-muted">إذا حددت الفترة يُتجاهل من/إلى تاريخ تلقائياً</small></div>
    <div class="form-row rpt-generic"><label>الموظف</label>
      <select name="employee_id"><option value="0">الكل</option><?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>" <?= $e['id'] == $f['employee_id'] ? 'selected' : '' ?>><?= Helpers::e($e['full_name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="form-row rpt-generic"><label>الفرع</label>
      <select name="branch_id"><option value="0">الكل</option><?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= $b['id'] == $f['branch_id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="form-row rpt-generic"><label>القسم</label>
      <select name="department_id"><option value="0">الكل</option><?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= $d['id'] == $f['department_id'] ? 'selected' : '' ?>><?= Helpers::e($d['name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="form-row rpt-generic"><label>الحالة</label><input type="text" name="status" value="<?= Helpers::e($f['status']) ?>" placeholder="approved/pending..."></div>
    <button class="btn">عرض</button>
  </form>
</div>

<?php if ($type === 'evaluations'): ?>
  <?php if (empty($report['job_id'])): ?>
  <div class="card"><p><i class="fa-solid fa-circle-info"></i> الرجاء اختيار وظيفة من القائمة أعلاه لعرض تقرير الأداء الوظيفي (KPI) الخاص بموظفيها. كل وظيفة لها مؤشرات أداء مختلفة، لذا لا يوجد تقرير أداء عام موحّد.</p></div>
  <?php elseif (!$report['employees']): ?>
  <div class="card"><p>لا يوجد موظفون نشطون حالياً في وظيفة "<?= Helpers::e($report['job_title']) ?>".</p></div>
  <?php else: ?>
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px">
      <h4 style="margin:0"><i class="fa-solid fa-sitemap"></i> مؤشرات الأداء المعتمدة لوظيفة: <?= Helpers::e($report['job_title']) ?></h4>
      <div style="display:flex;gap:8px">
        <a href="/hr/reports/export?<?= http_build_query(array_merge($f, ['type'=>$type,'job_id'=>$report['job_id'],'format'=>'excel'])) ?>" class="btn btn-sm btn-success">Excel</a>
        <a href="/hr/reports/export?<?= http_build_query(array_merge($f, ['type'=>$type,'job_id'=>$report['job_id'],'format'=>'pdf'])) ?>" class="btn btn-sm btn-danger">PDF</a>
      </div>
    </div>
    <?php if ($report['kpis']): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
      <?php foreach ($report['kpis'] as $k): $w = rtrim(rtrim(number_format((float)$k['weight'], 1), '0'), '.'); ?>
      <span class="badge badge-pending" title="<?= Helpers::e($k['description'] ?? '') ?>"><?= Helpers::e($k['name']) ?> (<?= $w ?>)</span>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="text-muted">لا توجد مؤشرات أداء نشطة مرتبطة بهذه الوظيفة حالياً.</p>
    <?php endif; ?>
    <table id="jobPerfTable" data-default-period="<?= date('Y-m') ?>">
      <tr>
        <th>اسم الموظف</th>
        <?php foreach ($report['kpis'] as $k): $w = rtrim(rtrim(number_format((float)$k['weight'], 1), '0'), '.'); ?>
        <th title="<?= Helpers::e($k['description'] ?? '') ?>"><?= Helpers::e($k['name']) ?><br><small class="text-muted">(من <?= $w ?>)</small></th>
        <?php endforeach; ?>
        <th>التقييم النهائي</th>
        <th>عدد المكافآت / كتب الشكر</th>
        <th>الخصومات / بالأيام</th>
        <th>عدد الإنذارات</th>
        <th style="text-align:left">الإجراء</th>
      </tr>
      <?php foreach ($report['employees'] as $emp): $ev = $emp['evaluation']; $locked = $ev && $ev['status'] === 'finalized';
        $itemsByKpi = [];
        foreach ($emp['items'] as $it) { $itemsByKpi[(int)$it['kpi_id']] = $it; }
        $penaltyDays = rtrim(rtrim(number_format((float)$emp['penalty_days'], 1), '0'), '.');
        if ($penaltyDays === '') { $penaltyDays = '0'; }
      ?>
      <tr data-emp-row data-employee-id="<?= (int)$emp['id'] ?>" data-evaluation-id="<?= $ev ? (int)$ev['id'] : 0 ?>">
        <td><?= Helpers::e($emp['full_name']) ?></td>
        <?php foreach ($report['kpis'] as $k):
          $it = $itemsByKpi[(int)$k['id']] ?? null;
          $score = $it ? (float)$it['score'] : 0;
        ?>
        <td>
          <input type="number" class="pj-kpi-input" min="0" max="100" step="1"
                 data-kpi-id="<?= (int)$k['id'] ?>" data-weight="<?= Helpers::e((string)$k['weight']) ?>"
                 value="<?= $score ?>" disabled style="width:65px;text-align:center">
        </td>
        <?php endforeach; ?>
        <td class="pj-final-score"><strong><?= $ev ? round((float)$ev['kpi_score'], 1) : 0 ?></strong>%</td>
        <td><?= (int)$emp['bonus_count'] ?></td>
        <td><?= $penaltyDays ?></td>
        <td><?= (int)$emp['warning_count'] ?></td>
        <td style="white-space:nowrap">
          <?php if ($locked): ?>
          <span class="badge badge-success">معتمد</span>
          <?php else: ?>
          <button type="button" class="btn btn-sm pj-edit-btn">تعديل</button>
          <button type="button" class="btn btn-sm btn-success pj-save-btn" disabled>حفظ</button>
          <button type="button" class="btn btn-sm btn-danger pj-delete-btn" <?= $ev ? '' : 'disabled' ?>>مسح</button>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
<?php elseif ($rows): ?>
<div class="card">
  <div style="display:flex;gap:10px;margin-bottom:10px">
    <a href="/hr/reports/export?<?= http_build_query(array_merge($f, ['type'=>$type,'format'=>'excel'])) ?>" class="btn btn-sm btn-success">Excel</a>
    <a href="/hr/reports/export?<?= http_build_query(array_merge($f, ['type'=>$type,'format'=>'pdf'])) ?>" class="btn btn-sm btn-danger">PDF</a>
    <button onclick="window.print()" class="btn btn-sm btn-light">طباعة</button>
  </div>
  <table>
    <tr><?php foreach ($report['headers'] as $h): ?><th><?= Helpers::e($h) ?></th><?php endforeach; ?></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <?php foreach ($columns as $key): ?>
      <?php $val = $r[$key] ?? ''; ?>
      <td>
        <?php if ($key === 'status' && $val): ?><span class="badge badge-<?= $val==='approved'||$val==='paid'||$val==='settled'||$val==='active'||$val==='fully_approved'?'success':($val==='rejected'?'danger':($val==='cancelled'?'secondary':'warning')) ?>"><?= $statusLabels[$val] ?? $val ?></span>
        <?php elseif ($key === 'adjustment_type'): ?><?= $adjustLabels[$val] ?? $val ?>
        <?php elseif ($key === 'leave_type'): ?><?= $leaveLabels[$val] ?? $val ?>
        <?php elseif ($key === 'request_type'): ?><?= $requestLabels[$val] ?? $val ?>
        <?php elseif ($key === 'biometric_match'): ?><?= $val ? 'نعم' : 'لا' ?>
        <?php elseif ($key === 'delay_hours' && is_numeric($val)): ?><?= floor($val) . ':' . str_pad((string)(round(($val - floor($val)) * 60)), 2, '0', STR_PAD_LEFT) ?>
        <?php elseif ($key === 'evaluation_type'): ?><?= $evalLabels[$val] ?? $val ?>
        <?php elseif ($key === 'advance_type'): ?><?= $val === 'short' ? 'قصيرة' : ($val === 'long' ? 'طويلة' : $val) ?>
        <?php elseif ($key === 'file_status'): ?><?= $fileStatusLabels[$val] ?? $val ?>
        <?php elseif ($key === 'check_type'): ?><?= $val === 'in' ? 'دخول' : 'خروج' ?>
        <?php elseif ($key === 'within_geofence'): ?><?= $val ? 'نعم' : 'لا' ?>
        <?php elseif ($key === 'photo' && $val): ?><a href="/<?= Helpers::e($val) ?>" target="_blank">صورة</a>
        <?php elseif (in_array($key, $moneyCols, true)): ?><?= Helpers::money($val) ?>
        <?php elseif ($key === 'check_in' || $key === 'check_out'): ?><?= nl2br(Helpers::e((string)$val), false) ?>
        <?php else: ?><?= Helpers::e((string)$val) ?><?php endif; ?>
      </td>
      <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php else: ?>
<div class="card"><p>لا توجد بيانات للفلاتر المحددة.</p></div>
<?php endif; ?>

<script>
/* أولوية الفترة على من/إلى تاريخ في الواجهة: منع إدخال النموطين معاً */
(function() {
  var period = document.getElementById('reportPeriod');
  var from = document.getElementById('reportDateFrom');
  var to = document.getElementById('reportDateTo');
  if (!period || !from || !to) return;
  period.addEventListener('input', function() {
    if (this.value.trim() !== '') { from.value = ''; to.value = ''; }
  });
  [from, to].forEach(function(el) {
    el.addEventListener('input', function() {
      if (this.value !== '') { period.value = ''; }
    });
  });
})();

/* إظهار/إخفاء فلتر الوظيفة والفلاتر العامة حسب نوع التقرير المختار (تقرير الأداء الوظيفي يتطلب وظيفة فقط) */
(function() {
  var typeSelect = document.getElementById('reportTypeSelect');
  var jobRow = document.getElementById('jobFilterRow');
  var jobSelect = document.getElementById('reportJobSelect');
  var genericRows = document.querySelectorAll('.rpt-generic');
  if (!typeSelect || !jobRow || !jobSelect) return;
  function toggle() {
    var isEval = typeSelect.value === 'evaluations';
    jobRow.style.display = isEval ? '' : 'none';
    jobSelect.required = isEval;
    genericRows.forEach(function(r) { r.style.display = isEval ? 'none' : ''; });
  }
  typeSelect.addEventListener('change', toggle);
  toggle();
})();

/* تعديل/حفظ/حذف درجات KPI مباشرة من جدول تقرير الأداء الوظيفي */
(function() {
  var table = document.getElementById('jobPerfTable');
  if (!table) return;
  var periodInput = document.getElementById('reportPeriod');
  var defaultPeriod = table.dataset.defaultPeriod;

  function recalc(row) {
    var inputs = row.querySelectorAll('.pj-kpi-input');
    var total = 0, weight = 0;
    inputs.forEach(function(inp) {
      var w = parseFloat(inp.dataset.weight) || 0;
      var s = Math.max(0, Math.min(100, parseFloat(inp.value) || 0));
      total += s * w;
      weight += w;
    });
    var score = weight > 0 ? Math.round((total / weight) * 10) / 10 : 0;
    row.querySelector('.pj-final-score').innerHTML = '<strong>' + score + '</strong>%';
  }

  table.addEventListener('input', function(e) {
    if (e.target.classList.contains('pj-kpi-input')) {
      recalc(e.target.closest('tr[data-emp-row]'));
    }
  });

  table.addEventListener('click', function(e) {
    var row = e.target.closest('tr[data-emp-row]');
    if (!row) return;

    if (e.target.classList.contains('pj-edit-btn')) {
      row.querySelectorAll('.pj-kpi-input').forEach(function(inp) { inp.disabled = false; });
      row.querySelector('.pj-save-btn').disabled = false;
      e.target.disabled = true;
      return;
    }

    if (e.target.classList.contains('pj-save-btn')) {
      var btn = e.target;
      var employeeId = row.dataset.employeeId;
      var period = (periodInput && periodInput.value.trim() !== '') ? periodInput.value.trim() : defaultPeriod;
      var params = new URLSearchParams();
      params.append('employee_id', employeeId);
      params.append('period', period);
      row.querySelectorAll('.pj-kpi-input').forEach(function(inp) {
        params.append('kpi_id[]', inp.dataset.kpiId);
        params.append('score[]', inp.value || '0');
        params.append('weight[]', inp.dataset.weight);
      });
      btn.disabled = true;
      var originalText = btn.textContent;
      btn.textContent = 'جارٍ الحفظ...';
      fetch('/hr/reports/evaluations/save', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
        body: params.toString()
      }).then(function(r) { return r.json(); }).then(function(data) {
        btn.textContent = originalText;
        if (data.success) {
          row.dataset.evaluationId = data.id;
          recalc(row);
          row.querySelectorAll('.pj-kpi-input').forEach(function(inp) { inp.disabled = true; });
          row.querySelector('.pj-edit-btn').disabled = false;
          row.querySelector('.pj-delete-btn').disabled = false;
        } else {
          alert(data.message || 'تعذر الحفظ');
          btn.disabled = false;
        }
      }).catch(function() {
        btn.textContent = originalText;
        btn.disabled = false;
        alert('تعذر الاتصال بالخادم');
      });
      return;
    }

    if (e.target.classList.contains('pj-delete-btn')) {
      var evalId = row.dataset.evaluationId;
      if (!evalId || evalId === '0') return;
      if (!confirm('هل أنت متأكد من حذف هذا التقييم؟')) return;
      var delBtn = e.target;
      fetch('/hr/reports/evaluations/delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
        body: 'evaluation_id=' + encodeURIComponent(evalId)
      }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
          row.dataset.evaluationId = '0';
          row.querySelectorAll('.pj-kpi-input').forEach(function(inp) { inp.value = 0; inp.disabled = true; });
          row.querySelector('.pj-final-score').innerHTML = '<strong>0</strong>%';
          row.querySelector('.pj-save-btn').disabled = true;
          delBtn.disabled = true;
        } else {
          alert(data.message || 'تعذر الحذف');
        }
      }).catch(function() { alert('تعذر الاتصال بالخادم'); });
    }
  });
})();
</script>
