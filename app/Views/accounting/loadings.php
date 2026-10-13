<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>التحميلات الشهرية للموظفين</h3>
  <form method="get" action="/accounting/loadings" class="form-inline">
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
  <form method="post" action="/accounting/loadings" class="form-inline" style="margin-top:15px">
    <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
    <div class="form-row"><label>الفترة</label><input type="month" name="period" value="<?= Helpers::e($period) ?>" required></div>
    <div class="form-row"><label>الفئة</label>
      <select name="category">
        <option value="تحميلات" selected>تحميلات</option>
        <option value="تيبس">تيبس</option>
        <option value="ضمان اجتماعي">ضمان اجتماعي</option>
        <option value="سلف">سلف</option>
        <option value="سلف طويلة الاجل">سلف طويلة الاجل</option>
        <option value="اخرى">أخرى</option>
      </select>
    </div>
    <div class="form-row"><label>المبلغ</label><input type="number" name="amount" step="0.01" min="0" required></div>
    <div class="form-row"><label>ملاحظات</label><input name="notes" placeholder="اختياري"></div>
    <button class="btn btn-success">حفظ</button>
  </form>

  <table style="margin-top:15px">
    <tr><th>الفترة</th><th>الفئة</th><th>المبلغ</th><th>ملاحظات</th><th></th></tr>
    <?php foreach ($records as $r): ?>
    <tr>
      <td><?= Helpers::e($r['period']) ?></td>
      <td><?= Helpers::e($r['category']) ?></td>
      <td><?= Helpers::money($r['amount']) ?></td>
      <td><?= Helpers::e($r['notes'] ?? '-') ?></td>
      <td>
        <form method="post" action="/accounting/loadings/<?= $r['id'] ?>/delete" style="display:inline" onsubmit="return confirm('تأكيد الحذف؟')">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$records): ?><tr><td colspan="5">لا توجد تحميلات مسجلة</td></tr><?php endif; ?>
  </table>
  <?php endif; ?>
</div>

<p style="margin-top:10px">
  <a href="/hr/payroll">← العودة إلى كشف الرواتب</a>
</p>
