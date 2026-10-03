<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h2>صندوق: <?= Helpers::e($register['name']) ?></h2>
  <p>الرصيد الحالي: <strong style="font-size:22px; color:#16a34a"><?= Helpers::money($register['balance']) ?></strong></p>
</div>

<div class="card" style="margin-bottom:16px">
  <h3>تعديل الصندوق</h3>
  <form method="post" action="/finance/banking/cash/<?= $register['id'] ?>/edit" class="form-inline">
    <div class="form-row"><label>الاسم</label><input name="name" value="<?= Helpers::e($register['name']) ?>" required></div>
    <div class="form-row"><label>الفرع</label><input name="branch_id" type="number" value="<?= (int)$register['branch_id'] ?>"></div>
    <div class="form-row"><label>المسؤول</label><input name="responsible_user_id" type="number" value="<?= (int)$register['responsible_user_id'] ?>"></div>
    <div class="form-row"><label>نشط</label><select name="is_active"><option value="1" <?= $register['is_active'] ? 'selected' : '' ?>>نعم</option><option value="0" <?= !$register['is_active'] ? 'selected' : '' ?>>لا</option></select></div>
    <button class="btn btn-warning">تحديث</button>
  </form>
</div>

<div class="card">
  <h3>حركات الصندوق</h3>
  <table>
    <tr><th>النوع</th><th>المبلغ</th><th>التصنيف</th><th>المرجع</th><th>البيان</th><th>بواسطة</th><th>الوقت</th></tr>
    <?php foreach ($transactions as $t): ?>
    <tr>
      <td><span class="badge badge-<?= $t['trx_type'] === 'in' ? 'success' : 'danger' ?>"><?= $t['trx_type'] === 'in' ? 'قبض' : 'صرف' ?></span></td>
      <td><?= Helpers::money($t['amount']) ?></td>
      <td><?= Helpers::e($t['category'] ?? '-') ?></td>
      <td><?= Helpers::e($t['reference'] ?? '-') ?></td>
      <td><?= Helpers::e($t['description'] ?? '-') ?></td>
      <td><?= Helpers::e($t['full_name'] ?? '-') ?></td>
      <td><?= $t['created_at'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
