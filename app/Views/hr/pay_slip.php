<?php use App\Core\Helpers; ?>
<?php $statusLabels = ['draft'=>'مسودة','approved'=>'معتمد','paid'=>'مدفوع']; ?>
<style>
.slip-wrap { max-width: 900px; margin: 0 auto; }
.slip-actions { text-align:center; margin-bottom:16px; }
.pay-slip { background:#fff; border:1px solid var(--border-light,#e5e7eb); border-radius:10px; padding:28px 32px; box-shadow:0 1px 3px rgba(0,0,0,0.05); }
.slip-header { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid #1B5E20; padding-bottom:14px; margin-bottom:18px; }
.slip-header .brand { display:flex; align-items:center; gap:10px; }
.slip-header .brand img { height:42px; }
.slip-header .brand-name { font-size:18px; font-weight:800; color:#1B5E20; }
.slip-header .doc-title { text-align:left; }
.slip-header .doc-title h2 { margin:0; font-size:20px; color:#111827; }
.slip-header .doc-title .period { font-size:13px; color:#6b7280; margin-top:4px; }
.slip-meta { display:grid; grid-template-columns: repeat(3, 1fr); gap:10px 16px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; padding:12px 16px; margin-bottom:18px; font-size:13px; }
.slip-meta div span.k { color:#6b7280; display:block; font-size:11px; margin-bottom:2px; }
.slip-meta div span.v { font-weight:700; color:#111827; }
.slip-columns { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:16px; }
.slip-section { page-break-inside: avoid; }
.slip-section h4 { margin:0; font-size:13px; color:#fff; background:#1B5E20; padding:7px 10px; border-radius:6px 6px 0 0; }
.slip-section.deductions h4 { background:#991b1b; }
.slip-section table { width:100%; border-collapse:collapse; font-size:12.5px; border:1px solid #e5e7eb; border-top:none; }
.slip-section td { padding:6px 10px; border-bottom:1px solid #f1f5f9; }
.slip-section tr:last-child td { border-bottom:none; }
.slip-section td:last-child { text-align:left; font-weight:600; white-space:nowrap; }
.slip-section .total-row td { font-weight:800; background:#f8fafc; border-top:2px solid #e5e7eb; }
.slip-net { display:flex; justify-content:space-between; align-items:center; background:linear-gradient(135deg,#1B5E20,#2E7D32); color:#fff; border-radius:8px; padding:14px 22px; margin:16px 0; page-break-inside: avoid; }
.slip-net .label { font-size:14px; font-weight:600; }
.slip-net .value { font-size:24px; font-weight:800; }
.slip-attendance { margin-bottom:20px; page-break-inside: avoid; }
.slip-attendance table { width:100%; border-collapse:collapse; font-size:12px; }
.slip-attendance th, .slip-attendance td { border:1px solid #e5e7eb; padding:6px; text-align:center; }
.slip-attendance th { background:#f8fafc; color:#374151; }
.slip-footer { display:flex; justify-content:space-between; margin-top:30px; font-size:12px; color:#374151; page-break-inside: avoid; }
.slip-footer .sign-box { text-align:center; width:220px; }
.slip-footer .sign-line { border-top:1px solid #9ca3af; margin-top:36px; padding-top:6px; }
.slip-status-badge { font-size:11px; padding:3px 10px; border-radius:999px; font-weight:700; }
.status-draft { background:#fef3c7; color:#92400e; }
.status-approved { background:#dbeafe; color:#1e40af; }
.status-paid { background:#dcfce7; color:#166534; }
@media print {
  .slip-wrap { max-width:100%; }
  .pay-slip { border:none; padding:0; box-shadow:none; }
  @page { size: A4; margin: 12mm; }
}
</style>
<div class="slip-wrap">
  <div class="no-print slip-actions">
    <a href="?format=pdf" class="btn"><i class="fa-solid fa-file-pdf"></i> تنزيل PDF</a>
    <button class="btn" onclick="window.print()"><i class="fa-solid fa-print"></i> طباعة</button>
  </div>
  <div class="pay-slip">
    <div class="slip-header">
      <div class="brand">
        <img src="/assets/images/logo.webp" alt="Top Organic">
        <span class="brand-name">Top Organic</span>
      </div>
      <div class="doc-title">
        <h2>قسيمة راتب</h2>
        <div class="period">الفترة: <?= Helpers::e($payroll['period']) ?> &middot; تاريخ الطباعة: <?= date('Y-m-d') ?></div>
      </div>
    </div>

    <div class="slip-meta">
      <div><span class="k">اسم الموظف</span><span class="v"><?= Helpers::e($payroll['full_name']) ?></span></div>
      <div><span class="k">الرقم الوظيفي</span><span class="v"><?= Helpers::e($payroll['emp_code']) ?></span></div>
      <div><span class="k">الحالة</span><span class="v"><span class="slip-status-badge status-<?= Helpers::e($payroll['status']) ?>"><?= $statusLabels[$payroll['status']] ?? $payroll['status'] ?></span></span></div>
      <div><span class="k">القسم</span><span class="v"><?= Helpers::e($payroll['department_name'] ?? ($payroll['department'] ?? '-')) ?></span></div>
      <div><span class="k">المنصب</span><span class="v"><?= Helpers::e($payroll['job_title'] ?? ($payroll['position'] ?? '-')) ?></span></div>
      <div><span class="k">الهاتف</span><span class="v"><?= Helpers::e($payroll['phone'] ?? '-') ?></span></div>
    </div>

    <div class="slip-columns">
      <div class="slip-section earnings">
        <h4><i class="fa-solid fa-plus"></i> الاستحقاقات</h4>
        <table>
          <tr><td>الراتب الأساسي</td><td><?= Helpers::money($payroll['base_salary']) ?></td></tr>
          <?php foreach ($allowances as $a): ?>
          <tr><td>بدل <?= Helpers::e($a['allowance_type']) ?></td><td><?= Helpers::money($a['amount']) ?></td></tr>
          <?php endforeach; ?>
          <?php if ($payroll['bonuses'] > 0): ?><tr><td>المكافآت</td><td><?= Helpers::money($payroll['bonuses']) ?></td></tr><?php endif; ?>
          <?php if (($payroll['travel_tickets'] ?? 0) > 0): ?><tr><td>تذاكر سفر</td><td><?= Helpers::money($payroll['travel_tickets']) ?></td></tr><?php endif; ?>
          <?php if (($payroll['tips_addition'] ?? 0) > 0): ?><tr><td>إضافي تيبس</td><td><?= Helpers::money($payroll['tips_addition']) ?></td></tr><?php endif; ?>
          <?php if (($payroll['work_days_settlement'] ?? 0) > 0): ?><tr><td>تسوية أيام دوام</td><td><?= Helpers::money($payroll['work_days_settlement']) ?></td></tr><?php endif; ?>
          <?php foreach ($overtime as $o): ?>
          <tr><td>إضافي <?= $o['hours'] ?> س × <?= $o['multiplier'] ?></td><td><?= Helpers::money($o['amount']) ?></td></tr>
          <?php endforeach; ?>
          <tr class="total-row"><td>إجمالي الاستحقاقات</td><td><?= Helpers::money($payroll['total_earnings']) ?></td></tr>
        </table>
      </div>
      <div class="slip-section deductions">
        <h4><i class="fa-solid fa-minus"></i> الاستقطاعات</h4>
        <table>
          <?php $shortTermAdvances = (float)$payroll['advances'] - (float)($payroll['long_term_advances'] ?? 0); ?>
          <?php if ($payroll['deductions'] > 0): ?><tr><td>الخصومات</td><td><?= Helpers::money($payroll['deductions']) ?></td></tr><?php endif; ?>
          <?php if ($payroll['penalties'] > 0): ?><tr><td>الجزاءات</td><td><?= Helpers::money($payroll['penalties']) ?></td></tr><?php endif; ?>
          <?php if ($shortTermAdvances > 0): ?><tr><td>سلف قصيرة الأجل</td><td><?= Helpers::money($shortTermAdvances) ?></td></tr><?php endif; ?>
          <?php if (($payroll['long_term_advances'] ?? 0) > 0): ?><tr><td>سلف طويلة الأجل</td><td><?= Helpers::money($payroll['long_term_advances']) ?></td></tr><?php endif; ?>
          <?php if (($payroll['security_deposit'] ?? 0) > 0): ?><tr><td>التأمينات المستقطعة</td><td><?= Helpers::money($payroll['security_deposit']) ?></td></tr><?php endif; ?>
          <?php if (($payroll['loadings'] ?? 0) > 0): ?><tr><td>التحميلات الشهرية</td><td><?= Helpers::money($payroll['loadings']) ?></td></tr><?php endif; ?>
          <?php if (($payroll['tips'] ?? 0) > 0): ?><tr><td>التيبس</td><td><?= Helpers::money($payroll['tips']) ?></td></tr><?php endif; ?>
          <?php if (($payroll['social_security'] ?? 0) > 0): ?><tr><td>الضمان الاجتماعي</td><td><?= Helpers::money($payroll['social_security']) ?></td></tr><?php endif; ?>
          <?php if (($payroll['housing_deduction'] ?? 0) > 0): ?><tr><td>خدمات السكن</td><td><?= Helpers::money($payroll['housing_deduction']) ?></td></tr><?php endif; ?>
          <?php if (($payroll['rounding_adjustment'] ?? 0) != 0): ?><tr><td>فرق التقريب</td><td><?= Helpers::money($payroll['rounding_adjustment']) ?></td></tr><?php endif; ?>
          <?php if ($payroll['absence_deduction'] > 0): ?><tr><td>غيابات (<?= (int)$payroll['absence_days'] ?> يوم<?= ((int)($payroll['unpaid_leave_days'] ?? 0) > 0) ? '، منها ' . (int)$payroll['unpaid_leave_days'] . ' بدون راتب' : '' ?>)</td><td><?= Helpers::money($payroll['absence_deduction']) ?></td></tr><?php endif; ?>
          <?php if ($payroll['tax_amount'] > 0): ?><tr><td>الضريبة</td><td><?= Helpers::money($payroll['tax_amount']) ?></td></tr><?php endif; ?>
          <tr class="total-row"><td>إجمالي الاستقطاعات</td><td><?= Helpers::money($payroll['total_deductions']) ?></td></tr>
        </table>
      </div>
    </div>

    <div class="slip-net">
      <span class="label">صافي الراتب المستحق للصرف</span>
      <span class="value"><?= Helpers::money($payroll['net_salary']) ?></span>
    </div>

    <?php if ((int)($payroll['expected_days'] ?? 0) > 0 || (int)($payroll['working_days'] ?? 0) > 0): ?>
    <div class="slip-attendance">
      <table>
        <tr><th>أيام العمل المتوقعة</th><th>أيام الدوام</th><th>الإجازات المعتمدة</th><th>إجازة بدون راتب</th><th>أيام الغياب</th></tr>
        <tr>
          <td><?= (int)($payroll['expected_days'] ?? 0) ?></td>
          <td><?= (int)($payroll['working_days'] ?? 0) ?></td>
          <td><?= (int)($payroll['leave_days'] ?? 0) ?></td>
          <td><?= (int)($payroll['unpaid_leave_days'] ?? 0) ?></td>
          <td><?= (int)($payroll['absence_days'] ?? 0) ?></td>
        </tr>
      </table>
    </div>
    <?php endif; ?>

    <div class="slip-footer">
      <div class="sign-box"><div class="sign-line">توقيع الموظف</div></div>
      <div class="sign-box"><div class="sign-line">توقيع الإدارة المالية</div></div>
    </div>
  </div>
</div>
</content>
</invoke>
