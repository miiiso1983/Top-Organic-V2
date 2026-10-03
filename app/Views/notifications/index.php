<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-bell"></i> الإشعارات <?= $unread ? "<span class='badge badge-danger'>$unread غير مقروء</span>" : '' ?></h2>
  <?php if ($unread): ?>
  <form method="post" action="/notifications/read-all"><button class="btn btn-secondary">تعليم الكل كمقروء</button></form>
  <?php endif; ?>
</div>

<style>
.ntf { display:flex; gap:12px; align-items:flex-start; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:12px 14px; margin-bottom:8px; cursor:pointer; }
.ntf.unread { border-right:4px solid #2563eb; background:#f0f7ff; }
.ntf.read { opacity:0.9; }
.ntf .dot { width:10px; height:10px; border-radius:50%; background:#2563eb; margin-top:6px; flex-shrink:0; }
.ntf.read .dot { background:#d1d5db; }
.ntf .ttl { font-weight:700; font-size:14px; }
.ntf .bdy { font-size:13px; color:#4b5563; }
.ntf .tm { font-size:11px; color:#9ca3af; margin-top:2px; }
#ntfOverlay { position:fixed; inset:0; background:rgba(0,0,0,0.5); display:none; align-items:center; justify-content:center; z-index:1000; }
#ntfModal { background:#fff; border-radius:16px; width:90%; max-width:480px; max-height:80vh; overflow:auto; padding:24px; direction:rtl; box-shadow:0 20px 50px rgba(0,0,0,0.2); }
#ntfModal .head { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
#ntfModal .head i { font-size:22px; color:#2563eb; }
#ntfModal .title { font-size:18px; font-weight:700; }
#ntfModal .body { font-size:15px; color:#374151; margin-bottom:16px; }
#ntfModal .row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f3f4f6; font-size:14px; }
#ntfModal .row .label { font-weight:600; color:#6b7280; }
#ntfModal .actions { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
</style>

<div class="card" style="background:transparent;border:none;box-shadow:none;padding:0">
  <?php foreach ($rows as $n):
    $meta = [];
    if (!empty($n['metadata_json'])) {
        $decoded = json_decode($n['metadata_json'], true);
        if (is_array($decoded)) $meta = $decoded;
    }
    $metaJson = Helpers::e(json_encode($meta, JSON_UNESCAPED_UNICODE));
  ?>
  <div class="ntf <?= $n['is_read'] ? 'read' : 'unread' ?>"
       onclick="openNtf(<?= (int)$n['id'] ?>, '<?= Helpers::e(addslashes($n['title'])) ?>', '<?= Helpers::e(addslashes($n['body'])) ?>', '<?= $n['created_at'] ?>', '<?= Helpers::e($n['action_type'] ?? '') ?>', '<?= Helpers::e($n['link'] ?? '') ?>', <?= $metaJson ?>)">
    <div class="dot"></div>
    <div style="flex:1">
      <div class="ttl"><?= Helpers::e($n['title']) ?></div>
      <?php if ($n['body']): ?><div class="bdy"><?= Helpers::e($n['body']) ?></div><?php endif; ?>
      <div class="tm"><?= $n['created_at'] ?></div>
    </div>
    <div>
      <a href="/notifications/<?= $n['id'] ?>/open" class="btn btn-sm <?= $n['is_read'] ? 'btn-secondary' : 'btn-primary' ?>" onclick="event.stopPropagation()">
        <?= $n['link'] ? 'فتح' : 'قراءة' ?>
      </a>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?>
  <div class="card" style="text-align:center;padding:40px">
    <div style="font-size:36px">🔕</div>
    <h3>لا إشعارات</h3>
  </div>
  <?php endif; ?>
</div>

<div id="ntfOverlay" onclick="closeNtf(event)">
  <div id="ntfModal" onclick="event.stopPropagation()">
    <div class="head"><i class="fa-solid fa-bell"></i><div class="title" id="ntfTitle"></div></div>
    <div class="body" id="ntfBody"></div>
    <div id="ntfMeta"></div>
    <div class="actions">
      <button class="btn btn-secondary" onclick="closeNtf()">إغلاق</button>
      <a id="ntfLink" href="#" class="btn btn-primary" style="display:none">فتح الطلب</a>
    </div>
  </div>
</div>

<script>
function openNtf(id, title, body, createdAt, actionType, link, meta) {
  document.getElementById('ntfTitle').textContent = title;
  document.getElementById('ntfBody').textContent = body;
  const metaDiv = document.getElementById('ntfMeta');
  const actionLabels = {
    'pending_approval': 'طلب موافقة',
    'pending_reminder': 'تذكير طلب معلق',
    'pending_reminder_manager': 'تذكير مدير مباشر',
    'approved': 'موافقة',
    'rejected': 'رفض',
    'returned_for_edit': 'طلب تعديل',
    'escalated': 'تصعيد',
    'status_update': 'تحديث حالة'
  };
  let rows = '';
  if (meta.template_name) rows += rowHtml('نوع الطلب', meta.template_name);
  if (meta.request_no) rows += rowHtml('رقم الطلب', meta.request_no);
  if (meta.requester_name) rows += rowHtml('الطالب', meta.requester_name);
  if (meta.hours_pending) rows += rowHtml('مدة الانتظار', meta.hours_pending + ' ساعة');
  if (meta.status) rows += rowHtml('الحالة', meta.status);
  if (meta.step_title) rows += rowHtml('الخطوة', meta.step_title);
  rows += rowHtml('التاريخ', createdAt);
  if (actionType) rows += rowHtml('نوع الإشعار', actionLabels[actionType] || actionType);
  metaDiv.innerHTML = rows;

  const linkBtn = document.getElementById('ntfLink');
  if (link) { linkBtn.href = link; linkBtn.style.display = ''; }
  else { linkBtn.style.display = 'none'; }

  document.getElementById('ntfOverlay').style.display = 'flex';

  fetch('/notifications/' + id + '/read', { method: 'POST' }).then(() => {
    const el = document.querySelector('.ntf[onclick*="' + id + '"]');
    if (el) { el.classList.remove('unread'); el.classList.add('read'); }
  });
}
function rowHtml(label, value) {
  return '<div class="row"><span class="label">' + label + '</span><span>' + value + '</span></div>';
}
function closeNtf(e) {
  if (e && e.target !== e.currentTarget) return;
  document.getElementById('ntfOverlay').style.display = 'none';
}
</script>
