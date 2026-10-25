<?php use App\Core\Helpers; ?>
<?php $statusLabels = ['pending'=>'بانتظار الموافقة','approved'=>'معتمدة','rejected'=>'مرفوضة','paid'=>'تم الصرف','settled'=>'مسددة']; ?>
<div class="card">
  <h3>طلب سلفة شهرية أو استثنائية أو طويلة الأجل</h3>
  <form method="post" action="/accounting/advances" class="form-inline" id="advance-form" onsubmit="return validateAdvanceForm(this);">
    <div class="form-row"><label>الموظف</label>
      <select name="employee_id" required>
        <?php foreach ($employees as $e): ?>
          <option value="<?= $e['id'] ?>" <?= $e['id'] == $employeeId ? 'selected' : '' ?>><?= Helpers::e($e['full_name']) ?> (راتب: <?= Helpers::money($e['base_salary']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>المبلغ</label><input type="number" name="amount" id="advance-amount-input" step="0.01" min="0.01" required oninput="autoFillInstallmentAmount()"></div>
    <div class="form-row"><label>مسار الطلب</label><select name="request_kind"><option value="monthly">شهرية — من يوم 13</option><option value="exceptional">استثنائية — الموارد البشرية فقط</option></select></div>
    <div class="form-row"><label>النوع</label>
      <select name="advance_type" id="advance-type-select" onchange="toggleAdvanceFields()"><option value="short">شهرية / استثنائية</option><option value="long">طويلة الأجل</option></select>
    </div>
    <div class="form-row" id="installments-row"><label>عدد الأقساط</label><input type="number" name="installments" id="advance-installments-input" value="1" min="1" oninput="autoFillInstallmentAmount()"></div>
    <div class="form-row" id="installment-method-row" style="display:none"><label>طريقة احتساب القسط</label>
      <select name="installment_method" id="installment-method-select" onchange="toggleInstallmentMethod()">
        <option value="fixed">مبلغ ثابت مستقطع شهرياً (يُحسب تلقائياً حسب عدد الأقساط)</option>
        <option value="percentage">نسبة % من الراتب الأساسي</option>
      </select>
    </div>
    <div class="form-row" id="installment-amount-row" style="display:none"><label>مبلغ القسط الشهري</label><input type="number" name="installment_amount" id="advance-installment-amount-input" step="0.01" min="0.01" readonly style="background:#f1f5f9">
      <span class="text-muted" style="font-size:12px;display:block">يُحسب تلقائياً = المبلغ ÷ عدد الأقساط المدخل أعلاه; غيّر عدد الأقساط لتعديل القسط</span></div>
    <div class="form-row" id="installment-percentage-row" style="display:none"><label>نسبة القسط %</label><input type="number" name="installment_percentage" step="0.01" min="0.01" max="100"></div>
    <div class="form-row"><label>السبب</label><input name="reason"></div>
    <button class="btn btn-success">تقديم الطلب</button>
  </form>
  <p>الشهرية والاستثنائية: الأقل من 25% من الراتب وصافي الاستحقاق بعد كامل المديونية والاستقطاعات. طلب واحد قائم شهرياً؛ المرفوض والملغى لا يمنعان البديل. الاستثنائية وطويلة الأجل تقدمها الموارد البشرية مع السبب.</p>
  <p class="text-muted" style="margin-top:10px">السلف طويلة الأجل تخضع للموافقات وتولد جدول سداد تلقائياً؛ عند اختيار "مبلغ ثابت شهرياً" يكفي إدخال عدد الأقساط فقط ويُحسب مبلغ القسط الشهري تلقائياً بقسمة المبلغ على عدد الأقساط دون الحاجة لكتابته، أو اختيار "نسبة % من الراتب الأساسي" لتحديد القسط كنسبة من الراتب (يُحسب عدد الأقساط حينئذ تلقائياً).</p>
  <script>
  function toggleAdvanceFields() {
      var isLong = document.getElementById('advance-type-select').value === 'long';
      document.getElementById('installment-method-row').style.display = isLong ? '' : 'none';
      if (isLong) {
          var installmentsField = document.getElementById('advance-installments-input');
          if ((parseInt(installmentsField.value) || 1) < 2) installmentsField.value = 2; // السلفة طويلة الأجل تتطلب قسطين على الأقل منطقياً
          toggleInstallmentMethod();
      } else {
          document.getElementById('advance-installments-input').value = 1;
          document.getElementById('installment-amount-row').style.display = 'none';
          document.getElementById('installment-percentage-row').style.display = 'none';
      }
  }
  function toggleInstallmentMethod() {
      var method = document.getElementById('installment-method-select').value;
      document.getElementById('installment-amount-row').style.display = method === 'fixed' ? '' : 'none';
      document.getElementById('installment-percentage-row').style.display = method === 'percentage' ? '' : 'none';
      autoFillInstallmentAmount();
  }
  // يحسب مبلغ القسط الشهري تلقائياً (المبلغ ÷ عدد الأقساط) فور تغيير أي منهما، دون حاجة لكتابته يدوياً
  function autoFillInstallmentAmount() {
      var isLong = document.getElementById('advance-type-select').value === 'long';
      var method = document.getElementById('installment-method-select').value;
      if (!isLong || method !== 'fixed') return;
      var amount = parseFloat(document.getElementById('advance-amount-input').value) || 0;
      var installments = parseInt(document.getElementById('advance-installments-input').value) || 0;
      var field = document.getElementById('advance-installment-amount-input');
      field.value = (amount > 0 && installments > 0) ? (Math.round((amount / installments) * 100) / 100) : '';
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
    <h3 style="margin:0">سجل السلف</h3><a class="btn btn-primary" href="/accounting/advances/disbursement">اعتماد وتسليم السلف</a>
    <div class="tabs">
      <a href="?scope=current" class="tab <?= $scope === 'current' ? 'active' : '' ?>">الحالية</a>
      <a href="?scope=previous" class="tab <?= $scope === 'previous' ? 'active' : '' ?>">السابقة</a>
      <a href="?scope=all" class="tab <?= $scope === 'all' ? 'active' : '' ?>">الكل</a>
    </div>
  </div>

  <form method="get" action="/accounting/advances" class="form-inline" style="margin-bottom:15px">
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
    <a href="/accounting/advances" class="btn btn-light">إعادة</a>
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
        <a href="/accounting/advances/<?= $a['id'] ?>" class="btn btn-sm btn-light">عرض</a>
        <?php if ($a['workflow_request_id']): ?>
        <a href="/workflows/requests/<?= $a['workflow_request_id'] ?>" class="btn btn-sm btn-secondary">Workflow</a>
        <?php endif; ?>
        <?php if ($a['status'] === 'approved' && !empty($a['accountant_approved_at']) && (float)($a['paid_amount'] ?? 0) <= 0 && (\App\Core\Auth::can('payroll.manage') || \App\Core\Auth::can('accounting.manage'))): ?>
        <form method="post" action="/accounting/advances/<?= $a['id'] ?>/pay" style="display:inline" onsubmit="return confirm('تأكيد تسليم السلفة؟')">
          <button class="btn btn-sm btn-primary">تسليم السلفة</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$advances): ?><tr><td colspan="11">لا توجد سلف</td></tr><?php endif; ?>
  </table>
</div>
