<?php use App\Core\Helpers; ?>
<?php $statusLabels = ['pending'=>'بانتظار الموافقة','approved'=>'معتمد','rejected'=>'مرفوض','paid'=>'مدفوع']; ?>
<div class="stats-grid">
  <div class="stat-card"><div class="stat-value"><?= Helpers::money($monthTotal['total']) ?></div><div class="stat-label">مصروفات الشهر المعتمدة</div></div>
</div>

<div class="card">
  <h3>تسجيل مصروف (يخضع لمسار الموافقات)</h3>
  <form method="post" action="/finance/expenses" class="form-inline">
    <div class="form-row"><label>التصنيف</label><input name="category" placeholder="كهرباء، صيانة، إيجار..." required></div>
    <div class="form-row"><label>المبلغ</label><input type="number" name="amount" step="0.01" min="0.01" required></div>
    <div class="form-row"><label>مركز الكلفة</label>
      <select name="cost_center_id">
        <option value="">—</option>
        <?php foreach ($costCenters as $cc): ?><option value="<?= $cc['id'] ?>"><?= Helpers::e($cc['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>التاريخ</label><input type="date" name="expense_date" value="<?= date('Y-m-d') ?>"></div>
    <div class="form-row"><label>البيان</label><input name="description"></div>
    <button class="btn btn-success">تسجيل وإرسال للموافقة</button>
  </form>
</div>

<div class="card">
  <h3>سجل المصروفات</h3>
  <table>
    <tr><th>الرقم</th><th>التصنيف</th><th>المبلغ</th><th>مركز الكلفة</th><th>التاريخ</th><th>الحالة</th><th>بواسطة</th></tr>
    <?php foreach ($expenses as $e): ?>
    <tr>
      <td><?= Helpers::e($e['expense_no']) ?></td>
      <td><?= Helpers::e($e['category']) ?></td>
      <td><?= Helpers::money($e['amount']) ?></td>
      <td><?= Helpers::e($e['cost_center_name'] ?? '-') ?></td>
      <td><?= $e['expense_date'] ?></td>
      <td><span class="badge badge-<?= in_array($e['status'],['approved','paid']) ? 'success' : ($e['status'] === 'rejected' ? 'danger' : 'warning') ?>">
        <?= $statusLabels[$e['status']] ?? $e['status'] ?></span></td>
      <td><?= Helpers::e($e['full_name'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$expenses): ?><tr><td colspan="7">لا توجد مصروفات</td></tr><?php endif; ?>
  </table>
</div>
