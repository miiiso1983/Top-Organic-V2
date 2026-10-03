<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h2>تنبيهات الديون المتأخرة — <?= $type === 'customer' ? 'العملاء' : 'الموردين' ?></h2>
  <form method="get" class="form-inline" style="margin-top:12px">
    <input type="hidden" name="type" value="<?= $type ?>">
    <label>متأخر أكثر من</label>
    <input type="number" name="days" value="<?= (int)$days ?>" min="1" style="width:80px">
    <span>يوم</span>
    <button class="btn">تحديث</button>
    <a href="?type=customer" class="btn <?= $type === 'customer' ? 'btn-success' : '' ?>">العملاء</a>
    <a href="?type=supplier" class="btn <?= $type === 'supplier' ? 'btn-success' : '' ?>">الموردين</a>
  </form>
</div>
<div class="grid grid-3" style="margin-bottom:16px">
  <div class="card" style="text-align:center">
    <div style="font-size:24px; font-weight:bold"><?= (int)$report['count'] ?></div>
    <div>عدد المستندات</div>
  </div>
  <div class="card" style="text-align:center">
    <div style="font-size:24px; font-weight:bold"><?= Helpers::money($report['total']) ?></div>
    <div>المبلغ المتأخر</div>
  </div>
  <div class="card" style="text-align:center">
    <div style="font-size:24px; font-weight:bold"><?= (int)$days ?></div>
    <div>يوم متأخرة</div>
  </div>
</div>
<div class="card">
  <table>
    <tr><th>الطرف</th><th>المستند</th><th>التاريخ</th><th>المتأخر (يوم)</th><th>المبلغ</th><th>المتبقي</th><th></th></tr>
    <?php foreach ($report['rows'] as $r): ?>
    <tr>
      <td><?= Helpers::e($r['party_name']) ?><br><small><?= Helpers::e($r['party_phone']) ?></small></td>
      <td><?= Helpers::e($r['ref']) ?></td>
      <td><?= Helpers::e($r['date']) ?></td>
      <td style="text-align:center; color:<?= (int)$r['overdue_days'] > 60 ? '#c0392b' : ((int)$r['overdue_days'] > 30 ? '#e67e22' : '#2980b9') ?>"><?= (int)$r['overdue_days'] ?></td>
      <td><?= Helpers::money($r['total']) ?></td>
      <td><?= Helpers::money($r['remaining']) ?></td>
      <td><a href="/accounting/<?= $type === 'customer' ? 'receivables' : 'payables' ?>/<?= (int)$r['party_id'] ?>" class="btn">عرض</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
