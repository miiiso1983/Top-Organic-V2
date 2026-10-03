<?php use App\Core\Helpers; ?>
<div class="card">
  <h3><i class="fa-solid fa-clock"></i> الورديات</h3>
  <p><a href="/hr/employees" class="btn btn-sm btn-secondary">← الموظفون</a></p>
</div>

<div class="card" style="margin-top:16px">
  <h4>إضافة وردية</h4>
  <form method="post" action="/hr/shifts" id="add-shift-form">
    <div class="form-row"><label>اسم الوردية</label><input name="name" required></div>
    <div class="form-row" style="min-width:100%">
      <label>فترات العمل</label>
      <div id="segments-container">
        <div class="segment-row" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
          <input type="time" name="seg_start[]" value="08:00" required style="width:130px">
          <span>إلى</span>
          <input type="time" name="seg_end[]" value="17:00" required style="width:130px">
          <button type="button" class="btn btn-sm btn-danger btn-remove-segment" onclick="removeSegment(this)" style="display:none">×</button>
        </div>
      </div>
      <button type="button" class="btn btn-sm btn-secondary" onclick="addSegment()"><i class="fa-solid fa-plus"></i> إضافة فترة أخرى</button>
    </div>
    <div class="form-row" style="align-items:flex-start">
      <label>أيام العمل</label>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <?php $dayNames = ['الأحد','الإثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت']; ?>
        <?php foreach ($dayNames as $idx => $dn): ?>
          <label style="display:flex;align-items:center;gap:4px;font-weight:normal">
            <input type="checkbox" name="work_days[]" value="<?= $idx ?>" checked>
            <?= $dn ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <button class="btn btn-success">إضافة</button>
  </form>
</div>

<script>
function addSegment() {
  var container = document.getElementById('segments-container');
  var row = document.createElement('div');
  row.className = 'segment-row';
  row.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px';
  row.innerHTML = '<input type="time" name="seg_start[]" required style="width:130px">' +
    '<span>إلى</span>' +
    '<input type="time" name="seg_end[]" required style="width:130px">' +
    '<button type="button" class="btn btn-sm btn-danger btn-remove-segment" onclick="removeSegment(this)">×</button>';
  container.appendChild(row);
  updateRemoveButtons();
}

function removeSegment(btn) {
  btn.closest('.segment-row').remove();
  updateRemoveButtons();
}

function updateRemoveButtons() {
  var rows = document.querySelectorAll('#segments-container .segment-row');
  rows.forEach(function(row, i) {
    var btn = row.querySelector('.btn-remove-segment');
    if (btn) btn.style.display = rows.length > 1 ? '' : 'none';
  });
}

document.addEventListener('DOMContentLoaded', updateRemoveButtons);
</script>

<div class="card" style="margin-top:16px">
  <h4>استيراد من Excel</h4>
  <p>حمّل ملف Excel يحتوي على أعمدة: <strong>name</strong>، <strong>start_time</strong>، <strong>end_time</strong>، <strong>work_days</strong> (مثال: 0,1,2,3,4)</p>
  <form method="post" action="/hr/shifts/import" enctype="multipart/form-data" class="form-inline">
    <div class="form-row"><input type="file" name="import_file" accept=".xlsx,.xls" required></div>
    <button class="btn btn-primary">استيراد</button>
    <a href="/hr/shifts/template" class="btn btn-secondary" style="margin-right:8px">تنزيل نموذج</a>
  </form>
</div>

<div class="card" style="margin-top:16px">
  <h4>الورديات</h4>
  <form id="bulk-delete-form" method="post" action="/hr/shifts/bulk-delete" onsubmit="return confirm('هل أنت متأكد من حذف الورديات المحددة؟')">
    <button type="submit" class="btn btn-sm btn-danger" style="margin-bottom:12px">
      <i class="fa-solid fa-trash"></i> حذف المحدد
    </button>
  </form>
  <div style="overflow:auto">
    <table>
      <tr><th><input type="checkbox" id="select-all-shifts" title="تحديد الكل"></th><th>#</th><th>الاسم</th><th>الفترات</th><th>أيام العمل</th><th>الحالة</th><th>حفظ</th><th>حذف</th></tr>
      <?php foreach ($shifts as $s):
        $shiftDays = $workDays[(int)$s['id']] ?? [];
        $shiftSegments = $segments[(int)$s['id']] ?? [['start_time' => substr($s['start_time'], 0, 5), 'end_time' => substr($s['end_time'], 0, 5)]];
        $dayNames = ['الأحد','الإثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];
      ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= $s['id'] ?>" form="bulk-delete-form" class="shift-checkbox"></td>
        <td><?= $s['id'] ?></td>
        <td><input name="name" form="shift-form-<?= $s['id'] ?>" value="<?= Helpers::e($s['name']) ?>" required></td>
        <td>
          <div id="segments-edit-<?= $s['id'] ?>">
            <?php foreach ($shiftSegments as $seg): ?>
            <div class="segment-edit-row" style="display:flex;gap:6px;align-items:center;margin-bottom:4px">
              <input type="time" name="seg_start[]" form="shift-form-<?= $s['id'] ?>" value="<?= Helpers::e($seg['start_time']) ?>" required style="width:110px">
              <span style="font-size:11px">إلى</span>
              <input type="time" name="seg_end[]" form="shift-form-<?= $s['id'] ?>" value="<?= Helpers::e($seg['end_time']) ?>" required style="width:110px">
              <button type="button" class="btn btn-sm btn-danger" style="padding:0 6px;font-size:11px" onclick="removeEditSegment(this, <?= $s['id'] ?>)" <?= count($shiftSegments) <= 1 ? 'style="display:none;padding:0 6px;font-size:11px"' : '' ?>>×</button>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-sm btn-secondary" style="font-size:11px;padding:2px 8px" onclick="addEditSegment(<?= $s['id'] ?>)"><i class="fa-solid fa-plus"></i> فترة</button>
        </td>
        <td>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php foreach ($dayNames as $idx => $dn): ?>
              <label style="display:flex;align-items:center;gap:3px;font-weight:normal;font-size:12px">
                <input type="checkbox" name="work_days[]" form="shift-form-<?= $s['id'] ?>" value="<?= $idx ?>" <?= isset($shiftDays[$idx]) ? 'checked' : '' ?>>
                <?= $dn ?>
              </label>
            <?php endforeach; ?>
          </div>
        </td>
        <td><?= (int)$s['is_active'] ? 'نشط' : 'غير نشط' ?></td>
        <td>
          <form id="shift-form-<?= $s['id'] ?>" method="post" action="/hr/shifts/<?= $s['id'] ?>" style="display:inline">
            <button class="btn btn-sm btn-primary">حفظ</button>
          </form>
        </td>
        <td>
          <form method="post" action="/hr/shifts/<?= $s['id'] ?>/delete" style="display:inline" onsubmit="return confirm('هل تريد حذف هذه الوردية؟')">
            <button class="btn btn-sm btn-danger">حذف</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($shifts)): ?>
      <tr><td colspan="8" class="text-center">لا توجد ورديات مسجلة</td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>

<script>
function addEditSegment(shiftId) {
  var container = document.getElementById('segments-edit-' + shiftId);
  var row = document.createElement('div');
  row.className = 'segment-edit-row';
  row.style.cssText = 'display:flex;gap:6px;align-items:center;margin-bottom:4px';
  row.innerHTML = '<input type="time" name="seg_start[]" form="shift-form-' + shiftId + '" required style="width:110px">' +
    '<span style="font-size:11px">إلى</span>' +
    '<input type="time" name="seg_end[]" form="shift-form-' + shiftId + '" required style="width:110px">' +
    '<button type="button" class="btn btn-sm btn-danger" style="padding:0 6px;font-size:11px" onclick="removeEditSegment(this, ' + shiftId + ')">×</button>';
  container.appendChild(row);
  updateEditRemoveButtons(shiftId);
}

function removeEditSegment(btn, shiftId) {
  btn.closest('.segment-edit-row').remove();
  updateEditRemoveButtons(shiftId);
}

function updateEditRemoveButtons(shiftId) {
  var rows = document.querySelectorAll('#segments-edit-' + shiftId + ' .segment-edit-row');
  rows.forEach(function(row) {
    var btn = row.querySelector('.btn-danger');
    if (btn) btn.style.display = rows.length > 1 ? '' : 'none';
  });
}

(function() {
  var selectAll = document.getElementById('select-all-shifts');
  var checkboxes = document.querySelectorAll('.shift-checkbox');
  if (selectAll && checkboxes.length) {
    selectAll.addEventListener('change', function() {
      for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = selectAll.checked;
      }
    });
  }
  // إظهار/إخفاء أزرار حذف الفترات في الجدول
  document.querySelectorAll('[id^="segments-edit-"]').forEach(function(container) {
    var shiftId = container.id.replace('segments-edit-', '');
    updateEditRemoveButtons(parseInt(shiftId));
  });
})();
</script>
