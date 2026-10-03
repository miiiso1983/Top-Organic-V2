<?php use App\Core\Helpers; ?>
<?php $statusLabels = ['pending'=>'بانتظار الموافقة','approved'=>'معتمدة','rejected'=>'مرفوضة','paid'=>'تم الصرف','settled'=>'مسددة']; ?>
<div class="card">
  <h3>طلب سلفة (قصيرة/طويلة الأجل — يخضع لمسار الموافقات)</h3>
  <form method="post" action="/hr/advances" class="form-inline" id="advance-form" onsubmit="return validateAdvanceForm(this);">
    <div class="form-row"><label>الموظف</label>
      <select name="employee_id" required>
        <?php foreach ($employees as $e): ?>
          <option value="<?= $e['id'] ?>" <?= $e['id'] == $employeeId ? 'selected' : '' ?>><?= Helpers::e($e['full_name']) ?> (راتب: <?= Helpers::money($e['base_salary']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>المبلغ</label><input type="number" name="amount" step="0.01" min="0.01" required></div>
    <div class="form-row"><label>النوع</label>
      <select name="advance_type" id="advance-type-select" onchange="toggleAdvanceFields()"><option value="short">قصيرة الأجل</option><option value="long">طويلة الأجل</option></select>
    </div>
    <div class="form-row" id="installments-row"><label>عدد الأقساط</label><input type="number" name="installments" value="1" min="1"></div>
    <div class="form-row" id="installment-method-row" style="display:none"><label>طريقة احتساب القسط</label>
      <select name="installment_method" id="installment-method-select" onchange="toggleInstallmentMethod()">
        <option value="fixed">مبلغ ثابت مستقطع شهرياً</option>
        <option value="percentage">نسبة % من الراتب الأساسي</option>
      </select>
    </div>
    <div class="form-row" id="installment-amount-row" style="display:none"><label>مبلغ القسط الشهري</label><input type="number" name="installment_amount" step="0.01" min="0.01"></div>
    <div class="form-row" id="installment-percentage-row" style="display:none"><label>نسبة القسط %</label><input type="number" name="installment_percentage" step="0.01" min="0.01" max="100"></div>
    <div class="form-row"><label>السبب</label><input name="reason"></div>
    <button class="btn btn-success">تقديم الطلب</button>
  </form>
  <p class="text-muted" style="margin-top:10px">السلف طويلة الأجل تخضع للموافقات وتولد جدول سداد تلقائياً؛ يمكن تحديد القسط الشهري كمبلغ ثابت أو كنسبة من الراتب الأساسي (يُحتسب عدد الأقساط تلقائياً)، أو ترك ذلك فارغاً وتحديد عدد الأقساط يدوياً لتقسيم المبلغ بالتساوي.</p>
  <script>
  function toggleAdvanceFields() {
      var isLong = document.getElementById('advance-type-select').value === 'long';
      document.getElementById('installment-method-row').style.display = isLong ? '' : 'none';
      if (isLong) { toggleInstallmentMethod(); } else {
          document.getElementById('installment-amount-row').style.display = 'none';
          document.getElementById('installment-percentage-row').style.display = 'none';
      }
  }
  function toggleInstallmentMethod() {
      var method = document.getElementById('installment-method-select').value;
      document.getElementById('installment-amount-row').style.display = method === 'fixed' ? '' : 'none';
      document.getElementById('installment-percentage-row').style.display = method === 'percentage' ? '' : 'none';
  }
  function validateAdvanceForm(form) {
      if (form.advance_type.value === 'long') {
          var method = form.installment_method.value;
          var hasAmount = method === 'fixed' && parseFloat(form.installment_amount.value) > 0;
          var hasPercentage = method === 'percentage' && parseFloat(form.installment_percentage.value) > 0;
          if (!hasAmount && !hasPercentage && parseInt(form.installments.value) < 2) {
              alert('السلف طويلة الأجل تتطلب تحديد مبلغ/نسبة القسط الشهري أو عدد أقساط لا يقل عن 2');
              return false;
          }
      }
      return true;
  }
  </script>
</div>

<div class="card">
  <div class="flex-between" style="margin-bottom:15px;flex-wrap:wrap;gap:10px">
    <h3 style="margin:0">سجل السلف</h3>
    <div class="tabs">
      <a href="?scope=current" class="tab <?= $scope === 'current' ? 'active' : '' ?>">الحالية</a>
      <a href="?scope=previous" class="tab <?= $scope === 'previous' ? 'active' : '' ?>">السابقة</a>
      <a href="?scope=all" class="tab <?= $scope === 'all' ? 'active' : '' ?>">الكل</a>
    </div>
  </div>

  <form method="get" action="/hr/advances" class="form-inline" style="margin-bottom:15px">
    <input type="hidden" name="scope" value="<?= Helpers::e($scope) ?>">
    <div class="form-row"><label>الموظف</label>
      <select name="employee_id">
        <option value="0">الكل</option>
        <?php foreach ($employees as $e): ?>
          <option value="<?= $e['id'] ?>" <?= $e['id'] == $employeeId ? 'selected' : '' ?>><?= Helpers::e($e['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الحالة</label>
      <select name="status">
        <option value="">الكل</option>
        <?php foreach ($statusLabels as $k => $v): ?>
          <option value="<?= $k ?>" <?= $k === $status ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-secondary">تصفية</button>
    <a href="/hr/advances" class="btn btn-light">إعادة</a>
  </form>

  <table>
    <tr><th>الموظف</th><th>المبلغ</th><th>النوع</th><th>الأقساط</th><th>المتبقي</th><th>السبب</th><th>المصدر</th><th>الحالة</th><th>المرحلة</th><th>التاريخ</th><th></th></tr>
    <?php foreach ($advances as $a): ?>
    <tr>
      <td><?= Helpers::e($a['full_name']) ?></td>
      <td><?= Helpers::money($a['amount']) ?></td>
      <td><?= $a['advance_type'] === 'short' ? 'قصيرة' : 'طويلة' ?></td>
      <td><?= $a['installments'] ?></td>
      <td><?= Helpers::money($a['remaining_amount'] ?? 0) ?></td>
      <td><?= Helpers::e($a['reason'] ?? '-') ?></td>
      <td><?= $a['request_source'] === 'mobile' ? 'موبايل' : 'ويب' ?></td>
      <td><span class="badge badge-<?= in_array($a['status'],['approved','paid','settled']) ? 'success' : ($a['status'] === 'rejected' ? 'danger' : 'warning') ?>">
        <?= $statusLabels[$a['status']] ?></span></td>
      <td><?= $a['workflow_request_id'] ? ($a['status'] === 'pending' ? 'المرحلة ' . ((int)($a['workflow_current_step'] ?? 1)) : 'مكتمل') : 'غير مرتبط' ?></td>
      <td><?= date('m/d', strtotime($a['created_at'])) ?></td>
      <td>
        <a href="/hr/advances/<?= $a['id'] ?>" class="btn btn-sm btn-light">عرض</a>
        <?php if ($a['workflow_request_id']): ?>
        <a href="/workflows/requests/<?= $a['workflow_request_id'] ?>" class="btn btn-sm btn-secondary">Workflow</a>
        <?php endif; ?>
        <?php if ($a['status'] === 'approved' && (float)($a['paid_amount'] ?? 0) <= 0 && \App\Core\Auth::can('payroll.manage')): ?>
        <form method="post" action="/hr/advances/<?= $a['id'] ?>/pay" style="display:inline" onsubmit="return confirm('تأكيد صرف السلفة؟')">
          <button class="btn btn-sm btn-primary">صرف</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$advances): ?><tr><td colspan="11">لا توجد سلف</td></tr><?php endif; ?>
  </table>
</div>
