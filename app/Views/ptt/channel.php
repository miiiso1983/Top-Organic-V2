<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2><i class="<?= Helpers::e($channel['icon']) ?>" style="color:<?= Helpers::e($channel['color']) ?>"></i> <?= Helpers::e($channel['name']) ?></h2>
  <a href="/ptt" class="btn btn-secondary">رجوع</a>
</div>

<style>
.pttc-wrap { display:flex; gap:12px; flex-wrap:wrap; }
.pttc-main { flex:2; min-width:300px; }
.pttc-side { flex:1; min-width:220px; }
.pttc-msg { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:10px 12px; margin-bottom:8px; border-right:4px solid #cbd5e1; }
.pttc-msg.p-urgent { border-right-color:#d97706; }
.pttc-msg.p-emergency { border-right-color:#dc2626; background:#fef2f2; }
.pttc-msg .meta { font-size:11px; color:#6b7280; }
.ptt-big { width:100%; padding:26px; font-size:20px; font-weight:800; border-radius:16px; border:none; color:#fff;
  background:<?= Helpers::e($channel['color']) ?>; cursor:pointer; user-select:none; touch-action:none; }
.ptt-big.recording { background:#dc2626 !important; animation:pttRec 1s infinite; }
@keyframes pttRec { 0%,100%{opacity:1} 50%{opacity:.7} }
.ptt-status { text-align:center; font-size:13px; color:#6b7280; margin-top:8px; min-height:20px; }
.pttc-members li { font-size:13px; margin-bottom:4px; }
</style>

<?php if ($liveConfigured): ?>
<div class="card" id="ptt-live-card" style="border:2px solid <?= $activeLive ? '#dc2626' : '#e5e7eb' ?>">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h3 style="margin:0">📡 البث المباشر <span id="live-indicator" style="display:<?= $activeLive ? 'inline' : 'none' ?>;color:#dc2626;font-size:12px">● مباشر الآن</span></h3>
    <span id="live-status" style="font-size:12px;color:#6b7280">غير متصل</span>
    <span id="live-version" style="font-size:10px;color:#9ca3af;margin-right:8px">v7</span>
  </div>
  <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
    <button id="live-listen" class="btn btn-secondary">🎧 استماع مباشر</button>
    <button id="live-test" class="btn btn-sm btn-outline" style="margin-right:auto" onclick="if(!window.__pttLiveLoaded){alert('سكربت البث لم يُحمّل — أعد فتح التطبيق');}">🔍 فحص</button>
    <?php if ($canLivePublish): ?>
    <button id="live-hold" class="ptt-big" style="flex:1;display:none">🎙️ اضغط مطولاً للبث الحي</button>
    <button id="live-broadcast" class="btn btn-danger" style="flex:1" onclick="if(!window.__pttLiveLoaded){alert('سكربت البث لم يُحمّل — أعد فتح التطبيق');}">بدء البث المباشر (Walkie-Talkie)</button>
    <?php endif; ?>
  </div>
</div>
<script>window.PTT_LIVE = { channel: <?= (int)$channel['id'] ?>, publish: <?= $canLivePublish ? 'true' : 'false' ?> };</script>
<script src="https://cdn.jsdelivr.net/npm/livekit-client@2/dist/livekit-client.umd.min.js"></script>
<script src="/assets/js/ptt-live.js?v=8"></script>
<script>
  // Fallback: إذا لم يُحمّل ptt-live.js، أضف رسالة توضيحية مباشرة
  setTimeout(function(){
    if (!window.__pttLiveLoaded) {
      var s = document.getElementById('live-status');
      if (s) { s.textContent = '❌ لم يُحمّل سكربت البث المباشر (مشكلة كاش)'; s.style.color='#dc2626'; s.style.fontWeight='700'; }
    }
  }, 4000);
</script>
<?php endif; ?>

<div class="pttc-wrap">
  <div class="pttc-main">
    <?php if ($canSend): ?>
    <div class="card" style="text-align:center">
      <div class="form-inline" style="justify-content:center;margin-bottom:8px">
        <select id="ptt-priority" class="form-row" style="max-width:140px">
          <?php foreach ($priorities as $k => $v): ?>
            <?php if ($k === 'emergency' && !$canEmergency) continue; ?>
            <option value="<?= $k ?>"><?= $v ?></option>
          <?php endforeach; ?>
        </select>
        <label style="font-size:12px"><input type="checkbox" id="ptt-req-ack"> تأكيد استلام</label>
        <label style="font-size:12px"><input type="checkbox" id="ptt-req-listen"> تأكيد استماع</label>
      </div>
      <button id="ptt-hold" class="ptt-big"
              data-channel="<?= (int)$channel['id'] ?>"
              data-max="<?= (int)$maxDuration ?>">
        🎙️ اضغط مطولاً للتحدث
      </button>
      <div class="ptt-status" id="ptt-status">الحد الأقصى: <?= (int)$maxDuration ?> ثانية — اضغط مطولاً ثم أفلت للإرسال</div>
    </div>
    <?php else: ?>
    <div class="card" style="text-align:center;color:#6b7280">قناة للقراءة فقط — الإرسال مقيد</div>
    <?php endif; ?>

    <div class="card">
      <h3>الرسائل (<?= count($messages) ?>)</h3>
      <?php foreach ($messages as $m): ?>
      <div class="pttc-msg p-<?= Helpers::e($m['priority']) ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;flex-wrap:wrap">
          <div>
            <strong><?= Helpers::e($m['sender_name']) ?></strong>
            <span class="meta"><?= $m['created_at'] ?> · <?= (float)$m['duration_sec'] ?> ث · سمعها <?= (int)$m['played_count'] ?>/<?= (int)$m['recipients_count'] ?></span>
            <?php if ($m['reply_to']): ?><span class="meta">↩ رد على #<?= (int)$m['reply_to'] ?></span><?php endif; ?>
          </div>
          <div style="display:flex;gap:4px">
            <button class="btn btn-sm btn-primary ptt-play" data-src="<?= Helpers::e($m['file_path']) ?>" data-id="<?= $m['id'] ?>"><i class="fa-solid fa-play"></i> تشغيل</button>
            <?php if ((int)$m['sender_id'] !== (int)\App\Core\Auth::id()): ?>
            <button class="btn btn-sm btn-secondary ptt-reply" data-id="<?= $m['id'] ?>" data-sender="<?= $m['sender_id'] ?>" data-name="<?= Helpers::e($m['sender_name']) ?>">↩</button>
            <?php endif; ?>
            <button class="btn btn-sm btn-secondary ptt-ai" data-id="<?= $m['id'] ?>">🤖</button>
            <?php if ((int)$m['sender_id'] === (int)\App\Core\Auth::id() || \App\Core\Auth::can('ptt.delete')): ?>
            <button class="btn btn-sm btn-danger ptt-delete" data-id="<?= $m['id'] ?>" title="حذف">🗑</button>
            <?php endif; ?>
          </div>
        </div>
        <?php if (!empty($m['transcript'])): ?>
        <div class="meta" style="margin-top:4px;background:#f8fafc;padding:5px;border-radius:6px">📝 <?= Helpers::e(mb_substr((string)$m['transcript'], 0, 160)) ?></div>
        <?php endif; ?>
        <div class="ptt-ai-out" style="font-size:12px;margin-top:4px"></div>
      </div>
      <?php endforeach; ?>
      <?php if (!$messages): ?><p style="color:#6b7280">لا رسائل بعد — كن أول من يتحدث 🎙️</p><?php endif; ?>
    </div>
  </div>

  <div class="pttc-side">
    <div class="card">
      <h4>الأعضاء (<?= count($members) ?>)</h4>
      <ul class="pttc-members" style="list-style:none;padding:0">
        <?php foreach ($members as $m): ?>
        <li>
          <?= $m['role'] === 'supervisor' ? '⭐' : '👤' ?> <?= Helpers::e($m['full_name']) ?>
          <?php if ($m['muted']): ?><span title="مكتوم">🔇</span><?php endif; ?>
          <?php if (!$m['can_send']): ?><span title="ممنوع من الإرسال">🚫</span><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<script src="/assets/js/ptt-offline.js?v=1"></script>
<script src="/assets/js/ptt.js?v=4"></script>
