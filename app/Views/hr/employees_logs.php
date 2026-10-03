<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>سجل تغييرات حالة الملف</h3>
  <p>
    <a href="/hr/employees" class="btn btn-sm btn-secondary">← الموظفون</a>
  </p>
  <?php if (!empty($statusLogs)): ?>
  <div style="overflow:auto">
    <table>
      <tr><th>الموظف</th><th>من</th><th>إلى</th><th>السبب</th><th>المسؤول</th><th>التاريخ</th></tr>
      <?php foreach ($statusLogs as $l): ?>
      <tr>
        <td><?= Helpers::e($l['full_name'] ?? '-') ?></td>
        <td><?= $statusLabel[$l['old_status']] ?? $l['old_status'] ?></td>
        <td><?= $statusLabel[$l['new_status']] ?? $l['new_status'] ?></td>
        <td><?= Helpers::e($l['reason'] ?? '-') ?></td>
        <td><?= Helpers::e($l['changed_by_name'] ?? '-') ?></td>
        <td><?= $l['created_at'] ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php else: ?>
  <div class="alert alert-info">لا توجد سجلات تغييرات حالة ملف.</div>
  <?php endif; ?>
</div>
