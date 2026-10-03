<?php use App\Core\Helpers; ?>
<style>
.mr-form { max-width: 900px; }
.mr-item-row { display:grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1.5fr auto; gap:8px; align-items:end; margin-bottom:8px; }
.existing-dropdown .dropdown-item { padding:8px 10px; cursor:pointer; border-bottom:1px solid #f3f4f6; }
.existing-dropdown .dropdown-item:hover { background:#f3f4f6; }
.existing-dropdown .dropdown-item small { color:#6b7280; }
@media (max-width: 768px) {
  .mr-item-row { grid-template-columns: 1fr 1fr; }
}
</style>

<div class="mr-form">
  <h2><?= $title ?></h2>
  <form method="POST" action="<?= $request ? '/material-requests/' . $request['id'] . '/edit' : '/material-requests' ?>">
    <div class="form-row">
      <div class="form-group">
        <label>الفرع</label>
        <select name="branch_id" class="form-input">
          <option value="">اختر الفرع</option>
          <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= ($request['branch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>القسم</label>
        <select name="department_id" class="form-input">
          <option value="">اختر القسم</option>
          <?php foreach ($departments as $d): ?>
          <option value="<?= $d['id'] ?>" <?= ($request['department_id'] ?? '') == $d['id'] ? 'selected' : '' ?>><?= Helpers::e($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>تاريخ الطلب</label>
        <input type="date" name="request_date" class="form-input" value="<?= $request['request_date'] ?? date('Y-m-d') ?>" required>
      </div>
      <div class="form-group">
        <label>الأولوية</label>
        <select name="priority" class="form-input">
          <option value="normal" <?= ($request['priority'] ?? 'normal') === 'normal' ? 'selected' : '' ?>>عادي</option>
          <option value="low" <?= ($request['priority'] ?? '') === 'low' ? 'selected' : '' ?>>منخفض</option>
          <option value="high" <?= ($request['priority'] ?? '') === 'high' ? 'selected' : '' ?>>مرتفع</option>
          <option value="urgent" <?= ($request['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>عاجل</option>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>مركز الكلفة</label>
        <select name="cost_center_id" class="form-input">
          <option value="">بدون</option>
          <?php foreach ($costCenters as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($request['cost_center_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= Helpers::e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>أمر التصنيع</label>
        <select name="production_order_id" class="form-input">
          <option value="">بدون</option>
          <?php foreach ($productionOrders as $p): ?>
          <option value="<?= $p['id'] ?>" <?= ($request['production_order_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= Helpers::e($p['production_no']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>قسم المطبخ</label>
        <select name="kitchen_section_id" class="form-input">
          <option value="">بدون</option>
          <?php foreach ($kitchenSections as $ks): ?>
          <option value="<?= $ks['id'] ?>" <?= ($request['kitchen_section_id'] ?? '') == $ks['id'] ? 'selected' : '' ?>><?= Helpers::e($ks['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>المخزن المستهدف</label>
        <select name="warehouse_id" class="form-input">
          <option value="">اختر المخزن</option>
          <?php foreach ($warehouses as $w): ?>
          <option value="<?= $w['id'] ?>" <?= ($request['warehouse_id'] ?? '') == $w['id'] ? 'selected' : '' ?>><?= Helpers::e($w['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label>سبب الطلب</label>
      <input type="text" name="reason" class="form-input" value="<?= Helpers::e($request['reason'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>ملاحظات</label>
      <textarea name="notes" class="form-input" rows="2"><?= Helpers::e($request['notes'] ?? '') ?></textarea>
    </div>

    <h4>البنود</h4>
    <div id="items-container">
      <?php
      $rows = $items ?: [[]];
      $idx = 0;
      foreach ($rows as $it):
        $isNew = (int)($it['is_new_item'] ?? 0) === 1;
      ?>
      <div class="mr-item-row item-row" data-idx="<?= $idx ?>">
        <div>
          <label>نوع المادة</label>
          <select name="items[<?= $idx ?>][is_new_item]" class="form-input item-type-toggle" onchange="toggleItemType(this)">
            <option value="0" <?= !$isNew ? 'selected' : '' ?>>موجودة</option>
            <option value="1" <?= $isNew ? 'selected' : '' ?>>جديدة</option>
          </select>
        </div>
        <div class="existing-item" style="grid-column: span 2; <?= $isNew ? 'display:none' : '' ?>; position:relative;">
          <label>المادة</label>
          <input type="hidden" name="items[<?= $idx ?>][item_id]" class="existing-select-hidden" value="<?= !$isNew ? ($it['item_id'] ?? '') : '' ?>" <?= !$isNew ? 'required' : '' ?>>
          <input type="text" class="form-input existing-search" value="<?= (!$isNew && ($it['item_id'] ?? '')) ? Helpers::e(($it['item_name'] ?? '') . ' (' . ($it['barcode'] ?? '') . ')') : '' ?>" placeholder="ابحث بالاسم أو الباركود..." oninput="filterItemSearch(this)" onclick="showItemDropdown(this)" autocomplete="off" <?= !$isNew ? 'required' : '' ?>>
          <div class="existing-dropdown" style="display:none; position:absolute; top:100%; right:0; left:0; max-height:200px; overflow-y:auto; background:#fff; border:1px solid #e5e7eb; border-radius:6px; box-shadow:0 4px 6px rgba(0,0,0,0.1); z-index:100;">
            <?php foreach ($itemsList as $i): ?>
            <div class="dropdown-item" data-value="<?= $i['id'] ?>" data-unit="<?= $i['unit_id'] ?>" data-text="<?= Helpers::e($i['name']) ?> (<?= Helpers::e($i['barcode'] ?? '') ?>)" onclick="selectItem(this)"><?= Helpers::e($i['name']) ?> <small class="text-muted">(<?= Helpers::e($i['barcode'] ?? '') ?>)</small></div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="new-item" style="grid-column: span 2; <?= !$isNew ? 'display:none' : '' ?>">
          <label>اسم المادة الجديدة</label>
          <input type="text" name="items[<?= $idx ?>][new_item_name]" class="form-input new-name" value="<?= Helpers::e($it['new_item_name'] ?? '') ?>" placeholder="اسم المادة الجديدة" <?= $isNew ? 'required' : '' ?>>
        </div>
        <div class="new-item" style="<?= !$isNew ? 'display:none' : '' ?>">
          <label>التصنيف</label>
          <select name="items[<?= $idx ?>][new_item_category_id]" class="form-input new-cat" <?= $isNew ? 'required' : '' ?>>
            <option value="">اختر التصنيف</option>
            <?php foreach ($itemCategories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($isNew && ($it['new_item_category_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= Helpers::e($c['name']) ?> (<?= Helpers::e($c['type']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>الكمية</label>
          <input type="number" step="0.01" name="items[<?= $idx ?>][requested_qty]" class="form-input" value="<?= $it['requested_qty'] ?? '' ?>" required>
        </div>
        <div>
          <label>الوحدة</label>
          <select name="items[<?= $idx ?>][unit_id]" class="form-input unit-select">
            <?php foreach ($units as $u): ?>
            <option value="<?= $u['id'] ?>" <?= (($it['unit_id'] ?? $it['new_item_unit_id'] ?? '') == $u['id']) ? 'selected' : '' ?>><?= Helpers::e($u['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="items[<?= $idx ?>][new_item_unit_id]" class="new-unit-id" value="<?= $it['new_item_unit_id'] ?? ($it['unit_id'] ?? '') ?>">
        </div>
        <div>
          <label>سعر الوحدة</label>
          <input type="number" step="0.01" name="items[<?= $idx ?>][unit_price]" class="form-input" value="<?= $it['unit_price'] ?? '' ?>">
        </div>
        <div>
          <label>المخزن/القسم</label>
          <select name="items[<?= $idx ?>][warehouse_id]" class="form-input">
            <option value="">تعيين لاحقاً</option>
            <?php foreach ($warehouses as $w): ?>
            <option value="<?= $w['id'] ?>" <?= ($it['warehouse_id'] ?? '') == $w['id'] ? 'selected' : '' ?>><?= Helpers::e($w['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>الغرض</label>
          <input type="text" name="items[<?= $idx ?>][purpose]" class="form-input" value="<?= Helpers::e($it['purpose'] ?? '') ?>">
        </div>
        <div>
          <label>ملاحظات مادة جديدة</label>
          <input type="text" name="items[<?= $idx ?>][new_item_notes]" class="form-input" value="<?= Helpers::e($it['new_item_notes'] ?? '') ?>">
        </div>
        <div><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fa-solid fa-xmark"></i></button></div>
      </div>
      <?php $idx++; endforeach; ?>
    </div>
    <button type="button" class="btn btn-secondary btn-sm" onclick="addRow()">+ إضافة بند</button>

    <div class="form-group" style="margin-top:20px">
      <button type="submit" class="btn btn-primary">حفظ</button>
      <a href="/material-requests" class="btn btn-light">إلغاء</a>
    </div>
  </form>
</div>

<script>
let idx = <?= $idx ?>;
const itemsList = <?= json_encode($itemsList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
function addRow() {
  const tpl = document.querySelector('.item-row').cloneNode(true);
  tpl.dataset.idx = idx;
  tpl.querySelectorAll('input, select').forEach(el => {
    el.name = el.name.replace(/items\[\d+\]/, 'items[' + idx + ']');
    if (el.tagName === 'INPUT') el.value = '';
    if (el.tagName === 'SELECT') el.selectedIndex = 0;
  });
  tpl.querySelectorAll('.existing-item').forEach(el => el.style.display = '');
  tpl.querySelectorAll('.new-item').forEach(el => el.style.display = 'none');
  const hidden = tpl.querySelector('.existing-select-hidden');
  const search = tpl.querySelector('.existing-search');
  const dropdown = tpl.querySelector('.existing-dropdown');
  if (hidden) { hidden.required = true; hidden.value = ''; }
  if (search) { search.required = true; search.value = ''; }
  if (dropdown) {
    dropdown.innerHTML = itemsList.map(i => `<div class="dropdown-item" data-value="${i.id}" data-unit="${i.unit_id}" data-text="${i.name} (${i.barcode || ''})" onclick="selectItem(this)">${i.name} <small class="text-muted">(${i.barcode || ''})</small></div>`).join('');
  }
  tpl.querySelector('.new-name').required = false;
  tpl.querySelector('.new-cat').required = false;
  document.getElementById('items-container').appendChild(tpl);
  idx++;
}
function removeRow(btn) {
  if (document.querySelectorAll('.item-row').length > 1) btn.closest('.item-row').remove();
}
function toggleItemType(sel) {
  const row = sel.closest('.item-row');
  const isNew = sel.value === '1';
  row.querySelectorAll('.existing-item').forEach(el => {
    el.style.display = isNew ? 'none' : '';
    const hidden = el.querySelector('.existing-select-hidden');
    const search = el.querySelector('.existing-search');
    if (hidden) hidden.required = !isNew;
    if (search) search.required = !isNew;
  });
  row.querySelectorAll('.new-item').forEach(el => {
    el.style.display = isNew ? '' : 'none';
  });
  row.querySelector('.new-name').required = isNew;
  row.querySelector('.new-cat').required = isNew;
}
function filterItemSearch(input) {
  const row = input.closest('.item-row');
  const term = input.value.toLowerCase();
  row.querySelectorAll('.existing-dropdown .dropdown-item').forEach(item => {
    const text = (item.dataset.text || '').toLowerCase();
    item.style.display = text.includes(term) ? '' : 'none';
  });
  showItemDropdown(input);
}
function showItemDropdown(input) {
  hideItemDropdowns();
  const row = input.closest('.item-row');
  const dropdown = row.querySelector('.existing-dropdown');
  if (dropdown) {
    dropdown.style.display = 'block';
    dropdown.querySelectorAll('.dropdown-item').forEach(item => item.style.display = '');
  }
}
function selectItem(item) {
  const row = item.closest('.item-row');
  const value = item.dataset.value;
  const text = item.dataset.text;
  const unit = item.dataset.unit;
  row.querySelector('.existing-select-hidden').value = value;
  row.querySelector('.existing-search').value = text;
  row.querySelector('.existing-dropdown').style.display = 'none';
  const unitSelect = row.querySelector('.unit-select');
  if (unitSelect && unit) {
    unitSelect.value = unit;
  }
}
function hideItemDropdowns() {
  document.querySelectorAll('.existing-dropdown').forEach(d => d.style.display = 'none');
}
document.addEventListener('click', function(e) {
  if (!e.target.closest('.existing-item')) hideItemDropdowns();
});
</script>
