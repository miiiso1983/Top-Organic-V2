<?php use App\Core\Helpers; ?>
<?php
$statusLabels = ['new' => 'جديد', 'reviewed' => 'تمت المراجعة', 'shortlisted' => 'قائمة مختصرة', 'rejected' => 'مرفوض', 'hired' => 'تم التوظيف'];
$statusBadge = ['new' => 'badge-info', 'reviewed' => 'badge-secondary', 'shortlisted' => 'badge-warning', 'rejected' => 'badge-danger', 'hired' => 'badge-success'];
?>
<div class="page-head">
  <h2><i class="fa-solid fa-users"></i> المتقدمون لوظيفة: <?= Helpers::e($job['title']) ?></h2>
  <a href="/hr/recruitment" class="btn btn-secondary">رجوع</a>
</div>

<div class="card">
  <div class="form-row" style="max-width:360px">
    <label><i class="fa-solid fa-magnifying-glass"></i> بحث سريع (الاسم أو الهاتف)</label>
    <input type="text" id="applicant-search" placeholder="اكتب للبحث الفوري..." oninput="filterApplicants()">
  </div>

  <?php if (!$applicants): ?>
  <p style="text-align:center;color:var(--text-muted);padding:30px 0">لا يوجد متقدمون لهذه الوظيفة بعد.</p>
  <?php else: ?>
  <table class="table" id="applicantsTable">
    <thead>
      <tr>
        <th>رقم الاستمارة</th><th>الاسم</th><th>الهاتف</th><th>الفرع</th><th>سنوات الخبرة</th><th>تاريخ التقديم</th><th>الحالة</th><th>السيرة الذاتية</th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($applicants as $a): ?>
      <tr data-search="<?= Helpers::e(mb_strtolower($a['full_name'] . ' ' . $a['phone'])) ?>">
        <td><?= Helpers::e($a['application_no']) ?></td>
        <td><?= Helpers::e($a['full_name']) ?></td>
        <td dir="ltr"><?= Helpers::e($a['phone']) ?></td>
        <td><?= $a['branch_name'] ? Helpers::e($a['branch_name']) : '—' ?></td>
        <td><?= Helpers::e($a['total_experience_years'] ?: '—') ?></td>
        <td><?= date('Y-m-d', strtotime($a['created_at'])) ?></td>
        <td><span class="badge <?= $statusBadge[$a['status']] ?? 'badge-secondary' ?>"><?= $statusLabels[$a['status']] ?? $a['status'] ?></span></td>
        <td><?php if ($a['cv_path']): ?><a href="<?= Helpers::e($a['cv_path']) ?>" target="_blank"><i class="fa-solid fa-file-pdf"></i> عرض</a><?php else: ?>—<?php endif; ?></td>
        <td><a href="/hr/recruitment/applications/<?= $a['id'] ?>" class="btn btn-sm btn-secondary">التفاصيل</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p id="noApplicantsMatch" style="display:none;text-align:center;color:var(--text-muted);padding:20px 0">لا توجد نتائج مطابقة للبحث.</p>
  <?php endif; ?>
</div>

<script>
function filterApplicants() {
  var q = document.getElementById('applicant-search').value.trim().toLowerCase();
  var rows = document.querySelectorAll('#applicantsTable tbody tr');
  var visible = 0;
  rows.forEach(function (row) {
    var match = row.dataset.search.indexOf(q) !== -1;
    row.style.display = match ? '' : 'none';
    if (match) visible++;
  });
  document.getElementById('noApplicantsMatch').style.display = (visible === 0) ? '' : 'none';
}
</script>
