// نقطة البيع: سلة محلية تدعم العمل أوف لاين ثم الإرسال عند الاتصال
let cart = [];

document.querySelectorAll('.pos-item').forEach(el => {
  el.addEventListener('click', () => {
    const id = +el.dataset.id;
    const existing = cart.find(l => l.id === id);
    if (existing) existing.qty++;
    else cart.push({ id, name: el.dataset.name, price: +el.dataset.price, qty: 1 });
    renderCart();
  });
});

document.querySelectorAll('.pos-cats button').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.pos-cats button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const cat = btn.dataset.cat;
    document.querySelectorAll('.pos-item').forEach(item => {
      item.style.display = (cat === 'all' || item.dataset.cat === cat) ? '' : 'none';
    });
  });
});

document.getElementById('order-type').addEventListener('change', e => {
  const type = e.target.value;
  document.getElementById('table-row').style.display = type === 'dine_in' ? '' : 'none';
  document.getElementById('delivery-row').style.display = type === 'delivery' ? '' : 'none';
  document.getElementById('app-type-row').style.display = type === 'online' ? '' : 'none';
});
document.getElementById('discount').addEventListener('input', renderCart);

function renderCart() {
  const tbody = document.querySelector('#cart-table tbody');
  tbody.innerHTML = '';
  let subtotal = 0;
  cart.forEach((line, i) => {
    const lineTotal = line.price * line.qty;
    subtotal += lineTotal;
    tbody.innerHTML += `<tr>
      <td>${line.name}</td>
      <td><input type="number" value="${line.qty}" min="1" style="width:55px"
           onchange="setQty(${i}, this.value)"></td>
      <td>${lineTotal.toLocaleString()}</td>
      <td><button class="btn btn-sm btn-danger" onclick="removeLine(${i})"><i class="fa-solid fa-xmark"></i></button></td>
    </tr>`;
  });
  const discount = +document.getElementById('discount').value || 0;
  const fee = +(document.getElementById('delivery-fee')?.value || 0);
  document.getElementById('grand-total').textContent = Math.max(0, subtotal - discount + fee).toLocaleString();
}

function setQty(i, v) { cart[i].qty = Math.max(1, +v); renderCart(); }
function removeLine(i) { cart.splice(i, 1); renderCart(); }
function clearCart() { cart = []; renderCart(); }

async function checkout() {
  const msg = document.getElementById('pos-msg');
  if (!cart.length) { msg.innerHTML = '<div class="alert alert-danger">السلة فارغة</div>'; return; }

  const payload = {
    cart,
    order_type: document.getElementById('order-type').value,
    table_id: document.getElementById('table-id').value || null,
    app_type_id: document.getElementById('app-type-id')?.value || null,
    discount: +document.getElementById('discount').value || 0,
    delivery_address: document.getElementById('delivery-address')?.value || null,
    delivery_fee: +(document.getElementById('delivery-fee')?.value || 0),
    paid_amount: +document.getElementById('paid-amount').value || 0,
    payment_method: document.getElementById('payment-method').value,
  };

  try {
    const res = await fetch('/pos/checkout', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (data.success) {
      // نظام الطباعة المزدوج: المطبخ المركزي + مراكز التحضير (طباعة تلقائية بدون ظهور الصفحة)
      function printReceiptUrl(url) {
        const iframe = document.createElement('iframe');
        iframe.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;visibility:hidden;';
        iframe.src = url;
        iframe.onload = () => setTimeout(() => iframe.remove(), 30000);
        document.body.appendChild(iframe);
      }
      if (data.central_kitchen_receipt_url) printReceiptUrl(data.central_kitchen_receipt_url);
      if (data.preparation_kitchen_receipts && data.preparation_kitchen_receipts.length) {
        data.preparation_kitchen_receipts.forEach(r => printReceiptUrl(r.url));
      }
      msg.innerHTML = `<div class="alert alert-success">تم إنشاء الطلب: ${data.order_no}
        <div style="margin-top:8px">
          <a class="btn btn-sm btn-secondary" href="${data.receipt_url}" target="_blank" onclick="this.innerHTML='\u0641تح الفاتورة...'">فتح الفاتورة</a>
        </div>
      </div>`;
      clearCart();
      flushOfflineQueue();
    } else {
      msg.innerHTML = `<div class="alert alert-danger">${data.error || 'حدث خطأ'}</div>`;
    }
  } catch (e) {
    // وضع أوف لاين: حفظ الطلب محلياً لإرساله لاحقاً
    const queue = JSON.parse(localStorage.getItem('pos_offline_queue') || '[]');
    queue.push({ payload, ts: Date.now() });
    localStorage.setItem('pos_offline_queue', JSON.stringify(queue));
    msg.innerHTML = '<div class="alert alert-info">لا يوجد اتصال — تم حفظ الطلب محلياً وسيُرسل تلقائياً</div>';
    clearCart();
  }
}

// إرسال الطلبات المحفوظة أوف لاين عند عودة الاتصال
async function flushOfflineQueue() {
  const queue = JSON.parse(localStorage.getItem('pos_offline_queue') || '[]');
  if (!queue.length) return;
  const remaining = [];
  for (const entry of queue) {
    try {
      const res = await fetch('/pos/checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(entry.payload),
      });
      if (!res.ok) remaining.push(entry);
    } catch (e) { remaining.push(entry); }
  }
  localStorage.setItem('pos_offline_queue', JSON.stringify(remaining));
}

window.addEventListener('online', flushOfflineQueue);
flushOfflineQueue();
