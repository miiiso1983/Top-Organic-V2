<?php use App\Core\Helpers; ?>
<?php use App\Core\Auth; ?>
<?php $statusLabels = ['active'=>'نشط','achieved'=>'محقق','missed'=>'غير محقق','cancelled'=>'ملغي']; ?>

<div class="card">
  <h3>أهداف الموظف</h3>
  <form method="get" action="/hr/goals" class="form-inline">
    <div class="form-row"><label>الموظف</label>
      <select name="employee_id" required onchange="this.form.submit()">
        <option value="">اختر موظف</option>
        <?php foreach ($employees as $e): ?>
        <option value="<?= $e['id'] ?>" <?= $e['id'] == $employee_id ? 'selected' : '' ?>><?= Helpers::e($e['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>

<?php if ($employee_id && Auth::can('hr.goals.manage')): ?>
<div class="card">
  <h4>إضافة هدف جديد</h4>
  <form method="post" action="/hr/goals">
    <input type="hidden" name="employee_id" value="<?= $employee_id ?>">
    <div class="form-row"><label>العنوان</label><input type="text" name="title" required></div>
    <div class="form-row"><label>الوصف</label><textarea name="description"></textarea></div>
    <div class="form-row"><label>الهدف</label><input type="number" step="0.01" name="target_value" placeholder="القيمة المستهدفة"></div>
    <div class="form-row"><label>الوحدة</label><input type="text" name="unit" placeholder="مثال: صفقة، مبيعات، ساعة"></div>
    <div class="form-row"><label>تاريخ الاستحقاق</label><input type="date" name="due_date"></div>
    <button class="btn btn-success">حفظ الهدف</button>
  </form>
</div>
<?php endif; ?>

<?php if ($goals): ?>
<div class="card">
  <h4>قائمة الأهداف</h4>
  <table>
    <tr><th>الهدف</th><th>الوصف</th><th>الهدف</th><th>الفعلي</th><th>الحالة</th><th>التاريخ</th><th></th></tr>
    <?php foreach ($goals as $g): ?>
    <tr>
      <td><?= Helpers::e($g['title']) ?></td>
      <td><?= Helpers::e($g['description'] ?? '-') ?></td>
      <td><?= Helpers::e($g['target_value'] ?? '-') ?> <?= Helpers::e($g['unit'] ?? '') ?></td>
      <td><?= Helpers::e($g['actual_value'] ?? '-') ?> <?= Helpers::e($g['unit'] ?? '') ?></td>
      <td><span class="badge badge-<?= $g['status'] === 'achieved' ? 'success' : ($g['status'] === 'missed' ? 'danger' : 'warning') ?>"><?= $statusLabels[$g['status']] ?></span></td>
      <td><?= Helpers::e($g['due_date'] ?? '-') ?></td>
      <td>
        <?php if (Auth::can('hr.goals.manage')): ?>
        <form method="post" action="/hr/goals/<?= $g['id'] ?>" style="display:inline">
          <input type="hidden" name="status" value="achieved">
          <button class="btn btn-sm btn-success">محقق</button>
        </form>
        <form method="post" action="/hr/goals/<?= $g['id'] ?>" style="display:inline">
          <input type="hidden" name="status" value="missed">
          <button class="btn btn-sm btn-danger">غير محقق</button>
        </form>
        <form method="post" action="/hr/goals/<?= $g['id'] ?>/delete" style="display:inline" onsubmit="return confirm('حذف الهدف؟');">
          <button class="btn btn-sm btn-light">حذف</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php elseif ($employee_id): ?>
<div class="card"><p>لا توجد أهداف مسجلة لهذا الموظف.</p></div>
<?php endif; ?>
