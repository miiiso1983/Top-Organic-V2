<?php use App\Core\Helpers; ?>
<div class="card" style="max-width:720px">
  <h3><i class="fa-solid fa-user-slash"></i> طلب إيقاف دوام موظف</h3>
  <form method="post" action="/hr/manager-requests/suspension">
    <div class="form-row"><label>الموظف التابع <small>*</small></label>
      <select name="employee_id" required>
        <option value="">— اختر موظف —</option>
        <?php foreach ($subordinates as $e): ?>
        <option value="<?= (int)$e['id'] ?>"><?= Helpers::e($e['full_name']) ?> (<?= Helpers::e($e['emp_code']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>تاريخ الإيقاف <small>*</small></label>
      <input type="date" name="suspension_date" required value="<?= date('Y-m-d') ?>">
    </div>
    <div class="form-row"><label>نوع الإيقاف</label>
      <select name="suspension_type">
        <option value="temporary">مؤقت</option>
        <option value="permanent">نهائي</option>
      </select>
    </div>
    <div class="form-row" style="min-width:100%"><label>سبب الإيقاف <small>*</small></label>
      <textarea name="reason" rows="3" required></textarea>
    </div>
    <div class="form-row" style="min-width:100%"><label>ملاحظات</label>
      <textarea name="notes" rows="2"></textarea>
    </div>
    <div style="margin-top:12px">
      <button class="btn btn-warning">تقديم الطلب</button>
      <a href="/hr/manager-requests" class="btn btn-secondary">إلغاء</a>
    </div>
  </form>
</div>
