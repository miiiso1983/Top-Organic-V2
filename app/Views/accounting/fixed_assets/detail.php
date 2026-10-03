<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <div style="display:flex; justify-content:space-between; align-items:center">
    <h2><?= Helpers::e($asset['name']) ?> <small>(<?= Helpers::e($asset['asset_no']) ?>)</small></h2>
    <div>
      <a href="/accounting/fixed-assets/<?= $asset['id'] ?>/qr" target="_blank" class="btn btn-secondary">QR</a>
      <a href="/accounting/fixed-assets/<?= $asset['id'] ?>/edit" class="btn btn-secondary">تعديل</a>
      <a href="/accounting/fixed-assets" class="btn">رجوع</a>
    </div>
  </div>
  <div class="grid grid-3" style="margin-top:12px">
    <div><strong>القيمة الشرائية:</strong> <?= Helpers::money($asset['purchase_cost']) ?></div>
    <div><strong>القيمة الدفترية:</strong> <?= Helpers::money($bookValue) ?></div>
    <div><strong>الإهلاك المتراكم:</strong> <?= Helpers::money($asset['accumulated_depreciation']) ?></div>
    <div><strong>الفرع:</strong> <?= Helpers::e($asset['branch_name'] ?? '') ?></div>
    <div><strong>القسم:</strong> <?= Helpers::e($asset['department_name'] ?? '') ?></div>
    <div><strong>المسؤول:</strong> <?= Helpers::e($asset['responsible_name'] ?? '') ?></div>
    <div><strong>الموقع:</strong> <?= Helpers::e($asset['location'] ?? '') ?></div>
    <div><strong>الطريقة:</strong> <?= App\Core\FixedAssetService::METHODS[$asset['depreciation_method']] ?? $asset['depreciation_method'] ?></div>
    <div><strong>الحالة:</strong> <span class="badge badge-<?= $asset['status'] ?>"><?= $asset['status'] ?></span></div>
  </div>
</div>
<div class="grid grid-2">
  <div class="card">
    <h3>تسجيل حركة</h3>
    <form method="post" action="/accounting/fixed-assets/transactions">
      <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
      <div class="form-row"><label>النوع</label>
        <select name="transaction_type">
          <?php foreach (App\Core\FixedAssetService::TYPES as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>التاريخ</label><input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-row"><label>المبلغ/القيمة</label><input type="number" name="amount" step="0.01" value="0"></div>
      <div class="form-row"><label>إلى فرع</label>
        <select name="to_branch_id"><option value="">بدون</option><?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= Helpers::e($b['name']) ?></option><?php endforeach; ?></select>
      </div>
      <div class="form-row"><label>إلى قسم</label>
        <select name="to_department_id"><option value="">بدون</option><?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= Helpers::e($d['name']) ?></option><?php endforeach; ?></select>
      </div>
      <div class="form-row"><label>إلى مستخدم</label>
        <select name="to_user_id"><option value="">بدون</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= Helpers::e($u['name']) ?></option><?php endforeach; ?></select>
      </div>
      <div class="form-row"><label>ملاحظات</label><textarea name="notes" rows="2"></textarea></div>
      <button class="btn btn-success">حفظ الحركة</button>
    </form>
  </div>
  <div class="card">
    <h3>صيانة</h3>
    <form method="post" action="/accounting/fixed-assets/maintenance">
      <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
      <div class="form-row"><label>تاريخ الصيانة</label><input type="date" name="maintenance_date" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-row"><label>التاريخ القادم</label><input type="date" name="next_due_date"></div>
      <div class="form-row"><label>التكلفة</label><input type="number" name="cost" step="0.01" value="0"></div>
      <div class="form-row"><label>مزود الخدمة</label><input type="text" name="provider"></div>
      <div class="form-row"><label>الوصف</label><textarea name="description" rows="2"></textarea></div>
      <button class="btn btn-success">حفظ الصيانة</button>
    </form>
  </div>
</div>
<div class="card" style="margin-top:16px">
  <h3>حركات الأصل</h3>
  <table>
    <tr><th>التاريخ</th><th>النوع</th><th>المبلغ</th><th>ملاحظات</th></tr>
    <?php foreach ($transactions as $t): ?>
    <tr><td><?= $t['transaction_date'] ?></td><td><?= App\Core\FixedAssetService::TYPES[$t['transaction_type']] ?? $t['transaction_type'] ?></td><td><?= Helpers::money($t['amount']) ?></td><td><?= Helpers::e($t['notes']) ?></td></tr>
    <?php endforeach; ?>
  </table>
</div>
<div class="grid grid-2">
  <div class="card">
    <h3>الصيانات</h3>
    <table>
      <tr><th>التاريخ</th><th>التكلفة</th><th>المزود</th><th>القادم</th></tr>
      <?php foreach ($maintenance as $m): ?>
      <tr><td><?= $m['maintenance_date'] ?></td><td><?= Helpers::money($m['cost']) ?></td><td><?= Helpers::e($m['provider']) ?></td><td><?= $m['next_due_date'] ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div class="card">
    <h3>تاريخ المواقع</h3>
    <table>
      <tr><th>التاريخ</th><th>الفرع</th><th>القسم</th><th>المستخدم</th></tr>
      <?php foreach ($locations as $l): ?>
      <tr><td><?= $l['effective_date'] ?></td><td><?= Helpers::e($l['branch_name'] ?? '') ?></td><td><?= Helpers::e($l['department_name'] ?? '') ?></td><td><?= Helpers::e($l['user_name'] ?? '') ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
