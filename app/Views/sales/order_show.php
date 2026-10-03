<?php use App\Core\Helpers; ?>
<?php
$statusLabels = ['pending'=>'معلق','confirmed'=>'مؤكد','preparing'=>'قيد التحضير','ready'=>'جاهز',
                 'served'=>'مقدم','delivering'=>'قيد التوصيل','completed'=>'مكتمل','cancelled'=>'ملغي'];
?>
<div class="grid grid-2">
  <div class="card">
    <h3>تفاصيل الطلب <?= Helpers::e($order['order_no']) ?> <a href="/orders/<?= $order['id'] ?>/receipt" target="_blank" class="btn btn-sm btn-secondary"><i class="fa-solid fa-print"></i> طباعة الفاتورة</a>
      <a href="/kitchen/order/<?= (int)$order['id'] ?>/receipt" target="_blank" class="btn btn-sm btn-secondary" style="margin-inline-start:4px"><i class="fa-solid fa-print"></i> المطبخ المركزي</a>
      <?php
      $prepMap = [];
      foreach ($items as $it) {
          if (!empty($it['preparation_kitchen_id'])) {
              $prepMap[(int)$it['preparation_kitchen_id']] = $it['preparation_kitchen_name'] ?? 'تحضير';
          }
      }
      foreach ($prepMap as $prepId => $prepName): ?>
        <a href="/kitchen/order/<?= (int)$order['id'] ?>/preparation/<?= (int)$prepId ?>/receipt" target="_blank" class="btn btn-sm btn-secondary" style="margin-inline-start:4px"><i class="fa-solid fa-print"></i> <?= Helpers::e($prepName) ?></a>
      <?php endforeach; ?>
      <?php
      $sectionsMap = [];
      foreach ($items as $it) {
          if (!empty($it['kitchen_section_id'])) {
              $sectionsMap[(int)$it['kitchen_section_id']] = $it['kitchen_section_name'] ?? 'قسم';
          }
      }
      foreach ($sectionsMap as $secId => $secName): ?>
        <a href="/kitchen/section/<?= (int)$secId ?>/receipt" target="_blank" class="btn btn-sm btn-secondary" style="margin-inline-start:4px"><i class="fa-solid fa-print"></i> <?= Helpers::e($secName) ?></a>
      <?php endforeach; ?>
    </h3>
    <?php
    $tableStatusLabels = ['available'=>'فارغة','occupied'=>'مشغولة','reserved'=>'محجوزة','cleaning'=>'تنظيف'];
    $tableStatusClass  = ['available'=>'success','occupied'=>'danger','reserved'=>'warning','cleaning'=>'info'];
    ?>
    <p>الحالة: <span class="badge badge-<?= $order['status'] ?>"><?= $statusLabels[$order['status']] ?></span>
       | الطاولة: <?= $order['table_name'] ? 'طاولة ' . Helpers::cleanName($order['table_name']) : '-' ?> <span class="badge badge-<?= $tableStatusClass[$order['table_status'] ?? 'available'] ?? 'success' ?>"><?= $tableStatusLabels[$order['table_status'] ?? 'available'] ?? 'غير معروفة' ?></span>
       <?= !empty($order['app_type_name']) ? ' | التطبيق: ' . Helpers::e($order['app_type_name']) : '' ?>
       | الكاشير: <?= Helpers::e($order['cashier'] ?? '-') ?></p>

    <?php if (!in_array($order['status'], ['completed','cancelled']) && $order['table_id']): ?>
    <div class="card" style="margin-bottom:12px">
      <h4>نقل الطلب إلى طاولة أخرى</h4>
      <form method="post" action="/orders/<?= $order['id'] ?>/move" class="form-inline" onsubmit="return confirm('نقل الطلب كاملاً إلى طاولة أخرى؟')">
        <div class="form-row" style="margin-bottom:0">
          <select name="target_table_id" required style="min-width:220px">
            <option value="">اختر طاولة فارغة</option>
            <?php foreach ($tables as $tbl):
              $isFree = empty($tbl['current_order']) && ($tbl['table_status'] === 'available');
              $label = 'طاولة ' . Helpers::cleanName($tbl['name']) . ' | ' . ($tbl['floor'] ?? '-') . '/' . ($tbl['hall'] ?? '-')
                     . ' — ' . ($isFree ? 'فارغة' : 'مشغولة')
                     . ($tbl['current_order'] ? ' (' . $tbl['current_order'] . ')' : '');
            ?>
              <option value="<?= $tbl['id'] ?>" <?= $isFree ? '' : 'disabled' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-secondary">نقل</button>
      </form>
      <small class="text-muted">يظهر خيار نقل الطلب كاملاً فقط للطاولات الفارغة.</small>
    </div>
    <?php endif; ?>

    <table style="margin-top:10px">
      <tr>
        <th><input type="checkbox" id="selectAll" title="تحديد الكل"></th>
        <th>الصنف</th><th>الكمية</th><th>السعر</th><th>المجموع</th><th>قسم المطبخ</th>
        <?php if (!in_array($order['status'], ['completed','cancelled'])): ?><th>إجراءات</th><?php endif; ?>
      </tr>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><input type="checkbox" class="split-cb" value="<?= $it['id'] ?>" data-name="<?= Helpers::e($it['name']) ?>"></td>
          <td><?= Helpers::e($it['name']) ?></td>
          <td><?= (float)$it['qty'] ?></td>
          <td><?= Helpers::money($it['unit_price']) ?></td>
          <td><?= Helpers::money($it['total']) ?></td>
          <td><?= Helpers::e($it['kitchen_section_name'] ?? '-') ?></td>
          <?php if (!in_array($order['status'], ['completed','cancelled'])): ?>
          <td>
            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
              <form method="post" action="/orders/<?= $order['id'] ?>/cancel-item"
                    onsubmit="return confirm('تأكيد إلغاء <?= Helpers::e($it['name']) ?>؟')" style="margin:0">
                <input type="hidden" name="order_item_id" value="<?= $it['id'] ?>">
                <button class="btn btn-sm btn-danger">إلغاء</button>
              </form>
              <form method="post" action="/orders/<?= $order['id'] ?>/move-item" style="margin:0; display:flex; gap:4px; align-items:center;"
                    onsubmit="return confirm('نقل <?= Helpers::e($it['name']) ?>؟')">
                <input type="hidden" name="order_item_id" value="<?= $it['id'] ?>">
                <select name="target_table_id" required style="padding:4px 6px; font-size:13px;">
                  <option value="">إلى طاولة</option>
                  <?php foreach ($tables as $tbl):
                    $tblStatus = $tbl['table_status'] ?? 'available';
                    $tblStatusLabel = $tableStatusLabels[$tblStatus] ?? $tblStatus;
                    $tblLabel = 'طاولة ' . Helpers::cleanName($tbl['name']) . ' [' . $tblStatusLabel . ']'
                              . ($tbl['floor'] ? ' | ' . Helpers::e($tbl['floor']) : '')
                              . ($tbl['hall'] ? '/' . Helpers::e($tbl['hall']) : '')
                              . ($tbl['current_order'] ? ' (' . $tbl['current_order'] . ')' : '');
                  ?>
                    <option value="<?= $tbl['id'] ?>"><?= $tblLabel ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-secondary">نقل</button>
              </form>
            </div>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php $summaryColspan = in_array($order['status'], ['completed','cancelled']) ? 5 : 6; ?>
      <tr><th colspan="<?= $summaryColspan ?>">الخصم</th><th><?= Helpers::money($order['discount']) ?></th></tr>
      <tr><th colspan="<?= $summaryColspan ?>">الإجمالي</th><th><?= Helpers::money($order['total']) ?></th></tr>
    </table>

    <?php if (!in_array($order['status'], ['completed','cancelled'])): ?>
    <div id="splitInputs" style="display:none;"></div>
    <form method="post" action="/orders/<?= $order['id'] ?>/split" id="splitForm" style="margin-top:12px; display:flex; gap:10px; align-items:center;">
      <button type="button" class="btn btn-warning" onclick="submitSplit()">تقسيم الأصناف المحددة</button>
      <small class="text-muted">الأصناف المحددة ستنتقل إلى طلب جديد على نفس الطاولة.</small>
    </form>
    <div class="toolbar" style="margin-top:14px">
      <?php foreach (['preparing'=>'قيد التحضير','ready'=>'جاهز','served'=>'مقدم','delivering'=>'قيد التوصيل','completed'=>'مكتمل'] as $st => $label): ?>
        <form method="post" action="/orders/<?= $order['id'] ?>/status">
          <input type="hidden" name="status" value="<?= $st ?>">
          <button class="btn btn-sm"><?= $label ?></button>
        </form>
      <?php endforeach; ?>
      <form method="post" action="/orders/<?= $order['id'] ?>/cancel"
            onsubmit="return confirm('تأكيد إلغاء الطلب؟')">
        <button class="btn btn-sm btn-danger">إلغاء الطلب</button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card">
      <h3>الدفعات</h3>
      <table>
        <tr><th>المبلغ</th><th>الطريقة</th><th>الوقت</th></tr>
        <?php foreach ($payments as $p): ?>
          <tr><td><?= Helpers::money($p['amount']) ?></td>
              <td><?= ['cash'=>'نقدي','card'=>'بطاقة','online'=>'إلكتروني','wallet'=>'محفظة'][$p['method']] ?></td>
              <td><?= date('H:i m/d', strtotime($p['created_at'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td colspan="3">لا توجد دفعات</td></tr><?php endif; ?>
      </table>
      <?php if ($order['payment_status'] !== 'paid' && $order['status'] !== 'cancelled'): ?>
      <form method="post" action="/orders/<?= $order['id'] ?>/pay" class="form-inline" style="margin-top:10px">
        <div class="form-row"><label>المبلغ</label><input type="number" name="amount" min="1" required></div>
        <div class="form-row"><label>الطريقة</label>
          <select name="method"><option value="cash">نقدي</option><option value="card">بطاقة</option></select>
        </div>
        <button class="btn btn-success">تسجيل دفعة</button>
      </form>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>مرتجع صنف</h3>
      <form method="post" action="/orders/<?= $order['id'] ?>/return" class="form-inline">
        <div class="form-row"><label>الصنف</label>
          <select name="order_item_id">
            <?php foreach ($items as $it): ?>
              <option value="<?= $it['id'] ?>"><?= Helpers::e($it['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row"><label>الكمية</label><input type="number" name="qty" value="1" min="0.5" step="0.5"></div>
        <div class="form-row"><label>السبب</label><input type="text" name="reason"></div>
        <button class="btn btn-warning">تسجيل مرتجع</button>
      </form>
    </div>

    <div class="card">
      <h3>سجل الحالات</h3>
      <table>
        <tr><th>الحالة</th><th>بواسطة</th><th>الوقت</th></tr>
        <?php foreach ($log as $l): ?>
          <tr><td><?= $statusLabels[$l['status']] ?? $l['status'] ?></td>
              <td><?= Helpers::e($l['full_name'] ?? '-') ?></td>
              <td><?= date('H:i m/d', strtotime($l['created_at'])) ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>

<script>
function submitSplit() {
    const boxes = document.querySelectorAll('.split-cb:checked');
    if (boxes.length === 0) {
        alert('اختر صنفاً واحداً على الأقل للتقسيم');
        return;
    }
    const container = document.getElementById('splitInputs');
    container.innerHTML = '';
    boxes.forEach(box => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'item_ids[]';
        input.value = box.value;
        container.appendChild(input);
    });
    document.getElementById('splitForm').submit();
}

document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.split-cb').forEach(cb => cb.checked = this.checked);
});
</script>

