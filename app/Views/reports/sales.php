<?php use App\Core\Helpers; ?>
<?php $typeLabels = ['dine_in'=>'صالة','takeaway'=>'سفري','delivery'=>'توصيل','online'=>'إلكتروني']; ?>
<div class="card">
  <form method="get" action="/reports/sales" class="form-inline">
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <button class="btn">عرض</button>
    <a href="?from=<?= Helpers::e($from) ?>&to=<?= Helpers::e($to) ?>&format=excel" class="btn btn-secondary">تصدير Excel</a>
    <a href="?from=<?= Helpers::e($from) ?>&to=<?= Helpers::e($to) ?>&format=pdf" class="btn btn-secondary">تصدير PDF</a>
    <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i> طباعة</button>
  </form>
</div>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-value"><?= $summary['orders_count'] ?></div><div class="stat-label">عدد الطلبات</div></div>
  <div class="stat-card"><div class="stat-value"><?= Helpers::money($summary['total_sales']) ?></div><div class="stat-label">إجمالي المبيعات</div></div>
  <div class="stat-card"><div class="stat-value"><?= Helpers::money($cost) ?></div><div class="stat-label">كلفة البضاعة (تاريخية)</div></div>
  <div class="stat-card"><div class="stat-value"><?= Helpers::money($profit) ?></div><div class="stat-label">مجمل الربح</div></div>
  <div class="stat-card"><div class="stat-value"><?= Helpers::money($summary['avg_order']) ?></div><div class="stat-label">متوسط قيمة الطلب</div></div>
</div>

<div class="card">
  <h3>المبيعات حسب نوع الطلب</h3>
  <table>
    <tr><th>النوع</th><th>عدد الطلبات</th><th>الإجمالي</th></tr>
    <?php foreach ($byType as $t): ?>
    <tr><td><?= $typeLabels[$t['order_type']] ?? $t['order_type'] ?></td><td><?= $t['cnt'] ?></td><td><?= Helpers::money($t['total']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$byType): ?><tr><td colspan="3">لا توجد بيانات</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h3>الأصناف الأكثر مبيعاً (Top 10)</h3>
  <table>
    <tr><th>الصنف</th><th>الكمية</th><th>الإيراد</th></tr>
    <?php foreach ($topItems as $i): ?>
    <tr><td><?= Helpers::e($i['name']) ?></td><td><?= (float)$i['qty'] ?></td><td><?= Helpers::money($i['total']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$topItems): ?><tr><td colspan="3">لا توجد بيانات</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h3>المبيعات اليومية</h3>
  <table>
    <tr><th>اليوم</th><th>الإجمالي</th></tr>
    <?php foreach ($daily as $d): ?>
    <tr><td><?= $d['d'] ?></td><td><?= Helpers::money($d['total']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$daily): ?><tr><td colspan="2">لا توجد بيانات</td></tr><?php endif; ?>
  </table>
</div>
