<?php use App\Core\Helpers; ?>
<div class="card">
  <h2>استيراد الأصول من Excel</h2>
  <p>الأعمدة المطلوبة: <strong>name</strong>، <strong>purchase_cost</strong>، <strong>life_years</strong>. التواريخ بصيغة YYYY-MM-DD.</p>
  <p>طريقة الإهلاك: <code>straight_line</code> أو <code>declining_balance</code> أو <code>units_of_production</code>.</p>
  <a href="/accounting/fixed-assets/import/template" class="btn btn-secondary">تحميل نموذج Excel</a>
  <hr>
  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <ul>
      <?php foreach ($errors as $e): ?><li><?= Helpers::e($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
  <form method="post" action="/accounting/fixed-assets/import" enctype="multipart/form-data">
    <div class="form-row"><label>ملف Excel</label><input type="file" name="import_file" accept=".xlsx,.xls" required></div>
    <button class="btn btn-success">استيراد</button>
    <a href="/accounting/fixed-assets" class="btn">رجوع</a>
  </form>
</div>
