<?php use App\Core\Helpers; ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h3 style="margin:0"><i class="fa-solid fa-screwdriver-wrench"></i> طلبات الصيانة</h3>
    <a href="/maintenance/create" class="btn btn-success"><i class="fa-solid fa-plus"></i> طلب صيانة جديد</a>
  </div>
</div>
<div class="card" style="margin-top:16px">
  <table class="table">
    <thead>
      <tr>
        <th>الرقم</th><th>العطل</th><th>الجهاز</th><th>الأهمية</th><th>صاحب الطلب</th><th>الحالة</th><th>المعين</th><th>التاريخ</th><th>الإجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= Helpers::e($r['request_number']) ?></td>
        <td><?= Helpers::e($r['title']) ?></td>
        <td><?= Helpers::e($r['device_name']) ?></td>
        <td><?= Helpers::e($priorities[$r['priority']] ?? $r['priority'] ?? '-') ?></td>
        <td><?= Helpers::e($r['requester_name'] ?? '-') ?></td>
        <td><span class="badge badge-<?= statusClass($r['status']) ?>"><?= $statuses[$r['status']] ?? $r['status'] ?></span></td>
        <td><?= Helpers::e($r['assigned_name'] ?? '-') ?></td>
        <td><?= Helpers::e($r['created_at'] ?? '-') ?></td>
        <td><a href="/maintenance/<?= $r['id'] ?>" class="btn btn-sm btn-primary">عرض</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="9" class="text-center">لا توجد طلبات</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php
function statusClass(string $s): string {
    return [
        'pending' => 'warning', 'assigned' => 'info', 'in_progress' => 'primary',
        'completed' => 'success', 'confirmed' => 'success', 'cancelled' => 'danger',
    ][$s] ?? 'secondary';
}
?>

