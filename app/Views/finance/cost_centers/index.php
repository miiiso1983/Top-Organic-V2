<?php use App\Core\Helpers; ?>
<?php
function renderTree(array $nodes, int $level = 0): void {
    foreach ($nodes as $n): ?>
    <tr>
      <td style="padding-left:<?= ($level * 24) + 8 ?>px"><?= str_repeat('— ', $level) . Helpers::e($n['name']) ?></td>
      <td><?= Helpers::e($n['code'] ?? '-') ?></td>
      <td><?= Helpers::e($n['manager_name'] ?? '-') ?></td>
      <td><span class="badge badge-<?= $n['is_active'] ? 'success' : 'danger' ?>"><?= $n['is_active'] ? 'نشط' : 'معطل' ?></span></td>
      <td>
        <a href="/finance/cost-centers/<?= $n['id'] ?>/edit" class="btn btn-sm btn-warning">تعديل</a>
        <a href="/finance/cost-centers/<?= $n['id'] ?>/report" class="btn btn-sm">تقرير</a>
        <form method="post" action="/finance/cost-centers/<?= $n['id'] ?>/delete" style="display:inline" onsubmit="return confirm('هل أنت متأكد؟')"><button class="btn btn-sm btn-danger">حذف</button></form>
      </td>
    </tr>
    <?php if (!empty($n['children'])) renderTree($n['children'], $level + 1); ?>
    <?php endforeach;
}
?>
<div class="grid grid-2">
  <div class="card">
    <h3>شجرة مراكز الكلفة</h3>
    <table>
      <tr><th>المركز</th><th>الكود</th><th>المدير</th><th>الحالة</th><th></th></tr>
      <?php renderTree($tree); ?>
      <?php if (!$tree): ?><tr><td colspan="5">لا توجد مراكز كلفة</td></tr><?php endif; ?>
    </table>
  </div>

  <div class="card">
    <h3>إضافة مركز كلفة</h3>
    <form method="post" action="/finance/cost-centers">
      <div class="form-row"><label>الاسم</label><input name="name" required></div>
      <div class="form-row"><label>الكود</label><input name="code"></div>
      <div class="form-row"><label>الوصف</label><textarea name="description" rows="2"></textarea></div>
      <div class="form-row"><label>الأب</label>
        <select name="parent_id">
          <option value="">—</option>
          <?php foreach ($costCenters as $c): ?><option value="<?= $c['id'] ?>"><?= Helpers::e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>المدير</label>
        <select name="manager_id">
          <option value="">—</option>
          <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= Helpers::e($u['full_name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-success">إضافة</button>
    </form>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <h3>روابط سريعة</h3>
  <a href="/finance/cost-centers/allocations" class="btn">توزيع المصروفات</a>
</div>
