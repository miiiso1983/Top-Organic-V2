<?php use App\Core\Helpers; ?>
<?php $partyLabels = ['supplier'=>'مورد','customer'=>'عميل','employee'=>'موظف','other'=>'أخرى'];
      $methodLabels = ['cash'=>'نقدي','card'=>'بطاقة','transfer'=>'حوالة']; ?>
<div class="card">
  <h3>سند قبض / صرف (ذمم الموردين والعملاء)</h3>
  <form method="post" action="/finance/payments" class="form-inline">
    <div class="form-row"><label>نوع الطرف</label>
      <select name="party_type" id="party-type">
        <option value="supplier">مورد</option>
        <option value="customer">عميل</option>
        <option value="employee">موظف</option>
        <option value="other">أخرى</option>
      </select>
    </div>
    <div class="form-row"><label>الطرف</label>
      <select name="party_id" id="party-id">
        <optgroup label="الموردون" data-type="supplier">
          <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>">مورد: <?= Helpers::e($s['name']) ?> (<?= Helpers::money($s['balance']) ?>)</option><?php endforeach; ?>
        </optgroup>
        <optgroup label="العملاء" data-type="customer">
          <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>">عميل: <?= Helpers::e($c['name']) ?> (<?= Helpers::money($c['balance']) ?>)</option><?php endforeach; ?>
        </optgroup>
        <optgroup label="الموظفون" data-type="employee">
          <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>">موظف: <?= Helpers::e($e['full_name']) ?></option><?php endforeach; ?>
        </optgroup>
      </select>
    </div>
    <div class="form-row"><label>الاتجاه</label>
      <select name="direction"><option value="out">صرف (دفع)</option><option value="in">قبض (تحصيل)</option></select>
    </div>
    <div class="form-row"><label>المبلغ</label><input type="number" name="amount" step="0.01" min="0.01" required></div>
    <div class="form-row"><label>الطريقة</label>
      <select name="method"><option value="cash">نقدي</option><option value="card">بطاقة</option><option value="transfer">حوالة</option></select>
    </div>
    <div class="form-row"><label>المرجع</label><input name="reference" placeholder="رقم أمر شراء/فاتورة"></div>
    <div class="form-row"><label>البيان</label><input name="description"></div>
    <button class="btn btn-success">تسجيل السند</button>
  </form>
</div>

<div class="card">
  <h3>سجل السندات</h3>
  <table>
    <tr><th>الرقم</th><th>الطرف</th><th>الاتجاه</th><th>المبلغ</th><th>الطريقة</th><th>المرجع</th><th>بواسطة</th><th>الوقت</th></tr>
    <?php foreach ($payments as $p): ?>
    <tr>
      <td><?= Helpers::e($p['payment_no']) ?></td>
      <td><?= $partyLabels[$p['party_type']] ?? $p['party_type'] ?> #<?= $p['party_id'] ?? '-' ?></td>
      <td><span class="badge badge-<?= $p['direction'] === 'in' ? 'success' : 'danger' ?>"><?= $p['direction'] === 'in' ? 'قبض' : 'صرف' ?></span></td>
      <td><?= Helpers::money($p['amount']) ?></td>
      <td><?= $methodLabels[$p['method']] ?? $p['method'] ?></td>
      <td><?= Helpers::e($p['reference'] ?? '-') ?></td>
      <td><?= Helpers::e($p['full_name'] ?? '-') ?></td>
      <td><?= date('m/d H:i', strtotime($p['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$payments): ?><tr><td colspan="8">لا توجد سندات</td></tr><?php endif; ?>
  </table>
</div>
