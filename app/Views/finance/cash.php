<?php use App\Core\Helpers; ?>
<div class="stats-grid">
  <div class="stat-card"><div class="stat-value" style="color:#16a34a"><?= Helpers::money($today['cash_in']) ?></div><div class="stat-label">مقبوضات اليوم</div></div>
  <div class="stat-card"><div class="stat-value" style="color:#dc2626"><?= Helpers::money($today['cash_out']) ?></div><div class="stat-label">مدفوعات اليوم</div></div>
  <div class="stat-card"><div class="stat-value"><?= Helpers::money($today['cash_in'] - $today['cash_out']) ?></div><div class="stat-label">صافي اليوم</div></div>
</div>

<div class="card">
  <h3>أرصدة الصناديق</h3>
  <table>
    <tr><th>الصندوق</th><th>الفرع</th><th>الرصيد الحالي</th></tr>
    <?php foreach ($registers as $r): ?>
    <tr>
      <td><?= Helpers::e($r['name']) ?></td>
      <td><?= Helpers::e($r['branch_name'] ?? '-') ?></td>
      <td style="font-weight:bold"><?= Helpers::money($r['balance']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="card">
  <h3>حركة صندوق جديدة (قبض/صرف)</h3>
  <form method="post" action="/finance/cash" class="form-inline">
    <div class="form-row"><label>الصندوق</label>
      <select name="register_id" required>
        <?php foreach ($registers as $r): ?><option value="<?= $r['id'] ?>"><?= Helpers::e($r['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>النوع</label>
      <select name="trx_type"><option value="in">قبض (إيراد)</option><option value="out">صرف</option></select>
    </div>
    <div class="form-row"><label>المبلغ</label><input type="number" name="amount" step="0.01" min="0.01" required></div>
    <div class="form-row"><label>التصنيف</label><input name="category" placeholder="مبيعات، مشتريات، رواتب..."></div>
    <div class="form-row"><label>المرجع</label><input name="reference"></div>
    <div class="form-row"><label>البيان</label><input name="description"></div>
    <button class="btn btn-success">تسجيل</button>
  </form>
</div>

<div class="card">
  <h3>آخر الحركات</h3>
  <table>
    <tr><th>الصندوق</th><th>النوع</th><th>المبلغ</th><th>التصنيف</th><th>المرجع</th><th>بواسطة</th><th>الوقت</th></tr>
    <?php foreach ($transactions as $t): ?>
    <tr>
      <td><?= Helpers::e($t['register_name']) ?></td>
      <td><span class="badge badge-<?= $t['trx_type'] === 'in' ? 'success' : 'danger' ?>"><?= $t['trx_type'] === 'in' ? 'قبض' : 'صرف' ?></span></td>
      <td><?= Helpers::money($t['amount']) ?></td>
      <td><?= Helpers::e($t['category'] ?? '-') ?></td>
      <td><?= Helpers::e($t['reference'] ?? '-') ?></td>
      <td><?= Helpers::e($t['full_name'] ?? '-') ?></td>
      <td><?= date('m/d H:i', strtotime($t['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$transactions): ?><tr><td colspan="7">لا توجد حركات</td></tr><?php endif; ?>
  </table>
</div>
