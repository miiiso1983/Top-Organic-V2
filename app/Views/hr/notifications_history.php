<?php use App\Core\Helpers; ?>
<?php $targetLabels = ['all'=>'الجميع','level'=>'مستوى إداري','role'=>'دور','branch'=>'فرع','department'=>'قسم','user'=>'موظف']; ?>
<?php $q = $q ?? ''; ?>

<div class="card">
  <h3>سجل التعميمات والتبليغات</h3>
  <form method="get" action="/hr/notifications-history" class="form-inline" style="margin-bottom:12px">
    <div class="form-row"><input type="text" name="q" value="<?= Helpers::e($q) ?>" placeholder="بحث بعنوان التعميم..."></div>
    <button class="btn btn-sm btn-primary">بحث</button>
    <?php if ($q !== ''): ?><a href="/hr/notifications-history" class="btn btn-sm btn-light">إلغاء البحث</a><?php endif; ?>
  </form>
  <table>
    <tr><th>العنوان</th><th>الفئة المستهدفة</th><th>المرسل</th><th>التاريخ</th><th>عدد المستلمين</th><th>عدد المشاهدات</th><th></th></tr>
    <?php foreach ($items as $n): ?>
    <tr id="row-<?= $n['id'] ?>">
      <td><?= Helpers::e($n['title']) ?></td>
      <td><?= $targetLabels[$n['target_type']] ?? $n['target_type'] ?></td>
      <td><?= Helpers::e($n['sender_name'] ?? '-') ?></td>
      <td><?= $n['created_at'] ? date('Y-m-d H:i', strtotime($n['created_at'])) : '-' ?></td>
      <td><?= (int)($n['recipient_count'] ?? 0) ?></td>
      <td><?= (int)($n['read_count'] ?? 0) ?></td>
      <td><a href="/hr/notifications-history/<?= $n['id'] ?>?page=<?= $page ?>&q=<?= urlencode($q) ?>" class="btn btn-sm btn-light">التفاصيل</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="7">لا توجد تبليغات</td></tr><?php endif; ?>
  </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="card" style="text-align:center">
  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
  <a href="/hr/notifications-history?page=<?= $p ?>&q=<?= urlencode($q) ?>" class="btn btn-sm <?= $p == $page ? 'btn-primary' : 'btn-light' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
