<?php use App\Core\Auth; ?>
<div class="page-head">
  <h2>سير العمل والموافقات</h2>
  <div class="toolbar">
    <?php if (Auth::can('workflow.create')): ?>
      <a href="/workflows/new" class="btn btn-success">طلب جديد</a>
    <?php endif; ?>
    <?php if (Auth::can('workflow.manage')): ?>
      <a href="/workflows/templates" class="btn btn-secondary">إدارة القوالب</a>
    <?php endif; ?>
    <a href="/workflows/inbox" class="btn btn-primary"><i class="fa-solid fa-inbox"></i> صندوق الموافقات</a>
    <a href="/workflows/requests" class="btn">كل الطلبات</a>
  </div>
</div>

<div class="grid grid-3">
  <div class="card stat">
    <div class="value"><?= count($pending) ?></div>
    <div class="label">بانتظار موافقتك</div>
  </div>
  <div class="card stat">
    <div class="value"><?= count($myRequests) ?></div>
    <div class="label">طلباتي المقدمة</div>
  </div>
  <div class="card stat">
    <div class="value"><?= count($visible) ?></div>
    <div class="label">الطلبات المرئية</div>
  </div>
</div>

<?php if ($pending): ?>
<div class="card">
  <h3>طلبات بانتظار قرارك</h3>
  <table>
    <tr><th>#</th><th>القالب</th><th>العنوان</th><th>مقدم الطلب</th><th>المرحلة</th><th>المبلغ</th><th></th></tr>
    <?php foreach ($pending as $p): ?>
    <tr>
      <td><?= $p['request_no'] ?></td>
      <td><?= htmlspecialchars($p['template_name']) ?></td>
      <td><?= htmlspecialchars($p['title']) ?></td>
      <td><?= htmlspecialchars($p['requester_name'] ?? '-') ?></td>
      <td><?= htmlspecialchars($p['step']['title'] ?? '') ?></td>
      <td><?= number_format((float)$p['amount'], 0) ?></td>
      <td><a href="/workflows/requests/<?= $p['id'] ?>" class="btn btn-sm">عرض</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <h3>أخر طلباتي</h3>
  <table>
    <tr><th>#</th><th>القالب</th><th>العنوان</th><th>الحالة</th><th>التاريخ</th></tr>
    <?php foreach (array_slice($myRequests, 0, 8) as $r): ?>
    <tr>
      <td><?= $r['request_no'] ?></td>
      <td><?= htmlspecialchars($r['template_name']) ?></td>
      <td><a href="/workflows/requests/<?= $r['id'] ?>"><?= htmlspecialchars($r['title']) ?></a></td>
      <td><span class="badge badge-<?= $r['status'] ?>"><?= htmlspecialchars($r['status']) ?></span></td>
      <td><?= $r['created_at'] ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$myRequests): ?><tr><td colspan="5">لا توجد طلبات بعد</td></tr><?php endif; ?>
  </table>
</div>
