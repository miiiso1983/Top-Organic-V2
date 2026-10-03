<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>جرد سريع (دوري أو مفاجئ) مع تسوية تلقائية</h3>
  <form method="post" action="/inventory/stocktakes" class="form-inline">
    <div class="form-row"><label>المخزن</label>
      <select name="warehouse_id">
        <?php foreach ($warehouses as $w): ?><option value="<?= $w['id'] ?>"><?= Helpers::e($w['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>المادة</label>
      <select name="item_id">
        <?php foreach ($items as $it): ?><option value="<?= $it['id'] ?>"><?= Helpers::e($it['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>النوع</label>
      <select name="stocktake_type"><option value="periodic">دوري</option><option value="surprise">مفاجئ</option></select>
    </div>
    <div class="form-row"><label>الكمية المعدودة فعلياً</label><input type="number" name="counted_qty" step="0.001" min="0" required></div>
    <button class="btn btn-success">تنفيذ الجرد</button>
  </form>
</div>

<div class="card">
  <h3>سجلات الجرد</h3>
  <table>
    <tr><th>#</th><th>المخزن</th><th>النوع</th><th>عدد المواد</th><th>بواسطة</th><th>التاريخ</th></tr>
    <?php foreach ($stocktakes as $st): ?>
    <tr>
      <td><?= $st['id'] ?></td>
      <td><?= Helpers::e($st['warehouse_name']) ?></td>
      <td><?= $st['stocktake_type'] === 'periodic' ? 'دوري' : 'مفاجئ' ?></td>
      <td><?= $st['items_count'] ?></td>
      <td><?= Helpers::e($st['full_name'] ?? '-') ?></td>
      <td><?= date('m/d H:i', strtotime($st['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$stocktakes): ?><tr><td colspan="6">لا توجد سجلات جرد</td></tr><?php endif; ?>
  </table>
</div>
