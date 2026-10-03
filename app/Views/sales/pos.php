<?php use App\Core\Helpers; ?>
<div class="pos-layout">
  <div>
    <?php if ($floors): ?>
    <div class="form-row" style="margin-bottom:8px">
      <label>الطابق/الصالة</label>
      <select onchange="location.href='/pos'+(this.value?('?floor_id='+this.value):'')">
        <option value="">كل الطوابق (جميع المنيوهات)</option>
        <?php foreach ($floors as $f): ?>
          <option value="<?= $f['id'] ?>" <?= $floorId == $f['id'] ? 'selected' : '' ?>><?= Helpers::e($f['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="pos-cats">
      <button class="active" data-cat="all">الكل</button>
      <?php foreach ($categories as $cat): ?>
        <button data-cat="<?= $cat['id'] ?>"><?= Helpers::e($cat['name']) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="pos-items">
      <?php foreach ($items as $item): ?>
        <div class="pos-item" data-cat="<?= $item['category_id'] ?>"
             data-id="<?= $item['id'] ?>" data-name="<?= Helpers::e($item['name']) ?>"
             data-price="<?= $item['price'] ?>">
          <?php if ($item['image']): ?>
            <img src="<?= Helpers::e($item['image']) ?>" alt="" style="width:100%;height:60px;object-fit:cover;border-radius:6px">
          <?php else: ?>
            <div style="font-size:30px"><i class="fa-solid fa-bowl-rice"></i></div>
          <?php endif; ?>
          <div><?= Helpers::e($item['name']) ?></div>
          <div class="price"><?= number_format((float)$item['price']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="pos-cart">
    <h3><i class="fa-solid fa-cart-shopping"></i> الطلب الحالي</h3>

    <?php if ($openOrders): ?>
    <div class="card" style="margin-bottom:12px; padding:10px; background:#fffbeb">
      <h4 style="font-size:14px; margin-bottom:8px"><i class="fa-solid fa-list"></i> الطلبات المفتوحة</h4>
      <div style="max-height:160px; overflow:auto; font-size:13px">
        <?php foreach ($openOrders as $oo): ?>
          <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed #e5e7eb">
            <div>
              <a href="/orders/<?= $oo['id'] ?>" style="font-weight:600"><?= Helpers::e($oo['order_no']) ?></a>
              <div style="font-size:11px; color:#6b7280"><?= Helpers::e($oo['table_name'] ?? '-') ?> · <?= $oo['status'] ?> · <?= $oo['payment_status'] ?></div>
            </div>
            <a class="btn btn-sm btn-success" href="/orders/<?= $oo['id'] ?>">تحصيل</a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="form-row">
      <label>نوع الطلب</label>
      <select id="order-type">
        <option value="dine_in">صالة</option>
        <option value="takeaway">سفري</option>
        <option value="delivery">توصيل</option>
        <option value="online">إلكتروني</option>
      </select>
    </div>
    <div class="form-row" id="table-row">
      <label>الطاولة</label>
      <select id="table-id">
        <option value="">بدون طاولة</option>
        <?php foreach ($tables as $t): ?>
          <option value="<?= $t['id'] ?>" <?= $t['status'] !== 'available' ? 'disabled' : '' ?>>
            طاولة <?= Helpers::cleanName($t['name']) ?> (<?= $t['status'] === 'available' ? 'متاحة' : 'مشغولة' ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row" id="app-type-row" style="display:none">
      <label>نوع التطبيق</label>
      <select id="app-type-id">
        <option value="">اختر التطبيق</option>
        <?php foreach ($appTypes as $at): ?>
          <option value="<?= $at['id'] ?>"><?= Helpers::e($at['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row" id="delivery-row" style="display:none">
      <label>عنوان التوصيل</label>
      <input type="text" id="delivery-address">
      <label style="margin-top:8px">أجرة التوصيل</label>
      <input type="number" id="delivery-fee" value="0" min="0">
    </div>

    <table id="cart-table">
      <thead><tr><th>الصنف</th><th>ع</th><th>المجموع</th><th></th></tr></thead>
      <tbody></tbody>
    </table>

    <div class="form-row" style="margin-top:10px">
      <label>الخصم</label>
      <input type="number" id="discount" value="0" min="0">
    </div>
    <div style="font-size:20px;font-weight:bold;margin:10px 0">
      الإجمالي: <span id="grand-total">0</span>
    </div>
    <div class="form-row">
      <label>المبلغ المستلم</label>
      <input type="number" id="paid-amount" value="0" min="0">
    </div>
    <div class="form-row">
      <label>طريقة الدفع</label>
      <select id="payment-method">
        <option value="cash">نقدي</option>
        <option value="card">بطاقة</option>
        <option value="online">إلكتروني</option>
        <option value="credit">آجل</option>
      </select>
    </div>
    <button class="btn btn-success" style="width:100%" onclick="checkout()"><i class="fa-solid fa-check"></i> إتمام الطلب</button>
    <button class="btn btn-secondary" style="width:100%;margin-top:8px" onclick="clearCart()"><i class="fa-solid fa-trash"></i> إفراغ السلة</button>
    <div id="pos-msg" style="margin-top:10px"></div>
  </div>
</div>
<script src="/assets/js/pos.js?v=3"></script>
<script>
(function(){
  const orderType = document.getElementById('order-type');
  if (!orderType) return;
  function togglePosFields() {
    const type = orderType.value;
    const tableRow = document.getElementById('table-row');
    const deliveryRow = document.getElementById('delivery-row');
    const appRow = document.getElementById('app-type-row');
    if (tableRow) tableRow.style.display = type === 'dine_in' ? '' : 'none';
    if (deliveryRow) deliveryRow.style.display = type === 'delivery' ? '' : 'none';
    if (appRow) appRow.style.display = type === 'online' ? '' : 'none';
  }
  orderType.addEventListener('change', togglePosFields);
  togglePosFields();
})();
</script>
