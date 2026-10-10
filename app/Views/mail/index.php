<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-envelope"></i> البريد الداخلي <?= $unread ? "<span class='badge badge-danger'>$unread غير مقروءة</span>" : '' ?></h2>
  <div style="display:flex;gap:8px">
    <a href="/mail/compose" class="btn btn-success"><i class="fa-solid fa-pen"></i> رسالة جديدة</a>
  </div>
</div>

<div style="display:flex;gap:8px;margin-bottom:16px">
  <a href="/mail" class="btn btn-primary"><i class="fa-solid fa-inbox"></i> الوارد</a>
  <a href="/mail/sent" class="btn btn-secondary"><i class="fa-solid fa-paper-plane"></i> الصادر</a>
</div>

<style>
.mb-item { display:flex; gap:12px; align-items:flex-start; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:12px 14px; margin-bottom:8px; }
.mb-item.unread { border-right:4px solid #2563eb; background:#f0f7ff; }
.mb-item .dot { width:10px; height:10px; border-radius:50%; background:#2563eb; margin-top:6px; flex-shrink:0; }
.mb-item.read .dot { background:#d1d5db; }
.mb-item .ttl { font-weight:700; font-size:14px; }
.mb-item .frm { font-size:12px; color:#6b7280; }
.mb-item .bdy { font-size:13px; color:#4b5563; }
.mb-item .tm { font-size:11px; color:#9ca3af; margin-top:2px; white-space:nowrap; }
</style>

<div class="card" style="background:transparent;border:none;box-shadow:none;padding:0">
  <?php foreach ($rows as $m): $read = !empty($m['read_at']); ?>
  <div class="mb-item <?= $read ? 'read' : 'unread' ?>">
    <div class="dot"></div>
    <div style="flex:1;cursor:pointer" onclick="location.href='/mail/<?= (int)$m['id'] ?>'">
      <div class="ttl"><?= Helpers::e($m['subject']) ?></div>
      <div class="frm">من: <?= Helpers::e($m['sender_name']) ?></div>
      <div class="bdy"><?= Helpers::e(mb_substr($m['body'], 0, 120)) ?><?= mb_strlen($m['body']) > 120 ? '…' : '' ?></div>
    </div>
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
      <span class="tm"><?= $m['created_at'] ?></span>
      <div style="display:flex;gap:6px">
        <a href="/mail/<?= (int)$m['id'] ?>" class="btn btn-sm <?= $read ? 'btn-secondary' : 'btn-primary' ?>">فتح</a>
        <form method="post" action="/mail/<?= (int)$m['id'] ?>/delete" onsubmit="return confirm('حذف هذه الرسالة؟')">
          <button class="btn btn-sm btn-danger" title="حذف"><i class="fa-solid fa-trash"></i></button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?>
  <div class="card" style="text-align:center;padding:40px">
    <div style="font-size:36px">📭</div>
    <h3>لا توجد رسائل واردة</h3>
  </div>
  <?php endif; ?>
</div>
