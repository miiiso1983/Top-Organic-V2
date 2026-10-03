<?php use App\Core\Helpers; ?>
<div class="card">
  <h3><i class="fa-solid fa-wrench"></i> طلب صيانة جديد</h3>
  <p><a href="/maintenance" class="btn btn-sm btn-secondary">← طلبات الصيانة</a></p>
  <form method="post" action="/maintenance" enctype="multipart/form-data" class="form-inline" id="maint-form">
    <div class="form-row" style="min-width:100%">
      <label>اسم مقدم الطلب</label>
      <input value="<?= Helpers::e($employee['full_name'] ?? '-') ?>" disabled>
    </div>
    <div class="form-row" style="min-width:100%">
      <label>الوظيفة</label>
      <input value="<?= Helpers::e($employee['job_title'] ?? '-') ?>" disabled>
    </div>
    <div class="form-row" style="min-width:100%">
      <label>القسم</label>
      <input value="<?= Helpers::e($employee['department_name'] ?? '-') ?>" disabled>
    </div>
    <div class="form-row" style="min-width:100%">
      <label>اسم العطل <small class="text-danger">*</small></label>
      <input name="title" required maxlength="150" placeholder="مثال: عطل في مكيف الصالة">
    </div>
    <div class="form-row" style="min-width:100%">
      <label>اسم الجهاز <small class="text-danger">*</small></label>
      <input name="device_name" required maxlength="150" placeholder="مثال: مكيف سبليت 2 طن">
    </div>
    <div class="form-row" style="min-width:100%">
      <label>الأهمية <small class="text-danger">*</small></label>
      <select name="priority" required>
        <?php foreach ($priorities as $key => $label): ?>
        <option value="<?= Helpers::e($key) ?>" <?= $key === 'normal' ? 'selected' : '' ?>><?= Helpers::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row" style="min-width:100%">
      <label>ملاحظات إضافية</label>
      <textarea name="description" rows="3" placeholder="وصف العطل أو المكان"></textarea>
    </div>
    <div class="form-row" style="min-width:100%">
      <label>صورة العطل (كاميرا أو رفع من الجهاز) <small class="text-danger">*</small></label>
      <input type="file" name="image" id="maint-image" accept="image/*" required style="padding:8px">
      <small>يجب إرفاق صورة توضح العطل (التقاط مباشر أو رفع ملف).</small>
    </div>
    <div class="form-row" style="min-width:100%; display:none" id="preview-row">
      <img id="image-preview" style="max-width:200px; border-radius:8px; border:1px solid var(--border)">
    </div>
    <button class="btn btn-success"><i class="fa-solid fa-paper-plane"></i> إرسال الطلب</button>
    <a href="/maintenance" class="btn btn-secondary">إلغاء</a>
  </form>
</div>
<script>
(function(){
  var input = document.getElementById('maint-image');
  var preview = document.getElementById('image-preview');
  var row = document.getElementById('preview-row');
  input.addEventListener('change', function(){
    if (input.files && input.files[0]) {
      var reader = new FileReader();
      reader.onload = function(e){ preview.src = e.target.result; row.style.display = ''; };
      reader.readAsDataURL(input.files[0]);
    }
  });
  document.getElementById('maint-form').addEventListener('submit', function(e){
    if (!input.value) { e.preventDefault(); alert('يجب إرفاق صورة من الكاميرا'); }
  });
})();
</script>
