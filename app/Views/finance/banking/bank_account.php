<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h2><?= Helpers::e($account['name']) ?> — <?= Helpers::e($account['bank_name']) ?></h2>
  <p>رقم الحساب: <?= Helpers::e($account['account_no']) ?> | الرصيد الحالي: <strong style="font-size:22px; color:#2980b9"><?= Helpers::money($account['current_balance']) ?></strong></p>
</div>

<div class="grid grid-2" style="margin-bottom:16px">
  <div class="card">
    <h3>إيداع</h3>
    <form method="post" action="/finance/banking/deposits">
      <input type="hidden" name="bank_account_id" value="<?= $account['id'] ?>">
      <div class="form-row"><label>المبلغ</label><input type="number" name="amount" step="0.01" required></div>
      <div class="form-row"><label>التاريخ</label><input type="date" name="date" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-row"><label>المرجع</label><input name="reference"></div>
      <div class="form-row"><label>البيان</label><input name="description"></div>
      <button class="btn btn-success">إيداع</button>
    </form>
  </div>
  <div class="card">
    <h3>سحب</h3>
    <form method="post" action="/finance/banking/withdrawals">
      <input type="hidden" name="bank_account_id" value="<?= $account['id'] ?>">
      <div class="form-row"><label>المبلغ</label><input type="number" name="amount" step="0.01" required></div>
      <div class="form-row"><label>التاريخ</label><input type="date" name="date" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-row"><label>المرجع</label><input name="reference"></div>
      <div class="form-row"><label>البيان</label><input name="description"></div>
      <button class="btn btn-danger">سحب</button>
    </form>
  </div>
</div>

<div class="card">
  <h3>حركات الحساب</h3>
  <table>
    <tr><th>النوع</th><th>المبلغ</th><th>المرجع</th><th>البيان</th><th>التاريخ</th><th>بواسطة</th></tr>
    <?php foreach ($transactions as $t): ?>
    <?php $typeLabels = ['deposit'=>'إيداع','withdrawal'=>'سحب','transfer_in'=>'تحويل وارد','transfer_out'=>'تحويل صادر','check_in'=>'شيك وارد','check_out'=>'شيك صادر','fee'=>'رسوم','interest'=>'فائدة']; ?>
    <tr>
      <td><span class="badge badge-info"><?= $typeLabels[$t['trx_type']] ?? $t['trx_type'] ?></span></td>
      <td><?= Helpers::money($t['amount']) ?></td>
      <td><?= Helpers::e($t['reference'] ?? '-') ?></td>
      <td><?= Helpers::e($t['description'] ?? '-') ?></td>
      <td><?= $t['trx_date'] ?></td>
      <td><?= Helpers::e($t['full_name'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
