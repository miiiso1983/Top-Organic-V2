<?php use App\Core\Helpers; ?>
<?php
$statusLabels = ['new' => 'جديد', 'reviewed' => 'تمت المراجعة', 'shortlisted' => 'قائمة مختصرة', 'rejected' => 'مرفوض', 'hired' => 'تم التوظيف'];
$yesNo = fn($v) => $v ? 'نعم' : 'لا';
?>
<div class="page-head">
  <h2><i class="fa-solid fa-user"></i> طلب توظيف: <?= Helpers::e($applicant['full_name']) ?></h2>
  <a href="/hr/recruitment/jobs/<?= $applicant['job_id'] ?>" class="btn btn-secondary">رجوع لقائمة المتقدمين</a>
</div>

<div class="card">
  <h3>البيانات الأساسية</h3>
  <table>
    <tr><th style="width:220px">رقم الاستمارة</th><td><?= Helpers::e($applicant['application_no']) ?></td></tr>
    <tr><th>الوظيفة المستهدفة</th><td><?= Helpers::e($applicant['job_title']) ?></td></tr>
    <tr><th>الفرع</th><td><?= $applicant['branch_name'] ? Helpers::e($applicant['branch_name']) : '—' ?></td></tr>
    <tr><th>الاسم الثلاثي واللقب</th><td><?= Helpers::e($applicant['full_name']) ?></td></tr>
    <tr><th>رقم الهاتف</th><td dir="ltr"><?= Helpers::e($applicant['phone']) ?></td></tr>
    <tr><th>العمر</th><td><?= Helpers::e((string)($applicant['age'] ?? '—')) ?></td></tr>
    <tr><th>الجنسية</th><td><?= Helpers::e($applicant['nationality'] ?: '—') ?></td></tr>
    <tr><th>الحالة الاجتماعية</th><td><?= Helpers::e($applicant['marital_status'] ?: '—') ?></td></tr>
    <tr><th>محل السكن</th><td><?= Helpers::e($applicant['residence'] ?: '—') ?></td></tr>
    <tr><th>التخصص الدراسي</th><td><?= Helpers::e($applicant['education_major'] ?: '—') ?></td></tr>
    <tr><th>سنة التخرج</th><td><?= Helpers::e($applicant['graduation_year'] ?: '—') ?></td></tr>
    <tr><th>إجمالي سنوات الخبرة</th><td><?= Helpers::e($applicant['total_experience_years'] ?: '—') ?></td></tr>
    <tr><th>الراتب المتوقع</th><td><?= $applicant['expected_salary'] !== null ? number_format((float)$applicant['expected_salary'], 0) : '—' ?></td></tr>
    <tr><th>خبرة سابقة في المطاعم/الفنادق</th><td><?= $yesNo($applicant['has_restaurant_experience']) ?></td></tr>
    <tr><th>أقارب يعملون في الشركة</th><td><?= $yesNo($applicant['has_relatives_in_company']) ?></td></tr>
    <tr><th>متفرغ للعمل حالياً</th><td><?= $yesNo($applicant['is_available_now']) ?></td></tr>
    <tr><th>تاريخ التقديم</th><td><?= date('Y-m-d H:i', strtotime($applicant['created_at'])) ?></td></tr>
    <tr><th>السيرة الذاتية</th><td>
      <?php if ($applicant['cv_path']): ?>
        <a href="<?= Helpers::e($applicant['cv_path']) ?>" target="_blank" class="btn btn-sm btn-secondary"><i class="fa-solid fa-file-arrow-down"></i> عرض / تحميل السيرة الذاتية</a>
      <?php else: ?>لم يُرفق ملف<?php endif; ?>
    </td></tr>
  </table>
</div>

<?php if ($experiences): ?>
<div class="card">
  <h3>الخبرات العملية السابقة</h3>
  <table>
    <thead><tr><th>ت</th><th>اسم الشركة أو مكان العمل</th><th>المسمى الوظيفي</th><th>مدة العمل</th><th>الراتب الأخير</th><th>سبب ترك العمل</th></tr></thead>
    <tbody>
      <?php foreach ($experiences as $i => $e): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><?= Helpers::e($e['company'] ?? '') ?></td>
        <td><?= Helpers::e($e['title'] ?? '') ?></td>
        <td><?= Helpers::e($e['duration'] ?? '') ?></td>
        <td><?= Helpers::e($e['last_salary'] ?? '') ?></td>
        <td><?= Helpers::e($e['leave_reason'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <h3>حالة الطلب</h3>
  <form method="post" action="/hr/recruitment/applications/<?= $applicant['id'] ?>/status" class="form-inline">
    <div class="form-row">
      <label>الحالة</label>
      <select name="status">
        <?php foreach ($statusLabels as $val => $label): ?>
        <option value="<?= $val ?>" <?= $applicant['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row" style="flex:3">
      <label>ملاحظات داخلية (لا تظهر للمتقدم)</label>
      <input type="text" name="hr_notes" value="<?= Helpers::e($applicant['hr_notes'] ?? '') ?>">
    </div>
    <button class="btn btn-success">حفظ</button>
  </form>
</div>
