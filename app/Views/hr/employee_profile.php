<?php use App\Core\Helpers; ?>
<?php use App\Core\Auth; ?>
<?php
$isModal = !empty($_GET['modal']);
$modalQuery = $isModal ? '?modal=1' : '';
$editVersion = @filemtime(__DIR__ . '/employees_edit.php') ?: '2';
$editQuery = $modalQuery ? $modalQuery . '&v=' . $editVersion : '?v=' . $editVersion;
$empStatus = ['active'=>'نشط','vacation'=>'إجازة','suspended'=>'موقوف','terminated'=>'منتهي الخدمة','resigned'=>'مستقيل'];
$statusClass = ['active'=>'success','vacation'=>'warning','suspended'=>'secondary','terminated'=>'danger','resigned'=>'info'];
$fileStatus = ['open'=>'مفتوح','closed'=>'مغلق','archived'=>'مأرشف'];
$fileStatusClass = ['open'=>'success','closed'=>'danger','archived'=>'secondary'];
$contractType = ['fixed'=>'محدد المدة','open'=>'مفتوح','project'=>'مشروع','part_time'=>'دوام جزئي'];
$contractStatus = ['active'=>'نشط','expired'=>'منتهي','terminated'=>'فسخ'];
$requestType = ['leave'=>'إجازة','exit'=>'إذن خروج','delay'=>'تأخير','work_resumption'=>'مباشرة عمل'];
$leaveType = ['annual'=>'سنوية','sick'=>'مرضية','unpaid'=>'بدون راتب','emergency'=>'طارئة','hourly'=>'ساعية','n/a'=>'غير محدد'];
$adjustType = ['bonus'=>'مكافأة','deduction'=>'خصم','raise'=>'زيادة','penalty'=>'جزاء','tips_addition'=>'إضافي تيبس','salary_reduction'=>'تخفيض راتب','work_days_settlement'=>'تسوية أيام دوام'];
$adjustStatusLabel = ['pending'=>'معلق','approved'=>'معتمد','rejected'=>'مرفوض','cancelled'=>'ملغاة'];
$advanceType = ['short'=>'قصيرة الأجل','long'=>'طويلة الأجل'];
$advanceStatus = ['pending'=>'معلقة','approved'=>'معتمدة','rejected'=>'مرفوضة','paid'=>'مدفوعة','settled'=>'مغلقة'];
$evalType = ['monthly'=>'شهري','quarterly'=>'ربع سنوي','half_yearly'=>'نصف سنوي','annual'=>'سنوي'];
$warningType = ['verbal'=>'شفهي','written'=>'خطي','final'=>'نهائي'];
?>

<?php if (!empty($_GET['close'])): ?>
<script>
(function(){
  try {
    if (window.parent && window.parent !== window && typeof window.parent.closeEmployeeModal === 'function') {
      window.parent.closeEmployeeModal();
      return;
    }
  } catch (e) {}
  try { window.close(); } catch (e) {}
  // Fallback: navigate back to employees list if closing fails
  if (window.location.search.indexOf('close=1') !== -1) {
    window.location.href = '/hr/employees';
  }
})();
</script>
<?php endif; ?>

<div class="page-head">
  <div>
    <h2 style="margin:0"><i class="fa-solid fa-user"></i> <?= Helpers::e($employee['full_name']) ?> <small style="color:var(--text-muted);font-weight:normal">(<?= Helpers::e($employee['emp_code']) ?>)</small></h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
      <span class="badge badge-<?= $statusClass[$employee['status']] ?? 'info' ?>"><?= $empStatus[$employee['status']] ?? $employee['status'] ?></span>
      <span class="badge badge-<?= $fileStatusClass[$employee['file_status']] ?? 'secondary' ?>"><?= $fileStatus[$employee['file_status']] ?? $employee['file_status'] ?></span>
    </div>
  </div>
  <div class="toolbar">
    <?php if ($isModal): ?>
    <button type="button" class="btn btn-sm btn-secondary" onclick="if(window.parent && window.parent.minimizeEmployeeModal){ window.parent.minimizeEmployeeModal(); } else { window.close(); }">
      <i class="fa-solid fa-minus"></i> تصغير
    </button>
    <button type="button" class="btn btn-sm btn-danger" onclick="if(window.parent && window.parent.closeEmployeeModal){ window.parent.closeEmployeeModal(); } else { window.close(); }">
      <i class="fa-solid fa-xmark"></i> إغلاق
    </button>
    <?php else: ?>
    <a href="/hr/team?employee=<?= (int)$employee['id'] ?>" class="btn btn-sm">التنبيهات والإنذارات وأرشيف الإجراءات</a>
    <a href="/hr/employees" class="btn btn-sm btn-secondary"><i class="fa-solid fa-arrow-right"></i> الموظفون</a>
    <?php endif; ?>
    <?php if (Auth::can('hr.manage')): ?>
    <a href="/hr/employees/<?= $employee['id'] ?>/edit<?= $editQuery ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-pen-to-square"></i> تعديل البيانات</a>
    <?php endif; ?>
    <?php if (Auth::can('hr.employee.documents')): ?>
    <a href="/hr/employees/<?= $employee['id'] ?>/documents<?= $modalQuery ?>" class="btn btn-sm btn-info"><i class="fa-solid fa-paperclip"></i> المستندات</a>
    <?php endif; ?>
    <?php if (Auth::can('hr.employee.audit')): ?>
    <a href="/hr/employees/<?= $employee['id'] ?>/audit<?= $modalQuery ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-magnifying-glass"></i> التدقيق</a>
    <?php endif; ?>
    <?php if (Auth::can('hr.custody.view')): ?>
    <a href="/hr/custody/employee/<?= $employee['id'] ?><?= $modalQuery ?>" class="btn btn-sm btn-info"><i class="fa-solid fa-box"></i> العهدة</a>
    <?php endif; ?>
    <button type="button" class="btn btn-sm btn-success" onclick="window.print()"><i class="fa-solid fa-print"></i> طباعة</button>
  </div>
</div>

<?php if (Auth::can('hr.manage')): ?>
<div class="card">
  <div class="toolbar" style="justify-content:space-between;align-items:center">
    <form method="post" action="/hr/employees/<?= $employee['id'] ?>/toggle<?= $modalQuery ?>" class="form-inline" onsubmit="return confirm('تغيير حالة الملف؟')">
      <span style="font-size:14px">حالة الملف:</span>
      <select name="file_status" style="width:auto">
        <option value="open" <?= $employee['file_status'] === 'open' ? 'selected' : '' ?>>مفتوح</option>
        <option value="closed" <?= $employee['file_status'] === 'closed' ? 'selected' : '' ?>>مغلق</option>
        <option value="archived" <?= $employee['file_status'] === 'archived' ? 'selected' : '' ?>>مأرشف</option>
      </select>
      <input type="text" name="reason" placeholder="السبب (اختياري)" style="width:160px">
      <button class="btn btn-sm btn-warning">حفظ</button>
    </form>
    <form method="post" action="/hr/employees/<?= $employee['id'] ?>/delete<?= $modalQuery ?>" style="display:inline" onsubmit="return confirm('هل أنت متأكد من حذف الموظف <?= Helpers::e($employee['full_name']) ?>؟ لا يمكن التراجع.')">
      <button class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i> حذف الموظف</button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h4 class="section-title"><i class="fa-solid fa-address-card"></i> البيانات الشخصية</h4>
  <div class="info-grid">
    <div class="info-item"><span class="info-label">الكود</span><span class="info-value"><?= Helpers::e($employee['emp_code']) ?></span></div>
    <div class="info-item"><span class="info-label">الاسم الكامل</span><span class="info-value"><?= Helpers::e($employee['full_name']) ?></span></div>
    <div class="info-item"><span class="info-label">الهاتف</span><span class="info-value"><?= Helpers::e($employee['phone'] ?? '-') ?></span></div>
    <div class="info-item"><span class="info-label">البريد الإلكتروني</span><span class="info-value"><?= Helpers::e($employee['email'] ?? '-') ?></span></div>
    <div class="info-item"><span class="info-label">الوظيفة</span><span class="info-value"><?= Helpers::e($employee['job_title'] ?? $employee['position'] ?? '-') ?></span></div>
    <div class="info-item"><span class="info-label">القسم</span><span class="info-value"><?= Helpers::e($employee['department_name'] ?? $employee['department'] ?? '-') ?></span></div>
    <div class="info-item"><span class="info-label">المدير المباشر</span><span class="info-value"><?= Helpers::e($employee['manager_name'] ?? '-') ?></span></div>
    <div class="info-item"><span class="info-label">الفرع</span><span class="info-value"><?= Helpers::e($employee['branch_name'] ?? '-') ?></span></div>
    <?php if ($canViewSalary): ?>
    <div class="info-item"><span class="info-label">الراتب الأساسي</span><span class="info-value"><?= Helpers::money($adjustedSalary ?? $employee['base_salary'] ?? $employee['salary'] ?? 0) ?></span></div>
    <?php $housingTypeLabels = ['external'=>'سكن خارجي','yarmouk'=>'سكن اليرموك','sebti'=>'سكن سبتي','qadisiya'=>'سكن القادسية','internal'=>'سكن الداخلي']; ?>
    <div class="info-item"><span class="info-label">نوع السكن</span><span class="info-value"><?= $housingTypeLabels[$employee['housing_type'] ?? ''] ?? '-' ?></span></div>
    <div class="info-item"><span class="info-label">استقطاع خدمات السكن الشهري</span><span class="info-value"><?= Helpers::money($employee['housing_monthly_deduction'] ?? 0) ?></span></div>
    <div class="info-item"><span class="info-label">نسبة/مرات التأمين الشهري</span><span class="info-value"><?= Helpers::e($employee['insurance_percentage'] ?? 0) ?>% × <?= (int)($employee['insurance_occurrences'] ?? 0) ?></span></div>
    <?php endif; ?>
    <div class="info-item"><span class="info-label">رصيد تأمين المستحقات</span><span class="info-value"><?= Helpers::money($securityDepositBalance) ?></span></div>
    <div class="info-item"><span class="info-label">تاريخ التعيين</span><span class="info-value"><?= Helpers::e($employee['hire_date'] ?? '-') ?></span></div>
    <div class="info-item"><span class="info-label">حساب الدخول</span><span class="info-value"><?= Helpers::e($employee['username'] ?? '-') ?></span></div>
    <div class="info-item"><span class="info-label">المستوى الإداري</span><span class="info-value"><?= (int)($employee['admin_level'] ?? 0) ?></span></div>
  </div>
</div>

<?php if ($canViewSalary && !empty($resignation)): ?>
<div class="card">
  <h4><i class="fa-solid fa-file-signature"></i> بيانات الاستقالة والمخالصة</h4>
  <?php $breakdown = json_decode($resignation['calculation_breakdown_json'] ?? '', true) ?? []; ?>
  <table class="detail-table">
    <tr><td>تاريخ الاستقالة</td><td><?= Helpers::e($resignation['resignation_date']) ?></td><td>عدد أيام العمل</td><td><?= Helpers::e($resignation['days_worked']) ?> يوم</td></tr>
    <tr><td>الراتب اليومي</td><td><?= Helpers::money($resignation['daily_salary']) ?></td><td>صافي الاستحقاق</td><td><strong><?= Helpers::money($resignation['total_due']) ?></strong></td></tr>
    <tr><td>سبب الاستقالة</td><td colspan="3"><?= nl2br(Helpers::e($resignation['reason'] ?: '-')) ?></td></tr>
    <tr><td>ملاحظات المخالصة</td><td colspan="3"><?= nl2br(Helpers::e($resignation['notes'] ?: '-')) ?></td></tr>
  </table>
  <?php if (!empty($breakdown)): ?>
  <h5 style="margin-top:16px">تفاصيل حساب الاستحقاق</h5>
  <table>
    <tr><th>البند</th><th>المبلغ</th></tr>
    <tr><td>الراتب</td><td><?= Helpers::money($settlementSalary ?? ($breakdown['salary_amount'] ?? 0)) ?></td></tr>
    <tr><td>استقطاع الغياب (<?= Helpers::e($breakdown['absence_days'] ?? 0) ?> يوم)</td><td><?= Helpers::money($breakdown['absence_deduction'] ?? 0) ?></td></tr>
    <tr><td>مبلغ الإضافي</td><td><?= Helpers::money($breakdown['overtime_amount'] ?? 0) ?></td></tr>
    <tr><td>السلف</td><td><?= Helpers::money($breakdown['advances_amount'] ?? 0) ?></td></tr>
    <tr><td>استقطاعات السكن</td><td><?= Helpers::money($breakdown['housing_deduction'] ?? 0) ?></td></tr>
    <tr><td>الخصومات</td><td><?= Helpers::money($breakdown['other_deductions'] ?? 0) ?></td></tr>
  </table>
  <?php endif; ?>
  <?php if (!empty($resignation['custody_summary_json'])): ?>
  <h5 style="margin-top:16px">حالة العهدة</h5>
  <div style="overflow:auto">
    <table>
      <tr><th>الصنف</th><th>الكمية</th><th>حالة التسليم</th></tr>
      <?php foreach (json_decode($resignation['custody_summary_json'], true) ?? [] as $rc): ?>
      <tr>
        <td><?= Helpers::e($rc['item_name'] ?? '-') ?></td>
        <td><?= Helpers::e($rc['qty'] ?? '-') ?></td>
        <td><span class="badge badge-<?= ($rc['returned'] ?? false) ? 'success' : 'warning' ?>"><?= ($rc['returned'] ?? false) ? 'تم التسليم' : 'لم يُسلّم' ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <h4><i class="fa-solid fa-face-smile"></i> الصورة المرجعية للوجه</h4>
  <?php if (!empty($employee['reference_photo'])): ?>
    <div style="margin-bottom:12px">
      <a href="<?= $employee['reference_photo'] ?>" target="_blank">
        <img src="<?= $employee['reference_photo'] ?>" style="max-width:220px;max-height:220px;border-radius:8px;border:1px solid #ddd">
      </a>
    </div>
    <?php if (Auth::can('hr.manage')): ?>
    <form method="post" action="/hr/employees/<?= $employee['id'] ?>/reference-photo/delete<?= $modalQuery ?>" style="display:inline" onsubmit="return confirm('هل تريد حذف الصورة المرجعية وطلب صورة جديدة من الموظف؟')">
      <button class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i> حذف وطلب صورة جديدة</button>
    </form>
    <?php endif; ?>
  <?php else: ?>
    <div class="alert alert-info">لا توجد صورة مرجعية للوجه. سيطلب التطبيق من الموظف رفع صورة عند أول بصمة.</div>
  <?php endif; ?>
</div>

<div class="card" id="contracts">
  <h4><i class="fa-solid fa-file"></i> العقود</h4>
  <?php if (Auth::can('hr.manage')): ?>
  <form method="post" action="/hr/employees/<?= $employee['id'] ?>/contracts<?= $modalQuery ?>" enctype="multipart/form-data" class="form-inline" style="margin-bottom:15px">
    <select name="contract_type"><option value="fixed">محدد</option><option value="open">مفتوح</option><option value="project">مشروع</option><option value="part_time">جزئي</option></select>
    <input type="date" name="start_date" required placeholder="من">
    <input type="date" name="end_date" placeholder="إلى">
    <input type="number" step="0.01" name="salary" placeholder="الراتب" required>
    <input type="text" name="position" placeholder="المنصب">
    <select name="department_id">
      <option value="">— القسم —</option>
      <?php foreach ($departments as $d): ?>
      <option value="<?= $d['id'] ?>" <?= ($employee['department_id'] ?? null) == $d['id'] ? 'selected' : '' ?>><?= Helpers::e($d['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="benefits" placeholder="المزايا">
    <input type="file" name="file" accept=".pdf,.jpg,.png">
    <button class="btn btn-success btn-sm">إضافة عقد</button>
  </form>
  <?php endif; ?>
  <table>
    <tr>
      <th>النوع</th><th>من</th><th>إلى</th>
      <?php if ($canViewSalary): ?><th>الراتب</th><?php endif; ?>
      <th>المنصب</th><th>الحالة</th><th></th>
    </tr>
    <?php foreach ($contracts as $c): ?>
    <tr>
      <td><?= $contractType[$c['contract_type']] ?? $c['contract_type'] ?></td>
      <td><?= $c['start_date'] ?></td>
      <td><?= $c['end_date'] ?? '-' ?></td>
      <?php if ($canViewSalary): ?><td><?= Helpers::money($c['salary']) ?></td><?php endif; ?>
      <td><?= Helpers::e($c['position'] ?? '-') ?></td>
      <td><?= $contractStatus[$c['status']] ?? $c['status'] ?></td>
      <td><?php if ($c['file_path']): ?><a href="<?= Helpers::e($c['file_path']) ?>" target="_blank" class="btn btn-sm btn-info">عرض</a><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$contracts): ?><tr><td colspan="<?= $canViewSalary ? 7 : 6 ?>">لا توجد عقود</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h4><i class="fa-solid fa-paperclip"></i> المستندات</h4>
  <table>
    <tr><th>النوع</th><th>العنوان</th><th>الإصدار</th><th>الانتهاء</th><th></th></tr>
    <?php foreach ($documents as $d): ?>
    <tr>
      <td><?= Helpers::e($d['doc_type']) ?></td>
      <td><?= Helpers::e($d['title']) ?></td>
      <td><?= $d['issue_date'] ?? '-' ?></td>
      <td><?= $d['expiry_date'] ?? '-' ?></td>
      <td><?php if ($d['file_path']): ?><a href="<?= Helpers::e($d['file_path']) ?>" target="_blank" class="btn btn-sm btn-info">عرض</a><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$documents): ?><tr><td colspan="5">لا توجد مستندات</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h4><i class="fa-solid fa-coins"></i> السلف</h4>
  <table>
    <tr><th>مبلغ السلفة الإجمالي</th><th>المبلغ المدفوع</th><th>المبلغ المتبقي</th><th>الحالة</th><th>التاريخ</th></tr>
    <?php foreach ($advances as $a): ?>
    <tr>
      <td><?= Helpers::money($a['amount']) ?></td>
      <td><?= Helpers::money($a['paid_amount'] ?? 0) ?></td>
      <td><?= Helpers::money($a['remaining_amount'] ?? 0) ?></td>
      <td><span class="badge badge-<?= $a['status']==='settled'?'success':($a['status']==='rejected'?'danger':'warning') ?>"><?= $advanceStatus[$a['status']] ?? $a['status'] ?></span></td>
      <td><?= $a['created_at'] ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$advances): ?><tr><td colspan="5">لا توجد سلف</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h4><i class="fa-solid fa-shield-halved"></i> تأمين المستحقات</h4>
  <p><strong>الرصيد المحبوس:</strong> <?= Helpers::money($securityDepositBalance) ?></p>
  <table>
    <tr><th>الفترة</th><th>مرتب الفترة</th><th>المبلغ</th><th>الحالة</th><th>تاريخ الإرجاع</th></tr>
    <?php foreach ($securityDeposits as $d): ?>
    <tr>
      <td><?= Helpers::e($d['period'] ?? '-') ?></td>
      <td><?= Helpers::e($d['payroll_period'] ?? '-') ?></td>
      <td><?= Helpers::money($d['amount']) ?></td>
      <td><span class="badge badge-<?= $d['status']==='held'?'warning':'success' ?>"><?= $d['status']==='held'?'محبوس':'مُرجَّع' ?></span></td>
      <td><?= $d['refunded_at'] ? Helpers::e($d['refunded_at']) : '-' ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$securityDeposits): ?><tr><td colspan="5">لا توجد سجلات تأمين</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h4><i class="fa-solid fa-plane"></i> تذاكر السفر</h4>
  <table>
    <tr><th>الفترة</th><th>الذهاب</th><th>العودة</th><th>المدة</th><th>الطريقة</th><th>المبلغ</th><th>ملاحظات</th></tr>
    <?php foreach ($travelTickets as $t): ?>
    <tr>
      <td><?= Helpers::e($t['period']) ?></td>
      <td><?= Helpers::e($t['departure_date'] ?? '-') ?></td>
      <td><?= Helpers::e($t['return_date'] ?? '-') ?></td>
      <td><?= Helpers::e($t['duration'] ?? '-') ?></td>
      <td><?= Helpers::e($t['travel_method'] ?? '-') ?></td>
      <td><?= Helpers::money($t['amount']) ?></td>
      <td><?= Helpers::e($t['notes'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$travelTickets): ?><tr><td colspan="7">لا توجد تذاكر سفر مسجلة</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h4><i class="fa-solid fa-weight-hanging"></i> التحميلات الشهرية</h4>
  <table>
    <tr><th>الفترة</th><th>الفئة</th><th>المبلغ</th><th>ملاحظات</th></tr>
    <?php foreach ($loadings as $l): ?>
    <tr>
      <td><?= Helpers::e($l['period']) ?></td>
      <td><?= Helpers::e($l['category']) ?></td>
      <td><?= Helpers::money($l['amount']) ?></td>
      <td><?= Helpers::e($l['notes'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$loadings): ?><tr><td colspan="4">لا توجد تحميلات مسجلة</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h4><i class="fa-solid fa-coins"></i> التيبس الشهري</h4>
  <table>
    <tr><th>الفترة</th><th>المبلغ الكامل</th><th>أيام الدوام</th><th>المبلغ المتناسب</th><th>ملاحظات</th></tr>
    <?php foreach ($tips as $t): ?>
    <tr>
      <td><?= Helpers::e($t['period']) ?></td>
      <td><?= Helpers::money($t['full_amount']) ?></td>
      <td><?= Helpers::e($t['working_days']) ?></td>
      <td><?= Helpers::money($t['prorated_amount']) ?></td>
      <td><?= Helpers::e($t['notes'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$tips): ?><tr><td colspan="5">لا يوجد تيبس مسجل</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h4><i class="fa-solid fa-hand-holding-dollar"></i> الضمان الاجتماعي الشهري</h4>
  <table>
    <tr><th>الفترة</th><th>المبلغ</th><th>ملاحظات</th></tr>
    <?php foreach ($socialSecurity as $s): ?>
    <tr>
      <td><?= Helpers::e($s['period']) ?></td>
      <td><?= Helpers::money($s['amount']) ?></td>
      <td><?= Helpers::e($s['notes'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$socialSecurity): ?><tr><td colspan="3">لا يوجد ضمان اجتماعي مسجل</td></tr><?php endif; ?>
  </table>
</div>



<?php if (Auth::can('hr.payslip.view')): ?>
<div class="card">
  <h4><i class="fa-solid fa-cash-register"></i> الرواتب</h4>
  <table>
    <tr><th>الفترة</th><th>الأساسي</th><th>بدلات</th><th>إضافي</th><th>زيادات</th><th>مكافآت</th><th>تذاكر سفر</th><th>إضافي تيبس</th><th>خصومات</th><th>سلف</th><th>تأمين مستحقات</th><th>تأمينات</th><th>تحميلات</th><th>تيبس</th><th>ضمان اجتماعي</th><th>سكن</th><th>تقريب</th><th>ضريبة</th><th>الصافي</th><th>الحالة</th></tr>
    <?php foreach ($payrolls as $p): ?>
    <tr>
      <td><?= $p['period'] ?></td>
      <td><?= Helpers::money($p['base_salary']) ?></td>
      <td><?= Helpers::money($p['allowances'] ?? 0) ?></td>
      <td><?= Helpers::money($p['overtime'] ?? 0) ?></td>
      <td><?= Helpers::money($p['raises'] ?? 0) ?></td>
      <td><?= Helpers::money($p['bonuses'] ?? 0) ?></td>
      <td><?= Helpers::money($p['travel_tickets'] ?? 0) ?></td>
      <td><?= Helpers::money($p['tips_addition'] ?? 0) ?></td>
      <td><?= Helpers::money($p['deductions'] ?? 0) ?></td>
      <td><?= Helpers::money($p['advances'] ?? 0) ?></td>
      <td style="color:#dc2626">−<?= Helpers::money($p['security_deposit'] ?? 0) ?></td>
      <td style="color:#dc2626">−<?= Helpers::money($p['insurance'] ?? 0) ?></td>
      <td style="color:#dc2626">−<?= Helpers::money($p['loadings'] ?? 0) ?></td>
      <td style="color:#dc2626">−<?= Helpers::money($p['tips'] ?? 0) ?></td>
      <td style="color:#dc2626">−<?= Helpers::money($p['social_security'] ?? 0) ?></td>
      <td style="color:#dc2626">−<?= Helpers::money($p['housing_deduction'] ?? 0) ?></td>
      <td><?= Helpers::money($p['rounding_adjustment'] ?? 0) ?></td>
      <td><?= Helpers::money($p['tax_amount'] ?? 0) ?></td>
      <td><strong><?= Helpers::money($p['net_salary']) ?></strong></td>
      <td><?= $p['status'] ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$payrolls): ?><tr><td colspan="20">لا توجد مسيرات رواتب</td></tr><?php endif; ?>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <h4><i class="fa-solid fa-scale-balanced"></i> المكافآت والخصومات والزيادات والجزاءات</h4>
  <table>
    <tr><th>النوع</th><th>المبلغ</th><th>التاريخ</th><th>الفترة</th><th>السبب</th><th>الحالة</th></tr>
    <?php foreach ($adjustments as $a): ?>
    <tr>
      <td><?= $adjustType[$a['adjustment_type']] ?? $a['adjustment_type'] ?></td>
      <td><?= Helpers::money($a['amount']) ?></td>
      <td><?= $a['effective_date'] ?></td>
      <td><?= $a['period'] ?? '-' ?></td>
      <td><?= Helpers::e($a['reason'] ?? '-') ?></td>
      <td><span class="badge badge-<?= $a['status']==='approved'?'success':($a['status']==='rejected'?'danger':($a['status']==='cancelled'?'secondary':'warning')) ?>"><?= $adjustStatusLabel[$a['status']] ?? $a['status'] ?></span></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$adjustments): ?><tr><td colspan="6">لا توجد تسويات</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h4><i class="fa-solid fa-triangle-exclamation"></i> الإنذارات</h4>
  <table>
    <tr><th>النوع</th><th>السبب</th><th>التاريخ</th><th>الحالة</th></tr>
    <?php foreach ($warnings as $w): ?>
    <tr>
      <td><?= $warningType[$w['warning_type']] ?? $w['warning_type'] ?></td>
      <td><?= Helpers::e($w['reason']) ?></td>
      <td><?= $w['warning_date'] ?></td>
      <td><?= $w['status'] ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$warnings): ?><tr><td colspan="4">لا توجد إنذارات</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h4><i class="fa-solid fa-chart-column"></i> التقييمات</h4>
  <table>
    <tr><th>الفترة</th><th>النوع</th><th>KPI</th><th>المدير</th><th>الإدارة</th><th>الذاتي</th><th>الإجمالي</th><th>الحالة</th></tr>
    <?php foreach ($evaluations as $e): ?>
    <tr>
      <td><?= $e['period'] ?></td>
      <td><?= $evalType[$e['evaluation_type']] ?? $e['evaluation_type'] ?></td>
      <td><?= $e['kpi_score'] ?></td>
      <td><?= $e['manager_score'] ?></td>
      <td><?= $e['admin_score'] ?></td>
      <td><?= $e['self_score'] ?></td>
      <td><strong><?= $e['total_score'] ?></strong></td>
      <td><?= $e['status'] ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$evaluations): ?><tr><td colspan="8">لا توجد تقييمات</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h4><i class="fa-solid fa-magnifying-glass"></i> سجل التعديلات</h4>
  <table>
    <tr><th>الإجراء</th><th>الحقل</th><th>القديم</th><th>الجديد</th><th>المستخدم</th><th>التاريخ</th></tr>
    <?php foreach ($auditLogs as $l): ?>
    <tr>
      <td><?= Helpers::e($l['action']) ?></td>
      <td><?= Helpers::e($l['field_name'] ?? '-') ?></td>
      <td><?= Helpers::e($l['old_value'] ?? '-') ?></td>
      <td><?= Helpers::e($l['new_value'] ?? '-') ?></td>
      <td><?= Helpers::e($l['changed_by_name'] ?? '-') ?></td>
      <td><?= $l['created_at'] ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$auditLogs): ?><tr><td colspan="6">لا يوجد سجل تعديلات</td></tr><?php endif; ?>
  </table>
</div>

<style>
/* Profile page specific */
.page-head h2 small { font-size: 16px; }
.card h4 {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 14px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--border-light);
}
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
}
.info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px;
  background: var(--surface-alt);
  border-radius: 8px;
}
.info-label { font-size: 12px; color: var(--text-muted); }
.info-value { font-size: 15px; font-weight: 600; }

@media print {
  /* إخفاء العناصر غير الضرورية */
  .sidebar, .topbar, #ptt-fab { display: none !important; }
  .main { margin-right: 0 !important; }
  .content { padding: 10px !important; }

  /* إخفاء الأزرار والنماذج وشريط الإجراءات */
  .btn, .form-inline, .print-btn, .page-head .toolbar { display: none !important; }
  .info-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
  .info-item { background: none; border: 1px solid #999; padding: 8px; }

  /* تنسيق البطاقات للطباعة */
  .card {
    border: 1px solid #999;
    box-shadow: none;
    margin-bottom: 14px;
    page-break-inside: avoid;
    break-inside: avoid;
  }
  .card h3, .card h4 {
    font-size: 15px;
    margin-bottom: 8px;
    page-break-after: avoid;
  }

  /* تنسيق الجداول */
  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
  }
  th, td {
    border: 1px solid #666;
    padding: 5px 6px;
    text-align: right;
  }
  th {
    background: #f3f4f6;
  }
  tr {
    page-break-inside: avoid;
  }

  /* الصورة المرجعية */
  img {
    max-width: 160px !important;
    max-height: 160px !important;
  }

  /* تجنب تداخل البيانات عند فواصل الصفحات */
  thead { display: table-header-group; }
  tbody { display: table-row-group; }
}
</style>
