<?php use App\Core\Helpers; ?>
<?php
$statusLabels = ['pending'=>'بانتظار التأكيد','confirmed'=>'مؤكد','seated'=>'تم الجلوس','cancelled'=>'ملغي','no_show'=>'لم يحضر','waiting'=>'قائمة انتظار','rejected'=>'مرفوض','completed'=>'مكتمل'];
$statusBadge = ['pending'=>'warning','confirmed'=>'success','seated'=>'primary','cancelled'=>'danger','no_show'=>'secondary','waiting'=>'info','rejected'=>'dark','completed'=>'success'];
$sourceLabels = ['web'=>'ويب','call_center'=>'كول سنتر','whatsapp'=>'واتساب','walk_in'=>'حضوري','app'=>'تطبيق','pos'=>'نقاط بيع'];
$typeLabels = ['dine_in'=>'طعام في المطعم','private'=>'قاعة خاصة','event'=>'فعالية','birthday'=>'عيد ميلاد','business'=>'عمل','delivery'=>'توصيل','takeaway'=>'takeaway'];
$activeStatuses = ['pending','confirmed','seated','waiting'];
?>
<div class="card">
  <h3>حجز <?= Helpers::e($res['reservation_no'] ?? '#' . $res['id']) ?></h3>
  <div class="info-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:15px">
    <div><strong>العميل:</strong> <?= Helpers::e($res['customer_name']) ?></div>
    <div><strong>الهاتف:</strong> <?= Helpers::e($res['customer_phone'] ?? '-') ?></div>
    <div><strong>البريد:</strong> <?= Helpers::e($res['customer_email'] ?? '-') ?></div>
    <div><strong>الضيوف:</strong> <?= (int)$res['guests'] ?></div>
    <div><strong>الموعد:</strong> <?= date('Y-m-d H:i', strtotime($res['reserved_at'])) ?></div>
    <div><strong>المدة:</strong> <?= (int)$res['duration_minutes'] ?> دقيقة</div>
    <div><strong>النوع:</strong> <?= $typeLabels[$res['reservation_type']] ?? $res['reservation_type'] ?></div>
    <div><strong>المصدر:</strong> <?= $sourceLabels[$res['source']] ?? $res['source'] ?></div>
    <div><strong>الحالة:</strong> <span class="badge badge-<?= $statusBadge[$res['status']] ?>"><?= $statusLabels[$res['status']] ?></span></div>
    <div><strong>الطاولة:</strong> <?= Helpers::e($res['table_name'] ?? '-') ?> <?= $res['floor'] ? '(طابق ' . Helpers::e($res['floor']) . ')' : '' ?></div>
    <div><strong>الفرع:</strong> <?= Helpers::e($res['branch_name'] ?? '-') ?></div>
  </div>
  <?php if ($res['customer_notes']): ?><p><strong>ملاحظات العميل:</strong> <?= nl2br(Helpers::e($res['customer_notes'])) ?></p><?php endif; ?>
  <?php if ($res['notes']): ?><p><strong>ملاحظات داخلية:</strong> <?= nl2br(Helpers::e($res['notes'])) ?></p><?php endif; ?>
  <?php if ($res['cancellation_reason']): ?><p><strong>سبب الإلغاء:</strong> <?= Helpers::e($res['cancellation_reason']) ?></p><?php endif; ?>
  <?php if ($res['rejection_reason']): ?><p><strong>سبب الرفض:</strong> <?= Helpers::e($res['rejection_reason']) ?></p><?php endif; ?>
</div>

<?php if (\App\Core\Auth::can('reservations.manage') || \App\Core\Auth::can('reservations.approve')): ?>
<div class="card">
  <h3>إجراءات</h3>
  <div class="form-inline" style="gap:10px;flex-wrap:wrap">
    <?php if (in_array($res['status'], ['pending','waiting'], true)): ?>
      <form method="post" action="/reservations/<?= $res['id'] ?>/status"><input type="hidden" name="status" value="confirmed"><button class="btn btn-success">تأكيد</button></form>
    <?php endif; ?>
    <?php if ($res['status'] === 'confirmed'): ?>
      <form method="post" action="/reservations/<?= $res['id'] ?>/status"><input type="hidden" name="status" value="seated"><button class="btn btn-primary">تسجيل جلوس</button></form>
      <a href="/reservations/<?= $res['id'] ?>/check-in" class="btn btn-primary" onclick="return confirm('تأكيد تسجيل الدخول؟')">Check-in</a>
    <?php endif; ?>
    <?php if ($res['status'] === 'seated'): ?>
      <a href="/reservations/<?= $res['id'] ?>/check-out" class="btn btn-secondary" onclick="return confirm('تأكيد تسجيل الخروج؟')">Check-out</a>
    <?php endif; ?>
    <?php if (in_array($res['status'], ['pending','confirmed'], true)): ?>
      <form method="post" action="/reservations/<?= $res['id'] ?>/status"><input type="hidden" name="status" value="waiting"><button class="btn btn-info">نقل لقائمة الانتظار</button></form>
    <?php endif; ?>
    <?php if (in_array($res['status'], $activeStatuses, true)): ?>
      <form method="post" action="/reservations/<?= $res['id'] ?>/status" onsubmit="return confirm('تأكيد الإلغاء؟')">
        <input type="hidden" name="status" value="cancelled">
        <input name="reason" placeholder="سبب الإلغاء" required>
        <button class="btn btn-danger">إلغاء</button>
      </form>
      <form method="post" action="/reservations/<?= $res['id'] ?>/status" onsubmit="return confirm('تأكيد الرفض؟')">
        <input type="hidden" name="status" value="rejected">
        <input name="reason" placeholder="سبب الرفض" required>
        <button class="btn btn-warning">رفض</button>
      </form>
    <?php endif; ?>
    <form method="post" action="/reservations/<?= $res['id'] ?>/reschedule" class="form-inline">
      <input type="text" class="reservation-datetime" name="reserved_at" value="<?= date('Y-m-d H:i', strtotime($res['reserved_at'])) ?>" required>
      <button class="btn btn-secondary">إعادة جدولة</button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h3>سجل العمليات (Audit Trail)</h3>
  <table>
    <tr><th>التاريخ</th><th>الإجراء</th><th>من</th><th>من حالة</th><th>إلى حالة</th><th>ملاحظات</th></tr>
    <?php foreach ($logs as $l): ?>
    <tr>
      <td><?= date('Y-m-d H:i', strtotime($l['created_at'])) ?></td>
      <td><?= Helpers::e($l['action']) ?></td>
      <td><?= Helpers::e($l['actor_name'] ?? '-') ?></td>
      <td><?= $l['old_status'] ? $statusLabels[$l['old_status']] : '-' ?></td>
      <td><?= $l['new_status'] ? $statusLabels[$l['new_status']] : '-' ?></td>
      <td><?= Helpers::e($l['notes'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$logs): ?><tr><td colspan="6">لا يوجد سجل</td></tr><?php endif; ?>
  </table>
</div>

<div class="card" style="text-align:center">
  <a href="/reservations" class="btn btn-primary">رجوع للحجوزات</a>
  <a href="/reservations/<?= $res['id'] ?>/edit" class="btn btn-secondary">تعديل الحجز</a>
</div>
