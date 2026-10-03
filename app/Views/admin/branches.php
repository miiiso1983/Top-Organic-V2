<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>إدارة فروع ومواقع الجيوفينس</h3>
  <p style="color:#6b7280">
    إحداثيات كل فرع للتحقق من موقع الموظفين عند تسجيل الحضور/الانصراف.
  </p>

  <h4>إضافة فرع جديد</h4>
  <form method="post" action="/admin/branches" class="form-inline" style="border-bottom:2px solid #e5e7eb;padding-bottom:15px;margin-bottom:20px">
    <div class="form-row"><label>الاسم</label><input name="name" required></div>
    <div class="form-row"><label>العنوان</label><input name="address"></div>
    <div class="form-row"><label>الهاتف</label><input name="phone"></div>
    <div class="form-row"><label>Lat</label><input type="number" name="latitude" step="any" placeholder="33.3125"></div>
    <div class="form-row"><label>Lng</label><input type="number" name="longitude" step="any" placeholder="44.3665"></div>
    <div class="form-row"><label>المنطقة (متر)</label><input type="number" name="allowed_radius_meters" value="200"></div>
    <div class="form-row"><label><input type="checkbox" name="is_active" value="1" checked> فعال</label></div>
    <button class="btn btn-primary">إضافة</button>
  </form>

  <?php foreach ($branches as $b): ?>
  <form method="post" action="/admin/branches/<?= $b['id'] ?>" class="form-inline" style="border-bottom:1px solid #e5e7eb;padding:15px 0">
    <div class="form-row"><label>الاسم</label><input name="name" value="<?= Helpers::e($b['name']) ?>" required></div>
    <div class="form-row"><label>العنوان</label><input name="address" value="<?= Helpers::e($b['address'] ?? '') ?>"></div>
    <div class="form-row"><label>الهاتف</label><input name="phone" value="<?= Helpers::e($b['phone'] ?? '') ?>"></div>
    <div class="form-row"><label>الإحداثيات (Lat)</label>
      <input type="number" name="latitude" id="lat-<?= $b['id'] ?>" step="any" value="<?= $b['latitude'] ?? '' ?>" placeholder="33.3125">
    </div>
    <div class="form-row"><label>الإحداثيات (Lng)</label>
      <input type="number" name="longitude" id="lng-<?= $b['id'] ?>" step="any" value="<?= $b['longitude'] ?? '' ?>" placeholder="44.3665">
    </div>
    <div class="form-row">
      <button type="button" class="btn btn-secondary" onclick="fillCurrentLocation(<?= $b['id'] ?>)">📍 استخدام موقعي الحالي</button>
      <span id="msg-<?= $b['id'] ?>" style="font-size:12px;color:#6b7280;margin-right:8px"></span>
    </div>
    <div class="form-row"><label>المنطقة المسموحة (متر)</label>
      <input type="number" name="allowed_radius_meters" value="<?= (int)($b['allowed_radius_meters'] ?? 200) ?>">
    </div>
    <div class="form-row" style="display:flex;align-items:center;gap:10px">
      <label><input type="checkbox" name="is_active" value="1" <?= ($b['is_active'] ?? 1) ? 'checked' : '' ?>> فعال</label>
      <button class="btn btn-success">حفظ</button>
    </div>
  </form>
  <form method="post" action="/admin/branches/<?= $b['id'] ?>/delete" style="margin-top:-10px;margin-bottom:15px" onsubmit="return confirm('هل أنت متأكد من حذف الفرع؟')">
    <button class="btn btn-danger btn-sm">حذف الفرع</button>
  </form>
  <?php endforeach; ?>
  <?php if (!$branches): ?><p>لا توجد فروع مسجّلة.</p><?php endif; ?>
</div>
<script>
function fillCurrentLocation(branchId) {
  const msg = document.getElementById('msg-' + branchId);
  if (!navigator.geolocation) {
    msg.textContent = 'المتصفح لا يدعم تحديد الموقع.';
    msg.style.color = '#dc2626';
    return;
  }
  msg.textContent = 'جارٍ تحديد الموقع...';
  msg.style.color = '#2563eb';
  navigator.geolocation.getCurrentPosition(
    function(position) {
      document.getElementById('lat-' + branchId).value = position.coords.latitude;
      document.getElementById('lng-' + branchId).value = position.coords.longitude;
      msg.textContent = 'تم تحديد الموقع. اضغط حفظ.';
      msg.style.color = '#16a34a';
    },
    function(error) {
      let text = 'تعذّر تحديد الموقع.';
      if (error.code === 1) text = 'تم رفض إذن الموقع. فعّل الموقع من إعدادات المتصفح.';
      if (error.code === 2) text = 'لا يوجد موقع متاح حالياً.';
      if (error.code === 3) text = 'انتهى الوقت أثناء تحديد الموقع.';
      msg.textContent = text;
      msg.style.color = '#dc2626';
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );
}
</script>
