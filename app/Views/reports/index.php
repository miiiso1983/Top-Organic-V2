<?php use App\Core\Helpers; ?>
<?php $movementLabels = ['in'=>'إدخال','out'=>'إخراج','transfer_in'=>'مناقلة واردة','transfer_out'=>'مناقلة صادرة','adjustment'=>'تعديل','consumption'=>'استهلاك','return'=>'مرتجع'];
$exportUrl = function (string $tab, string $format) use ($filters): string {
    $qs = $filters;
    $qs['format'] = $format;
    return '/reports/export/' . $tab . '?' . http_build_query($qs);
};
?>
<style>
.dashboard-tabs { display:flex; gap:8px; border-bottom:1px solid var(--border-light); padding-bottom:12px; margin-bottom:16px; flex-wrap:wrap; }
.dashboard-tabs .tab-btn { background: var(--surface-alt); color: var(--text); border:1px solid var(--border-light); }
.dashboard-tabs .tab-btn.active { background: var(--brand); color: #fff; border-color: var(--brand); }
.tab-panel { display:none; }
.tab-panel.active { display:block; }
.chart-wrap { position:relative; height:320px; margin-bottom:20px; }
</style>

<div class="card">
  <form method="get" action="/reports" class="form-inline" id="dashboard-filter">
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($filters['from']) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($filters['to']) ?>"></div>
    <div class="form-row">
      <label>الفرع</label>
      <select name="branch_id"><option value="">كل الفروع</option><?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= $filters['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="form-row">
      <label>الفئة</label>
      <select name="category_id"><option value="">كل الفئات</option><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $filters['category_id'] == $c['id'] ? 'selected' : '' ?>><?= Helpers::e($c['name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="form-row">
      <label>الموظف</label>
      <select name="employee_id"><option value="">كل الموظفين</option><?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>" <?= $filters['employee_id'] == $e['id'] ? 'selected' : '' ?>><?= Helpers::e($e['full_name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="form-row">
      <label>حالة الطلب</label>
      <select name="status"><option value="">الكل</option><?php foreach ($statuses as $k => $v): ?><option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
    </div>
    <div class="form-row">
      <label>نوع الطلب</label>
      <select name="order_type"><option value="">الكل</option><?php foreach ($orderTypes as $k => $v): ?><option value="<?= $k ?>" <?= $filters['order_type'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
    </div>
    <button class="btn">تحديث</button>
  </form>
  <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
    <?php
    $quick = function (string $from, string $to) use ($filters): string {
        $qs = $filters;
        $qs['from'] = $from;
        $qs['to'] = $to;
        return '?' . http_build_query($qs);
    };
    $today = date('Y-m-d');
    ?>
    <a href="<?= $quick($today, $today) ?>" class="btn btn-sm btn-secondary">اليوم</a>
    <a href="<?= $quick(date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))) ?>" class="btn btn-sm btn-secondary">الأمس</a>
    <a href="<?= $quick(date('Y-m-d', strtotime('-6 days')), $today) ?>" class="btn btn-sm btn-secondary">آخر 7 أيام</a>
    <a href="<?= $quick(date('Y-m-01'), $today) ?>" class="btn btn-sm btn-secondary">هذا الشهر</a>
  </div>
</div>

<div class="grid grid-4">
  <div class="stat"><div class="value"><?= Helpers::money($kpis['total_sales']) ?></div><div class="label">إجمالي المبيعات</div></div>
  <div class="stat"><div class="value"><?= (int)$kpis['orders_count'] ?></div><div class="label">عدد الطلبات</div></div>
  <div class="stat"><div class="value"><?= Helpers::money($kpis['profit']) ?></div><div class="label">الربح</div></div>
  <div class="stat"><div class="value"><?= Helpers::money($kpis['avg_order']) ?></div><div class="label">متوسط الطلب</div></div>
  <div class="stat"><div class="value"><?= Helpers::money($kpis['inventory_value']) ?></div><div class="label">قيمة المخزون</div></div>
  <div class="stat"><div class="value"><?= Helpers::money($kpis['expenses']) ?></div><div class="label">المصروفات</div></div>
  <div class="stat"><div class="value"><?= Helpers::money($kpis['cost']) ?></div><div class="label">كلفة البضاعة</div></div>
</div>

<div class="card">
  <div class="dashboard-tabs">
    <button type="button" class="btn tab-btn active" data-tab="sales">المبيعات</button>
    <button type="button" class="btn tab-btn" data-tab="inventory">المخزون</button>
    <button type="button" class="btn tab-btn" data-tab="hr">الموارد البشرية</button>
    <button type="button" class="btn tab-btn" data-tab="expenses">المصروفات</button>
  </div>

  <div id="tab-sales" class="tab-panel active">
    <div class="grid grid-2">
      <div class="card"><h3>المبيعات اليومية</h3><div class="chart-wrap"><canvas id="chart-sales-date"></canvas></div></div>
      <div class="card"><h3>حسب طريقة الدفع</h3><div class="chart-wrap"><canvas id="chart-sales-payment"></canvas></div></div>
    </div>
    <div class="grid grid-2">
      <div class="card"><h3>حسب نوع الطلب</h3><div class="chart-wrap"><canvas id="chart-sales-type"></canvas></div></div>
      <div class="card">
        <h3>ملخص الضريبة</h3>
        <div class="grid grid-3">
          <div class="stat"><div class="value"><?= (int)$vatSummary['taxable_orders'] ?></div><div class="label">طلبات خاضعة للضريبة</div></div>
          <div class="stat"><div class="value"><?= Helpers::money($vatSummary['total_vat']) ?></div><div class="label">إجمالي الضريبة</div></div>
          <div class="stat"><div class="value"><?= Helpers::money($vatSummary['avg_vat']) ?></div><div class="label">متوسط الضريبة</div></div>
        </div>
      </div>
    </div>
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <h3>الأصناف الأكثر مبيعاً</h3>
        <div class="toolbar">
          <a href="<?= $exportUrl('sales', 'excel') ?>" class="btn btn-sm btn-secondary">Excel</a>
          <a href="<?= $exportUrl('sales', 'pdf') ?>" class="btn btn-sm btn-secondary">PDF</a>
          <a href="<?= $exportUrl('sales', 'csv') ?>" class="btn btn-sm btn-secondary">CSV</a>
        </div>
      </div>
      <table class="data-table">
        <thead><tr><th>الصنف</th><th>الكمية</th><th>الإيراد</th></tr></thead>
        <tbody><?php foreach ($topItems as $i): ?><tr><td><?= Helpers::e($i['name']) ?></td><td><?= (float)$i['qty'] ?></td><td><?= Helpers::money($i['total']) ?></td></tr><?php endforeach; ?><?php if (!$topItems): ?><tr><td colspan="3">لا توجد بيانات</td></tr><?php endif; ?></tbody>
      </table>
    </div>
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <h3>أداء الكابتن</h3>
        <div class="toolbar">
          <a href="<?= $exportUrl('sales', 'excel') ?>" class="btn btn-sm btn-secondary">Excel</a>
          <a href="<?= $exportUrl('sales', 'pdf') ?>" class="btn btn-sm btn-secondary">PDF</a>
          <a href="<?= $exportUrl('sales', 'csv') ?>" class="btn btn-sm btn-secondary">CSV</a>
        </div>
      </div>
      <table class="data-table">
        <thead><tr><th>الكابتن</th><th>عدد الطلبات</th><th>إجمالي المبيعات</th></tr></thead>
        <tbody><?php foreach ($captainPerformance as $c): ?><tr><td><?= Helpers::e($c['captain_name'] ?? '-') ?> <?= $c['emp_code'] ? '(' . Helpers::e($c['emp_code']) . ')' : '' ?></td><td><?= (int)$c['orders_count'] ?></td><td><?= Helpers::money($c['total_sales']) ?></td></tr><?php endforeach; ?><?php if (!$captainPerformance): ?><tr><td colspan="3">لا توجد بيانات</td></tr><?php endif; ?></tbody>
      </table>
    </div>
  </div>

  <div id="tab-inventory" class="tab-panel">
    <div class="grid grid-2">
      <div class="card"><h3>قيمة المخزون حسب التصنيف</h3><div class="chart-wrap"><canvas id="chart-inventory-value"></canvas></div></div>
      <div class="card"><h3>حركات المخزون</h3><div class="chart-wrap"><canvas id="chart-inventory-movement"></canvas></div></div>
    </div>
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <h3>قيمة المخزون حسب التصنيف</h3>
        <div class="toolbar">
          <a href="<?= $exportUrl('inventory', 'excel') ?>" class="btn btn-sm btn-secondary">Excel</a>
          <a href="<?= $exportUrl('inventory', 'pdf') ?>" class="btn btn-sm btn-secondary">PDF</a>
          <a href="<?= $exportUrl('inventory', 'csv') ?>" class="btn btn-sm btn-secondary">CSV</a>
        </div>
      </div>
      <table class="data-table">
        <thead><tr><th>التصنيف</th><th>عدد الأصناف</th><th>القيمة</th></tr></thead>
        <tbody><?php foreach ($stockValueByCategory as $v): ?><tr><td><?= Helpers::e($v['name']) ?></td><td><?= (int)$v['items_count'] ?></td><td><?= Helpers::money($v['total_value']) ?></td></tr><?php endforeach; ?><?php if (!$stockValueByCategory): ?><tr><td colspan="3">لا توجد بيانات</td></tr><?php endif; ?></tbody>
      </table>
    </div>
    <div class="card">
      <h3>حركات المخزون</h3>
      <table class="data-table">
        <thead><tr><th>النوع</th><th>عدد الحركات</th><th>الكمية</th></tr></thead>
        <tbody><?php foreach ($inventoryMovement as $m): ?><tr><td><?= $movementLabels[$m['movement_type']] ?? $m['movement_type'] ?></td><td><?= (int)$m['movements_count'] ?></td><td><?= (float)$m['qty'] ?></td></tr><?php endforeach; ?><?php if (!$inventoryMovement): ?><tr><td colspan="3">لا توجد حركات</td></tr><?php endif; ?></tbody>
      </table>
    </div>
  </div>

  <div id="tab-hr" class="tab-panel">
    <div class="grid grid-2">
      <div class="card"><h3>أداء الموظفين</h3><div class="chart-wrap"><canvas id="chart-hr-performance"></canvas></div></div>
      <div class="card">
        <h3>ملخص الحضور</h3>
        <div class="grid grid-2">
          <div class="stat"><div class="value"><?= count(array_filter($hrAttendanceSummary, fn($a) => $a['check_ins'] > 0)) ?></div><div class="label">موظفون حضروا</div></div>
          <div class="stat"><div class="value"><?= count(array_filter($hrAttendanceSummary, fn($a) => $a['check_ins'] == 0)) ?></div><div class="label">موظفون بدون حضور</div></div>
        </div>
      </div>
    </div>
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <h3>الحضور والانصراف</h3>
        <div class="toolbar">
          <a href="<?= $exportUrl('hr', 'excel') ?>" class="btn btn-sm btn-secondary">Excel</a>
          <a href="<?= $exportUrl('hr', 'pdf') ?>" class="btn btn-sm btn-secondary">PDF</a>
          <a href="<?= $exportUrl('hr', 'csv') ?>" class="btn btn-sm btn-secondary">CSV</a>
        </div>
      </div>
      <table class="data-table">
        <thead><tr><th>الموظف</th><th>القسم</th><th>أول حضور</th><th>آخر انصراف</th><th>عدد الحضور</th></tr></thead>
        <tbody><?php foreach ($hrAttendanceSummary as $a): ?><tr><td><?= Helpers::e($a['full_name']) ?></td><td><?= Helpers::e($a['department'] ?? '-') ?></td><td><?= $a['first_in'] ? date('H:i', strtotime($a['first_in'])) : '-' ?></td><td><?= $a['last_out'] ? date('H:i', strtotime($a['last_out'])) : '-' ?></td><td><?= (int)$a['check_ins'] ?></td></tr><?php endforeach; ?><?php if (!$hrAttendanceSummary): ?><tr><td colspan="5">لا يوجد موظفون</td></tr><?php endif; ?></tbody>
      </table>
    </div>
    <div class="card">
      <h3>أداء الموظفين</h3>
      <table class="data-table">
        <thead><tr><th>الموظف</th><th>القسم</th><th>متوسط التقييم</th><th>مهام منجزة</th></tr></thead>
        <tbody><?php foreach ($employeePerformance as $p): ?><tr><td><?= Helpers::e($p['full_name']) ?></td><td><?= Helpers::e($p['department'] ?? '-') ?></td><td><?= number_format((float)$p['avg_score'], 1) ?></td><td><?= (int)$p['tasks_completed'] ?></td></tr><?php endforeach; ?><?php if (!$employeePerformance): ?><tr><td colspan="4">لا توجد بيانات</td></tr><?php endif; ?></tbody>
      </table>
    </div>
  </div>

  <div id="tab-expenses" class="tab-panel">
    <div class="grid grid-2">
      <div class="card"><h3>المصروفات حسب التصنيف</h3><div class="chart-wrap"><canvas id="chart-expenses"></canvas></div></div>
      <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
          <h3>المصروفات حسب التصنيف</h3>
          <div class="toolbar">
            <a href="<?= $exportUrl('expenses', 'excel') ?>" class="btn btn-sm btn-secondary">Excel</a>
            <a href="<?= $exportUrl('expenses', 'pdf') ?>" class="btn btn-sm btn-secondary">PDF</a>
            <a href="<?= $exportUrl('expenses', 'csv') ?>" class="btn btn-sm btn-secondary">CSV</a>
          </div>
        </div>
        <table class="data-table">
          <thead><tr><th>التصنيف</th><th>عدد العمليات</th><th>المبلغ</th></tr></thead>
          <tbody><?php foreach ($expensesByCategory as $e): ?><tr><td><?= Helpers::e($e['category']) ?></td><td><?= (int)$e['expenses_count'] ?></td><td><?= Helpers::money($e['total']) ?></td></tr><?php endforeach; ?><?php if (!$expensesByCategory): ?><tr><td colspan="3">لا توجد مصروفات</td></tr><?php endif; ?></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
$typeLabels = ['dine_in'=>'صالة','takeaway'=>'سفري','delivery'=>'توصيل','online'=>'إلكتروني'];
$methodLabels = ['cash'=>'نقدي','card'=>'بطاقة','online'=>'إلكتروني','wallet'=>'محفظة'];
$movementLabels = ['in'=>'إدخال','out'=>'إخراج','transfer_in'=>'مناقلة واردة','transfer_out'=>'مناقلة صادرة','adjustment'=>'تعديل','consumption'=>'استهلاك','return'=>'مرتجع'];
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
(function(){
  const typeLabels = <?= json_encode($typeLabels) ?>;
  const methodLabels = <?= json_encode($methodLabels) ?>;
  const movementLabels = <?= json_encode($movementLabels) ?>;
  const palette = ['#2E7D32','#66BB6A','#1B5E20','#A5D6A7','#8BC34A','#d97706','#dc2626','#2563eb'];

  document.querySelectorAll('.tab-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      const tab = this.dataset.tab;
      document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
      document.querySelectorAll('.tab-panel').forEach(function(p){ p.classList.remove('active'); });
      this.classList.add('active');
      document.getElementById('tab-' + tab).classList.add('active');
    });
  });

  function initDataTable($table) {
    if ($table.data('dt-initialized')) return;
    $table.data('dt-initialized', true);
    $table.DataTable({
      autoWidth: false,
      pageLength: 10,
      lengthMenu: [[5,10,25,50,-1],[5,10,25,50,'الكل']],
      language: { search: 'بحث:', lengthMenu: 'عرض _MENU_ سجل', info: 'من _START_ إلى _END_ من _TOTAL_', infoEmpty: 'لا توجد سجلات', zeroRecords: 'لا توجد نتائج', paginate: { first: 'الأول', last: 'الأخير', next: 'التالي', previous: 'السابق' } }
    });
  }
  function initTablesInTab(tab) {
    document.getElementById('tab-' + tab).querySelectorAll('.data-table').forEach(function(t){
      initDataTable($(t));
    });
  }
  initTablesInTab(document.querySelector('.tab-btn.active').dataset.tab);

  document.querySelectorAll('.tab-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      const tab = this.dataset.tab;
      document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
      document.querySelectorAll('.tab-panel').forEach(function(p){ p.classList.remove('active'); });
      this.classList.add('active');
      document.getElementById('tab-' + tab).classList.add('active');
      initTablesInTab(tab);
      setTimeout(function(){
        document.getElementById('tab-' + tab).querySelectorAll('.data-table').forEach(function(t){
          $(t).DataTable().columns.adjust().draw();
        });
      }, 10);
    });
  });

  const salesDateLabels = <?= json_encode(array_column($salesByDate, 'd')) ?>;
  const salesDateData = <?= json_encode(array_map(fn($d)=>(float)$d['total'], $salesByDate)) ?>;
  new Chart(document.getElementById('chart-sales-date'), { type: 'line', data: { labels: salesDateLabels, datasets: [{ label: 'المبيعات', data: salesDateData, borderColor: '#2E7D32', backgroundColor: 'rgba(46,125,50,0.1)', fill: true, tension: 0.3 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } } } });

  const paymentLabels = <?= json_encode(array_map(fn($p)=>$methodLabels[$p['method']] ?? $p['method'], $salesByPaymentMethod)) ?>;
  const paymentData = <?= json_encode(array_map(fn($p)=>(float)$p['total'], $salesByPaymentMethod)) ?>;
  new Chart(document.getElementById('chart-sales-payment'), { type: 'bar', data: { labels: paymentLabels, datasets: [{ label: 'المبيعات', data: paymentData, backgroundColor: palette }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } } });

  const typeLabelsData = <?= json_encode(array_map(fn($t)=>$typeLabels[$t['order_type']] ?? $t['order_type'], $salesByOrderType)) ?>;
  const typeData = <?= json_encode(array_map(fn($t)=>(float)$t['total'], $salesByOrderType)) ?>;
  new Chart(document.getElementById('chart-sales-type'), { type: 'doughnut', data: { labels: typeLabelsData, datasets: [{ data: typeData, backgroundColor: palette }] }, options: { responsive: true, maintainAspectRatio: false } });

  const invValueLabels = <?= json_encode(array_map(fn($v)=>$v['name'], $stockValueByCategory)) ?>;
  const invValueData = <?= json_encode(array_map(fn($v)=>(float)$v['total_value'], $stockValueByCategory)) ?>;
  new Chart(document.getElementById('chart-inventory-value'), { type: 'pie', data: { labels: invValueLabels, datasets: [{ data: invValueData, backgroundColor: palette }] }, options: { responsive: true, maintainAspectRatio: false } });

  const movementLabelsData = <?= json_encode(array_map(fn($m)=>$movementLabels[$m['movement_type']] ?? $m['movement_type'], $inventoryMovement)) ?>;
  const movementData = <?= json_encode(array_map(fn($m)=>(float)$m['qty'], $inventoryMovement)) ?>;
  new Chart(document.getElementById('chart-inventory-movement'), { type: 'bar', data: { labels: movementLabelsData, datasets: [{ label: 'الكمية', data: movementData, backgroundColor: palette }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } } });

  const perfLabels = <?= json_encode(array_map(fn($p)=>$p['full_name'], $employeePerformance)) ?>;
  const perfScore = <?= json_encode(array_map(fn($p)=>(float)$p['avg_score'], $employeePerformance)) ?>;
  const perfTasks = <?= json_encode(array_map(fn($p)=>(int)$p['tasks_completed'], $employeePerformance)) ?>;
  new Chart(document.getElementById('chart-hr-performance'), { type: 'bar', data: { labels: perfLabels, datasets: [{ label: 'متوسط التقييم', data: perfScore, backgroundColor: '#2563eb' }, { label: 'مهام منجزة', data: perfTasks, backgroundColor: '#66BB6A' }] }, options: { responsive: true, maintainAspectRatio: false } });

  const expenseLabels = <?= json_encode(array_map(fn($e)=>$e['category'], $expensesByCategory)) ?>;
  const expenseData = <?= json_encode(array_map(fn($e)=>(float)$e['total'], $expensesByCategory)) ?>;
  new Chart(document.getElementById('chart-expenses'), { type: 'pie', data: { labels: expenseLabels, datasets: [{ data: expenseData, backgroundColor: palette }] }, options: { responsive: true, maintainAspectRatio: false } });
})();
</script>
