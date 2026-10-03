<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>بطاقات تعريف الأصناف</h3>
  <form method="get" action="/inventory/item-cards" class="form-inline">
    <div class="form-row"><label>بحث</label><input name="search" value="<?= Helpers::e($search ?? '') ?>" placeholder="اسم، باركود، أو رقم البطاقة"></div>
    <button class="btn btn-secondary">بحث</button>
    <a href="/inventory/item-cards" class="btn btn-sm">إعادة ضبط</a>
  </form>
</div>

<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px">
    <h3>قائمة الأصناف</h3>
    <div class="form-inline" style="gap:8px">
      <a href="/inventory/item-cards/export?format=excel&search=<?= urlencode($search ?? '') ?>" class="btn btn-primary">تصدير Excel</a>
      <a href="/inventory/item-cards/export?format=pdf&search=<?= urlencode($search ?? '') ?>" class="btn btn-secondary">تصدير PDF</a>
    </div>
  </div>
  <table>
    <tr>
      <th>رقم البطاقة</th>
      <th>الباركود</th>
      <th>اسم الصنف</th>
      <th>التصنيف</th>
      <th>المستودع الرئيسي</th>
      <th>مطبخ التحضير</th>
      <th>مدة التحضير</th>
      <th>إجراء</th>
    </tr>
    <?php foreach ($items as $it): ?>
    <tr>
      <td><?= Helpers::e($it['card_no'] ?? '-') ?></td>
      <td><?= Helpers::e($it['barcode'] ?? '-') ?></td>
      <td><?= Helpers::e($it['name']) ?></td>
      <td><?= Helpers::e($it['category_name']) ?></td>
      <td><?= Helpers::e($it['warehouse_name'] ?? '-') ?></td>
      <td><?= Helpers::e($it['kitchen_name'] ?? '-') ?></td>
      <td><?= (int) ($it['prep_time_minutes'] ?? 0) ?> دقيقة</td>
      <td>
        <a href="/inventory/item-cards/<?= (int)$it['id'] ?>" class="btn btn-sm">عرض</a>
        <?php if (\App\Core\Auth::can('inventory.manage')): ?>
        <a href="/inventory/item-cards/<?= (int)$it['id'] ?>/edit" class="btn btn-sm btn-success">تعديل</a>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?>
    <tr><td colspan="8">لا توجد أصناف</td></tr>
    <?php endif; ?>
  </table>
</div>
