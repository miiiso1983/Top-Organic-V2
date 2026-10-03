<?php use App\Core\Helpers; ?>
<?php
$statusLabels = ['pending'=>'بانتظار الموافقة','approved'=>'موافَق عليه','rejected'=>'مرفوض'];
$levelLabels = ['manager'=>'المدير المباشر','hr'=>'الموارد البشرية','finance'=>'المالية'];
?>
<div class="card" style="margin-bottom:16px">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h3 style="margin:0"><i class="fa-solid fa-users"></i> لوحة المدير</h3>
    <div class="badge badge-primary" style="font-size:1.1rem">
      <i class="fa-solid fa-user-check"></i> عدد التابعين: <?= (int)($subordinatesCount ?? 0) ?>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:16px">
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="/hr/manager-requests/hiring/create" class="btn btn-success">
      <i class="fa-solid fa-user-plus"></i> طلب تعيين جديد
    </a>
    <a href="/hr/manager-requests/suspension/create" class="btn btn-warning">
      <i class="fa-solid fa-user-slash"></i> طلب إيقاف دوام
    </a>
  </div>
</div>

<div class="card" style="margin-bottom:16px">
  <h4><i class="fa-solid fa-list"></i> طلبات التعيين</h4>
  <table>
    <tr><th>الرقم</th><th>المسمى الوظيفي</th><th>القسم</th><th>الراتب المتوقع</th><th>الحالة</th><th>المراحل</th><th>التاريخ</th><th></th></tr>
    <?php foreach ($hiring as $h): ?>
    <tr>
      <td><?= Helpers::e($h['request_no'] ?? '-') ?></td>
      <td><?= Helpers::e($h['job_title']) ?></td>
      <td><?= Helpers::e($h['department_name'] ?? '-') ?></td>
      <td><?= Helpers::money($h['expected_salary']) ?></td>
      <td><span class="badge badge-<?= $h['overall_status'] === 'approved' ? 'success' : ($h['overall_status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= $statusLabels[$h['overall_status']] ?? $h['overall_status'] ?></span></td>
      <td>
        <small>مدير: <?= $h['manager_status'] ?></small><br>
        <small>HR: <?= $h['hr_status'] ?></small><br>
        <small>مالية: <?= $h['finance_status'] ?></small>
      </td>
      <td><?= Helpers::e($h['created_at'] ?? '-') ?></td>
      <td><a href="/hr/hiring-requests/<?= (int)$h['id'] ?>" class="btn btn-sm btn-primary">عرض</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$hiring): ?><tr><td colspan="8">لا توجد طلبات تعيين</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h4><i class="fa-solid fa-list"></i> طلبات إيقاف الدوام</h4>
  <table>
    <tr><th>الرقم</th><th>الموظف</th><th>تاريخ الإيقاف</th><th>النوع</th><th>الحالة</th><th>المراحل</th><th>التاريخ</th><th></th></tr>
    <?php foreach ($suspensions as $s): ?>
    <tr>
      <td><?= Helpers::e($s['request_no'] ?? '-') ?></td>
      <td><?= Helpers::e($s['employee_name'] ?? '-') ?></td>
      <td><?= Helpers::e($s['suspension_date']) ?></td>
      <td><?= $s['suspension_type'] === 'permanent' ? 'نهائي' : 'مؤقت' ?></td>
      <td><span class="badge badge-<?= $s['overall_status'] === 'approved' ? 'success' : ($s['overall_status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= $statusLabels[$s['overall_status']] ?? $s['overall_status'] ?></span></td>
      <td>
        <small>مدير: <?= $s['manager_status'] ?></small><br>
        <small>HR: <?= $s['hr_status'] ?></small><br>
        <small>مالية: <?= $s['finance_status'] ?></small>
      </td>
      <td><?= Helpers::e($s['created_at'] ?? '-') ?></td>
      <td><a href="/hr/suspension-requests/<?= (int)$s['id'] ?>" class="btn btn-sm btn-primary">عرض</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$suspensions): ?><tr><td colspan="8">لا توجد طلبات إيقاف دوام</td></tr><?php endif; ?>
  </table>
</div>
