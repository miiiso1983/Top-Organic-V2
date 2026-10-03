<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>تحديث سعر الشراء: <?= Helpers::e($item['name']) ?></h3>
  <p>
    التصنيف: <strong><?= Helpers::e($item['category_name']) ?></strong> | 
    الوحدة: <strong><?= Helpers::e($item['unit_name']) ?></strong> | 
    السعر الحالي: <strong><?= Helpers::money($item['cost_price']) ?></strong>
  </p>
</div>

<div class="card">
  <form method="post" action="/inventory/items/<?= (int)$item['id'] ?>/price" style="max-width:500px">
    <div class="form-row">
      <label>سعر الشراء الجديد</label>
      <input type="number" name="cost_price" value="<?= (float)$item['cost_price'] ?>" step="0.01" min="0.01" required>
    </div>
    <div class="form-row">
      <label>تاريخ بدء السعر (فعّال من)</label>
      <input type="date" name="effective_date" value="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="form-row">
      <button type="submit" class="btn btn-success"><i class="fa-solid fa-lock"></i> حفظ السعر</button>
      <a href="/inventory" class="btn btn-sm">رجوع</a>
    </div>
  </form>
</div>

<?php if ($history): ?>
<div class="card">
  <h4>سجل تغييرات السعر</h4>
  <table>
    <tr><th>التاريخ الفعّال</th><th>السعر</th><th>الملاحظات</th></tr>
    <?php foreach ($history as $h): ?>
    <tr>
      <td><?= Helpers::e($h['effective_date']) ?></td>
      <td><?= Helpers::money($h['price']) ?></td>
      <td><?= Helpers::e($h['notes'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>
