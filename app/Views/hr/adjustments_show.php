<?php use App\Core\Helpers; ?>
<?php $typeLabels = ['bonus'=>'مكافأة','deduction'=>'خصم','raise'=>'زيادة','penalty'=>'جزاء','tips_addition'=>'إضافي تيبس','salary_reduction'=>'تخفيض راتب','work_days_settlement'=>'تسوية أيام دوام']; ?>
<?php $statusLabels = ['pending'=>'معلق','approved'=>'معتمد','rejected'=>'مرفوض','cancelled'=>'ملغاة']; ?>

<div class="card">
  <h3>تفاصيل التسوية المالية</h3>
  <table class="detail-table">
    <tr><td>الموظف</td><td><?= Helpers::e($adjustment['full_name']) ?> (<?= Helpers::e($adjustment['emp_code'] ?? '-') ?>)</td></tr>
    <tr><td>القسم</td><td><?= Helpers::e($adjustment['department'] ?? '-') ?></td></tr>
    <tr><td>النوع</td><td><?= $typeLabels[$adjustment['adjustment_type']] ?? $adjustment['adjustment_type'] ?></td></tr>
    <tr><td>المبلغ</td><td><?= Helpers::money($adjustment['amount']) ?></td></tr>
    <?php if (isset($adjustment['days']) && $adjustment['days'] !== null): ?>
    <tr><td>عدد الأيام</td><td><?= Helpers::e($adjustment['days']) ?></td></tr>
    <?php endif; ?>
    <tr><td>الفترة</td><td><?= Helpers::e($adjustment['period'] ?? '-') ?></td></tr>
    <tr><td>تاريخ التطبيق</td><td><?= Helpers::e($adjustment['effective_date']) ?></td></tr>
    <tr><td>السبب</td><td><?= Helpers::e($adjustment['reason'] ?? '-') ?></td></tr>
    <tr><td>الحالة</td><td><span class="badge badge-<?= $adjustment['status'] === 'approved' ? 'success' : ($adjustment['status'] === 'rejected' ? 'danger' : ($adjustment['status'] === 'cancelled' ? 'secondary' : 'warning')) ?>"><?= $statusLabels[$adjustment['status']] ?></span></td></tr>
    <tr><td>Workflow</td><td><?= $workflowRequestId ? '<a href="/workflows/requests/' . $workflowRequestId . '">عرض مسار الموافقات</a>' : 'غير مرتبط' ?></td></tr>
    <tr><td>مقدم الطلب</td><td><?= Helpers::e($adjustment['requested_name'] ?? '-') ?></td></tr>
    <tr><td>المعتمد</td><td><?= Helpers::e($adjustment['approved_name'] ?? '-') ?> <?= $adjustment['approved_at'] ? '— ' . date('Y-m-d H:i', strtotime($adjustment['approved_at'])) : '' ?></td></tr>
    <?php if ($adjustment['status'] === 'cancelled'): ?>
    <tr><td>ألغيت بواسطة</td><td><?= Helpers::e($adjustment['cancelled_name'] ?? '-') ?> <?= !empty($adjustment['cancelled_at']) ? '— ' . date('Y-m-d H:i', strtotime($adjustment['cancelled_at'])) : '' ?></td></tr>
    <tr><td>سبب الإلغاء</td><td><?= Helpers::e($adjustment['cancel_reason'] ?? '-') ?></td></tr>
    <?php endif; ?>
  </table>
  <?php if ($adjustment['status'] === 'approved' && \App\Core\Auth::can('hr.adjustments.manage')): ?>
  <button class="btn btn-danger" style="margin-top:15px" onclick="cancelAdjustment(<?= $adjustment['id'] ?>)">إلغاء التسوية</button>
  <?php endif; ?>
  <a href="/hr/adjustments" class="btn btn-light" style="margin-top:15px">العودة للقائمة</a>
</div>
<script>
function cancelAdjustment(id){
  var reason = prompt('الرجاء إدخال سبب إلغاء هذه التسوية:');
  if (reason === null) return;
  if (!reason.trim()) { alert('سبب الإلغاء مطلوب'); return; }
  fetch('/hr/adjustments/'+id+'/cancel', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'reason='+encodeURIComponent(reason)
  }).then(r=>r.json()).then(d=>{
    if(d.ok) location.reload(); else alert(d.error || 'خطأ');
  }).catch(e=>alert(e.message));
}
</script>

<?php if ($history): ?>
<div class="card">
  <h3>سجل موافقات Workflow</h3>
  <table>
    <tr><th>التاريخ</th><th>الإجراء</th><th>المرحلة</th><th>ملاحظات</th><th>بواسطة</th></tr>
    <?php foreach ($history as $h): ?>
    <tr>
      <td><?= $h['created_at'] ? date('Y-m-d H:i', strtotime($h['created_at'])) : '-' ?></td>
      <td><span class="badge badge-<?= $h['action'] === 'approved' ? 'success' : ($h['action'] === 'rejected' ? 'danger' : ($h['action'] === 'returned_for_edit' ? 'warning' : 'info')) ?>"><?= $h['action'] ?></span></td>
      <td><?= Helpers::e($h['step_title'] ?? '') ?> <?= $h['step_order'] ? "(#{$h['step_order']})" : '' ?></td>
      <td><?= Helpers::e($h['notes'] ?? '-') ?></td>
      <td><?= Helpers::e($h['actor_name'] ?? $h['actor_real_name'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>
