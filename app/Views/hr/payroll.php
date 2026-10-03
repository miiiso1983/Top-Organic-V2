<?php use App\Core\Helpers; ?>
<?php
$statusLabels = ['draft'=>'مسودة','approved'=>'معتمد','paid'=>'مدفوع'];
$statusColors = ['draft'=>'warning','approved'=>'info','paid'=>'success'];
$exportQuery = ['period' => $period];
if ($selected_employee) { $exportQuery['employee_id'] = $selected_employee; }
if (!empty($selected_department)) { $exportQuery['department'] = $selected_department; }
if ($selected_branch) { $exportQuery['branch_id'] = $selected_branch; }
if ($selected_status) { $exportQuery['status'] = $selected_status; }
$exportBase = http_build_query($exportQuery);
?>
<style>
.payroll-dashboard { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 16px; }
.payroll-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column; gap: 4px; }
.payroll-card .value { font-size: 22px; font-weight: 700; }
.payroll-card .label { font-size: 12px; color: #6b7280; }
.payroll-card .meta { font-size: 11px; color: #9ca3af; margin-top: 4px; }
.payroll-card.total { border-top: 3px solid #3b82f6; }
.payroll-card.earnings { border-top: 3px solid #10b981; }
.payroll-card.deductions { border-top: 3px solid #f59e0b; }
.payroll-card.net { border-top: 3px solid #16a34a; }
.payroll-toolbar { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin:10px 0; }
.payroll-filterbar { display:flex; flex-wrap:wrap; gap:12px; align-items:end; background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; padding:12px; margin-bottom:10px; }
.payroll-filterbar .form-row { display:flex; flex-direction:column; gap:4px; margin:0; flex:1 1 160px; max-width:260px; min-width:0; }
.payroll-filterbar .form-row label { font-size:12px; color:#374151; white-space:nowrap; }
.payroll-filterbar select, .payroll-filterbar input { padding:6px 8px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; width:100%; max-width:100%; box-sizing:border-box; min-width:0; }
.payroll-filterbar select { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.payroll-table-wrap { position:relative; overflow-x:auto; margin-top:12px; border:1px solid #e5e7eb; border-radius:8px; }
.payroll-table { font-size:13px; min-width:1400px; border-collapse: collapse; width:100%; table-layout:fixed; }
.payroll-table th, .payroll-table td { padding:5px 7px; border:1px solid #e5e7eb; text-align:center; vertical-align:middle; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.payroll-table th.pcol-th { position:relative; }
.payroll-table th.pcol-draggable { cursor:grab; }
.payroll-table th.pcol-draggable:active { cursor:grabbing; }
.payroll-table th.pcol-dragging { opacity:0.4; }
.payroll-table th.pcol-dragover { outline:2px dashed #2563eb; outline-offset:-2px; background:#eff6ff !important; }
.pcol-resizer { position:absolute; top:0; bottom:0; left:-3px; width:6px; cursor:col-resize; user-select:none; touch-action:none; z-index:40; }
.pcol-resizer:hover, .pcol-resizer.pcol-resizing { background:#93c5fd; }
.payroll-table thead th { background:#f8fafc; font-weight:700; color:#374151; position: sticky; top:0; z-index:10; }
.payroll-table .group-header th { background:#e2e8f0; color:#1e293b; font-size:12px; font-weight:700; }
.payroll-table .sub-header th { background:#f1f5f9; font-size:11px; color:#475569; }
.payroll-table .sticky-col-1 { position: sticky; right: 0; background: #fff; z-index: 20; width: 42px; min-width: 42px; max-width: 42px; box-shadow: -2px 0 4px rgba(0,0,0,0.05); box-sizing: border-box; }
.payroll-table .sticky-col-2 { position: sticky; right: 42px; background: #fff; z-index: 20; width: 200px; min-width: 200px; max-width: 200px; box-shadow: -2px 0 4px rgba(0,0,0,0.05); box-sizing: border-box; }
.payroll-table thead th.sticky-col-1, .payroll-table thead th.sticky-col-2 { z-index: 30; background: #f8fafc; }
.payroll-table .group-header th.sticky-col-1, .payroll-table .group-header th.sticky-col-2 { background: #e2e8f0; }
.payroll-table .sub-header th.sticky-col-1, .payroll-table .sub-header th.sticky-col-2 { background: #f1f5f9; }
.payroll-table .col-advances { color:#dc2626; font-weight:700; background:#fff1f2; }
.payroll-table .col-net { color:#16a34a; font-weight:700; background:#f0fdf4; }
.payroll-table .col-earning { color:#15803d; }
.payroll-table .col-deduction { color:#b91c1c; }
.badge { padding:3px 8px; border-radius:999px; font-size:11px; display:inline-block; font-weight:600; }
.badge-warning { background:#fef3c7; color:#92400e; }
.badge-info { background:#dbeafe; color:#1e40af; }
.badge-success { background:#dcfce7; color:#166534; }
.adv-badge { background:#ffedd5; color:#9a3412; border:1px solid #fdba74; font-size:10px; padding:2px 6px; border-radius:999px; cursor:pointer; display:inline-flex; align-items:center; gap:4px; }
.adv-badge:hover { background:#fed7aa; }
.modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center; }
.modal-content { background:#fff; border-radius:8px; width:92%; max-width:650px; max-height:86vh; overflow-y:auto; box-shadow:0 10px 25px rgba(0,0,0,0.2); }
.modal-header { display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid #e5e7eb; }
.modal-header h4 { margin:0; }
.modal-close { cursor:pointer; font-size:20px; color:#6b7280; }
.modal-body { padding:16px; }
.modal-body table { font-size:12px; width:100%; border-collapse:collapse; }
.modal-body th, .modal-body td { border:1px solid #e5e7eb; padding:6px; text-align:center; }
.modal-body th { background:#f8fafc; }
.pj-link { color:inherit; text-decoration:none; border-bottom:1px dashed #94a3b8; cursor:pointer; display:inline-block; }
.pj-link:hover { color:#1d4ed8; border-bottom-color:#1d4ed8; }
#pjItemBody .pj-summary th { text-align:right; width:48%; }
#pjItemBody .pj-notes { margin:12px 0 0; padding-right:18px; font-size:12px; color:#6b7280; }
.text-muted { color:#6b7280; }
.payroll-tabs { display:flex; gap:8px; margin:14px 0 4px; border-bottom:1px solid #e5e7eb; }
.payroll-tabs .tab-btn { background:transparent; border:none; border-bottom:3px solid transparent; padding:10px 6px; font-size:14px; font-weight:600; color:#6b7280; cursor:pointer; }
.payroll-tabs .tab-btn.active { color:#2563eb; border-bottom-color:#2563eb; }
.payroll-tabs .tab-btn .badge { margin-right:4px; }
.tab-panel { display:none; }
.tab-panel.active { display:block; }
.settlement-table { font-size:13px; border-collapse: collapse; width:100%; }
.settlement-table th, .settlement-table td { padding:6px 8px; border:1px solid #e5e7eb; text-align:center; vertical-align:middle; }
.settlement-table thead th { background:#f8fafc; font-weight:700; color:#374151; }
.settlement-table .group-header th { background:#e2e8f0; color:#1e293b; font-size:12px; font-weight:700; }
.settlement-table .col-earning { color:#15803d; }
.settlement-table .col-deduction { color:#b91c1c; }
.settlement-table .col-net { color:#16a34a; font-weight:700; background:#f0fdf4; }
@media (max-width:768px) { .payroll-dashboard { grid-template-columns:1fr 1fr; } .payroll-filterbar { flex-direction:column; align-items:stretch; } .payroll-filterbar .form-row { width:100%; } .payroll-filterbar select, .payroll-filterbar input { width:100%; } }
</style>

<div class="card">
  <h3><i class="fa-solid fa-gear"></i> إدارة الرواتب</h3>
  <form method="get" action="/hr/payroll" class="payroll-filterbar">
    <div class="form-row">
      <label>الفترة</label>
      <input type="month" name="period" value="<?= Helpers::e($period) ?>">
    </div>
    <div class="form-row">
      <label>الموظف</label>
      <select name="employee_id"><option value="0">الكل</option><?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>" <?= $e['id'] == $selected_employee ? 'selected' : '' ?>><?= Helpers::e($e['full_name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="form-row">
      <label>القسم</label>
      <select name="department"><option value="">الكل</option><?php foreach ($departments as $d): ?><option value="<?= Helpers::e($d['name']) ?>" <?= $d['name'] === $selected_department ? 'selected' : '' ?>><?= Helpers::e($d['name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="form-row">
      <label>الفرع</label>
      <select name="branch_id"><option value="0">الكل</option><?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= $b['id'] == $selected_branch ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="form-row">
      <label>الحالة</label>
      <select name="status">
        <option value="">الكل</option>
        <?php foreach ($statusLabels as $key => $label): ?>
        <option value="<?= $key ?>" <?= $selected_status === $key ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn"><i class="fa-solid fa-filter"></i> عرض</button>
    <a href="/hr/payroll" class="btn btn-sm btn-secondary">إعادة ضبط</a>
  </form>
  <form method="post" action="/hr/payroll/generate" style="margin-top:10px">
    <input type="hidden" name="period" value="<?= Helpers::e($period) ?>">
    <button class="btn btn-success"><i class="fa-solid fa-gear"></i> توليد رواتب الفترة <?= Helpers::e($period) ?></button>
  </form>
  <div class="payroll-toolbar">
    <a href="/hr/payroll/allowances" class="btn btn-sm btn-secondary"><i class="fa-solid fa-sliders"></i> البدلات</a>
    <a href="/hr/payroll/tips" class="btn btn-sm btn-secondary"><i class="fa-solid fa-coins"></i> التيبس</a>
    <a href="/hr/payroll/social-security" class="btn btn-sm btn-secondary"><i class="fa-solid fa-hand-holding-dollar"></i> الضمان</a>
    <a href="/hr/payroll/travel-tickets" class="btn btn-sm btn-secondary"><i class="fa-solid fa-plane"></i> تذاكر السفر</a>
    <a href="/hr/payroll/loadings" class="btn btn-sm btn-secondary"><i class="fa-solid fa-weight-hanging"></i> التحميلات</a>
    <a href="/hr/payroll/tax-brackets" class="btn btn-sm btn-secondary"><i class="fa-solid fa-bars"></i> شرائح الضريبة</a>
    <a href="/hr/payroll/messages?period=<?= urlencode($period) ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-comment-sms"></i> رسائل</a>
    <a href="/hr/headcount?period=<?= urlencode($period) ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-users-gear"></i> الأعداد</a>
    <a href="/hr/payroll/import" class="btn btn-sm btn-secondary"><i class="fa-solid fa-file-import"></i> استيراد</a>
  </div>
  <form method="post" action="/hr/payroll/absence-mode" class="form-inline" style="margin-top:10px">
    <input type="hidden" name="period" value="<?= Helpers::e($period) ?>">
    <div class="form-row"><label><i class="fa-solid fa-fingerprint"></i> احتساب الغياب</label>
      <select name="mode">
        <option value="attendance" <?= ($absence_mode ?? 'attendance') === 'attendance' ? 'selected' : '' ?>>تلقائي من البصمات والورديات والإجازات</option>
        <option value="alerts" <?= ($absence_mode ?? '') === 'alerts' ? 'selected' : '' ?>>من تنبيهات عدم البصمة</option>
      </select>
    </div>
    <button class="btn btn-sm">حفظ</button>
  </form>
  <p class="text-muted" style="margin-top:10px">
    <i class="fa-solid fa-circle-info"></i> توليد الراتب يحسب: الأساسي + الزيادات + البدلات + الإضافي + المكافآت + تذاكر السفر − الخصومات − الجزاءات − السلف − التأمينات المستقطعة − التحميلات − التيبس − الضمان الاجتماعي − التقريب − الغيابات − الضريبة.
    الغياب = أيام العمل المتوقعة (الوردية − العطل) − أيام الدوام الفعلية − الإجازات المعتمدة؛ الأجر اليومي = الأساسي ÷ 30.
    عند توليد راتب الشهر الجاري قبل انتهائه: يُحتسب صافي الراتب داخلياً بشكل تناسبي (Pro-rata) من بداية الشهر وحتى تاريخ اليوم فقط، بينما يعرض عمود الأساسي دائماً الراتب النهائي الكامل (الراتب الأساسي + الزيادات)؛ وعمود الغياب يعرض أيام الغياب المسجلة من الأيام المتوقعة حتى اليوم.
  </p>
</div>

<div class="payroll-tabs">
  <button type="button" class="btn tab-btn active" data-tab="payroll">كشف الرواتب</button>
  <button type="button" class="btn tab-btn" data-tab="settlements">المخالصات لهذا الشهر <span class="badge badge-warning"><?= (int)$settlementsSummary['count'] ?></span></button>
</div>

<div id="tab-payroll" class="tab-panel active">
<div class="payroll-dashboard">
  <div class="payroll-card total">
    <span class="label">عدد الموظفين</span>
    <span class="value"><?= number_format($summary['count']) ?></span>
    <span class="meta">
      <span class="badge badge-warning"><?= $summary['draft'] ?> مسودة</span>
      <span class="badge badge-info"><?= $summary['approved'] ?> معتمد</span>
      <span class="badge badge-success"><?= $summary['paid'] ?> مدفوع</span>
    </span>
  </div>
  <div class="payroll-card earnings">
    <span class="label">إجمالي الاستحقاقات</span>
    <span class="value"><?= Helpers::money($summary['gross']) ?></span>
  </div>
  <div class="payroll-card deductions">
    <span class="label">إجمالي الاستقطاعات</span>
    <span class="value"><?= Helpers::money($summary['deductions']) ?></span>
    <?php if ($summary['advances'] > 0): ?><span class="meta">منها سلف: <?= Helpers::money($summary['advances']) ?></span><?php endif; ?>
  </div>
  <div class="payroll-card net">
    <span class="label">صافي المستحق للصرف</span>
    <span class="value"><?= Helpers::money($summary['net']) ?></span>
  </div>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px">
    <h3>كشف رواتب <?= Helpers::e($period) ?></h3>
    <div class="payroll-toolbar">
      <?php if (\App\Core\Auth::can('payroll.approve')): ?>
      <form method="post" action="/hr/payroll/bulk-approve" id="bulk-approve-form" style="display:inline">
        <input type="hidden" name="period" value="<?= Helpers::e($period) ?>">
      </form>
      <button type="button" class="btn btn-sm btn-info" onclick="submitBulkApprove()">
        <i class="fa-solid fa-check-double"></i> اعتماد المحدد
      </button>
      <?php endif; ?>
      <a href="?<?= $exportBase ?>&format=bank" class="btn btn-sm btn-secondary"><i class="fa-solid fa-building-columns"></i> كشف بنك</a>
      <a href="?<?= $exportBase ?>&format=excel" class="btn btn-sm btn-secondary pj-export-link" data-export-base="?<?= $exportBase ?>&format=excel"><i class="fa-solid fa-file-excel"></i> Excel</a>
      <a href="?<?= $exportBase ?>&format=pdf" class="btn btn-sm btn-secondary pj-export-link" data-export-base="?<?= $exportBase ?>&format=pdf"><i class="fa-solid fa-file-pdf"></i> PDF</a>
      <button type="button" class="btn btn-sm btn-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i> طباعة</button>
      <button type="button" class="btn btn-sm btn-secondary" id="pcol-reset-btn" title="استعادة ترتيب وعرض الأعمدة الافتراضي"><i class="fa-solid fa-table-columns"></i> إعادة ضبط الأعمدة</button>
    </div>
  </div>

  <p class="text-muted" style="margin:0 0 6px"><i class="fa-solid fa-arrows-left-right"></i> اسحب عناوين أعمدة الاستحقاقات/الاستقطاعات لإعادة ترتيبها، أو اسحب الحافة اليسرى للعمود لتغيير عرضه.</p>

  <div class="payroll-table-wrap">
    <table class="payroll-table" id="payroll-table">
      <colgroup id="payroll-colgroup">
        <col data-col-key="select">
        <col data-col-key="employee">
        <col data-col-key="base"><col data-col-key="overtime"><col data-col-key="bonuses"><col data-col-key="tips_addition">
        <col data-col-key="working_days"><col data-col-key="absence"><col data-col-key="deductions"><col data-col-key="advances"><col data-col-key="long_term_advances"><col data-col-key="insurance"><col data-col-key="loadings"><col data-col-key="tips"><col data-col-key="social_security"><col data-col-key="housing"><col data-col-key="remaining">
        <col data-col-key="net"><col data-col-key="status"><col data-col-key="actions">
      </colgroup>
      <thead>
        <tr class="group-header">
          <th rowspan="2" class="sticky-col-1">
            <input type="checkbox" id="select-all" title="اختيار الكل">
          </th>
          <th rowspan="2" class="sticky-col-2">الموظف</th>
          <th colspan="4">الاستحقاقات</th>
          <th colspan="10">الاستقطاعات</th>
          <th colspan="1">المتبقي</th>
          <th rowspan="2" class="col-net pcol-th" data-col-key="net">صافي<span class="pcol-resizer" data-resize-key="net"></span></th>
          <th rowspan="2" class="pcol-th" data-col-key="status">الحالة<span class="pcol-resizer" data-resize-key="status"></span></th>
          <th rowspan="2" class="pcol-th" data-col-key="actions">إجراءات<span class="pcol-resizer" data-resize-key="actions"></span></th>
        </tr>
        <tr class="sub-header" id="payroll-reorder-row">
          <th class="pcol-th pcol-draggable" data-col-key="base" draggable="true">الراتب الأساسي<span class="pcol-resizer" data-resize-key="base"></span></th><th class="pcol-th pcol-draggable" data-col-key="overtime" draggable="true">إضافي (أيام/مبلغ)<span class="pcol-resizer" data-resize-key="overtime"></span></th><th class="pcol-th pcol-draggable" data-col-key="bonuses" draggable="true">مكافآت<span class="pcol-resizer" data-resize-key="bonuses"></span></th><th class="pcol-th pcol-draggable" data-col-key="tips_addition" draggable="true">إضافي تيبس<span class="pcol-resizer" data-resize-key="tips_addition"></span></th>
          <th class="pcol-th pcol-draggable" data-col-key="working_days" draggable="true">أيام الدوام<span class="pcol-resizer" data-resize-key="working_days"></span></th><th class="pcol-th pcol-draggable" data-col-key="absence" draggable="true">خصم الغياب<span class="pcol-resizer" data-resize-key="absence"></span></th><th class="pcol-th pcol-draggable" data-col-key="deductions" draggable="true">خصومات<span class="pcol-resizer" data-resize-key="deductions"></span></th><th class="col-advances pcol-th pcol-draggable" data-col-key="advances" draggable="true">سلف<span class="pcol-resizer" data-resize-key="advances"></span></th><th class="col-advances pcol-th pcol-draggable" data-col-key="long_term_advances" draggable="true">سلف طويلة الأجل<span class="pcol-resizer" data-resize-key="long_term_advances"></span></th><th class="pcol-th pcol-draggable" data-col-key="insurance" draggable="true">التأمينات المستقطعة<span class="pcol-resizer" data-resize-key="insurance"></span></th><th class="pcol-th pcol-draggable" data-col-key="loadings" draggable="true">تحميلات<span class="pcol-resizer" data-resize-key="loadings"></span></th><th class="pcol-th pcol-draggable" data-col-key="tips" draggable="true">تيبس<span class="pcol-resizer" data-resize-key="tips"></span></th><th class="pcol-th pcol-draggable" data-col-key="social_security" draggable="true">ضمان<span class="pcol-resizer" data-resize-key="social_security"></span></th><th class="pcol-th pcol-draggable" data-col-key="housing" draggable="true">سكن<span class="pcol-resizer" data-resize-key="housing"></span></th><th class="pcol-th pcol-draggable" data-col-key="remaining" draggable="true">قيمة المتبقي<span class="pcol-resizer" data-resize-key="remaining"></span></th>
        </tr>
      </thead>
      <tbody>
        <?php
        $totalNet = 0; $totalInsurance = 0;
        $totalLoadings = 0; $totalTips = 0; $totalSocialSecurity = 0;
        $totalAbsence = 0; $totalAdvances = 0; $totalTipsAddition = 0; $totalHousingDeduction = 0;
        $totalLongTermAdvances = 0;
        $totalWorkingDays = 0; $totalAbsenceDays = 0; $totalOvertimeDays = 0; $totalOvertime = 0;
        $totalRemainingDays = 0; $totalRemainingValue = 0;
        foreach ($payrolls as $p):
            $totalNet += (float)$p['net_salary'];
            $totalInsurance += (float)($p['security_deposit'] ?? 0);
            $totalWorkingDays += (int)($p['working_days'] ?? 0); $totalAbsenceDays += (int)($p['absence_days'] ?? 0);
            $totalOvertimeDays += (int)($p['overtime_days'] ?? 0); $totalOvertime += (float)($p['overtime'] ?? 0);
            $totalLoadings += (float)($p['loadings'] ?? 0);
            $totalTips += (float)($p['tips'] ?? 0); $totalSocialSecurity += (float)($p['social_security'] ?? 0);
            $totalAbsence += (float)($p['absence_deduction'] ?? 0); $totalAdvances += (float)$p['advances'];
            $totalTipsAddition += (float)($p['tips_addition'] ?? 0); $totalHousingDeduction += (float)($p['housing_deduction'] ?? 0);
            $totalLongTermAdvances += (float)($p['long_term_advances'] ?? 0);
            $totalRemainingDays += (int)($p['remaining_days'] ?? 0); $totalRemainingValue += (float)($p['remaining_value'] ?? 0);
        ?>
        <tr>
          <td class="sticky-col-1" data-col-key="select">
            <?php if ($p['status'] === 'draft' && \App\Core\Auth::can('payroll.approve')): ?>
            <input type="checkbox" name="payroll_ids[]" value="<?= (int)$p['id'] ?>" class="payroll-check">
            <?php endif; ?>
          </td>
          <td class="sticky-col-2" data-col-key="employee" style="text-align:right">
            <div style="font-weight:700"><?= Helpers::e($p['full_name']) ?></div>
            <div style="font-size:11px;color:#6b7280">
              <?= Helpers::e($p['emp_code'] ?? '-') ?>
              <?php $deptDisplay = $p['department_name'] ?? ($p['department'] ?? ''); ?>
              <?php if (!empty($deptDisplay)): ?> · <?= Helpers::e($deptDisplay) ?><?php endif; ?>
              <?php $jobDisplay = $p['job_title'] ?? ($p['position'] ?? ''); ?>
              <?php if (!empty($jobDisplay)): ?> · <?= Helpers::e($jobDisplay) ?><?php endif; ?>
              <?php if (!empty($p['branch_name'])): ?> · <?= Helpers::e($p['branch_name']) ?><?php endif; ?>
            </div>
          </td>
          <td class="col-earning" data-col-key="base">
            <?php $baseTotal = (float)($p['full_base_salary'] ?? $p['base_salary']) + (float)($p['raises'] ?? 0) - (float)($p['salary_reduction'] ?? 0); ?>
            <a href="javascript:void(0)" class="pj-link" data-pid="<?= (int)$p['id'] ?>" data-item="base" title="اضغط لعرض تفاصيل الراتب الأساسي">
              <?= Helpers::money($baseTotal) ?>
            </a>
          </td>
          <td class="col-earning" data-col-key="overtime" title="عدد أيام الإضافي المعتمدة والمبلغ المستحق المحسوب بالمعادلة المعتمدة — اضغط للتفاصيل">
            <a href="javascript:void(0)" class="pj-link" data-pid="<?= (int)$p['id'] ?>" data-item="overtime">
              <?= (int)($p['overtime_days'] ?? 0) ?> يوم
              <?php if ((float)($p['overtime'] ?? 0) > 0): ?><br><small>+<?= Helpers::money($p['overtime']) ?></small><?php endif; ?>
            </a>
          </td>
          <td class="col-earning" data-col-key="bonuses"><a href="javascript:void(0)" class="pj-link" data-pid="<?= (int)$p['id'] ?>" data-item="bonuses" title="اضغط لعرض تفاصيل المكافآت">+<?= Helpers::money($p['bonuses']) ?></a></td>
          <td class="col-earning" data-col-key="tips_addition"><a href="javascript:void(0)" class="pj-link" data-pid="<?= (int)$p['id'] ?>" data-item="tips_addition" title="اضغط لعرض تفاصيل إضافي تيبس">+<?= Helpers::money($p['tips_addition'] ?? 0) ?></a></td>
          <td data-col-key="working_days" title="أيام الدوام الفعلية من بيانات البصمة · متوقع <?= (int)($p['expected_days'] ?? 0) ?> · إجازات <?= (int)($p['leave_days'] ?? 0) ?> · بدون راتب <?= (int)($p['unpaid_leave_days'] ?? 0) ?> — اضغط للتفاصيل">
            <a href="javascript:void(0)" class="pj-link" data-pid="<?= (int)$p['id'] ?>" data-item="working_days"><?= (int)($p['working_days'] ?? 0) ?></a>
          </td>
          <td data-col-key="absence" title="غياب <?= (int)($p['absence_days'] ?? 0) ?> من أصل <?= (int)($p['expected_days'] ?? 0) ?> يوم دوام متوقع حتى تاريخه — اضغط للتفاصيل">
            <a href="javascript:void(0)" class="pj-link" data-pid="<?= (int)$p['id'] ?>" data-item="absence">
              <span class="col-deduction"><?= (int)($p['absence_days'] ?? 0) ?> يوم</span>
              <?php if ((float)($p['absence_deduction'] ?? 0) > 0): ?><br><small class="col-deduction">−<?= Helpers::money($p['absence_deduction']) ?></small><?php endif; ?>
            </a>
          </td>
          <td class="col-deduction" data-col-key="deductions"><a href="javascript:void(0)" class="pj-link" data-pid="<?= (int)$p['id'] ?>" data-item="deductions" title="اضغط لعرض تفاصيل الخصومات">−<?= Helpers::money($p['deductions']) ?></a></td>
          <td class="col-advances" data-col-key="advances">
            <a href="javascript:void(0)" class="pj-link" data-pid="<?= (int)$p['id'] ?>" data-item="advances" title="اضغط لعرض تفاصيل السلف">
              <?= (float)$p['advances'] > 0 ? '−' . Helpers::money($p['advances']) : '—' ?>
            </a>
          </td>
          <td class="col-advances" data-col-key="long_term_advances"><a href="javascript:void(0)" class="pj-link" data-pid="<?= (int)$p['id'] ?>" data-item="long_term_advances" title="اضغط لعرض تفاصيل السلف طويلة الأجل"><?= (float)($p['long_term_advances'] ?? 0) > 0 ? '−' . Helpers::money($p['long_term_advances']) : '—' ?></a></td>
          <td class="col-deduction" data-col-key="insurance"><a href="javascript:void(0)" class="pj-link" data-pid="<?= (int)$p['id'] ?>" data-item="insurance" title="اضغط لعرض تفاصيل التأمينات المستقطعة">−<?= Helpers::money($p['security_deposit'] ?? 0) ?></a></td>
          <td class="col-deduction" data-col-key="loadings"><a href="javascript:void(0)" class="pj-link" data-pid="<?= (int)$p['id'] ?>" data-item="loadings" title="اضغط لعرض تفاصيل التحميلات">−<?= Helpers::money($p['loadings'] ?? 0) ?></a></td>
          <td class="col-deduction" data-col-key="tips"><a href="javascript:void(0)" class="pj-link" data-pid="<?= (int)$p['id'] ?>" data-item="tips" title="اضغط لعرض تفاصيل التيبس">−<?= Helpers::money($p['tips'] ?? 0) ?></a></td>
          <td class="col-deduction" data-col-key="social_security"><a href="javascript:void(0)" class="pj-link" data-pid="<?= (int)$p['id'] ?>" data-item="social_security" title="اضغط لعرض تفاصيل الضمان الاجتماعي">−<?= Helpers::money($p['social_security'] ?? 0) ?></a></td>
          <td class="col-deduction" data-col-key="housing"><a href="javascript:void(0)" class="pj-link" data-pid="<?= (int)$p['id'] ?>" data-item="housing" title="اضغط لعرض تفاصيل السكن">−<?= Helpers::money($p['housing_deduction'] ?? 0) ?></a></td>
          <td data-col-key="remaining" title="(عدد أيام الشهر − عدد أيام البصمة المسجلة) × (الراتب الأساسي المعدّل ÷ 30)">
            <?= (int)($p['remaining_days'] ?? 0) ?> يوم<?php if ((float)($p['remaining_value'] ?? 0) > 0): ?><br><small><?= Helpers::money($p['remaining_value']) ?></small><?php endif; ?>
          </td>
          <td class="col-net" data-col-key="net"><a href="javascript:void(0)" class="pj-link" data-pid="<?= (int)$p['id'] ?>" data-item="net" title="اضغط لعرض معادلة الصافي"><?= Helpers::money($p['net_salary']) ?></a></td>
          <td data-col-key="status"><span class="badge badge-<?= $statusColors[$p['status']] ?? 'warning' ?>"><?= $statusLabels[$p['status']] ?></span></td>
          <td data-col-key="actions">
            <a href="/hr/payroll/<?= $p['id'] ?>/slip" class="btn btn-sm">ورقة</a>
            <?php if ($p['status'] === 'draft' && \App\Core\Auth::can('payroll.approve')): ?>
            <form method="post" action="/hr/payroll/<?= $p['id'] ?>/approve" style="display:inline">
              <button class="btn btn-sm btn-info">اعتماد</button>
            </form>
            <?php endif; ?>
            <?php if ($p['status'] !== 'paid' && \App\Core\Auth::can('payroll.overtime')): ?>
            <button class="btn btn-sm btn-secondary" onclick="document.getElementById('ot-<?= $p['id'] ?>').style.display='block'">+ إضافي</button>
            <?php endif; ?>
            <?php if ($p['status'] === 'approved' && $p['period'] === date('Y-m') && \App\Core\Auth::can('payroll.approve')): ?>
            <form method="post" action="/hr/payroll/<?= $p['id'] ?>/unapprove" style="display:inline" onsubmit="return confirm('سيتم إلغاء اعتماد هذا الراتب وعكس قيد الاستحقاق المحاسبي المرتبط به. متابعة؟');">
              <button class="btn btn-sm btn-warning">إلغاء الاعتماد</button>
            </form>
            <?php endif; ?>
            <?php if ($p['status'] === 'approved' && \App\Core\Auth::can('payroll.manage')): ?>
            <form method="post" action="/hr/payroll/<?= $p['id'] ?>/pay" style="display:inline">
              <button class="btn btn-sm btn-success">صرف</button>
            </form>
            <?php endif; ?>
            <div id="ot-<?= $p['id'] ?>" style="display:none;margin-top:8px">
              <form method="post" action="/hr/payroll/<?= $p['id'] ?>/overtime" class="form-inline">
                <input type="number" name="hours" step="0.01" placeholder="ساعات" required>
                <input type="number" name="hourly_rate" step="0.01" placeholder="سعر الساعة" required>
                <input type="number" name="multiplier" step="0.01" value="1.0" placeholder="مضاعف" required>
                <input name="notes" placeholder="ملاحظات">
                <button class="btn btn-sm">حفظ</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if ($payrolls): ?>
        <tr style="font-weight:bold; background:#f8fafc;">
          <td class="sticky-col-1" data-col-key="select"></td>
          <td class="sticky-col-2" data-col-key="employee">الإجمالي</td>
          <td data-col-key="base"></td>
          <td data-col-key="overtime"><?= $totalOvertimeDays ?> يوم<?php if ($totalOvertime > 0): ?><br><small class="col-earning"><?= Helpers::money($totalOvertime) ?></small><?php endif; ?></td>
          <td data-col-key="bonuses"></td>
          <td data-col-key="tips_addition"></td>
          <td data-col-key="working_days"><?= $totalWorkingDays ?></td>
          <td class="col-deduction" data-col-key="absence"><?= $totalAbsenceDays ?> يوم<?php if ($totalAbsence > 0): ?><br><small><?= Helpers::money($totalAbsence) ?></small><?php endif; ?></td>
          <td data-col-key="deductions"></td>
          <td class="col-advances" data-col-key="advances"><?= Helpers::money($totalAdvances) ?></td>
          <td class="col-advances" data-col-key="long_term_advances"><?= Helpers::money($totalLongTermAdvances) ?></td>
          <td data-col-key="insurance"><?= Helpers::money($totalInsurance) ?></td>
          <td data-col-key="loadings"><?= Helpers::money($totalLoadings) ?></td>
          <td data-col-key="tips"><?= Helpers::money($totalTips) ?></td>
          <td data-col-key="social_security"><?= Helpers::money($totalSocialSecurity) ?></td>
          <td data-col-key="housing"><?= Helpers::money($totalHousingDeduction) ?></td>
          <td data-col-key="remaining"><?= $totalRemainingDays ?> يوم<?php if ($totalRemainingValue > 0): ?><br><small><?= Helpers::money($totalRemainingValue) ?></small><?php endif; ?></td>
          <td class="col-net" data-col-key="net"><?= Helpers::money($totalNet) ?></td>
          <td data-col-key="status"></td>
          <td data-col-key="actions"></td>
        </tr>
        <?php else: ?>
        <tr><td colspan="20">لم تولَّد رواتب لهذه الفترة بعد</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<div id="tab-settlements" class="tab-panel">
<div class="payroll-dashboard">
  <div class="payroll-card total">
    <span class="label">عدد المخالصات</span>
    <span class="value"><?= number_format($settlementsSummary['count']) ?></span>
  </div>
  <div class="payroll-card earnings">
    <span class="label">إجمالي الاستحقاقات</span>
    <span class="value"><?= Helpers::money($settlementsSummary['earnings']) ?></span>
  </div>
  <div class="payroll-card deductions">
    <span class="label">إجمالي الاستقطاعات</span>
    <span class="value"><?= Helpers::money($settlementsSummary['deductions']) ?></span>
  </div>
  <div class="payroll-card net">
    <span class="label">صافي المخالصات</span>
    <span class="value"><?= Helpers::money($settlementsSummary['net']) ?></span>
  </div>
</div>

<div class="card">
  <h3>مخالصات الموظفين المستقيلين خلال <?= Helpers::e($period) ?></h3>
  <p class="text-muted">الموظفون الذين تمت مخالصتهم النهائية (استقالة) خلال هذا الشهر لا تظهر بياناتهم في كشف الرواتب أعلاه، وتُحتسب مستحقاتهم النهائية هنا بشكل منفصل.</p>
  <div class="payroll-table-wrap">
    <table class="settlement-table">
      <thead>
        <tr class="group-header">
          <th rowspan="2">الموظف</th>
          <th rowspan="2">تاريخ الاستقالة</th>
          <th rowspan="2">أيام العمل</th>
          <th colspan="2">الاستحقاقات</th>
          <th colspan="4">الاستقطاعات</th>
          <th rowspan="2" class="col-net">صافي المستحق</th>
          <th rowspan="2">ملاحظات</th>
        </tr>
        <tr>
          <th>الراتب</th>
          <th>الإضافي</th>
          <th>خصم الغياب</th>
          <th>السلف</th>
          <th>خصم السكن</th>
          <th>استقطاعات أخرى</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($settlements): ?>
        <?php foreach ($settlements as $s): $b = $s['breakdown']; ?>
        <tr>
          <td style="text-align:right">
            <div style="font-weight:700"><?= Helpers::e($s['full_name']) ?></div>
            <div style="font-size:11px;color:#6b7280">
              <?= Helpers::e($s['emp_code'] ?? '-') ?>
              <?php $sDeptDisplay = $s['department_name'] ?? ($s['department'] ?? ''); ?>
              <?php if (!empty($sDeptDisplay)): ?> · <?= Helpers::e($sDeptDisplay) ?><?php endif; ?>
              <?php $sJobDisplay = $s['job_title'] ?? ($s['position'] ?? ''); ?>
              <?php if (!empty($sJobDisplay)): ?> · <?= Helpers::e($sJobDisplay) ?><?php endif; ?>
              <?php if (!empty($s['branch_name'])): ?> · <?= Helpers::e($s['branch_name']) ?><?php endif; ?>
            </div>
          </td>
          <td><?= Helpers::e($s['resignation_date']) ?></td>
          <td><?= Helpers::e($s['days_worked']) ?></td>
          <td class="col-earning">+<?= Helpers::money($b['salary_amount'] ?? 0) ?></td>
          <td class="col-earning">+<?= Helpers::money($b['overtime_amount'] ?? 0) ?></td>
          <td class="col-deduction">−<?= Helpers::money($b['absence_deduction'] ?? 0) ?></td>
          <td class="col-deduction">−<?= Helpers::money($b['advances_amount'] ?? 0) ?></td>
          <td class="col-deduction">−<?= Helpers::money($b['housing_deduction'] ?? 0) ?></td>
          <td class="col-deduction">−<?= Helpers::money($b['other_deductions'] ?? 0) ?></td>
          <td class="col-net"><?= Helpers::money($s['total_due']) ?></td>
          <td style="text-align:right"><?= Helpers::e($s['notes'] ?: '-') ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="font-weight:bold; background:#f8fafc;">
          <td colspan="3">الإجمالي</td>
          <td class="col-earning" colspan="2"><?= Helpers::money($settlementsSummary['earnings']) ?></td>
          <td class="col-deduction" colspan="4"><?= Helpers::money($settlementsSummary['deductions']) ?></td>
          <td class="col-net"><?= Helpers::money($settlementsSummary['net']) ?></td>
          <td></td>
        </tr>
        <?php else: ?>
        <tr><td colspan="10">لا توجد مخالصات لهذه الفترة</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<div id="pjItemModal" class="modal-overlay" onclick="if(event.target.id === 'pjItemModal') closePjItem()">
  <div class="modal-content">
    <div class="modal-header">
      <h4 id="pjItemTitle">تفاصيل البند</h4>
      <span class="modal-close" onclick="closePjItem()">&times;</span>
    </div>
    <div class="modal-body" id="pjItemBody"></div>
  </div>
</div>

<div id="advanceModal" class="modal-overlay" onclick="if(event.target.id === 'advanceModal') closeAdvances()">
  <div class="modal-content">
    <div class="modal-header">
      <h4 id="advTitle">تفاصيل السلف</h4>
      <span class="modal-close" onclick="closeAdvances()">&times;</span>
    </div>
    <div class="modal-body" id="advBody"></div>
  </div>
</div>

<script>
var advancesData = <?= json_encode($advancesByEmployee, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
function formatMoney(v) {
    return parseFloat(v).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
}
function showAdvances(empId, name) {
    var rows = advancesData[empId] || [];
    var html = '<table><thead><tr><th>السلفة</th><th>النوع</th><th>القسط</th><th>إجمالي السلفة</th><th>طريقة الصرف</th><th>تاريخ الصرف</th><th>الحساب/البنك</th><th>الحالة</th></tr></thead><tbody>';
    var total = 0;
    rows.forEach(function(r) {
        total += parseFloat(r.amount);
        var method = r.payment_method === 'bank' ? 'بنك' : (r.payment_method === 'cash' ? 'نقد' : '-');
        var bank = r.bank_account_name ? r.bank_account_name : (r.payment_method === 'cash' ? 'صندوق' : '-');
        var status = r.schedule_status === 'paid' ? '<span class="badge badge-success">مسدد</span>' : '<span class="badge badge-warning">مستحق</span>';
        html += '<tr><td>#' + r.advance_id + '</td><td>' + (r.advance_type === 'short' ? 'قصيرة' : 'طويلة') + '</td><td>' + formatMoney(r.amount) + '</td><td>' + formatMoney(r.advance_amount) + '</td><td>' + method + '</td><td>' + (r.paid_at ? r.paid_at.split(' ')[0] : '-') + '</td><td>' + bank + '</td><td>' + status + '</td></tr>';
    });
    html += '</tbody><tfoot><tr style="font-weight:bold"><td colspan="2">الإجمالي</td><td>' + formatMoney(total) + '</td><td colspan="5"></td></tr></tfoot></table>';
    document.getElementById('advBody').innerHTML = html;
    document.getElementById('advTitle').textContent = 'تفاصيل السلف - ' + name;
    document.getElementById('advanceModal').style.display = 'flex';
}
function closeAdvances() { document.getElementById('advanceModal').style.display = 'none'; }

/* نوافذ تفاصيل بنود كشف الرواتب (النقر على الأرقام) */
function pjEsc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
}
function openPjItem(pid, item) {
    var modal = document.getElementById('pjItemModal');
    document.getElementById('pjItemTitle').textContent = 'تفاصيل البند';
    document.getElementById('pjItemBody').innerHTML = '<p style="text-align:center;color:#6b7280">جارِ التحميل…</p>';
    modal.style.display = 'flex';
    fetch('/hr/payroll/' + encodeURIComponent(pid) + '/item-details?item=' + encodeURIComponent(item), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d || !d.ok) {
                document.getElementById('pjItemBody').innerHTML = '<p style="text-align:center;color:#dc2626">' + pjEsc((d && d.error) || 'تعذر تحميل التفاصيل') + '</p>';
                return;
            }
            document.getElementById('pjItemTitle').textContent = d.title || 'تفاصيل البند';
            var html = '';
            if (d.summary && d.summary.length) {
                html += '<table class="pj-summary"><tbody>';
                d.summary.forEach(function(s) {
                    html += '<tr><th>' + pjEsc(s.label) + '</th><td>' + pjEsc(s.value) + '</td></tr>';
                });
                html += '</tbody></table>';
            }
            if (d.table && d.table.rows && d.table.rows.length) {
                html += '<table style="margin-top:12px"><thead><tr>';
                d.table.headers.forEach(function(h) { html += '<th>' + pjEsc(h) + '</th>'; });
                html += '</tr></thead><tbody>';
                d.table.rows.forEach(function(r) {
                    html += '<tr>';
                    r.forEach(function(c) { html += '<td>' + pjEsc(c) + '</td>'; });
                    html += '</tr>';
                });
                html += '</tbody></table>';
            } else if (d.table) {
                html += '<p style="margin-top:12px;text-align:center;color:#6b7280">لا توجد سجلات مفصلة لهذا البند في هذه الفترة.</p>';
            }
            if (d.notes && d.notes.length) {
                html += '<ul class="pj-notes">';
                d.notes.forEach(function(n) { html += '<li>' + pjEsc(n) + '</li>'; });
                html += '</ul>';
            }
            document.getElementById('pjItemBody').innerHTML = html || '<p style="text-align:center;color:#6b7280">لا توجد بيانات.</p>';
        })
        .catch(function() {
            document.getElementById('pjItemBody').innerHTML = '<p style="text-align:center;color:#dc2626">تعذر تحميل التفاصيل — تحقق من الاتصال وأعد المحاولة.</p>';
        });
}
function closePjItem() { document.getElementById('pjItemModal').style.display = 'none'; }
document.addEventListener('click', function(e) {
    var a = e.target.closest ? e.target.closest('.pj-link') : null;
    if (a) {
        e.preventDefault();
        openPjItem(a.getAttribute('data-pid'), a.getAttribute('data-item'));
    }
});
function submitBulkApprove() {
    var form = document.getElementById('bulk-approve-form');
    if (!form) { alert('لا يوجد نموذج للاعتماد'); return; }
    form.querySelectorAll('input[name="payroll_ids[]"]').forEach(function(i) { i.remove(); });
    var checked = document.querySelectorAll('.payroll-check:checked');
    if (!checked.length) { alert('اختر راتباً واحداً على الأقل'); return; }
    checked.forEach(function(cb) {
        var input = document.createElement('input');
        input.type = 'hidden'; input.name = 'payroll_ids[]'; input.value = cb.value;
        form.appendChild(input);
    });
    form.submit();
}
document.addEventListener('DOMContentLoaded', function() {
    var selectAll = document.getElementById('select-all');
    var checkboxes = document.querySelectorAll('.payroll-check');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
        });
    }
    document.querySelectorAll('.payroll-tabs .tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.payroll-tabs .tab-btn').forEach(function(b) { b.classList.remove('active'); });
            document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
        });
    });
    initPayrollTableColumns();
    pjWireExportLinks();
});

/* إلحاق ترتيب الأعمدة الحالي (المحفوظ في localStorage) برابط التصدير ليطابق Excel/PDF الجدول المعروض */
function pjWireExportLinks() {
    var links = document.querySelectorAll('.pj-export-link');
    if (!links.length) return;
    var prefs = pjLoadColumnPrefs();
    var order = (prefs && prefs.order && prefs.order.length) ? prefs.order : PJ_REORDERABLE_KEYS;
    var colsParam = 'cols=' + encodeURIComponent(order.join(','));
    links.forEach(function(a) {
        var base = a.getAttribute('data-export-base');
        a.setAttribute('href', base + '&' + colsParam);
    });
}

/* إعادة ترتيب أعمدة كشف الرواتب بالسحب والإفلات، وتغيير عرض الأعمدة (محفوظة لكل متصفح في localStorage) */
var PJ_COL_STORAGE_KEY = 'payrollTableColumnsV1';
/* الأعمدة القابلة لإعادة الترتيب (التفاصيل فقط، بدون التحديد/الموظف/الصافي/الحالة/الإجراءات) */
var PJ_REORDERABLE_KEYS = ['base','overtime','bonuses','tips_addition','working_days','absence','deductions','advances','long_term_advances','insurance','loadings','tips','social_security','housing','remaining'];
function pjLoadColumnPrefs() {
    try {
        var raw = localStorage.getItem(PJ_COL_STORAGE_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch (e) { return null; }
}
function pjSaveColumnPrefs(prefs) {
    try { localStorage.setItem(PJ_COL_STORAGE_KEY, JSON.stringify(prefs)); } catch (e) {}
}
function initPayrollTableColumns() {
    var table = document.getElementById('payroll-table');
    if (!table) return;
    var colgroup = document.getElementById('payroll-colgroup');
    var prefs = pjLoadColumnPrefs() || {order: null, widths: {}};

    /* قياس العرض الطبيعي الحالي لكل عمود قبل تثبيت الجدول لضمان عدم تغيير الشكل عند أول عرض */
    table.querySelectorAll('thead th[data-col-key]').forEach(function(th) {
        var key = th.dataset.colKey;
        var col = colgroup.querySelector('col[data-col-key="' + key + '"]');
        if (col && !col.style.width) col.style.width = th.getBoundingClientRect().width + 'px';
    });
    table.classList.add('pcol-fixed');

    /* تطبيق الترتيب المحفوظ عند تحميل الصفحة — يُطبّق على كامل الترتيب (بادئة ثابتة + الأعمدة القابلة للسحب + لاحقة ثابتة) لضمان عدم اختلاط الأعمدة الثابتة */
    if (prefs.order && prefs.order.length) {
        var validOrder = prefs.order.filter(function(k) { return PJ_REORDERABLE_KEYS.indexOf(k) !== -1; });
        PJ_REORDERABLE_KEYS.forEach(function(k) { if (validOrder.indexOf(k) === -1) validOrder.push(k); });
        var isDefaultOrder = validOrder.length === PJ_REORDERABLE_KEYS.length
            && validOrder.every(function(k, i) { return k === PJ_REORDERABLE_KEYS[i]; });
        if (!isDefaultOrder) pjApplyFullOrder(table, validOrder);
    }

    /* تطبيق العرض المحفوظ للعرض (الأعرض) على الأعمدة */
    Object.keys(prefs.widths || {}).forEach(function(key) {
        var col = colgroup.querySelector('col[data-col-key="' + key + '"]');
        if (col) col.style.width = prefs.widths[key] + 'px';
    });

    /* السحب والإفلات لإعادة الترتيب */
    var dragSrc = null;
    table.querySelectorAll('th.pcol-draggable').forEach(function(th) {
        th.addEventListener('dragstart', function(e) {
            dragSrc = th;
            th.classList.add('pcol-dragging');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', th.dataset.colKey); } catch (err) {}
        });
        th.addEventListener('dragend', function() {
            th.classList.remove('pcol-dragging');
            table.querySelectorAll('th.pcol-dragover').forEach(function(t) { t.classList.remove('pcol-dragover'); });
        });
        th.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (th !== dragSrc) th.classList.add('pcol-dragover');
        });
        th.addEventListener('dragleave', function() { th.classList.remove('pcol-dragover'); });
        th.addEventListener('drop', function(e) {
            e.preventDefault();
            th.classList.remove('pcol-dragover');
            if (!dragSrc || dragSrc === th) return;
            pjSwapColumns(table, dragSrc.dataset.colKey, th.dataset.colKey);
            pjPersistCurrentOrder(table);
        });
    });

    /* التحجيم بالسحب من حافة العمود */
    var activeResize = null;
    table.querySelectorAll('.pcol-resizer').forEach(function(handle) {
        handle.addEventListener('mousedown', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var key = handle.dataset.resizeKey;
            var col = colgroup.querySelector('col[data-col-key="' + key + '"]');
            var th = table.querySelector('th[data-col-key="' + key + '"]');
            if (!col || !th) return;
            activeResize = {key: key, col: col, startX: e.clientX, startWidth: th.getBoundingClientRect().width};
            handle.classList.add('pcol-resizing');
            document.body.style.userSelect = 'none';
        });
    });
    document.addEventListener('mousemove', function(e) {
        if (!activeResize) return;
        /* الواجهة RTL: السحب لليسار يوسّع العمود (المقبض على الحافة اليسرى للعمود) */
        var delta = activeResize.startX - e.clientX;
        var newWidth = Math.max(50, Math.round(activeResize.startWidth + delta));
        activeResize.col.style.width = newWidth + 'px';
    });
    document.addEventListener('mouseup', function() {
        if (!activeResize) return;
        var width = parseInt(activeResize.col.style.width, 10);
        var prefs2 = pjLoadColumnPrefs() || {order: null, widths: {}};
        prefs2.widths = prefs2.widths || {};
        prefs2.widths[activeResize.key] = width;
        pjSaveColumnPrefs(prefs2);
        table.querySelectorAll('.pcol-resizer.pcol-resizing').forEach(function(h) { h.classList.remove('pcol-resizing'); });
        document.body.style.userSelect = '';
        activeResize = null;
    });

    var resetBtn = document.getElementById('pcol-reset-btn');
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            localStorage.removeItem(PJ_COL_STORAGE_KEY);
            location.reload();
        });
    }
}
function pjSwapColumns(table, keyA, keyB) {
    /* بناء الترتيب الحالي للأعمدة القابلة للسحب من صف الرأس، ثم تبديل موضعي keyA/keyB في المصفوفة فقط */
    var reorderRow = document.getElementById('payroll-reorder-row');
    var order = Array.prototype.map.call(reorderRow.querySelectorAll('th[data-col-key]'), function(t) { return t.dataset.colKey; });
    var iA = order.indexOf(keyA), iB = order.indexOf(keyB);
    if (iA === -1 || iB === -1) return;
    var tmp = order[iA]; order[iA] = order[iB]; order[iB] = tmp;
    pjApplyFullOrder(table, order);
}
/**
 * يطبّق ترتيبًا كاملًا للأعمدة القابلة للسحب (PJ_REORDERABLE_KEYS) على الرؤوس وكل صفوف الجسم والعمود،
 * مع الحفاظ على موضع الأعمدة الثابتة (التحديد/الموظف قبلها والصافي/الحالة/الإجراءات بعدها).
 */
var PJ_FIXED_SUFFIX_KEYS = ['net', 'status', 'actions'];
/**
 * يطبّق الترتيب عبر جميع صفوف الجدول بأقل قدر ممكن من عمليات DOM:
 * بناء خريطة mapping لكل صف بمرور واحد على أبنائه (بدلاً من querySelector لكل عمود)،
 * ثم إعادة إلحاقها دفعة واحدة عبر DocumentFragment (عملية DOM واحدة لكل صف بدلاً من عشرات العمليات).
 */
function pjApplyFullOrder(table, reorderableOrder) {
    var fullOrder = reorderableOrder.concat(PJ_FIXED_SUFFIX_KEYS);

    var reorderRow = document.getElementById('payroll-reorder-row');
    var headMap = {};
    Array.prototype.forEach.call(reorderRow.children, function(th) {
        if (th.dataset && th.dataset.colKey) headMap[th.dataset.colKey] = th;
    });
    var headFrag = document.createDocumentFragment();
    fullOrder.forEach(function(key) { if (headMap[key]) headFrag.appendChild(headMap[key]); });
    reorderRow.appendChild(headFrag);

    var tbody = table.tBodies[0];
    Array.prototype.forEach.call(tbody.rows, function(row) {
        var cellMap = {};
        Array.prototype.forEach.call(row.children, function(cell) {
            if (cell.dataset && cell.dataset.colKey) cellMap[cell.dataset.colKey] = cell;
        });
        var rowFrag = document.createDocumentFragment();
        fullOrder.forEach(function(key) { if (cellMap[key]) rowFrag.appendChild(cellMap[key]); });
        row.appendChild(rowFrag);
    });

    var colgroup = document.getElementById('payroll-colgroup');
    var colMap = {};
    Array.prototype.forEach.call(colgroup.children, function(col) {
        if (col.dataset && col.dataset.colKey) colMap[col.dataset.colKey] = col;
    });
    var colFrag = document.createDocumentFragment();
    fullOrder.forEach(function(key) { if (colMap[key]) colFrag.appendChild(colMap[key]); });
    colgroup.appendChild(colFrag);
}
function pjPersistCurrentOrder(table) {
    var reorderRow = document.getElementById('payroll-reorder-row');
    var order = Array.prototype.map.call(reorderRow.querySelectorAll('th[data-col-key]'), function(t) { return t.dataset.colKey; });
    var prefs = pjLoadColumnPrefs() || {order: null, widths: {}};
    prefs.order = order;
    pjSaveColumnPrefs(prefs);
}
</script>
