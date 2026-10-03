<?php use App\Core\Helpers; ?>
<?php $statusLabels = $statusLabels ?? ['pending'=>'بانتظار المدير المباشر','manager_approved'=>'بانتظار مدير المدير','fully_approved'=>'معتمد نهائياً','rejected'=>'مرفوض','expired'=>'منتهي الصلاحية']; ?>
<div class="card">
  <h3>طلب عمل إضافي</h3>
  <form method="post" action="/hr/overtime" class="form-inline">
    <div class="form-row"><label>الموظف</label>
      <select name="employee_id" required>
        <?php foreach ($employees as $e): ?>
          <option value="<?= $e['id'] ?>" <?= $e['id'] == $employeeId ? 'selected' : '' ?>><?= Helpers::e($e['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>التاريخ</label><input type="date" name="request_date" required></div>
    <div class="form-row"><label>الساعات</label><input type="number" name="hours" step="0.01" min="0.01" required></div>
    <div class="form-row"><label>السبب</label><input name="reason"></div>
    <button class="btn btn-success">تقديم الطلب</button>
  </form>
</div>

<div class="card">
  <div class="flex-between" style="margin-bottom:15px;flex-wrap:wrap;gap:10px">
    <h3 style="margin:0">سجل طلبات العمل الإضافي</h3>
  </div>

  <form method="get" action="/hr/overtime" class="form-inline" style="margin-bottom:15px">
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
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn btn-secondary">تصفية</button>
    <a href="/hr/overtime" class="btn btn-light">إعادة</a>
  </form>

  <table>
    <tr><th>الموظف</th><th>التاريخ</th><th>الساعات</th><th>المبلغ</th><th>السبب</th><th>مطابقة البصمة</th><th>الحالة</th><th>التاريخ</th><th></th></tr>
    <?php foreach ($overtime as $o): ?>
    <?php $matched = (int)($o['biometric_match'] ?? 0); ?>
    <tr>
      <td><?= Helpers::e($o['full_name']) ?> (<?= Helpers::e($o['emp_code'] ?? '-') ?>)</td>
      <td><?= Helpers::e($o['request_date']) ?></td>
      <td><?= Helpers::e($o['hours']) ?></td>
      <td><?= Helpers::money($o['amount'] ?? 0) ?></td>
      <td><?= Helpers::e($o['reason'] ?? '-') ?></td>
      <td>
        <span class="badge badge-<?= $matched ? 'success' : 'warning' ?>">
          <?= $matched ? 'مطابقة (' . ($o['biometric_hours'] ?? 0) . ' س)' : 'غير مطابقة (' . ($o['biometric_hours'] ?? 0) . ' س)' ?>
        </span>
      </td>
      <td><span class="badge badge-<?= $o['status'] === 'fully_approved' ? 'success' : ($o['status'] === 'rejected' || $o['status'] === 'expired' ? 'danger' : 'warning') ?>"><?= $statusLabels[$o['status']] ?? $o['status'] ?></span></td>
      <td><?= date('m/d', strtotime($o['created_at'])) ?></td>
      <td><a href="/hr/overtime/<?= $o['id'] ?>" class="btn btn-sm btn-light">عرض</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$overtime): ?><tr><td colspan="9">لا توجد طلبات</td></tr><?php endif; ?>
  </table>
</div>
