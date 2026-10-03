<?php use App\Core\Helpers; ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h3>حضور اليوم (<?= date('Y-m-d') ?>)</h3>
    <div>
      <a href="?format=excel" class="btn btn-sm">تصدير Excel</a>
      <a href="?format=pdf" class="btn btn-sm">تصدير PDF</a>
      <button type="button" class="btn btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i> طباعة</button>
    </div>
  </div>
  <table>
    <tr><th>الموظف</th><th>القسم</th><th>أول حضور</th><th>آخر انصراف</th><th>الحالة</th></tr>
    <?php foreach ($today as $t): ?>
    <tr>
      <td><?= Helpers::e($t['full_name']) ?></td>
      <td><?= Helpers::e($t['department'] ?? '-') ?></td>
      <td><?= $t['first_in'] ? date('H:i', strtotime($t['first_in'])) : '-' ?></td>
      <td><?= $t['last_out'] ? date('H:i', strtotime($t['last_out'])) : '-' ?></td>
      <td>
        <?php if ($t['first_in']): ?>
          <span class="badge badge-success">حاضر</span>
        <?php else: ?>
          <span class="badge badge-danger">غائب</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$today): ?><tr><td colspan="5">لا يوجد موظفون</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h3>إجازات هذا الشهر المعتمدة</h3>
  <table>
    <tr><th>الموظف</th><th>من</th><th>إلى</th><th>السبب</th></tr>
    <?php foreach ($leavesThisMonth as $l): ?>
    <tr>
      <td><?= Helpers::e($l['full_name']) ?></td>
      <td><?= $l['date_from'] ?></td>
      <td><?= $l['date_to'] ?></td>
      <td><?= Helpers::e($l['reason'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$leavesThisMonth): ?><tr><td colspan="4">لا توجد إجازات هذا الشهر</td></tr><?php endif; ?>
  </table>
</div>
