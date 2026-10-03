<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>سلفة نقدية جديدة</h3>
  <form method="post" action="/finance/banking/cash-advances">
    <div class="form-row"><label>الموظف</label>
      <select name="employee_id" required>
        <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= Helpers::e($e['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>المبلغ</label><input type="number" name="amount" step="0.01" required></div>
    <div class="form-row"><label>التاريخ</label><input type="date" name="advance_date" value="<?= date('Y-m-d') ?>"></div>
    <div class="form-row"><label>الغرض</label><input name="purpose"></div>
    <div class="form-row"><label>طريقة الدفع</label>
      <select name="payment_method"><option value="cash">نقد</option><option value="bank">بنك</option></select>
    </div>
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
    <button class="btn btn-success">إنشاء</button>
  </form>
</div>

<div class="card">
  <h3>سجل السلف</h3>
  <table>
    <tr><th>الموظف</th><th>المبلغ</th><th>المسدد</th><th>التاريخ</th><th>الحالة</th><th></th></tr>
    <?php foreach ($advances as $a): ?>
    <tr>
      <td><?= Helpers::e($a['employee_name']) ?></td>
      <td><?= Helpers::money($a['amount']) ?></td>
      <td><?= Helpers::money($a['settled_amount']) ?></td>
      <td><?= $a['advance_date'] ?></td>
      <td><span class="badge badge-<?= $a['status'] === 'settled' ? 'success' : ($a['status'] === 'approved' ? 'info' : 'warning') ?>"><?= Helpers::e($a['status']) ?></span></td>
      <td>
        <?php if ($a['status'] === 'pending'): ?>
        <form method="post" action="/finance/banking/cash-advances/<?= $a['id'] ?>/approve" style="display:inline"><button class="btn btn-sm btn-success">اعتماد</button></form>
        <?php elseif ($a['status'] !== 'settled' && $a['status'] !== 'cancelled'): ?>
        <form method="post" action="/finance/banking/cash-advances/<?= $a['id'] ?>/settle" style="display:inline">
          <input type="number" name="amount" step="0.01" placeholder="مبلغ التسوية" style="width:100px">
          <input type="date" name="date" value="<?= date('Y-m-d') ?>">
          <button class="btn btn-sm btn-warning">تسوية</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
