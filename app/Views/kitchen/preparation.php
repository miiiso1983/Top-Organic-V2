<?php use App\Core\Helpers; ?>
<div class="page-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
  <div>
    <h2><i class="fa-solid fa-utensils"></i> <?= Helpers::e($kitchen['name']) ?></h2>
    <span style="color:#6b7280">تحديث تلقائي كل 10 ثوانٍ</span>
  </div>
  <div>
    <a href="/kitchen/preparation/<?= (int)$kitchen['id'] ?>/receipt" target="_blank" class="btn btn-sm btn-secondary"><i class="fa-solid fa-print"></i> طباعة البون</a>
    <a href="/kitchen" class="btn btn-sm btn-secondary">الشاشة المجمعة</a>
  </div>
</div>
<div class="kds" id="kds">
  <div class="card" style="grid-column:1/-1;text-align:center;color:#6b7280">جاري تحميل الطلبات...</div>
</div>

<script>
const kitchenId = <?= (int)$kitchen['id'] ?>;
const typeLabels = { dine_in: 'صالة', takeaway: 'سفري', delivery: 'توصيل', online: 'إلكتروني' };

function formatTimer(totalSeconds) {
  const h = Math.floor(totalSeconds / 3600);
  const m = Math.floor((totalSeconds % 3600) / 60);
  const s = totalSeconds % 60;
  const parts = [String(m).padStart(2, '0'), String(s).padStart(2, '0')];
  if (h > 0) parts.unshift(String(h).padStart(2, '0'));
  return parts.join(':');
}

function updateTimers() {
  const now = Date.now();
  document.querySelectorAll('.kds-timer').forEach(el => {
    const created = new Date(el.dataset.createdAt).getTime();
    const seconds = Math.max(0, Math.floor((now - created) / 1000));
    el.textContent = formatTimer(seconds);
    if (seconds > 600) el.classList.add('late');
    else el.classList.remove('late');
  });
}

function locationText(o) {
  const parts = [];
  if (o.table_hall) parts.push(o.table_hall);
  if (o.table_floor && !o.table_hall) parts.push('طابق ' + o.table_floor);
  if (o.table_name) parts.push('طاولة ' + o.table_name);
  if (o.order_type === 'takeaway') parts.push('سفري');
  if (o.order_type === 'delivery') parts.push('توصيل');
  if (o.order_type === 'online') parts.push('إلكتروني');
  return parts.length ? parts.join(' – ') : 'داخلي';
}

function render(orders) {
  const kds = document.getElementById('kds');
  if (!orders.length) {
    kds.innerHTML = '<div class="card" style="grid-column:1/-1;text-align:center;padding:40px 20px"><div style="font-size:48px;margin-bottom:12px"><i class="fa-solid fa-utensils"></i></div><div style="font-size:18px;font-weight:600">لا توجد طلبات لهذا المطبخ</div><div style="color:#6b7280;margin-top:8px">سيتم عرض الطلبات الجديدة تلقائياً هنا</div></div>';
    return;
  }
  kds.innerHTML = orders.map(o => `
    <div class="kds-card ${o.overdue ? 'overdue' : ''}" data-order-id="${o.order_id}">
      <div class="head">
        <div class="head-right">
          <div class="order-no">${o.order_no}</div>
          <div class="order-location">${locationText(o)}</div>
        </div>
        <div class="head-left">
          <div class="kds-timer" data-created-at="${o.created_at}">00:00</div>
        </div>
      </div>
      <ul class="kds-items">
        ${o.items.map(it => `
          <li>
            <span class="item-name">
              <strong>${it.qty}×</strong> ${it.name}
              ${it.notes ? `<br><small style="color:#d97706"><i class="fa-solid fa-pen"></i> ${it.notes}</small>` : ''}
            </span>
          </li>`).join('')}
      </ul>
      <div class="kds-actions">
        <button class="btn btn-success" onclick="readyKitchenOrder(${o.order_id})">جاهز للمطبخ</button>
      </div>
    </div>`).join('');
  updateTimers();
}

async function readyKitchenOrder(orderId) {
  if (!confirm('تأكيد تجهيز أصناف هذا المطبخ في الطلب؟')) return;
  await fetch(`/kitchen/preparation/${kitchenId}/order/${orderId}/ready`, { method: 'POST' });
  refresh();
}

async function refresh() {
  try {
    const res = await fetch(`/kitchen/preparation/${kitchenId}/data`);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const text = await res.text();
    console.log('KDS raw response:', text);
    const data = text ? JSON.parse(text) : {};
    const orders = Array.isArray(data.orders) ? data.orders : [];
    console.log('KDS orders:', orders.length, orders);
    render(orders);
  } catch (e) {
    console.error('KDS refresh error:', e);
    document.getElementById('kds').innerHTML = '<div class="card" style="grid-column:1/-1;text-align:center;color:#dc2626">فشل تحميل البيانات: ' + e.message + '<br><button class="btn btn-sm btn-secondary" onclick="refresh()" style="margin-top:10px">إعادة المحاولة</button></div>';
  }
}

refresh();
setInterval(refresh, 10000);
setInterval(updateTimers, 1000);
</script>
