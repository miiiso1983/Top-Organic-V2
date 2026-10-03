<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>التقارير المالية</h3>
  <div class="toolbar" style="margin-bottom:14px">
    <a href="/accounting/reports/trial-balance?to=<?= date('Y-m-d') ?>" class="btn btn-sm" target="_blank">ميزان المراجعة</a>
    <a href="/accounting/reports/profit-loss?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-sm" target="_blank">قائمة الدخل</a>
    <a href="/accounting/reports/balance-sheet?to=<?= date('Y-m-d') ?>" class="btn btn-sm" target="_blank">الميزانية العمومية</a>
  </div>
  <p>افتح التقرير في نافذة جديدة. يمكنك أيضًا تخصيص التاريخ من خلال عنوان الصفحة.</p>
  <div class="card" style="margin-top:14px">
    <h4>عرض التقرير</h4>
    <iframe id="report-frame" src="/accounting/reports/trial-balance?to=<?= date('Y-m-d') ?>" style="width:100%;height:500px;border:1px solid #e5e7eb;border-radius:8px"></iframe>
  </div>
</div>
