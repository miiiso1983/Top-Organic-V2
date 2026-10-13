<?php use App\Core\Helpers; ?>
<?php $statusLabels = ['pending'=>'بانتظار الموافقة','approved'=>'معتمدة','rejected'=>'مرفوضة','paid'=>'تم الصرف','settled'=>'مسددة']; ?>
<?php $typeLabel = $advance['advance_type'] === 'short' ? 'قصيرة الأجل' : 'طويلة الأجل'; ?>

<div class="card">
  <h3>تفاصيل سلفة: <?= Helpers::e($advance['full_name']) ?></h3>
  <table class="detail-table">
    <tr><td>الموظف</td><td><?= Helpers::e($advance['full_name']) ?> (<?= Helpers::e($advance['emp_code'] ?? '-') ?>)</td></tr>
    <tr><td>القسم</td><td><?= Helpers::e($advance['department'] ?? '-') ?></td></tr>
    <tr><td>المبلغ</td><td><?= Helpers::money($advance['amount']) ?></td></tr>
    <tr><td>النوع</td><td><?= $typeLabel ?></td></tr>
    <tr><td>عدد الأقساط</td><td><?= $advance['installments'] ?></td></tr>
    <?php if (!empty($advance['installment_amount'])): ?>
    <tr><td>طريقة احتساب القسط</td><td>
      <?= ($advance['installment_method'] ?? 'fixed') === 'percentage' ? 'نسبة ' . Helpers::e($advance['installment_percentage']) . '% من الراتب' : 'مبلغ ثابت' ?>
      — القسط الشهري: <?= Helpers::money($advance['installment_amount']) ?>
    </td></tr>
    <?php endif; ?>
    <tr><td>المسدد</td><td><?= Helpers::money($advance['paid_amount'] ?? 0) ?></td></tr>
    <tr><td>المتبقي</td><td><?= Helpers::money($advance['remaining_amount'] ?? 0) ?></td></tr>
    <tr><td>السبب</td><td><?= Helpers::e($advance['reason'] ?? '-') ?></td></tr>
    <tr><td>الحالة</td><td><span class="badge badge-<?= in_array($advance['status'],['approved','paid','settled']) ? 'success' : ($advance['status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= $statusLabels[$advance['status']] ?></span></td></tr>
    <tr><td>Workflow</td><td><?= $workflowRequestId ? '<a href="/workflows/requests/' . $workflowRequestId . '">عرض مسار الموافقات</a>' : 'غير مرتبط' ?></td></tr>
    <tr><td>المصدر</td><td><?= $advance['request_source'] === 'mobile' ? 'موبايل' : 'ويب' ?></td></tr>
    <?php if ($advance['approved_by_name']): ?>
    <tr><td>اعتمد بواسطة</td><td><?= Helpers::e($advance['approved_by_name']) ?> - <?= date('Y-m-d H:i', strtotime($advance['approved_at'])) ?></td></tr>
    <?php endif; ?>
    <?php if ($advance['entry_no']): ?>
    <tr><td>القيد المحاسبي</td><td><?= Helpers::e($advance['entry_no']) ?></td></tr>
    <?php endif; ?>
  </table>

  <div class="flex-between" style="margin-top:15px">
    <a href="/accounting/advances" class="btn btn-light">العودة للقائمة</a>
    <?php if ($advance['status'] === 'approved' && (float)($advance['paid_amount'] ?? 0) <= 0 && (\App\Core\Auth::can('payroll.manage') || \App\Core\Auth::can('accounting.manage'))): ?>
    <form method="post" action="/accounting/advances/<?= $advance['id'] ?>/pay" onsubmit="return confirm('تأكيد صرف السلفة؟')" id="disburse-form">
      <div class="form-inline" style="gap:10px; margin-bottom:10px">
        <div class="form-row">
          <label>طريقة الدفع</label>
          <select name="payment_method" id="payment-method" onchange="togglePaymentMethod()">
            <option value="cash">نقد (صندوق)</option>
            <option value="bank">بنك</option>
          </select>
        </div>
        <div class="form-row" id="cash-register-row">
          <label>الصندوق</label>
          <select name="cash_register_id">
            <?php foreach ($cashRegisters as $r): ?>
            <option value="<?= (int)$r['id'] ?>"><?= Helpers::e($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row" id="bank-account-row" style="display:none">
          <label>الحساب البنكي</label>
          <select name="bank_account_id">
            <option value="">— اختر —</option>
            <?php foreach ($bankAccounts as $b): ?>
            <option value="<?= (int)$b['id'] ?>"><?= Helpers::e($b['name']) ?> (<?= Helpers::e($b['bank_name']) ?>) - <?= Helpers::e($b['account_no']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <button class="btn btn-primary">صرف السلفة</button>
    </form>
    <?php endif; ?>
  </div>

<script>
function togglePaymentMethod() {
    var method = document.getElementById('payment-method').value;
    document.getElementById('cash-register-row').style.display = method === 'cash' ? '' : 'none';
    document.getElementById('bank-account-row').style.display = method === 'bank' ? '' : 'none';
}
</script>
</div>

<?php if ($schedule): ?>
<div class="card">
  <h3>جدول الأقساط</h3>
  <table>
    <tr><th>الفترة</th><th>المبلغ</th><th>الحالة</th><th>مسير الراتب</th><th>تاريخ السداد</th></tr>
    <?php foreach ($schedule as $s): ?>
    <tr>
      <td><?= Helpers::e($s['period']) ?></td>
      <td><?= Helpers::money($s['amount']) ?></td>
      <td><span class="badge badge-<?= $s['status'] === 'paid' ? 'success' : 'warning' ?>"><?= $s['status'] === 'paid' ? 'مسدد' : 'مستحق' ?></span></td>
      <td><?= Helpers::e($s['payroll_period'] ?? '-') ?></td>
      <td><?= $s['paid_at'] ? date('Y-m-d', strtotime($s['paid_at'])) : '-' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php if ($approvals): ?>
<div class="card">
  <h3>سجل الموافقات</h3>
  <table>
    <tr><th>التاريخ</th><th>الإجراء</th><th>المرحلة</th><th>ملاحظات</th><th>بواسطة</th></tr>
    <?php foreach ($approvals as $ap): ?>
    <tr>
      <td><?= $ap['created_at'] ? date('Y-m-d H:i', strtotime($ap['created_at'])) : '-' ?></td>
      <td><span class="badge badge-<?= $ap['action'] === 'approved' ? 'success' : ($ap['action'] === 'rejected' ? 'danger' : ($ap['action'] === 'returned_for_edit' ? 'warning' : 'info')) ?>"><?= $ap['action'] ?></span></td>
      <td><?= Helpers::e($ap['step_title'] ?? '') ?> <?= $ap['step_order'] ? "(#{$ap['step_order']})" : '' ?></td>
      <td><?= Helpers::e($ap['notes'] ?? '-') ?></td>
      <td><?= Helpers::e($ap['actor_name'] ?? $ap['actor_real_name'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php if ($canDecide): ?>
<div class="card" style="margin-top:16px">
  <h3><i class="fa-solid fa-pen-to-square"></i> اتخاذ قرار</h3>
  <form method="post" action="/accounting/advances/<?= $advance['id'] ?>/decide" class="form-inline">
    <input type="hidden" name="decision" value="approved">
    <div class="form-row"><label>ملاحظات</label><input name="notes" placeholder="ملاحظات الموافقة"></div>
    <button class="btn btn-success">موافقة</button>
  </form>
  <form method="post" action="/accounting/advances/<?= $advance['id'] ?>/decide" class="form-inline" style="margin-top:10px">
    <input type="hidden" name="decision" value="rejected">
    <div class="form-row"><label>ملاحظات</label><input name="notes" placeholder="ملاحظات الرفض"></div>
    <button class="btn btn-danger">رفض</button>
  </form>
</div>
<?php endif; ?>
