<?php use App\Core\{Helpers, AccountingService}; ?>
<div class="grid grid-2">
  <div class="card">
    <h3>تعديل حساب: <?= Helpers::e($account['code'] . ' - ' . $account['name']) ?></h3>
    <form method="post" action="/accounting/accounts/<?= (int)$account['id'] ?>/edit">
      <div class="form-row">
        <label>الحساب الأب</label>
        <select name="parent_id">
          <option value="0">بدون أب (حساب رئيسي)</option>
          <?php foreach ($parents as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)$account['parent_id'] ? 'selected' : '' ?>>
              <?= Helpers::e($p['code'] . ' - ' . $p['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label>اسم الحساب</label>
        <input type="text" name="name" value="<?= Helpers::e($account['name']) ?>" required>
      </div>
      <div class="form-row">
        <label>النوع</label>
        <select name="type" required>
          <?php $types = ['asset'=>'أصل','liability'=>'خصم','equity'=>'حقوق ملكية','revenue'=>'إيراد','expense'=>'مصروف']; ?>
          <?php foreach ($types as $key => $label): ?>
            <option value="<?= $key ?>" <?= $account['type'] === $key ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label>المجموع</label>
        <input type="text" name="account_group" value="<?= Helpers::e($account['account_group'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label>رصيد افتتاحي</label>
        <input type="number" name="opening_balance" step="0.01" value="<?= Helpers::e($account['opening_balance'] ?? 0) ?>">
      </div>
      <div class="form-row">
        <button class="btn btn-success">حفظ التعديلات</button>
        <a href="/accounting/accounts" class="btn">إلغاء</a>
      </div>
    </form>
  </div>
  <div class="card">
    <h4>معلومات الحساب</h4>
    <p><strong>الكود:</strong> <?= Helpers::e($account['code']) ?></p>
    <p><strong>الرصيد الحالي:</strong> <?= Helpers::money(AccountingService::balance((int)$account['id'])) ?></p>
    <p><strong>is_leaf:</strong> <?= (int)$account['is_leaf'] ? 'نعم' : 'لا' ?></p>
  </div>
</div>
