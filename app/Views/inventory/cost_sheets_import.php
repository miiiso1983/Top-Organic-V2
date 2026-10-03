<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>استيراد قوالب تكلفة الأصناف من Excel</h3>
  <p>
    يمكنك رفع ملف Excel يحتوي على أوراق العمل التالية:
    <strong>سعر اخر شراء</strong> (تحديث أسعار المواد)،
    <strong>كلفة مواد</strong> (مكونات الأطباق)،
    <strong>كلفة المحضر</strong> (مكونات الأصناف المحضرة)،
    <strong>Cost Of Plates</strong> (أسعار البيع).
  </p>
</div>

<div class="card">
  <form method="post" action="/inventory/cost-sheets/import" enctype="multipart/form-data" style="max-width:600px">
    <div class="form-row">
      <label>ملف Excel</label>
      <input type="file" name="import_file" accept=".xlsx,.xls" required>
    </div>
    <div class="form-row">
      <button type="submit" class="btn btn-success"><i class="fa-solid fa-file-import"></i> بدء الاستيراد</button>
      <a href="/inventory/cost-sheets/template.xlsx" class="btn btn-secondary"><i class="fa-solid fa-file-import"></i> تحميل نموذج</a>
      <a href="/inventory/cost-sheets" class="btn btn-sm">رجوع</a>
    </div>
  </form>
</div>

<div class="card">
  <h4>تنسيق الأعمدة المتوقع</h4>
  <table>
    <tr><th>الورقة</th><th>الأعمدة المطلوبة</th></tr>
    <tr><td>كلفة مواد</td><td>الايتم، المادة، الوحدة، الوزن، سعر اخر شراء، %هدر المادة الاولية</td></tr>
    <tr><td>كلفة المحضر</td><td>المادة المصنعة، المادة، الوحدة، الكمية، نسبة الهدر</td></tr>
    <tr><td>سعر اخر شراء</td><td>المادة، سعر</td></tr>
    <tr><td>Cost Of Plates</td><td>اسم المادة، سعر المبيع</td></tr>
  </table>
</div>
