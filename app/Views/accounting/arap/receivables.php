<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h2>الحسابات المدينة (العملاء)</h2>
  <div class="grid grid-5" style="margin-top:12px">
    <?php foreach ([30, 60, 90, 120] as $i => $d): ?>
    <div class="card" style="text-align:center">
      <div style="font-size:24px; font-weight:bold"><?= Helpers::money($aging['buckets'][$i]) ?></div>
      <div>حتى <?= $d ?> يوم</div>
    </div>
    <?php endforeach; ?>
    <div class="card" style="text-align:center">
      <div style="font-size:24px; font-weight:bold"><?= Helpers::money($aging['buckets'][4]) ?></div>
      <div>أكثر من 120</div>
    </div>
  </div>
</div>
<div class="grid grid-2">
  <div class="card">
    <h3>قائمة العملاء</h3>
    <table>
      <tr><th>الاسم</th><th>الهاتف</th><th>الرصيد المستحق</th><th></th></tr>
      <?php foreach ($customers as $c): ?>
      <tr>
        <td><?= Helpers::e($c['name']) ?></td>
        <td><?= Helpers::e($c['phone']) ?></td>
        <td><?= Helpers::money($c['balance']) ?></td>
        <td><a href="/accounting/receivables/<?= $c['id'] ?>" class="btn btn-sm">كشف</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div class="card">
    <h3>تحصيل من عميل</h3>
    <form method="post" action="/accounting/arap/payments">
      <input type="hidden" name="party_type" value="customer">
      <div class="form-row"><label>العميل</label>
        <select name="party_id" required>
          <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>"><?= Helpers::e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>المبلغ</label><input type="number" name="amount" step="0.01" required></div>
      <div class="form-row"><label>التاريخ</label><input type="date" name="date" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-row"><label>الطريقة</label>
        <select name="method"><option value="cash">نقد</option><option value="card">بطاقة</option><option value="transfer">تحويل</option></select>
      </div>
      <div class="form-row"><label>الرقم المرجعي</label><input type="text" name="reference"></div>
      <div class="form-row"><label>البيان</label><textarea name="description" rows="2"></textarea></div>
      <button class="btn btn-success">تسجيل التحصيل</button>
    </form>
  </div>
</div>
