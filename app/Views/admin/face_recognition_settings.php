<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-face-smile"></i> إعدادات التعرف على الوجه (AWS Rekognition)</h2>
</div>

<form method="post" action="/admin/face-recognition-settings">
<div class="card">
  <h3>بيانات AWS</h3>
  <p style="color:#6b7280;font-size:13px">
    الحالة: <?= $configured ? '<span class="badge badge-success">مضبوط ✔</span>' : '<span class="badge badge-warning">غير مضبوط</span>' ?>
    — أنشئ مستخدم IAM مع صلاحية <code>AmazonRekognitionFullAccess</code> من <a href="https://console.aws.amazon.com/iam" target="_blank">AWS IAM</a>.
  </p>
  <div class="form-row" style="min-width:100%">
    <label>Access Key ID</label>
    <input name="aws_access_key" value="<?= Helpers::e($s['aws_access_key']) ?>" style="direction:ltr" placeholder="AKIA...">
  </div>
  <div class="form-row" style="min-width:100%">
    <label>Secret Access Key</label>
    <input type="password" name="aws_secret_key" value="<?= Helpers::e($s['aws_secret_key']) ?>" style="direction:ltr" placeholder="wJalrXUtnFEMI/K7MDENG/bPxRfiCY...">
  </div>
  <div class="form-row" style="min-width:100%">
    <label>Region</label>
    <select name="aws_region" style="direction:ltr">
      <option value="">— اختر —</option>
      <?php foreach ($regions as $r => $label): ?>
      <option value="<?= $r ?>" <?= ($s['aws_region'] ?? '') === $r ? 'selected' : '' ?>><?= Helpers::e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div class="card">
  <h3>إعدادات التحقق</h3>
  <div class="form-row" style="min-width:100%">
    <label>الحد الأدنى للتطابق (Similarity Threshold %)</label>
    <input type="number" name="aws_similarity_threshold" value="<?= (int)($s['aws_similarity_threshold'] ?? 80) ?>" min="0" max="100" step="1">
    <small style="color:#6b7280">إذا كانت نسبة التشابه أقل من هذا الرقم تُرفض البصمة.</small>
  </div>
  <div class="form-row" style="min-width:100%">
    <label>
      <input type="checkbox" name="face_recognition_enabled" value="1" <?= !empty($s['face_recognition_enabled']) ? 'checked' : '' ?>>
      تفعيل التحقق من الوجه عند البصمة
    </label>
  </div>
</div>

<button class="btn btn-success btn-lg">حفظ الإعدادات</button>
</form>

<div class="card" style="margin-top:14px">
  <h3>اختبار الاتصال بـ AWS</h3>
  <p style="color:#6b7280;font-size:13px">اضغط لاختبار صلاحية بيانات AWS (لا يحتاج صورة مرجعية).</p>
  <button id="aws-test-btn" class="btn btn-secondary">اختبار الاتصال</button>
  <div id="aws-test-result" style="margin-top:10px"></div>
</div>

<div class="card" style="margin-top:14px">
  <h3>ملاحظات</h3>
  <ul style="color:#6b7280;font-size:13px">
    <li>لا تُخزّن الصور في AWS؛ تبقى في السيرفر المحلي.</li>
    <li>كل بصمة تُكلف تقريباً 1/10 سنت (Group 1 API).</li>
    <li>يجب أن يكون للموظف صورة مرجعية في ملفه.</li>
  </ul>
</div>


<script>
(function() {
  const btn = document.getElementById('aws-test-btn');
  const result = document.getElementById('aws-test-result');
  if (!btn || !result) return;
  btn.addEventListener('click', async function() {
    btn.disabled = true;
    btn.textContent = 'جاري الاختبار...';
    result.innerHTML = '<span style="color:#6b7280">جاري الاتصال...</span>';
    try {
      const res = await fetch('/admin/face-recognition-settings/test', {
        method: 'POST',
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json();
      if (data.ok) {
        result.innerHTML = '<span class="badge badge-success">تم الاتصال ✔</span> ' +
          '<span style="color:#047857;font-size:13px">' + (data.message || '') + '</span>';
      } else {
        result.innerHTML = '<span class="badge badge-danger">فشل الاتصال</span> ' +
          '<span style="color:#b91c1c;font-size:13px">' + (data.error || 'خطأ غير معروف') + '</span>';
      }
    } catch (e) {
      result.innerHTML = '<span class="badge badge-danger">خطأ</span> ' +
        '<span style="color:#b91c1c;font-size:13px">' + e.message + '</span>';
    } finally {
      btn.disabled = false;
      btn.textContent = 'اختبار الاتصال';
    }
  });
})();
</script>
