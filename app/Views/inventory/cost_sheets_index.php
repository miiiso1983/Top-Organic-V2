<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>قوالب تكلفة الأصناف</h3>
  <div class="form-row" style="margin-bottom:12px">
    <?php if (\App\Core\Auth::can('inventory.manage')): ?>
    <a href="/inventory/cost-sheets/import" class="btn btn-success"><i class="fa-solid fa-file-import"></i> استيراد Excel</a>
    <a href="/inventory/cost-sheets/template.xlsx" class="btn btn-secondary"><i class="fa-solid fa-file-import"></i> نموذج</a>
    <?php endif; ?>
  </div>
  <form method="get" action="/inventory/cost-sheets" class="form-inline">
    <div class="form-row"><label>بحث</label><input name="search" value="<?= Helpers::e($search ?? '') ?>" placeholder="اسم أو باركود الصنف"></div>
    <button class="btn btn-secondary">بحث</button>
    <a href="/inventory/cost-sheets" class="btn btn-sm">إعادة ضبط</a>
  </form>
</div>

<div class="card">
  <h3>القوالب الحالية</h3>
  <table>
    <tr><th>الصنف</th><th>الباركود</th><th>وحدة الناتج</th><th>الناتج</th><th>كلفة الوحدة</th><th>سعر البيع</th><th>الربح</th><th>نسبة تكلفة الغذاء</th><th>إجراء</th></tr>
    <?php foreach ($sheets as $s): ?>
    <tr>
      <td><?= Helpers::e($s['item_name']) ?></td>
      <td><?= Helpers::e($s['barcode'] ?? '-') ?></td>
      <td><?= Helpers::e($s['yield_unit_name'] ?? $s['item_unit_name'] ?? '-') ?></td>
      <td><?= (float)($s['yield_qty'] ?? 1) ?></td>
      <td><?= Helpers::money($s['cost_per_unit'] ?? 0) ?></td>
      <td><?= Helpers::money($s['sale_price'] ?? 0) ?></td>
      <td style="color:<?= ($s['profit'] ?? 0) >= 0 ? '#16a34a' : '#dc2626' ?>"><?= Helpers::money($s['profit'] ?? 0) ?></td>
      <td><?= number_format(($s['food_cost_pct'] ?? 0) * 100, 2) ?>%</td>
      <td>
        <a href="/inventory/cost-sheets/<?= (int)$s['item_id'] ?>" class="btn btn-sm">عرض</a>
        <?php if (\App\Core\Auth::can('inventory.manage')): ?>
        <a href="/inventory/cost-sheets/<?= (int)$s['item_id'] ?>/edit" class="btn btn-sm btn-success">تحرير</a>
        <form method="post" action="/inventory/cost-sheets/<?= (int)$s['id'] ?>/delete" style="display:inline" onsubmit="return confirm('هل أنت متأكد؟');">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$sheets): ?>
    <tr><td colspan="9">لا توجد قوالب تكلفة. ابدأ بإنشاء قالب من صفحة الأصناف.</td></tr>
    <?php endif; ?>
  </table>
</div>
