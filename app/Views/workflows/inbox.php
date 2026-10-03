<?php use App\Core\Helpers; ?>
<?php $c = $inbox['counts']; $items = $inbox['items']; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-inbox"></i> صندوق الموافقات</h2>
  <a href="/workflows" class="btn btn-secondary">سير العمل</a>
</div>

<style>
.inb-counts { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:14px; }
.inb-count { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:14px; text-align:center; }
.inb-count .v { font-size:26px; font-weight:800; }
.inb-count.urgent .v { color:#dc2626; }
.inb-item { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:14px; margin-bottom:10px; border-right:5px solid #cbd5e1; }
.inb-item.overdue { border-right-color:#dc2626; }
.inb-item.today { border-right-color:#16a34a; }
.inb-title { font-weight:700; font-size:15px; margin-bottom:4px; }
.inb-meta { font-size:12px; color:#6b7280; line-height:1.9; }
.inb-badge { display:inline-block; font-size:11px; padding:1px 8px; border-radius:20px; margin-left:4px; }
.inb-overdue { background:#fee2e2; color:#dc2626; }
.inb-today { background:#dcfce7; color:#16a34a; }
.inb-actions { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
.inb-actions .btn { flex:1; min-width:90px; padding:10px; font-size:14px; text-align:center; }
@media (max-width:600px){ .inb-counts{grid-template-columns:repeat(3,1fr);} .inb-actions .btn{min-width:70px;padding:12px 6px;} }
</style>

<div class="inb-counts">
  <div class="inb-count"><div class="v"><?= $c['pending'] ?></div><div>بانتظارك</div></div>
  <div class="inb-count urgent"><div class="v"><?= $c['urgent'] ?></div><div>متأخر (عاجل)</div></div>
  <div class="inb-count"><div class="v"><?= $c['today'] ?></div><div>طلبات اليوم</div></div>
</div>

<?php foreach ($items as $it): ?>
<div class="inb-item <?= $it['is_overdue'] ? 'overdue' : ($it['is_today'] ? 'today' : '') ?>">
  <div class="inb-title"><?= Helpers::e($it['title']) ?></div>
  <div class="inb-meta">
    <span class="inb-badge" style="background:#e0e7ff;color:#3730a3"><?= Helpers::e($it['template_name'] ?? '') ?></span>
    <?php if ($it['is_overdue']): ?><span class="inb-badge inb-overdue">متأخر عن SLA</span><?php endif; ?>
    <?php if ($it['is_today']): ?><span class="inb-badge inb-today">اليوم</span><?php endif; ?>
    <br>
    مقدم الطلب: <strong><?= Helpers::e($it['requester_name'] ?? '-') ?></strong>
    <?php if ((float)($it['amount'] ?? 0) > 0): ?> · المبلغ: <strong><?= number_format((float)$it['amount'], 0) ?></strong><?php endif; ?>
    <br>
    <?php if (!empty($it['current_step_title'])): ?>المرحلة: <?= Helpers::e($it['current_step_title']) ?> · <?php endif; ?>
    منذ <?= Helpers::e($it['elapsed']) ?>
    <?php if (!empty($it['due_at'])): ?> · الاستحقاق: <?= Helpers::e($it['due_at']) ?><?php endif; ?>
  </div>
  <div class="inb-actions">
    <form method="post" action="/workflows/requests/<?= $it['id'] ?>/decide" style="flex:2;display:flex">
      <input type="hidden" name="notes" value="">
      <button name="decision" value="approved" class="btn btn-success" style="flex:1">
        <i class="fa-solid fa-check"></i> موافقة
      </button>
    </form>
    <a href="/workflows/requests/<?= $it['id'] ?>" class="btn btn-secondary" style="flex:1">تفاصيل</a>
    <button class="btn btn-warning" style="flex:1" onclick="inbDecide(<?= $it['id'] ?>,'returned_for_edit','سبب الإعادة')">إعادة</button>
    <button class="btn btn-danger" style="flex:1" onclick="inbDecide(<?= $it['id'] ?>,'rejected','سبب الرفض')">رفض</button>
  </div>
</div>
<?php endforeach; ?>

<?php if (!$items): ?>
<div class="card" style="text-align:center;padding:40px">
  <div style="font-size:40px">🎉</div>
  <h3>لا توجد طلبات بانتظارك</h3>
  <p style="color:#6b7280">كل شيء تحت السيطرة</p>
</div>
<?php endif; ?>

<script>
function inbDecide(id, decision, label) {
  const notes = prompt(label + ' (اختياري):');
  if (notes === null) return;
  const f = document.createElement('form');
  f.method = 'post';
  f.action = '/workflows/requests/' + id + '/decide';
  f.innerHTML = '<input type="hidden" name="decision" value="' + decision + '">' +
                '<input type="hidden" name="notes" value="' + notes.replace(/"/g, '&quot;') + '">';
  document.body.appendChild(f);
  f.submit();
}
</script>
