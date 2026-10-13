<?php use App\Core\Helpers; ?>
<?php use App\Core\ImportHelper; ?>
<?php $period = $period ?? date('Y-m'); ?>
<?php $importErrors = ImportHelper::getErrors(); ?>
<?php if ($importErrors): ?>
<div class="alert alert-danger">
  <strong>أخطاء الاستيراد:</strong>
  <ul><?php foreach ($importErrors as $err): ?><li><?= Helpers::e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>
<div class="card">
  <h3>استيراد بيانات الرواتب التاريخية</h3>
  <p>استورد بيانات التيبس، الضمان الاجتماعي، تذاكر السفر، الزيادات، المكافآت، الخصومات، إضافي التيبس، تسوية أيام الدوام، والبدلات جميعها في ملف Excel/CSV واحد. كل صف يحدد نوعه بعمود <strong>type</strong>. لإدارة التحميلات الشهرية أو السلف، استخدم الواجهة المخصصة لهما ضمن وحدة المحاسبة: <a href="/accounting/loadings">التحميلات</a> و<a href="/accounting/advances">السلف</a>.</p>
</div>

<div class="card" style="margin-top:15px">
  <h4>استيراد سريع لفترة محددة (أيام دوام + استقطاعات + استحقاقات)</h4>
  <p>قالب معبأ مسبقاً بقائمة الموظفين النشطين (رمز/اسم) لإدخال <strong>تسوية أيام الدوام، استقطاعات (أيام أو مبلغ)، واستحقاقات/مكافآت</strong> لجميع الموظفين دفعة واحدة لفترة محددة، دون الحاجة لتكرار الفترة أو نوع السجل في كل صف.</p>
  <form method="get" action="/hr/payroll/import-period-template" class="form-inline" style="margin-bottom:15px">
    <div class="form-row"><label>الفترة</label><input type="month" name="period" value="<?= Helpers::e($period) ?>" required></div>
    <button class="btn btn-outline">تحميل قالب الفترة</button>
  </form>

  <hr style="margin:15px 0">

  <form method="post" action="/hr/payroll/import-period" enctype="multipart/form-data" class="form-inline">
    <div class="form-row"><label>الفترة</label><input type="month" name="period" value="<?= Helpers::e($period) ?>" required></div>
    <div class="form-row"><label>ملف Excel أو CSV</label><input type="file" name="import_file" accept=".xlsx,.xls,.csv" required></div>
    <button class="btn btn-primary">استيراد تسويات الفترة</button>
  </form>

  <ul style="margin-top:15px">
    <li><strong>رمز الموظف:</strong> مطلوب لمطابقة الصف بالموظف. اسم الموظف للمراجعة فقط.</li>
    <li><strong>أيام استحقاق/تسوية دوام إضافية:</strong> تُضاف كاستحقاق أيام (تسوية أيام دوام) بنفس معدل اليومية المعتمد في باقي النظام (الأساس المعدّل/30 × عدد الأيام).</li>
    <li><strong>أيام الاستقطاع/الغياب أو مبلغ الاستقطاع:</strong> يمكن إدخال أحدهما فقط (إذا أدخل المبلغ مباشرة يُعتمد، وإلا يُحسب تلقائياً من عدد الأيام).</li>
    <li><strong>مبلغ الاستحقاق/المكافأة:</strong> يُضاف كمكافأة/استحقاق مبلغ مقطوع.</li>
    <li>أعمدة السبب اختيارية للتوثيق فقط. الصفوف الفارغة (بدون أي قيمة) تُتجاهل بصمت.</li>
    <li>تُستورد التسويات بحالة <strong>معتمدة</strong> مباشرة (بنفس آلية الاستيراد الموحد أعلاه) وتُحتسب تلقائياً ضمن استحقاقات/استقطاعات الفترة عند توليد أو إعادة توليد راتبها من صفحة كشف الرواتب مباشرة.</li>
  </ul>
</div>

<div class="card" style="margin-top:15px">
  <h4>القالب والاستيراد الموحد</h4>
  <p>حمل القالب الموحد أدناه؛ يحتوي على جميع الأعمدة مع أمثلة جاهزة لكل نوع.</p>
  <a href="/hr/payroll/import-template" class="btn btn-outline" style="margin-bottom:15px">تحميل قالب Excel موحد</a>

  <hr style="margin:15px 0">

  <form method="post" action="/hr/payroll/import-all" enctype="multipart/form-data" class="form-inline">
    <div class="form-row"><label>ملف Excel أو CSV</label><input type="file" name="import_file" accept=".xlsx,.xls,.csv" required></div>
    <button class="btn btn-primary">استيراد الملف الموحد</button>
  </form>
</div>

<div class="card" style="margin-top:15px">
  <h4>استيراد رواتب تاريخية كاملة (فترات سابقة)</h4>
  <p>لاستيراد كشف راتب كامل لفترة سابقة (مثل يوليو أو أغسطس) دفعة واحدة، بجميع بنود الاستحقاقات والاستقطاعات المعتمدة حالياً في مسير الرواتب. أسماء الأعمدة في القالب <strong>بالعربية وبنفس ترتيب أعمدة كشف الرواتب</strong> المعتمد (ولا تزال الأسماء الإنجليزية القديمة مدعومة للقوالب السابقة). تُنشأ الرواتب بحالة <strong>مسودة</strong>، وعند اعتمادها لاحقاً من كشف الرواتب تُنشأ قيود المحاسبة تلقائياً بنفس قواعد ترحيل العجز إلى سلفة عند سلبية الصافي.</p>
  <a href="/hr/payroll/import-historical-template" class="btn btn-outline" style="margin-bottom:15px">تحميل قالب الرواتب التاريخية</a>

  <hr style="margin:15px 0">

  <form method="post" action="/hr/payroll/import-historical" enctype="multipart/form-data" class="form-inline">
    <div class="form-row"><label>ملف Excel أو CSV</label><input type="file" name="import_file" accept=".xlsx,.xls,.csv" required></div>
    <button class="btn btn-primary">استيراد الرواتب التاريخية</button>
  </form>

  <ul style="margin-top:15px">
    <li><strong>رمز الموظف:</strong> رمز الموظف (مطلوب).</li>
    <li><strong>اسم الموظف:</strong> اختياري، للمراجعة فقط، لا يُستخدم في المطابقة.</li>
    <li><strong>الفترة:</strong> بالشكل YYYY-MM (مطلوبة).</li>
    <li><strong>الأساسي:</strong> الراتب الأساسي (مطلوب).</li>
    <li><strong>أيام الإضافي / الإضافي:</strong> عدد أيام الإضافي والمبلغ المستحق المحسوب بالمعادلة المعتمدة (اختياري، افتراضي 0).</li>
    <li><strong>المكافآت / إضافي تيبس / تسوية أيام دوام / البدلات / تذاكر سفر:</strong> بنود الاستحقاقات (اختياري، افتراضي 0).</li>
    <li><strong>أيام الدوام / أيام الغياب / خصم الغياب:</strong> أيام الدوام الفعلية من بيانات البصمة، وعدد أيام الغياب والمبلغ المالي المخصوم نتيجةً لذلك (اختياري، افتراضي 0).</li>
    <li><strong>الخصومات / السلف / سلف طويلة الأجل:</strong> الخصومات وأقساط السلف العادية وطويلة الأجل لتلك الفترة (اختياري، افتراضي 0).</li>
    <li><strong>التأمينات المستقطعة / التحميلات / التيبس / الضمان / السكن:</strong> بنود الاستقطاعات الثابتة (اختياري، افتراضي 0).</li>
    <li><strong>أيام الدوام المتوقعة / الإجازات / إجازات بدون راتب / الجزاءات / الضريبة:</strong> بيانات هيكلية إضافية مطلوبة لصحة الاحتساب والمطابقة (اختياري، افتراضي 0).</li>
    <li><strong>صافي الراتب:</strong> صافي الراتب الفعلي للفترة (مطلوب). يُحتسب فرق التقريب تلقائياً ليطابق مجموع البنود أعلاه هذا الصافي تماماً. إن كان الصافي سالباً، يُصفَّر ويُصبح مقدار العجز سلفة تلقائية تُخصم من الشهر التالي، كما هو معمول به في الاحتساب العادي.</li>
    <li>لا يمكن استيراد فترة سبق اعتماد أو صرف راتبها لنفس الموظف — يجب أن تكون مسودة أو غير موجودة.</li>
  </ul>
</div>

<div class="card" style="margin-top:15px">
  <h4>تعليمات الأعمدة</h4>
  <ul>
    <li><strong>emp_code:</strong> رمز الموظف الموجود في النظام (مطلوب لكل الأنواع).</li>
    <li><strong>type:</strong> نوع السجل. القيم المسموحة: <code>tips</code> / <code>social_security</code> / <code>travel_tickets</code> / <code>raise</code> / <code>bonus</code> / <code>deduction</code> / <code>tips_addition</code> / <code>work_days_settlement</code> / <code>allowance</code> (أو بالعربية: تيبس، ضمان، تذاكر، زيادة، مكافأة، خصم، إضافي تيبس، تسوية أيام دوام، بدل). التحميلات والسلف لم تعد ضمن هذا الاستيراد الموحد — لهما واجهة مخصصة ضمن وحدة المحاسبة.</li>
    <li><strong>period:</strong> الفترة بالشكل YYYY-MM (مطلوبة لكل الأنواع ما عدا البدل).</li>
    <li><strong>amount:</strong> المبلغ بالدينار (مطلوب للضمان، تذاكر السفر، الزيادة، المكافأة، إضافي التيبس، البدل؛ اختياري للخصم/تسوية أيام الدوام إذا أُدخل عمود days بدلاً منه).</li>
    <li><strong>full_amount / working_days:</strong> للتيبس فقط (المبلغ الكامل وعدد أيام الدوام).</li>
    <li><strong>category:</strong> للبدل فقط (نوع البدل، مثل مواصلات أو سكن).</li>
    <li><strong>days:</strong> للخصم وتسوية أيام الدوام فقط: عدد الأيام (يُحتسب المبلغ تلقائياً = الراتب الأساسي/30 × الأيام إذا لم يُدخل amount مباشرة) — للخصم يُستقطع، ولتسوية أيام الدوام يُضاف كاستحقاق.</li>
    <li><strong>departure_date / return_date / travel_method:</strong> لتذاكر السفر (اختياري).</li>
    <li><strong>approved_at:</strong> للزيادة/المكافأة/الخصم/إضافي التيبس/تسوية أيام الدوام: تاريخ السريان (اختياري، افتراضي أول الفترة أو اليوم).</li>
    <li><strong>reason / notes:</strong> نص إضافي (اختياري).</li>
    <li>سجلات الزيادة/المكافأة/الخصم/إضافي التيبس/تسوية أيام الدوام تُستورد بحالة <strong>معتمدة</strong> مباشرة (دون المرور بسير موافقة التسويات اليدوية) وتُحتسب في مسير الراتب عند توليده لنفس الفترة. أما البدل فيُحدَّث كقيمة ثابتة شهرية للموظف (أو يُنشأ إن لم يكن موجوداً) حتى تُعدَّل أو تُلغى يدوياً.</li>
  </ul>

  <h4 style="margin-top:15px">الأعمدة المطلوبة حسب النوع</h4>
  <table class="table table-sm" style="margin-top:10px">
    <thead>
      <tr><th>type</th><th>الأعمدة المطلوبة</th></tr>
    </thead>
    <tbody>
      <tr><td>tips</td><td>emp_code, period, full_amount, working_days</td></tr>
      <tr><td>social_security</td><td>emp_code, period, amount</td></tr>
      <tr><td>travel_tickets</td><td>emp_code, period, amount</td></tr>
      <tr><td>raise</td><td>emp_code, amount</td></tr>
      <tr><td>bonus</td><td>emp_code, amount</td></tr>
      <tr><td>tips_addition</td><td>emp_code, amount</td></tr>
      <tr><td>work_days_settlement</td><td>emp_code, وأحد العمودين days أو amount</td></tr>
      <tr><td>deduction</td><td>emp_code, وأحد العمودين amount أو days</td></tr>
      <tr><td>allowance</td><td>emp_code, category, amount</td></tr>
    </tbody>
  </table>
  <p style="margin-top:10px">لإدارة <strong>التحميلات</strong> أو <strong>السلف</strong>، استخدم الواجهة المخصصة لهما ضمن وحدة المحاسبة: <a href="/accounting/loadings">التحميلات الشهرية</a> و<a href="/accounting/advances">السلف</a>.</p>
</div>

<p style="margin-top:10px">
  <a href="/hr/payroll">← العودة إلى كشف الرواتب</a>
</p>
