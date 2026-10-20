<?php use App\Core\Helpers, App\Core\Auth; ?>
<style>
.disb-card { margin-bottom: 14px; }
.disb-row { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px; }
.disb-info table { width: auto; }
.disb-info td { padding: 3px 10px 3px 0; border: none; }
.disb-info td:first-child { color: var(--text-muted); font-size: 13px; }
.disb-form { min-width: 280px; }
</style>

<div class="page-head">
  <h2><i class="fa-solid fa-hand-holding-dollar"></i> تسليم السلف</h2>
  <a href="/accounting/advances" class="btn btn-secondary">عرض كل السلف ومسار موافقاتها</a>
</div>

<div class="alert alert-info">
  <i class="fa-solid fa-circle-info"></i>
  تظهر هنا طلبات السلف التي أكملت جميع مراحل الموافقة المطلوبة ولم تُسلَّم بعد. عند الضغط على "تسليم السلفة"
  يُنشأ قيد محاسبي تلقائياً وتُرسل إشعار فوري لمقدم الطلب بنجاح التسليم.
</div>

<?php if (!$advances): ?>
<div class="card">
  <p style="text-align:center;color:var(--text-muted);padding:30px 0">لا توجد سلف معتمدة بانتظار التسليم حالياً.</p>
</div>
<?php else: ?>
<?php foreach ($advances as $a): ?>
<div class="card disb-card">
  <div class="disb-row">
    <div class="disb-info">
      <h3 style="margin:0 0 8px"><?= Helpers::e($a['full_name']) ?> <span class="badge badge-success">معتمدة</span></h3>
      <table>
        <tr><td>القسم</td><td><?= Helpers::e($a['department'] ?? '-') ?></td></tr>
        <tr><td>المبلغ</td><td><strong><?= Helpers::money($a['amount']) ?></strong></td></tr>
        <tr><td>السبب</td><td><?= Helpers::e($a['reason'] ?: '-') ?></td></tr>
        <tr><td>مقدّم الطلب</td><td><?= Helpers::e($a['requested_by_name'] ?? $a['full_name']) ?></td></tr>
        <tr><td>تاريخ الاعتماد</td><td><?= $a['approved_at'] ? date('Y-m-d H:i', strtotime($a['approved_at'])) : '-' ?></td></tr>
      </table>
      <a href="/accounting/advances/<?= $a['id'] ?>" style="font-size:13px">عرض تفاصيل الطلب ومسار الموافقات</a>
    </div>

    <?php if (Auth::can('payroll.manage') || Auth::can('accounting.manage')): ?>
    <form method="post" action="/accounting/advances/<?= $a['id'] ?>/pay" class="disb-form" onsubmit="return confirm('تأكيد تسليم السلفة؟ سيُنشأ قيد محاسبي تلقائياً وتُرسل إشعار للموظف.')">
      <input type="hidden" name="redirect_to" value="/accounting/advances/disbursement">
      <div class="form-inline" style="gap:10px; margin-bottom:10px">
        <div class="form-row">
          <label>طريقة التسليم</label>
          <select name="payment_method" id="payment-method-<?= $a['id'] ?>" onchange="toggleDisbMethod(<?= $a['id'] ?>)">
            <option value="cash">نقد (صندوق)</option>
            <option value="bank">بنك</option>
          </select>
        </div>
        <div class="form-row" id="cash-row-<?= $a['id'] ?>">
          <label>الصندوق</label>
          <select name="cash_register_id">
            <?php foreach ($cashRegisters as $r): ?>
            <option value="<?= (int)$r['id'] ?>"><?= Helpers::e($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row" id="bank-row-<?= $a['id'] ?>" style="display:none">
          <label>الحساب البنكي</label>
          <select name="bank_account_id">
            <option value="">— اختر —</option>
            <?php foreach ($bankAccounts as $b): ?>
            <option value="<?= (int)$b['id'] ?>"><?= Helpers::e($b['name']) ?> (<?= Helpers::e($b['bank_name']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <button class="btn btn-success" style="width:100%"><i class="fa-solid fa-hand-holding-dollar"></i> تسليم السلفة</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<script>
function toggleDisbMethod(id) {
  var method = document.getElementById('payment-method-' + id).value;
  document.getElementById('cash-row-' + id).style.display = method === 'cash' ? '' : 'none';
  document.getElementById('bank-row-' + id).style.display = method === 'bank' ? '' : 'none';
}
</script>
