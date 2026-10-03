<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h2>تقرير مركز الكلفة: <?= Helpers::e($center['name']) ?></h2>
  <p><?= Helpers::e($center['description'] ?? '') ?></p>
</div>

<div class="stats-grid" style="margin-bottom:16px">
  <div class="stat-card"><div class="stat-value" style="color:#16a34a"><?= Helpers::money($profitability['revenue']) ?></div><div class="stat-label">الإيرادات</div></div>
  <div class="stat-card"><div class="stat-value" style="color:#dc2626"><?= Helpers::money($profitability['expenses']) ?></div><div class="stat-label">المصروفات</div></div>
  <div class="stat-card"><div class="stat-value" style="color:<?= $profitability['profit'] >= 0 ? '#2980b9' : '#c0392b' ?>"><?= Helpers::money($profitability['profit']) ?></div><div class="stat-label">صافي الربحية</div></div>
</div>

<div class="card" style="margin-bottom:16px">
  <h3>فترة التقرير</h3>
  <form method="get" class="form-inline">
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">تحديث</button>
  </form>
</div>

<div class="card">
  <h3>الحركات</h3>
  <table>
    <tr><th>النوع</th><th>الرقم</th><th>التاريخ</th><th>الطرف/التصنيف</th><th>الاتجاه</th><th>المبلغ</th></tr>
    <?php foreach ($transactions as $t): ?>
    <tr>
      <td><?= Helpers::e($t['type']) ?></td>
      <td><?= Helpers::e($t['ref']) ?></td>
      <td><?= $t['date'] ?></td>
      <td><?= Helpers::e($t['party'] ?? '-') ?></td>
      <td><span class="badge badge-<?= $t['direction'] === 'revenue' ? 'success' : 'danger' ?>"><?= $t['direction'] === 'revenue' ? 'إيراد' : 'مصروف' ?></span></td>
      <td><?= Helpers::money($t['amount']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$transactions): ?><tr><td colspan="6">لا توجد حركات في الفترة</td></tr><?php endif; ?>
  </table>
</div>
