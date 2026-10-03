<?php use App\Core\Helpers; ?>
<?php $typeLabels = ['annual'=>'سنوية','sick'=>'مرضية','exit'=>'إذن خروج','unpaid'=>'بدون راتب','emergency'=>'طارئة','hourly'=>'زمنية']; ?>
<div class="card">
  <h3>رصيد الإجازات</h3>
  <form method="get" action="/hr/leave-balances" class="form-inline">
    <div class="form-row"><label>الموظف</label>
      <select name="employee_id" required onchange="this.form.submit()">
        <option value="">اختر موظف</option>
        <?php foreach ($employees as $e): ?>
        <option value="<?= $e['id'] ?>" <?= $selected === (int)$e['id'] ? 'selected' : '' ?>><?= Helpers::e($e['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>السنة</label><input type="number" name="year" value="<?= $year ?>" required></div>
    <button class="btn btn-primary">عرض</button>
  </form>
</div>

<?php if ($selected): ?>
<div class="card">
  <h4>أرصدة الموظف لعام <?= $year ?></h4>
  <?php if (!$balances): ?>
    <p>لا توجد أرصدة مسجّلة.</p>
  <?php else: ?>
  <table>
    <tr><th>النوع</th><th>المستحق</th><th>المستخدم</th><th>الرصيد</th><th>تعديل</th></tr>
    <?php foreach ($balances as $b): ?>
    <tr>
      <td><?= $typeLabels[$b['leave_type']] ?? $b['leave_type'] ?></td>
      <td><?= $b['entitled'] ?></td>
      <td><?= $b['used'] ?></td>
      <td><span class="badge badge-<?= $b['balance'] > 0 ? 'success' : ($b['balance'] < 0 ? 'danger' : 'warning') ?>"><?= $b['balance'] ?></span></td>
      <td>
        <form method="post" action="/hr/leave-balances" class="form-inline" style="margin:0">
          <input type="hidden" name="employee_id" value="<?= $selected ?>">
          <input type="hidden" name="year" value="<?= $year ?>">
          <input type="hidden" name="leave_type" value="<?= $b['leave_type'] ?>">
          <input type="number" name="entitled" step="0.01" value="<?= $b['entitled'] ?>" required>
          <button class="btn btn-sm btn-success">حفظ</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>
