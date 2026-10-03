<?php use App\Core\Helpers; ?>
<style>
.wl-header { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.wl-header form { display: flex; gap: 8px; flex-wrap: wrap; }
.wl-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; }
.wl-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; }
.wl-card.vip { border-right: 4px solid #f59e0b; }
.wl-card .time { font-size: 13px; color: #6b7280; }
.wl-card .name { font-weight: 700; font-size: 15px; color: #111827; }
.wl-card .meta { font-size: 13px; color: #4b5563; margin: 4px 0; }
.wl-card .priority { display: inline-block; background: #111827; color: #fff; border-radius: 12px; padding: 2px 10px; font-size: 12px; }
.wl-card .actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; }
.wl-card .actions button { padding: 5px 10px; font-size: 12px; border: none; border-radius: 6px; cursor: pointer; }
.btn-checkin { background: #10b981; color: #fff; }
.btn-remove { background: #ef4444; color: #fff; }
.btn-suggest { background: #3b82f6; color: #fff; }
.btn-notify { background: #8b5cf6; color: #fff; }
.empty-state { text-align: center; padding: 30px; color: #9ca3af; }
.modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; }
.modal.active { display: flex; }
.modal-box { background: #fff; border-radius: 12px; padding: 20px; width: 90%; max-width: 420px; }
.modal-box h4 { margin-top: 0; }
.modal-box select, .modal-box input, .modal-box textarea { width: 100%; padding: 10px; margin-bottom: 12px; border: 1px solid #e5e7eb; border-radius: 8px; }
.modal-actions { display: flex; gap: 8px; justify-content: flex-end; }
@media (max-width: 768px) {
  .wl-grid { grid-template-columns: 1fr; }
  .wl-header { flex-direction: column; align-items: stretch; }
}
</style>

<div class="wl-header">
  <h2 style="margin:0"><i class="fa-solid fa-hourglass-half"></i> قائمة الانتظار</h2>
  <form method="GET" action="/waiting-list">
    <select name="branch_id" class="form-input">
      <option value="0">كل الفروع</option>
      <?php foreach ($branches as $b): ?>
      <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">عرض</button>
  </form>
  <button class="btn btn-primary" onclick="openAdd()">+ إضافة عميل</button>
</div>

<?php if (empty($items)): ?>
  <div class="empty-state">لا يوجد عملاء في قائمة الانتظار</div>
<?php else: ?>
  <div class="wl-grid">
    <?php foreach ($items as $r): ?>
    <div class="wl-card <?= $r['is_vip'] ? 'vip' : '' ?>" data-id="<?= $r['id'] ?>">
      <div class="time"><?= date('H:i', strtotime($r['expected_arrival_at'])) ?> — <?= date('Y-m-d', strtotime($r['expected_arrival_at'])) ?></div>
      <div class="name">
        <?= Helpers::e($r['customer_name'] ?: '—') ?>
        <?php if ($r['is_vip']): ?><span style="color:#f59e0b">⭐ VIP</span><?php endif; ?>
      </div>
      <div class="meta">
        <?= (int)($r['party_size'] ?? $r['guests']) ?> أفراد
        <?= $r['customer_phone'] ? ' • ' . Helpers::e($r['customer_phone']) : '' ?>
        <?= $r['branch_name'] ? ' • ' . Helpers::e($r['branch_name']) : '' ?>
      </div>
      <div class="meta">
        <span class="priority">الأولوية: <?= (int)$r['priority_score'] ?></span>
        <?php if ($r['waiting_notified']): ?><span style="color:#10b981"> • تم التنبيه</span><?php endif; ?>
      </div>
      <?php if ($r['notes']): ?>
      <div class="meta" style="font-style:italic">ملاحظة: <?= Helpers::e($r['notes']) ?></div>
      <?php endif; ?>
      <div class="actions">
        <button class="btn-checkin" onclick="openCheckIn(<?= $r['id'] ?>, <?= (int)($r['party_size'] ?? $r['guests']) ?>)">تحويل لطاولة</button>
        <button class="btn-suggest" onclick="suggest(<?= $r['id'] ?>)"><i class="fa-solid fa-robot"></i> اقتراح طاولة</button>
        <button class="btn-notify" onclick="action(<?= $r['id'] ?>, 'notify')">إشعار</button>
        <button class="btn-remove" onclick="action(<?= $r['id'] ?>, 'remove')">إزالة</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="modal" id="addModal">
  <div class="modal-box">
    <h4>إضافة عميل لقائمة الانتظار</h4>
    <input type="text" id="addName" placeholder="اسم العميل" class="form-input">
    <input type="text" id="addPhone" placeholder="رقم الهاتف" class="form-input">
    <input type="email" id="addEmail" placeholder="البريد الإلكتروني (اختياري)" class="form-input">
    <select id="addBranch" class="form-input">
      <?php foreach ($branches as $b): ?>
      <option value="<?= $b['id'] ?>"><?= Helpers::e($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="number" id="addParty" value="2" min="1" class="form-input">
    <input type="datetime-local" id="addAt" class="form-input" value="<?= date('Y-m-d\TH:00') ?>">
    <input type="number" id="addDuration" value="90" min="15" class="form-input">
    <label style="display:flex;align-items:center;gap:8px;font-size:13px">
      <input type="checkbox" id="addVip" value="1"> عميل VIP
    </label>
    <textarea id="addNotes" rows="2" placeholder="ملاحظات" class="form-input"></textarea>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModals()">إلغاء</button>
      <button class="btn btn-primary" onclick="saveAdd()">حفظ</button>
    </div>
  </div>
</div>

<div class="modal" id="checkInModal">
  <div class="modal-box">
    <h4>تحويل إلى طاولة</h4>
    <select id="tableSelect" class="form-input">
      <option value="">— اختر طاولة —</option>
      <?php foreach ($tables as $t): ?>
      <option value="<?= $t['id'] ?>" data-capacity="<?= $t['max_guests'] ?>">
        <?= Helpers::e($t['name']) ?> (<?= Helpers::e($t['floor']) ?>) — <?= $t['max_guests'] ?> كراسي
      </option>
      <?php endforeach; ?>
    </select>
    <div id="suggestResult" style="margin-bottom:12px;font-size:13px"></div>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModals()">إلغاء</button>
      <button class="btn btn-primary" onclick="saveCheckIn()">تحويل</button>
    </div>
  </div>
</div>

<script>
let currentId = 0;
let currentParty = 2;

function openAdd() {
  document.getElementById('addModal').classList.add('active');
}

function openCheckIn(id, party) {
  currentId = id;
  currentParty = party;
  document.getElementById('suggestResult').innerHTML = '';
  document.getElementById('tableSelect').value = '';
  document.getElementById('checkInModal').classList.add('active');
}

function closeModals() {
  document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
}

function action(id, type) {
  if (!confirm('هل أنت متأكد؟')) return;
  fetch('/waiting-list/' + id + '/' + type, {method: 'POST'})
    .then(r => r.json()).then(res => {
      if (res.ok) location.reload();
      else alert(res.error);
    });
}

function saveAdd() {
  const body = new URLSearchParams();
  body.append('customer_name', document.getElementById('addName').value);
  body.append('customer_phone', document.getElementById('addPhone').value);
  body.append('customer_email', document.getElementById('addEmail').value);
  body.append('branch_id', document.getElementById('addBranch').value);
  body.append('party_size', document.getElementById('addParty').value);
  body.append('expected_arrival_at', document.getElementById('addAt').value);
  body.append('duration_minutes', document.getElementById('addDuration').value);
  body.append('is_vip', document.getElementById('addVip').checked ? 1 : 0);
  body.append('notes', document.getElementById('addNotes').value);
  fetch('/waiting-list', {method: 'POST', body: body})
    .then(r => r.json()).then(res => {
      if (res.ok) location.reload();
      else alert(res.error);
    });
}

function suggest(id) {
  fetch('/waiting-list/' + id + '/suggest-table')
    .then(r => r.json()).then(res => {
      if (res.ok && res.table) {
        alert('أفضل طاولة: ' + res.table.name + ' (' + res.table.max_guests + ' كراسي)');
      } else {
        alert('لا توجد طاولة مناسبة متاحة');
      }
    });
}

function saveCheckIn() {
  const tableId = document.getElementById('tableSelect').value;
  const capacity = document.getElementById('tableSelect').selectedOptions[0]?.dataset.capacity || 0;
  if (!tableId) return alert('اختر طاولة');
  if (parseInt(capacity) < currentParty) {
    if (!confirm('سعة الطاولة أقل من عدد الأفراد. هل تريد المتابعة؟')) return;
  }
  const body = new URLSearchParams();
  body.append('table_id', tableId);
  fetch('/waiting-list/' + currentId + '/check-in', {method: 'POST', body: body})
    .then(r => r.json()).then(res => {
      if (res.ok) location.reload();
      else alert(res.error);
    });
}
</script>
