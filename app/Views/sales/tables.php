<?php use App\Core\{Helpers, Auth}; ?>
<div class="page-head">
  <h2>مخطط الطاولات</h2>
  <div class="toolbar">
    <span class="badge badge-success">متاحة</span>
    <span class="badge badge-danger">مشغولة</span>
    <span class="badge badge-info">محجوزة</span>
    <span class="badge badge-secondary">تنظيف</span>
  </div>
</div>

<?php if (Auth::can('sales.view')): ?>
<div class="card">
  <h3>إضافة طاولة جديدة</h3>
  <form method="post" action="/tables" class="form-inline">
    <div class="form-row"><label>اسم الطاولة</label><input name="name" required placeholder="مثال: T-05"></div>
    <div class="form-row"><label>الصالة/القاعة</label><input name="hall" placeholder="مثال: قاعة الإطلال"></div>
    <div class="form-row"><label>عدد الكراسي</label><input type="number" name="capacity" value="4" min="1" required></div>
    <div class="form-row"><label>الطابق</label>
      <select name="floor_id">
        <option value="">بدون تصنيف</option>
        <?php foreach ($floors as $f): ?>
          <option value="<?= $f['id'] ?>"><?= Helpers::e($f['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-success">إضافة</button>
  </form>
</div>

<div class="card">
  <h3>إضافة طابق/صالة جديدة</h3>
  <form method="post" action="/tables/floors" class="form-inline">
    <div class="form-row"><label>الاسم</label><input name="name" required placeholder="مثال: الطابق الأول"></div>
    <div class="form-row"><label>الترتيب</label><input type="number" name="sort_order" value="0"></div>
    <div class="form-row"><label>مطبخ التحضير</label>
      <select name="preparation_kitchen_id">
        <option value="">بدون مطبخ (الصنف يحدد)</option>
        <?php foreach ($preparationKitchens as $k): ?>
          <option value="<?= $k['id'] ?>"><?= Helpers::e($k['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-success">حفظ</button>
  </form>
</div>
<?php endif; ?>

<?php
$byFloor = [];
$noFloor = [];
foreach ($tables as $t) {
    if ($t['floor_id']) {
        $byFloor[(int)$t['floor_id']][] = $t;
    } else {
        $noFloor[] = $t;
    }
}

function renderTableBox(array $t): void {
    ?>
    <div class="table-box <?= $t['status'] ?>">
      <div style="font-size:22px"><i class="fa-solid fa-chair"></i></div>
      <strong><?= Helpers::e($t['name']) ?></strong>
      <div style="font-size:12px">السعة: <?= (int)($t['capacity'] ?? $t['seats']) ?> كرسي</div>
      <?php if ($t['hall']): ?>
        <div style="font-size:11px;color:#e0f2fe"><?= Helpers::e($t['hall']) ?></div>
      <?php endif; ?>
      <?php if ($t['current_order']): ?>
        <div style="font-size:11px;margin-top:4px"><?= Helpers::e($t['current_order']) ?></div>
      <?php endif; ?>
      <form method="post" action="/tables/<?= $t['id'] ?>/status" style="margin-top:8px">
        <select name="status" onchange="this.form.submit()"
                style="font-size:12px;padding:4px;color:#111">
          <?php foreach (['available'=>'متاحة','occupied'=>'مشغولة','reserved'=>'محجوزة','cleaning'=>'تنظيف'] as $k => $v): ?>
            <option value="<?= $k ?>" <?= $t['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <div style="display:flex;gap:6px;justify-content:center;margin-top:8px">
        <a href="/tables/<?= $t['id'] ?>/edit" class="btn btn-sm btn-secondary">تعديل</a>
        <form method="post" action="/tables/<?= $t['id'] ?>/delete" onsubmit="return confirm('تأكيد حذف الطاولة؟')" style="display:inline">
          <button type="submit" class="btn btn-sm btn-danger">حذف</button>
        </form>
      </div>
    </div>
    <?php
}
?>

<?php foreach ($floors as $f): ?>
  <?php $floorTables = $byFloor[(int)$f['id']] ?? []; ?>
  <h3 style="margin-top:16px">
    <?= Helpers::e($f['name']) ?>
    <?php if ($f['preparation_kitchen_name']): ?>
      <span style="font-size:13px;color:#16a34a">(مطبخ: <?= Helpers::e($f['preparation_kitchen_name']) ?>)</span>
    <?php else: ?>
      <span style="font-size:13px;color:#9ca3af">(بدون مطبخ محدد)</span>
    <?php endif; ?>
  </h3>
  <div class="form-inline" style="margin-bottom:10px">
    <form method="post" action="/tables/floors/<?= (int)$f['id'] ?>/update" class="form-inline">
      <div class="form-row"><label>مطبخ التحضير</label>
        <select name="preparation_kitchen_id">
          <option value="">بدون مطبخ (الصنف يحدد)</option>
          <?php foreach ($preparationKitchens as $k): ?>
            <option value="<?= $k['id'] ?>" <?= (int)($f['preparation_kitchen_id'] ?? 0) === (int)$k['id'] ? 'selected' : '' ?>><?= Helpers::e($k['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-sm btn-success">تحديث</button>
    </form>
    <form method="post" action="/tables/floors/<?= (int)$f['id'] ?>/delete" onsubmit="return confirm('تأكيد حذف الطابق؟ سيتم نقل طاولاته إلى بدون طابق.')" style="display:inline">
      <button type="submit" class="btn btn-sm btn-danger" style="margin-right:8px">حذف</button>
    </form>
  </div>
  <?php if ($floorTables): ?>
    <div class="tables-map">
      <?php foreach ($floorTables as $t): renderTableBox($t); endforeach; ?>
    </div>
  <?php else: ?>
    <p style="font-size:13px;color:#666">لا توجد طاولات في هذا الطابق.</p>
  <?php endif; ?>
<?php endforeach; ?>

<?php if ($noFloor): ?>
  <h3 style="margin-top:16px">بدون طابق</h3>
  <div class="tables-map">
    <?php foreach ($noFloor as $t): renderTableBox($t); endforeach; ?>
  </div>
<?php endif; ?>
