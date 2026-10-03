<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>الأستاذ المساعد</h3>
  <form method="get" class="form-inline">
    <div class="form-row"><label>نوع الطرف</label>
      <select name="party_type"><option value="customer" <?= $type === 'customer' ? 'selected' : '' ?>>عميل</option><option value="supplier" <?= $type === 'supplier' ? 'selected' : '' ?>>مورد</option></select>
    </div>
    <div class="form-row"><label>الطرف</label>
      <select name="party_id">
        <option value="">—</option>
        <?php foreach ($parties as $p): ?><option value="<?= $p['id'] ?>" <?= $partyId == $p['id'] ? 'selected' : '' ?>><?= Helpers::e($p['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">تحديث</button>
  </form>
</div>
<?php if ($data['party']): ?>
<div class="card">
  <h4><?= Helpers::e($data['party']['name']) ?></h4>
  <table>
    <tr><th>التاريخ</th><th>النوع</th><th>المبلغ</th></tr>
    <?php foreach ($data['lines'] as $l): ?>
    <tr>
      <td><?= $l['date'] ?></td>
      <td><?= Helpers::e($l['type']) ?></td>
      <td><?= Helpers::money($l['amount']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>
