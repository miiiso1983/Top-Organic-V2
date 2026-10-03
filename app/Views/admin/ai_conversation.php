<?php use App\Core\Helpers; ?>
<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px">
    <h2 style="margin:0">محادثة #<?= $conversation['id'] ?></h2>
    <span class="badge badge-<?= $conversation['status'] === 'handoff' ? 'danger' : ($conversation['status'] === 'active' ? 'success' : 'secondary') ?>">
      <?= $conversation['status'] ?>
    </span>
  </div>
  <div style="margin-bottom:16px; color:#6b7280; font-size:14px">
    القناة: <?= Helpers::e($conversation['channel']) ?> | العميل: <?= Helpers::e($conversation['customer_name'] ?: $conversation['channel_id']) ?>
  </div>

  <div id="messagesBox" style="max-height:500px; overflow-y:auto; border:1px solid #e5e7eb; border-radius:10px; padding:16px; background:#f9fafb">
    <?php foreach ($messages as $m): ?>
      <div style="margin-bottom:12px; <?= $m['direction'] === 'in' ? '' : 'text-align:left' ?>">
        <div style="display:inline-block; padding:10px 14px; border-radius:12px; max-width:80%; font-size:14px; <?= $m['role'] === 'human' ? 'background:#dbeafe; color:#1e3a8a;' : ($m['direction'] === 'in' ? 'background:#fff; color:#1f2937; border:1px solid #e5e7eb;' : 'background:#10914d; color:#fff;') ?>">
          <strong><?= $m['role'] === 'human' ? 'موظف' : ($m['direction'] === 'in' ? 'عميل' : 'AI') ?>:</strong>
          <?= nl2br(Helpers::e($m['message'])) ?>
        </div>
        <div style="font-size:11px; color:#9ca3af; margin-top:2px"><?= $m['created_at'] ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($conversation['status'] === 'handoff' || \App\Core\Auth::can('ai_assistant.handoff')): ?>
  <div style="display:flex; gap:8px; margin-top:16px">
    <input type="text" id="agentReply" style="flex:1; padding:10px; border:1px solid #e5e7eb; border-radius:8px" placeholder="رد الموظف..." onkeydown="if(event.key==='Enter') sendReply()">
    <button class="btn btn-primary" onclick="sendReply()">إرسال</button>
  </div>
  <?php endif; ?>
</div>

<script>
const conversationId = <?= (int)$conversation['id'] ?>;
const box = document.getElementById('messagesBox');
box.scrollTop = box.scrollHeight;

async function sendReply() {
  const input = document.getElementById('agentReply');
  const message = input.value.trim();
  if (!message) return;
  const res = await fetch('/admin/ai-conversations/' + conversationId + '/reply', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({message})
  }).then(r => r.json());
  if (res.ok) {
    location.reload();
  } else {
    alert(res.error);
  }
}
</script>
