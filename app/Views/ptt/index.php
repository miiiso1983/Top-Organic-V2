<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-tower-broadcast"></i> PTT — الاتصال الفوري</h2>
  <div>
    <a href="/ptt/search" class="btn btn-secondary"><i class="fa-solid fa-magnifying-glass"></i> بحث</a>
  </div>
</div>

<style>
.ptt-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:12px; margin-bottom:16px; }
.ptt-ch { border-radius:14px; padding:16px 12px; text-align:center; color:#fff; text-decoration:none; position:relative; transition:transform .15s; }
.ptt-ch:hover { transform:translateY(-3px); }
.ptt-ch .ic { font-size:26px; margin-bottom:8px; }
.ptt-ch .nm { font-weight:700; font-size:14px; }
.ptt-ch .mc { font-size:11px; opacity:.85; margin-top:3px; }
.ptt-msg { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:12px; margin-bottom:10px; border-right:5px solid #cbd5e1; }
.ptt-msg.p-important { border-right-color:#2563eb; }
.ptt-msg.p-urgent { border-right-color:#d97706; }
.ptt-msg.p-emergency { border-right-color:#dc2626; background:#fef2f2; animation:pttPulse 1.4s infinite; }
@keyframes pttPulse { 0%,100%{box-shadow:0 0 0 0 rgba(220,38,38,.25)} 50%{box-shadow:0 0 0 8px rgba(220,38,38,0)} }
.ptt-prio { font-size:11px; padding:1px 8px; border-radius:20px; }
.ptt-st { font-size:11px; color:#6b7280; }
.ptt-ack-btn { font-size:12px; }
</style>

<?php if ($counts['emergency'] > 0): ?>
<div class="card" style="background:#dc2626;color:#fff;text-align:center">
  <strong>🚨 لديك <?= $counts['emergency'] ?> رسالة طارئة لم تُسمع — شغّلها فوراً!</strong>
</div>
<?php endif; ?>

<div class="grid grid-3">
  <div class="card stat"><div class="value"><?= $counts['total'] ?></div><div class="label">غير مسموعة</div></div>
  <div class="card stat"><div class="value" style="color:#d97706"><?= $counts['urgent'] ?></div><div class="label">عاجلة</div></div>
  <div class="card stat"><div class="value" style="color:#dc2626"><?= $counts['emergency'] ?></div><div class="label">طارئة</div></div>
</div>

<div class="card">
  <h3>القنوات</h3>
  <div class="ptt-grid">
    <?php foreach ($channels as $c): ?>
    <a href="/ptt/channels/<?= $c['id'] ?>" class="ptt-ch" style="background:<?= Helpers::e($c['color']) ?>">
      <div class="ic"><i class="<?= Helpers::e($c['icon']) ?>"></i></div>
      <div class="nm"><?= Helpers::e($c['name']) ?></div>
      <div class="mc"><?= (int)$c['members_count'] ?> عضو</div>
    </a>
    <?php endforeach; ?>
    <?php if (!$channels): ?><p style="color:#6b7280">لا توجد قنوات بعد — تواصل مع مدير النظام لإضافتك.</p><?php endif; ?>
  </div>
</div>

<div class="card">
  <h3>الرسائل الواردة</h3>
  <?php foreach ($inbox as $m): ?>
  <div class="ptt-msg p-<?= Helpers::e($m['priority']) ?>" data-msg="<?= $m['id'] ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px">
      <div>
        <strong><?= Helpers::e($m['sender_name']) ?></strong>
        <?php if ($m['channel_name']): ?>
          <span class="ptt-prio" style="background:<?= Helpers::e($m['channel_color'] ?? '#64748b') ?>;color:#fff"><?= Helpers::e($m['channel_name']) ?></span>
        <?php endif; ?>
        <span class="ptt-prio" style="background:#f1f5f9"><?= $priorities[$m['priority']] ?? $m['priority'] ?></span>
        <div class="ptt-st">
          <?= $m['created_at'] ?> · <?= (float)$m['duration_sec'] ?> ث
          · <?= ['sent'=>'أُرسلت','delivered'=>'وصلت','played'=>'سُمعت','acknowledged'=>'أُكّدت','failed'=>'فشلت'][$m['my_status']] ?? $m['my_status'] ?>
        </div>
      </div>
      <div style="display:flex;gap:6px;align-items:center">
        <button class="btn btn-sm btn-primary ptt-play" data-src="<?= Helpers::e($m['file_path']) ?>" data-id="<?= $m['id'] ?>"><i class="fa-solid fa-play"></i> تشغيل</button>
        <button class="btn btn-sm btn-secondary ptt-reply" data-id="<?= $m['id'] ?>" data-sender="<?= $m['sender_id'] ?>" data-name="<?= Helpers::e($m['sender_name']) ?>">↩ رد</button>
        <button class="btn btn-sm btn-secondary ptt-ai" data-id="<?= $m['id'] ?>">🤖 تحليل</button>
        <?php if ($m['require_ack'] && $m['my_status'] !== 'acknowledged'): ?>
        <button class="btn btn-sm btn-success ptt-ack-btn" data-id="<?= $m['id'] ?>">تأكيد</button>
        <?php endif; ?>
        <?php if ((int)$m['sender_id'] === (int)\App\Core\Auth::id() || \App\Core\Auth::can('ptt.delete')): ?>
        <button class="btn btn-sm btn-danger ptt-delete" data-id="<?= $m['id'] ?>" title="حذف">🗑</button>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($m['reply_to']): ?><div class="ptt-st">↩ رد على رسالة #<?= (int)$m['reply_to'] ?></div><?php endif; ?>
    <?php if (!empty($m['transcript'])): ?>
    <div class="ptt-st" style="margin-top:4px;background:#f8fafc;padding:6px;border-radius:6px">📝 <?= Helpers::e(mb_substr((string)$m['transcript'], 0, 180)) ?></div>
    <?php endif; ?>
    <?php if (!empty($m['ai_summary'])): ?><div class="ptt-st" style="margin-top:4px">🤖 <?= Helpers::e((string)$m['ai_summary']) ?></div><?php endif; ?>
    <div class="ptt-ai-out" style="font-size:12px;margin-top:6px"></div>
  </div>
  <?php endforeach; ?>
  <?php if (!$inbox): ?><p style="color:#6b7280">لا توجد رسائل واردة بعد.</p><?php endif; ?>
</div>

<script src="/assets/js/ptt-offline.js?v=1"></script>
<script src="/assets/js/ptt.js?v=3"></script>
