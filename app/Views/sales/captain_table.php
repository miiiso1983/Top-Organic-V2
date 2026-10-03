<?php use App\Core\Helpers; ?>
<style>
.captain-table { margin: -24px; height: calc(100vh - 54px); display: grid; grid-template-columns: 200px 1fr 360px; gap: 16px; overflow: hidden; padding: 16px; background: #f2f4f7; }
@media (max-width: 1100px) { .captain-table { grid-template-columns: 160px 1fr 320px; } }
@media (max-width: 900px) { .captain-table { grid-template-columns: 1fr; grid-template-rows: auto 1fr auto; height: auto; min-height: calc(100vh - 54px); overflow: visible; } }

.captain-col { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.06); display: flex; flex-direction: column; overflow: hidden; }
.captain-col-head { padding: 14px 16px; border-bottom: 1px solid #e5e7eb; font-weight: 700; font-size: 16px; display: flex; justify-content: space-between; align-items: center; }
.captain-col-body { flex: 1; overflow-y: auto; padding: 12px; }

.captain-cats { display: flex; flex-direction: column; gap: 8px; }
.captain-cats button { width: 100%; text-align: right; padding: 12px 14px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; cursor: pointer; font-family: inherit; font-size: 14px; }
.captain-cats button:hover { background: #f0fdf4; border-color: var(--brand); }
.captain-cats button.active { background: var(--brand); color: #fff; border-color: var(--brand); }

.captain-items { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 14px; }
@media (max-width: 900px) { .captain-items { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); } }
.captain-items .item { border: 1px solid #e5e7eb; border-radius: 12px; padding: 10px; text-align: center; cursor: pointer; background: #fff; transition: box-shadow .1s; display: flex; flex-direction: column; }
.captain-items .item:hover { box-shadow: 0 0 0 2px var(--brand); }
.captain-items .item .thumb { width: 100%; height: 90px; object-fit: cover; border-radius: 8px; margin-bottom: 8px; background: #f3f4f6; }
.captain-items .item .noimg { width: 100%; height: 90px; display: flex; align-items: center; justify-content: center; background: #f3f4f6; border-radius: 8px; margin-bottom: 8px; font-size: 32px; }
.captain-items .item .name { font-size: 13px; min-height: 38px; display: flex; align-items: center; justify-content: center; }

.captain-cart { padding: 12px; }
.captain-cart h4 { margin-bottom: 10px; font-size: 15px; }
.captain-cart table { width: 100%; font-size: 13px; }
.captain-cart td, .captain-cart th { padding: 6px 4px; border-bottom: 1px solid #e5e7eb; }
.captain-cart .qty-btn { width: 28px; height: 28px; border: none; border-radius: 6px; background: #e5e7eb; cursor: pointer; }
.captain-current { background: #f0f9ff; border-radius: 8px; padding: 10px; margin-bottom: 12px; font-size: 13px; }
.captain-current h5 { margin-bottom: 8px; font-size: 14px; color: #0369a1; }
.captain-current .line { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px dashed #bae6fd; }
.captain-total { font-size: 18px; font-weight: 700; margin: 12px 0; padding-top: 10px; border-top: 2px solid #e5e7eb; }
.captain-actions { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
.captain-actions .btn { width: 100%; }
#captain-msg { margin-top: 10px; }
</style>

<div class="captain-table">
  <!-- القسم الأيمن: الفئات -->
  <div class="captain-col">
    <div class="captain-col-head">الفئات</div>
    <div class="captain-col-body">
      <div class="captain-cats" id="cat-buttons">
        <button class="active" data-cat="all">الكل</button>
        <?php foreach ($categories as $cat): ?>
          <button data-cat="<?= $cat['id'] ?>"><?= Helpers::e($cat['name']) ?></button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- القسم الأوسط: الأصناف -->
  <div class="captain-col">
    <div class="captain-col-head">الأصناف</div>
    <div class="captain-col-body">
      <div class="captain-items" id="items-grid">
        <?php foreach ($items as $item): ?>
          <div class="item" data-cat="<?= $item['category_id'] ?>" data-id="<?= $item['id'] ?>" data-name="<?= Helpers::e($item['name']) ?>" data-price="<?= $item['price'] ?>">
            <?php if (!empty($item['image'])): ?>
              <img src="<?= Helpers::e($item['image']) ?>" alt="" class="thumb">
            <?php else: ?>
              <div class="noimg"><i class="fa-solid fa-utensils"></i></div>
            <?php endif; ?>
            <div class="name"><?= Helpers::e($item['name']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- القسم الأيسر: الطلب والسلة -->
  <div class="captain-col">
    <div class="captain-col-head">
      <span>طلب طاولة <?= Helpers::cleanName($table['name']) ?></span>
      <a href="/captain" class="btn btn-sm btn-secondary">رجوع</a>
    </div>
    <div class="captain-col-body">
      <div class="captain-cart">
        <?php if ($currentOrder): ?>
          <div class="captain-current">
            <h5>طلب مفتوح: <?= Helpers::e($currentOrder['order_no']) ?></h5>
            <?php foreach ($currentItems as $it): ?>
              <div class="line">
                <span><?= Helpers::e($it['name']) ?> × <?= (float)$it['qty'] ?></span>
                <span><?= Helpers::money($it['total']) ?></span>
              </div>
            <?php endforeach; ?>
            <div class="line" style="font-weight:700; border:none; margin-top:6px;">
              <span>المجموع الحالي</span>
              <span><?= Helpers::money($currentOrder['total']) ?></span>
            </div>
            <?php if (!in_array($currentOrder['status'], ['completed', 'cancelled'], true) && $currentOrder['payment_status'] !== 'paid'): ?>
            <div class="captain-order-actions" style="display:flex; gap:8px; margin-top:10px;">
              <button class="btn btn-primary" onclick="printBill()"><i class="fa-solid fa-print"></i> طباعة الفاتورة</button>
              <button class="btn btn-success" onclick="settlePayment()"><i class="fa-solid fa-cash-register"></i> إتمام الحساب</button>
            </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <h4><i class="fa-solid fa-cart-shopping"></i> السلة</h4>
        <table>
          <thead><tr><th>الصنف</th><th>الكمية</th><th>المجموع</th><th></th></tr></thead>
          <tbody id="cart-body"></tbody>
        </table>
        <div class="captain-total">الإجمالي: <span id="cart-total">0</span></div>

        <div class="form-row">
          <label>ملاحظات</label>
          <textarea id="order-notes" rows="2"></textarea>
        </div>

        <div class="captain-actions">
          <button class="btn btn-success" onclick="submitCaptainOrder()"><?= $currentOrder ? 'إضافة للطلب' : 'إرسال الطلب' ?></button>
          <a href="/captain" class="btn btn-secondary">إلغاء</a>
        </div>
        <div id="captain-msg"></div>
      </div>
    </div>
  </div>
</div>

<script>
const tableId = <?= (int)$table['id'] ?>;
const currentOrderId = <?= (int)($currentOrder['id'] ?? 0) ?>;
let cart = [];

const catButtons = document.querySelectorAll('#cat-buttons button');
catButtons.forEach(btn => {
  btn.addEventListener('click', () => {
    catButtons.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const cat = btn.dataset.cat;
    document.querySelectorAll('#items-grid .item').forEach(el => {
      el.style.display = (cat === 'all' || el.dataset.cat === cat) ? '' : 'none';
    });
  });
});

document.querySelectorAll('#items-grid .item').forEach(el => {
  el.addEventListener('click', () => {
    const id = +el.dataset.id;
    const existing = cart.find(l => l.id === id);
    if (existing) { existing.qty++; }
    else { cart.push({ id, name: el.dataset.name, price: +el.dataset.price, qty: 1, notes: '' }); }
    renderCart();
  });
});

function renderCart() {
  const tbody = document.getElementById('cart-body');
  tbody.innerHTML = '';
  let total = 0;
  cart.forEach((line, i) => {
    const lineTotal = line.price * line.qty;
    total += lineTotal;
    tbody.innerHTML += `<tr>
      <td>
        <div>${line.name}</div>
        <input type="text" placeholder="ملاحظات: بدون بصل، إكسترا صوص..." value="${line.notes || ''}" onchange="setNote(${i}, this.value)" style="width:100%;margin-top:4px;padding:4px 6px;font-size:12px;border:1px solid #d1d5db;border-radius:6px;">
      </td>
      <td>
        <button class="qty-btn" onclick="changeQty(${i}, -1)">-</button>
        <span style="margin:0 6px">${line.qty}</span>
        <button class="qty-btn" onclick="changeQty(${i}, 1)">+</button>
      </td>
      <td>${lineTotal.toLocaleString()}</td>
      <td><button class="btn btn-sm btn-danger" onclick="removeCartLine(${i})"><i class="fa-solid fa-xmark"></i></button></td>
    </tr>`;
  });
  document.getElementById('cart-total').textContent = total.toLocaleString();
}

function changeQty(i, delta) {
  cart[i].qty = Math.max(1, cart[i].qty + delta);
  renderCart();
}
function setNote(i, note) { cart[i].notes = note.trim(); }
function removeCartLine(i) { cart.splice(i, 1); renderCart(); }

function printReceiptUrl(url) {
  const iframe = document.createElement('iframe');
  iframe.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;visibility:hidden;';
  iframe.src = url;
  iframe.onload = () => setTimeout(() => iframe.remove(), 30000);
  document.body.appendChild(iframe);
}


async function submitCaptainOrder() {
  const msg = document.getElementById('captain-msg');
  if (!cart.length) { msg.innerHTML = '<div class="alert alert-danger">السلة فارغة</div>'; return; }
  try {
    const payload = { table_id: tableId, cart, notes: document.getElementById('order-notes').value };
    if (currentOrderId) payload.order_id = currentOrderId;
    const res = await fetch('/captain/order', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      // نظام الطباعة المزدوج: المطبخ المركزي + مراكز التحضير (طباعة تلقائية بدون ظهور الصفحة)
      if (data.central_kitchen_receipt_url) printReceiptUrl(data.central_kitchen_receipt_url);
      if (data.preparation_kitchen_receipts && data.preparation_kitchen_receipts.length) {
        data.preparation_kitchen_receipts.forEach(r => printReceiptUrl(r.url));
      }
      if (data.is_addon) {
        msg.innerHTML = `<div class="alert alert-success">تمت إضافة الأصناف للطلب: ${data.order_no}</div>`;
        cart = []; renderCart();
        setTimeout(() => window.location.reload(), 800);
      } else {
        msg.innerHTML = `<div class="alert alert-success">تم إرسال الطلب: ${data.order_no}</div>`;
        setTimeout(() => window.location.href = '/captain', 800);
      }
    } else {
      msg.innerHTML = `<div class="alert alert-danger">${data.error || 'خطأ'}</div>`;
    }
  } catch (e) {
    msg.innerHTML = '<div class="alert alert-danger">فشل في الإرسال</div>';
  }
}

async function printBill() {
  const msg = document.getElementById('captain-msg');
  if (!currentOrderId) { msg.innerHTML = '<div class="alert alert-danger">لا يوجد طلب مفتوح</div>'; return; }
  try {
    const res = await fetch('/captain/orders/' + currentOrderId + '/print-bill', { method: 'POST' });
    const data = await res.json();
    if (!data.ok) { msg.innerHTML = '<div class="alert alert-danger">' + (data.error || 'خطأ') + '</div>'; return; }
    if (data.receipt_url) printReceiptUrl(data.receipt_url);
    msg.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
    setTimeout(() => window.location.reload(), 800);
  } catch (e) {
    msg.innerHTML = '<div class="alert alert-danger">فشل في طباعة الفاتورة</div>';
  }
}

async function settlePayment() {
  const msg = document.getElementById('captain-msg');
  if (!currentOrderId) { msg.innerHTML = '<div class="alert alert-danger">لا يوجد طلب مفتوح</div>'; return; }
  if (!confirm('تأكيد إتمام الحساب؟')) return;
  try {
    const res = await fetch('/captain/orders/' + currentOrderId + '/settle', { method: 'POST' });
    const data = await res.json();
    if (!data.ok) { msg.innerHTML = '<div class="alert alert-danger">' + (data.error || 'خطأ') + '</div>'; return; }
    msg.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
    setTimeout(() => window.location.href = data.redirect || '/captain', 800);
  } catch (e) {
    msg.innerHTML = '<div class="alert alert-danger">فشل في إتمام الحساب</div>';
  }
}

</script>
