<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>ميزان المراجعة التفصيلي</h3>
  <form method="get" class="form-inline">
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">تحديث</button>
  </form>
</div>
<div class="card">
  <h4>أرصدة الحسابات</h4>
  <table>
    <tr><th>الكود</th><th>الحساب</th><th>النوع</th><th>مدين</th><th>دائن</th><th>الرصيد</th></tr>
    <?php foreach ($data['accounts'] as $a): ?>
    <tr>
      <td><?= Helpers::e($a['code']) ?></td>
      <td><?= Helpers::e($a['name']) ?></td>
      <td><?= Helpers::e($a['type']) ?></td>
      <td><?= Helpers::money($a['debit']) ?></td>
      <td><?= Helpers::money($a['credit']) ?></td>
      <td><?= Helpers::money($a['balance']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<div class="card" style="margin-top:16px">
  <h4>القيود التفصيلية</h4>
  <table>
    <tr><th>الكود</th><th>الحساب</th><th>التاريخ</th><th>الرقم</th><th>البيان</th><th>مدين</th><th>دائن</th></tr>
    <?php foreach ($data['lines'] as $l): ?>
    <tr>
      <td><?= Helpers::e($l['code']) ?></td>
      <td><?= Helpers::e($l['name']) ?></td>
      <td><?= $l['entry_date'] ?></td>
      <td><?= Helpers::e($l['entry_no']) ?></td>
      <td><?= Helpers::e($l['description'] ?? '-') ?></td>
      <td><?= Helpers::money($l['debit']) ?></td>
      <td><?= Helpers::money($l['credit']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
