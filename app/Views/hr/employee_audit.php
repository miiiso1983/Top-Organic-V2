<?php use App\Core\Helpers; ?>
<?php $isModal = !empty($_GET['modal']); $modalQuery = $isModal ? '?modal=1' : ''; ?>
<div class="card">
  <h3><i class="fa-solid fa-list"></i> سجل تدقيق الموظف: <?= Helpers::e($employee['full_name']) ?> <small>(<?= Helpers::e($employee['emp_code']) ?>)</small></h3>
  <p>
    <a href="/hr/employees/<?= $employee['id'] ?><?= $modalQuery ?>" class="btn btn-sm btn-secondary">← الملف الشخصي</a>
    <?php if ($isModal): ?>
    <button type="button" class="btn btn-sm btn-danger" onclick="if(window.parent && window.parent.closeEmployeeModal){ window.parent.closeEmployeeModal(); } else { history.back(); }">
      <i class="fa-solid fa-xmark"></i> إغلاق
    </button>
    <?php endif; ?>
    <a href="/hr/employees/<?= $employee['id'] ?>/edit<?= $modalQuery ?>" class="btn btn-sm btn-primary">تعديل البيانات</a>
    <a href="/hr/employees/<?= $employee['id'] ?>/documents<?= $modalQuery ?>" class="btn btn-sm btn-info">المستندات</a>
  </p>
</div>

<div class="card" style="margin-top:16px">
  <h4>سجل التعديلات</h4>
  <div style="overflow:auto">
    <table>
      <tr><th>#</th><th>الإجراء</th><th>الحقل</th><th>القيمة القديمة</th><th>القيمة الجديدة</th><th>المستخدم</th><th>التاريخ</th></tr>
      <?php foreach ($logs as $i => $l): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><?= Helpers::e($l['action']) ?></td>
        <td><?= Helpers::e($l['field_name'] ?? '-') ?></td>
        <td><?= Helpers::e($l['old_value'] ?? '-') ?></td>
        <td><?= Helpers::e($l['new_value'] ?? '-') ?></td>
        <td><?= Helpers::e($l['changed_by_name'] ?? '-') ?></td>
        <td><?= $l['created_at'] ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?><tr><td colspan="7">لا توجد تعديلات مسجلة</td></tr><?php endif; ?>
    </table>
  </div>
</div>
