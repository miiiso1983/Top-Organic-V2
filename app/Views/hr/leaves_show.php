<?php use App\Core\Helpers; ?>
<?php
$typeLabels = ['leave'=>'إجازة','exit'=>'إذن خروج','delay'=>'إذن تأخير','work_resumption'=>'مباشرة عمل'];
$statusLabels = ['pending'=>'بانتظار الموافقة','approved'=>'موافَق عليه','rejected'=>'مرفوض','cancelled'=>'ملغي'];
$decisionLabels = ['approved'=>'موافقة','rejected'=>'رفض'];
$leaveTypeLabels = ['unpaid_short'=>'إجازة قصيرة بدون راتب','unpaid_long'=>'إجازة طويلة بدون راتب','annual'=>'سنوية','sick'=>'مرضية','unpaid'=>'بدون راتب','emergency'=>'طارئة','hourly'=>'زمنية','n/a'=>'—'];
$req = $request;
$time = $req['start_time'] ? ($req['start_time'] . ($req['end_time'] ? ' - ' . $req['end_time'] : '')) : '-';
?>
<div class="card">
  <h3>طلب <?= $typeLabels[$req['request_type']] ?> <small>#<?= $req['id'] ?></small></h3>
  <p>
    <a href="/hr/leaves" class="btn btn-sm btn-secondary">← الطلبات</a>
    <?php if ($req['request_type'] === 'leave'): ?>
    <a href="/hr/leave-balances?employee_id=<?= $req['employee_id'] ?>" class="btn btn-sm btn-info">رصيد الإجازات</a>
    <?php endif; ?>
  </p>
  <table>
    <tr><th>الموظف</th><td><?= Helpers::e($req['full_name']) ?> (<?= Helpers::e($req['emp_code']) ?>)</td></tr>
    <tr><th>المقدم</th><td><?= Helpers::e($req['requester_name'] ?? '-') ?></td></tr>
    <tr><th>المدير المباشر</th><td><?= Helpers::e($req['manager_name'] ?? '-') ?></td></tr>
    <?php if ($req['request_type'] === 'leave'): ?><tr><th>نوع الإجازة</th><td><?= $leaveTypeLabels[$req['leave_type']] ?? $req['leave_type'] ?></td></tr><?php endif; ?>
    <tr><th>التاريخ</th><td><?= $req['date_from'] ?><?= $req['date_to'] && $req['date_to'] !== $req['date_from'] ? ' ↔ ' . $req['date_to'] : '' ?></td></tr>
    <tr><th>الوقت</th><td><?= $time ?></td></tr>
    <tr><th>السبب</th><td><?= Helpers::e($req['reason'] ?? '-') ?></td></tr>
    <tr><th>ملاحظات</th><td><?= Helpers::e($req['notes'] ?? '-') ?></td></tr>
    <tr><th>الحالة</th><td><span class="badge badge-<?= $req['status'] === 'approved' ? 'success' : ($req['status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= $statusLabels[$req['status']] ?? $req['status'] ?></span></td></tr>
    <tr><th>Workflow</th><td><?= $workflowRequestId ? '<a href="/workflows/requests/' . $workflowRequestId . '">عرض مسار الموافقات</a>' : 'غير مرتبط' ?></td></tr>
    <tr><th>تاريخ التقديم</th><td><?= $req['created_at'] ?></td></tr>
  </table>
</div>

<?php if ($attachments): ?>
<div class="card" style="margin-top:16px">
  <h3><i class="fa-solid fa-paperclip"></i> المرفقات</h3>
  <ul>
    <?php foreach ($attachments as $a): ?>
    <li><a href="<?= Helpers::e($a['file_path']) ?>" target="_blank"><?= Helpers::e($a['original_name'] ?? 'ملف') ?> (<?= number_format((int)($a['file_size'] ?? 0) / 1024, 1) ?> KB)</a></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
  <h3><i class="fa-solid fa-list"></i> سجل الموافقات</h3>
  <table>
    <tr><th>التاريخ</th><th>الإجراء</th><th>المرحلة</th><th>ملاحظات</th><th>بواسطة</th></tr>
    <?php foreach ($approvals as $a): ?>
    <tr>
      <td><?= $a['created_at'] ?? '-' ?></td>
      <td><span class="badge badge-<?= $a['action'] === 'approved' ? 'success' : ($a['action'] === 'rejected' ? 'danger' : ($a['action'] === 'returned_for_edit' ? 'warning' : 'info')) ?>"><?= $a['action'] ?></span></td>
      <td><?= Helpers::e($a['step_title'] ?? '') ?> <?= $a['step_order'] ? "(#{$a['step_order']})" : '' ?></td>
      <td><?= Helpers::e($a['notes'] ?? '-') ?></td>
      <td><?= Helpers::e($a['actor_name'] ?? $a['actor_real_name'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$approvals): ?><tr><td colspan="5">لا يوجد سجل موافقات</td></tr><?php endif; ?>
  </table>
</div>

<?php if ($canDecide): ?>
<div class="card" style="margin-top:16px">
  <h3><i class="fa-solid fa-pen-to-square"></i> اتخاذ قرار</h3>
  <form method="post" action="/hr/leaves/<?= $req['id'] ?>/decide" class="form-inline">
    <input type="hidden" name="decision" value="approved">
    <div class="form-row"><label>ملاحظات</label><input name="notes" placeholder="ملاحظات الموافقة"></div>
    <button class="btn btn-success">اعتماد</button>
  </form>
  <form method="post" action="/hr/leaves/<?= $req['id'] ?>/decide" class="form-inline" style="margin-top:10px">
    <input type="hidden" name="decision" value="rejected">
    <div class="form-row"><label>ملاحظات</label><input name="notes" placeholder="ملاحظات الرفض"></div>
    <button class="btn btn-danger">رفض</button>
  </form>
</div>
<?php endif; ?>
