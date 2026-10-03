<?php use App\Core\{Helpers, Auth, ImportHelper}; ?>
<?php $canManage = Auth::can('recipes.manage'); ?>
<?php $importErrors = ImportHelper::getErrors(); ?>
<?php if ($importErrors): ?>
<div class="alert alert-danger">
  <strong>أخطاء الاستيراد:</strong>
  <ul><?php foreach ($importErrors as $err): ?><li><?= Helpers::e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if ($canManage): ?>
<div class="grid grid-2">
  <div class="card">
    <h3 id="menu-form-title">إضافة منيو جديد</h3>
    <form id="menu-form" method="post" action="/menu/menus" class="form-inline">
      <input type="hidden" id="menu-id" value="">
      <div class="form-row"><label>اسم المنيو</label><input id="menu-name" name="name" required></div>
      <div class="form-row"><label>الترتيب</label><input id="menu-sort" type="number" name="sort_order" value="0"></div>
      <div class="form-inline">
        <button class="btn btn-success" id="menu-form-submit">حفظ</button>
        <button type="button" class="btn btn-secondary" id="menu-form-cancel" style="display:none" onclick="resetMenuForm()">إلغاء</button>
      </div>
    </form>
  </div>
  <div class="card">
    <h3 id="category-form-title">إضافة تصنيف</h3>
    <form id="category-form" method="post" action="/menu/categories" class="form-inline">
      <input type="hidden" id="category-id" value="">
      <div class="form-row"><label>الاسم</label><input id="category-name" name="name" required></div>
      <div class="form-row"><label>المنيو</label>
        <select id="category-menu" name="menu_id" required>
          <?php foreach ($menus as $m): ?>
            <option value="<?= $m['id'] ?>"><?= Helpers::e($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>الترتيب</label><input id="category-sort" type="number" name="sort_order" value="0"></div>
      <div class="form-inline">
        <button class="btn btn-success" id="category-form-submit">حفظ</button>
        <button type="button" class="btn btn-secondary" id="category-form-cancel" style="display:none" onclick="resetCategoryForm()">إلغاء</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <h3>ربط القوائم بالطوابق/الصالات</h3>
  <?php if (!$floors): ?>
    <p>لا توجد طوابق/صالات بعد. أضِفها من صفحة الطاولات.</p>
  <?php else: ?>
  <table>
    <tr><th>المنيو</th><th>الطوابق/الصالات المرتبطة</th><th>إجراءات</th></tr>
    <?php foreach ($menus as $m):
      $menuFloors = array_filter($assignments, fn($a) => $a['menu_id'] == $m['id']);
      $menuFloorIds = array_column($menuFloors, 'floor_id');
    ?>
    <tr>
      <td>
        <strong id="menu-name-<?= $m['id'] ?>"><?= Helpers::e($m['name']) ?></strong>
        <?php if ($m['is_default']): ?><span class="badge badge-info">افتراضي</span><?php endif; ?>
        <br><small class="text-muted">الترتيب: <?= (int)$m['sort_order'] ?></small>
      </td>
      <td>
        <form method="post" action="/menu/menus/<?= $m['id'] ?>/floors" id="floor-form-<?= $m['id'] ?>">
          <div class="floor-checks">
            <?php foreach ($floors as $f):
              $checked = in_array($f['id'], $menuFloorIds);
            ?>
              <label class="floor-check"><input type="checkbox" name="floor_id[]" value="<?= $f['id'] ?>" <?= $checked ? 'checked' : '' ?>> <?= Helpers::e($f['name']) ?></label>
            <?php endforeach; ?>
          </div>
          <button type="submit" class="btn btn-sm btn-success" style="margin-top:10px">حفظ</button>
        </form>
      </td>
      <td>
        <button type="button" class="btn btn-sm btn-primary" onclick='editMenu(<?= json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>تعديل</button>
        <?php if (!$m['is_default']): ?>
        <form method="post" action="/menu/menus/<?= $m['id'] ?>/delete" onsubmit="return confirm('هل أنت متأكد من حذف المنيو؟')" style="display:inline">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<div class="card">
  <h3>التصنيفات</h3>
  <table>
    <tr><th>المنيو</th><th>التصنيف</th><th>الترتيب</th><th></th></tr>
    <?php foreach ($categories as $c): ?>
    <tr>
      <td><?= Helpers::e($c['menu_name'] ?? '-') ?></td>
      <td><?= Helpers::e($c['name']) ?></td>
      <td><?= (int)$c['sort_order'] ?></td>
      <td>
        <button type="button" class="btn btn-sm btn-primary" onclick='editCategory(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>تعديل</button>
        <form method="post" action="/menu/categories/<?= $c['id'] ?>/delete" onsubmit="return confirm('هل أنت متأكد من حذف التصنيف؟')" style="display:inline">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$categories): ?><tr><td colspan="4">لا توجد تصنيفات بعد</td></tr><?php endif; ?>
  </table>
</div>

<div class="grid grid-2">
  <div class="card" id="item-form-card">
    <h3 id="item-form-title">إضافة صنف</h3>
    <form id="item-form" method="post" action="/menu/items" enctype="multipart/form-data">
      <input type="hidden" id="item-id" value="">
      <div class="form-row"><label>الاسم</label><input id="item-name" name="name" required></div>
      <div class="form-row"><label>التصنيف</label>
        <select id="item-category" name="category_id" required>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= Helpers::e($c['menu_name'] . ' - ' . $c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>السعر</label><input id="item-price" type="number" name="price" min="0" step="0.01" required></div>
      <div class="form-row"><label>التكلفة</label><input id="item-cost" type="number" name="cost" min="0" step="0.01" value="0"></div>
      <div class="form-row"><label>وقت التحضير (دقيقة)</label><input id="item-prep" type="number" name="prep_minutes" value="10" min="1"></div>
      <div class="form-row"><label>محطة المطبخ</label>
        <select id="item-station" name="station_id"><option value="">-</option>
          <?php foreach ($stations as $s): ?>
            <option value="<?= $s['id'] ?>"><?= Helpers::e($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>قسم المطبخ</label>
        <select id="item-kitchen-section" name="kitchen_section_id"><option value="">-</option>
          <?php foreach ($sections as $sec): ?>
            <option value="<?= $sec['id'] ?>"><?= Helpers::e($sec['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>مطبخ التحضير</label>
        <select id="item-preparation-kitchen" name="preparation_kitchen_id"><option value="">-</option>
          <?php foreach ($preparationKitchens as $pk): ?>
            <option value="<?= $pk['id'] ?>"><?= Helpers::e($pk['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>صورة الصنف</label><input type="file" name="image" accept="image/png,image/jpeg,image/webp">
        <small id="item-image-hint" style="color:#666">اترك فارغ للتعديل إذا لم ترد تغيير الصورة</small>
      </div>
      <div class="form-row"><label>الوصف</label><textarea id="item-desc" name="description" rows="2"></textarea></div>
      <div class="form-inline">
        <button class="btn btn-success" id="item-form-submit">حفظ</button>
        <button type="button" class="btn btn-secondary" id="item-form-cancel" style="display:none" onclick="resetItemForm()">إلغاء</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h3>استيراد ملف Excel</h3>
    <p style="font-size:13px;color:#555">الأعمدة المطلوبة: <code>menu_name</code>, <code>category_name</code>, <code>item_name</code>, <code>price</code>.<br>اختيارية: <code>cost</code>, <code>prep_minutes</code>, <code>description</code>.<br>المنيو والتصنيفات تُنشأ تلقائيًا إن لم تكن موجودة.</p>
    <form method="post" action="/menu/import-excel" enctype="multipart/form-data">
      <div class="form-row"><input type="file" name="import_file" accept=".xlsx,.xls" required></div>
      <button class="btn btn-success">رفع واستيراد</button>
      <a href="/menu/template.xlsx" class="btn btn-sm btn-secondary" style="margin-inline-start:8px">تنزيل نموذج Excel</a>
      <a href="/menu/template.csv" class="btn btn-sm btn-secondary" style="margin-inline-start:8px">CSV</a>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h3>الأصناف (<?= count($items) ?>)</h3>
  <table>
    <tr><th>الصورة</th><th>الصنف</th><th>التصنيف</th><th>السعر</th><th>التكلفة</th><th>وقت التحضير</th><th>قسم المطبخ</th><th>مطبخ التحضير</th><?php if ($canManage): ?><th></th><?php endif; ?></tr>
    <?php foreach ($items as $it): ?>
    <tr>
      <td><?php if ($it['image']): ?><img src="<?= Helpers::e($it['image']) ?>" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:6px"><?php else: ?><i class="fa-solid fa-bowl-rice"></i><?php endif; ?></td>
      <td><?= Helpers::e($it['name']) ?></td>
      <td><?= Helpers::e($it['category_name']) ?></td>
      <td><?= Helpers::money($it['price']) ?></td>
      <td><?= Helpers::money($it['cost']) ?></td>
      <td><?= $it['prep_minutes'] ?> د</td>
      <td><?= Helpers::e($it['section_name'] ?? '-') ?></td>
      <td><?= Helpers::e($it['preparation_kitchen_name'] ?? '-') ?></td>
      <?php if ($canManage): ?>
      <td>
        <button type="button" class="btn btn-sm btn-primary" onclick='editItem(<?= json_encode($it, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>تعديل</button>
        <form method="post" action="/menu/items/<?= $it['id'] ?>/delete" onsubmit="return confirm('تأكيد الحذف؟')" style="display:inline">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="8">لا توجد أصناف بعد</td></tr><?php endif; ?>
  </table>
</div>

<?php if ($canManage): ?>
<script>
function editMenu(menu) {
  const form = document.getElementById('menu-form');
  form.action = '/menu/menus/' + menu.id;
  document.getElementById('menu-form-title').textContent = 'تعديل منيو: ' + menu.name;
  document.getElementById('menu-form-submit').textContent = 'حفظ التعديلات';
  document.getElementById('menu-form-cancel').style.display = '';
  document.getElementById('menu-id').value = menu.id;
  document.getElementById('menu-name').value = menu.name || '';
  document.getElementById('menu-sort').value = menu.sort_order || 0;
  document.getElementById('menu-form').scrollIntoView({ behavior: 'smooth' });
}
function resetMenuForm() {
  const form = document.getElementById('menu-form');
  form.action = '/menu/menus';
  form.reset();
  document.getElementById('menu-form-title').textContent = 'إضافة منيو جديد';
  document.getElementById('menu-form-submit').textContent = 'حفظ';
  document.getElementById('menu-form-cancel').style.display = 'none';
  document.getElementById('menu-id').value = '';
}

function editCategory(category) {
  const form = document.getElementById('category-form');
  form.action = '/menu/categories/' + category.id;
  document.getElementById('category-form-title').textContent = 'تعديل تصنيف: ' + category.name;
  document.getElementById('category-form-submit').textContent = 'حفظ التعديلات';
  document.getElementById('category-form-cancel').style.display = '';
  document.getElementById('category-id').value = category.id;
  document.getElementById('category-menu').value = category.menu_id || '';
  document.getElementById('category-name').value = category.name || '';
  document.getElementById('category-sort').value = category.sort_order || 0;
  document.getElementById('category-form').scrollIntoView({ behavior: 'smooth' });
}
function resetCategoryForm() {
  const form = document.getElementById('category-form');
  form.action = '/menu/categories';
  form.reset();
  document.getElementById('category-form-title').textContent = 'إضافة تصنيف';
  document.getElementById('category-form-submit').textContent = 'حفظ';
  document.getElementById('category-form-cancel').style.display = 'none';
  document.getElementById('category-id').value = '';
}

function editItem(item) {
  const form = document.getElementById('item-form');
  form.action = '/menu/items/' + item.id;
  document.getElementById('item-form-title').textContent = 'تعديل صنف: ' + item.name;
  document.getElementById('item-form-submit').textContent = 'حفظ التعديلات';
  document.getElementById('item-form-cancel').style.display = '';
  document.getElementById('item-id').value = item.id;
  document.getElementById('item-name').value = item.name || '';
  document.getElementById('item-category').value = item.category_id || '';
  document.getElementById('item-price').value = item.price || '';
  document.getElementById('item-cost').value = item.cost || 0;
  document.getElementById('item-prep').value = item.prep_minutes || 10;
  document.getElementById('item-station').value = item.station_id || '';
  document.getElementById('item-kitchen-section').value = item.kitchen_section_id || '';
  document.getElementById('item-preparation-kitchen').value = item.preparation_kitchen_id || '';
  document.getElementById('item-desc').value = item.description || '';
  document.getElementById('item-image-hint').textContent = item.image ? 'الصورة الحالية: ' + item.image + ' (اترك فارغ إذا لم ترد تغييرها)' : 'لا توجد صورة';
  document.getElementById('item-form-card').scrollIntoView({ behavior: 'smooth' });
}

function resetItemForm() {
  const form = document.getElementById('item-form');
  form.action = '/menu/items';
  form.reset();
  document.getElementById('item-form-title').textContent = 'إضافة صنف';
  document.getElementById('item-form-submit').textContent = 'حفظ';
  document.getElementById('item-form-cancel').style.display = 'none';
  document.getElementById('item-id').value = '';
  document.getElementById('item-kitchen-section').value = '';
  document.getElementById('item-image-hint').textContent = 'اترك فارغ للتعديل إذا لم ترد تغيير الصورة';
}
</script>
<?php endif; ?>
