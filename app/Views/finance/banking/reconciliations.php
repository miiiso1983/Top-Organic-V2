<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>تسوية بنكية جديدة</h3>
  <form method="post" action="/finance/banking/reconciliations">
    <div class="form-row"><label>الحساب البنكي</label>
      <select name="bank_account_id" required>
        <?php foreach ($bankAccounts as $b): ?><option value="<?= $b['id'] ?>" <?= $bankId == $b['id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>تاريخ كشف الحساب</label><input type="date" name="statement_date" value="<?= date('Y-m-d') ?>" required></div>
    <div class="form-row"><label>رصيد كشف البنك</label><input type="number" name="statement_balance" step="0.01" required></div>
    <div class="form-row"><label>ملاحظات</label><textarea name="notes" rows="2"></textarea></div>
    <button class="btn btn-success">إنشاء</button>
  </form>
</div>

<div class="card">
  <h3>سجل التسويات</h3>
  <table>
    <tr><th>الحساب</th><th>تاريخ الكشف</th><th>رصيد البنك</th><th>رصيد النظام</th><th>الفرق</th><th>الحالة</th></tr>
    <?php foreach ($reconciliations as $r): ?>
    <tr>
      <td><?= Helpers::e($r['bank_name']) ?></td>
      <td><?= $r['statement_date'] ?></td>
      <td><?= Helpers::money($r['statement_balance']) ?></td>
      <td><?= Helpers::money($r['system_balance']) ?></td>
      <td style="color:<?= $r['difference'] != 0 ? '#c0392b' : '#16a34a' ?>"><?= Helpers::money($r['difference']) ?></td>
      <td><span class="badge badge-<?= $r['status'] === 'reconciled' ? 'success' : 'warning' ?>"><?= $r['status'] ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
