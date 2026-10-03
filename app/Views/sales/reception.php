<?php use App\Core\Helpers; ?>
<style>
.reception-header { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.reception-header form { display: flex; gap: 8px; flex-wrap: wrap; }
.reception-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; }
.reception-col { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
.reception-col h3 { margin: 0; padding: 12px 16px; background: #f3f4f6; font-size: 15px; display: flex; justify-content: space-between; align-items: center; }
.reception-col h3 .badge { background: #10914d; color: #fff; border-radius: 12px; padding: 2px 10px; font-size: 12px; }
.reception-list { padding: 8px; max-height: 60vh; overflow-y: auto; }
.reception-card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; margin-bottom: 10px; background: #fafafa; }
.reception-card.late { border-right: 4px solid #ef4444; }
.reception-card.waiting { border-right: 4px solid #f59e0b; }
.reception-card.current { border-right: 4px solid #10b981; }
.reception-card.cancelled { opacity: .7; }
.reception-card .time { font-size: 13px; color: #6b7280; }
.reception-card .name { font-weight: 700; font-size: 15px; color: #111827; }
.reception-card .meta { font-size: 13px; color: #4b5563; margin: 4px 0; }
.reception-card .actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
.reception-card .actions button { padding: 5px 10px; font-size: 12px; border: none; border-radius: 6px; cursor: pointer; }
.btn-checkin { background: #10b981; color: #fff; }
.btn-noshow { background: #ef4444; color: #fff; }
.btn-table { background: #3b82f6; color: #fff; }
.btn-complete { background: #6366f1; color: #fff; }
.btn-notes { background: #9ca3af; color: #fff; }
.empty-state { text-align: center; padding: 20px; color: #9ca3af; font-size: 13px; }
.modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; }
.modal.active { display: flex; }
.modal-box { background: #fff; border-radius: 12px; padding: 20px; width: 90%; max-width: 420px; }
.modal-box h4 { margin-top: 0; }
.modal-box select, .modal-box textarea { width: 100%; padding: 10px; margin-bottom: 12px; border: 1px solid #e5e7eb; border-radius: 8px; }
.modal-box .modal-actions { display: flex; gap: 8px; justify-content: flex-end; }
@media (max-width: 768px) {
  .reception-grid { grid-template-columns: 1fr; }
  .reception-header { flex-direction: column; align-items: stretch; }
  .reception-card .actions button { flex: 1; }
}
</style>

<div class="reception-header">
  <h2 style="margin:0"><i class="fa-solid fa-bell-concierge"></i> شاشة الاستقبال</h2>
  <form method="GET" action="/reception">
    <input type="date" name="date" class="form-input" value="<?= $dashboard['date'] ?>">
    <select name="branch_id" class="form-input">
      <option value="0">كل الفروع</option>
      <?php foreach ($branches as $b): ?>
      <option value="<?= $b['id'] ?>" <?= $dashboard['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">عرض</button>
  </form>
</div>

<div class="reception-grid">
  <?php
  $sections = [
    'current' => ['title' => 'الحجوزات الحالية', 'class' => 'current', 'badge' => '#10b981'],
    'upcoming' => ['title' => 'الحجوزات القادمة', 'class' => '', 'badge' => '#3b82f6'],
    'confirmed' => ['title' => 'الحجوزات المؤكدة', 'class' => '', 'badge' => '#10914d'],
    'late' => ['title' => 'العملاء المتأخرين', 'class' => 'late', 'badge' => '#ef4444'],
    'waiting' => ['title' => 'العملاء المنتظرين', 'class' => 'waiting', 'badge' => '#f59e0b'],
    'cancelled' => ['title' => 'الحجوزات الملغاة', 'class' => 'cancelled', 'badge' => '#6b7280'],
  ];
  foreach ($sections as $key => $cfg):
    $items = $dashboard['categories'][$key] ?? [];
  ?>
  <div class="reception-col">
    <h3>
      <?= $cfg['title'] ?>
      <span class="badge" style="background:<?= $cfg['badge'] ?>"><?= count($items) ?></span>
    </h3>
    <div class="reception-list">
      <?php if (empty($items)): ?>
        <div class="empty-state">لا يوجد</div>
      <?php else: foreach ($items as $r): ?>
        <div class="reception-card <?= $cfg['class'] ?>" data-id="<?= $r['id'] ?>">
          <div class="time"><?= date('H:i', strtotime($r['reserved_at'])) ?> — <?= date('Y-m-d', strtotime($r['reserved_at'])) ?></div>
          <div class="name"><?= Helpers::e($r['customer_name'] ?: '—') ?></div>
          <div class="meta">
            <?= (int)$r['guests'] ?> ضيوف
            <?= $r['customer_phone'] ? ' • ' . Helpers::e($r['customer_phone']) : '' ?>
            <?= $r['table_name'] ? ' • طاولة ' . Helpers::e($r['table_name']) : '' ?>
            <?= $r['type_name'] ? ' • ' . Helpers::e($r['type_name']) : '' ?>
          </div>
          <?php if ($r['notes']): ?>
          <div class="meta" style="font-style:italic">ملاحظة: <?= Helpers::e($r['notes']) ?></div>
          <?php endif; ?>
          <div class="actions">
            <?php if (in_array($r['status'], ['confirmed','pending'], true)): ?>
              <button class="btn-checkin" onclick="action(<?= $r['id'] ?>, 'check-in')">تسجيل وصول</button>
              <button class="btn-noshow" onclick="action(<?= $r['id'] ?>, 'no-show')">عدم حضور</button>
              <button class="btn-table" onclick="openTable(<?= $r['id'] ?>, <?= (int)$r['table_id'] ?>, <?= (int)$r['guests'] ?>)">تحويل لطاولة</button>
            <?php endif; ?>
            <?php if ($r['status'] === 'seated'): ?>
              <button class="btn-table" onclick="openTable(<?= $r['id'] ?>, <?= (int)$r['table_id'] ?>, <?= (int)$r['guests'] ?>)">تغيير الطاولة</button>
              <button class="btn-complete" onclick="action(<?= $r['id'] ?>, 'complete')">إنهاء الحجز</button>
            <?php endif; ?>
            <?php if ($r['status'] === 'waiting'): ?>
              <button class="btn-table" onclick="openTable(<?= $r['id'] ?>, 0, <?= (int)$r['guests'] ?>)">تخصيص طاولة</button>
            <?php endif; ?>
            <button class="btn-notes" onclick="openNotes(<?= $r['id'] ?>, '<?= Helpers::e(str_replace("'", "\\'", $r['notes'] ?? '')) ?>')">ملاحظات</button>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="modal" id="tableModal">
  <div class="modal-box">
    <h4>اختيار الطاولة</h4>
    <select id="tableSelect">
      <option value="">— اختر طاولة —</option>
      <?php foreach ($tables as $t): ?>
      <option value="<?= $t['id'] ?>" data-capacity="<?= $t['max_guests'] ?>">
        <?= Helpers::e($t['name']) ?> (<?= $t['floor'] ?>) — <?= $t['max_guests'] ?> كراسي
      </option>
      <?php endforeach; ?>
    </select>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModals()">إلغاء</button>
      <button class="btn btn-primary" onclick="saveTable()">حفظ</button>
    </div>
  </div>
</div>

<div class="modal" id="notesModal">
  <div class="modal-box">
    <h4>إضافة ملاحظات</h4>
    <textarea id="notesText" rows="4" placeholder="اكتب الملاحظات هنا..."></textarea>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModals()">إلغاء</button>
      <button class="btn btn-primary" onclick="saveNotes()">حفظ</button>
    </div>
  </div>
</div>

<script>
let currentId = 0;
let currentGuests = 2;

function action(id, type) {
  if (!confirm('هل أنت متأكد؟')) return;
  fetch('/reception/' + id + '/' + type, {method: 'POST'})
    .then(r => r.json()).then(res => {
      if (res.ok) location.reload();
      else alert(res.error);
    });
}

function openTable(id, currentTableId, guests) {
  currentId = id;
  currentGuests = guests;
  document.getElementById('tableSelect').value = currentTableId || '';
  document.getElementById('tableModal').classList.add('active');
}

function openNotes(id, notes) {
  currentId = id;
  document.getElementById('notesText').value = notes;
  document.getElementById('notesModal').classList.add('active');
}

function closeModals() {
  document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
}

function saveTable() {
  const tableId = document.getElementById('tableSelect').value;
  const capacity = document.getElementById('tableSelect').selectedOptions[0]?.dataset.capacity || 0;
  if (!tableId) return alert('اختر طاولة');
  if (parseInt(capacity) < currentGuests) {
    if (!confirm('سعة الطاولة أقل من عدد الضيوف. هل تريد المتابعة؟')) return;
  }
  fetch('/reception/' + currentId + '/table', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'table_id=' + encodeURIComponent(tableId)
  }).then(r => r.json()).then(res => {
    if (res.ok) location.reload();
    else alert(res.error);
  });
}

function saveNotes() {
  const notes = document.getElementById('notesText').value;
  fetch('/reception/' + currentId + '/notes', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'notes=' + encodeURIComponent(notes)
  }).then(r => r.json()).then(res => {
    if (res.ok) location.reload();
    else alert(res.error);
  });
}
</script>
