<?php use App\Core\Helpers; ?>
<?php use App\Core\Auth; ?>
<?php $typeLabels = ['monthly'=>'شهري','quarterly'=>'ربع سنوي','half_yearly'=>'نصف سنوي','annual'=>'سنوي']; ?>
<?php $statusLabels = ['draft'=>'مسودة','finalized'=>'معتمد']; ?>
<?php $recLabels = ['none'=>'لا شيء','bonus'=>'مكافأة','raise'=>'زيادة','promotion'=>'ترقية','warning'=>'إنذار']; ?>
<div class="card">
  <h3>إضافة تقييم أداء</h3>
  <form method="post" action="/hr/evaluations">
    <div class="form-row"><label>الموظف</label>
      <select name="employee_id" id="eval_employee_select" required>
        <?php foreach ($employees as $e): ?>
        <option value="<?= $e['id'] ?>" data-job-id="<?= (int)($e['job_id'] ?? 0) ?>"><?= Helpers::e($e['full_name']) ?><?= $e['job_title'] ? ' - ' . Helpers::e($e['job_title']) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الفترة</label><input type="text" name="period" value="<?= date('Y-m') ?>" pattern="\d{4}-\d{2}"></div>
    <div class="form-row"><label>نوع التقييم</label>
      <select name="evaluation_type">
        <option value="monthly">شهري</option>
        <option value="quarterly">ربع سنوي</option>
        <option value="half_yearly">نصف سنوي</option>
        <option value="annual">سنوي</option>
      </select>
    </div>
    <h4>تقييمات المصادر</h4>
    <div class="form-row" style="align-items:center">
      <label style="min-width:150px">المدير المباشر (0-100)</label>
      <input type="number" name="manager_score" min="0" max="100" style="width:80px">
      <input type="text" name="manager_notes" placeholder="ملاحظات المدير" style="width:250px;margin-right:10px">
    </div>
    <div class="form-row" style="align-items:center">
      <label style="min-width:150px">الإدارة (0-100)</label>
      <input type="number" name="admin_score" min="0" max="100" style="width:80px">
      <input type="text" name="admin_notes" placeholder="ملاحظات الإدارة" style="width:250px;margin-right:10px">
    </div>
    <div class="form-row" style="align-items:center">
      <label style="min-width:150px">التقييم الذاتي (0-100)</label>
      <input type="number" name="self_score" min="0" max="100" style="width:80px">
    </div>
    <div class="form-row"><label>ملاحظات عامة</label><textarea name="notes"></textarea></div>
    <h4>مؤشرات الأداء (KPIs)</h4>
    <p style="font-size:13px;color:#6b7280">يتم عرض المؤشرات الخاصة بالوظيفة المسجلة للموظف تلقائيًا، إضافة إلى المؤشرات العامة.</p>
    <?php foreach ($kpis as $i => $k): ?>
    <div class="form-row kpi-row" data-job-ids="<?= Helpers::e($k['job_ids'] ?? '') ?>" style="align-items:center">
      <label style="min-width:160px"><?= Helpers::e($k['name']) ?> (<?= $k['weight'] ?>%)<?php if (!empty($k['job_titles'])): ?> <small style="color:#6b7280">[<?= Helpers::e($k['job_titles']) ?>]</small><?php endif; ?></label>
      <input type="hidden" class="kpi-input" name="kpi_id[<?= $i ?>]" value="<?= $k['id'] ?>">
      <input type="hidden" class="kpi-input" name="weight[<?= $i ?>]" value="<?= $k['weight'] ?>">
      <input type="number" class="kpi-input" name="score[<?= $i ?>]" min="0" max="100" placeholder="الدرجة" style="width:80px">
      <input type="text" class="kpi-input" name="kpi_notes[<?= $i ?>]" placeholder="ملاحظات" style="width:200px;margin-right:10px">
    </div>
    <?php endforeach; ?>
    <button class="btn btn-success">حفظ التقييم</button>
  </form>
</div>

<script>
(function(){
  var empSelect = document.getElementById('eval_employee_select');
  if (!empSelect) return;
  function filterKpis() {
    var opt = empSelect.options[empSelect.selectedIndex];
    var jobId = opt ? (opt.dataset.jobId || '0') : '0';
    document.querySelectorAll('.kpi-row').forEach(function(row) {
      var rowJobIds = (row.dataset.jobIds || '').split(',').filter(Boolean);
      var visible = (rowJobIds.length === 0) || (rowJobIds.indexOf(jobId) !== -1);
      row.style.display = visible ? '' : 'none';
      row.querySelectorAll('.kpi-input').forEach(function(input) {
        input.disabled = !visible;
      });
    });
  }
  empSelect.addEventListener('change', filterKpis);
  filterKpis();
})();
</script>

<div class="card">
  <h4>التقييمات</h4>
  <div class="form-row" style="max-width:420px;margin-bottom:10px">
    <label><i class="fa-solid fa-magnifying-glass"></i> بحث سريع</label>
    <input type="text" id="eval-search" placeholder="ابحث باسم الموظف، القسم، أو فترة التقييم..." oninput="filterEvaluations()" autocomplete="off">
  </div>
  <table id="eval-table">
    <tr><th>الموظف</th><th>الوظيفة</th><th>القسم</th><th>الفترة</th><th>النوع</th><th>KPI</th><th>المدير</th><th>الإدارة</th><th>النتيجة</th><th>الحالة</th><th>المقيّم</th><th></th></tr>
    <?php foreach ($evaluations as $e): ?>
    <tr class="eval-row"
        data-name="<?= Helpers::e(mb_strtolower($e['full_name'] ?? '')) ?>"
        data-department="<?= Helpers::e(mb_strtolower($e['department'] ?? '')) ?>"
        data-period="<?= Helpers::e(mb_strtolower($e['period'] ?? '')) ?>">
      <td><?= Helpers::e($e['full_name']) ?></td>
      <td><?= Helpers::e($e['job_title'] ?? '-') ?></td>
      <td><?= Helpers::e($e['department'] ?? '-') ?></td>
      <td><?= Helpers::e($e['period'] ?? '-') ?></td>
      <td><?= $typeLabels[$e['evaluation_type']] ?? $e['evaluation_type'] ?></td>
      <td><?= $e['kpi_score'] ?></td>
      <td><?= $e['manager_score'] ?></td>
      <td><?= $e['admin_score'] ?></td>
      <td><strong><?= $e['total_score'] ?>/100</strong></td>
      <td><span class="badge badge-<?= $e['status'] === 'finalized' ? 'success' : 'warning' ?>"><?= $statusLabels[$e['status']] ?></span></td>
      <td><?= Helpers::e($e['evaluator_name'] ?? '-') ?></td>
      <td>
        <a href="/hr/evaluations/<?= $e['id'] ?>" class="btn btn-sm btn-light">عرض</a>
        <?php if ($e['status'] === 'draft' && Auth::can('hr.evaluation.manage')): ?>
        <form method="post" action="/hr/evaluations/<?= $e['id'] ?>/finalize" style="display:inline;align-items:center" class="form-inline">
          <input type="number" name="bonus_amount" step="0.01" min="0" placeholder="مكافأة" style="width:80px;margin-left:5px">
          <input type="number" name="raise_amount" step="0.01" min="0" placeholder="زيادة" style="width:80px;margin-left:5px">
          <select name="recommendation" style="width:100px;margin-left:5px">
            <?php foreach ($recLabels as $k => $label): ?>
            <option value="<?= $k ?>"><?= $label ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-success">اعتماد</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$evaluations): ?><tr><td colspan="12">لا توجد تقييمات</td></tr><?php endif; ?>
  </table>
  <div id="eval-search-no-results" style="display:none;padding:10px;color:#6b7280">لا توجد نتائج مطابقة لبحثك</div>
  <script>
  function filterEvaluations() {
    var q = (document.getElementById('eval-search').value || '').trim().toLowerCase();
    var rows = document.querySelectorAll('#eval-table tr.eval-row');
    var visibleCount = 0;
    rows.forEach(function (row) {
      var match = !q
        || (row.getAttribute('data-name') || '').indexOf(q) !== -1
        || (row.getAttribute('data-department') || '').indexOf(q) !== -1
        || (row.getAttribute('data-period') || '').indexOf(q) !== -1;
      row.style.display = match ? '' : 'none';
      if (match) visibleCount++;
    });
    var noResults = document.getElementById('eval-search-no-results');
    if (noResults) noResults.style.display = (q && visibleCount === 0) ? '' : 'none';
  }
  </script>
</div>
