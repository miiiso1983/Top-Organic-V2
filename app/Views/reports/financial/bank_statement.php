<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>كشف حساب بنكي</h3>
  <form method="get" class="form-inline">
    <div class="form-row"><label>الحساب</label>
      <select name="bank_id">
        <option value="">—</option>
        <?php foreach ($banks as $b): ?><option value="<?= $b['id'] ?>" <?= $bankId == $b['id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">تحديث</button>
  </form>
</div>
<?php if ($data['account']): ?>
<div class="card">
  <h4><?= Helpers::e($data['account']['name']) ?> — الرصيد الافتتاحي: <?= Helpers::money($data['opening']) ?></h4>
  <table>
    <tr><th>التاريخ</th><th>النوع</th><th>المبلغ</th><th>المرجع</th><th>البيان</th><th>الرصيد</th></tr>
    <?php foreach ($data['lines'] as $l): ?>
    <?php $delta = in_array($l['trx_type'], ['deposit','transfer_in','check_in','interest']) ? (float)$l['amount'] : -(float)$l['amount']; ?>
    <tr>
      <td><?= $l['trx_date'] ?></td>
      <td><?= Helpers::e($l['trx_type']) ?></td>
      <td style="color:<?= $delta >= 0 ? '#16a34a' : '#c0392b' ?>"><?= Helpers::money($delta) ?></td>
      <td><?= Helpers::e($l['reference'] ?? '-') ?></td>
      <td><?= Helpers::e($l['description'] ?? '-') ?></td>
      <td><?= Helpers::money($l['balance']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>
