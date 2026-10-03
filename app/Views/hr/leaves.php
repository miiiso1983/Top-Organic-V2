<?php use App\Core\Helpers; ?>
<?php
$typeLabels = ['leave'=>'إجازة','exit'=>'إذن خروج','delay'=>'إذن تأخير','work_resumption'=>'مباشرة عمل'];
$statusLabels = ['pending'=>'بانتظار الموافقة','approved'=>'موافَق عليه','rejected'=>'مرفوض','cancelled'=>'ملغي'];
$leaveTypeLabels = ['unpaid_short'=>'إجازة قصيرة بدون راتب','unpaid_long'=>'إجازة طويلة بدون راتب','annual'=>'سنوية','sick'=>'مرضية','unpaid'=>'بدون راتب','emergency'=>'طارئة','hourly'=>'زمنية','n/a'=>'—'];
$activeTab = (string)($_GET['tab'] ?? 'leave');
?>
<div class="card">
  <h3>طلب جديد</h3>
  <div class="tabs" style="margin-bottom:12px">
    <?php foreach ($typeLabels as $k => $v): ?>
    <a href="?tab=<?= $k ?>" class="btn btn-sm <?= $activeTab === $k ? 'btn-primary' : 'btn-secondary' ?>"><?= $v ?></a>
    <?php endforeach; ?>
  </div>
  <form method="post" action="/hr/leaves" enctype="multipart/form-data" class="form-inline">
    <input type="hidden" name="request_type" value="<?= $activeTab ?>">
    <div class="form-row"><label>الموظف</label>
      <select name="employee_id" required>
        <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= Helpers::e($e['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php if ($activeTab === 'leave'): ?>
    <div class="form-row"><label>نوع الإجازة</label>
      <select name="leave_type">
        <option value="unpaid_short">إجازة قصيرة بدون راتب (حتى 3 أيام)</option>
        <option value="unpaid_long">إجازة طويلة بدون راتب (4 أيام فأكثر)</option>
      </select>
    </div>
    <div class="form-row"><label>من</label><input type="date" name="date_from" required></div>
    <div class="form-row"><label>إلى</label><input type="date" name="date_to" required></div>
    <?php else: ?>
    <div class="form-row"><label>التاريخ</label><input type="date" name="date_from" required></div>
    <div class="form-row"><label><?= $activeTab === 'work_resumption' ? 'وقت المباشرة' : 'من الساعة' ?></label><input type="time" name="start_time"></div>
    <?php if ($activeTab !== 'work_resumption'): ?>
    <div class="form-row"><label>إلى الساعة</label><input type="time" name="end_time"></div>
    <?php endif; ?>
    <?php endif; ?>
    <div class="form-row"><label>السبب</label><input name="reason"></div>
    <div class="form-row"><label>ملاحظات</label><input name="notes"></div>
    <div class="form-row"><label>مرفقات</label><input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></div>
    <button class="btn btn-success">تقديم الطلب</button>
  </form>
</div>

<div class="card" style="margin-top:16px">
  <h3>الطلبات</h3>
  <form method="get" action="/hr/leaves" class="form-inline" style="margin-bottom:10px">
    <input type="hidden" name="tab" value="<?= $activeTab ?>">
    <div class="form-row"><label>النوع</label>
      <select name="type">
        <option value="">الكل</option>
        <?php foreach ($typeLabels as $k => $v): ?><option value="<?= $k ?>" <?= $filters['type'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الحالة</label>
      <select name="status">
        <option value="">الكل</option>
        <?php foreach ($statusLabels as $k => $v): ?><option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الموظف</label>
      <select name="employee_id">
        <option value="">الكل</option>
        <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>" <?= (string)($filters['employee_id'] ?? '') === (string)$e['id'] ? 'selected' : '' ?>><?= Helpers::e($e['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary">تصفية</button>
    <a href="/hr/leaves" class="btn btn-secondary">إعادة تعيين</a>
  </form>
  <table>
    <tr><th>الموظف</th><th>النوع</th><th>التاريخ</th><th>الوقت</th><th>السبب</th><th>الحالة</th><th>المرحلة</th><th>مرفقات</th><th></th></tr>
    <?php foreach ($requests as $r): ?>
    <?php $time = $r['start_time'] ? ($r['start_time'] . ($r['end_time'] ? ' - ' . $r['end_time'] : '')) : '-'; ?>
    <tr>
      <td><?= Helpers::e($r['full_name']) ?></td>
      <td><?= $typeLabels[$r['request_type']] ?? $r['request_type'] ?><br><small><?= $r['request_type'] === 'leave' ? ($leaveTypeLabels[$r['leave_type']] ?? $r['leave_type']) : '' ?></small></td>
      <td><?= $r['date_from'] ?><?= $r['date_to'] && $r['date_to'] !== $r['date_from'] ? ' <br>↔ ' . $r['date_to'] : '' ?></td>
      <td><?= $time ?></td>
      <td><?= Helpers::e($r['reason'] ?? '-') ?></td>
      <td><span class="badge badge-<?= $r['status'] === 'approved' ? 'success' : ($r['status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= $statusLabels[$r['status']] ?? $r['status'] ?></span></td>
      <td><?= $r['workflow_request_id'] ? ($r['status'] === 'pending' ? 'المرحلة ' . ((int)($r['workflow_current_step'] ?? 1)) : 'مكتمل') : 'غير مرتبط' ?></td>
      <td><?= count($r['attachments'] ?? []) ?></td>
      <td>
        <a href="/hr/leaves/<?= $r['id'] ?>" class="btn btn-sm btn-primary">عرض</a>
        <?php if ($r['workflow_request_id']): ?>
        <a href="/workflows/requests/<?= $r['workflow_request_id'] ?>" class="btn btn-sm btn-secondary">Workflow</a>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$requests): ?><tr><td colspan="9">لا توجد طلبات</td></tr><?php endif; ?>
  </table>
</div>
