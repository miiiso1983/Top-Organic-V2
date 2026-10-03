<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>الأستاذ العام</h3>
  <form method="get" class="form-inline">
    <div class="form-row"><label>الحساب</label>
      <select name="account_id">
        <option value="">—</option>
        <?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>" <?= $accountId == $a['id'] ? 'selected' : '' ?>><?= Helpers::e($a['code'] . ' ' . $a['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">تحديث</button>
  </form>
</div>
<?php if ($data['account']): ?>
<div class="card">
  <h4><?= Helpers::e($data['account']['code'] . ' ' . $data['account']['name']) ?> — الرصيد الافتتاحي: <?= Helpers::money($data['opening']) ?></h4>
  <table>
    <tr><th>التاريخ</th><th>الرقم</th><th>البيان</th><th>مدين</th><th>دائن</th><th>الرصيد</th></tr>
    <?php foreach ($data['lines'] as $l): ?>
    <tr>
      <td><?= $l['entry_date'] ?></td>
      <td><?= Helpers::e($l['entry_no']) ?></td>
      <td><?= Helpers::e($l['description'] ?? '-') ?></td>
      <td><?= Helpers::money($l['debit']) ?></td>
      <td><?= Helpers::money($l['credit']) ?></td>
      <td><?= Helpers::money($l['balance']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>
