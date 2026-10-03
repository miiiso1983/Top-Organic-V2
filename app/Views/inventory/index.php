<?php use App\Core\{Helpers, ImportHelper}; ?>
<?php $importErrors = ImportHelper::getErrors(); ?>
<div class="card">
  <h3>إضافة تصنيف جديد</h3>
  <form method="post" action="/inventory/categories" class="form-inline">
    <?php
    $types = ['raw'=>'مواد أولية','prepared'=>'مواد محضرة','asset'=>'موجودات','tool'=>'أدوات','clothing'=>'ملابس','other'=>'أخرى'];
    ?>
    <div class="form-row"><label>اسم التصنيف</label><input name="name" required placeholder="مثال: خضروات، لحوم، أدوات مطبخ"></div>
    <div class="form-row"><label>نوع التصنيف</label>
      <select name="type">
        <?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-success">إضافة تصنيف</button>
  </form>
</div>

<div class="card">
  <h3>إضافة مادة جديدة (يدعم الباركود)</h3>
  <form method="post" action="/inventory/items" class="form-inline">
    <div class="form-row"><label>الباركود</label><input name="barcode" placeholder="امسح أو اكتب"></div>
    <div class="form-row"><label>الاسم</label><input name="name" required></div>
    <div class="form-row"><label>التصنيف</label>
      <select name="category_id" required>
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>"><?= Helpers::e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الوحدة</label>
      <select name="unit_id" required>
        <?php foreach ($units as $u): ?>
          <option value="<?= $u['id'] ?>"><?= Helpers::e($u['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>حد التنبيه</label><input type="number" name="min_qty" value="0" step="0.001"></div>
    <div class="form-row"><label>سعر الكلفة</label><input type="number" name="cost_price" value="0" step="0.01"></div>
    <div class="form-row"><label>تتبع الصلاحية؟</label>
      <select name="track_expiry"><option value="0">لا</option><option value="1">نعم</option></select>
    </div>
    <button class="btn btn-success">إضافة</button>
  </form>
</div>

<div class="card">
  <h3>تصفية الأصناف</h3>
  <form method="get" action="/inventory" class="form-inline">
    <div class="form-row"><label>التصنيف</label>
      <select name="type">
        <?php
        $types = ['all'=>'الكل','raw'=>'مواد أولية','prepared'=>'مواد محضرة','asset'=>'موجودات','tool'=>'أدوات','clothing'=>'ملابس','other'=>'أخرى'];
        foreach ($types as $k=>$v): ?>
          <option value="<?= $k ?>" <?= ($type ?? 'all') === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-secondary">عرض</button>
  </form>
</div>

<div class="card">
  <h3>تصدير واستيراد</h3>
  <?php if ($importErrors): ?>
  <div class="alert alert-danger">
    <strong>أخطاء الاستيراد:</strong>
    <ul><?php foreach ($importErrors as $err): ?><li><?= Helpers::e($err) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>
  <div class="form-inline" style="gap:8px; align-items:flex-end;">
    <a href="/inventory/export?format=excel&type=<?= urlencode($type ?? 'all') ?>" class="btn btn-primary">تصدير Excel</a>
    <a href="/inventory/export?format=pdf&type=<?= urlencode($type ?? 'all') ?>" class="btn btn-secondary">تصدير PDF</a>
    <a href="/inventory/import-template" class="btn btn-sm">نموذج الاستيراد</a>
  </div>
  <hr style="margin:16px 0">
  <form method="post" action="/inventory/import" enctype="multipart/form-data" class="form-inline">
    <div class="form-row"><label>رفع ملف Excel</label><input type="file" name="import_file" accept=".xlsx,.xls" required></div>
    <button class="btn btn-success">استيراد</button>
  </form>
</div>


<?php if ($lowStock): ?>
<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> يوجد <?= count($lowStock) ?> مادة وصلت لحد النواقص</div>
<?php endif; ?>

<div class="card">
  <h3>الأصناف والأرصدة الإجمالية</h3>
  <table>
    <tr><th>الباركود</th><th>المادة</th><th>التصنيف</th><th>الوحدة</th><th>الرصيد</th><th>حد التنبيه</th><th>الكلفة</th><th>إجراء</th></tr>
    <?php foreach ($items as $it): ?>
    <tr>
      <td><?= Helpers::e($it['barcode'] ?? '-') ?></td>
      <td><?= Helpers::e($it['name']) ?></td>
      <td><?= Helpers::e($it['category_name']) ?></td>
      <td><?= Helpers::e($it['unit_name']) ?></td>
      <td style="<?= $it['total_qty'] <= $it['min_qty'] && $it['min_qty'] > 0 ? 'color:#dc2626;font-weight:bold' : '' ?>">
        <?= (float)$it['total_qty'] ?></td>
      <td><?= (float)$it['min_qty'] ?></td>
      <td><?= Helpers::money($it['cost_price']) ?></td>
      <td>
        <a href="/inventory/item-cards/<?= (int)$it['id'] ?>" class="btn btn-sm">بطاقة</a>
        <a href="/inventory/cost-sheets/<?= (int)$it['id'] ?>" class="btn btn-sm">تكلفة</a>
        <a href="/inventory/items/<?= (int)$it['id'] ?>/price" class="btn btn-sm btn-success">سعر</a>
        <form method="post" action="/inventory/items/<?= (int)$it['id'] ?>/delete" style="display:inline" onsubmit="return confirm('هل أنت متأكد من حذف المادة: <?= Helpers::e($it['name']) ?>؟');">
          <button type="submit" class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="8">لا توجد مواد بعد</td></tr><?php endif; ?>
  </table>
</div>
