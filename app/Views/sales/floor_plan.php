<?php use App\Core\Helpers; ?>
<?php
$statusColors = [
    'available' => '#22c55e',
    'reserved' => '#f59e0b',
    'occupied' => '#ef4444',
    'cleaning' => '#3b82f6',
    'bill_requested' => '#8b5cf6',
    'merged' => '#06b6d4',
];
$statusLabels = [
    'available' => 'فارغة', 'reserved' => 'محجوزة', 'occupied' => 'مشغولة',
    'cleaning' => 'تنظيف', 'bill_requested' => 'طلب الحساب', 'merged' => 'مدمجة',
];
$selectedBranch = (int)($_GET['branch_id'] ?? 0);
?>
<style>
.floor-toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.floor-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.floor-plan { position: relative; min-height: 420px; border: 1px solid #e5e7eb; background: #f9fafb; border-radius: 12px; padding: 20px; overflow: auto; }
.table-node { position: absolute; width: 90px; min-height: 64px; color: #fff; display: flex; align-items: center; justify-content: center; flex-direction: column; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.15); transition: transform .1s, box-shadow .1s; user-select: none; }
.table-node:hover { transform: scale(1.04); box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 10; }
.table-node.selected { outline: 3px solid #111827; box-shadow: 0 0 0 4px rgba(17,24,39,0.2); }
.table-node .t-name { font-size: 12px; font-weight: 700; }
.table-node .t-cap { font-size: 10px; }
.table-node .t-guest { font-size: 9px; white-space: nowrap; max-width: 80px; overflow: hidden; text-overflow: ellipsis; }
.legend { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 16px; }
.legend-item { display: flex; align-items: center; gap: 6px; font-size: 13px; }
.legend-dot { width: 14px; height: 14px; border-radius: 50%; }
.modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; }
.modal.active { display: flex; }
.modal-box { background: #fff; border-radius: 12px; padding: 20px; width: 90%; max-width: 420px; }
.modal-box h4 { margin-top: 0; }
.modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; }
@media (max-width: 768px) {
  .floor-toolbar { flex-direction: column; align-items: stretch; }
  .table-node { width: 76px; min-height: 56px; }
}
</style>

<div class="floor-toolbar">
  <h2 style="margin:0"><i class="fa-solid fa-chair"></i> مخطط الطاولات</h2>
  <form method="GET" action="/reservations/floor-plan" class="floor-actions">
    <select name="branch_id" class="form-input">
      <option value="0">كل الفروع</option>
      <?php foreach ($branches as $b): ?>
      <option value="<?= $b['id'] ?>" <?= $selectedBranch == $b['id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">عرض</button>
  </form>
  <div class="floor-actions">
    <button class="btn btn-secondary" id="mergeBtn" onclick="mergeSelected()" disabled>دمج</button>
    <button class="btn btn-secondary" onclick="clearSelection()">إلغاء التحديد</button>
    <button class="btn btn-primary" onclick="suggestTable()"><i class="fa-solid fa-robot"></i> اقتراح طاولة</button>
  </div>
</div>

<?php foreach ($floors as $floor): ?>
  <h4 style="margin-top:20px"><?= $floor ? Helpers::e($floor) : 'الطابق الافتراضي' ?></h4>
  <div class="floor-plan" id="floorPlan<?= md5($floor) ?>">
    <?php foreach ($tables as $t):
      if ($t['floor'] !== $floor) continue;
      $color = $statusColors[$t['status']] ?? '#9ca3af';
      $shape = in_array($t['shape'], ['round','oval']) ? '50%' : '10px';
      $isMerged = $t['status'] === 'merged';
      $capacity = (int)($t['merged_capacity'] ?? $t['max_capacity'] ?? $t['capacity'] ?? $t['seats'] ?? 0);
    ?>
    <div class="table-node <?= $isMerged ? 'merged-parent' : '' ?>"
         data-id="<?= $t['id'] ?>"
         style="left:<?= (int)$t['pos_x'] ?>px;top:<?= (int)$t['pos_y'] ?>px;background:<?= $color ?>;border-radius:<?= $shape ?>;"
         onclick="selectTable(<?= $t['id'] ?>)"
         title="<?= Helpers::e($t['name']) ?> — <?= $statusLabels[$t['status']] ?? $t['status'] ?>">
      <span class="t-name"><?= Helpers::e($t['name']) ?></span>
      <span class="t-cap"><?= $capacity ?> كرسي</span>
      <?php if (!empty($t['reservation_id'])): ?>
        <span class="t-guest"><?= Helpers::e($t['customer_name']) ?></span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
<?php if (!$floors): ?><p>لا توجد طاولات مسجلة.</p><?php endif; ?>

<div class="legend">
  <?php foreach ($statusLabels as $k => $label): ?>
  <div class="legend-item"><span class="legend-dot" style="background:<?= $statusColors[$k] ?>"></span> <?= $label ?></div>
  <?php endforeach; ?>
</div>

<div class="modal" id="tableModal">
  <div class="modal-box">
    <h4 id="modalTitle">تفاصيل الطاولة</h4>
    <div id="modalBody"></div>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModal()">إغلاق</button>
    </div>
  </div>
</div>

<div class="modal" id="suggestModal">
  <div class="modal-box">
    <h4>اقتراح طاولة تلقائي</h4>
    <div class="form-row">
      <label>عدد الضيوف</label>
      <input type="number" id="sgGuests" class="form-input" value="4" min="1">
    </div>
    <div class="form-row">
      <label>التاريخ والوقت</label>
      <input type="datetime-local" id="sgAt" class="form-input" value="<?= date('Y-m-d\TH:00') ?>">
    </div>
    <div class="form-row">
      <label>نوع الحجز</label>
      <select id="sgType" class="form-input">
        <option value="regular">عادي</option>
        <option value="vip">VIP</option>
        <option value="birthday">عيد ميلاد</option>
        <option value="meeting">اجتماع</option>
        <option value="events">مناسبات</option>
        <option value="wedding">أعراس</option>
        <option value="group">جماعي</option>
        <option value="corporate">شركات</option>
      </select>
    </div>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModal()">إلغاء</button>
      <button class="btn btn-primary" onclick="runSuggest()">اقتراح</button>
    </div>
    <div id="sgResult" style="margin-top:12px"></div>
  </div>
</div>

<script>
const tables = <?= json_encode($tables, JSON_UNESCAPED_UNICODE) ?>;
const selected = new Set();

function selectTable(id) {
  const t = tables.find(x => x.id == id);
  if (!t) return;
  if (selected.has(id)) {
    selected.delete(id);
  } else {
    selected.add(id);
  }
  renderSelection();
  if (selected.size === 1) {
    showTableDetails(t);
  }
  document.getElementById('mergeBtn').disabled = selected.size < 2;
}

function renderSelection() {
  document.querySelectorAll('.table-node').forEach(el => {
    el.classList.toggle('selected', selected.has(parseInt(el.dataset.id)));
  });
}

function clearSelection() {
  selected.clear();
  renderSelection();
  document.getElementById('mergeBtn').disabled = true;
}

function showTableDetails(t) {
  const statusLabels = {'available':'فارغة','reserved':'محجوزة','occupied':'مشغولة','cleaning':'تنظيف','bill_requested':'طلب الحساب','merged':'مدمجة'};
  const capacity = t.merged_capacity || t.max_capacity || t.capacity || t.seats || 0;
  let html = '<p><strong>' + t.name + '</strong> — ' + (statusLabels[t.status] || t.status) + '</p>';
  html += '<p>السعة: ' + capacity + ' كرسي</p>';
  if (t.section) html += '<p>القسم: ' + t.section + '</p>';
  if (t.floor) html += '<p>الطابق: ' + t.floor + '</p>';
  if (t.reservation_id) {
    html += '<hr><p><strong>حجز حالي:</strong></p>';
    html += '<p>' + (t.customer_name || '—') + ' — ' + t.guests + ' ضيوف</p>';
    html += '<p>الوقت: ' + t.reserved_at + '</p>';
    html += '<p>النوع: ' + (t.type_name || '—') + '</p>';
  }
  html += '<div class="modal-actions" style="justify-content:flex-start;margin-top:12px">';
  if (t.status === 'available' || t.status === 'reserved') {
    html += '<button class="btn" style="background:#3b82f6;color:#fff" onclick="setCleaning(' + t.id + ')">تنظيف</button>';
  }
  if (t.status === 'cleaning') {
    html += '<button class="btn" style="background:#22c55e;color:#fff" onclick="setStatus(' + t.id + ',\'available\')">متاحة</button>';
  }
  if (t.status === 'merged') {
    html += '<button class="btn" style="background:#ef4444;color:#fff" onclick="splitTable(' + t.id + ')">تقسيم</button>';
  }
  html += '</div>';
  document.getElementById('modalTitle').textContent = 'تفاصيل الطاولة';
  document.getElementById('modalBody').innerHTML = html;
  document.getElementById('tableModal').classList.add('active');
}

function closeModal() {
  document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
}

function mergeSelected() {
  if (selected.size < 2) return;
  if (!confirm('هل تريد دمج الطاولات المحددة؟')) return;
  fetch('/tables/merge', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({table_ids: Array.from(selected)})
  }).then(r => r.json()).then(res => {
    if (res.ok) location.reload();
    else alert(res.error);
  });
}

function splitTable(id) {
  if (!confirm('هل تريد تقسيم هذه الطاولة؟')) return;
  fetch('/tables/' + id + '/split', {method: 'POST'})
    .then(r => r.json()).then(res => {
      if (res.ok) location.reload();
      else alert(res.error);
    });
}

function setCleaning(id) {
  fetch('/tables/' + id + '/cleaning', {method: 'POST'})
    .then(r => r.json()).then(res => {
      if (res.ok) location.reload();
      else alert(res.error);
    });
}

function setStatus(id, status) {
  fetch('/tables/' + id + '/status', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'status=' + status
  }).then(r => r.json()).then(res => {
    if (res.ok) location.reload();
    else alert(res.error);
  });
}

function suggestTable() {
  document.getElementById('suggestModal').classList.add('active');
}

function runSuggest() {
  const guests = document.getElementById('sgGuests').value;
  const at = document.getElementById('sgAt').value;
  const type = document.getElementById('sgType').value;
  const url = '/reservations/suggest-table?guests=' + guests + '&reserved_at=' + encodeURIComponent(at) + '&duration=90&type=' + type;
  fetch(url).then(r => r.json()).then(res => {
    const box = document.getElementById('sgResult');
    if (res.ok && res.table) {
      box.innerHTML = '<div class="alert alert-success">أفضل طاولة: <strong>' + res.table.name + '</strong> (' + res.table.max_guests + ' كرسي)</div>';
    } else {
      box.innerHTML = '<div class="alert alert-warning">لا توجد طاولة مناسبة</div>';
    }
  });
}
</script>
