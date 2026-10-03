<?php use App\Core\Helpers; ?>
<?php $statusLabels = ['pending'=>'بانتظار الموافقة','approved'=>'معتمدة','delivered'=>'مسلّمة','rejected'=>'مرفوضة']; ?>
<div class="card">
  <h3>مناقلة جديدة (تخضع لمسار الموافقات)</h3>
  <form method="post" action="/inventory/transfers" class="form-inline">
    <div class="form-row"><label>من مخزن</label>
      <select name="from_warehouse_id">
        <?php foreach ($warehouses as $w): ?><option value="<?= $w['id'] ?>"><?= Helpers::e($w['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>إلى مخزن</label>
      <select name="to_warehouse_id">
        <?php foreach ($warehouses as $w): ?><option value="<?= $w['id'] ?>"><?= Helpers::e($w['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>المادة</label>
      <select name="item_id">
        <?php foreach ($items as $it): ?><option value="<?= $it['id'] ?>"><?= Helpers::e($it['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الكمية</label><input type="number" name="qty" step="0.001" min="0.001" required></div>
    <div class="form-row"><label>ملاحظات</label><input name="notes"></div>
    <button class="btn btn-success">إنشاء وإرسال للموافقة</button>
  </form>
</div>

<div class="card">
  <h3>المناقلات</h3>
  <table>
    <tr><th>الرقم</th><th>من</th><th>إلى</th><th>الحالة</th><th>التاريخ</th><th></th></tr>
    <?php foreach ($transfers as $t): ?>
    <tr>
      <td><?= Helpers::e($t['transfer_no']) ?></td>
      <td><?= Helpers::e($t['from_name']) ?></td>
      <td><?= Helpers::e($t['to_name']) ?></td>
      <td><span class="badge badge-<?= $t['status'] ?>"><?= $statusLabels[$t['status']] ?></span></td>
      <td><?= date('m/d H:i', strtotime($t['created_at'])) ?></td>
      <td>
        <?php if ($t['status'] === 'approved'): ?>
        <form method="post" action="/inventory/transfers/<?= $t['id'] ?>/deliver">
          <button class="btn btn-sm btn-success">تسليم فعلي</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$transfers): ?><tr><td colspan="6">لا توجد مناقلات</td></tr><?php endif; ?>
  </table>
</div>
