<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2>محرك سير العمل الديناميكي</h2>
  <div>
    <a href="/workflows" class="btn btn-secondary">سير العمل</a>
    <a href="/workflow-engine/new" class="btn btn-success">+ تعريف جديد</a>
  </div>
</div>

<div class="card">
  <h3>تعريفات Workflow</h3>
  <table>
    <tr>
      <th>الاسم</th><th>الكود</th><th>نوع الطلب</th><th>القالب المرتبط</th>
      <th>الأولوية</th><th>الصلاحية</th><th>الحالة</th><th>النشر</th><th></th>
    </tr>
    <?php foreach ($defs as $d): ?>
    <tr>
      <td><strong><?= Helpers::e($d['name']) ?></strong>
        <?php if ($d['description']): ?><br><small style="color:#6b7280"><?= Helpers::e($d['description']) ?></small><?php endif; ?>
      </td>
      <td><code><?= Helpers::e($d['code']) ?></code></td>
      <td><?= Helpers::e($d['request_type']) ?></td>
      <td><?= Helpers::e($d['template_name'] ?? '—') ?></td>
      <td><?= (int)$d['priority'] ?></td>
      <td><small>
        <?= $d['valid_from'] ? Helpers::e($d['valid_from']) : '∞' ?> ←
        <?= $d['valid_to'] ? Helpers::e($d['valid_to']) : '∞' ?>
      </small></td>
      <td><span class="badge badge-<?= $d['is_active'] ? 'success' : 'secondary' ?>"><?= $d['is_active'] ? 'فعال' : 'معطل' ?></span></td>
      <td><?= $d['published_count'] ? '<span class="badge badge-success">منشور</span>' : '<span class="badge badge-warning">مسودة</span>' ?></td>
      <td style="white-space:nowrap">
        <a href="/workflow-engine/<?= $d['id'] ?>/design" class="btn btn-sm btn-primary"><i class="fa-solid fa-diagram-project"></i> المصمم</a>
        <form method="post" action="/workflow-engine/<?= $d['id'] ?>/delete" style="display:inline" onsubmit="return confirm('حذف التعريف وكل نسخه؟')">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$defs): ?><tr><td colspan="9">لا توجد تعريفات بعد — أنشئ أول تعريف</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h4>كيف يعمل؟</h4>
  <ol style="line-height:2">
    <li>أنشئ <strong>تعريف</strong> واربطه بقالب طلب موجود (طلب شراء، إجازة، سلفة...)</li>
    <li>افتح <strong>المصمم</strong> واسحب العقد: بداية ← شرط ← موافقة ← نهاية</li>
    <li>اضغط <strong>حفظ</strong> ثم <strong>نشر</strong> — الطلبات الجديدة من هذا القالب ستسير على المخطط تلقائياً</li>
    <li>القوالب بدون مخطط منشور تستمر بالعمل على نظام الخطوات الحالي</li>
  </ol>
</div>
