<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>إضافة صنف جديد لطلبات الشراء</h3>
  <form method="post" action="/purchasing/purchase-request-items" class="form-inline">
    <div class="form-row">
      <label>اسم الصنف</label>
      <input name="name" required placeholder="مثال: زيت دوار الشمس 5 لتر">
    </div>
    <div class="form-row">
      <label>التصنيف</label>
      <select name="category_id" required>
        <option value="">— اختر —</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= Helpers::e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <label>الوحدة</label>
      <select name="unit_id">
        <option value="0">— بدون —</option>
        <?php foreach ($units as $u): ?>
          <option value="<?= (int)$u['id'] ?>"><?= Helpers::e($u['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-success">إضافة</button>
  </form>
</div>

<div class="card">
  <h3>الأصناف المتاحة لطلبات الشراء</h3>
  <p class="text-muted">الأصناف المُفعّلة (مفتاح أخضر) فقط تظهر في تطبيق الموبايل عند إنشاء طلب شراء.</p>
  <table>
    <tr>
      <th>الاسم</th>
      <th>التصنيف</th>
      <th>الوحدة</th>
      <th>متاح لطلب الشراء</th>
      <th>إجراء</th>
    </tr>
    <?php foreach ($items as $it): ?>
    <tr>
      <td><?= Helpers::e($it['name']) ?></td>
      <td><?= Helpers::e($it['category_name'] ?? '-') ?></td>
      <td><?= Helpers::e($it['unit_name'] ?? '-') ?></td>
      <td>
        <span class="badge <?= $it['available_for_purchase_request'] ? 'badge-success' : 'badge-secondary' ?>">
          <?= $it['available_for_purchase_request'] ? 'نعم' : 'لا' ?>
        </span>
      </td>
      <td>
        <form method="post" action="/purchasing/purchase-request-items/<?= (int)$it['id'] ?>/toggle" style="display:inline">
          <button type="submit" class="btn btn-sm <?= $it['available_for_purchase_request'] ? 'btn-warning' : 'btn-success' ?>">
            <?= $it['available_for_purchase_request'] ? 'إخفاء من طلب الشراء' : 'إظهار في طلب الشراء' ?>
          </button>
        </form>
        <form method="post" action="/purchasing/purchase-request-items/<?= (int)$it['id'] ?>/delete" style="display:inline" onsubmit="return confirm('هل أنت متأكد من حذف <?= Helpers::e($it['name']) ?>؟');">
          <button type="submit" class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?>
      <tr><td colspan="5">لا توجد أصناف مضافة بعد. استخدم النموذج أعلاه لإضافة صنف.</td></tr>
    <?php endif; ?>
  </table>
</div>
