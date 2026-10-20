<?php use App\Core\Helpers; ?>
<style>
.recruit-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
.recruit-card { background: var(--surface); border: 1px solid var(--border-light); border-radius: 14px; padding: 18px; text-decoration: none; color: var(--text); transition: transform .15s, box-shadow .15s; display: block; }
.recruit-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,.1); }
.recruit-card h4 { margin: 0 0 4px; font-size: 16px; }
.recruit-card .dept { color: var(--text-muted); font-size: 12px; margin-bottom: 12px; }
.recruit-counts { display: flex; gap: 8px; flex-wrap: wrap; }
.recruit-counts .badge { font-size: 11px; }
.recruit-total { font-size: 28px; font-weight: 800; color: var(--brand); float: left; }
</style>

<div class="page-head">
  <h2><i class="fa-solid fa-user-tie"></i> إدارة طلبات التوظيف</h2>
  <a href="/careers/apply" target="_blank" class="btn btn-secondary"><i class="fa-solid fa-link"></i> رابط استمارة التقديم العامة</a>
</div>

<?php if (!$jobs): ?>
<div class="card">
  <p style="text-align:center;color:var(--text-muted);padding:30px 0">لا توجد طلبات توظيف مستلمة بعد.</p>
</div>
<?php else: ?>
<div class="recruit-grid">
  <?php foreach ($jobs as $j): ?>
  <a href="/hr/recruitment/jobs/<?= $j['id'] ?>" class="recruit-card">
    <span class="recruit-total"><?= (int)$j['total_count'] ?></span>
    <h4><?= Helpers::e($j['title']) ?></h4>
    <div class="dept"><?= $j['department_name'] ? Helpers::e($j['department_name']) : 'بدون قسم' ?></div>
    <div class="recruit-counts">
      <?php if ((int)$j['new_count'] > 0): ?><span class="badge badge-info">جديد: <?= (int)$j['new_count'] ?></span><?php endif; ?>
      <?php if ((int)$j['shortlisted_count'] > 0): ?><span class="badge badge-warning">قائمة مختصرة: <?= (int)$j['shortlisted_count'] ?></span><?php endif; ?>
      <?php if ((int)$j['hired_count'] > 0): ?><span class="badge badge-success">تم التوظيف: <?= (int)$j['hired_count'] ?></span><?php endif; ?>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
