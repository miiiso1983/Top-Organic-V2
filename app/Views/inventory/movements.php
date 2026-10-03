<?php use App\Core\Helpers; ?>
<?php $typeLabels = ['in'=>'إدخال','out'=>'إخراج','transfer_in'=>'مناقلة واردة','transfer_out'=>'مناقلة صادرة',
                     'adjustment'=>'تسوية','consumption'=>'استهلاك','return'=>'مرتجع']; ?>
<div class="card">
  <h3>حركة جديدة (إدخال/إخراج بالباركود أو بالاختيار)</h3>
  <form method="post" action="/inventory/movements" class="form-inline">
    <div class="form-row"><label>الباركود</label><input name="barcode" placeholder="اختياري"></div>
    <div class="form-row" style="min-width:220px"><label>أو المادة</label>
      <select name="item_id" class="select2-item" data-placeholder="ابحث باسم المادة أو الباركود" style="width:100%">
        <option value="">— اختر مادة —</option>
        <?php foreach ($items as $it): ?>
          <option value="<?= $it['id'] ?>"><?= Helpers::e($it['name']) ?><?= $it['barcode'] ? ' (' . Helpers::e($it['barcode']) . ')' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>المخزن</label>
      <select name="warehouse_id">
        <?php foreach ($warehouses as $w): ?>
          <option value="<?= $w['id'] ?>"><?= Helpers::e($w['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>النوع</label>
      <select name="movement_type" id="movement_type" onchange="toggleDestination()">
        <option value="in">إدخال</option><option value="out">إخراج</option>
      </select>
    </div>
    <div class="form-row" id="destination_row" style="display:none">
      <label>الوجهة (مخزن/قسم)</label>
      <select name="destination_warehouse_id">
        <option value="">— اختياري —</option>
        <?php foreach ($warehouses as $w): ?>
          <option value="<?= $w['id'] ?>"><?= Helpers::e($w['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الكمية</label><input type="number" name="qty" step="0.001" min="0.001" required></div>
    <div class="form-row"><label>الوحدة</label>
      <select name="unit_id" required>
        <?php foreach ($units as $u): ?><option value="<?= $u['id'] ?>"><?= Helpers::e($u['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>سعر الوحدة</label><input type="number" name="unit_cost" step="0.01" value="0"></div>
    <div class="form-row"><label>الصلاحية</label><input type="date" name="expiry_date"></div>
    <div class="form-row"><label>ملاحظات</label><input name="notes"></div>
    <button class="btn btn-success">تسجيل</button>
  </form>
</div>
<script>
function toggleDestination() {
  var type = document.getElementById('movement_type').value;
  document.getElementById('destination_row').style.display = (type === 'out') ? 'block' : 'none';
}
</script>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h3>آخر الحركات</h3>
    <div>
      <a href="?format=excel" class="btn btn-sm">تصدير Excel</a>
      <a href="?format=pdf" class="btn btn-sm">تصدير PDF</a>
      <button type="button" class="btn btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i> طباعة</button>
    </div>
  </div>
  <table>
    <tr><th>المادة</th><th>المخزن</th><th>الوجهة</th><th>النوع</th><th>الكمية</th><th>الصلاحية</th><th>المرجع</th><th>بواسطة</th><th>الوقت</th></tr>
    <?php foreach ($movements as $m): ?>
    <tr>
      <td><?= Helpers::e($m['item_name']) ?></td>
      <td><?= Helpers::e($m['warehouse_name']) ?></td>
      <td><?= Helpers::e($m['destination_warehouse_name'] ?? '-') ?></td>
      <td><span class="badge badge-<?= in_array($m['movement_type'],['in','transfer_in','return']) ? 'success' : 'warning' ?>">
        <?= $typeLabels[$m['movement_type']] ?? $m['movement_type'] ?></span></td>
      <td><?= (float)$m['qty'] ?></td>
      <td><?= $m['expiry_date'] ?? '-' ?></td>
      <td><?= Helpers::e($m['reference'] ?? '-') ?></td>
      <td><?= Helpers::e($m['full_name'] ?? '-') ?></td>
      <td><?= date('m/d H:i', strtotime($m['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$movements): ?><tr><td colspan="9">لا توجد حركات</td></tr><?php endif; ?>
  </table>
</div>


