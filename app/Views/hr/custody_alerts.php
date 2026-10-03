<?php use App\Core\Helpers; ?>
<?php
$typeLabels = ['due_soon' => 'قريب من الاستحقاق', 'overdue' => 'متأخر', 'expiring' => 'تنتهي صلاحيتها'];
$typeBadge = ['due_soon' => 'warning', 'overdue' => 'danger', 'expiring' => 'info'];
?>
<div class="card">
  <h3>تنبيهات العهد</h3>
  <form method="get" action="/hr/custody/alerts" class="form-inline" style="margin-bottom:15px">
    <div class="form-row"><label>التاريخ</label><input type="date" name="date" value="<?= $date ?>"></div>
    <button class="btn btn-sm btn-primary">عرض</button>
    <a href="/hr/custody/alerts?date=<?= $date ?>&run=1" class="btn btn-sm btn-success" style="margin-right:10px">تشغيل الفحص</a>
  </form>
  <?php if ($created): ?>
    <div class="alert alert-info">تم إنشاء <?= count($created) ?> تنبيه جديد.</div>
  <?php endif; ?>
  <table>
    <tr><th>الموظف</th><th>القطعة</th><th>تاريخ مرتبط</th><th>نوع التنبيه</th><th>الحالة</th><th>تاريخ التنبيه</th><th></th></tr>
    <?php foreach ($alerts as $a):
      $relatedDate = '-';
      if ($a['alert_type'] === 'expiring' && $a['expiry_date']) {
          $relatedDate = date('Y-m-d', strtotime($a['expiry_date'])) . ' (انتهاء)';
      } elseif ($a['due_date']) {
          $relatedDate = date('Y-m-d', strtotime($a['due_date']));
      }
    ?>
    <tr>
      <td><?= Helpers::e($a['full_name']) ?></td>
      <td><?= Helpers::e($a['item_name']) ?></td>
      <td><?= $relatedDate ?></td>
      <td><span class="badge badge-<?= $typeBadge[$a['alert_type']] ?>"><?= $typeLabels[$a['alert_type']] ?></span></td>
      <td><?= $a['is_acknowledged'] ? 'مُعالَج' : 'جديد' ?></td>
      <td><?= date('Y-m-d H:i', strtotime($a['created_at'])) ?></td>
      <td>
        <?php if (!$a['is_acknowledged'] && \App\Core\Auth::can('hr.custody.manage')): ?>
        <form method="post" action="/hr/custody/alerts/<?= $a['id'] ?>/ack" style="display:inline">
          <button class="btn btn-sm btn-primary">تسليم</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$alerts): ?><tr><td colspan="7">لا توجد تنبيهات لهذا اليوم</td></tr><?php endif; ?>
  </table>
</div>
