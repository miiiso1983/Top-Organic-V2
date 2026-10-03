<?php use App\Core\Helpers; ?>
<div class="cc-layout">
  <aside class="cc-sidebar">
    <div class="cc-card">
      <h3><i class="fa-solid fa-magnifying-glass"></i> البحث عن العميل</h3>
      <input type="text" id="customerSearch" placeholder="الاسم أو الهاتف" autocomplete="off">
      <div id="customerResults" class="cc-results"></div>
    </div>
    <div class="cc-card" id="customerCard" style="display:none">
      <h3><i class="fa-solid fa-user"></i> بيانات العميل</h3>
      <div id="customerInfo"></div>
      <div id="customerStats" class="cc-stats"></div>
    </div>
    <div class="cc-card" id="historyCard" style="display:none">
      <h3><i class="fa-solid fa-scroll"></i> تاريخ الحجوزات</h3>
      <div id="reservationHistory" class="cc-history"></div>
    </div>
  </aside>

  <main class="cc-main">
    <div class="cc-card">
      <h3><i class="fa-solid fa-phone"></i> إنشاء / تعديل حجز</h3>
      <form id="bookingForm" onsubmit="return false">
        <input type="hidden" id="customerId" name="customer_id">
        <input type="hidden" id="reservationId" name="reservation_id">
        <div class="cc-form-grid">
          <input type="hidden" id="branchId" name="branch_id" value="<?= $defaultBranch ?>">
          <div class="form-row"><label>العميل</label><input id="customerName" name="customer_name" required placeholder="اسم العميل"></div>
          <div class="form-row"><label>الهاتف</label><input id="customerPhone" name="customer_phone" required placeholder="05xxxxxxxx"></div>
          <div class="form-row"><label>نوع الحجز</label><select id="reservationType" name="reservation_type" required><?php foreach ($types as $t): ?><option value="<?= $t['code'] ?>" data-min="<?= $t['min_guests'] ?>" data-max="<?= $t['max_guests'] ?>" data-duration="<?= $t['duration_minutes'] ?>"><?= Helpers::e($t['name_ar']) ?></option><?php endforeach; ?></select></div>
          <div class="form-row"><label>الضيوف</label><input type="number" id="guests" name="guests" value="2" min="1" required></div>
          <div class="form-row"><label>التاريخ</label><input type="date" id="date" name="date" required></div>
          <div class="form-row"><label>الوقت</label><input type="time" id="time" name="time" required></div>
          <div class="form-row"><label>المدة (دقيقة)</label><input type="number" id="duration" name="duration_minutes" value="90" min="15"></div>
          <div class="form-row"><label>الطاولة</label><select id="tableId" name="table_id"><option value="">—</option></select></div>
        </div>
        <div class="form-row"><label>ملاحظات</label><textarea id="notes" name="notes" rows="2"></textarea></div>
        <div class="form-row"><label>ملاحظات الكول سنتر</label><textarea id="callCenterNotes" name="call_center_notes" rows="2"></textarea></div>
        <div class="cc-actions">
          <button type="button" class="btn btn-primary" id="saveBtn" onclick="saveReservation()"><i class="fa-solid fa-lock"></i> حفظ الحجز</button>
          <button type="button" class="btn btn-success" id="confirmBtn" onclick="confirmReservation()" disabled><i class="fa-solid fa-check"></i> تأكيد</button>
          <button type="button" class="btn btn-warning" id="waitBtn" onclick="waitlistReservation()" disabled><i class="fa-solid fa-hourglass-half"></i> انتظار</button>
          <button type="button" class="btn btn-info" id="rescheduleBtn" onclick="rescheduleReservation()" disabled><i class="fa-solid fa-arrow-right-arrow-left"></i> إعادة جدولة</button>
          <button type="button" class="btn btn-danger" id="cancelBtn" onclick="cancelReservation()" disabled><i class="fa-solid fa-xmark"></i> إلغاء</button>
        </div>
      </form>
    </div>

    <div class="cc-card">
      <h3><i class="fa-solid fa-clock"></i> الفترات الزمنية</h3>
      <div id="slotsPanel" class="cc-slots"></div>
    </div>
  </main>

  <aside class="cc-sidebar">
    <div class="cc-card">
      <h3><i class="fa-solid fa-chart-column"></i> الإشغال</h3>
      <div class="cc-occupancy" id="occupancyValue">0%</div>
      <div class="cc-bar"><div id="occupancyBar" style="width:0%"></div></div>
      <div id="occupancyLegend"></div>
    </div>
    <div class="cc-card">
      <h3><i class="fa-solid fa-chair"></i> الطاولات المتاحة</h3>
      <div id="tablesPanel" class="cc-tables"></div>
    </div>
  </aside>
</div>

<div class="modal" id="newCustomerModal" style="display:none">
  <div class="modal-content">
    <h3><i class="fa-solid fa-plus"></i> عميل جديد</h3>
    <div class="form-row"><label>الاسم</label><input id="newName" required></div>
    <div class="form-row"><label>الهاتف</label><input id="newPhone"></div>
    <div class="form-row"><label>العنوان</label><input id="newAddress"></div>
    <div class="form-row"><label><input type="checkbox" id="newVip"> VIP</label></div>
    <div class="form-row"><label>ملاحظات</label><textarea id="newNotes" rows="2"></textarea></div>
    <div class="cc-actions">
      <button class="btn btn-primary" onclick="createCustomer()">إنشاء</button>
      <button class="btn btn-secondary" onclick="document.getElementById('newCustomerModal').style.display='none'">إغلاق</button>
    </div>
  </div>
</div>

<style>
.cc-layout { display: grid; grid-template-columns: 300px 1fr 280px; gap: 16px; padding: 16px; }
@media (max-width:1100px) { .cc-layout { grid-template-columns: 1fr; } }
.cc-sidebar { display: flex; flex-direction: column; gap: 16px; }
.cc-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 16px; }
.cc-card h3 { margin: 0 0 12px; font-size: 15px; color: #111827; }
.cc-form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; }
.cc-form-grid .form-row { margin-bottom: 0; }
.cc-form-grid label, .form-row label { display: block; font-size: 12px; color: #6b7280; margin-bottom: 4px; }
.cc-form-grid input, .cc-form-grid select, .cc-form-grid textarea, .form-row input, .form-row select, .form-row textarea { width: 100%; padding: 8px 10px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; }
.cc-results { max-height: 180px; overflow-y: auto; margin-top: 8px; }
.cc-result { padding: 8px; border-bottom: 1px solid #f3f4f6; cursor: pointer; }
.cc-result:hover { background: #f9fafb; }
.cc-result .vip { color: #d97706; font-weight: 700; }
.cc-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
.cc-actions .btn { padding: 8px 12px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
.cc-actions .btn-primary { background: #10914d; color: #fff; }
.cc-actions .btn-success { background: #16a34a; color: #fff; }
.cc-actions .btn-warning { background: #f59e0b; color: #fff; }
.cc-actions .btn-info { background: #3b82f6; color: #fff; }
.cc-actions .btn-danger { background: #b91c1c; color: #fff; }
.cc-actions .btn-secondary { background: #e5e7eb; color: #374151; }
.cc-occupancy { font-size: 32px; font-weight: 700; color: #10914d; text-align: center; }
.cc-bar { height: 10px; background: #e5e7eb; border-radius: 5px; overflow: hidden; margin: 8px 0; }
.cc-bar > div { height: 100%; background: #10914d; transition: width .3s; }
.cc-slots { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 6px; }
.cc-slot { padding: 8px; border: 1px solid #e5e7eb; border-radius: 6px; text-align: center; font-size: 12px; cursor: pointer; }
.cc-slot:hover { background: #f3f4f6; }
.cc-slot.selected { background: #10914d; color: #fff; border-color: #10914d; }
.cc-slot.full { background: #fee2e2; color: #b91c1c; cursor: not-allowed; }
.cc-tables { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 6px; }
.cc-table { padding: 8px; border: 1px solid #e5e7eb; border-radius: 6px; text-align: center; font-size: 12px; cursor: pointer; }
.cc-table:hover { border-color: #10914d; }
.cc-table.selected { background: #10914d; color: #fff; }
.cc-history { max-height: 300px; overflow-y: auto; }
.cc-history-item { padding: 8px; border-bottom: 1px solid #f3f4f6; font-size: 13px; }
.cc-history-item .status { font-weight: 700; }
.cc-history-item .cancelled { color: #b91c1c; }
.modal { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 100; }
.modal-content { background: #fff; padding: 24px; border-radius: 12px; width: 360px; max-width: 90%; }
</style>

<script>
let currentCustomer = null;
let currentReservation = null;

const searchInput = document.getElementById('customerSearch');
const resultsBox = document.getElementById('customerResults');
searchInput.addEventListener('input', async () => {
  const q = searchInput.value.trim();
  if (!q) { resultsBox.innerHTML = ''; return; }
  const res = await fetch('/call-center/customers/search?q=' + encodeURIComponent(q)).then(r => r.json());
  resultsBox.innerHTML = '';
  if (!res.ok || !res.customers.length) {
    resultsBox.innerHTML = '<div class="cc-result" onclick="showNewCustomerModal()">+ إنشاء عميل جديد</div>';
    return;
  }
  res.customers.forEach(c => {
    const div = document.createElement('div');
    div.className = 'cc-result';
    div.innerHTML = '<div>' + (c.is_vip ? '<span class="vip">⭐ VIP</span> ' : '') + c.name + '</div><div style="font-size:11px;color:#6b7280">' + (c.phone || '—') + '</div>';
    div.onclick = () => loadCustomer(c.id);
    resultsBox.appendChild(div);
  });
});

function showNewCustomerModal() {
  document.getElementById('newCustomerModal').style.display = 'flex';
  document.getElementById('newPhone').value = searchInput.value;
}

async function createCustomer() {
  const body = {
    name: document.getElementById('newName').value,
    phone: document.getElementById('newPhone').value,
    address: document.getElementById('newAddress').value,
    is_vip: document.getElementById('newVip').checked ? 1 : 0,
    notes: document.getElementById('newNotes').value,
  };
  const res = await fetch('/call-center/customers', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)}).then(r => r.json());
  if (res.ok) {
    document.getElementById('newCustomerModal').style.display = 'none';
    loadCustomer(res.customer.id);
  } else {
    alert(res.error);
  }
}

async function loadCustomer(id) {
  const res = await fetch('/call-center/customers/' + id).then(r => r.json());
  if (!res.ok) return alert(res.error);
  currentCustomer = res.customer;
  document.getElementById('customerCard').style.display = 'block';
  document.getElementById('historyCard').style.display = 'block';
  document.getElementById('customerId').value = res.customer.id;
  document.getElementById('customerName').value = res.customer.name;
  document.getElementById('customerPhone').value = res.customer.phone || '';
  document.getElementById('customerInfo').innerHTML = '<strong>' + res.customer.name + '</strong>' + (res.customer.is_vip ? ' <span style="color:#d97706">⭐ VIP</span>' : '') + '<br>' + (res.customer.phone || '') + '<br><small>' + (res.customer.notes || '') + '</small>';
  document.getElementById('customerStats').innerHTML = 'الحجوزات: ' + res.reservations.length + ' | الملغاة: ' + res.cancelled.length;
  const hist = document.getElementById('reservationHistory');
  hist.innerHTML = '';
  res.reservations.forEach(r => {
    const div = document.createElement('div');
    div.className = 'cc-history-item';
    div.innerHTML = '<div>' + r.reservation_no + ' — ' + r.reserved_at.split(' ')[0] + ' ' + r.reserved_at.split(' ')[1] + '</div><div>الضيوف: ' + r.guests + ' | <span class="status ' + (r.status === 'cancelled' ? 'cancelled' : '') + '">' + r.status + '</span></div>' + (r.notes ? '<div style="color:#6b7280;font-size:11px">' + r.notes + '</div>' : '');
    div.onclick = () => loadReservation(r);
    hist.appendChild(div);
  });
  resultsBox.innerHTML = '';
}

function loadReservation(r) {
  currentReservation = r;
  document.getElementById('reservationId').value = r.id;
  document.getElementById('reservationType').value = r.reservation_type || 'regular';
  document.getElementById('guests').value = r.guests;
  document.getElementById('date').value = r.reserved_at.split(' ')[0];
  document.getElementById('time').value = r.reserved_at.split(' ')[1].substring(0, 5);
  document.getElementById('duration').value = r.duration_minutes || 90;
  document.getElementById('tableId').value = r.table_id || '';
  document.getElementById('notes').value = r.notes || '';
  document.getElementById('callCenterNotes').value = r.call_center_notes || '';
  document.getElementById('confirmBtn').disabled = false;
  document.getElementById('waitBtn').disabled = false;
  document.getElementById('rescheduleBtn').disabled = false;
  document.getElementById('cancelBtn').disabled = false;
  refreshAll();
}

function getFormData() {
  const date = document.getElementById('date').value;
  const time = document.getElementById('time').value;
  return {
    customer_id: parseInt(document.getElementById('customerId').value) || null,
    customer_name: document.getElementById('customerName').value,
    customer_phone: document.getElementById('customerPhone').value,
    branch_id: parseInt(document.getElementById('branchId').value) || null,
    reservation_type: document.getElementById('reservationType').value,
    guests: parseInt(document.getElementById('guests').value),
    reserved_at: date + ' ' + time + ':00',
    duration_minutes: parseInt(document.getElementById('duration').value),
    table_id: document.getElementById('tableId').value || null,
    notes: document.getElementById('notes').value,
    call_center_notes: document.getElementById('callCenterNotes').value,
  };
}

async function saveReservation() {
  const data = getFormData();
  const id = document.getElementById('reservationId').value;
  const url = id ? '/call-center/reservations/' + id : '/call-center/reservations';
  const res = await fetch(url, {method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data)}).then(r => r.json());
  if (res.ok) {
    alert('تم حفظ الحجز: ' + res.reservation.reservation_no);
    resetForm();
  } else {
    alert(res.error);
  }
}

function resetForm() {
  currentCustomer = null;
  currentReservation = null;
  document.getElementById('reservationId').value = '';
  document.getElementById('customerId').value = '';
  document.getElementById('customerName').value = '';
  document.getElementById('customerPhone').value = '';
  document.getElementById('reservationType').selectedIndex = 0;
  document.getElementById('guests').value = 2;
  document.getElementById('date').value = new Date().toISOString().split('T')[0];
  document.getElementById('time').value = '';
  document.getElementById('duration').value = 90;
  document.getElementById('tableId').value = '';
  document.getElementById('notes').value = '';
  document.getElementById('callCenterNotes').value = '';
  document.getElementById('customerSearch').value = '';
  document.getElementById('customerResults').innerHTML = '';
  document.getElementById('customerCard').style.display = 'none';
  document.getElementById('historyCard').style.display = 'none';
  document.getElementById('customerInfo').innerHTML = '';
  document.getElementById('customerStats').innerHTML = '';
  document.getElementById('reservationHistory').innerHTML = '';
  document.getElementById('confirmBtn').disabled = true;
  document.getElementById('waitBtn').disabled = true;
  document.getElementById('rescheduleBtn').disabled = true;
  document.getElementById('cancelBtn').disabled = true;
  refreshAll();
}

async function confirmReservation() {
  if (!currentReservation) return;
  const res = await fetch('/call-center/reservations/' + currentReservation.id + '/status', {method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({status:'confirmed'})}).then(r => r.json());
  if (res.ok) { alert('تم التأكيد'); loadCustomer(currentCustomer.id); }
  else alert(res.error);
}

async function waitlistReservation() {
  if (!currentReservation) return;
  const res = await fetch('/call-center/reservations/' + currentReservation.id + '/status', {method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({status:'waiting'})}).then(r => r.json());
  if (res.ok) { alert('تم نقل الحجز للانتظار'); loadCustomer(currentCustomer.id); }
  else alert(res.error);
}

async function rescheduleReservation() {
  if (!currentReservation) return;
  const data = getFormData();
  const res = await fetch('/call-center/reservations/' + currentReservation.id + '/reschedule', {method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({reserved_at: data.reserved_at, duration_minutes: data.duration_minutes})}).then(r => r.json());
  if (res.ok) { alert('تمت إعادة الجدولة'); loadCustomer(currentCustomer.id); }
  else alert(res.error);
}

async function cancelReservation() {
  if (!currentReservation) return;
  const reason = prompt('سبب الإلغاء؟') || '';
  const res = await fetch('/call-center/reservations/' + currentReservation.id + '/cancel', {method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({reason})}).then(r => r.json());
  if (res.ok) { alert('تم الإلغاء'); loadCustomer(currentCustomer.id); }
  else alert(res.error);
}

async function refreshAll() {
  loadSlots();
  loadOccupancy();
  loadTables();
}

async function loadSlots() {
  const branchId = document.getElementById('branchId').value;
  const date = document.getElementById('date').value;
  const guests = document.getElementById('guests').value;
  const type = document.getElementById('reservationType').value;
  if (!branchId || !date) return;
  const res = await fetch('/call-center/slots?branch_id=' + branchId + '&date=' + date + '&guests=' + guests + '&type=' + type).then(r => r.json());
  const panel = document.getElementById('slotsPanel');
  panel.innerHTML = '';
  if (!res.ok || !res.slots.length) { panel.innerHTML = '<div>لا توجد فترات</div>'; return; }
  res.slots.forEach(s => {
    const btn = document.createElement('div');
    btn.className = 'cc-slot' + (s.available ? '' : ' full') + (document.getElementById('time').value === s.time ? ' selected' : '');
    btn.innerHTML = '<div>' + s.time + '</div><div>' + (s.available ? s.remaining : 'ممتلئ') + '</div>';
    if (s.available) {
      btn.onclick = () => { document.getElementById('time').value = s.time; document.querySelectorAll('.cc-slot').forEach(el => el.classList.remove('selected')); btn.classList.add('selected'); loadTables(); };
    }
    panel.appendChild(btn);
  });
}

async function loadOccupancy() {
  const branchId = document.getElementById('branchId').value;
  const date = document.getElementById('date').value || new Date().toISOString().split('T')[0];
  if (!branchId) return;
  const res = await fetch('/call-center/occupancy?branch_id=' + branchId + '&date=' + date).then(r => r.json());
  if (!res.ok) return;
  document.getElementById('occupancyValue').textContent = res.occupancy_rate + '%';
  document.getElementById('occupancyBar').style.width = res.occupancy_rate + '%';
  const color = res.occupancy_rate > 80 ? '#b91c1c' : res.occupancy_rate > 50 ? '#f59e0b' : '#10914d';
  document.getElementById('occupancyBar').style.background = color;
  document.getElementById('occupancyValue').style.color = color;
  let legend = 'الطاقة: ' + res.capacity + ' ضيف';
  document.getElementById('occupancyLegend').innerHTML = legend;
}

async function loadTables() {
  const branchId = document.getElementById('branchId').value;
  const date = document.getElementById('date').value;
  const time = document.getElementById('time').value;
  const guests = document.getElementById('guests').value;
  const duration = document.getElementById('duration').value;
  if (!branchId || !date || !time) return;
  const res = await fetch('/call-center/tables?branch_id=' + branchId + '&date=' + date + '&time=' + time + '&guests=' + guests + '&duration=' + duration).then(r => r.json());
  const panel = document.getElementById('tablesPanel');
  panel.innerHTML = '';
  if (!res.ok || !res.tables.length) { panel.innerHTML = '<div>لا توجد طاولات متاحة</div>'; return; }
  res.tables.forEach(t => {
    const btn = document.createElement('div');
    btn.className = 'cc-table' + (document.getElementById('tableId').value == t.id ? ' selected' : '');
    btn.innerHTML = '<div>' + t.name + '</div><div>' + (t.max_guests || t.capacity || t.seats) + ' مقعد</div>';
    btn.onclick = () => { document.getElementById('tableId').value = t.id; document.querySelectorAll('.cc-table').forEach(el => el.classList.remove('selected')); btn.classList.add('selected'); };
    panel.appendChild(btn);
  });
}

['branchId','reservationType','guests','date'].forEach(id => document.getElementById(id).addEventListener('change', refreshAll));
document.getElementById('time').addEventListener('change', loadTables);

document.getElementById('date').value = new Date().toISOString().split('T')[0];
refreshAll();
</script>
