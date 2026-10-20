<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>التقديم على الوظائف - Top Organic</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="/assets/css/app.css?v=10">
<meta name="theme-color" content="#1B5E20">
<style>
body { background: linear-gradient(160deg, var(--brand-dark), var(--brand)); min-height: 100vh; }
.apply-wrap { max-width: 860px; margin: 0 auto; padding: 30px 16px 60px; }
.apply-head { text-align: center; color: #fff; margin-bottom: 20px; }
.apply-head img { height: 90px; margin-bottom: 10px; }
.apply-head h1 { margin: 0; font-size: 22px; }
.apply-head p { margin: 6px 0 0; opacity: .9; font-size: 14px; }
.apply-card { background: var(--surface); border-radius: 14px; padding: 28px; box-shadow: 0 10px 30px rgba(0,0,0,.2); }
.section-title { display: flex; align-items: center; gap: 8px; font-size: 16px; font-weight: 700; color: var(--brand-dark); margin: 26px 0 14px; padding-bottom: 8px; border-bottom: 2px solid var(--brand); }
.section-title:first-child { margin-top: 0; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
@media (max-width: 640px) { .grid-2 { grid-template-columns: 1fr; } }
.exp-row { display: grid; grid-template-columns: repeat(5, 1fr) auto; gap: 8px; align-items: end; margin-bottom: 10px; padding: 10px; background: var(--surface-alt); border-radius: 8px; }
.exp-row label { font-size: 11px; }
@media (max-width: 760px) { .exp-row { grid-template-columns: 1fr 1fr; } }
.exp-remove { background: var(--danger); color: #fff; border: none; border-radius: 6px; width: 32px; height: 32px; cursor: pointer; }
.upload-box { border: 2px dashed var(--border); border-radius: 10px; padding: 18px; text-align: center; color: var(--text-muted); cursor: pointer; }
.upload-box.has-file { border-color: var(--success); color: var(--success-text); background: var(--success-bg); }
.submit-bar { text-align: center; margin-top: 26px; }
.submit-bar .btn { padding: 12px 40px; font-size: 16px; }
.required-mark { color: var(--danger); }
</style>
</head>
<body>
<div class="apply-wrap">
  <div class="apply-head">
    <img src="/assets/images/logo.webp" alt="Top Organic">
    <h1>استمارة طلب توظيف</h1>
    <p>يرجى تعبئة البيانات التالية بدقة — سيتواصل معك فريق الموارد البشرية في حال توافقك مع الوظيفة</p>
  </div>

  <div class="apply-card">
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/careers/apply" enctype="multipart/form-data" id="applyForm">

      <div class="section-title"><i class="fa-solid fa-briefcase"></i> الوظيفة المستهدفة</div>
      <div class="grid-2">
        <div class="form-row">
          <label>الوظيفة المستهدفة <span class="required-mark">*</span></label>
          <select name="job_id" required>
            <option value="">اختر الوظيفة...</option>
            <?php foreach ($jobs as $j): ?>
            <option value="<?= $j['id'] ?>" <?= (string)($old['job_id'] ?? '') === (string)$j['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($j['title']) ?><?= $j['department_name'] ? ' - ' . htmlspecialchars($j['department_name']) : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label>الفرع</label>
          <select name="branch_id">
            <option value="">—</option>
            <?php foreach ($branches as $b): ?>
            <option value="<?= $b['id'] ?>" <?= (string)($old['branch_id'] ?? '') === (string)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="section-title"><i class="fa-solid fa-id-card"></i> المعلومات الشخصية</div>
      <div class="grid-2">
        <div class="form-row"><label>الاسم الثلاثي واللقب <span class="required-mark">*</span></label>
          <input type="text" name="full_name" value="<?= htmlspecialchars($old['full_name'] ?? '') ?>" required></div>
        <div class="form-row"><label>رقم الهاتف <span class="required-mark">*</span></label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($old['phone'] ?? '') ?>" required></div>
        <div class="form-row"><label>العمر</label><input type="number" name="age" min="15" max="75" value="<?= htmlspecialchars($old['age'] ?? '') ?>"></div>
        <div class="form-row"><label>الجنسية</label><input type="text" name="nationality" value="<?= htmlspecialchars($old['nationality'] ?? '') ?>"></div>
        <div class="form-row"><label>الحالة الاجتماعية</label>
          <select name="marital_status">
            <option value="">—</option>
            <?php foreach ($maritalOptions as $m): ?>
            <option value="<?= htmlspecialchars($m) ?>" <?= ($old['marital_status'] ?? '') === $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row"><label>محل السكن</label><input type="text" name="residence" value="<?= htmlspecialchars($old['residence'] ?? '') ?>"></div>
        <div class="form-row"><label>التخصص الدراسي</label><input type="text" name="education_major" value="<?= htmlspecialchars($old['education_major'] ?? '') ?>"></div>
        <div class="form-row"><label>سنة التخرج</label><input type="text" name="graduation_year" value="<?= htmlspecialchars($old['graduation_year'] ?? '') ?>"></div>
        <div class="form-row"><label>إجمالي سنوات الخبرة</label><input type="text" name="total_experience_years" placeholder="مثال: 3 سنوات" value="<?= htmlspecialchars($old['total_experience_years'] ?? '') ?>"></div>
        <div class="form-row"><label>الراتب المتوقع</label><input type="number" name="expected_salary" value="<?= htmlspecialchars($old['expected_salary'] ?? '') ?>"></div>
        <div class="form-row"><label>هل لديك خبرة سابقة في المطاعم أو الفنادق؟</label>
          <select name="has_restaurant_experience"><option value="0">لا</option><option value="1" <?= !empty($old['has_restaurant_experience']) ? 'selected' : '' ?>>نعم</option></select></div>
        <div class="form-row"><label>هل لديك أقارب يعملون في الشركة؟</label>
          <select name="has_relatives_in_company"><option value="0">لا</option><option value="1" <?= !empty($old['has_relatives_in_company']) ? 'selected' : '' ?>>نعم</option></select></div>
        <div class="form-row"><label>هل أنت متفرغ للعمل حالياً؟</label>
          <select name="is_available_now"><option value="1" <?= ($old['is_available_now'] ?? '1') === '1' ? 'selected' : '' ?>>نعم</option><option value="0" <?= ($old['is_available_now'] ?? '') === '0' ? 'selected' : '' ?>>لا</option></select></div>
      </div>

      <div class="section-title"><i class="fa-solid fa-clock-rotate-left"></i> الخبرات العملية السابقة <small style="color:var(--text-muted);font-weight:400">(اختياري)</small></div>
      <div id="expBox"></div>
      <button type="button" class="btn btn-secondary btn-sm" onclick="addExpRow()"><i class="fa-solid fa-plus"></i> إضافة خبرة عمل سابقة</button>

      <div class="section-title"><i class="fa-solid fa-file-arrow-up"></i> السيرة الذاتية <small style="color:var(--text-muted);font-weight:400">(اختياري)</small></div>
      <label class="upload-box" id="uploadBox" for="cvInput">
        <i class="fa-solid fa-cloud-arrow-up" style="font-size:22px;display:block;margin-bottom:6px"></i>
        <span id="uploadText">اضغط لاختيار ملف PDF أو صورة للسيرة الذاتية (حد أقصى 10MB)</span>
      </label>
      <input type="file" id="cvInput" name="cv_file" accept=".pdf,.jpg,.jpeg,.png,.webp" style="display:none" onchange="onCvChange(this)">

      <div class="submit-bar">
        <button type="submit" class="btn btn-success"><i class="fa-solid fa-paper-plane"></i> إرسال الطلب</button>
      </div>
    </form>
  </div>
</div>

<script>
var expIndex = 0;
function escapeAttr(v) {
  return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function addExpRow(vals) {
  vals = vals || {};
  var box = document.getElementById('expBox');
  var row = document.createElement('div');
  row.className = 'exp-row';
  row.innerHTML =
    '<div><label>اسم الشركة</label><input name="exp_company[]" value="' + escapeAttr(vals.company) + '"></div>' +
    '<div><label>المسمى الوظيفي</label><input name="exp_title[]" value="' + escapeAttr(vals.title) + '"></div>' +
    '<div><label>مدة العمل</label><input name="exp_duration[]" value="' + escapeAttr(vals.duration) + '"></div>' +
    '<div><label>الراتب الأخير</label><input name="exp_salary[]" value="' + escapeAttr(vals.last_salary) + '"></div>' +
    '<div><label>سبب ترك العمل</label><input name="exp_reason[]" value="' + escapeAttr(vals.leave_reason) + '"></div>' +
    '<button type="button" class="exp-remove" onclick="this.closest(\'.exp-row\').remove()">×</button>';
  box.appendChild(row);
  expIndex++;
}
function onCvChange(input) {
  var box = document.getElementById('uploadBox');
  var text = document.getElementById('uploadText');
  if (input.files && input.files[0]) {
    box.classList.add('has-file');
    text.textContent = 'تم اختيار: ' + input.files[0].name;
  } else {
    box.classList.remove('has-file');
    text.textContent = 'اضغط لاختيار ملف PDF أو صورة للسيرة الذاتية (حد أقصى 10MB)';
  }
}
var oldExperiences = <?= json_encode(array_map(function ($c, $t, $d, $s, $r) {
    return ['company' => $c, 'title' => $t, 'duration' => $d, 'last_salary' => $s, 'leave_reason' => $r];
}, $old['exp_company'] ?? [], $old['exp_title'] ?? [], $old['exp_duration'] ?? [], $old['exp_salary'] ?? [], $old['exp_reason'] ?? []), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
if (oldExperiences.length) {
  oldExperiences.forEach(function (v) { addExpRow(v); });
} else {
  addExpRow();
}
</script>
</body>
</html>
