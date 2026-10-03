<?php use App\Core\Helpers; ?>
<?php
$typeLabels = [
    'warehouse' => 'مخزن',
    'department' => 'قسم',
    'sales' => 'قسم مبيعات',
    'manufacturing' => 'قسم تصنيع',
];
?>

<div class="card">
  <h3>إضافة مخزن / قسم جديد</h3>
  <form method="post" action="/warehouses" class="form-inline">
    <div class="form-row"><label>الاسم</label><input name="name" required></div>
    <div class="form-row"><label>الموقع</label><input name="location" placeholder="مثلاً: الطابق الأول"></div>
    <div class="form-row"><label>النوع</label>
      <select name="warehouse_type">
        <option value="warehouse">مخزن</option>
        <option value="department">قسم</option>
        <option value="sales">قسم مبيعات</option>
        <option value="manufacturing">قسم تصنيع</option>
      </select>
    </div>
    <button class="btn btn-success">إضافة</button>
  </form>
</div>

<div class="card">
  <h3>المخازن والأقسام</h3>
  <table>
    <tr><th>الاسم</th><th>الموقع</th><th>النوع</th><th>الحالة</th><th></th></tr>
    <?php foreach ($warehouses as $w): ?>
    <tr>
      <td>
        <form method="post" action="/warehouses/<?= (int)$w['id'] ?>" style="display:flex;gap:8px">
          <input name="name" value="<?= Helpers::e($w['name']) ?>" required>
          <input name="location" value="<?= Helpers::e($w['location'] ?? '') ?>">
          <select name="warehouse_type">
            <?php foreach ($typeLabels as $k => $v): ?>
              <option value="<?= $k ?>" <?= ($w['warehouse_type'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-secondary">حفظ</button>
        </form>
      </td>
      <td><?= Helpers::e($w['location'] ?? '') ?></td>
      <td><?= $typeLabels[$w['warehouse_type'] ?? 'warehouse'] ?? 'مخزن' ?></td>
      <td><span class="badge badge-<?= $w['is_active'] ? 'success' : 'danger' ?>"><?= $w['is_active'] ? 'نشط' : 'معطل' ?></span></td>
      <td>
        <?php if ($w['is_active']): ?>
          <form method="post" action="/warehouses/<?= (int)$w['id'] ?>/delete" onsubmit="return confirm('تأكيد تعطيل المخزن؟')" style="display:inline">
            <button class="btn btn-sm btn-danger">تعطيل</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$warehouses): ?>
      <tr><td colspan="5">لا توجد مخازن</td></tr>
    <?php endif; ?>
  </table>
</div>
