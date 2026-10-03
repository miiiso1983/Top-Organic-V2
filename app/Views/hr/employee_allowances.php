<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>بدلات الموظفين الثابتة</h3>
  <form method="get" action="/hr/payroll/allowances" class="form-inline">
    <div class="form-row"><label>الموظف</label>
      <select name="employee_id">
        <option value="0">اختر موظفاً</option>
        <?php foreach ($employees as $e): ?>
          <option value="<?= $e['id'] ?>" <?= $e['id'] == $employeeId ? 'selected' : '' ?>><?= Helpers::e($e['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn">عرض</button>
  </form>

  <?php if ($employeeId): ?>
  <form method="post" action="/hr/payroll/allowances" class="form-inline" style="margin-top:15px">
    <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
    <div class="form-row"><label>نوع البدل</label><input name="allowance_type" required placeholder="مثال: سكن، مواصلات، طعام"></div>
    <div class="form-row"><label>المبلغ</label><input type="number" name="amount" step="0.01" min="0" required></div>
    <button class="btn btn-success">إضافة / تحديث</button>
  </form>

  <table style="margin-top:15px">
    <tr><th>البدل</th><th>المبلغ</th></tr>
    <?php foreach ($allowances as $a): ?>
    <tr>
      <td><?= Helpers::e($a['allowance_type']) ?></td>
      <td><?= Helpers::money($a['amount']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$allowances): ?><tr><td colspan="2">لا توجد بدلات مسجلة</td></tr><?php endif; ?>
  </table>
  <?php endif; ?>
</div>
