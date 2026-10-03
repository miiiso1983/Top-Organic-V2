<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>بصمة الحضور / الانصراف</h3>
  <?php if (!$employee): ?>
    <div class="alert alert-danger">لا يوجد ملف موظف مرتبط بحسابك. يرجى مراجعة إدارة الموارد البشرية.</div>
  <?php else: ?>
    <p><strong>الموظف:</strong> <?= Helpers::e($employee['full_name']) ?> &nbsp;|&nbsp; <strong>الفرع:</strong> <?= Helpers::e($branch['name'] ?? 'غير محدد') ?></p>
    <?php if ($lastRecord): ?>
      <p><strong>آخر سجل:</strong> <span class="badge badge-info"><?= $lastRecord['check_type'] === 'in' ? 'حضور' : 'انصراف' ?></span> في <?= $lastRecord['checked_at'] ?></p>
    <?php endif; ?>

    <form method="post" action="/hr/clock" enctype="multipart/form-data" id="clockForm">
      <input type="hidden" name="latitude" id="latitude">
      <input type="hidden" name="longitude" id="longitude">
      <input type="hidden" name="check_type" id="checkType" value="in">

      <div class="form-row">
        <label>الحركة</label>
        <button type="button" id="btnIn" class="btn btn-success" onclick="setType('in')">حضور</button>
        <button type="button" id="btnOut" class="btn btn-secondary" onclick="setType('out')">انصراف</button>
      </div>

      <div class="form-row">
        <label>الصورة الشخصية (Selfie)</label>
        <input type="file" name="photo" id="photo" accept="image/*" capture="user" required>
        <small>يفضل استخدام الكاميرا الأمامية للهواتف.</small>
      </div>

      <div class="form-row" id="locationStatus">
        <label>الموقع الجغرافي</label>
        <span id="locMsg">جارٍ الحصول على الموقع...</span>
      </div>

      <button class="btn btn-primary" id="submitBtn" type="submit">تسجيل</button>
    </form>

<script>
function setType(type) {
  document.getElementById('checkType').value = type;
  document.getElementById('btnIn').className = type === 'in' ? 'btn btn-success' : 'btn btn-secondary';
  document.getElementById('btnOut').className = type === 'out' ? 'btn btn-danger' : 'btn btn-secondary';
}
setType('in');

function updateLocation(lat, lng, success) {
  document.getElementById('latitude').value = lat;
  document.getElementById('longitude').value = lng;
  document.getElementById('locMsg').textContent = success
    ? 'تم الحصول على الموقع: ' + lat + ', ' + lng
    : lat;
  document.getElementById('locMsg').className = success ? 'text-success' : 'text-danger';
}

if (navigator.geolocation) {
  navigator.geolocation.getCurrentPosition(
    function(pos) { updateLocation(pos.coords.latitude, pos.coords.longitude, true); },
    function(err) { updateLocation('تعذر الحصول على الموقع: ' + (err.message || err), false); },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
  );
} else {
  updateLocation('المتصفح لا يدعم الموقع الجغرافي', false);
}

document.getElementById('clockForm').addEventListener('submit', function(e) {
  var photo = document.getElementById('photo').value;
  if (!photo) {
    alert('يجب التقاط صورة سيلفي');
    e.preventDefault();
    return;
  }
  document.getElementById('submitBtn').disabled = true;
  document.getElementById('submitBtn').textContent = 'جارٍ الإرسال...';
});
</script>
  <?php endif; ?>
</div>
