<?php use App\Core\Helpers; ?>
<div class="card">
  <div class="item-card-header">
    <div>
      <div class="item-card-title">استيراد الأصناف المحضرة</div>
      <div class="item-card-subtitle">رفع ملف Excel يحتوي على الأصناف المحضرة وموادها الأولية</div>
    </div>
  </div>
</div>

<div class="card">
  <h4>تنسيق الملف المطلوب</h4>
  <table style="font-size:13px; margin-top:12px;">
    <tr><th>العمود</th><th>المحتوى</th><th>الملاحظات</th></tr>
    <tr><td>A</td><td>اسم الصنف المحضر</td><td>يكرر في كل سطور من مكونات الوصفة</td></tr>
    <tr><td>B</td><td>المادة الأولية</td><td>إذا لم تكن موجودة سيتم إنشاؤها</td></tr>
    <tr><td>C</td><td>الرمز (باركود)</td><td>اختياري</td></tr>
    <tr><td>D</td><td>الكمية المستخدمة</td><td>بالوحدة المدخلة</td></tr>
    <tr><td>E</td><td>الوحدة</td><td>سيتم إنشاءها إذا لم تكن موجودة</td></tr>
    <tr><td>G</td><td>آخر سعر شراء</td><td>للمادة الأولية</td></tr>
    <tr><td>H</td><td>نسبة هدر المادة</td><td>0.10 = 10%</td></tr>
    <tr><td>I</td><td>نسبة هدر الناتج</td><td>للصنف المحضر (0.10 = 10%)</td></tr>
  </table>
  <p style="margin-top:16px; color:#6b7280; font-size:13px;">
    إذا كان الصنف المحضر غير موجود، سيتم إنشاؤه تلقائياً مع قالب تكلفة خاص به. يتم حساب التكلفة لكل كيلوغرام واحد من الناتج.
  </p>
</div>

<div class="card">
  <form method="post" action="/inventory/prepared-items/import" enctype="multipart/form-data" class="form-inline">
    <div class="form-row">
      <label>ملف Excel</label>
      <input type="file" name="import_file" accept=".xlsx,.xls" required>
    </div>
    <button class="btn btn-success">رفع واستيراد</button>
    <a href="/inventory/prepared-items" class="btn">العودة</a>
  </form>
</div>
