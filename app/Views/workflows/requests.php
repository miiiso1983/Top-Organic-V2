<?php use App\Core\Helpers; ?>
<?php $statusLabels = ['draft'=>'مسودة','pending'=>'بانتظار','approved'=>'موافق','rejected'=>'مرفوض','returned_for_edit'=>'معاد للتعديل','cancelled'=>'ملغية']; ?>
<div class="page-head">
  <h2>الطلبات</h2>
  <a href="/workflows" class="btn btn-secondary">رجوع</a>
</div>

<div class="card">
  <h3>كل الطلبات</h3>
  <table>
    <tr><th>#</th><th>القالب</th><th>العنوان</th><th>مقدم</th><th>الحالة</th><th>المبلغ</th><th>التاريخ</th><th></th></tr>
    <?php foreach ($visible as $r): ?>
    <tr>
      <td><?= Helpers::e($r['request_no']) ?></td>
      <td><?= Helpers::e($r['template_name']) ?></td>
      <td><a href="/workflows/requests/<?= $r['id'] ?>"><?= Helpers::e($r['title']) ?></a></td>
      <td><?= Helpers::e($r['requester_name'] ?? '-') ?></td>
      <td><span class="badge badge-<?= $r['status'] ?>"><?= $statusLabels[$r['status']] ?? $r['status'] ?></span></td>
      <td><?= number_format((float)$r['amount'], 0) ?></td>
      <td><?= $r['created_at'] ?></td>
      <td><a href="/workflows/requests/<?= $r['id'] ?>" class="btn btn-sm">عرض</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$visible): ?><tr><td colspan="8">لا توجد طلبات</td></tr><?php endif; ?>
  </table>
</div>
