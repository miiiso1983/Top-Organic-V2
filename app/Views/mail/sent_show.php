<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-paper-plane"></i> <?= Helpers::e($row['subject']) ?></h2>
  <a href="/mail/sent" class="btn btn-secondary"><i class="fa-solid fa-arrow-right"></i> رجوع للصادر</a>
</div>

<div class="card" style="max-width:720px">
  <div style="color:#6b7280;font-size:13px;margin-bottom:12px;border-bottom:1px solid #e5e7eb;padding-bottom:10px">
    <?= $row['created_at'] ?>
  </div>
  <div style="white-space:pre-wrap;font-size:15px;line-height:1.8;margin-bottom:20px"><?= Helpers::e($row['body']) ?></div>

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
