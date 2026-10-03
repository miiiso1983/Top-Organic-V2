<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>عهدة مالية جديدة</h3>
  <form method="post" action="/finance/banking/custodies">
    <div class="form-row"><label>الموظف</label>
      <select name="employee_id" required>
        <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= Helpers::e($e['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>المبلغ</label><input type="number" name="amount" step="0.01" required></div>
    <div class="form-row"><label>التاريخ</label><input type="date" name="custody_date" value="<?= date('Y-m-d') ?>"></div>
    <div class="form-row"><label>الغرض</label><input name="purpose"></div>
    <div class="form-row"><label>الصندوق</label>
      <select name="cash_register_id">
        <option value="">—</option>
        <?php foreach ($cashRegisters as $r): ?><option value="<?= $r['id'] ?>"><?= Helpers::e($r['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الحساب البنكي</label>
      <select name="bank_account_id">
        <option value="">—</option>
        <?php foreach ($bankAccounts as $b): ?><option value="<?= $b['id'] ?>"><?= Helpers::e($b['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-success">إصدار</button>
  </form>
</div>

<div class="card">
  <h3>سجل العهد</h3>
  <table>
    <tr><th>الموظف</th><th>المبلغ</th><th>مصروف</th><th>مردود</th><th>الحالة</th><th></th></tr>
    <?php foreach ($custodies as $c): ?>
    <tr>
      <td><?= Helpers::e($c['employee_name']) ?></td>
      <td><?= Helpers::money($c['amount']) ?></td>
      <td><?= Helpers::money($c['spent_amount']) ?></td>
      <td><?= Helpers::money($c['returned_amount']) ?></td>
      <td><span class="badge badge-<?= $c['status'] === 'settled' ? 'success' : ($c['status'] === 'active' ? 'info' : 'warning') ?>"><?= Helpers::e($c['status']) ?></span></td>
      <td>
        <?php if ($c['status'] === 'active'): ?>
        <form method="post" action="/finance/banking/custodies/<?= $c['id'] ?>/expenses" style="display:inline">
          <input type="number" name="amount" step="0.01" placeholder="مصروف" style="width:80px">
          <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>">
          <input name="category" placeholder="تصنيف" style="width:80px">
          <input name="description" placeholder="بيان" style="width:100px">
          <button class="btn btn-sm btn-warning">مصروف</button>
        </form>
        <form method="post" action="/finance/banking/custodies/<?= $c['id'] ?>/return" style="display:inline">
          <input type="number" name="amount" step="0.01" placeholder="مردود" style="width:80px">
          <input type="date" name="date" value="<?= date('Y-m-d') ?>">
          <button class="btn btn-sm btn-success">رد</button>
        </form>
        <form method="post" action="/finance/banking/custodies/<?= $c['id'] ?>/settle" style="display:inline"><button class="btn btn-sm btn-danger">إقفال</button></form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
