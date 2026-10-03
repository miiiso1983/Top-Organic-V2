<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h2>تنبيهات الصيانة</h2>
  <form method="get" class="form-inline">
    <label>عرض الأصول التي تحتاج صيانة خلال (يوم)</label>
    <input type="number" name="days" value="<?= (int)$days ?>" min="1" max="365">
    <button class="btn btn-secondary">تحديث</button>
  </form>
</div>
<div class="card">
  <table>
    <tr><th>الرقم</th><th>الاسم</th><th>الموقع</th><th>آخر صيانة</th><th>التاريخ القادم</th><th>المزود</th><th>التكلفة</th><th>الحالة</th></tr>
    <?php foreach ($rows as $r): ?>
    <?php $isOverdue = $r['next_due_date'] && $r['next_due_date'] < $today; ?>
    <?php $isMissing = !$r['next_due_date']; ?>
    <tr>
      <td><?= Helpers::e($r['asset_no']) ?></td>
      <td><a href="/accounting/fixed-assets/<?= $r['id'] ?>"><?= Helpers::e($r['name']) ?></a></td>
      <td><?= Helpers::e($r['location'] ?? '') ?></td>
      <td><?= $r['maintenance_date'] ?? '-' ?></td>
      <td><?= $r['next_due_date'] ?? 'غير محدد' ?></td>
      <td><?= Helpers::e($r['provider'] ?? '') ?></td>
      <td><?= Helpers::money($r['cost'] ?? 0) ?></td>
      <td>
        <?php if ($isOverdue): ?><span class="badge badge-danger">متأخر</span>
        <?php elseif ($isMissing): ?><span class="badge badge-warning">لم تُسجّل</span>
        <?php else: ?><span class="badge badge-info">قريب</span><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
