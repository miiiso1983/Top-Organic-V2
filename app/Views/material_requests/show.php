<?php use App\Core\{Auth, Helpers}; ?>
<style>
.mr-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:18px; margin-bottom:16px; }
.mr-card h3 { margin-top:0; }
.mr-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
.mr-label { color:#6b7280; font-size:12px; }
.mr-value { font-weight:600; }
.mr-table { width:100%; border-collapse:collapse; margin-top:10px; }
.mr-table th, .mr-table td { border:1px solid #e5e7eb; padding:8px; text-align:right; }
.mr-table th { background:#f3f4f6; }
.badge { display:inline-block; padding:4px 8px; border-radius:6px; font-size:12px; }
.badge-approved { background:#d1fae5; color:#065f46; }
.badge-fulfilled { background:#dcfce7; color:#166534; }
.badge-partially { background:#fef3c7; color:#92400e; }
.badge-rejected, .badge-cancelled { background:#fee2e2; color:#991b1b; }
.badge-submitted { background:#dbeafe; color:#1e40af; }
.badge-draft { background:#e5e7eb; color:#374151; }
</style>

<h2>طلب مواد <?= Helpers::e($mr['request_no']) ?></h2>

<div class="mr-card">
  <div class="mr-grid">
    <div><div class="mr-label">الفرع</div><div class="mr-value"><?= Helpers::e($mr['branch_name'] ?? '-') ?></div></div>
    <div><div class="mr-label">القسم</div><div class="mr-value"><?= Helpers::e($mr['department_name'] ?? '-') ?></div></div>
    <div><div class="mr-label">المقدم</div><div class="mr-value"><?= Helpers::e($mr['requester_name'] ?? '-') ?></div></div>
    <div><div class="mr-label">التاريخ</div><div class="mr-value"><?= $mr['request_date'] ?></div></div>
    <div><div class="mr-label">الأولوية</div><div class="mr-value"><?= Helpers::e($mr['priority']) ?></div></div>
    <div><div class="mr-label">الحالة</div><div class="mr-value"><span class="badge badge-<?= $mr['status'] ?>"><?= $mr['status'] ?></span></div></div>
    <div><div class="mr-label">التكلفة المقدرة</div><div class="mr-value"><?= number_format((float)$mr['total_estimated_cost'], 2) ?> <?= Helpers::e($mr['currency_code']) ?></div></div>
  </div>
  <?php if ($mr['reason']): ?><div style="margin-top:10px"><span class="mr-label">السبب:</span> <?= Helpers::e($mr['reason']) ?></div><?php endif; ?>
  <?php if ($mr['notes']): ?><div><span class="mr-label">ملاحظات:</span> <?= Helpers::e($mr['notes']) ?></div><?php endif; ?>
</div>

<div class="mr-card">
  <h3>البنود</h3>
  <table class="mr-table">
    <tr><th>المادة</th><th>النوع</th><th>التصنيف</th><th>الكمية المطلوبة</th><th>الكمية المعتمدة</th><th>الكمية المُلباة</th><th>الوحدة</th><th>المخزن</th><th>الحالة</th><th>الغرض</th></tr>
    <?php foreach ($items as $it): ?>
    <tr>
      <td><?= Helpers::e($it['item_name']) ?></td>
      <td><?= (int)($it['is_new_item'] ?? 0) === 1 ? 'جديدة' : 'موجودة' ?></td>
      <td><?= Helpers::e($it['category_name'] ?? '-') ?> (<?= Helpers::e($it['category_type'] ?? '-') ?>)</td>
      <td><?= number_format((float)$it['requested_qty'], 2) ?></td>
      <td><?= $it['approved_qty'] ? number_format((float)$it['approved_qty'], 2) : '-' ?></td>
      <td><?= number_format((float)$it['fulfilled_qty'], 2) ?></td>
      <td><?= Helpers::e($it['unit_name'] ?? '-') ?></td>
      <td><?= Helpers::e($it['warehouse_name'] ?? '-') ?></td>
      <td><?= Helpers::e($it['status']) ?></td>
      <td><?= Helpers::e($it['purpose'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if ($purchaseRequests || $purchaseRequest): ?>
<div class="mr-card">
  <h3>طلبات الشراء المرتبطة</h3>
  <table class="mr-table">
    <tr><th>رقم الطلب</th><th>الحالة</th><th>الملاحظات</th></tr>
    <?php foreach ($purchaseRequests as $pr): ?>
    <tr>
      <td><a href="/purchasing/requests"><?= Helpers::e($pr['request_no']) ?></a></td>
      <td><?= Helpers::e($pr['status']) ?></td>
      <td><?= Helpers::e($pr['notes'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php if ($transfers): ?>
<div class="mr-card">
  <h3>مناقلات المخزون المرتبطة</h3>
  <table class="mr-table">
    <tr><th>رقم المناقلة</th><th>من</th><th>إلى</th><th>الحالة</th><th>المستلم</th><th>إجراء</th></tr>
    <?php foreach ($transfers as $t): ?>
    <tr>
      <td><?= Helpers::e($t['transfer_no']) ?></td>
      <td><?= Helpers::e($t['from_warehouse'] ?? '-') ?></td>
      <td><?= Helpers::e($t['to_warehouse'] ?? '-') ?></td>
      <td><?= Helpers::e($t['status']) ?></td>
      <td><?= Helpers::e($t['recipient_name'] ?? '-') ?></td>
      <td>
        <?php if (in_array($t['status'], ['approved','prepared'], true) && Auth::can('material_request.fulfill')): ?>
        <a href="/material-requests/transfer/<?= $t['id'] ?>/signature" class="btn btn-sm btn-success">تسليم</a>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<div class="mr-card">
  <h3>التلبية</h3>
  <?php if ($fulfillment): ?>
  <table class="mr-table">
    <tr><th>النوع</th><th>الوثيقة</th><th>الكمية</th><th>من مخزن</th><th>إلى مخزن</th><th>التاريخ</th></tr>
    <?php foreach ($fulfillment as $f): ?>
    <tr>
      <td><?= $f['fulfillment_type'] ?></td>
      <td><?= $f['document_no'] ? Helpers::e($f['document_no']) : ('#' . $f['document_id']) ?></td>
      <td><?= number_format((float)$f['qty'], 2) ?></td>
      <td><?= Helpers::e($f['from_warehouse'] ?? '-') ?></td>
      <td><?= Helpers::e($f['to_warehouse'] ?? '-') ?></td>
      <td><?= $f['created_at'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <p>لا توجد عمليات تلبية بعد.</p>
  <?php endif; ?>
</div>

<div class="mr-card">
  <h3>سير الموافقات</h3>
  <?php if ($workflow): ?>
  <p>رقم الموافقة: <a href="/workflows/requests/<?= $workflow['id'] ?>"><?= Helpers::e($workflow['request_no']) ?></a> — الحالة: <?= Helpers::e($workflow['status']) ?></p>
  <table class="mr-table">
    <tr><th>الخطوة</th><th>الإجراء</th><th>المُنفذ</th><th>الملاحظات</th><th>التاريخ</th></tr>
    <?php foreach ($history as $h): ?>
    <tr><td><?= Helpers::e($h['step_title'] ?? '-') ?></td><td><?= Helpers::e($h['action']) ?></td><td><?= Helpers::e($h['actor_name'] ?? '-') ?></td><td><?= Helpers::e($h['notes'] ?? '-') ?></td><td><?= $h['created_at'] ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <p>لم يُرسل الطلب بعد.</p>
  <?php endif; ?>
</div>

<div class="mr-card">
  <h3>سجل التدقيق</h3>
  <table class="mr-table">
    <tr><th>الإجراء</th><th>المستخدم</th><th>القيم القديمة</th><th>القيم الجديدة</th><th>التاريخ</th></tr>
    <?php foreach ($audit as $a): ?>
    <tr><td><?= Helpers::e($a['action']) ?></td><td><?= Helpers::e($a['user_id'] ?? 'نظام') ?></td><td><?= Helpers::e($a['old_values'] ?? '-') ?></td><td><?= Helpers::e($a['new_values'] ?? '-') ?></td><td><?= $a['created_at'] ?></td></tr>
    <?php endforeach; ?>
  </table>
</div>

<div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px">
  <?php if ($mr['status'] === 'draft' && Auth::can('material_request.manage')): ?>
  <a href="/material-requests/<?= $mr['id'] ?>/edit" class="btn btn-secondary">تعديل</a>
  <form method="POST" action="/material-requests/<?= $mr['id'] ?>/submit" style="display:inline"><button type="submit" class="btn btn-primary">إرسال للموافقة</button></form>
  <?php endif; ?>
  <?php if (in_array($mr['status'], ['draft','submitted','pending','approved','returned'], true) && Auth::can('material_request.manage')): ?>
  <form method="POST" action="/material-requests/<?= $mr['id'] ?>/cancel" style="display:inline" onsubmit="return confirm('تأكيد الإلغاء؟')"><button type="submit" class="btn btn-danger">إلغاء</button></form>
  <?php endif; ?>
  <?php if ($mr['status'] === 'approved' && Auth::can('material_request.fulfill')): ?>
  <a href="/material-requests/<?= $mr['id'] ?>/fulfillment" class="btn btn-success">تلبية الطلب</a>
  <?php endif; ?>
  <a href="/material-requests" class="btn btn-light">العودة</a>
</div>
