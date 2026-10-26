<?php use App\Core\Helpers; ?>
<?php $typeLabels = ['bonus'=>'مكافأة','deduction'=>'خصم','raise'=>'زيادة','penalty'=>'جزاء','tips_addition'=>'إضافي تيبس','salary_reduction'=>'تخفيض راتب','work_days_settlement'=>'تسوية أيام دوام']; ?>
<?php $statusLabels = ['pending'=>'معلق','approved'=>'معتمد','rejected'=>'مرفوض','cancelled'=>'ملغاة']; ?>
<?php $warningTypeLabels = ['verbal'=>'شفهي','written'=>'خطي','final'=>'نهائي']; ?>
<?php $warningStatusLabels = ['active'=>'نشط','resolved'=>'محلول','expired'=>'منتهي']; ?>

<div class="card">
  <h3>إدارة التسويات المالية</h3>
  <form method="post" action="/hr/adjustments">
    <div class="form-row"><label>الموظف</label>
      <select name="employee_id" required><?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>" <?= $e['id'] == $employeeId ? 'selected' : '' ?>><?= Helpers::e($e['full_name']) ?> (<?= Helpers::money($e['base_salary']) ?>)</option><?php endforeach; ?></select>
    </div>
    <div class="form-row"><label>النوع</label>
      <select name="adjustment_type" id="adjType" required onchange="toggleAdjustmentAmountMode()">
        <option value="bonus">مكافأة</option>
        <option value="deduction">خصم (بالأيام)</option>
        <option value="raise">زيادة</option>
        <option value="tips_addition">إضافي تيبس</option>
        <option value="salary_reduction">تخفيض راتب</option>
        <option value="work_days_settlement">تسوية عدد أيام دوام</option>
      </select>
    </div>
    <div class="form-row" id="adjAmountRow"><label>المبلغ</label><input type="number" step="0.01" name="amount" id="adjAmount" required></div>
    <div class="form-row" id="adjDaysRow" style="display:none"><label id="adjDaysLabel">عدد أيام الاستقطاع</label><input type="number" step="0.5" min="0.5" name="days" id="adjDays"></div>
    <div class="form-row"><label>الفترة (YYYY-MM)</label><input type="text" name="period" value="<?= date('Y-m') ?>" pattern="\d{4}-\d{2}"></div>
    <div class="form-row"><label>تاريخ التطبيق</label><input type="date" name="effective_date"></div>
    <div class="form-row"><label>أدنى مستوى موافق</label><input type="number" name="admin_level_required" value="1" min="1"></div>
    <div class="form-row"><label>السبب</label><input type="text" name="reason" maxlength="255"></div>
    <button class="btn btn-success">تقديم</button>
  </form>
</div>
<script>
function toggleAdjustmentAmountMode(){
  var type = document.getElementById('adjType').value;
  var isDays = (type === 'deduction' || type === 'work_days_settlement');
  document.getElementById('adjDaysRow').style.display = isDays ? '' : 'none';
  document.getElementById('adjAmountRow').style.display = isDays ? 'none' : '';
  document.getElementById('adjDays').required = isDays;
  document.getElementById('adjAmount').required = !isDays;
  // تسوية أيام الدوام: زيادة = الأيام × تكلفة يوم العمل (الراتب الأساسي/30)
  document.getElementById('adjDaysLabel').textContent = (type === 'work_days_settlement') ? 'عدد أيام الدوام' : 'عدد أيام الاستقطاع';
}
</script>

<div class="card">
  <h3>تسجيل إنذار</h3>
  <?php $_SESSION['hr_team_csrf'] ??= bin2hex(random_bytes(32)); ?>
<form method="post" action="/hr/adjustments/warnings">
<input type="hidden" name="hr_team_csrf" value="<?= htmlspecialchars($_SESSION['hr_team_csrf'],ENT_QUOTES) ?>"><input type="hidden" name="request_key" value="<?= bin2hex(random_bytes(20)) ?>">
    <div class="form-row"><label>الموظف</label>
      <select name="employee_id" required><?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>" <?= $e['id'] == $employeeId ? 'selected' : '' ?>><?= Helpers::e($e['full_name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="form-row"><label>نوع الإنذار</label>
      <select name="warning_type" required>
        <option value="verbal">شفهي</option>
        <option value="written">خطي</option>
        <option value="final">نهائي</option>
      </select>
    </div>
    <div class="form-row"><label>تاريخ الإنذار</label><input type="date" name="warning_date" value="<?= date('Y-m-d') ?>"></div>
    <div class="form-row"><label>السبب</label><input type="text" name="reason" required maxlength="255"></div>
    <button class="btn btn-warning">تسجيل إنذار</button>
  </form>
</div>

<div class="card">
  <div class="form-inline" style="margin-bottom:10px">
    <form method="get" action="/hr/adjustments">
      <select name="status">
        <option value="all">الكل</option>
        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>معلق</option>
        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>معتمد</option>
        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>مرفوض</option>
        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>ملغاة</option>
      </select>
      <select name="employee_id">
        <option value="0">كل الموظفين</option>
        <?php foreach ($employees as $e): ?>
          <option value="<?= $e['id'] ?>" <?= $e['id'] == $employeeId ? 'selected' : '' ?>><?= Helpers::e($e['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn">عرض</button>
    </form>
  </div>
  <table>
    <tr><th>الموظف</th><th>القسم</th><th>النوع</th><th>المبلغ</th><th>الأيام</th><th>الفترة</th><th>الحالة</th><th>المرحلة</th><th>مقدم/موافق</th><th></th></tr>
    <?php foreach ($adjustments as $a): ?>
    <tr>
      <td><?= Helpers::e($a['full_name']) ?></td>
      <td><?= Helpers::e($a['department'] ?? '-') ?></td>
      <td><?= $typeLabels[$a['adjustment_type']] ?? $a['adjustment_type'] ?></td>
      <td><?= Helpers::money($a['amount']) ?></td>
      <td><?= isset($a['days']) && $a['days'] !== null ? Helpers::e($a['days']) : '-' ?></td>
      <td><?= Helpers::e($a['period'] ?? '-') ?></td>
      <td><span class="badge badge-<?= $a['status'] === 'approved' ? 'success' : ($a['status'] === 'rejected' ? 'danger' : ($a['status'] === 'cancelled' ? 'secondary' : 'warning')) ?>"><?= $statusLabels[$a['status']] ?></span></td>
      <td><?= $a['workflow_request_id'] ? ($a['status'] === 'pending' ? 'المرحلة ' . ((int)($a['workflow_current_step'] ?? 1)) : 'مكتمل') : 'غير مرتبط' ?></td>
      <td>من: <?= Helpers::e($a['requested_name'] ?? '-') ?><br>بواسطة: <?= Helpers::e($a['approved_name'] ?? '-') ?></td>
      <td>
        <a href="/hr/adjustments/<?= $a['id'] ?>" class="btn btn-sm btn-light">عرض</a>
        <?php if ($a['workflow_request_id']): ?>
        <a href="/workflows/requests/<?= $a['workflow_request_id'] ?>" class="btn btn-sm btn-secondary">Workflow</a>
        <?php endif; ?>
        <?php if ($a['status'] === 'approved' && \App\Core\Auth::can('hr.adjustments.manage')): ?>
        <button class="btn btn-sm btn-danger" onclick="cancelAdjustment(<?= $a['id'] ?>)">إلغاء</button>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$adjustments): ?><tr><td colspan="11">لا توجد تسويات</td></tr><?php endif; ?>
  </table>
</div>

<?php if ($employeeId && $warnings): ?>
<div class="card">
  <h3>سجل إنذارات الموظف</h3>
  <table>
    <tr><th>التاريخ</th><th>النوع</th><th>السبب</th><th>الحالة</th><th>بواسطة</th><th></th></tr>
    <?php foreach ($warnings as $w): ?>
    <tr>
      <td><?= $w['warning_date'] ?></td>
      <td><?= $warningTypeLabels[$w['warning_type']] ?? $w['warning_type'] ?></td>
      <td><?= Helpers::e($w['reason']) ?></td>
      <td><span class="badge badge-<?= $w['status'] === 'active' ? 'danger' : ($w['status'] === 'resolved' ? 'success' : 'secondary') ?>"><?= $warningStatusLabels[$w['status']] ?></span></td>
      <td><?= Helpers::e($w['created_by_name'] ?? '-') ?></td>
      <td>
        <?php if ($w['status'] === 'active' && \App\Core\Auth::can('hr.warnings.manage')): ?>
        <form method="post" action="/hr/adjustments/warnings/<?= $w['id'] ?>/resolve" style="display:inline">
          <button class="btn btn-sm btn-success">إغلاق</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<script>
function adjust(id, decision){
  if(!confirm('هل أنت متأكد من ' + (decision === 'approved' ? 'الموافقة' : 'الرفض') + '؟')) return;
  fetch('/hr/adjustments/'+id+'/decide', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'decision='+decision
  }).then(r=>r.json()).then(d=>{
    if(d.ok) location.reload(); else alert(d.error || 'خطأ');
  }).catch(e=>alert(e.message));
}
function cancelAdjustment(id){
  var reason = prompt('الرجاء إدخال سبب إلغاء هذه التسوية:');
  if (reason === null) return;
  if (!reason.trim()) { alert('سبب الإلغاء مطلوب'); return; }
  fetch('/hr/adjustments/'+id+'/cancel', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'reason='+encodeURIComponent(reason)
  }).then(r=>r.json()).then(d=>{
    if(d.ok) location.reload(); else alert(d.error || 'خطأ');
  }).catch(e=>alert(e.message));
}
</script>
