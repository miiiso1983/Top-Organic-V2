<?php use App\Core\Helpers; ?>
<?php $entityLabels = ['purchase_request'=>'طلب شراء','leave_request'=>'إجازة','expense'=>'مصروف',
      'salary_advance'=>'سلفة','salary_adjustment'=>'تعديل راتب','stock_transfer'=>'مناقلة مخزنية','quotation'=>'عرض سعر']; ?>
<div class="card">
  <h3>طلبات بانتظار قرار</h3>
  <table>
    <tr><th>#</th><th>النوع</th><th>المبلغ</th><th>مقدم الطلب</th><th>المستوى</th><th>التاريخ</th><th>القرار</th></tr>
    <?php foreach ($pending as $p): ?>
    <tr>
      <td><?= $p['request_no'] ?? ('#' . $p['id']) ?></td>
      <td>
        <?php if ($p['source'] === 'workflow'): ?>
          <?= Helpers::e($p['entity_type']) ?> <small>(<?= Helpers::e($p['title'] ?? '') ?>)</small>
        <?php else: ?>
          <?= $entityLabels[$p['entity_type']] ?? $p['entity_type'] ?> #<?= $p['entity_id'] ?>
        <?php endif; ?>
      </td>
      <td><?= Helpers::money($p['amount'] ?? 0) ?></td>
      <td><?= Helpers::e($p['requester'] ?? '-') ?></td>
      <td><?= $p['source'] === 'workflow' ? Helpers::e($p['step_title'] ?? '') : ('المستوى ' . $p['current_step']) ?></td>
      <td><?= date('m/d H:i', strtotime($p['created_at'])) ?></td>
      <td>
        <?php if ($p['source'] === 'workflow'): ?>
        <a href="/workflows/requests/<?= $p['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-eye"></i> عرض</a>
        <form method="post" action="/workflows/requests/<?= $p['id'] ?>/decide" class="form-inline" style="display:inline-flex">
          <input name="notes" placeholder="ملاحظات">
          <button name="decision" value="approved" class="btn btn-sm btn-success"><i class="fa-solid fa-check"></i> موافقة</button>
          <button name="decision" value="rejected" class="btn btn-sm btn-danger"><i class="fa-solid fa-xmark"></i> رفض</button>
        </form>
        <?php else: ?>
        <a href="/approvals/<?= $p['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-eye"></i> عرض</a>
        <form method="post" action="/approvals/<?= $p['id'] ?>/decide" class="form-inline" style="display:inline-flex">
          <input name="notes" placeholder="ملاحظات">
          <button name="decision" value="approved" class="btn btn-sm btn-success"><i class="fa-solid fa-check"></i> موافقة</button>
          <button name="decision" value="rejected" class="btn btn-sm btn-danger"><i class="fa-solid fa-xmark"></i> رفض</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$pending): ?><tr><td colspan="7"><i class="fa-solid fa-check-double"></i> لا توجد طلبات بانتظارك</td></tr><?php endif; ?>
  </table>
</div>

<?php
$allStatusLabels = ['pending'=>'معلق','approved'=>'معتمد','rejected'=>'مرفوض','returned_for_edit'=>'أعيد للتعديل','cancelled'=>'ملغي','draft'=>'مسودة'];
$sortLink = function (string $col) use ($filters): string {
    $dir = ($filters['sort'] === $col && $filters['dir'] === 'asc') ? 'desc' : 'asc';
    $params = array_merge($filters, ['sort' => $col, 'dir' => $dir, 'page' => 1]);
    return '/approvals?' . http_build_query(array_filter($params, fn ($v) => $v !== ''));
};
$sortIcon = function (string $col) use ($filters): string {
    if ($filters['sort'] !== $col) return '';
    return $filters['dir'] === 'asc' ? ' <i class="fa-solid fa-arrow-up"></i>' : ' <i class="fa-solid fa-arrow-down"></i>';
};
$totalPages = max(1, (int)ceil($totalAll / $perPage));
?>
<div class="card">
  <h3>سجل جميع طلبات الموافقة (<?= $totalAll ?>)</h3>
  <form method="get" action="/approvals" class="form-inline" style="margin-bottom:12px; flex-wrap:wrap; gap:6px">
    <input type="text" name="q" placeholder="بحث باسم الموظف/رقم الطلب/النوع" value="<?= Helpers::e($filters['q']) ?>">
    <select name="status">
      <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>كل الحالات</option>
      <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>معلق</option>
      <option value="approved" <?= $filters['status'] === 'approved' ? 'selected' : '' ?>>معتمد</option>
      <option value="rejected" <?= $filters['status'] === 'rejected' ? 'selected' : '' ?>>مرفوض</option>
      <option value="returned_for_edit" <?= $filters['status'] === 'returned_for_edit' ? 'selected' : '' ?>>أعيد للتعديل</option>
      <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>ملغي</option>
    </select>
    <select name="type">
      <option value="">كل أنواع الطلبات</option>
      <?php foreach ($types as $t): ?>
        <option value="<?= Helpers::e($t['code']) ?>" <?= $filters['type'] === $t['code'] ? 'selected' : '' ?>><?= Helpers::e($t['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <label>من <input type="date" name="from" value="<?= Helpers::e($filters['from']) ?>"></label>
    <label>إلى <input type="date" name="to" value="<?= Helpers::e($filters['to']) ?>"></label>
    <input type="hidden" name="sort" value="<?= Helpers::e($filters['sort']) ?>">
    <input type="hidden" name="dir" value="<?= Helpers::e($filters['dir']) ?>">
    <button class="btn btn-primary"><i class="fa-solid fa-filter"></i> تطبيق</button>
    <a href="/approvals" class="btn btn-light">إلغاء</a>
  </form>
  <table>
    <tr>
      <th>#</th>
      <th>النوع</th>
      <th>المبلغ</th>
      <th><a href="<?= $sortLink('requester') ?>">مقدم الطلب<?= $sortIcon('requester') ?></a></th>
      <th><a href="<?= $sortLink('status') ?>">الحالة<?= $sortIcon('status') ?></a></th>
      <th><a href="<?= $sortLink('created_at') ?>">التاريخ<?= $sortIcon('created_at') ?></a></th>
      <th>إجراء</th>
    </tr>
    <?php foreach ($allRequests as $h): ?>
    <tr>
      <td><?= Helpers::e($h['request_no'] ?? ('#' . $h['id'])) ?></td>
      <td><?= Helpers::e($h['type_label'] ?? $h['type_code']) ?> #<?= $h['entity_id'] ?></td>
      <td><?= Helpers::money($h['amount'] ?? 0) ?></td>
      <td><?= Helpers::e($h['requester'] ?? '-') ?></td>
      <td><span class="badge badge-<?= $h['status'] === 'approved' ? 'success' : ($h['status'] === 'rejected' ? 'danger' : ($h['status'] === 'pending' ? 'warning' : ($h['status'] === 'cancelled' ? 'secondary' : 'warning'))) ?>">
        <?= $allStatusLabels[$h['status']] ?? $h['status'] ?></span></td>
      <td><?= date('Y-m-d H:i', strtotime($h['created_at'])) ?></td>
      <td>
        <?php if ($h['source'] === 'workflow'): ?>
        <a href="/workflows/requests/<?= $h['id'] ?>" class="btn btn-sm btn-secondary">عرض</a>
        <?php else: ?>
        <a href="/approvals/<?= $h['id'] ?>" class="btn btn-sm btn-secondary">عرض</a>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$allRequests): ?><tr><td colspan="7">لا توجد طلبات مطابقة لمعايير البحث</td></tr><?php endif; ?>
  </table>
  <?php if ($totalPages > 1): ?>
  <div class="form-inline" style="margin-top:10px; gap:6px">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="/approvals?<?= http_build_query(array_filter(array_merge($filters, ['page' => $i]), fn ($v) => $v !== '')) ?>"
         class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-light' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
