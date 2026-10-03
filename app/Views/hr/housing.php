<?php use App\Core\{Helpers, ImportHelper}; ?>
<?php $importErrors = ImportHelper::getErrors(); ?>
<?php
  $totalBeds = array_sum(array_column($rooms, 'bed_capacity'));
  $occupiedBedsAll = array_sum(array_column($rooms, 'occupied_beds'));
  $availableBedsAll = max(0, $totalBeds - $occupiedBedsAll);
  $siteColors = ['#2E7D32', '#1565C0', '#B8860B', '#6A1B9A', '#C62828', '#00838F', '#5D4037', '#37474F'];
  $siteColorMap = [];
  foreach ($sites as $i => $s) { $siteColorMap[(int)$s['id']] = $siteColors[$i % count($siteColors)]; }
?>
<style>
.hsg-hero { background: linear-gradient(135deg, var(--brand-dark), var(--brand)); border-radius: 14px; padding: 22px 26px; margin-bottom: 20px; color: #fff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; box-shadow: 0 4px 14px var(--shadow-strong); }
.hsg-hero h2 { margin: 0; font-size: 22px; display: flex; align-items: center; gap: 10px; }
.hsg-hero p { margin: 6px 0 0; opacity: .9; font-size: 13px; }
.hsg-hero .btn { background: rgba(255,255,255,.18); color: #fff; backdrop-filter: blur(2px); }
.hsg-hero .btn:hover { background: rgba(255,255,255,.3); }

.hsg-stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; margin-bottom: 20px; }
@media (max-width: 1100px) { .hsg-stats { grid-template-columns: repeat(2, 1fr); } }
.hsg-stat-card { background: var(--surface); border-radius: 12px; padding: 16px 18px; box-shadow: 0 1px 3px var(--shadow); display: flex; align-items: center; gap: 12px; border-right: 4px solid var(--brand); }
.hsg-stat-card .hsg-stat-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #fff; flex-shrink: 0; }
.hsg-stat-card .hsg-stat-value { font-size: 22px; font-weight: 800; color: var(--text); line-height: 1.1; }
.hsg-stat-card .hsg-stat-label { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

.hsg-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
.hsg-card { background: var(--surface); border-radius: 14px; padding: 22px; box-shadow: 0 1px 4px var(--shadow); margin-bottom: 0; }
.hsg-card-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
.hsg-card-title { display: flex; align-items: center; gap: 10px; margin: 0; font-size: 16px; font-weight: 800; }
.hsg-card-title .hsg-icon-badge { width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; }
.hsg-card-sub { font-size: 12.5px; color: var(--text-muted); margin: -8px 0 16px; line-height: 1.7; }

.hsg-import-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 900px) { .hsg-import-row { grid-template-columns: 1fr; } }
.hsg-import-box { border: 1px dashed var(--border); border-radius: 12px; padding: 16px; background: var(--surface-alt); }
.hsg-import-box h5 { margin: 0 0 6px; font-size: 14px; display: flex; align-items: center; gap: 8px; }
.hsg-import-box .hsg-import-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }

.hsg-toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.hsg-search-wrap { position: relative; }
.hsg-search-wrap i { position: absolute; top: 50%; transform: translateY(-50%); right: 12px; color: var(--text-muted); font-size: 12px; }
.hsg-search-wrap input { padding-right: 32px; min-width: 240px; }
.hsg-count-badge { background: var(--info-bg); color: var(--info-text); border-radius: 999px; padding: 3px 10px; font-size: 12px; font-weight: 700; }

.hsg-table-wrap { overflow-x: auto; border: 1px solid var(--border-light); border-radius: 12px; }
#hsgAssignmentsTable { width: 100%; border-collapse: collapse; }
#hsgAssignmentsTable thead th { position: sticky; top: 0; background: var(--surface-alt); z-index: 2; font-size: 12.5px; font-weight: 700; color: var(--text-muted); padding: 12px 10px; border-bottom: 2px solid var(--border-light); white-space: nowrap; }
#hsgAssignmentsTable tbody td { padding: 10px; border-bottom: 1px solid var(--border-light); font-size: 13.5px; vertical-align: middle; }
#hsgAssignmentsTable tbody tr { transition: background .12s; }
#hsgAssignmentsTable tbody tr:hover td { background: var(--surface-alt); }
#hsgAssignmentsTable tbody tr.hsg-row-selected td { background: var(--info-bg); }
.hsg-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--brand); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
.hsg-emp-cell { display: flex; align-items: center; gap: 10px; }
.hsg-emp-name { font-weight: 700; color: var(--text); }
.hsg-emp-code { font-size: 11.5px; color: var(--text-muted); }
.hsg-site-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.hsg-site-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.hsg-money { font-weight: 700; color: var(--brand-dark); }
[data-theme="dark"] .hsg-money { color: var(--brand-light); }
.hsg-actions-cell { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: nowrap; }
.hsg-actions-cell .btn { padding: 6px 10px; }
.hsg-empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }
.hsg-empty-state i { font-size: 34px; margin-bottom: 10px; display: block; opacity: .5; }

.hsg-bulkbar { display: none; align-items: center; justify-content: space-between; background: var(--info-bg); color: var(--info-text); border-radius: 10px; padding: 10px 16px; margin-bottom: 12px; font-size: 13.5px; font-weight: 700; }
.hsg-bulkbar.active { display: flex; }

.hsg-site-block { border: 1px solid var(--border-light); border-radius: 12px; margin-bottom: 14px; overflow: hidden; }
.hsg-site-block > summary { cursor: pointer; padding: 14px 18px; background: var(--surface-alt); font-weight: 700; font-size: 14.5px; list-style: none; display: flex; justify-content: space-between; align-items: center; }
.hsg-site-block > summary::-webkit-details-marker { display: none; }
.hsg-site-block > summary::before { content: '\f107'; font-family: 'Font Awesome 6 Free'; font-weight: 900; margin-left: 10px; transition: transform .15s; color: var(--text-muted); }
.hsg-site-block[open] > summary::before { transform: rotate(180deg); }
.hsg-site-body { padding: 16px 18px; }
.hsg-progress { height: 6px; border-radius: 999px; background: var(--border-light); overflow: hidden; width: 90px; }
.hsg-progress-bar { height: 100%; background: var(--brand); }
.hsg-apt-block { border: 1px solid var(--border-light); border-radius: 10px; margin-bottom: 10px; padding: 12px 14px; background: var(--surface-alt); }
.hsg-apt-head { display: flex; justify-content: space-between; align-items: center; font-weight: 700; margin-bottom: 10px; font-size: 13.5px; }
.hsg-room-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-radius: 8px; background: var(--surface); border: 1px solid var(--border-light); margin-bottom: 6px; font-size: 13px; }
.hsg-badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.hsg-badge-full { background: var(--danger-bg); color: var(--danger-text); }
.hsg-badge-avail { background: var(--success-bg); color: var(--success-text); }
.hsg-inline-form { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 8px; }
.hsg-inline-form input { max-width: 140px; }
</style>

<div class="hsg-hero">
  <div>
    <h2><i class="fa-solid fa-house-chimney-user"></i> إدارة السكن</h2>
    <p>إدارة مواقع السكن، الشقق والغرف، وتسكين الموظفين ومتابعة استقطاعاتهم الشهرية</p>
  </div>
  <a href="/module/hr" class="btn">← العودة إلى الموارد البشرية</a>
</div>

<?php if ($importErrors): ?>
<div class="alert alert-danger">
  <strong>أخطاء استيراد بيانات السكن:</strong>
  <ul><?php foreach ($importErrors as $err): ?><li><?= Helpers::e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<!-- ===== إحصائيات سريعة ===== -->
<div class="hsg-stats">
  <div class="hsg-stat-card" style="border-color:#2E7D32">
    <div class="hsg-stat-icon" style="background:#2E7D32"><i class="fa-solid fa-users"></i></div>
    <div><div class="hsg-stat-value"><?= count($assignments) ?></div><div class="hsg-stat-label">موظف مسكَّن حالياً</div></div>
  </div>
  <div class="hsg-stat-card" style="border-color:#1565C0">
    <div class="hsg-stat-icon" style="background:#1565C0"><i class="fa-solid fa-city"></i></div>
    <div><div class="hsg-stat-value"><?= count($sites) ?></div><div class="hsg-stat-label">مواقع السكن</div></div>
  </div>
  <div class="hsg-stat-card" style="border-color:#6A1B9A">
    <div class="hsg-stat-icon" style="background:#6A1B9A"><i class="fa-solid fa-door-open"></i></div>
    <div><div class="hsg-stat-value"><?= count($apartments) ?></div><div class="hsg-stat-label">إجمالي الشقق</div></div>
  </div>
  <div class="hsg-stat-card" style="border-color:#B8860B">
    <div class="hsg-stat-icon" style="background:#B8860B"><i class="fa-solid fa-bed"></i></div>
    <div><div class="hsg-stat-value"><?= $occupiedBedsAll ?>/<?= $totalBeds ?></div><div class="hsg-stat-label">أسرّة مشغولة/الإجمالي</div></div>
  </div>
  <div class="hsg-stat-card" style="border-color:#00838F">
    <div class="hsg-stat-icon" style="background:#00838F"><i class="fa-solid fa-bed-pulse"></i></div>
    <div><div class="hsg-stat-value"><?= $availableBedsAll ?></div><div class="hsg-stat-label">أسرّة متاحة</div></div>
  </div>
</div>

<div class="hsg-grid">

  <!-- ===== تسكين موظف جديد ===== -->
  <div class="hsg-card">
    <div class="hsg-card-head">
      <h4 class="hsg-card-title"><span class="hsg-icon-badge" style="background:#2E7D32"><i class="fa-solid fa-user-plus"></i></span> تسكين موظف جديد</h4>
    </div>
    <form method="post" action="/hr/housing/assign" class="form-inline" id="hsgAssignForm">
      <div class="form-row" style="min-width:220px">
        <label>الموظف</label>
        <select name="employee_id" required style="width:100%">
          <option value="">اختر موظفاً</option>
          <?php foreach ($employees as $e): ?>
            <option value="<?= (int)$e['id'] ?>">
              <?= Helpers::e($e['full_name']) ?> (<?= Helpers::e($e['emp_code']) ?>) — <?= Helpers::e($e['department_name'] ?? '-') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label>نوع السكن (الموقع)</label>
        <select name="site_id" id="hsgSiteSelect" required>
          <option value="">اختر الموقع</option>
          <?php foreach ($sites as $s): if ($s['name'] === 'سكن خارجي') continue; ?>
            <option value="<?= (int)$s['id'] ?>" data-deduction="<?= (float)$s['monthly_deduction'] ?>"><?= Helpers::e($s['name']) ?> (استقطاع افتراضي: <?= Helpers::money($s['monthly_deduction']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label>رقم الشقة</label>
        <select name="apartment_id" id="hsgApartmentSelect" disabled>
          <option value="">اختر الموقع أولاً</option>
        </select>
      </div>
      <div class="form-row">
        <label>رقم الغرفة</label>
        <select name="room_id" id="hsgRoomSelect" disabled>
          <option value="">اختر الشقة أولاً</option>
        </select>
      </div>
      <div class="form-row">
        <label>مبلغ الاستقطاع الشهري</label>
        <input type="number" step="0.01" min="0" name="deduction_amount" id="hsgDeductionInput" placeholder="يُملأ تلقائياً من الموقع">
      </div>
      <div class="form-row">
        <label>تاريخ التسكين</label>
        <input type="date" name="assigned_date" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-row" style="min-width:180px">
        <label>ملاحظات</label>
        <input name="notes" placeholder="اختياري">
      </div>
      <button class="btn btn-success"><i class="fa-solid fa-check"></i> تسكين</button>
    </form>
    <p class="hsg-card-sub" style="margin-top:12px;margin-bottom:0"><i class="fa-solid fa-circle-info"></i> يمكن التسكين على مستوى الموقع فقط، أو تحديد الشقة والغرفة عند توفر السرير. تسكين موظف جديد ينهي تلقائياً أي تسكين سابق نشط له، ويُحدَّث تلقائياً حقل "استقطاع خدمات السكن الشهري" في ملف الموظف حسب المبلغ المحدد.</p>
  </div>

  <!-- ===== استيراد بيانات السكن من Excel ===== -->
  <div class="hsg-card">
    <div class="hsg-card-head">
      <h4 class="hsg-card-title"><span class="hsg-icon-badge" style="background:#1565C0"><i class="fa-solid fa-file-excel"></i></span> استيراد جماعي من Excel</h4>
    </div>
    <div class="hsg-import-row">
      <div class="hsg-import-box">
        <h5><i class="fa-solid fa-users-rectangle"></i> تسكين الموظفين</h5>
        <p class="hsg-card-sub" style="margin:6px 0 0"><i class="fa-solid fa-circle-info"></i> الأعمدة: كود الموظف، اسم الموظف (مرجعي)، السكن، رقم الشقة، رقم الغرفة، مبلغ الاستقطاع. عند ترك مبلغ الاستقطاع فارغاً يُستخدم المبلغ الافتراضي للموقع. الشقق والغرف غير الموجودة تُنشأ تلقائياً.</p>
        <div class="hsg-import-actions">
          <a href="/hr/housing/import/template" class="btn btn-sm"><i class="fa-solid fa-download"></i> تنزيل النموذج</a>
          <form method="post" action="/hr/housing/import" enctype="multipart/form-data" style="display:inline-flex;gap:6px;align-items:center" onsubmit="return confirm('سيتم تسكين/نقل الموظفين وتحديث استقطاعات السكن حسب بيانات الملف المرفوع. هل تريد المتابعة؟')">
            <input type="file" name="import_file" accept=".xlsx,.xls,.csv" required style="max-width:180px">
            <button class="btn btn-sm btn-primary"><i class="fa-solid fa-upload"></i> رفع واستيراد</button>
          </form>
        </div>
      </div>
      <div class="hsg-import-box">
        <h5><i class="fa-solid fa-building"></i> هيكلية السكن (شقق/غرف)</h5>
        <p class="hsg-card-sub" style="margin:6px 0 0"><i class="fa-solid fa-circle-info"></i> الأعمدة: اسم السكن، رقم الشقة، رقم الغرفة، عدد الأسرة. يُنشئ/يحدّث الشقق والغرف فقط دون تسكين أي موظف.</p>
        <div class="hsg-import-actions">
          <a href="/hr/housing/structure/template" class="btn btn-sm"><i class="fa-solid fa-download"></i> تنزيل النموذج</a>
          <form method="post" action="/hr/housing/structure/import" enctype="multipart/form-data" style="display:inline-flex;gap:6px;align-items:center" onsubmit="return confirm('سيتم إنشاء/تحديث الشقق والغرف حسب بيانات الملف المرفوع. هل تريد المتابعة؟')">
            <input type="file" name="structure_file" accept=".xlsx,.xls,.csv" required style="max-width:180px">
            <button class="btn btn-sm btn-primary"><i class="fa-solid fa-upload"></i> رفع واستيراد</button>
          </form>
        </div>
      </div>
    </div>

    <div class="hsg-table-wrap" style="margin-top:16px">
    <table>
      <thead>
      <tr><th>اسم السكن</th><th>عدد الشقق</th><th>الغرف المتاحة / الكلي</th><th>عدد الأسرّة الكلية</th><th>الأسرّة المتاحة / الكلي</th></tr>
      </thead>
      <tbody>
      <?php foreach ($sites as $s):
          if ($s['name'] === 'سكن خارجي') continue;
          $siteApts = $apartmentsBySite[(int)$s['id']] ?? [];
          $totalRooms = 0; $availableRooms = 0; $totalBedsS = 0; $occBedsS = 0;
          foreach ($siteApts as $sa) {
              foreach (($roomsByApartment[(int)$sa['id']] ?? []) as $sr) {
                  $totalRooms++;
                  $cap = (int)$sr['bed_capacity']; $occ = (int)$sr['occupied_beds'];
                  $totalBedsS += $cap; $occBedsS += $occ;
                  if ($occ < $cap) { $availableRooms++; }
              }
          }
          $availBedsS = max(0, $totalBedsS - $occBedsS);
          $sc = $siteColorMap[(int)$s['id']] ?? '#2E7D32';
      ?>
      <tr>
        <td><span class="hsg-site-chip" style="background:<?= $sc ?>1a;color:<?= $sc ?>"><span class="hsg-site-dot" style="background:<?= $sc ?>"></span> <?= Helpers::e($s['name']) ?></span></td>
        <td><?= count($siteApts) ?></td>
        <td><span class="hsg-badge <?= $availableRooms > 0 ? 'hsg-badge-avail' : 'hsg-badge-full' ?>"><?= $availableRooms ?> / <?= $totalRooms ?></span></td>
        <td><strong><?= $totalBedsS ?></strong></td>
        <td><span class="hsg-badge <?= $availBedsS > 0 ? 'hsg-badge-avail' : 'hsg-badge-full' ?>"><?= $availBedsS ?> / <?= $totalBedsS ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$sites): ?>
      <tr><td colspan="5"><div class="hsg-empty-state"><i class="fa-solid fa-city"></i>لا توجد مواقع سكن مضافة بعد</div></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>

  <!-- ===== قائمة الموظفين المسكّنين حالياً ===== -->
  <div class="hsg-card">
    <div class="hsg-card-head">
      <h4 class="hsg-card-title"><span class="hsg-icon-badge" style="background:#C62828"><i class="fa-solid fa-users"></i></span> الموظفون المسكَّنون حالياً <span class="hsg-count-badge"><?= count($assignments) ?></span></h4>
      <div class="hsg-toolbar">
        <div class="hsg-search-wrap">
          <input type="text" id="hsgAssignSearch" placeholder="بحث: الاسم، الكود، القسم، الموقع...">
          <i class="fa-solid fa-magnifying-glass"></i>
        </div>
        <a href="/hr/housing/export" class="btn btn-sm btn-success"><i class="fa-solid fa-file-excel"></i> تصدير Excel</a>
      </div>
    </div>

    <div class="hsg-bulkbar" id="hsgBulkBar">
      <span><i class="fa-solid fa-check-double"></i> تم تحديد <span id="hsgSelectedCount">0</span> موظف</span>
      <div style="display:flex;gap:8px">
        <button type="button" class="btn btn-sm" id="hsgClearSelection">إلغاء التحديد</button>
        <button type="button" class="btn btn-sm btn-danger" id="hsgBulkDeleteBtn"><i class="fa-solid fa-trash"></i> حذف المحدد</button>
      </div>
    </div>

    <div class="hsg-table-wrap">
    <table id="hsgAssignmentsTable">
      <thead>
      <tr>
        <th style="width:36px"><input type="checkbox" id="hsgSelectAll"></th>
        <th>الموظف</th><th>القسم</th><th>الوظيفة</th><th>الموقع</th><th>الشقة</th><th>الغرفة</th><th>الاستقطاع</th><th>تاريخ التسكين</th><th style="text-align:left">الإجراءات</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($assignments as $a): $sc = $siteColorMap[(int)$a['site_id']] ?? '#2E7D32'; ?>
      <tr class="hsg-assign-row" data-id="<?= (int)$a['id'] ?>">
        <td><input type="checkbox" class="hsg-row-check" value="<?= (int)$a['id'] ?>"></td>
        <td>
          <div class="hsg-emp-cell">
            <span class="hsg-avatar"><?= Helpers::e(mb_substr($a['full_name'], 0, 1)) ?></span>
            <div>
              <div class="hsg-emp-name"><?= Helpers::e($a['full_name']) ?></div>
              <div class="hsg-emp-code"><?= Helpers::e($a['emp_code']) ?></div>
            </div>
          </div>
        </td>
        <td><?= Helpers::e($a['department_name'] ?? '-') ?></td>
        <td><?= Helpers::e($a['job_title'] ?? '-') ?></td>
        <td><span class="hsg-site-chip" style="background:<?= $sc ?>1a;color:<?= $sc ?>"><span class="hsg-site-dot" style="background:<?= $sc ?>"></span> <?= Helpers::e($a['site_name']) ?></span></td>
        <td><?= Helpers::e($a['apartment_number'] ?? '-') ?></td>
        <td><?= Helpers::e($a['room_number'] ?? '-') ?></td>
        <td><span class="hsg-money"><?= Helpers::money($a['deduction_amount'] ?? 0) ?></span></td>
        <td><?= Helpers::e($a['assigned_date']) ?></td>
        <td>
          <div class="hsg-actions-cell">
            <button type="button" class="btn btn-sm btn-primary hsg-edit-btn"
                    data-id="<?= (int)$a['id'] ?>"
                    data-site-id="<?= (int)$a['site_id'] ?>"
                    data-apartment-id="<?= (int)($a['apartment_id'] ?? 0) ?>"
                    data-room-id="<?= (int)($a['room_id'] ?? 0) ?>"
                    data-deduction="<?= (float)($a['deduction_amount'] ?? 0) ?>"
                    data-assigned-date="<?= Helpers::e($a['assigned_date']) ?>"
                    data-notes="<?= Helpers::e($a['notes'] ?? '') ?>"
                    data-employee="<?= Helpers::e($a['full_name']) ?>" title="تعديل">
              <i class="fa-solid fa-pen"></i>
            </button>
            <form method="post" action="/hr/housing/assignments/<?= (int)$a['id'] ?>/end" onsubmit="return confirm('تأكيد حذف/إنهاء تسكين هذا الموظف؟')">
              <button class="btn btn-sm btn-danger" title="حذف"><i class="fa-solid fa-trash"></i></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$assignments): ?>
      <tr><td colspan="10"><div class="hsg-empty-state"><i class="fa-solid fa-house-circle-xmark"></i>لا يوجد موظفون مسكَّنون حالياً</div></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>

  <!-- ===== نموذج حذف جماعي مخفي (يُبنى ويُرسل عبر JS) ===== -->
  <form method="post" action="/hr/housing/assignments/bulk-end" id="hsgBulkDeleteForm" style="display:none"></form>

  <!-- ===== نافذة تعديل التسكين (مشتركة لكل الصفوف) ===== -->
  <div id="hsgEditModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--surface);color:var(--text);border-radius:12px;padding:20px;max-width:520px;width:92%;max-height:90vh;overflow-y:auto">
      <h4 style="margin-top:0"><i class="fa-solid fa-pen"></i> تعديل تسكين: <span id="hsgEditEmpName"></span></h4>
      <form method="post" id="hsgEditForm">
        <div class="form-row">
          <label>نوع السكن (الموقع)</label>
          <select name="site_id" id="hsgEditSiteSelect" required style="width:100%">
            <?php foreach ($sites as $s): if ($s['name'] === 'سكن خارجي') continue; ?>
              <option value="<?= (int)$s['id'] ?>" data-deduction="<?= (float)$s['monthly_deduction'] ?>"><?= Helpers::e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label>رقم الشقة</label>
          <select name="apartment_id" id="hsgEditApartmentSelect" style="width:100%">
            <option value="">بدون تحديد شقة</option>
          </select>
        </div>
        <div class="form-row">
          <label>رقم الغرفة</label>
          <select name="room_id" id="hsgEditRoomSelect" style="width:100%">
            <option value="">بدون تحديد غرفة</option>
          </select>
        </div>
        <div class="form-row">
          <label>مبلغ الاستقطاع الشهري</label>
          <input type="number" step="0.01" min="0" name="deduction_amount" id="hsgEditDeductionInput">
        </div>
        <div class="form-row">
          <label>تاريخ التسكين</label>
          <input type="date" name="assigned_date" id="hsgEditAssignedDate">
        </div>
        <div class="form-row">
          <label>ملاحظات</label>
          <input type="text" name="notes" id="hsgEditNotes">
        </div>
        <div style="display:flex;gap:8px;margin-top:12px;justify-content:flex-end">
          <button type="button" class="btn" onclick="document.getElementById('hsgEditModal').style.display='none'">إلغاء</button>
          <button type="submit" class="btn btn-success"><i class="fa-solid fa-save"></i> حفظ التعديل</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ===== إعدادات هيكلية السكن: المواقع -> الشقق -> الغرف -> الأسرّة ===== -->
  <div class="hsg-card">
    <div class="hsg-card-head">
      <h4 class="hsg-card-title"><span class="hsg-icon-badge" style="background:#6A1B9A"><i class="fa-solid fa-sitemap"></i></span> إعدادات هيكلية السكن</h4>
    </div>
    <p class="hsg-card-sub"><i class="fa-solid fa-circle-info"></i> كل موقع سكن يحتوي على شقق، وكل شقة تحتوي على غرف، ولكل غرفة عدد أسرّة (سعة) يُحسب منها العدد المتاح تلقائياً.</p>

    <?php foreach ($sites as $s):
        if ($s['name'] === 'سكن خارجي') continue;
        $siteApartments = $apartmentsBySite[(int)$s['id']] ?? [];
        $sc = $siteColorMap[(int)$s['id']] ?? '#2E7D32';
        $siteBedsTotal = 0; $siteBedsOcc = 0;
        foreach ($siteApartments as $sa) {
            foreach (($roomsByApartment[(int)$sa['id']] ?? []) as $sr) {
                $siteBedsTotal += (int)$sr['bed_capacity'];
                $siteBedsOcc += (int)$sr['occupied_beds'];
            }
        }
        $sitePct = $siteBedsTotal > 0 ? round(($siteBedsOcc / $siteBedsTotal) * 100) : 0;
    ?>
    <details class="hsg-site-block">
      <summary>
        <span><span class="hsg-site-dot" style="background:<?= $sc ?>;display:inline-block;margin-left:6px"></span><?= Helpers::e($s['name']) ?> <span style="color:var(--text-muted);font-weight:400">(<?= count($siteApartments) ?> شقة — استقطاع افتراضي: <?= Helpers::money($s['monthly_deduction']) ?>)</span></span>
        <span style="display:flex;align-items:center;gap:8px">
          <span class="hsg-progress"><span class="hsg-progress-bar" style="width:<?= $sitePct ?>%;background:<?= $sc ?>"></span></span>
          <small style="color:var(--text-muted)"><?= $siteBedsOcc ?>/<?= $siteBedsTotal ?></small>
        </span>
      </summary>
      <div class="hsg-site-body">
        <form method="post" action="/hr/housing/sites/<?= (int)$s['id'] ?>/deduction" class="hsg-inline-form" style="margin-bottom:12px;padding-bottom:10px;border-bottom:1px dashed var(--border-light)">
          <label style="font-weight:600">مبلغ الاستقطاع الافتراضي لهذا الموقع</label>
          <input type="number" step="0.01" min="0" name="monthly_deduction" value="<?= (float)$s['monthly_deduction'] ?>">
          <button class="btn btn-sm btn-primary"><i class="fa-solid fa-save"></i> حفظ</button>
        </form>
        <?php foreach ($siteApartments as $apt):
            $aptRooms = $roomsByApartment[(int)$apt['id']] ?? [];
            $totalBedsApt = array_sum(array_column($aptRooms, 'bed_capacity'));
            $occupiedBedsApt = array_sum(array_column($aptRooms, 'occupied_beds'));
        ?>
        <div class="hsg-apt-block">
          <div class="hsg-apt-head">
            <span><i class="fa-solid fa-door-closed"></i> شقة رقم <?= Helpers::e($apt['apartment_number']) ?>
              <small style="font-weight:400;color:var(--text-muted)">(<?= $occupiedBedsApt ?>/<?= $totalBedsApt ?> سرير مشغول)</small>
            </span>
            <form method="post" action="/hr/housing/apartments/<?= (int)$apt['id'] ?>/delete" onsubmit="return confirm('حذف الشقة وجميع غرفها؟')">
              <button class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i></button>
            </form>
          </div>

          <?php foreach ($aptRooms as $r):
              $available = max(0, (int)$r['bed_capacity'] - (int)$r['occupied_beds']);
          ?>
          <div class="hsg-room-row">
            <span><i class="fa-solid fa-bed"></i> غرفة <?= Helpers::e($r['room_number']) ?></span>
            <span style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
              <span class="hsg-badge <?= $available > 0 ? 'hsg-badge-avail' : 'hsg-badge-full' ?>">
                <?= $available > 0 ? ($available . ' متاح') : 'مكتملة' ?>
              </span>
              <form method="post" action="/hr/housing/rooms/<?= (int)$r['id'] ?>/update" style="display:flex;gap:4px;align-items:center;margin:0" title="تعديل عدد الأسرّة">
                <input type="number" name="bed_capacity" min="<?= (int)$r['occupied_beds'] ?>" value="<?= (int)$r['bed_capacity'] ?>" style="width:58px;padding:4px 6px;font-size:12px">
                <button class="btn btn-sm btn-primary" title="حفظ عدد الأسرّة"><i class="fa-solid fa-save"></i></button>
              </form>
              <form method="post" action="/hr/housing/rooms/<?= (int)$r['id'] ?>/delete" style="display:inline;margin:0" onsubmit="return confirm('حذف الغرفة؟')">
                <button class="btn btn-sm btn-danger" title="حذف الغرفة"><i class="fa-solid fa-trash"></i></button>
              </form>
            </span>
          </div>
          <?php endforeach; ?>
          <?php if (!$aptRooms): ?><p style="color:var(--text-muted);font-size:12px">لا توجد غرف مضافة بعد</p><?php endif; ?>

          <form method="post" action="/hr/housing/rooms" class="hsg-inline-form">
            <input type="hidden" name="apartment_id" value="<?= (int)$apt['id'] ?>">
            <input name="room_number" placeholder="رقم الغرفة" required>
            <input type="number" name="bed_capacity" min="1" value="1" placeholder="عدد الأسرّة" required>
            <input name="notes" placeholder="ملاحظات (اختياري)">
            <button class="btn btn-sm"><i class="fa-solid fa-plus"></i> إضافة غرفة</button>
          </form>
        </div>
        <?php endforeach; ?>
        <?php if (!$siteApartments): ?><p style="color:var(--text-muted);font-size:13px">لا توجد شقق مضافة بعد في هذا الموقع</p><?php endif; ?>

        <form method="post" action="/hr/housing/apartments" class="hsg-inline-form" style="margin-top:10px;border-top:1px dashed var(--border-light);padding-top:10px">
          <input type="hidden" name="site_id" value="<?= (int)$s['id'] ?>">
          <input name="apartment_number" placeholder="رقم الشقة" required>
          <input name="notes" placeholder="ملاحظات (اختياري)">
          <button class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> إضافة شقة إلى <?= Helpers::e($s['name']) ?></button>
        </form>
      </div>
    </details>
    <?php endforeach; ?>
  </div>

</div>

<script>
(function() {
  var siteSelect = document.getElementById('hsgSiteSelect');
  var apartmentSelect = document.getElementById('hsgApartmentSelect');
  var roomSelect = document.getElementById('hsgRoomSelect');
  var deductionInput = document.getElementById('hsgDeductionInput');

  function resetSelect(el, placeholder, disabled) {
    el.innerHTML = '<option value="">' + placeholder + '</option>';
    el.disabled = disabled;
    if (window.reinitSelect2) window.reinitSelect2(el);
  }

  siteSelect.addEventListener('change', function() {
    resetSelect(roomSelect, 'اختر الشقة أولاً', true);
    var selectedOpt = siteSelect.options[siteSelect.selectedIndex];
    deductionInput.value = selectedOpt ? (selectedOpt.getAttribute('data-deduction') || '') : '';
    var siteId = siteSelect.value;
    if (!siteId) { resetSelect(apartmentSelect, 'اختر الموقع أولاً', true); return; }
    resetSelect(apartmentSelect, 'جاري التحميل...', true);
    fetch('/hr/housing/apartments-by-site/' + siteId)
      .then(function(r) { return r.json(); })
      .then(function(res) {
        var data = res.data || [];
        apartmentSelect.innerHTML = '<option value="">بدون تحديد شقة (تسكين على مستوى الموقع)</option>';
        data.forEach(function(a) {
          var opt = document.createElement('option');
          opt.value = a.id;
          opt.textContent = 'شقة رقم ' + a.apartment_number;
          apartmentSelect.appendChild(opt);
        });
        apartmentSelect.disabled = false;
        if (window.reinitSelect2) window.reinitSelect2(apartmentSelect);
      });
  });

  apartmentSelect.addEventListener('change', function() {
    var apartmentId = apartmentSelect.value;
    if (!apartmentId) { resetSelect(roomSelect, 'اختر الشقة أولاً', true); return; }
    resetSelect(roomSelect, 'جاري التحميل...', true);
    fetch('/hr/housing/rooms-by-apartment/' + apartmentId)
      .then(function(r) { return r.json(); })
      .then(function(res) {
        var data = res.data || [];
        roomSelect.innerHTML = '<option value="">بدون تحديد غرفة</option>';
        data.forEach(function(r) {
          var opt = document.createElement('option');
          opt.value = r.id;
          opt.textContent = 'غرفة ' + r.room_number + ' — متاح ' + r.available_beds + ' من ' + r.bed_capacity;
          if (r.available_beds <= 0) { opt.disabled = true; opt.textContent += ' (مكتملة)'; }
          roomSelect.appendChild(opt);
        });
        roomSelect.disabled = false;
        if (window.reinitSelect2) window.reinitSelect2(roomSelect);
      });
  });
})();

(function() {
  // ===== بحث فوري في جدول الموظفين المسكّنين =====
  var searchInput = document.getElementById('hsgAssignSearch');
  var rows = document.querySelectorAll('#hsgAssignmentsTable .hsg-assign-row');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      var q = searchInput.value.trim().toLowerCase();
      rows.forEach(function(row) {
        row.style.display = row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
      });
    });
  }

  // ===== تحديد الكل + شريط الإجراءات الجماعية + حذف جماعي =====
  var selectAll = document.getElementById('hsgSelectAll');
  var rowChecks = document.querySelectorAll('.hsg-row-check');
  var bulkBar = document.getElementById('hsgBulkBar');
  var selectedCountEl = document.getElementById('hsgSelectedCount');
  var clearSelectionBtn = document.getElementById('hsgClearSelection');
  var bulkDeleteBtn = document.getElementById('hsgBulkDeleteBtn');
  var bulkDeleteForm = document.getElementById('hsgBulkDeleteForm');

  function updateBulkBar() {
    var checked = document.querySelectorAll('.hsg-row-check:checked');
    var count = checked.length;
    selectedCountEl.textContent = count;
    bulkBar.classList.toggle('active', count > 0);
    checked.forEach(function(cb) {
      var tr = cb.closest('tr');
      if (tr) tr.classList.add('hsg-row-selected');
    });
    document.querySelectorAll('.hsg-row-check:not(:checked)').forEach(function(cb) {
      var tr = cb.closest('tr');
      if (tr) tr.classList.remove('hsg-row-selected');
    });
    var visibleChecks = Array.prototype.filter.call(rowChecks, function(cb) {
      var tr = cb.closest('tr');
      return tr && tr.style.display !== 'none';
    });
    selectAll.checked = visibleChecks.length > 0 && visibleChecks.every(function(cb) { return cb.checked; });
  }

  if (selectAll) {
    selectAll.addEventListener('change', function() {
      rowChecks.forEach(function(cb) {
        var tr = cb.closest('tr');
        if (!tr || tr.style.display === 'none') return;
        cb.checked = selectAll.checked;
      });
      updateBulkBar();
    });
  }
  rowChecks.forEach(function(cb) { cb.addEventListener('change', updateBulkBar); });

  if (clearSelectionBtn) {
    clearSelectionBtn.addEventListener('click', function() {
      rowChecks.forEach(function(cb) { cb.checked = false; });
      if (selectAll) selectAll.checked = false;
      updateBulkBar();
    });
  }

  if (bulkDeleteBtn) {
    bulkDeleteBtn.addEventListener('click', function() {
      var checked = document.querySelectorAll('.hsg-row-check:checked');
      if (!checked.length) return;
      if (!confirm('تأكيد حذف/إنهاء تسكين ' + checked.length + ' موظف؟')) return;
      bulkDeleteForm.innerHTML = '';
      checked.forEach(function(cb) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = cb.value;
        bulkDeleteForm.appendChild(input);
      });
      bulkDeleteForm.submit();
    });
  }

  // ===== نافذة تعديل التسكين =====
  var modal = document.getElementById('hsgEditModal');
  var editForm = document.getElementById('hsgEditForm');
  var editSite = document.getElementById('hsgEditSiteSelect');
  var editApartment = document.getElementById('hsgEditApartmentSelect');
  var editRoom = document.getElementById('hsgEditRoomSelect');
  var editDeduction = document.getElementById('hsgEditDeductionInput');
  var editDate = document.getElementById('hsgEditAssignedDate');
  var editNotes = document.getElementById('hsgEditNotes');
  var editEmpName = document.getElementById('hsgEditEmpName');
  var currentAssignmentId = null;

  function resetEditSelect(el, placeholder) {
    el.innerHTML = '<option value="">' + placeholder + '</option>';
    if (window.reinitSelect2) window.reinitSelect2(el);
  }

  function loadEditApartments(siteId, selectedAptId, selectedRoomId) {
    resetEditSelect(editApartment, 'بدون تحديد شقة');
    resetEditSelect(editRoom, 'بدون تحديد غرفة');
    if (!siteId) return;
    fetch('/hr/housing/apartments-by-site/' + siteId)
      .then(function(r) { return r.json(); })
      .then(function(res) {
        var data = res.data || [];
        editApartment.innerHTML = '<option value="">بدون تحديد شقة</option>';
        data.forEach(function(a) {
          var opt = document.createElement('option');
          opt.value = a.id;
          opt.textContent = 'شقة رقم ' + a.apartment_number;
          if (selectedAptId && parseInt(a.id, 10) === parseInt(selectedAptId, 10)) opt.selected = true;
          editApartment.appendChild(opt);
        });
        if (window.reinitSelect2) window.reinitSelect2(editApartment);
        if (selectedAptId) loadEditRooms(selectedAptId, selectedRoomId);
      });
  }

  function loadEditRooms(apartmentId, selectedRoomId) {
    resetEditSelect(editRoom, 'بدون تحديد غرفة');
    if (!apartmentId) return;
    var url = '/hr/housing/rooms-by-apartment/' + apartmentId + (currentAssignmentId ? '?exclude_id=' + currentAssignmentId : '');
    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(res) {
        var data = res.data || [];
        editRoom.innerHTML = '<option value="">بدون تحديد غرفة</option>';
        data.forEach(function(r) {
          var opt = document.createElement('option');
          opt.value = r.id;
          opt.textContent = 'غرفة ' + r.room_number + ' — متاح ' + r.available_beds + ' من ' + r.bed_capacity;
          if (r.available_beds <= 0) { opt.disabled = true; opt.textContent += ' (مكتملة)'; }
          if (selectedRoomId && parseInt(r.id, 10) === parseInt(selectedRoomId, 10)) { opt.selected = true; opt.disabled = false; }
          editRoom.appendChild(opt);
        });
        if (window.reinitSelect2) window.reinitSelect2(editRoom);
      });
  }

  editSite.addEventListener('change', function() {
    loadEditApartments(editSite.value, null, null);
  });
  editApartment.addEventListener('change', function() {
    loadEditRooms(editApartment.value, null);
  });

  document.querySelectorAll('.hsg-edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      currentAssignmentId = btn.getAttribute('data-id');
      editForm.action = '/hr/housing/assignments/' + currentAssignmentId + '/update';
      editEmpName.textContent = btn.getAttribute('data-employee') || '';
      editDeduction.value = btn.getAttribute('data-deduction') || 0;
      editDate.value = btn.getAttribute('data-assigned-date') || '';
      editNotes.value = btn.getAttribute('data-notes') || '';
      var siteId = btn.getAttribute('data-site-id');
      var aptId = btn.getAttribute('data-apartment-id');
      var roomId = btn.getAttribute('data-room-id');
      editSite.value = siteId;
      if (window.reinitSelect2) window.reinitSelect2(editSite);
      loadEditApartments(siteId, aptId && aptId !== '0' ? aptId : null, roomId && roomId !== '0' ? roomId : null);
      modal.style.display = 'flex';
    });
  });
})();
</script>
