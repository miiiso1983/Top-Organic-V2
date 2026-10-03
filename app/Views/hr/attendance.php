<?php use App\Core\Helpers; ?>
<?php
$attQuery = $_SERVER['QUERY_STRING'] ?? '';
$attQueryExcel = $attQuery ? $attQuery . '&format=excel' : 'format=excel';
$attQueryPdf = $attQuery ? $attQuery . '&format=pdf' : 'format=pdf';
?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px">
    <h3 style="margin:0">الحضور والانصراف</h3>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <a href="/hr/attendance/export?<?= Helpers::e($attQueryExcel) ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-file-excel"></i> Excel</a>
      <a href="/hr/attendance/export?<?= Helpers::e($attQueryPdf) ?>" class="btn btn-sm btn-danger"><i class="fa-solid fa-file-pdf"></i> PDF</a>
    </div>
  </div>
  <div style="margin-bottom:12px">
    <a href="/hr/clock" class="btn btn-success">بصمة الحضور/الانصراف عبر المتصفح</a>
  </div>
  <form method="get" action="/hr/attendance" class="form-inline">
    <div class="form-row"><label>اليوم</label><input type="date" name="date" value="<?= Helpers::e($date) ?>"></div>
    <button class="btn">عرض</button>
  </form>
  <p style="color:#6b7280;margin-top:8px"><i class="fa-solid fa-lightbulb"></i> تطبيق الموبايل: <code>POST /api/attendance/check</code></p>
</div>

<div class="card">
  <h4>استيراد سجلات أجهزة البصمة (CSV)</h4>
  <form method="post" action="/hr/attendance/biometric" enctype="multipart/form-data" class="form-inline">
    <div class="form-row"><label>رقم الجهاز</label><input name="device_id" placeholder="ZKT-001"></div>
    <div class="form-row"><label>ملف CSV</label><input type="file" name="import_file" accept=".csv,.txt" required></div>
    <button class="btn btn-primary">استيراد</button>
  </form>
  <small style="color:#6b7280">
    الأعمدة المعتورة: emp_code, date, time, check_type (in/out), device_id.
  </small>
</div>

<div class="card">
  <h3>سجل يوم <?= Helpers::e($date) ?></h3>
  <table>
    <tr><th>الموظف</th><th>القسم</th><th>حضور</th><th>انصراف</th><th>ساعات العمل</th><th>المصدر</th></tr>
    <?php foreach ($records as $r):
        $sourceLabels = ['mobile'=>'موبايل','web'=>'ويب','device'=>'جهاز بصمة','request'=>'طلب نسيان بصمة'];
        $sourceText = [];
        foreach ($r['sources'] as $s) {
            $sourceText[] = $sourceLabels[$s] ?? $s;
        }
        $inLines = [];
        $outLines = [];
        foreach ($r['pairs'] as $p) {
            $inLines[] = Helpers::e($p['in']);
            $outLines[] = Helpers::e($p['out']);
        }
        if (!$inLines) {
            $inLines[] = '-';
            $outLines[] = '-';
        }
    ?>
    <tr>
      <td><?= Helpers::e($r['full_name']) ?></td>
      <td><?= Helpers::e($r['department'] ?? '-') ?></td>
      <td><?= implode('<br>', $inLines) ?></td>
      <td><?= implode('<br>', $outLines) ?></td>
      <td><?= Helpers::e($r['total_hours']) ?></td>
      <td><?= implode(' + ', $sourceText) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$records): ?><tr><td colspan="6">لا توجد سجلات لهذا اليوم</td></tr><?php endif; ?>
  </table>
</div>
