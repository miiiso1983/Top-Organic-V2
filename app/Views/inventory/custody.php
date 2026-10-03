<?php use App\Core\Helpers; ?>
<?php $statusLabels = ['active'=>'نشطة','returned'=>'مرجعة','damaged'=>'تالفة','lost'=>'مفقودة']; ?>
<div class="card">
  <h3>تسليم عهدة (موجودات/أدوات/ملابس)</h3>
  <form method="post" action="/custody" class="form-inline" id="custody-form">
    <div class="form-row"><label>الباركود</label><input name="barcode" id="custody-barcode" placeholder="امسح أو اكتب"></div>
    <div class="form-row"><label>أو المادة</label>
      <select name="item_id" id="custody-item">
        <option value="">— اختيار —</option>
        <?php foreach ($items as $it): ?>
          <option value="<?= $it['id'] ?>" data-type="<?= $it['category_type'] ?? 'other' ?>"><?= Helpers::e($it['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>المخزن/القسم</label>
      <select name="warehouse_id" required>
        <?php foreach ($warehouses as $w): ?><option value="<?= $w['id'] ?>"><?= Helpers::e($w['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الوحدة</label>
      <select name="unit_id" required>
        <?php foreach ($units as $u): ?><option value="<?= $u['id'] ?>"><?= Helpers::e($u['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الكمية</label><input type="number" name="qty" value="1" min="0.001" step="0.001" required></div>
    <div class="form-row"><label>نوع العهدة</label>
      <select name="custodian_type" id="custodian-type">
        <option value="employee">عهدة شخصية</option>
        <option value="department">عهدة قسم</option>
      </select>
    </div>
    <div class="form-row"><label>الموظف</label>
      <select name="employee_id">
        <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= Helpers::e($e['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>أو القسم</label><input name="department" placeholder="مثال: المطبخ"></div>
    <div class="form-row"><label>انتهاء العهدة</label><input type="date" name="expiry_date"></div>
    <button class="btn btn-success">تسليم</button>
  </form>
  <?php if (!$items): ?>
    <p style="color:#d97706;margin-top:8px"><i class="fa-solid fa-triangle-exclamation"></i> أضف مواداً بتصنيف موجودات/أدوات/ملابس في المخزون أولاً</p>
  <?php endif; ?>
</div>
<script>
(function(){
  const item = document.getElementById('custody-item');
  const typeSel = document.getElementById('custodian-type');
  function update(){
    const opt = item.options[item.selectedIndex];
    const t = opt?.dataset?.type || '';
    if (t === 'clothing') {
      typeSel.value = 'employee';
      typeSel.disabled = true;
    } else {
      typeSel.disabled = false;
    }
  }
  item?.addEventListener('change', update);
  update();
})();
</script>

<div class="card">
  <h3>سجل العهدة</h3>
  <table>
    <tr><th>المادة</th><th>الكمية</th><th>العهدة على</th><th>الانتهاء</th><th>الحالة</th><th>التاريخ</th><th></th></tr>
    <?php foreach ($custody as $c): ?>
    <tr>
      <td><?= Helpers::e($c['item_name']) ?></td>
      <td><?= (float)$c['qty'] ?></td>
      <td><?= $c['custodian_type'] === 'employee'
            ? '<i class="fa-solid fa-user"></i> ' . Helpers::e($c['employee_name'] ?? '-')
            : '<i class="fa-solid fa-building"></i> ' . Helpers::e($c['department'] ?? '-') ?></td>
      <td><?= $c['expiry_date'] ?? '-' ?></td>
      <td><span class="badge badge-<?= $c['status'] === 'active' ? 'info' : ($c['status'] === 'returned' ? 'success' : 'danger') ?>">
        <?= $statusLabels[$c['status']] ?></span></td>
      <td><?= date('m/d', strtotime($c['created_at'])) ?></td>
      <td>
        <?php if ($c['status'] === 'active'): ?>
        <form method="post" action="/custody/<?= $c['id'] ?>/return" class="form-inline">
          <select name="status">
            <option value="returned">إرجاع سليم</option>
            <option value="damaged">تالفة</option>
            <option value="lost">مفقودة</option>
          </select>
          <button class="btn btn-sm btn-warning">تحديث</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$custody): ?><tr><td colspan="7">لا توجد عهد مسجلة</td></tr><?php endif; ?>
  </table>
</div>
