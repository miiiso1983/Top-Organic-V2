<?php use App\Core\Helpers; ?>
<div class="card">
  <div class="item-card-header">
    <div>
      <div class="item-card-title">الأصناف المحضرة</div>
      <div class="item-card-subtitle">إدارة قوالب التكلفة (BOM) والأصناف شبه المصنعة</div>
    </div>
    <?php if (\App\Core\Auth::can('inventory.manage')): ?>
    <div>
      <a href="/inventory/prepared-items/import" class="btn btn-success">⬆️ استيراد Excel</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <form method="get" action="/inventory/prepared-items" class="form-inline">
    <div class="form-row"><label>بحث</label><input name="search" value="<?= Helpers::e($search ?? '') ?>" placeholder="اسم الصنف أو الباركود"></div>
    <button class="btn btn-secondary">بحث</button>
    <a href="/inventory/prepared-items" class="btn btn-sm">إعادة ضبط</a>
  </form>
</div>

<div class="card">
  <h3>قائمة الأصناف المحضرة</h3>
  <table>
    <tr>
      <th>الصنف</th>
      <th>التصنيف</th>
      <th>الوحدة</th>
      <th>قالب التكلفة</th>
      <th>التكلفة/الوحدة</th>
      <th>سعر البيع</th>
      <th>إجراء</th>
    </tr>
    <?php foreach ($items as $it): ?>
    <tr>
      <td><strong><?= Helpers::e($it['name']) ?></strong></td>
      <td><?= Helpers::e($it['category_name']) ?></td>
      <td><?= Helpers::e($it['unit_name'] ?? '-') ?></td>
      <td>
        <?php if ($it['sheet_id']): ?>
        <span class="badge badge-success">مفعل</span>
        <?php else: ?>
        <span class="badge badge-info">غير مفعل</span>
        <?php endif; ?>
      </td>
      <td><?= Helpers::e(number_format($it['cost_per_unit'], 2)) ?></td>
      <td><?= Helpers::e(number_format($it['sale_price'], 2)) ?></td>
      <td>
        <a href="/inventory/prepared-items/<?= (int)$it['id'] ?>" class="btn btn-sm">عرض</a>
        <?php if ($it['sheet_id']): ?>
        <a href="/inventory/cost-sheets/<?= (int)$it['sheet_id'] ?>/edit" class="btn btn-sm btn-success">تعديل BOM</a>
        <?php endif; ?>
        <?php if (\App\Core\Auth::can('inventory.manage')): ?>
        <form method="post" action="/inventory/prepared-items/<?= (int)$it['id'] ?>/recalculate" style="display:inline;">
          <button class="btn btn-sm btn-warning"><i class="fa-solid fa-arrow-right-arrow-left"></i> إعادة حساب</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?>
    <tr><td colspan="7">لا توجد أصناف محضرة</td></tr>
    <?php endif; ?>
  </table>
</div>
