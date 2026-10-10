<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-envelope-open"></i> <?= Helpers::e($row['subject']) ?></h2>
  <a href="/mail" class="btn btn-secondary"><i class="fa-solid fa-arrow-right"></i> رجوع للوارد</a>
</div>

<div class="card" style="max-width:720px">
  <div style="display:flex;justify-content:space-between;color:#6b7280;font-size:13px;margin-bottom:12px;border-bottom:1px solid #e5e7eb;padding-bottom:10px">
    <span>من: <strong><?= Helpers::e($row['sender_name']) ?></strong></span>
    <span><?= $row['created_at'] ?></span>
  </div>
  <div style="white-space:pre-wrap;font-size:15px;line-height:1.8"><?= Helpers::e($row['body']) ?></div>

  <div style="display:flex;gap:8px;margin-top:24px">
    <a href="/mail/compose?to=<?= (int)$row['sender_id'] ?>&subject=<?= urlencode('رد: ' . $row['subject']) ?>" class="btn btn-primary">
      <i class="fa-solid fa-reply"></i> رد
    </a>
    <form method="post" action="/mail/<?= (int)$row['id'] ?>/delete" onsubmit="return confirm('حذف هذه الرسالة؟')">
      <button class="btn btn-danger"><i class="fa-solid fa-trash"></i> حذف</button>
    </form>
  </div>
</div>
