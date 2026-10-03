<?php use App\Core\Helpers; ?>
<?php
$statusLabels = ['pending'=>'بانتظار الموافقة','approved'=>'موافَق عليه','rejected'=>'مرفوض'];
$levelLabels = ['manager'=>'المدير المباشر','hr'=>'الموارد البشرية','finance'=>'المدير المالي'];
$canDecide = fn(string $level) => $this->canDecideHrRequest($level) && $request['overall_status'] === 'pending';
$steps = ['manager' => $request['manager_status'], 'hr' => $request['hr_status'], 'finance' => $request['finance_status']];
$nextLevel = null;
foreach ($steps as $lvl => $st) {
  if ($st === 'pending') { $nextLevel = $lvl; break; }
}
?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px">
    <h3 style="margin:0">طلب تعيين <small>#<?= Helpers::e($request['request_no'] ?? '-') ?></small></h3>
    <span class="badge badge-<?= $request['overall_status'] === 'approved' ? 'success' : ($request['overall_status'] === 'rejected' ? 'danger' : 'warning') ?>">
      <?= $statusLabels[$request['overall_status']] ?? $request['overall_status'] ?>
    </span>
  </div>
  <table class="detail-table">
    <tr><td>الطالب</td><td><?= Helpers::e($request['requester_name'] ?? '-') ?></td><td>تاريخ الطلب</td><td><?= Helpers::e($request['created_at'] ?? '-') ?></td></tr>
    <tr><td>المدير المباشر</td><td><?= Helpers::e($request['manager_name'] ?? '-') ?></td><td>المسمى الوظيفي</td><td><?= Helpers::e($request['job_title']) ?></td></tr>
    <tr><td>القسم</td><td><?= Helpers::e($request['department_name'] ?? '-') ?></td><td>الراتب المتوقع</td><td><?= Helpers::money($request['expected_salary']) ?></td></tr>
    <tr><td>سبب التعيين</td><td colspan="3"><?= nl2br(Helpers::e($request['reason'])) ?></td></tr>
    <?php if ($request['notes']): ?>
    <tr><td>ملاحظات</td><td colspan="3"><?= nl2br(Helpers::e($request['notes'])) ?></td></tr>
    <?php endif; ?>
  </table>

  <h4 style="margin-top:18px"><i class="fa-solid fa-check-double"></i> سير الموافقات</h4>
  <table>
    <tr><th>المستوى</th><th>الحالة</th><th>المعتمد</th><th>التاريخ</th><th>ملاحظات</th></tr>
    <?php foreach (['manager'=>'المدير المباشر','hr'=>'الموارد البشرية','finance'=>'المدير المالي'] as $lvl => $label): ?>
    <tr>
      <td><?= $label ?></td>
      <td><span class="badge badge-<?= $request[$lvl.'_status'] === 'approved' ? 'success' : ($request[$lvl.'_status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= $statusLabels[$request[$lvl.'_status']] ?? $request[$lvl.'_status'] ?></span></td>
      <td><?= Helpers::e($request[$lvl.'_name'] ?? '-') ?></td>
      <td><?= Helpers::e($request[$lvl.'_at'] ?? '-') ?></td>
      <td><?= Helpers::e($request[$lvl.'_notes'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <?php if ($nextLevel && $canDecide($nextLevel)): ?>
  <div class="card" style="margin-top:16px;background:#f9f9f9">
    <h4>قرار <?= $levelLabels[$nextLevel] ?></h4>
    <form method="post" action="/hr/hiring-requests/<?= (int)$request['id'] ?>/decide" class="form-inline">
      <input type="hidden" name="level" value="<?= Helpers::e($nextLevel) ?>">
      <div class="form-row" style="min-width:100%"><label>ملاحظات</label><textarea name="notes" rows="2"></textarea></div>
      <div class="form-row" style="min-width:100%">
        <button name="decision" value="approved" class="btn btn-success"><i class="fa-solid fa-check"></i> موافقة</button>
        <button name="decision" value="rejected" class="btn btn-danger" style="margin-right:8px"><i class="fa-solid fa-xmark"></i> رفض</button>
      </div>
    </form>
  </div>
  <?php endif; ?>
</div>
