<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>الشيكات <?= $type === 'incoming' ? 'الواردة' : 'الصادرة' ?></h3>
  <a href="?type=incoming" class="btn <?= $type === 'incoming' ? 'btn-success' : '' ?>">واردة</a>
  <a href="?type=outgoing" class="btn <?= $type === 'outgoing' ? 'btn-success' : '' ?>">صادرة</a>
</div>

<div class="card" style="margin-bottom:16px">
  <h3>تسجيل شيك <?= $type === 'incoming' ? 'واردة' : 'صادر' ?></h3>
  <form method="post" action="/finance/banking/checks">
    <input type="hidden" name="check_type" value="<?= $type ?>">
    <div class="form-row"><label>رقم الشيك</label><input name="check_no" required></div>
    <div class="form-row"><label>الطرف</label>
      <select name="party_type">
        <option value="">غير محدد</option>
        <option value="customer">عميل</option>
        <option value="supplier">مورد</option>
        <option value="employee">موظف</option>
        <option value="other">أخرى</option>
      </select>
      <input type="number" name="party_id" placeholder="رقم الطرف">
    </div>
    <div class="form-row"><label>الحساب البنكي</label>
      <select name="bank_account_id">
        <option value="">—</option>
        <?php foreach ($bankAccounts as $b): ?><option value="<?= $b['id'] ?>"><?= Helpers::e($b['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>المبلغ</label><input type="number" name="amount" step="0.01" required></div>
    <div class="form-row"><label>تاريخ الشيك</label><input type="date" name="check_date" value="<?= date('Y-m-d') ?>"></div>
    <div class="form-row"><label>تاريخ الاستحقاق</label><input type="date" name="due_date"></div>
    <div class="form-row"><label>المرجع</label><input name="reference"></div>
    <div class="form-row"><label>البيان</label><input name="description"></div>
    <button class="btn btn-success">تسجيل</button>
  </form>
</div>

<div class="card">
  <h3>سجل الشيكات</h3>
  <table>
    <tr><th>رقم الشيك</th><th>المبلغ</th><th>التاريخ</th><th>الاستحقاق</th><th>الحالة</th><th>البنك</th><th></th></tr>
    <?php foreach ($checks as $c): ?>
    <tr>
      <td><?= Helpers::e($c['check_no']) ?></td>
      <td><?= Helpers::money($c['amount']) ?></td>
      <td><?= $c['check_date'] ?></td>
      <td><?= $c['due_date'] ?? '-' ?></td>
      <td><span class="badge badge-<?= $c['status'] === 'cleared' ? 'success' : ($c['status'] === 'bounced' ? 'danger' : 'warning') ?>"><?= Helpers::e($c['status']) ?></span></td>
      <td><?= Helpers::e($c['bank_name'] ?? '-') ?></td>
      <td>
        <?php if ($c['status'] === 'pending'): ?>
        <form method="post" action="/finance/banking/checks/<?= $c['id'] ?>/clear" style="display:inline">
          <input type="hidden" name="type" value="<?= $type ?>">
          <input type="hidden" name="date" value="<?= date('Y-m-d') ?>">
          <button class="btn btn-sm btn-success">تحصيل</button>
        </form>
        <form method="post" action="/finance/banking/checks/<?= $c['id'] ?>/bounce" style="display:inline">
          <input type="hidden" name="type" value="<?= $type ?>">
          <input type="hidden" name="date" value="<?= date('Y-m-d') ?>">
          <button class="btn btn-sm btn-danger">ارتداد</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
