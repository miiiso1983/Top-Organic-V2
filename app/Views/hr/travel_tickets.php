<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>تذاكر سفر الموظفين</h3>
  <form method="get" action="/hr/payroll/travel-tickets" class="form-inline">
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
  <form method="post" action="/hr/payroll/travel-tickets" class="form-inline" style="margin-top:15px">
    <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
    <div class="form-row"><label>الفترة</label><input type="month" name="period" value="<?= Helpers::e($period) ?>" required></div>
    <div class="form-row"><label>تاريخ الذهاب</label><input type="date" name="departure_date"></div>
    <div class="form-row"><label>تاريخ العودة</label><input type="date" name="return_date"></div>
    <div class="form-row"><label>طريقة السفر</label>
      <select name="travel_method">
        <option value="طيران">طيران</option>
        <option value="بري">بري</option>
        <option value="بحري">بحري</option>
      </select>
    </div>
    <div class="form-row"><label>المبلغ</label><input type="number" name="amount" step="0.01" min="0" required></div>
    <div class="form-row"><label>ملاحظات</label><input name="notes" placeholder="اختياري"></div>
    <button class="btn btn-success">حفظ</button>
  </form>

  <table style="margin-top:15px">
    <tr><th>الفترة</th><th>الذهاب</th><th>العودة</th><th>المدة</th><th>الطريقة</th><th>المبلغ</th><th>ملاحظات</th><th></th></tr>
    <?php foreach ($records as $r): ?>
    <tr>
      <td><?= Helpers::e($r['period']) ?></td>
      <td><?= Helpers::e($r['departure_date'] ?? '-') ?></td>
      <td><?= Helpers::e($r['return_date'] ?? '-') ?></td>
      <td><?= Helpers::e($r['duration'] ?? '-') ?></td>
      <td><?= Helpers::e($r['travel_method'] ?? '-') ?></td>
      <td><?= Helpers::money($r['amount']) ?></td>
      <td><?= Helpers::e($r['notes'] ?? '-') ?></td>
      <td>
        <form method="post" action="/hr/payroll/travel-tickets/<?= $r['id'] ?>/delete" style="display:inline" onsubmit="return confirm('تأكيد الحذف؟')">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$records): ?><tr><td colspan="8">لا توجد تذاكر مسجلة</td></tr><?php endif; ?>
  </table>
  <?php endif; ?>
</div>

<p style="margin-top:10px">
  <a href="/hr/payroll">← العودة إلى كشف الرواتب</a>
</p>
