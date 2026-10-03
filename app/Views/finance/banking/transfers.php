<?php use App\Core\Helpers; ?>
<?php
$accountName = function($type, $id) use ($cashRegisters, $bankAccounts) {
    if ($type === 'cash') {
        foreach ($cashRegisters as $r) { if ((int)$r['id'] === (int)$id) return $r['name']; }
    } else {
        foreach ($bankAccounts as $b) { if ((int)$b['id'] === (int)$id) return $b['name']; }
    }
    return '-';
};
?>
<div class="card" style="margin-bottom:16px">
  <h3>تحويل جديد</h3>
  <form method="post" action="/finance/banking/transfers" class="form-inline">
    <div class="form-row"><label>من</label>
      <select name="from_type"><option value="cash">صندوق</option><option value="bank">بنك</option></select>
      <select name="from_id">
        <optgroup label="الصناديق"><?php foreach ($cashRegisters as $r): ?><option value="<?= $r['id'] ?>"><?= Helpers::e($r['name']) ?></option><?php endforeach; ?></optgroup>
        <optgroup label="البنوك"><?php foreach ($bankAccounts as $b): ?><option value="<?= $b['id'] ?>"><?= Helpers::e($b['name']) ?></option><?php endforeach; ?></optgroup>
      </select>
    </div>
    <div class="form-row"><label>إلى</label>
      <select name="to_type"><option value="cash">صندوق</option><option value="bank">بنك</option></select>
      <select name="to_id">
        <optgroup label="الصناديق"><?php foreach ($cashRegisters as $r): ?><option value="<?= $r['id'] ?>"><?= Helpers::e($r['name']) ?></option><?php endforeach; ?></optgroup>
        <optgroup label="البنوك"><?php foreach ($bankAccounts as $b): ?><option value="<?= $b['id'] ?>"><?= Helpers::e($b['name']) ?></option><?php endforeach; ?></optgroup>
      </select>
    </div>
    <div class="form-row"><label>المبلغ</label><input type="number" name="amount" step="0.01" required></div>
    <div class="form-row"><label>التاريخ</label><input type="date" name="transfer_date" value="<?= date('Y-m-d') ?>"></div>
    <div class="form-row"><label>المرجع</label><input name="reference"></div>
    <div class="form-row"><label>البيان</label><input name="description"></div>
    <button class="btn btn-success">تحويل</button>
  </form>
</div>

<div class="card">
  <h3>سجل التحويلات</h3>
  <table>
    <tr><th>التاريخ</th><th>من</th><th>إلى</th><th>المبلغ</th><th>المرجع</th><th>الحالة</th><th>بواسطة</th></tr>
    <?php foreach ($transfers as $t): ?>
    <tr>
      <td><?= $t['transfer_date'] ?></td>
      <td><?= $accountName($t['from_type'], $t['from_id']) ?> (<?= $t['from_type'] ?>)</td>
      <td><?= $accountName($t['to_type'], $t['to_id']) ?> (<?= $t['to_type'] ?>)</td>
      <td><?= Helpers::money($t['amount']) ?></td>
      <td><?= Helpers::e($t['reference'] ?? '-') ?></td>
      <td><?= Helpers::e($t['status']) ?></td>
      <td><?= Helpers::e($t['full_name'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
