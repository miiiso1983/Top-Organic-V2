<?php use App\Core\{Helpers, AccountingService, Database}; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-chart-column"></i> لوحة محاسبية</h2>
  <div class="toolbar">
    <a href="/accounting/journal" class="btn btn-sm">قيود يومية</a>
    <a href="/accounting/fixed-assets" class="btn btn-sm btn-secondary">الأصول الثابتة</a>
  </div>
</div>

<div class="grid grid-4">
  <div class="stat">
    <div class="value" style="color:#16a34a"><?= Helpers::money($assets) ?></div>
    <div class="label">الأصول</div>
  </div>
  <div class="stat">
    <div class="value" style="color:#dc2626"><?= Helpers::money($liabilities) ?></div>
    <div class="label">الخصوم</div>
  </div>
  <div class="stat">
    <div class="value" style="color:#2563eb"><?= Helpers::money($equity) ?></div>
    <div class="label">حقوق الملكية</div>
  </div>
  <div class="stat">
    <div class="value" style="color:#d97706"><?= Helpers::money($revenue - $expenses) ?></div>
    <div class="label">الربح / الخسارة</div>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>أخر القيود</h3>
    <table>
      <tr><th>رقم القيد</th><th>التاريخ</th><th>البيان</th><th>المبلغ</th></tr>
      <?php foreach ($entries as $e): ?>
      <tr>
        <td><a href="/accounting/journal"><?= Helpers::e($e['entry_no']) ?></a></td>
        <td><?= Helpers::e($e['entry_date']) ?></td>
        <td><?= Helpers::e($e['description']) ?></td>
        <td>
          <?php
            $t = Database::query("SELECT SUM(debit) AS s FROM journal_lines WHERE entry_id = ?", [$e['id']])->fetch()['s'];
            echo Helpers::money($t);
          ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$entries): ?><tr><td colspan="4">لا توجد قيود</td></tr><?php endif; ?>
    </table>
  </div>
  <div class="card">
    <h3>تقارير</h3>
    <p><a href="/accounting/reports?period=<?= date('Y-m') ?>" class="btn btn-sm">فتح صفحة التقارير</a></p>
    <ul style="line-height:2">
      <li><a href="/accounting/reports/trial-balance" target="_blank">ميزان المراجعة</a></li>
      <li><a href="/accounting/reports/profit-loss" target="_blank">قائمة الدخل</a></li>
      <li><a href="/accounting/reports/balance-sheet" target="_blank">الميزانية العمومية</a></li>
      <li><a href="/accounting/ledger">كشوف الحسابات</a></li>
    </ul>
  </div>
</div>
