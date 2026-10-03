<?php use App\Core\Helpers; ?>
<?php
$eventLabels = [
    'missing_clockin' => 'تأخير البصمة',
    'leave_request' => 'طلب إجازة',
    'advance_request' => 'طلب سلفة',
    'adjustment_request' => 'طلب تسوية',
    'task_assigned' => 'تعيين مهمة',
];
?>
<div class="card">
  <div style="display:flex;gap:10px;margin-bottom:10px">
    <a href="/hr/announcements" class="btn btn-light"><i class="fa-solid fa-bullhorn"></i> التعميمات</a>
    <a href="/hr/notifications-history" class="btn btn-light"><i class="fa-solid fa-envelope"></i> سجل التبليغات</a>
  </div>
  <h3>إضافة قاعدة تبليغ إداري</h3>
  <form method="post" action="/hr/notification-rules" class="form-inline" onsubmit="return validateRuleForm(this)">
    <div class="form-row">
      <label>الحدث</label>
      <select name="event_type" required>
        <?php foreach ($eventLabels as $k => $v): ?>
          <option value="<?= $k ?>"><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <label>نوع المستلم</label>
      <select name="recipient_type" id="recipient_type" onchange="toggleRecipient(this.value)">
        <option value="level">مستوى إداري</option>
        <option value="role">دور (Role)</option>
        <option value="user">شخص محدد</option>
      </select>
    </div>
    <div class="form-row" id="row_level">
      <label>أدنى مستوى إداري</label>
      <input type="number" name="min_admin_level" value="1" min="0">
    </div>
    <div class="form-row" id="row_role" style="display:none">
      <label>الدور</label>
      <select name="role_id"><option value="">—</option><?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>"><?= Helpers::e($r['name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="form-row" id="row_user" style="display:none">
      <label>الشخص</label>
      <select name="user_id"><option value="">—</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= Helpers::e($u['full_name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="form-row">
      <label>الفرع</label>
      <select name="branch_specific">
        <option value="1">نعم</option>
        <option value="0">لا</option>
      </select>
    </div>
    <div class="form-row">
      <label>القسم</label>
      <select name="department_specific">
        <option value="1">نعم</option>
        <option value="0">لا</option>
      </select>
    </div>
    <button class="btn btn-success">إضافة</button>
  </form>
</div>

<div class="card">
  <h4>القواعد المسجّلة</h4>
  <table style="width:100%;direction:rtl">
    <thead>
      <tr>
        <th style="text-align:right">الحدث</th>
        <th style="text-align:right">المستلم</th>
        <th style="text-align:right">الفرع</th>
        <th style="text-align:right">القسم</th>
        <th style="text-align:right">الحالة</th>
        <th style="text-align:right"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rules as $r): ?>
      <tr>
        <td><?= $eventLabels[$r['event_type']] ?? Helpers::e($r['event_type']) ?></td>
        <td>
          <?php if ($r['user_id']): ?>
            <i class="fa-solid fa-user"></i> <?= Helpers::e($r['user_name']) ?>
          <?php elseif ($r['role_id']): ?>
            <i class="fa-solid fa-tag"></i> <?= Helpers::e($r['role_name']) ?>
          <?php else: ?>
            <i class="fa-solid fa-chart-column"></i> مستوى ≥ <?= (int) $r['min_admin_level'] ?>
            <?php if ($r['branch_specific'] || $r['department_specific']): ?>
              <small>(<?= $r['branch_specific'] ? 'الفرع' : '' ?><?= $r['branch_specific'] && $r['department_specific'] ? ' + ' : '' ?><?= $r['department_specific'] ? 'القسم' : '' ?>)</small>
            <?php endif; ?>
          <?php endif; ?>
        </td>
        <td><?= $r['branch_specific'] ? 'نعم' : 'لا' ?></td>
        <td><?= $r['department_specific'] ? 'نعم' : 'لا' ?></td>
        <td><span class="badge badge-<?= $r['is_active'] ? 'success' : 'danger' ?>"><?= $r['is_active'] ? 'فعال' : 'معطل' ?></span></td>
        <td><a href="/hr/notification-rules/<?= $r['id'] ?>/toggle" class="btn btn-sm btn-<?= $r['is_active'] ? 'warning' : 'success' ?>"><?= $r['is_active'] ? 'إيقاف' : 'تفعيل' ?></a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rules): ?><tr><td colspan="6">لا توجد قواعد</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<script>
function toggleRecipient(type) {
  document.getElementById('row_level').style.display = type === 'level' ? 'block' : 'none';
  document.getElementById('row_role').style.display = type === 'role' ? 'block' : 'none';
  document.getElementById('row_user').style.display = type === 'user' ? 'block' : 'none';
}
function validateRuleForm(form) {
  const type = form.recipient_type.value;
  if (type === 'role' && !form.role_id.value) { alert('اختر الدور'); return false; }
  if (type === 'user' && !form.user_id.value) { alert('اختر الشخص'); return false; }
  return true;
}
</script>
