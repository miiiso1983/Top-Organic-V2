<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>تنبيهات الحضور</h3>
  <form method="get" action="/hr/attendance/alerts" class="form-inline">
    <div class="form-row"><label>اليوم</label><input type="date" name="date" value="<?= Helpers::e($date) ?>"></div>
    <button class="btn">عرض</button>
    <a href="/hr/attendance/alerts?run=1&date=<?= Helpers::e($date) ?>" class="btn btn-warning">فحص التنبيهات</a>
  </form>
  <?php if ($created): ?>
  <div class="alert alert-info">تم إنشاء <?= count($created) ?> تنبيه جديد.</div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
  <div class="alert alert-danger">خطأ أثناء فحص التنبيهات: <?= $error ?></div>
  <?php endif; ?>
</div>

<div class="card">
  <h4>سجل تنبيهات يوم <?= Helpers::e($date) ?></h4>
  <table>
    <tr><th>الموظف</th><th>القسم</th><th>موعد العمل</th><th>الحالة</th><th>التاريخ</th><th></th></tr>
    <?php foreach ($alerts as $a): ?>
    <tr>
      <td><?= Helpers::e($a['full_name']) ?></td>
      <td><?= Helpers::e($a['department'] ?? '-') ?></td>
      <td><?= $a['work_start_time'] ?? '-' ?></td>
      <td>
        <span class="badge badge-<?= $a['status'] === 'new' ? 'danger' : ($a['status'] === 'acknowledged' ? 'warning' : 'success') ?>">
          <?= ['new'=>'جديد','acknowledged'=>'جارٍ المراجعة','resolved'=>'محلول'][$a['status']] ?>
        </span>
      </td>
      <td><?= $a['created_at'] ?></td>
      <td>
        <?php if ($a['status'] === 'new'): ?>
        <form method="post" action="/hr/attendance/alerts/<?= $a['id'] ?>/status" style="display:inline">
          <input type="hidden" name="status" value="acknowledged">
          <button class="btn btn-sm btn-primary">مراجعة</button>
        </form>
        <?php endif; ?>
        <form method="post" action="/hr/attendance/alerts/<?= $a['id'] ?>/status" style="display:inline">
          <input type="hidden" name="status" value="resolved">
          <button class="btn btn-sm btn-success">استيراد</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$alerts): ?><tr><td colspan="6">لا توجد تنبيهات</td></tr><?php endif; ?>
  </table>
</div>
