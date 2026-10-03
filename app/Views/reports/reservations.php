<?php use App\Core\Helpers; ?>
<?php
$statusLabels = ['pending'=>'بانتظار التأكيد','confirmed'=>'مؤكد','seated'=>'تم الجلوس','cancelled'=>'ملغي','no_show'=>'لم يحضر','waiting'=>'قائمة انتظار','rejected'=>'مرفوض','completed'=>'مكتمل'];
$d = $data;
?>
<style>
.report-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; margin-bottom: 16px; }
.report-filters .form-row { display: flex; flex-direction: column; gap: 4px; }
.report-filters label { font-size: 12px; color: #6b7280; }
.report-filters input, .report-filters select { padding: 8px; border: 1px solid #e5e7eb; border-radius: 8px; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 16px; }
.stat-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; text-align: center; }
.stat-value { font-size: 22px; font-weight: 700; color: #111827; }
.stat-label { font-size: 12px; color: #6b7280; margin-top: 4px; }
.report-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; margin-bottom: 16px; }
.report-section h3 { margin: 0 0 12px; font-size: 16px; }
.report-section table { width: 100%; border-collapse: collapse; font-size: 13px; }
.report-section th, .report-section td { border: 1px solid #e5e7eb; padding: 8px; text-align: right; }
.report-section th { background: #f3f4f6; }
@media print {
  .report-filters, .no-print { display: none !important; }
  .report-section { break-inside: avoid; }
}
</style>

<div class="report-filters no-print">
  <form method="get" action="/reports/reservations">
    <div class="form-row"><label>من</label><input type="date" name="from" value="<?= Helpers::e($from) ?>"></div>
    <div class="form-row"><label>إلى</label><input type="date" name="to" value="<?= Helpers::e($to) ?>"></div>
    <div class="form-row"><label>الفرع</label>
      <select name="branch_id">
        <option value="0">كل الفروع</option>
        <?php foreach ($branches as $b): ?>
        <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>بحث</label><input type="text" name="q" value="<?= Helpers::e($q) ?>" placeholder="اسم/هاتف/رقم"></div>
    <button class="btn btn-primary">عرض</button>
    <a href="?<?= http_build_query(['from'=>$from,'to'=>$to,'branch_id'=>$branch_id,'q'=>$q,'format'=>'excel']) ?>" class="btn btn-secondary">Excel</a>
    <a href="?<?= http_build_query(['from'=>$from,'to'=>$to,'branch_id'=>$branch_id,'q'=>$q,'format'=>'pdf']) ?>" class="btn btn-secondary">PDF</a>
    <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i> طباعة</button>
  </form>
</div>

<h2>تقرير الحجوزات</h2>
<p style="color:#6b7280;font-size:13px">الفترة: <?= Helpers::e($from) ?> — <?= Helpers::e($to) ?></p>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-value"><?= (int)$d['summary']['total'] ?></div><div class="stat-label">إجمالي الحجوزات</div></div>
  <div class="stat-card"><div class="stat-value"><?= (int)$d['summary']['seated'] ?></div><div class="stat-label">تم الجلوس</div></div>
  <div class="stat-card"><div class="stat-value"><?= (int)$d['summary']['cancelled'] ?></div><div class="stat-label">الملغاة</div></div>
  <div class="stat-card"><div class="stat-value"><?= (int)$d['summary']['no_show'] ?></div><div class="stat-label">No Show</div></div>
  <div class="stat-card"><div class="stat-value"><?= (int)$d['summary']['total_guests'] ?></div><div class="stat-label">إجمالي الضيوف</div></div>
  <div class="stat-card"><div class="stat-value"><?= $d['summary']['occupancy_rate'] ?>%</div><div class="stat-label">نسبة الإشغال</div></div>
  <div class="stat-card"><div class="stat-value"><?= round((int)($d['avgDuration']['avg_minutes'] ?? 0) / 60, 1) ?> س</div><div class="stat-label">متوسط مدة الإشغال</div></div>
  <div class="stat-card"><div class="stat-value"><?= (int)$d['summary']['waiting'] ?></div><div class="stat-label">قائمة الانتظار</div></div>
</div>

<div class="report-section">
  <h3>حجوزات حسب الفرع</h3>
  <table>
    <tr><th>الفرع</th><th>الحجوزات</th><th>الضيوف</th><th>تم الجلوس</th><th>الملغاة</th><th>No Show</th></tr>
    <?php foreach ($d['byBranch'] as $r): ?>
    <tr><td><?= Helpers::e($r['branch_name'] ?: 'بدون فرع') ?></td><td><?= (int)$r['total'] ?></td><td><?= (int)$r['guests'] ?></td><td><?= (int)$r['seated'] ?></td><td><?= (int)$r['cancelled'] ?></td><td><?= (int)$r['no_show'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$d['byBranch']): ?><tr><td colspan="6">لا توجد بيانات</td></tr><?php endif; ?>
  </table>
</div>

<div class="report-section">
  <h3>حجوزات حسب الفترة</h3>
  <table>
    <tr><th>اليوم</th><th>الحجوزات</th><th>الضيوف</th><th>تم الجلوس</th></tr>
    <?php foreach ($d['byPeriod'] as $r): ?>
    <tr><td><?= $r['day'] ?></td><td><?= (int)$r['total'] ?></td><td><?= (int)$r['guests'] ?></td><td><?= (int)$r['seated'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$d['byPeriod']): ?><tr><td colspan="4">لا توجد بيانات</td></tr><?php endif; ?>
  </table>
</div>

<div class="report-section">
  <h3>حجوزات حسب نوع الحجز</h3>
  <table>
    <tr><th>النوع</th><th>الحجوزات</th><th>الضيوف</th><th>تم الجلوس</th><th>الملغاة</th></tr>
    <?php foreach ($d['byType'] as $r): ?>
    <tr><td><?= Helpers::e($r['type_name']) ?></td><td><?= (int)$r['total'] ?></td><td><?= (int)$r['guests'] ?></td><td><?= (int)$r['seated'] ?></td><td><?= (int)$r['cancelled'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$d['byType']): ?><tr><td colspan="5">لا توجد بيانات</td></tr><?php endif; ?>
  </table>
</div>

<div class="report-section">
  <h3>نسبة الإشغال حسب الطاولة</h3>
  <table>
    <tr><th>الطاولة</th><th>الطابق/القسم</th><th>الحجوزات</th><th>تم الجلوس</th><th>الضيوف</th><th>دقائق إشغال</th></tr>
    <?php foreach ($d['occupancy'] as $r): ?>
    <tr><td><?= Helpers::e($r['table_name']) ?></td><td><?= Helpers::e($r['floor']) ?> / <?= Helpers::e($r['section']) ?></td><td><?= (int)$r['reservations'] ?></td><td><?= (int)$r['occupied'] ?></td><td><?= (int)$r['guests'] ?></td><td><?= (int)$r['minutes'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$d['occupancy']): ?><tr><td colspan="6">لا توجد بيانات</td></tr><?php endif; ?>
  </table>
</div>


<div class="report-section">
  <h3>العملاء الأكثر زيارة</h3>
  <table>
    <tr><th>العميل</th><th>الهاتف</th><th>عدد الزيارات</th><th>إجمالي الضيوف</th><th>تم الجلوس</th></tr>
    <?php foreach ($d['topCustomers'] as $r): ?>
    <tr><td><?= Helpers::e($r['customer_name']) ?></td><td><?= Helpers::e($r['customer_phone']) ?></td><td><?= (int)$r['visits'] ?></td><td><?= (int)$r['guests'] ?></td><td><?= (int)$r['seated_visits'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$d['topCustomers']): ?><tr><td colspan="5">لا توجد بيانات</td></tr><?php endif; ?>
  </table>
</div>

<div class="report-section">
  <h3>الحجوزات الملغاة</h3>
  <table>
    <tr><th>الرقم</th><th>العميل</th><th>الهاتف</th><th>الضيوف</th><th>التاريخ</th><th>الفرع</th><th>السبب</th></tr>
    <?php foreach ($d['cancelled'] as $r): ?>
    <tr><td><?= Helpers::e($r['reservation_no']) ?></td><td><?= Helpers::e($r['customer_name']) ?></td><td><?= Helpers::e($r['customer_phone']) ?></td><td><?= (int)$r['guests'] ?></td><td><?= $r['reserved_at'] ?></td><td><?= Helpers::e($r['branch_name'] ?: '-') ?></td><td><?= Helpers::e($r['cancellation_reason'] ?: '-') ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$d['cancelled']): ?><tr><td colspan="7">لا توجد بيانات</td></tr><?php endif; ?>
  </table>
</div>

<div class="report-section">
  <h3>No Show</h3>
  <table>
    <tr><th>الرقم</th><th>العميل</th><th>الهاتف</th><th>الضيوف</th><th>التاريخ</th><th>الفرع</th></tr>
    <?php foreach ($d['noShow'] as $r): ?>
    <tr><td><?= Helpers::e($r['reservation_no']) ?></td><td><?= Helpers::e($r['customer_name']) ?></td><td><?= Helpers::e($r['customer_phone']) ?></td><td><?= (int)$r['guests'] ?></td><td><?= $r['reserved_at'] ?></td><td><?= Helpers::e($r['branch_name'] ?: '-') ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$d['noShow']): ?><tr><td colspan="6">لا توجد بيانات</td></tr><?php endif; ?>
  </table>
</div>

<div class="report-section">
  <h3>أكثر الأوقات ازدحاماً</h3>
  <table>
    <tr><th>الساعة</th><th>الحجوزات</th><th>الضيوف</th></tr>
    <?php foreach ($d['peakHours'] as $r): ?>
    <tr><td><?= (int)$r['hour'] ?>:00</td><td><?= (int)$r['total'] ?></td><td><?= (int)$r['guests'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$d['peakHours']): ?><tr><td colspan="3">لا توجد بيانات</td></tr><?php endif; ?>
  </table>
</div>

<div class="report-section">
  <h3>أداء موظفي الكول سنتر</h3>
  <table>
    <tr><th>الموظف</th><th>الحجوزات</th><th>المؤكدة</th><th>تم الجلوس</th><th>الملغاة</th><th>No Show</th></tr>
    <?php foreach ($d['callCenter'] as $r): ?>
    <tr><td><?= Helpers::e($r['full_name'] ?: 'غير معروف') ?></td><td><?= (int)$r['total'] ?></td><td><?= (int)$r['confirmed'] ?></td><td><?= (int)$r['seated'] ?></td><td><?= (int)$r['cancelled'] ?></td><td><?= (int)$r['no_show'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$d['callCenter']): ?><tr><td colspan="6">لا توجد بيانات</td></tr><?php endif; ?>
  </table>
</div>

<div class="report-section">
  <h3>أداء موظفي الاستقبال</h3>
  <table>
    <tr><th>الموظف</th><th>إجمالي العمليات</th><th>تسجيل دخول</th><th>تسجيل خروج</th><th>إضافة للانتظار</th></tr>
    <?php foreach ($d['reception'] as $r): ?>
    <tr><td><?= Helpers::e($r['full_name'] ?: 'غير معروف') ?></td><td><?= (int)$r['total'] ?></td><td><?= (int)$r['check_ins'] ?></td><td><?= (int)$r['check_outs'] ?></td><td><?= (int)$r['waitlisted'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$d['reception']): ?><tr><td colspan="5">لا توجد بيانات</td></tr><?php endif; ?>
  </table>
</div>

<div class="report-section">
  <h3>تقرير VIP</h3>
  <table>
    <tr><th>الرقم</th><th>العميل</th><th>الهاتف</th><th>الضيوف</th><th>التاريخ</th><th>الحالة</th><th>الفرع</th><th>الطاولة</th></tr>
    <?php foreach ($d['vip'] as $r): ?>
    <tr><td><?= Helpers::e($r['reservation_no']) ?></td><td><?= Helpers::e($r['customer_name']) ?></td><td><?= Helpers::e($r['customer_phone']) ?></td><td><?= (int)$r['guests'] ?></td><td><?= $r['reserved_at'] ?></td><td><?= $statusLabels[$r['status']] ?? $r['status'] ?></td><td><?= Helpers::e($r['branch_name'] ?: '-') ?></td><td><?= Helpers::e($r['table_name'] ?: '-') ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$d['vip']): ?><tr><td colspan="8">لا توجد بيانات</td></tr><?php endif; ?>
  </table>
</div>
