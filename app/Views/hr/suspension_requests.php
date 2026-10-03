<?php use App\Core\Helpers; ?>
<?php $statusLabels = ['pending'=>'بانتظار الموافقة','approved'=>'موافَق عليه','rejected'=>'مرفوض']; ?>
<div class="card">
  <h3><i class="fa-solid fa-user-slash"></i> طلبات إيقاف الدوام</h3>
  <table>
    <tr><th>الرقم</th><th>الطالب</th><th>الموظف</th><th>تاريخ الإيقاف</th><th>النوع</th><th>الحالة</th><th>المراحل</th><th>التاريخ</th><th></th></tr>
    <?php foreach ($requests as $r): ?>
    <tr>
      <td><?= Helpers::e($r['request_no'] ?? '-') ?></td>
      <td><?= Helpers::e($r['requester_name'] ?? '-') ?></td>
      <td><?= Helpers::e($r['employee_name'] ?? '-') ?></td>
      <td><?= Helpers::e($r['suspension_date']) ?></td>
      <td><?= $r['suspension_type'] === 'permanent' ? 'نهائي' : 'مؤقت' ?></td>
      <td><span class="badge badge-<?= $r['overall_status'] === 'approved' ? 'success' : ($r['overall_status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= $statusLabels[$r['overall_status']] ?? $r['overall_status'] ?></span></td>
      <td>
        <small>مدير: <?= $r['manager_status'] ?></small><br>
        <small>HR: <?= $r['hr_status'] ?></small><br>
        <small>مالية: <?= $r['finance_status'] ?></small>
      </td>
      <td><?= Helpers::e($r['created_at'] ?? '-') ?></td>
      <td><a href="/hr/suspension-requests/<?= (int)$r['id'] ?>" class="btn btn-sm btn-primary">عرض</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$requests): ?><tr><td colspan="9">لا توجد طلبات</td></tr><?php endif; ?>
  </table>
</div>
