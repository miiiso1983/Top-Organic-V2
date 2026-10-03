<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>تصنيع داخلي (من قسم التصنيع إلى قسم المبيعات)</h3>
  <form method="post" action="/production/manufacture" class="form-inline">
    <div class="form-row"><label>الوصفة</label>
      <select name="recipe_id" required>
        <?php foreach ($recipes as $r): ?>
          <option value="<?= $r['id'] ?>"><?= Helpers::e($r['name']) ?> (ناتج: <?= Helpers::e($r['output_name']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الكمية الناتجة</label><input type="number" name="qty" step="0.001" min="0.001" required></div>
    <div class="form-row"><label>من المخزن/القسم</label>
      <select name="from_warehouse_id" required>
        <?php foreach ($warehouses as $w): ?>
          <option value="<?= $w['id'] ?>"><?= Helpers::e($w['name']) ?> (<?= $w['warehouse_type'] ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>إلى المخزن/القسم</label>
      <select name="to_warehouse_id" required>
        <?php foreach ($warehouses as $w): ?>
          <option value="<?= $w['id'] ?>"><?= Helpers::e($w['name']) ?> (<?= $w['warehouse_type'] ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-success">تنفيذ التصنيع</button>
  </form>
  <?php if (!$recipes): ?>
    <p style="color:#d97706;margin-top:8px"><i class="fa-solid fa-triangle-exclamation"></i> أضف وصفات تصنيع من المخزن أولاً</p>
  <?php endif; ?>
</div>

<div class="card">
  <h3>الوصفات المعرفة</h3>
  <table>
    <tr><th>الوصفة</th><th>الناتج</th><th>الكمية الأساسية</th><th>الحالة</th></tr>
    <?php foreach ($recipes as $r): ?>
    <tr>
      <td><?= Helpers::e($r['name']) ?></td>
      <td><?= Helpers::e($r['output_name']) ?></td>
      <td><?= (float) $r['output_qty'] ?></td>
      <td><?= $r['is_active'] ? 'نشط' : 'معطل' ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$recipes): ?><tr><td colspan="4">لا توجد وصفات</td></tr><?php endif; ?>
  </table>
</div>
