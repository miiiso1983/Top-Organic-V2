<?php use App\Core\Helpers; ?>
<?php
$statusLabels = [
    'active' => 'مفتوحة',
    'returned' => 'مرجعة',
    'damaged' => 'تالفة',
    'lost' => 'فقدت',
];
$statusBadge = [
    'active' => 'warning',
    'returned' => 'success',
    'damaged' => 'danger',
    'lost' => 'danger',
];
$typeLabels = [
    'fixed' => 'ثابتة',
    'consumable' => 'استهلاكية',
];
$typeBadge = [
    'fixed' => 'secondary',
    'consumable' => 'info',
];
?>
<div class="card">
  <h3>تسليم عهدة للموظف</h3>
  <?php if (\App\Core\Auth::can('hr.custody.manage')): ?>
  <form method="post" action="/hr/custody" class="form-inline" id="custodyForm">
    <div class="form-row"><label>الموظف</label>
      <select name="employee_id" required>
        <option value="">اختر موظف</option>
        <?php foreach ($employees as $e): ?>
          <option value="<?= $e['id'] ?>"><?= Helpers::e($e['full_name']) ?> (<?= Helpers::e($e['emp_code']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>القطعة</label>
      <select name="item_id" id="custodyItem" required>
        <option value="">اختر قطعة</option>
        <?php foreach ($items as $i): ?>
          <option value="<?= $i['id'] ?>" data-cost="<?= $i['cost_price'] ?>" data-unit="<?= $i['unit_id'] ?>">
            <?= Helpers::e($i['name']) ?> (<?= Helpers::e($i['category_type']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>المخزن</label>
      <select name="warehouse_id" required>
        <?php foreach ($warehouses as $w): ?>
          <option value="<?= $w['id'] ?>"><?= Helpers::e($w['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>نوع العهدة</label>
      <select name="type" id="custodyType" required>
        <option value="fixed"><?= $typeLabels['fixed'] ?></option>
        <option value="consumable"><?= $typeLabels['consumable'] ?></option>
      </select>
    </div>
    <div class="form-row" id="expiryDateRow" style="display:none"><label>تاريخ الانتهاء</label><input type="date" name="expiry_date" id="custodyExpiryDate"></div>
    <div class="form-row"><label>الرقم التسلسلي</label><input name="serial_number" placeholder="Serial Number"></div>
    <div class="form-row"><label>الكمية</label><input type="number" name="qty" id="custodyQty" step="0.001" min="0.001" value="1" required></div>
    <div class="form-row"><label>قيمة الوحدة</label><input type="number" name="unit_cost" id="custodyCost" step="0.0001" min="0" required></div>
    <div class="form-row"><label>تاريخ الإرجاع</label><input type="date" name="due_date"></div>
    <div class="form-row"><label>ملاحظات</label><input name="notes"></div>
    <button class="btn btn-success">تسليم العهدة</button>
  </form>
  <?php else: ?>
  <p class="text-muted">ليس لديك صلاحية لتسليم عهدة.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h3>سجل العهد</h3>
  <form method="get" action="/hr/custody" class="form-inline" style="margin-bottom:15px">
    <div class="form-row"><label>الحالة</label>
      <select name="status">
        <option value="">الكل</option>
        <?php foreach ($statusLabels as $k => $v): ?>
          <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-sm btn-primary">عرض</button>
  </form>
  <table>
    <tr>
      <th>الموظف</th><th>القطعة</th><th>الرقم التسلسلي</th>
      <th>النوع</th><th>الكمية</th><th>القيمة</th><th>الإجمالي</th>
      <th>الحالة</th><th>تاريخ الإرجاع</th><th>تاريخ الانتهاء</th><th>المخزن</th><th>التاريخ</th><th></th>
    </tr>
    <?php foreach ($custody as $c):
      $rowClass = '';
      if ($c['status'] === 'active' && $c['type'] === 'consumable' && isset($c['days_to_expiry']) && $c['days_to_expiry'] !== null && $c['days_to_expiry'] <= 15) {
          $rowClass = ($c['days_to_expiry'] < 0) ? 'row-expired' : (($c['days_to_expiry'] <= 7) ? 'row-urgent' : 'row-warning');
      }
    ?>
    <tr class="<?= $rowClass ?>">
      <td><?= Helpers::e($c['employee_name']) ?></td>
      <td><?= Helpers::e($c['item_name']) ?></td>
      <td><?= Helpers::e($c['serial_number'] ?? '-') ?></td>
      <td><span class="badge badge-<?= $typeBadge[$c['type']] ?>"><?= $typeLabels[$c['type']] ?></span></td>
      <td><?= $c['qty'] ?></td>
      <td><?= Helpers::money($c['unit_cost']) ?></td>
      <td><?= Helpers::money($c['total_value']) ?></td>
      <td><span class="badge badge-<?= $statusBadge[$c['status']] ?>"><?= $statusLabels[$c['status']] ?></span></td>
      <td><?= $c['due_date'] ? date('Y-m-d', strtotime($c['due_date'])) : '-' ?></td>
      <td>
        <?php if ($c['expiry_date']): ?>
          <span class="expiry-badge <?= $rowClass ? 'expiry-danger' : '' ?>">
            <?= date('Y-m-d', strtotime($c['expiry_date'])) ?>
            <?php if ($c['days_to_expiry'] !== null): ?>
              (<?= $c['days_to_expiry'] < 0 ? 'منتهية ' . abs($c['days_to_expiry']) . ' يوم' : $c['days_to_expiry'] . ' يوم' ?>)
            <?php endif; ?>
          </span>
        <?php else: ?>
          -
        <?php endif; ?>
      </td>
      <td><?= Helpers::e($c['warehouse_name'] ?? '-') ?></td>
      <td><?= date('Y-m-d', strtotime($c['issued_at'])) ?></td>
      <td>
        <?php if ($c['status'] === 'active' && (\App\Core\Auth::can('hr.custody.manage') || \App\Core\Auth::can('hr.custody.clearance'))): ?>
        <form method="post" action="/hr/custody/<?= $c['id'] ?>/return" style="display:inline">
          <input type="hidden" name="status" value="returned">
          <input type="hidden" name="condition" value="good">
          <button class="btn btn-sm btn-success" onclick="return confirm('تأكيد إرجاع العهدة سليما؟')">إرجاع</button>
        </form>
        <form method="post" action="/hr/custody/<?= $c['id'] ?>/return" style="display:inline">
          <input type="hidden" name="status" value="damaged">
          <button class="btn btn-sm btn-warning" onclick="return confirm('تسجيل العهدة تالفة؟ سيتم إنشاء خصم بقيمتها.')">تالف</button>
        </form>
        <form method="post" action="/hr/custody/<?= $c['id'] ?>/return" style="display:inline">
          <input type="hidden" name="status" value="lost">
          <button class="btn btn-sm btn-danger" onclick="return confirm('تسجيل العهدة فقدت؟ سيتم إنشاء خصم بقيمتها.')">فقد</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$custody): ?><tr><td colspan="13">لا توجد سجلات عهد</td></tr><?php endif; ?>
  </table>
</div>

<style>
.row-warning { background-color: #fff8e1; }
.row-urgent { background-color: #ffebee; }
.row-expired { background-color: #f8bbd0; }
.expiry-badge { display:inline-block; padding:2px 6px; border-radius:4px; font-size:12px; }
.expiry-danger { background:#dc3545; color:#fff; }
</style>

<script>
(function() {
  const itemSelect = document.getElementById('custodyItem');
  const typeSelect = document.getElementById('custodyType');
  const expiryRow = document.getElementById('expiryDateRow');
  const expiryInput = document.getElementById('custodyExpiryDate');

  function toggleExpiry() {
    if (typeSelect.value === 'consumable') {
      expiryRow.style.display = '';
      expiryInput.required = true;
    } else {
      expiryRow.style.display = 'none';
      expiryInput.required = false;
      expiryInput.value = '';
    }
  }

  typeSelect?.addEventListener('change', toggleExpiry);
  toggleExpiry();

  itemSelect?.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const cost = opt.getAttribute('data-cost') || 0;
    document.getElementById('custodyCost').value = cost;
  });
})();
</script>
