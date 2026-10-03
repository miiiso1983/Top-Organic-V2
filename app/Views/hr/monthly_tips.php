<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>التيبس الشهري للموظفين</h3>
  <form method="get" action="/hr/payroll/tips" class="form-inline">
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
  <form method="post" action="/hr/payroll/tips" class="form-inline" style="margin-top:15px">
    <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
    <div class="form-row"><label>الفترة</label><input type="month" name="period" value="<?= Helpers::e($period) ?>" required></div>
    <div class="form-row"><label>المبلغ الكامل</label><input type="number" name="full_amount" step="0.01" min="0" required></div>
    <div class="form-row"><label>أيام الدوام <small class="text-muted">(0 = تلقائي من البصمات)</small></label><input type="number" name="working_days" min="0" max="30" value="30" required></div>
    <div class="form-row"><label>ملاحظات</label><input name="notes" placeholder="اختياري"></div>
    <button class="btn btn-success">حفظ</button>
  </form>

  <table style="margin-top:15px">
    <tr><th>الفترة</th><th>المبلغ الكامل</th><th>أيام الدوام</th><th>المبلغ المتناسب</th><th>ملاحظات</th><th></th></tr>
    <?php foreach ($records as $r): ?>
    <tr>
      <td><?= Helpers::e($r['period']) ?></td>
      <td><?= Helpers::money($r['full_amount']) ?></td>
      <td><?= (int)$r['working_days'] > 0 ? Helpers::e($r['working_days']) : 'تلقائي' ?></td>
      <td><?= Helpers::money($r['prorated_amount']) ?></td>
      <td><?= Helpers::e($r['notes'] ?? '-') ?></td>
      <td>
        <form method="post" action="/hr/payroll/tips/<?= $r['id'] ?>/delete" style="display:inline" onsubmit="return confirm('تأكيد الحذف؟')">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$records): ?><tr><td colspan="6">لا يوجد تيبس مسجل</td></tr><?php endif; ?>
  </table>
  <?php endif; ?>
</div>

<p style="margin-top:10px">
  <a href="/hr/payroll">← العودة إلى كشف الرواتب</a>
</p>
