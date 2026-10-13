<?php use App\Core\Helpers; ?>
<?php use App\Core\Auth; ?>
<?php $targetLabels = ['all'=>'الجميع','level'=>'مستوى إداري','role'=>'دور','branch'=>'فرع','department'=>'قسم','user'=>'موظف']; ?>
<?php
$targetUserIds = [];
if (($notification['target_type'] ?? '') === 'user') {
    foreach ($recipients as $r) {
        if (!empty($r['user_id'])) { $targetUserIds[] = (int)$r['user_id']; }
    }
}
$resendData = [
    'id' => (int)$notification['id'],
    'title' => $notification['title'],
    'body' => $notification['body'],
    'link' => $notification['link'],
    'target_type' => $notification['target_type'] ?? 'all',
    'target_level' => $notification['target_level'],
    'target_role_id' => $notification['target_role_id'],
    'target_branch_id' => $notification['target_branch_id'],
    'target_department_id' => $notification['target_department_id'],
    'target_user_ids' => $targetUserIds,
];
?>
<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px">
    <h3 style="margin:0"><?= Helpers::e($notification['title']) ?></h3>
    <?php if (Auth::can('hr.notifications.send')): ?>
    <a href="/hr/announcements?resend=<?= (int)$notification['id'] ?>" class="btn btn-sm btn-light" data-resend-announcement="1"
       data-resend='<?= Helpers::e(json_encode($resendData, JSON_UNESCAPED_UNICODE)) ?>'>
      <i class="fa-solid fa-pen-to-square"></i> تعديل وإعادة الإرسال
    </a>
    <?php endif; ?>
  </div>
  <table class="detail-table">
    <tr><td>النص</td><td><?= nl2br(Helpers::e($notification['body'])) ?></td></tr>
    <tr><td>المستلمين</td><td><?= $targetLabels[$notification['target_type']] ?? $notification['target_type'] ?></td></tr>
    <tr><td>المرسل</td><td><?= Helpers::e($notification['sender_name'] ?? '-') ?></td></tr>
    <tr><td>التاريخ</td><td><?= $notification['created_at'] ? date('Y-m-d H:i', strtotime($notification['created_at'])) : '-' ?></td></tr>
    <?php if ($notification['link']): ?>
    <tr><td>الرابط</td><td><a href="<?= Helpers::e($notification['link']) ?>"><?= Helpers::e($notification['link']) ?></a></td></tr>
    <?php endif; ?>
  </table>
</div>

<?php
$totalRecipients = count($recipients);
$viewedCount = 0;
foreach ($recipients as $r) {
    if (!empty($r['receipt_read_at'] ?? $r['read_at'])) { $viewedCount++; }
}
?>
<div class="card">
  <h4>سجل الاستلام والقراءة</h4>
  <div class="announcement-views-summary">
    <span class="badge badge-info"><i class="fa-solid fa-eye"></i> عدد المشاهدات: <?= $viewedCount ?> من <?= $totalRecipients ?></span>
  </div>
  <table>
    <tr><th>المستلم</th><th>اسم المستخدم</th><th>تاريخ القراءة</th><th>IP</th></tr>
    <?php foreach ($recipients as $r):
        $readAt = $r['receipt_read_at'] ?? $r['read_at'];
        $readIp = $r['receipt_read_ip'] ?? $r['read_ip'];
    ?>
    <tr>
      <td><?= Helpers::e($r['full_name'] ?? '-') ?></td>
      <td><?= Helpers::e($r['username'] ?? '-') ?></td>
      <td><?= !empty($readAt) ? date('Y-m-d H:i', strtotime($readAt)) : '<span class="badge badge-warning">غير مقروء</span>' ?></td>
      <td><?= Helpers::e($readIp ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$recipients): ?><tr><td colspan="4">لا توجد بيانات استلام</td></tr><?php endif; ?>
  </table>
</div>

<style>
.announcement-views-summary { margin-bottom: 10px; }
.announcement-views-summary .badge { font-size: 13px; padding: 6px 10px; }
</style>
