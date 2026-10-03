<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>ميزان المراجعة</h3>
  <form method="get" class="form-inline">
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">تحديث</button>
    <a href="?from=<?= $from ?>&to=<?= $to ?>&format=excel" class="btn btn-success">Excel</a>
  </form>
</div>
<div class="card">
  <table>
    <tr><th>الكود</th><th>الحساب</th><th>النوع</th><th>مدين</th><th>دائن</th><th>الرصيد</th></tr>
    <?php foreach ($accounts as $a): ?>
    <tr>
      <td><?= Helpers::e($a['code']) ?></td>
      <td><?= Helpers::e($a['name']) ?></td>
      <td><?= Helpers::e($a['type']) ?></td>
      <td><?= Helpers::money($a['debit']) ?></td>
      <td><?= Helpers::money($a['credit']) ?></td>
      <td><?= Helpers::money($a['balance']) ?> <?= $a['balance'] >= 0 ? 'مدين' : 'دائن' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
