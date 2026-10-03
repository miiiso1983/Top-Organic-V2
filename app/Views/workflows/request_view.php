<?php use App\Core\{Helpers, App}; ?>
<?php $statusLabels = ['draft'=>'مسودة','pending'=>'بانتظار','approved'=>'موافق','rejected'=>'مرفوض','returned_for_edit'=>'معاد للتعديل','cancelled'=>'ملغية']; ?>
<div class="page-head">
  <h2><?= Helpers::e($request['title']) ?></h2>
  <div class="toolbar">
    <a href="/workflows/requests" class="btn btn-secondary">رجوع</a>
    <?php if ($canEdit): ?>
      <a href="/workflows/requests/<?= $request['id'] ?>/edit" class="btn btn-warning">تعديل</a>
    <?php endif; ?>
    <?php if ($canCancel): ?>
      <form method="post" action="/workflows/requests/<?= $request['id'] ?>/cancel" style="display:inline" onsubmit="return confirm('إلغاء الطلب؟')">
        <button class="btn btn-danger">إلغاء</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>تفاصيل الطلب</h3>
    <p><strong>رقم الطلب:</strong> <?= Helpers::e($request['request_no']) ?></p>
    <p><strong>القالب:</strong> <?= Helpers::e($request['template_name']) ?></p>
    <p><strong>الحالة:</strong> <span class="badge badge-<?= $request['status'] ?>"><?= $statusLabels[$request['status']] ?? $request['status'] ?></span></p>
    <p><strong>مقدم الطلب:</strong> <?= Helpers::e($request['requester_name'] ?? '-') ?></p>
    <p><strong>الموقف:</strong> <?= Helpers::e($request['requester_position'] ?? '-') ?></p>
    <p><strong>المبلغ:</strong> <?= number_format((float)$request['amount'], 0) ?> <?= App::config('currency') ?></p>
    <p><strong>تاريخ الإنشاء:</strong> <?= $request['created_at'] ?></p>
  </div>

  <div class="card">
    <h3>بيانات الطلب</h3>
    <?php foreach ($request['fields'] as $f): ?>
      <?php $v = $request['data'][$f['name']] ?? ''; ?>
      <p><strong><?= Helpers::e($f['label']) ?>:</strong>
        <?php if ($f['type'] === 'checkbox'): ?> <?= $v ? 'نعم' : 'لا' ?>
        <?php elseif ($f['type'] === 'file' && $v): ?> <a href="/uploads/<?= Helpers::e((string)$v) ?>" target="_blank">عرض الملف</a>
        <?php else: ?> <?= Helpers::e((string)$v) ?>
        <?php endif; ?>
      </p>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!empty($graph) && $graph['nodes']): ?>
<?php require BASE_PATH . '/app/Views/workflows/_graph.php'; ?>
<?php endif; ?>

<?php if ($steps): ?>
<div class="card">
  <h3>المرحلة الحالية: <?= $request['current_step'] ?></h3>
  <ul>
    <?php foreach ($steps as $s): ?>
    <?php $ch = json_decode($s['notify_channels'] ?? '{}', true); ?>
    <li>
      <?= Helpers::e($s['title']) ?> —
      <?= $s['step_type'] === 'parallel' ? 'خطوة متوازية' : 'خطوة تسلسلية' ?>
      (<?= $s['step_type'] === 'parallel' ? ($s['required_count'] ?: 'الكل') : '1' ?> موافقات)
      <?php if ($ch['email'] ?? 0): ?><i class="fa-solid fa-envelope"></i><?php endif; ?>
      <?php if ($ch['whatsapp'] ?? 0): ?><i class="fa-solid fa-mobile-screen"></i><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if ($canDecide): ?>
<div class="card">
  <h3>اتخذ قرار</h3>
  <form method="post" action="/workflows/requests/<?= $request['id'] ?>/decide">
    <div class="form-row" style="max-width:600px">
      <label>ملاحظات</label>
      <textarea name="notes" rows="2" placeholder="الملاحظات الخاصة بالقرار"></textarea>
    </div>
    <div class="toolbar">
      <button name="decision" value="approved" class="btn btn-success"><i class="fa-solid fa-check"></i> موافقة</button>
      <button name="decision" value="returned_for_edit" class="btn btn-warning"><i class="fa-solid fa-arrow-right-arrow-left"></i> إعادة للتعديل</button>
      <button name="decision" value="rejected" class="btn btn-danger"><i class="fa-solid fa-xmark"></i> رفض</button>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h3>سجل الموافقات والأحداث</h3>
  <table>
    <tr><th>التاريخ</th><th>المستخدم</th><th>الإجراء</th><th>المرحلة</th><th>الجهاز / IP</th><th>الملاحظات</th></tr>
    <?php foreach ($history as $h): ?>
    <tr>
      <td><?= $h['created_at'] ?></td>
      <td><?= Helpers::e($h['actor_name'] ?? $h['actor_real_name'] ?? '-') ?></td>
      <td><span class="badge badge-<?= $h['action'] ?>"><?= $h['action'] ?></span></td>
      <td><?= Helpers::e($h['step_title'] ?? '') ?> <?= $h['step_order'] ? "(#{$h['step_order']})" : '' ?></td>
      <td><small><?= Helpers::e($h['device'] ?? '') ?> <?= !empty($h['ip']) ? '<br>' . Helpers::e($h['ip']) : '' ?></small></td>
      <td><?= Helpers::e($h['notes']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$history): ?><tr><td colspan="5">لا يوجد سجل بعد</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h3>إضافة تعليق</h3>
  <form method="post" action="/workflows/requests/<?= $request['id'] ?>/comment" class="form-inline">
    <div class="form-row" style="min-width:300px"><input name="notes" placeholder="تعليقك" required></div>
    <button class="btn btn-secondary">إرسال تعليق</button>
  </form>
</div>
