<?php use App\Core\Helpers; ?>

<div class="card">
  <h3>اختيار المخزن</h3>
  <form method="get" action="/warehouses/items" class="form-inline" style="justify-content:space-between">
    <div style="display:flex;gap:12px;align-items:flex-end">
      <div class="form-row">
        <label>المخزن</label>
        <select name="warehouse_id">
          <option value="">— كل المخازن —</option>
          <?php foreach ($warehouses as $w): ?>
            <option value="<?= (int)$w['id'] ?>" <?= $selectedId === (int)$w['id'] ? 'selected' : '' ?>>
              <?= Helpers::e($w['name']) ?><?= $w['location'] ? ' (' . Helpers::e($w['location']) . ')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-secondary">عرض</button>
    </div>
    <div style="display:flex;gap:8px">
      <a href="/warehouses/items/export?format=excel&warehouse_id=<?= $selectedId ?>" class="btn btn-primary" download="warehouse_items_<?= date('Ymd') ?>.xlsx">تصدير Excel</a>
      <a href="/warehouses/items/export?format=pdf&warehouse_id=<?= $selectedId ?>" class="btn btn-secondary" download="warehouse_items_<?= date('Ymd') ?>.pdf">تصدير PDF</a>
    </div>
  </form>
</div>

<?php
$showAll = $selectedId === 0;
$rows = [];
foreach ($byWarehouse as $warehouse) {
    foreach ($warehouse['items'] as $it) {
        $rows[] = array_merge($it, ['warehouse_name' => $warehouse['info']['name']]);
    }
}
?>

<div class="card">
  <h3>
    <?php if ($showAll): ?>
      الأصناف في جميع المخازن
    <?php else: ?>
      الأصناف في <?= Helpers::e($rows[0]['warehouse_name'] ?? 'المخزن المختار') ?>
    <?php endif; ?>
    <span class="badge badge-info"><?= count($rows) ?> صنف</span>
  </h3>
  <?php if ($rows): ?>
  <table>
    <tr>
      <?php if ($showAll): ?><th>المخزن</th><?php endif; ?>
      <th>الباركود</th><th>المادة</th><th>الوحدة</th><th>الرصيد</th>
    </tr>
    <?php foreach ($rows as $it): ?>
    <tr>
      <?php if ($showAll): ?><td><?= Helpers::e($it['warehouse_name']) ?></td><?php endif; ?>
      <td><?= Helpers::e($it['barcode'] ?? '-') ?></td>
      <td><?= Helpers::e($it['item_name']) ?></td>
      <td><?= Helpers::e($it['unit_name'] ?? '-') ?></td>
      <td><?= (float)$it['qty'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
    <p style="color:#666">لا توجد أصناف متوفرة في هذا المخزن.</p>
  <?php endif; ?>
</div>
