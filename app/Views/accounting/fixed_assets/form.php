<?php use App\Core\Helpers; $isEdit = !empty($asset); $action = $isEdit ? '/accounting/fixed-assets/' . $asset['id'] . '/edit' : '/accounting/fixed-assets'; ?>
<div class="card">
  <h2><?= $isEdit ? 'تعديل الأصل' : 'إضافة أصل ثابت' ?></h2>
  <form method="post" action="<?= $action ?>">
    <div class="grid grid-2">
      <div class="form-row"><label>الاسم</label><input type="text" name="name" value="<?= Helpers::e($asset['name'] ?? '') ?>" required></div>
      <div class="form-row"><label>الباركود</label><input type="text" name="barcode" value="<?= Helpers::e($asset['barcode'] ?? '') ?>"></div>
      <div class="form-row"><label>QR Code</label><input type="text" name="qr_code" value="<?= Helpers::e($asset['qr_code'] ?? '') ?>"></div>
      <div class="form-row"><label>الفئة</label>
        <select name="category_id"><option value="">بدون</option>
          <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= ($asset['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= Helpers::e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>تاريخ الشراء</label><input type="date" name="purchase_date" value="<?= Helpers::e($asset['purchase_date'] ?? date('Y-m-d')) ?>" required></div>
      <div class="form-row"><label>القيمة الشرائية</label><input type="number" name="purchase_cost" step="0.01" value="<?= Helpers::e($asset['purchase_cost'] ?? '') ?>" required></div>
      <div class="form-row"><label>قيمة البقاية</label><input type="number" name="salvage_value" step="0.01" value="<?= Helpers::e($asset['salvage_value'] ?? '0') ?>"></div>
      <div class="form-row"><label>القيمة المتبقية</label><input type="number" name="residual_value" step="0.01" value="<?= Helpers::e($asset['residual_value'] ?? '') ?>"></div>
      <div class="form-row"><label>العمر الإنتاجي (سنوات)</label><input type="number" name="life_years" value="<?= Helpers::e($asset['life_years'] ?? '5') ?>" required></div>
      <div class="form-row"><label>طريقة الإهلاك</label>
        <select name="depreciation_method">
          <?php foreach (App\Core\FixedAssetService::METHODS as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($asset['depreciation_method'] ?? 'straight_line') === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>الفرع</label>
        <select name="branch_id"><option value="">بدون</option>
          <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= ($asset['branch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>مركز التكلفة</label>
        <select name="cost_center_id"><option value="">بدون</option>
          <?php foreach ($costCenters as $cc): ?><option value="<?= $cc['id'] ?>" <?= ($asset['cost_center_id'] ?? '') == $cc['id'] ? 'selected' : '' ?>><?= Helpers::e($cc['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>القسم/المنصب</label>
        <select name="department_id"><option value="">بدون</option>
          <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= ($asset['department_id'] ?? '') == $d['id'] ? 'selected' : '' ?>><?= Helpers::e($d['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>المسؤول/العهد</label>
        <select name="responsible_user_id"><option value="">بدون</option>
          <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= ($asset['responsible_user_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= Helpers::e($u['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>الموقع</label><input type="text" name="location" value="<?= Helpers::e($asset['location'] ?? '') ?>"></div>
      <div class="form-row" style="grid-column:1/-1"><label>ملاحظات</label><textarea name="notes" rows="2"><?= Helpers::e($asset['notes'] ?? '') ?></textarea></div>
    </div>
    <button class="btn btn-success">حفظ</button>
    <a href="/accounting/fixed-assets" class="btn">إلغاء</a>
  </form>
</div>
