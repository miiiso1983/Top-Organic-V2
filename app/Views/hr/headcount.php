<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>خطة الأعداد - <?= Helpers::e($period) ?></h3>
  <form method="get" action="/hr/headcount" class="form-inline">
    <div class="form-row"><label>الفترة</label><input type="month" name="period" value="<?= Helpers::e($period) ?>" required></div>
    <button class="btn">عرض</button>
  </form>

  <form method="post" action="/hr/headcount" class="form-inline" style="margin-top:15px">
    <input type="hidden" name="period" value="<?= Helpers::e($period) ?>">
    <div class="form-row"><label>الوظيفة</label>
      <select name="job_id" id="headcount-job" required>
        <option value="">اختر وظيفة</option>
        <?php foreach ($jobs as $j): ?>
          <option value="<?= $j['id'] ?>" data-department="<?= (int)($j['department_id'] ?? 0) ?>"><?= Helpers::e($j['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>القسم</label><input type="text" id="headcount-department" readonly placeholder="يُحدَّد تلقائياً من الوظيفة"></div>
    <div class="form-row"><label>العدد المخطط</label><input type="number" name="planned_count" min="0" value="0" required></div>
    <div class="form-row"><label>ميزانية الرواتب</label><input type="number" name="budget_salary" step="0.01" min="0" value="0" required></div>
    <div class="form-row"><label>ملاحظات</label><input name="notes" placeholder="اختياري"></div>
    <button class="btn btn-success">حفظ</button>
  </form>
</div>

<div class="card">
  <table>
    <tr><th>الوظيفة</th><th>القسم</th><th>العدد المخطط</th><th>العدد الفعلي</th><th>الفرق</th><th>ميزانية الرواتب</th><th>صافي الرواتب الفعلي</th><th>الفرق مبلغ</th><th>ملاحظات</th><th></th></tr>
    <?php foreach ($plans as $plan): ?>
    <?php $countDiff = (int)$plan['actual_count'] - (int)$plan['planned_count']; ?>
    <?php $budgetDiff = (float)($plan['actual_net_salary'] ?? 0) - (float)($plan['budget_salary'] ?? 0); ?>
    <tr>
      <td><?= Helpers::e($plan['job_title'] ?? '-') ?></td>
      <td><?= Helpers::e($plan['department_name']) ?></td>
      <td><?= Helpers::e($plan['planned_count']) ?></td>
      <td><?= Helpers::e($plan['actual_count'] ?? 0) ?></td>
      <td style="color:<?= $countDiff >= 0 ? '#16a34a' : '#dc2626' ?>"><?= $countDiff > 0 ? '+' : '' ?><?= $countDiff ?></td>
      <td><?= Helpers::money($plan['budget_salary']) ?></td>
      <td><?= Helpers::money($plan['actual_net_salary'] ?? 0) ?></td>
      <td style="color:<?= $budgetDiff >= 0 ? '#16a34a' : '#dc2626' ?>"><?= $budgetDiff >= 0 ? '+' : '' ?><?= Helpers::money($budgetDiff) ?></td>
      <td><?= Helpers::e($plan['notes'] ?? '-') ?></td>
      <td>
        <form method="post" action="/hr/headcount/<?= $plan['id'] ?>/delete" style="display:inline" onsubmit="return confirm('تأكيد الحذف؟')">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$plans): ?><tr><td colspan="10">لا توجد خطط أعداد مسجلة لهذه الفترة</td></tr><?php endif; ?>
  </table>
</div>

<script>
(function(){
  var deptNames = <?= json_encode(array_column($departments, 'name', 'id')) ?>;
  var jobSelect = document.getElementById('headcount-job');
  var deptDisplay = document.getElementById('headcount-department');
  if (jobSelect && deptDisplay) {
    jobSelect.addEventListener('change', function() {
      var opt = jobSelect.options[jobSelect.selectedIndex];
      var deptId = opt ? opt.getAttribute('data-department') : '';
      deptDisplay.value = (deptId && deptNames[deptId]) ? deptNames[deptId] : '';
    });
  }
})();
</script>

<p style="margin-top:10px">
  <a href="/hr/payroll?period=<?= urlencode($period) ?>">← العودة إلى كشف الرواتب</a>
</p>
