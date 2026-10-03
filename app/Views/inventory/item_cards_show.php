<?php use App\Core\Helpers;

$typeLabels = [
    'raw'      => 'خامة',
    'prepared' => 'محضر',
    'finished' => 'جاهز',
    'packaging'=> 'تعبئة',
    'service'  => 'خدمة',
];
$catTypeLabels = [
    'raw'      => 'مادة أولية',
    'prepared' => 'محضر',
    'asset'    => 'موجود',
    'tool'     => 'أداة',
    'clothing' => 'ملابس',
    'other'    => 'أخرى',
];
$custodyLabels = [
    'in_stock'   => 'في المخزن',
    'in_custody' => 'في عهدة',
    'disposed'   => 'مستهلك',
];

$activeClass = ($item['is_active'] ?? 1) ? 'badge-success' : 'badge-danger';
$activeText  = ($item['is_active'] ?? 1) ? 'نشط' : 'معطل';
$typeText    = $typeLabels[$item['item_type'] ?? ''] ?? ($item['item_type'] ?? '-');
$catTypeText = $catTypeLabels[$item['category_type'] ?? ''] ?? ($item['category_type'] ?? '-');
$custodyStatus = $item['custody_status'] ?? 'in_stock';
$custodyText   = $custodyLabels[$custodyStatus] ?? $custodyStatus;
$custodyClass  = ($custodyStatus === 'in_stock') ? 'badge-success' : (($custodyStatus === 'in_custody') ? 'badge-warning' : 'badge-danger');
$trackExpiry   = !empty($item['track_expiry']);
$expiryText    = $trackExpiry ? 'يتطلب تتبع الصلاحية' : 'لا يتطلب تتبع الصلاحية';
$expiryClass   = $trackExpiry ? 'badge-warning' : 'badge-info';
?>
<style>
.item-card-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
.item-card-title { font-size: 24px; font-weight: 700; margin-bottom: 4px; }
.item-card-subtitle { font-size: 14px; color: #6b7280; }
.item-card-badges { display: flex; gap: 8px; flex-wrap: wrap; }
.badge { padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.badge-success { background: #dcfce7; color: #166534; }
.badge-danger { background: #fee2e2; color: #991b1b; }
.badge-info { background: #e0f2fe; color: #075985; }
.badge-warning { background: #fef3c7; color: #92400e; }
.item-card-section { margin-bottom: 24px; }
.item-card-section h4 { font-size: 16px; color: #374151; margin-bottom: 14px; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; }
.item-card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
.info-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
.info-row:last-child { border-bottom: none; }
.info-row .label { color: #6b7280; font-size: 13px; }
.info-row .value { font-weight: 600; color: #111827; font-size: 14px; text-align: left; }
.movements-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.movements-table th, .movements-table td { padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: right; }
.movements-table th { background: #f9fafb; color: #374151; }
</style>

<div class="card">
  <div class="item-card-header">
    <div>
      <div class="item-card-title"><?= Helpers::e($item['name']) ?></div>
      <div class="item-card-subtitle">
        <span>الكود: <?= Helpers::e($item['code'] ?? '-') ?></span>
        <span> | </span>
        <span>الاسم الإنجليزي: <?= Helpers::e($item['name_en'] ?? '-') ?></span>
        <span> | </span>
        <span>رقم البطاقة: <?= Helpers::e($item['card_no'] ?? '-') ?></span>
      </div>
    </div>
    <div class="item-card-badges">
      <span class="badge <?= $activeClass ?>"><?= $activeText ?></span>
      <span class="badge badge-info"><?= $typeText ?></span>
      <span class="badge badge-info"><?= $catTypeText ?></span>
      <span class="badge <?= $custodyClass ?>"><?= $custodyText ?></span>
      <span class="badge <?= $expiryClass ?>"><?= $expiryText ?></span>
    </div>
  </div>
  <div style="margin-top: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div><strong>الباركود:</strong> <?= Helpers::e($item['barcode'] ?? '-') ?></div>
    <div>
      <?php if (\App\Core\Auth::can('inventory.manage')): ?>
      <a href="/inventory/item-cards/<?= (int)$item['id'] ?>/edit" class="btn btn-success">تعديل البطاقة</a>
      <?php endif; ?>
      <a href="/inventory/cost-sheets/<?= (int)$item['id'] ?>" class="btn btn-primary">قالب تكلفة</a>
      <a href="/inventory/item-cards/<?= (int)$item['id'] ?>/export-pdf" class="btn btn-secondary">تصدير PDF</a>
      <a href="/inventory/item-cards" class="btn btn-sm">عودة</a>
    </div>
  </div>
</div>

<div class="card">
  <div class="item-card-section">
    <h4>البيانات الأساسية</h4>
    <div class="item-card-grid">
      <div class="info-row"><span class="label">الكود</span><span class="value"><?= Helpers::e($item['code'] ?? '-') ?></span></div>
      <div class="info-row"><span class="label">الاسم الإنجليزي</span><span class="value"><?= Helpers::e($item['name_en'] ?? '-') ?></span></div>
      <div class="info-row"><span class="label">التصنيف</span><span class="value"><?= Helpers::e($item['category_name']) ?> (<?= $catTypeText ?>)</span></div>
      <div class="info-row"><span class="label">نوع الصنف</span><span class="value"><?= $typeText ?></span></div>
      <div class="info-row"><span class="label">الوحدة الأساسية</span><span class="value"><?= Helpers::e($item['unit_name']) ?></span></div>
      <div class="info-row"><span class="label">وحدة الشراء</span><span class="value"><?= Helpers::e($item['purchase_unit_name'] ?? '-') ?></span></div>
      <div class="info-row"><span class="label">وحدة الوصفة</span><span class="value"><?= Helpers::e($item['recipe_unit_name'] ?? '-') ?></span></div>
      <div class="info-row"><span class="label">معامل التحويل</span><span class="value"><?= (float)($item['conversion_factor'] ?? 1) ?></span></div>
      <div class="info-row"><span class="label">حد التنبيه</span><span class="value"><?= (float)($item['min_qty'] ?? 0) ?></span></div>
      <div class="info-row"><span class="label">سعر الكلفة</span><span class="value"><?= Helpers::money($item['cost_price'] ?? 0) ?></span></div>
      <div class="info-row"><span class="label">الكلفة المعيارية</span><span class="value"><?= Helpers::money($item['standard_cost'] ?? 0) ?></span></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="item-card-section">
    <h4>المخزون والتشغيل</h4>
    <div class="item-card-grid">
      <div class="info-row"><span class="label">المستودع الرئيسي</span><span class="value"><?= Helpers::e($item['warehouse_name'] ?? '-') ?></span></div>
      <div class="info-row"><span class="label">قسم المطبخ</span><span class="value"><?= Helpers::e($item['kitchen_name'] ?? '-') ?></span></div>
      <div class="info-row"><span class="label">مدة التحضير</span><span class="value"><?= (int)($item['prep_time_minutes'] ?? 0) ?> دقيقة</span></div>
      <div class="info-row"><span class="label">الكمية الإجمالية</span><span class="value"><?= number_format((float)($item['total_qty'] ?? 0), 2) ?> <?= Helpers::e($item['unit_name']) ?></span></div>
      <div class="info-row"><span class="label">تتبع الصلاحية</span><span class="value"><?= $expiryText ?></span></div>
      <div class="info-row"><span class="label">حالة العهدة</span><span class="value"><?= $custodyText ?></span></div>
      <div class="info-row"><span class="label">تاريخ الإنشاء</span><span class="value"><?= Helpers::e($item['created_at'] ?? '-') ?></span></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="item-card-section">
    <h4>الوصف والمواصفات</h4>
    <p><?= nl2br(Helpers::e($item['description'] ?? 'لا يوجد وصف'), false) ?></p>
  </div>
</div>

<div class="card">
  <div class="item-card-section">
    <h4>سجل حركة المخازن</h4>
    <?php if ($movements): ?>
    <table class="movements-table">
      <tr>
        <th>التاريخ</th>
        <th>المستودع</th>
        <th>نوع الحركة</th>
        <th>الكمية</th>
        <th>المرجع</th>
        <th>ملاحظات</th>
      </tr>
      <?php foreach ($movements as $m): ?>
      <tr>
        <td><?= Helpers::e($m['created_at'] ?? '-') ?></td>
        <td><?= Helpers::e($m['warehouse_name'] ?? '-') ?></td>
        <td><?= Helpers::e($m['movement_type'] ?? '-') ?></td>
        <td><?= number_format((float)($m['qty'] ?? 0), 2) ?></td>
        <td><?= Helpers::e($m['reference'] ?? '-') ?></td>
        <td><?= Helpers::e($m['notes'] ?? '-') ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>لا توجد حركات مخزون لهذا الصنف.</p>
    <?php endif; ?>
  </div>
</div>
