<?php use App\Core\Helpers; ?>
<style>
.mr-table { width:100%; border-collapse:collapse; }
.mr-table th, .mr-table td { border:1px solid #e5e7eb; padding:10px; text-align:right; }
.mr-table th { background:#f3f4f6; }
.mr-table input { width:100%; box-sizing:border-box; }
</style>

<h2>خطة تلبية طلب <?= Helpers::e($mr['request_no']) ?></h2>
<p>الحالة: <?= Helpers::e($mr['status']) ?> — التكلفة: <?= number_format((float)$mr['total_estimated_cost'], 2) ?> <?= Helpers::e($mr['currency_code']) ?></p>

<form method="POST" action="/material-requests/<?= $mr['id'] ?>/fulfillment">
  <table class="mr-table">
    <thead>
      <tr>
        <th>المادة</th>
        <th>النوع</th>
        <th>مطلوب</th>
        <th>متاح</th>
        <th>مناقلة</th>
        <th>من مخزن</th>
        <th>إلى مخزن</th>
        <th>شراء</th>
        <th>تصنيع</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($plan as $idx => $p):
        $isNew = !empty($p['is_new_item']);
      ?>
      <tr>
        <td>
          <?= Helpers::e($p['item_name']) ?>
          <input type="hidden" name="plan[<?= $idx ?>][item_id]" value="<?= $p['item_id'] ?>">
          <input type="hidden" name="plan[<?= $idx ?>][material_request_item_id]" value="<?= $p['material_request_item_id'] ?>">
          <input type="hidden" name="plan[<?= $idx ?>][unit_id]" value="<?= $p['unit_id'] ?>">
          <input type="hidden" name="plan[<?= $idx ?>][warehouse_id]" value="<?= $p['warehouse_id'] ?>">
          <input type="hidden" name="plan[<?= $idx ?>][is_new_item]" value="<?= $isNew ? 1 : 0 ?>">
        </td>
        <td><?= $isNew ? 'جديدة — <a href="/purchasing/requests">طلب شراء</a>' : 'موجودة' ?></td>
        <td><?= number_format($p['requested_qty'], 2) ?></td>
        <td><?= number_format($p['available_qty'], 2) ?></td>
        <td>
          <?php if ($isNew): ?>
            <input type="text" value="-" disabled>
            <input type="hidden" name="plan[<?= $idx ?>][transfer_qty]" value="0">
          <?php else: ?>
            <input type="number" step="0.01" name="plan[<?= $idx ?>][transfer_qty]" value="<?= number_format($p['transfer_qty'], 2, '.', '') ?>">
          <?php endif; ?>
        </td>
        <td>
          <?php if ($isNew): ?>
            <input type="text" value="-" disabled>
          <?php else: ?>
            <select name="plan[<?= $idx ?>][from_warehouse_id]" class="form-input">
              <?php foreach ($warehouses as $w): ?>
              <option value="<?= $w['id'] ?>" <?= $p['warehouse_id'] == $w['id'] ? 'selected' : '' ?>><?= Helpers::e($w['name']) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($isNew): ?>
            <input type="text" value="-" disabled>
          <?php else: ?>
            <select name="plan[<?= $idx ?>][to_warehouse_id]" class="form-input">
              <?php foreach ($warehouses as $w): ?>
              <option value="<?= $w['id'] ?>" <?= $p['warehouse_id'] == $w['id'] ? 'selected' : '' ?>><?= Helpers::e($w['name']) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($isNew): ?>
            <input type="text" value="في انتظار طلب الشراء" disabled>
            <input type="hidden" name="plan[<?= $idx ?>][purchase_qty]" value="0">
          <?php else: ?>
            <input type="number" step="0.01" name="plan[<?= $idx ?>][purchase_qty]" value="<?= number_format($p['purchase_qty'], 2, '.', '') ?>">
          <?php endif; ?>
        </td>
        <td>
          <?php if ($isNew): ?>
            <input type="text" value="-" disabled>
            <input type="hidden" name="plan[<?= $idx ?>][manufacturing_qty]" value="0">
          <?php else: ?>
            <input type="number" step="0.01" name="plan[<?= $idx ?>][manufacturing_qty]" value="0">
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="form-group" style="margin-top:20px">
    <button type="submit" class="btn btn-success" onclick="return confirm('تأكيد تنفيذ التلبية؟')">تنفيذ التلبية</button>
    <a href="/material-requests/<?= $mr['id'] ?>" class="btn btn-light">إلغاء</a>
  </div>
</form>
