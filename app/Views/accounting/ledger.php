<?php use App\Core\{Helpers, AccountingService}; ?>
<div class="card">
  <h3>كشف حساب</h3>
  <form method="get" action="/accounting/ledger" class="form-inline">
    <div class="form-row">
      <label>الحساب</label>
      <select name="account_id" required>
        <option value="">اختر حساب</option>
        <?php foreach ($accounts as $a): ?>
          <option value="<?= $a['id'] ?>" <?= ($account['id'] ?? null) == $a['id'] ? 'selected' : '' ?>><?= Helpers::e($a['code'] . ' ' . $a['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn btn-sm">عرض</button>
  </form>
</div>

<?php if ($account): ?>
<div class="card">
  <h3><?= Helpers::e($account['code'] . ' - ' . $account['name']) ?> — الرصيد: <?= Helpers::money(AccountingService::balance((int)$account['id'], $to)) ?></h3>
  <p>
    <a href="?account_id=<?= (int)$account['id'] ?>&from=<?= Helpers::e($from) ?>&to=<?= Helpers::e($to) ?>&format=excel" class="btn btn-sm">تصدير Excel</a>
    <a href="?account_id=<?= (int)$account['id'] ?>&from=<?= Helpers::e($from) ?>&to=<?= Helpers::e($to) ?>&format=pdf" class="btn btn-sm">تصدير PDF</a>
    <button onclick="window.print()" class="btn btn-sm">طباعة</button>
  </p>
  <table>
    <tr><th>التاريخ</th><th>رقم القيد</th><th>الوصف</th><th>مركز التكلفة</th><th>مدين</th><th>دائن</th><th>الرصيد</th></tr>
    <?php
      $running = (float)$account['opening_balance'];
      $normal = in_array($account['type'], ['asset','expense']) ? 1 : -1;
      foreach ($lines as $l):
        $running += $normal * ((float)$l['debit'] - (float)$l['credit']);
    ?>
    <tr>
      <td><?= Helpers::e($l['entry_date']) ?></td>
      <td><?= Helpers::e($l['entry_no']) ?></td>
      <td><?= Helpers::e($l['description']) ?></td>
      <td><?= Helpers::e($l['cost_center_name'] ?? '-') ?></td>
      <td><?= Helpers::money($l['debit']) ?></td>
      <td><?= Helpers::money($l['credit']) ?></td>
      <td><?= Helpers::money($running) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>
