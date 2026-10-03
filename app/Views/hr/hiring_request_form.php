<?php use App\Core\Helpers; ?>
<div class="card" style="max-width:720px">
  <h3><i class="fa-solid fa-user-plus"></i> طلب تعيين موظف جديد</h3>
  <form method="post" action="/hr/manager-requests/hiring">
    <div class="form-row"><label>المسمى الوظيفي <small>*</small></label>
      <input type="text" name="job_title" id="job_title_input" required>
    </div>
    <div class="form-row"><label>الوظيفة المسجلة (اختياري)</label>
      <select name="job_id" id="job_id_select">
        <option value="">— اختر —</option>
        <?php foreach ($jobs as $j): ?>
        <option value="<?= (int)$j['id'] ?>" data-title="<?= Helpers::e($j['title']) ?>" data-department="<?= (int)($j['department_id'] ?? 0) ?>"><?= Helpers::e($j['title']) ?> <?= $j['department_name'] ? '(' . Helpers::e($j['department_name']) . ')' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>القسم</label>
      <select name="department_id" id="department_id_select">
        <option value="">— اختر —</option>
        <?php foreach ($departments as $d): ?>
        <option value="<?= (int)$d['id'] ?>"><?= Helpers::e($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الراتب المتوقع</label>
      <input type="number" name="expected_salary" step="0.01" min="0" value="0">
    </div>
    <div class="form-row" style="min-width:100%"><label>سبب التعيين <small>*</small></label>
      <textarea name="reason" rows="3" required></textarea>
    </div>
    <div class="form-row" style="min-width:100%"><label>ملاحظات</label>
      <textarea name="notes" rows="2"></textarea>
    </div>
    <div style="margin-top:12px">
      <button class="btn btn-success">تقديم الطلب</button>
      <a href="/hr/manager-requests" class="btn btn-secondary">إلغاء</a>
    </div>
  </form>
</div>
<script>
(function(){
  var jobSelect = document.getElementById('job_id_select');
  var titleInput = document.getElementById('job_title_input');
  var deptSelect = document.getElementById('department_id_select');
  if (jobSelect) {
    jobSelect.addEventListener('change', function() {
      var opt = jobSelect.options[jobSelect.selectedIndex];
      if (opt && opt.dataset.title) {
        titleInput.value = opt.dataset.title;
      }
      if (opt && opt.dataset.department) {
        deptSelect.value = opt.dataset.department;
      }
    });
  }
})();
</script>
