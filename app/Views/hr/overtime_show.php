<?php use App\Core\Helpers; ?>
<?php $statusLabels = ['pending'=>'بانتظار المدير المباشر','manager_approved'=>'بانتظار مدير المدير','fully_approved'=>'معتمد نهائياً','rejected'=>'مرفوض','expired'=>'منتهي الصلاحية']; ?>
<?php $matched = (int)($overtime['biometric_match'] ?? 0); ?>

<div class="card">
  <h3>تفاصيل طلب العمل الإضافي: <?= Helpers::e($overtime['full_name']) ?></h3>
  <table class="detail-table">
    <tr><td>الموظف</td><td><?= Helpers::e($overtime['full_name']) ?> (<?= Helpers::e($overtime['emp_code'] ?? '-') ?>)</td></tr>
    <tr><td>التاريخ</td><td><?= Helpers::e($overtime['request_date']) ?></td></tr>
    <tr><td>الساعات المطلوبة</td><td><?= Helpers::e($overtime['hours']) ?></td></tr>
    <tr><td>الساعات الفعلية (البصمة)</td><td><?= Helpers::e($biometricHours) ?></td></tr>
    <tr><td>مطابقة البصمة</td><td><span class="badge badge-<?= $matched ? 'success' : 'warning' ?>"><?= $matched ? 'مطابقة' : 'غير مطابقة' ?></span></td></tr>
    <tr><td>المبلغ المحتسب</td><td><?= Helpers::money($overtime['amount'] ?? 0) ?></td></tr>
    <tr><td>السبب</td><td><?= Helpers::e($overtime['reason'] ?? '-') ?></td></tr>
    <tr><td>الحالة</td><td><span class="badge badge-<?= $overtime['status'] === 'fully_approved' ? 'success' : ($overtime['status'] === 'rejected' || $overtime['status'] === 'expired' ? 'danger' : 'warning') ?>"><?= $statusLabels[$overtime['status']] ?></span></td></tr>
    <tr><td>Workflow</td><td><?= $workflowRequestId ? '<a href="/workflows/requests/' . $workflowRequestId . '">عرض مسار الموافقات</a>' : 'غير مرتبط' ?></td></tr>
    <?php if ($overtime['manager_name']): ?>
    <tr><td>اعتمد المدير المباشر</td><td><?= Helpers::e($overtime['manager_name']) ?> - <?= $overtime['manager_approved_at'] ? date('Y-m-d H:i', strtotime($overtime['manager_approved_at'])) : '-' ?></td></tr>
    <?php endif; ?>
    <?php if ($overtime['grand_manager_name']): ?>
    <tr><td>اعتمد مدير المدير</td><td><?= Helpers::e($overtime['grand_manager_name']) ?> - <?= $overtime['grand_manager_approved_at'] ? date('Y-m-d H:i', strtotime($overtime['grand_manager_approved_at'])) : '-' ?></td></tr>
    <?php endif; ?>
    <?php if ($overtime['rejected_by_name']): ?>
    <tr><td>رفض بواسطة</td><td><?= Helpers::e($overtime['rejected_by_name']) ?> - <?= $overtime['rejected_at'] ? date('Y-m-d H:i', strtotime($overtime['rejected_at'])) : '-' ?></td></tr>
    <?php endif; ?>
  </table>

  <div class="flex-between" style="margin-top:15px">
    <a href="/hr/overtime" class="btn btn-light">العودة للقائمة</a>
  </div>
</div>

<?php if ($attendance): ?>
<div class="card">
  <h3>سجلات البصمة</h3>
  <table>
    <tr><th>النوع</th><th>الوقت</th><th>التأخير (د)</th><th>الانصراف المبكر (د)</th><th>الإضافي (د)</th><th>المصدر</th></tr>
    <?php foreach ($attendance as $a): ?>
    <tr>
      <td><?= $a['check_type'] === 'in' ? 'دخول' : 'خروج' ?></td>
      <td><?= $a['checked_at'] ? date('Y-m-d H:i', strtotime($a['checked_at'])) : '-' ?></td>
      <td><?= Helpers::e($a['late_minutes'] ?? 0) ?></td>
      <td><?= Helpers::e($a['early_minutes'] ?? 0) ?></td>
      <td><?= Helpers::e($a['overtime_minutes'] ?? 0) ?></td>
      <td><?= Helpers::e($a['source'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php if ($approvals): ?>
<div class="card">
  <h3>سجل الموافقات</h3>
  <table>
    <tr><th>التاريخ</th><th>الإجراء</th><th>المرحلة</th><th>ملاحظات</th><th>بواسطة</th></tr>
    <?php foreach ($approvals as $ap): ?>
    <tr>
      <td><?= $ap['created_at'] ? date('Y-m-d H:i', strtotime($ap['created_at'])) : '-' ?></td>
      <td><span class="badge badge-<?= $ap['action'] === 'approved' ? 'success' : ($ap['action'] === 'rejected' ? 'danger' : 'warning') ?>"><?= $ap['action'] ?></span></td>
      <td><?= Helpers::e($ap['step_title'] ?? '') ?> <?= $ap['step_order'] ? "(#{$ap['step_order']})" : '' ?></td>
      <td><?= Helpers::e($ap['notes'] ?? '-') ?></td>
      <td><?= Helpers::e($ap['actor_name'] ?? $ap['actor_real_name'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php if ($canDecide): ?>
<div class="card" style="margin-top:16px">
  <h3><i class="fa-solid fa-pen-to-square"></i> اتخاذ قرار</h3>
  <form method="post" action="/hr/overtime/<?= $overtime['id'] ?>/decide" class="form-inline">
    <input type="hidden" name="decision" value="approved">
    <div class="form-row"><label>ملاحظات</label><input name="notes" placeholder="ملاحظات الموافقة"></div>
    <button class="btn btn-success">موافقة</button>
  </form>
  <form method="post" action="/hr/overtime/<?= $overtime['id'] ?>/decide" class="form-inline" style="margin-top:10px">
    <input type="hidden" name="decision" value="rejected">
    <div class="form-row"><label>ملاحظات</label><input name="notes" placeholder="ملاحظات الرفض"></div>
    <button class="btn btn-danger">رفض</button>
  </form>
</div>
<?php endif; ?>
