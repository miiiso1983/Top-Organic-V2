<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>قالب تكلفة: <?= Helpers::e($item['name']) ?></h3>
  <div class="grid grid-3">
    <div><strong>الباركود:</strong> <?= Helpers::e($item['barcode'] ?? '-') ?></div>
    <div><strong>الوحدة الأساسية:</strong> <?= Helpers::e($item['unit_name'] ?? '-') ?></div>
    <div><strong>الكلفة الحالية:</strong> <?= Helpers::money($item['cost_price'] ?? 0) ?></div>
  </div>
</div>

<div class="card">
  <h3>بيانات القالب</h3>
  <style>
    .line-qty { width: 70px; }
    .line-waste { width: 95px; }
  </style>
  <form method="post" action="/inventory/cost-sheets/<?= (int)$item['id'] ?>">
    <div class="grid grid-4">
      <div class="form-row">
        <label>كمية الناتج</label>
        <input type="number" name="yield_qty" value="<?= (float)($sheet['yield_qty'] ?? 1) ?>" step="0.001" min="0.001">
      </div>
      <div class="form-row">
        <label>وحدة الناتج</label>
        <select name="yield_unit_id">
          <option value="">-- الوحدة الأساسية --</option>
          <?php foreach ($units as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= ($sheet['yield_unit_id'] ?? $item['unit_id'] ?? null) == $u['id'] ? 'selected' : '' ?>><?= Helpers::e($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label>هدر القسم/التحضير</label>
        <input type="number" name="waste_rate" value="<?= (float)($sheet['waste_rate'] ?? 0) ?>" step="0.01" min="0" max="0.95" placeholder="0.05 = 5%">
      </div>
      <div class="form-row">
        <label>سعر البيع للوحدة</label>
        <input type="number" name="sale_price" value="<?= (float)($sheet['sale_price'] ?? 0) ?>" step="0.01" min="0">
      </div>
      <div class="form-row">
        <label>ملاحظات</label>
        <input name="notes" value="<?= Helpers::e($sheet['notes'] ?? '') ?>">
      </div>
    </div>

    <h4 style="margin-top:20px;">المكونات والأوزان</h4>
    <p class="text-muted" style="font-size:12px;">
      الكمية الإجمالية = الكمية الصافية ÷ (١ − هدر المادة) ÷ (١ − هدر القسم)
    </p>
    <table id="lines-table">
      <tr>
        <th>المادة</th>
        <th>الكمية</th>
        <th>الوحدة</th>
        <th>هدر المادة %</th>
        <th>زيادة/نقصان الناتج</th>
        <th>الكمية الإجمالية</th>
        <th>تكلفة المادة</th>
        <th>التكلفة الإجمالية</th>
        <th></th>
      </tr>
      <?php foreach ($lines as $line): ?>
      <tr>
        <td>
          <select name="ingredient_item_id[]" class="line-item">
            <option value="">-- اختر --</option>
            <?php foreach ($stockItems as $si): ?>
            <option value="<?= (int)$si['id'] ?>" <?= $si['id'] == $line['ingredient_item_id'] ? 'selected' : '' ?>><?= Helpers::e($si['name']) ?> (<?= Helpers::e($si['unit']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </td>
        <td><input type="number" name="qty[]" value="<?= (float)$line['qty'] ?>" step="0.001" min="0" class="line-qty"></td>
        <td>
          <select name="unit_id[]" class="line-unit">
            <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= $u['id'] == $line['unit_id'] ? 'selected' : '' ?>><?= Helpers::e($u['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td>
          <input type="number" name="waste_rate_line[]" value="<?= number_format((float)$line['waste_rate'], 2) ?>" step="0.01" min="-0.95" max="0.95" placeholder="+ هدر / − زيادة" class="line-waste">
          <input type="hidden" name="yield_gain_rate[]" value="0">
        </td>
        <td class="cell-output-adjustment">-</td>
        <td class="cell-gross-qty"><?= number_format((float)($line['gross_qty'] ?? 0), 2) ?> <?= Helpers::e($line['line_unit_name'] ?? '') ?></td>
        <td class="cell-unit-cost">-</td>
        <td class="cell-total-cost">-</td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i class="fa-solid fa-xmark"></i></button></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <button type="button" class="btn btn-secondary" style="margin-top:10px" onclick="addLine()">+ إضافة مكون</button>
    <div style="margin-top:16px;">
      <button class="btn btn-success"><i class="fa-solid fa-lock"></i> حفظ وحساب التكلفة</button>
      <a href="/inventory/cost-sheets/<?= (int)$item['id'] ?>/export-excel" class="btn btn-secondary"><i class="fa-solid fa-chart-column"></i> Excel</a>
      <a href="/inventory/cost-sheets/<?= (int)$item['id'] ?>/export-pdf" class="btn btn-secondary"><i class="fa-solid fa-file"></i> PDF</a>
      <a href="/inventory/cost-sheets/<?= (int)$item['id'] ?>" class="btn btn-secondary">عرض</a>
      <a href="/inventory/cost-sheets" class="btn btn-sm">عودة</a>
    </div>
  </form>
</div>

<template id="line-template">
  <tr>
    <td>
      <select name="ingredient_item_id[]" class="line-item">
        <option value="">-- اختر --</option>
        <?php foreach ($stockItems as $si): ?>
        <option value="<?= (int)$si['id'] ?>"><?= Helpers::e($si['name']) ?> (<?= Helpers::e($si['unit']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input type="number" name="qty[]" value="0" step="0.001" min="0" class="line-qty"></td>
    <td>
      <select name="unit_id[]" class="line-unit">
        <?php foreach ($units as $u): ?>
        <option value="<?= (int)$u['id'] ?>"><?= Helpers::e($u['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td>
      <input type="number" name="waste_rate_line[]" value="0.00" step="0.01" min="-0.95" max="0.95" placeholder="+ هدر / − زيادة" class="line-waste">
      <input type="hidden" name="yield_gain_rate[]" value="0">
    </td>
    <td class="cell-output-adjustment">-</td>
    <td class="cell-gross-qty">-</td>
    <td class="cell-unit-cost">-</td>
    <td class="cell-total-cost">-</td>
    <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i class="fa-solid fa-xmark"></i></button></td>
  </tr>
</template>

<script>
const costMap = <?= json_encode($costMap, JSON_UNESCAPED_UNICODE) ?>;
const unitMap = <?= json_encode($unitMap, JSON_UNESCAPED_UNICODE) ?>;

function formatMoney(num) {
  return Number(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function toBaseUnit(unitId, qty) {
  let guard = 0;
  while (unitId && unitMap[unitId] && unitMap[unitId].base_unit_id && guard < 10) {
    qty *= unitMap[unitId].factor;
    unitId = unitMap[unitId].base_unit_id;
    guard++;
  }
  return qty;
}

function fromBaseUnit(unitId, qty) {
  let guard = 0;
  while (unitId && unitMap[unitId] && unitMap[unitId].base_unit_id && guard < 10) {
    qty /= unitMap[unitId].factor;
    unitId = unitMap[unitId].base_unit_id;
    guard++;
  }
  return qty;
}

function recalcRow(row) {
  const itemSelect = row.querySelector('.line-item');
  const unitSelect = row.querySelector('.line-unit');
  const qtyInput = row.querySelector('.line-qty');
  const wasteInput = row.querySelector('.line-waste');
  const headerWasteInput = document.querySelector('input[name="waste_rate"]');

  const itemId = itemSelect ? itemSelect.value : 0;
  const unitId = unitSelect ? unitSelect.value : 0;
  const qty = parseFloat(qtyInput ? qtyInput.value : 0) || 0;
  const waste = parseFloat(wasteInput ? wasteInput.value : 0) || 0;
  const headerWaste = parseFloat(headerWasteInput ? headerWasteInput.value : 0) || 0;

  const baseUnitCost = (costMap[itemId] !== undefined) ? costMap[itemId] : 0;
  // تكلفة الوحدة المختارة = كلفة الوحدة الأساسية × معامل التحويل إلى الوحدة المختارة
  const unitCost = toBaseUnit(unitId, baseUnitCost);
  const outputAdjustment = qty * waste;
  let grossQty = 0;
  if ((1 - waste) !== 0 && (1 - headerWaste) !== 0) {
    grossQty = qty / (1 - waste) / (1 - headerWaste);
  }
  const baseGrossQty = toBaseUnit(unitId, grossQty);
  const totalCost = baseGrossQty * baseUnitCost;

  const unitName = unitSelect && unitSelect.selectedIndex >= 0 ? unitSelect.options[unitSelect.selectedIndex].text : '';
  const outputAdjustmentCell = row.querySelector('.cell-output-adjustment');
  const grossQtyCell = row.querySelector('.cell-gross-qty');
  const unitCostCell = row.querySelector('.cell-unit-cost');
  const totalCostCell = row.querySelector('.cell-total-cost');

  if (outputAdjustmentCell) outputAdjustmentCell.textContent = outputAdjustment.toFixed(2) + ' ' + unitName;
  if (grossQtyCell) grossQtyCell.textContent = grossQty.toFixed(2) + ' ' + unitName;
  if (unitCostCell) unitCostCell.textContent = formatMoney(unitCost) + ' / ' + unitName;
  if (totalCostCell) totalCostCell.textContent = formatMoney(totalCost);
}

function recalcAll() {
  document.querySelectorAll('#lines-table tbody tr').forEach(recalcRow);
}

document.getElementById('lines-table').addEventListener('input', function (e) {
  const row = e.target.closest('tr');
  if (row) recalcRow(row);
});

document.getElementById('lines-table').addEventListener('change', function (e) {
  const row = e.target.closest('tr');
  if (row) recalcRow(row);
});

const headerWasteInput = document.querySelector('input[name="waste_rate"]');
if (headerWasteInput) {
  headerWasteInput.addEventListener('input', recalcAll);
}

function addLine() {
  const tpl = document.getElementById('line-template');
  document.querySelector('#lines-table').appendChild(tpl.content.cloneNode(true));
  const rows = document.querySelectorAll('#lines-table tbody tr');
  recalcRow(rows[rows.length - 1]);
}

<?php if (!$lines): ?>addLine();<?php endif; ?>
recalcAll();
</script>
