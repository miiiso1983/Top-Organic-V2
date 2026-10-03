<?php use App\Core\Helpers; ?>
<style>
.captain-index { margin: -24px; height: calc(100vh - 54px); padding: 16px; display: flex; flex-direction: column; overflow: hidden; }
.captain-index-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-shrink: 0; }
.captain-legend { display: flex; gap: 12px; font-size: 13px; }
.captain-legend span { display: inline-flex; align-items: center; gap: 4px; }
.captain-legend .dot { width: 14px; height: 14px; border-radius: 4px; }
.captain-tables-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 14px; overflow-y: auto; flex: 1; padding: 8px; }
.captain-tables-grid .table-card { border: 1px solid #e5e7eb; border-radius: 14px; padding: 18px 10px; text-align: center; cursor: pointer; transition: transform .1s, box-shadow .1s; text-decoration: none; color: inherit; }
.captain-tables-grid .table-card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,.1); }
.captain-tables-grid .table-card.available { background: linear-gradient(135deg, #fff9c4, #ffeb3b); color: #333; }
.captain-tables-grid .table-card.occupied { background: linear-gradient(135deg, #ef4444, #b91c1c); color: #fff; }
.captain-tables-grid .table-card.bill_requested { background: linear-gradient(135deg, #60a5fa, #2563eb); color: #fff; }
.captain-tables-grid .table-card.cleaning { background: linear-gradient(135deg, #fef08a, #facc15); color: #333; }
.captain-tables-grid .table-card .no { font-size: 26px; font-weight: 700; }
.captain-tables-grid .table-card .meta { font-size: 13px; margin-top: 6px; opacity: .9; }
@media (max-width: 900px) { .captain-tables-grid { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); } }
</style>

<div class="captain-index">
  <div class="captain-index-head">
    <h2><i class="fa-solid fa-wine-glass"></i> شاشة الكابتن</h2>
    <div class="captain-legend">
      <span><span class="dot" style="background:#ffeb3b"></span> متاحة / بانتظار التنظيف</span>
      <span><span class="dot" style="background:#ef4444"></span> مشغولة</span>
      <span><span class="dot" style="background:#2563eb"></span> تم طلب الحساب</span>
    </div>
  </div>

  <div class="captain-tables-grid">
    <?php foreach ($tables as $t): ?>
      <a href="/captain/<?= $t['id'] ?>" class="table-card <?= $t['status'] ?>">
        <div class="no"><?= Helpers::e($t['name']) ?></div>
        <div class="meta"><?= (int)($t['capacity'] ?? $t['seats']) ?> كرسي</div>
        <?php if ($t['hall']): ?><div class="meta"><?= Helpers::e($t['hall']) ?></div><?php endif; ?>
        <?php if ($t['current_order']): ?><div class="meta">طلب: <?= Helpers::e($t['current_order']) ?></div><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>


<script>
// تحديث خريطة الطاولات تلقائياً كل 30 ثانية لاستقبال حالات "تم طلب الحساب" و"بانتظار التنظيف"
setInterval(function() {
    window.location.reload();
}, 30000);
</script>