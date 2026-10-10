<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-paper-plane"></i> <?= Helpers::e($row['subject']) ?></h2>
  <a href="/mail/sent" class="btn btn-secondary"><i class="fa-solid fa-arrow-right"></i> رجوع للصادر</a>
</div>

<div class="card" style="max-width:720px">
  <div style="color:#6b7280;font-size:13px;margin-bottom:12px;border-bottom:1px solid #e5e7eb;padding-bottom:10px">
    <?= $row['created_at'] ?>
  </div>

  <?php if (!empty($targets)): ?>
  <div style="margin-bottom:14px">
    <?php foreach ($targets as $t): ?>
    <span class="badge badge-light" style="margin-left:6px">
      <i class="fa-solid <?= $t['target_type'] === 'department' ? 'fa-building' : 'fa-users-rectangle' ?>"></i>
      <?= $t['target_type'] === 'department' ? 'قسم' : 'مجموعة' ?>: <?= Helpers::e($t['target_label']) ?>
    </span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div style="white-space:pre-wrap;font-size:15px;line-height:1.8;margin-bottom:20px"><?= Helpers::e($row['body']) ?></div>

  <?php if (!empty($row['attachments'])): ?>
  <div style="margin-bottom:20px">
    <h4 style="margin-bottom:8px"><i class="fa-solid fa-paperclip"></i> المرفقات (<?= count($row['attachments']) ?>)</h4>
    <div style="display:flex;flex-direction:column;gap:6px">
      <?php foreach ($row['attachments'] as $a): ?>
      <a href="<?= Helpers::e($a['file_path']) ?>" target="_blank" class="btn btn-sm btn-secondary" style="justify-content:flex-start;width:fit-content">
        <i class="fa-solid fa-paperclip"></i> <?= Helpers::e($a['original_name'] ?: 'ملف') ?>
        <?php if ($a['file_size']): ?><span style="color:#6b7280"> (<?= number_format((int)$a['file_size'] / 1024, 1) ?> KB)</span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <h4 style="margin-bottom:8px">المستلمون وحالة القراءة</h4>
  <table class="table">
    <thead><tr><th>المستلم</th><th>الحالة</th></tr></thead>
    <tbody>
      <?php foreach ($recipients as $r): ?>
      <tr>
        <td><?= Helpers::e($r['full_name']) ?></td>
        <td><?= $r['read_at'] ? '<span class="badge badge-success">قُرئت — ' . Helpers::e($r['read_at']) . '</span>' : '<span class="badge badge-light">لم تُقرأ بعد</span>' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div style="margin-top:16px">
    <form method="post" action="/mail/sent/<?= (int)$row['id'] ?>/delete" onsubmit="return confirm('حذف هذه الرسالة؟')">
      <button class="btn btn-danger"><i class="fa-solid fa-trash"></i> حذف</button>
    </form>
  </div>
</div>
