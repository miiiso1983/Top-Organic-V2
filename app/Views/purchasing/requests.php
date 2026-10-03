<?php use App\Core\Helpers; ?>
<?php $statusLabels = ['pending'=>'بانتظار الموافقة','approved'=>'معتمد','rejected'=>'مرفوض','converted'=>'حُوّل لأمر شراء']; ?>
<div class="card">
  <h3>طلب شراء جديد (من الأقسام)</h3>
  <form method="post" action="/purchasing/requests" class="form-inline">
    <div class="form-row"><label>القسم الطالب</label><input name="department" placeholder="مثال: المطبخ" required></div>
    <div class="form-row"><label>المادة</label>
      <select name="item_id">
        <option value="">— مادة جديدة —</option>
        <?php foreach ($items as $it): ?><option value="<?= $it['id'] ?>"><?= Helpers::e($it['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>اسم المادة الجديدة</label><input name="new_item_name" placeholder="إن لم تكن موجودة"></div>
    <div class="form-row"><label>الكمية</label><input type="number" name="qty" step="0.001" min="0.001" required></div>
    <div class="form-row"><label>ملاحظات المادة</label><input name="item_notes"></div>
    <div class="form-row"><label>ملاحظات عامة</label><input name="notes"></div>
    <button class="btn btn-success">إرسال للموافقة</button>
  </form>
</div>

<div class="card">
  <h3>طلبات الشراء</h3>
  <table>
    <tr><th>الرقم</th><th>القسم</th><th>عدد المواد</th><th>الحالة</th><th>الطالب</th><th>التاريخ</th></tr>
    <?php foreach ($requests as $r): ?>
    <tr>
      <td><?= Helpers::e($r['request_no']) ?></td>
      <td><?= Helpers::e($r['department'] ?? '-') ?></td>
      <td><?= $r['items_count'] ?></td>
      <td><span class="badge badge-<?= $r['status'] === 'approved' || $r['status'] === 'converted' ? 'success' : ($r['status'] === 'rejected' ? 'danger' : 'warning') ?>">
        <?= $statusLabels[$r['status']] ?? $r['status'] ?></span></td>
      <td><?= Helpers::e($r['requester'] ?? '-') ?></td>
      <td><?= date('m/d H:i', strtotime($r['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$requests): ?><tr><td colspan="6">لا توجد طلبات شراء</td></tr><?php endif; ?>
  </table>
</div>
