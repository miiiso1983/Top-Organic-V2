<?php use App\Core\Helpers; ?>
<?php
$statusLabels = ['active' => 'مفتوحة', 'returned' => 'مرجعة', 'damaged' => 'تالفة', 'lost' => 'فقدت'];
$statusBadge = ['active' => 'warning', 'returned' => 'success', 'damaged' => 'danger', 'lost' => 'danger'];
$typeLabels = ['fixed' => 'ثابتة', 'consumable' => 'استهلاكية'];
$typeBadge = ['fixed' => 'secondary', 'consumable' => 'info'];
$hasOutstanding = false;
foreach ($custody as $c) { if ($c['status'] === 'active') { $hasOutstanding = true; break; } }
?>
<div class="card">
  <h3>عهد الموظف: <?= Helpers::e($employee['full_name']) ?> (<?= Helpers::e($employee['emp_code']) ?>)</h3>
  <p>
    <a href="/hr/employees/<?= $employee['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-arrow-right"></i> الملف الشخصي</a>
    <a href="/hr/employees" class="btn btn-sm btn-secondary"><i class="fa-solid fa-users"></i> الموظفون</a>
  </p>
  <p>الحالة: <span class="badge badge-<?= $employee['file_status'] === 'open' ? 'success' : 'danger' ?>"><?= $employee['file_status'] === 'open' ? 'مفتوح' : 'مغلق' ?></span></p>
  <?php if ($hasOutstanding): ?>
    <div class="alert alert-warning">يوجد عهد مفتوحة. لا يمكن إغلاق الملف قبل إرجاعها أو تسويتها.</div>
  <?php else: ?>
    <div class="alert alert-success">أرشيف العهد مصفى — يمكن إخلاء الطرف.</div>
  <?php endif; ?>
</div>

<div class="card">
  <h3>سجل العهد</h3>
  <table>
    <tr><th>القطعة</th><th>الرقم التسلسلي</th><th>النوع</th><th>الكمية</th><th>القيمة</th><th>الإجمالي</th><th>الحالة</th><th>تاريخ الإرجاع</th><th>تاريخ الانتهاء</th><th></th></tr>
    <?php foreach ($custody as $c):
      $rowClass = '';
      if ($c['status'] === 'active' && $c['type'] === 'consumable' && isset($c['days_to_expiry']) && $c['days_to_expiry'] !== null && $c['days_to_expiry'] <= 15) {
          $rowClass = ($c['days_to_expiry'] < 0) ? 'row-expired' : (($c['days_to_expiry'] <= 7) ? 'row-urgent' : 'row-warning');
      }
    ?>
    <tr class="<?= $rowClass ?>">
      <td><?= Helpers::e($c['item_name']) ?></td>
      <td><?= Helpers::e($c['serial_number'] ?? '-') ?></td>
      <td><span class="badge badge-<?= $typeBadge[$c['type']] ?>"><?= $typeLabels[$c['type']] ?></span></td>
      <td><?= $c['qty'] ?></td>
      <td><?= Helpers::money($c['unit_cost']) ?></td>
      <td><?= Helpers::money($c['total_value']) ?></td>
      <td><span class="badge badge-<?= $statusBadge[$c['status']] ?>"><?= $statusLabels[$c['status']] ?></span></td>
      <td><?= $c['due_date'] ? date('Y-m-d', strtotime($c['due_date'])) : '-' ?></td>
      <td>
        <?php if ($c['expiry_date']): ?>
          <span class="expiry-badge <?= $rowClass ? 'expiry-danger' : '' ?>">
            <?= date('Y-m-d', strtotime($c['expiry_date'])) ?>
            <?php if ($c['days_to_expiry'] !== null): ?>
              (<?= $c['days_to_expiry'] < 0 ? 'منتهية ' . abs($c['days_to_expiry']) . ' يوم' : $c['days_to_expiry'] . ' يوم' ?>)
            <?php endif; ?>
          </span>
        <?php else: ?>
          -
        <?php endif; ?>
      </td>
      <td>
        <?php if ($c['status'] === 'active' && (\App\Core\Auth::can('hr.custody.manage') || \App\Core\Auth::can('hr.custody.clearance'))): ?>
        <form method="post" action="/hr/custody/<?= $c['id'] ?>/return" style="display:inline">
          <input type="hidden" name="status" value="returned">
          <button class="btn btn-sm btn-success" onclick="return confirm('تأكيد إرجاع العهدة؟')">إرجاع</button>
        </form>
        <form method="post" action="/hr/custody/<?= $c['id'] ?>/return" style="display:inline">
          <input type="hidden" name="status" value="damaged">
          <button class="btn btn-sm btn-warning">تالف</button>
        </form>
        <form method="post" action="/hr/custody/<?= $c['id'] ?>/return" style="display:inline">
          <input type="hidden" name="status" value="lost">
          <button class="btn btn-sm btn-danger">فقد</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$custody): ?><tr><td colspan="10">لا توجد سجلات عهد لهذا الموظف</td></tr><?php endif; ?>
  </table>
</div>

<style>
.row-warning { background-color: #fff8e1; }
.row-urgent { background-color: #ffebee; }
.row-expired { background-color: #f8bbd0; }
.expiry-badge { display:inline-block; padding:2px 6px; border-radius:4px; font-size:12px; }
.expiry-danger { background:#dc3545; color:#fff; }
</style>

<div class="card" style="text-align:center">
  <a href="/hr/custody" class="btn btn-primary">رجوع للعهد</a>
  <a href="/hr/employees" class="btn btn-secondary">رجوع للموظفين</a>
</div>
