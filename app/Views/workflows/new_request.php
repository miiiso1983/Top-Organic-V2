<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2>طلب جديد</h2>
  <a href="/workflows" class="btn btn-secondary">رجوع</a>
</div>

<div class="grid grid-3">
  <a href="/maintenance/create" class="card stat" style="text-decoration:none; border: 2px solid #16a34a">
    <div class="value" style="font-size:32px"><i class="fa-solid fa-screwdriver-wrench"></i></div>
    <div class="label" style="font-size:16px; font-weight:600">طلب صيانة</div>
    <div style="font-size:12px; color:#6b7280; margin-top:6px">إبلاغ عن عطل في جهاز أو معدات</div>
  </a>
  <?php foreach ($templates as $t): ?>
  <a href="/workflows/create/<?= $t['id'] ?>" class="card stat" style="text-decoration:none">
    <div class="value" style="font-size:32px"><?= $t['icon'] ?></div>
    <div class="label" style="font-size:16px; font-weight:600"><?= Helpers::e($t['name']) ?></div>
    <div style="font-size:12px; color:#6b7280; margin-top:6px"><?= Helpers::e($t['description']) ?></div>
  </a>
  <?php endforeach; ?>
  <?php if (!$templates): ?>
  <div class="card">
    <p>لا توجد قوالب متاحة لك إنشاء طلب.</p>
  </div>
  <?php endif; ?>
</div>
