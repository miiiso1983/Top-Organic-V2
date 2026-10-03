<?php use App\Core\Helpers; ?>
<?php $typeLabels = ['monthly'=>'شهري','quarterly'=>'ربع سنوي','half_yearly'=>'نصف سنوي','annual'=>'سنوي']; ?>
<?php $statusLabels = ['draft'=>'مسودة','finalized'=>'معتمد']; ?>
<?php $recLabels = ['none'=>'لا شيء','bonus'=>'مكافأة','raise'=>'زيادة','promotion'=>'ترقية','warning'=>'إنذار']; ?>

<div class="card">
  <h3>تقرير تقييم الأداء</h3>
  <table class="detail-table">
    <tr><td>الموظف</td><td><?= Helpers::e($eval['full_name']) ?> (<?= Helpers::e($eval['job_title'] ?? '-') ?>)</td></tr>
    <tr><td>القسم</td><td><?= Helpers::e($eval['department'] ?? '-') ?></td></tr>
    <tr><td>الفترة</td><td><?= Helpers::e($eval['period'] ?? '-') ?></td></tr>
    <tr><td>النوع</td><td><?= $typeLabels[$eval['evaluation_type']] ?? $eval['evaluation_type'] ?></td></tr>
    <tr><td>الراتب الأساسي</td><td><?= Helpers::money($eval['salary'] ?? 0) ?></td></tr>
    <tr><td>درجة KPI</td><td><?= $eval['kpi_score'] ?>/100</td></tr>
    <tr><td>تقييم المدير</td><td><?= $eval['manager_score'] ?>/100</td></tr>
    <tr><td>تقييم الإدارة</td><td><?= $eval['admin_score'] ?>/100</td></tr>
    <tr><td>التقييم الذاتي</td><td><?= $eval['self_score'] ?>/100</td></tr>
    <tr><td>النتيجة الإجمالية</td><td><strong><?= $eval['total_score'] ?>/100</strong></td></tr>
    <tr><td>الحالة</td><td><span class="badge badge-<?= $eval['status'] === 'finalized' ? 'success' : 'warning' ?>"><?= $statusLabels[$eval['status']] ?></span></td></tr>
    <tr><td>التوصية</td><td><?= $recLabels[$eval['recommendation']] ?? '-' ?></td></tr>
    <tr><td>ملاحظات المدير</td><td><?= Helpers::e($eval['manager_notes'] ?? '-') ?></td></tr>
    <tr><td>ملاحظات الإدارة</td><td><?= Helpers::e($eval['admin_notes'] ?? '-') ?></td></tr>
    <tr><td>ملاحظات عامة</td><td><?= Helpers::e($eval['notes'] ?? '-') ?></td></tr>
    <tr><td>المقيّم</td><td><?= Helpers::e($eval['evaluator_name'] ?? '-') ?></td></tr>
  </table>
  <a href="/hr/evaluations" class="btn btn-light" style="margin-top:15px">العودة للقائمة</a>
</div>

<?php if ($items): ?>
<div class="card">
  <h4>تفصيل مؤشرات الأداء (KPIs)</h4>
  <table>
    <tr><th>المؤشر</th><th>الوزن</th><th>الدرجة</th><th>الملاحظات</th></tr>
    <?php foreach ($items as $i): ?>
    <tr>
      <td><?= Helpers::e($i['kpi_name']) ?></td>
      <td><?= $i['weight'] ?>%</td>
      <td><?= $i['score'] ?>/100</td>
      <td><?= Helpers::e($i['notes'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php if ($goals): ?>
<div class="card">
  <h4>تقييم الأهداف</h4>
  <table>
    <tr><th>الهدف</th><th>الوصف</th><th>الهدف</th><th>نسبة الإنجاز</th><th>ملاحظات</th></tr>
    <?php foreach ($goals as $g): ?>
    <tr>
      <td><?= Helpers::e($g['title']) ?></td>
      <td><?= Helpers::e($g['description'] ?? '-') ?></td>
      <td><?= Helpers::e($g['target_value'] ?? '-') ?> <?= Helpers::e($g['unit'] ?? '') ?></td>
      <td><?= $g['achievement_rate'] ?>%</td>
      <td><?= Helpers::e($g['eval_notes'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>
