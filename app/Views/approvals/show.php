<?php use App\Core\Helpers; ?>
<?php $entityLabels = ['purchase_request'=>'طلب شراء','leave_request'=>'إجازة','expense'=>'مصروف',
      'salary_advance'=>'سلفة','salary_adjustment'=>'تعديل راتب','stock_transfer'=>'مناقلة مخزنية','quotation'=>'عرض سعر']; ?>
<?php $statusLabels = ['pending'=>'معلق','approved'=>'موافَق','rejected'=>'مرفوض']; ?>
<div class="card">
  <h3>طلب موافقة #<?= (int)$request['id'] ?></h3>
  <table>
    <tr><th>سير العمل</th><td><?= Helpers::e($request['workflow_name'] ?? '-') ?></td></tr>
    <tr><th>النوع</th><td><?= $entityLabels[$request['entity_type']] ?? $request['entity_type'] ?> #<?= (int)$request['entity_id'] ?></td></tr>
    <tr><th>المبلغ</th><td><?= Helpers::money($request['amount']) ?></td></tr>
    <tr><th>الحالة</th><td><span class="badge badge-<?= $request['status'] === 'approved' ? 'success' : ($request['status'] === 'rejected' ? 'danger' : 'info') ?>"><?= $statusLabels[$request['status']] ?? $request['status'] ?></span></td></tr>
    <tr><th>المستوى الحالي</th><td><?= (int)$request['current_step'] ?></td></tr>
    <tr><th>مقدم الطلب</th><td><?= Helpers::e($request['requester'] ?? '-') ?></td></tr>
    <tr><th>تاريخ التقديم</th><td><?= date('Y/m/d H:i', strtotime($request['created_at'])) ?></td></tr>
  </table>
</div>

<div class="card">
  <h3>مستويات سير العمل</h3>
  <table>
    <tr><th>المستوى</th><th>الدور المسؤول</th><th>المبلغ الأدنى</th></tr>
    <?php foreach ($steps as $s): ?>
    <tr class="<?= (int)$s['step_order'] === (int)$request['current_step'] && $request['status'] === 'pending' ? 'highlight' : '' ?>">
      <td><?= (int)$s['step_order'] ?></td>
      <td><?= Helpers::e($s['role_name'] ?? '-') ?></td>
      <td><?= Helpers::money($s['min_amount']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$steps): ?><tr><td colspan="3">لا توجد مستويات محددة</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h3>سجل القرارات</h3>
  <table>
    <tr><th>المستوى</th><th>القرار</th><th>بواسطة</th><th>ملاحظات</th><th>التاريخ</th></tr>
    <?php foreach ($actions as $a): ?>
    <tr>
      <td><?= (int)$a['step_order'] ?></td>
      <td><span class="badge badge-<?= $a['decision'] === 'approved' ? 'success' : 'danger' ?>"><?= $a['decision'] === 'approved' ? 'موافقة' : 'رفض' ?></span></td>
      <td><?= Helpers::e($a['decider'] ?? '-') ?></td>
      <td><?= Helpers::e($a['notes'] ?: '-') ?></td>
      <td><?= date('Y/m/d H:i', strtotime($a['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$actions): ?><tr><td colspan="5">لم يُتخذ أي قرار بعد</td></tr><?php endif; ?>
  </table>
</div>

<?php $canDecideNow = $request['status'] === 'pending' && App\Core\ApprovalService::canDecide($request); ?>
<?php if ($canDecideNow): ?>
<div class="card">
  <h3>اتخاذ قرار</h3>
  <form method="post" action="/approvals/<?= (int)$request['id'] ?>/decide">
    <div class="form-group">
      <label>ملاحظات</label>
      <input type="text" name="notes" class="form-input" placeholder="ملاحظات (اختياري)">
    </div>
    <div class="toolbar" style="margin-top:12px">
      <button type="submit" name="decision" value="approved" class="btn btn-success"><i class="fa-solid fa-check"></i> موافقة</button>
      <button type="submit" name="decision" value="rejected" class="btn btn-danger"><i class="fa-solid fa-xmark"></i> رفض</button>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="toolbar">
  <a href="/approvals" class="btn btn-secondary">← العودة إلى الموافقات</a>
</div>
