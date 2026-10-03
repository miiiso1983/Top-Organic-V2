<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>كشف صندوق</h3>
  <form method="get" class="form-inline">
    <div class="form-row"><label>الصندوق</label>
      <select name="register_id">
        <option value="">—</option>
        <?php foreach ($registers as $r): ?><option value="<?= $r['id'] ?>" <?= $registerId == $r['id'] ? 'selected' : '' ?>><?= Helpers::e($r['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">تحديث</button>
  </form>
</div>
<?php if ($data['register']): ?>
<div class="card">
  <h4><?= Helpers::e($data['register']['name']) ?> — الرصيد الافتتاحي: <?= Helpers::money($data['opening']) ?></h4>
  <table>
    <tr><th>التاريخ</th><th>النوع</th><th>المبلغ</th><th>التصنيف</th><th>المرجع</th><th>الرصيد</th></tr>
    <?php foreach ($data['lines'] as $l): ?>
    <?php $delta = $l['trx_type'] === 'in' ? (float)$l['amount'] : -(float)$l['amount']; ?>
    <tr>
      <td><?= $l['created_at'] ?></td>
      <td><?= $l['trx_type'] === 'in' ? 'قبض' : 'صرف' ?></td>
      <td style="color:<?= $delta >= 0 ? '#16a34a' : '#c0392b' ?>"><?= Helpers::money($delta) ?></td>
      <td><?= Helpers::e($l['category'] ?? '-') ?></td>
      <td><?= Helpers::e($l['reference'] ?? '-') ?></td>
      <td><?= Helpers::money($l['balance']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>
