<?php use App\Core\Helpers; ?>
<div class="stats-grid">
  <div class="stat-card"><div class="stat-value" style="color:#16a34a"><?= Helpers::money($totalCash) ?></div><div class="stat-label">رصيد الصناديق</div></div>
  <div class="stat-card"><div class="stat-value" style="color:#2980b9"><?= Helpers::money($totalBank) ?></div><div class="stat-label">رصيد البنوك</div></div>
  <div class="stat-card"><div class="stat-value" style="font-weight:bold"><?= Helpers::money($totalCash + $totalBank) ?></div><div class="stat-label">إجمالي النقدية</div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>الصناديق</h3>
    <table>
      <tr><th>الصندوق</th><th>الفرع</th><th>المسؤول</th><th>الرصيد</th><th></th></tr>
      <?php foreach ($cashRegisters as $r): ?>
      <tr>
        <td><?= Helpers::e($r['name']) ?></td>
        <td><?= Helpers::e($r['branch_name'] ?? '-') ?></td>
        <td><?= Helpers::e($r['responsible_name'] ?? '-') ?></td>
        <td style="font-weight:bold"><?= Helpers::money($r['balance']) ?></td>
        <td><a href="/finance/banking/cash/<?= $r['id'] ?>" class="btn btn-sm">عرض</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <h4 style="margin-top:16px">إضافة صندوق</h4>
    <form method="post" action="/finance/banking/cash" class="form-inline">
      <div class="form-row"><label>الاسم</label><input name="name" required></div>
      <div class="form-row"><label>الفرع</label><input name="branch_id" type="number" placeholder="رقم الفرع"></div>
      <div class="form-row"><label>المسؤول</label><input name="responsible_user_id" type="number" placeholder="رقم المستخدم"></div>
      <button class="btn btn-success">إضافة</button>
    </form>
  </div>

  <div class="card">
    <h3>الحسابات البنكية</h3>
    <table>
      <tr><th>الحساب</th><th>البنك</th><th>رقم الحساب</th><th>الرصيد</th><th></th></tr>
      <?php foreach ($bankAccounts as $b): ?>
      <tr>
        <td><?= Helpers::e($b['name']) ?></td>
        <td><?= Helpers::e($b['bank_name']) ?></td>
        <td><?= Helpers::e($b['account_no']) ?></td>
        <td style="font-weight:bold"><?= Helpers::money($b['current_balance']) ?></td>
        <td><a href="/finance/banking/banks/<?= $b['id'] ?>" class="btn btn-sm">عرض</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <h4 style="margin-top:16px">إضافة حساب بنكي</h4>
    <form method="post" action="/finance/banking/banks" class="form-inline">
      <div class="form-row"><label>الاسم</label><input name="name" required></div>
      <div class="form-row"><label>البنك</label><input name="bank_name" required></div>
      <div class="form-row"><label>رقم الحساب</label><input name="account_no" required></div>
      <div class="form-row"><label>IBAN</label><input name="iban"></div>
      <div class="form-row"><label>الفرع</label><input name="branch"></div>
      <div class="form-row"><label>رصيد افتتاحي</label><input name="opening_balance" type="number" step="0.01" value="0"></div>
      <button class="btn btn-success">إضافة</button>
    </form>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <h3>روابط سريعة</h3>
  <a href="/finance/banking/transfers" class="btn">التحويلات</a>
  <a href="/finance/banking/checks" class="btn">الشيكات</a>
  <a href="/finance/banking/reconciliations" class="btn">التسويات البنكية</a>
  <a href="/finance/banking/cash-advances" class="btn">السلف النقدية</a>
  <a href="/finance/banking/custodies" class="btn">العهدة المالية</a>
</div>
