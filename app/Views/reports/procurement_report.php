<?php use App\Core\Helpers; ?>
<?php
$statuses = ['draft'=>'مسودة','pending'=>'بانتظار الموافقة','approved'=>'معتمد','rejected'=>'مرفوض','fulfilling'=>'قيد التلبية','fulfilled'=>'مكتمل','cancelled'=>'ملغى'];
$q = urlencode((string)($filters['q'] ?? ''));
$base = "/reports/procurement/$type?from=" . urlencode($filters['from']) . "&to=" . urlencode($filters['to']) . "&branch_id=" . $filters['branch_id'] . "&department_id=" . $filters['department_id'] . "&supplier_id=" . $filters['supplier_id'] . "&item_id=" . $filters['item_id'] . "&status=" . urlencode($filters['status']) . "&q=$q";
?>
<style>
.report-toolbar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; align-items: end; }
.report-toolbar .form-row { display: flex; flex-direction: column; }
.report-toolbar label { font-size: 12px; color: #4b5563; margin-bottom: 2px; }
.report-table { width: 100%; border-collapse: collapse; }
.report-table th, .report-table td { border: 1px solid #e5e7eb; padding: 8px; text-align: right; }
.report-table th { background: #f3f4f6; }
@media print { .report-toolbar, .no-print { display: none; } }
</style>
<h2><?= Helpers::e($title) ?></h2>
<form method="GET" action="/reports/procurement/<?= $type ?>" class="report-toolbar">
  <div class="form-row"><label>من</label><input type="date" name="from" value="<?= $filters['from'] ?>"></div>
  <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= $filters['to'] ?>"></div>
  <?php if ($type !== 'suppliers' && $type !== 'unavailable' && $type !== 'stagnant'): ?>
  <div class="form-row"><label>الفرع</label>
    <select name="branch_id"><option value="0">الكل</option><?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= $filters['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option><?php endforeach; ?></select>
  </div>
  <?php endif; ?>
  <?php if (in_array($type, ['material_requests','transfer_requests','consumption','transfers'])): ?>
  <div class="form-row"><label>القسم</label>
    <select name="department_id"><option value="0">الكل</option><?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= $filters['department_id'] == $d['id'] ? 'selected' : '' ?>><?= Helpers::e($d['name']) ?></option><?php endforeach; ?></select>
  </div>
  <?php endif; ?>
  <?php if (in_array($type, ['purchase_orders','suppliers','costs','price_analysis'])): ?>
  <div class="form-row"><label>المورد</label>
    <select name="supplier_id"><option value="0">الكل</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>" <?= $filters['supplier_id'] == $s['id'] ? 'selected' : '' ?>><?= Helpers::e($s['name']) ?></option><?php endforeach; ?></select>
  </div>
  <?php endif; ?>
  <?php if (in_array($type, ['material_requests','purchase_requests','top_requested','variances','unavailable','stagnant','consumption','price_analysis','receipts'])): ?>
  <div class="form-row"><label>المادة</label>
    <select name="item_id"><option value="0">الكل</option><?php foreach ($items as $it): ?><option value="<?= $it['id'] ?>" <?= $filters['item_id'] == $it['id'] ? 'selected' : '' ?>><?= Helpers::e($it['name']) ?></option><?php endforeach; ?></select>
  </div>
  <?php endif; ?>
  <?php if (!in_array($type, ['suppliers','top_requested','unavailable','stagnant','price_analysis','cycle_time'])): ?>
  <div class="form-row"><label>الحالة</label>
    <select name="status">
      <option value="">الكل</option>
      <?php foreach ($statuses as $k=>$v): ?><option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <div class="form-row"><label>بحث</label><input type="text" name="q" value="<?= Helpers::e($filters['q']) ?>" placeholder="رقم/اسم..."></div>
  <button class="btn btn-primary">تصفية</button>
  <a class="btn btn-success" href="<?= $base ?>&format=excel">Excel</a>
  <a class="btn btn-danger" href="<?= $base ?>&format=pdf">PDF</a>
  <button class="btn btn-secondary no-print" type="button" onclick="window.print()">طباعة</button>
  <a class="btn btn-light" href="/reports/procurement">← التقارير</a>
</form>
<table class="report-table">
  <tr><th>#</th><?php foreach ($headers as $h): ?><th><?= Helpers::e($h['label']) ?></th><?php endforeach; ?></tr>
  <?php foreach ($rows as $idx => $r): ?>
  <tr>
    <td><?= $idx + 1 ?></td>
    <?php foreach ($headers as $h): ?>
    <td>
      <?php
      $value = $r[$h['key']] ?? '';
      if (($h['format'] ?? '') === 'money') echo Helpers::money($value);
      elseif (($h['format'] ?? '') === 'number') echo number_format((float)$value, 2);
      elseif (($h['format'] ?? '') === 'datetime') echo $value ? date('Y-m-d H:i', strtotime($value)) : '-';
      elseif (($h['format'] ?? '') === 'date') echo $value ? date('Y-m-d', strtotime($value)) : '-';
      else echo Helpers::e((string)$value);
      ?>
    </td>
    <?php endforeach; ?>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="<?= count($headers)+1 ?>">لا توجد بيانات</td></tr><?php endif; ?>
</table>
