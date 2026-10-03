<?php use App\Core\{Helpers, Database}; ?>
<style>
.journal-header { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 14px; }
.journal-header .form-row { min-width: 220px; flex: 1; }
.journal-toolbar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 10px; }
.journal-help { font-size: 12px; color: var(--text-muted); }
.journal-grid-wrap { overflow-x: auto; border: 1px solid var(--border-light); border-radius: 8px; }
.journal-grid { width: 100%; border-collapse: collapse; font-size: 13px; }
.journal-grid th { background: var(--surface-alt); padding: 8px 6px; text-align: center; font-weight: 600; border-bottom: 2px solid var(--border); white-space: nowrap; }
.journal-grid td { padding: 2px; border-bottom: 1px solid var(--border-light); text-align: center; }
.journal-grid td input { width: 100%; border: none; padding: 6px 8px; background: transparent; font-size: 13px; outline: none; }
.journal-grid td input:focus { background: #eefbe6; }
.journal-grid td input[readonly] { background: var(--surface); color: var(--text-muted); }
.journal-grid .row-num { width: 30px; color: var(--text-muted); font-weight: 600; }
.journal-grid .col-code { width: 120px; }
.journal-grid .col-name { min-width: 220px; text-align: right; }
.journal-grid .col-debit, .journal-grid .col-credit { width: 120px; }
.journal-grid .col-desc { min-width: 220px; }
.journal-grid .col-actions { width: 40px; }
.journal-grid .totals-row td { font-weight: 700; background: var(--surface-alt); padding: 8px; }
.journal-grid .status-ok { color: var(--brand); }
.journal-grid .status-error { color: #dc2626; }
.journal-grid input.is-invalid { background: #ffe6e6 !important; color: #991b1b; }
.journal-actions { margin-top: 14px; display: flex; gap: 8px; }
.btn-remove-row { padding: 4px 8px; }
</style>

<?php $isEdit = !empty($editEntry); ?>
<div class="card">
  <h3><?= $isEdit ? 'تعديل قيد يومي' : 'تسجيل قيد يومي' ?></h3>
  <form method="post" action="<?= $isEdit ? '/accounting/journal/' . (int)$editEntry['id'] . '/edit' : '/accounting/journal' ?>" id="journal-form">
    <div class="journal-header">
      <div class="form-row"><label>التاريخ</label><input type="date" name="entry_date" value="<?= Helpers::e($editEntry['entry_date'] ?? date('Y-m-d')) ?>" required></div>
      <div class="form-row"><label>الوصف العام</label><input type="text" name="description" value="<?= Helpers::e($editEntry['description'] ?? '') ?>" required placeholder="وصف مختصر للقيد"></div>
      <div class="form-row"><label>مركز التكلفة</label>
        <select name="cost_center_id"><option value="">بدون</option>
          <?php foreach ($costCenters as $cc): ?>
            <option value="<?= $cc['id'] ?>" <?= (($editEntry['cost_center_id'] ?? '') == $cc['id'] ? 'selected' : '') ?>><?= Helpers::e($cc['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="journal-toolbar">
      <button type="button" class="btn btn-sm btn-primary" id="btn-paste-excel"><i class="fa-solid fa-paste"></i> لصق من Excel</button>
      <button type="button" class="btn btn-sm btn-secondary" id="btn-add-row"><i class="fa-solid fa-plus"></i> إضافة سطر</button>
      <button type="button" class="btn btn-sm btn-danger" id="btn-clear-grid"><i class="fa-solid fa-trash"></i> مسح</button>
      <span class="journal-help">يمكنك نسخ أعمدة Excel (كود الحساب | اسم الحساب | مدين | دائن | بيان) ثم Ctrl+V داخل الجدول</span>
    </div>

    <div class="journal-grid-wrap">
      <table class="journal-grid" id="journal-grid">
        <thead>
          <tr>
            <th class="row-num">#</th>
            <th class="col-code">كود الحساب</th>
            <th class="col-name">اسم الحساب</th>
            <th class="col-debit">مدين</th>
            <th class="col-credit">دائن</th>
            <th class="col-desc">البيان</th>
            <th class="col-actions"></th>
          </tr>
        </thead>
        <tbody id="journal-grid-body"></tbody>
        <tfoot>
          <tr class="totals-row">
            <td colspan="3">المجموع</td>
            <td id="total-debit">0</td>
            <td id="total-credit">0</td>
            <td colspan="2" id="balance-status" class="status-ok">متزن</td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="journal-actions">
      <button type="submit" class="btn btn-success" id="btn-save-entry"><i class="fa-solid fa-save"></i> <?= $isEdit ? 'تحديث القيد' : 'حفظ القيد' ?></button>
      <a href="/accounting/journal" class="btn btn-secondary">إلغاء</a>
    </div>
  </form>
</div>

<script>
var JOURNAL_ACCOUNTS = <?= json_encode(array_map(fn($a) => ['id' => (int)$a['id'], 'code' => (string)$a['code'], 'name' => (string)$a['name']], $accounts), JSON_UNESCAPED_UNICODE) ?>;
var JOURNAL_ACCOUNT_BY_CODE = {};
var JOURNAL_ACCOUNT_BY_NAME = {};
JOURNAL_ACCOUNTS.forEach(function(a){
  JOURNAL_ACCOUNT_BY_CODE[a.code] = a;
  JOURNAL_ACCOUNT_BY_CODE[String(a.code).replace(/\s+/g, '')] = a;
  JOURNAL_ACCOUNT_BY_NAME[a.name] = a;
});
var JOURNAL_INITIAL_LINES = <?= json_encode(array_map(fn($l) => ['account_id' => (int)$l['account_id'], 'code' => (string)$l['code'], 'name' => (string)($l['account_name'] ?? $l['name'] ?? ''), 'debit' => (float)$l['debit'], 'credit' => (float)$l['credit'], 'description' => (string)($l['description'] ?? '')], $editLines ?? []), JSON_UNESCAPED_UNICODE) ?>;

function escapeHtml(text) {
  var div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}
function toEnDigits(str) {
  return String(str).replace(/[\u0660-\u0669\u06F0-\u06F9]/g, function(c){
    var map = {'٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'};
    return map[c] || c;
  });
}
function parseAmount(v) {
  if (v === undefined || v === null || v === '') return 0;
  var s = toEnDigits(String(v)).replace(/,/g, '').trim();
  var n = parseFloat(s);
  return isNaN(n) ? 0 : n;
}
function formatMoney(n) {
  return n.toLocaleString('en-US', {maximumFractionDigits: 2, minimumFractionDigits: 0});
}
function findAccount(term) {
  if (!term) return null;
  var t = String(term).trim();
  var norm = t.replace(/\s+/g, '');
  if (JOURNAL_ACCOUNT_BY_CODE[t]) return JOURNAL_ACCOUNT_BY_CODE[t];
  if (JOURNAL_ACCOUNT_BY_CODE[norm]) return JOURNAL_ACCOUNT_BY_CODE[norm];
  if (JOURNAL_ACCOUNT_BY_NAME[t]) return JOURNAL_ACCOUNT_BY_NAME[t];
  for (var i = 0; i < JOURNAL_ACCOUNTS.length; i++) if (JOURNAL_ACCOUNTS[i].name.indexOf(t) !== -1) return JOURNAL_ACCOUNTS[i];
  return null;
}

function createRow(data) {
  data = data || {};
  var tr = document.createElement('tr');
  tr.className = 'journal-row';
  tr.innerHTML =
    '<td class="row-num"></td>' +
    '<td><input type="text" class="j-code" placeholder="كود" value="' + escapeHtml(data.code || '') + '" dir="ltr" autocomplete="off"></td>' +
    '<td><input type="text" class="j-name" placeholder="اسم الحساب" value="' + escapeHtml(data.name || '') + '" readonly tabindex="-1"></td>' +
    '<td><input type="number" class="j-debit" step="0.01" min="0" value="' + (data.debit !== undefined && data.debit !== '' && data.debit !== 0 ? data.debit : '') + '" placeholder="0"></td>' +
    '<td><input type="number" class="j-credit" step="0.01" min="0" value="' + (data.credit !== undefined && data.credit !== '' && data.credit !== 0 ? data.credit : '') + '" placeholder="0"></td>' +
    '<td><input type="text" class="j-desc" placeholder="بيان" value="' + escapeHtml(data.description || '') + '"></td>' +
    '<td><button type="button" class="btn btn-sm btn-danger btn-remove-row" title="حذف السطر"><i class="fa-solid fa-trash"></i></button></td>';
  var hidden = document.createElement('input');
  hidden.type = 'hidden';
  hidden.name = 'account_id[]';
  hidden.className = 'j-account-id';
  hidden.value = data.account_id || '';
  tr.appendChild(hidden);

  var codeIn = tr.querySelector('.j-code');
  var nameIn = tr.querySelector('.j-name');
  var debitIn = tr.querySelector('.j-debit');
  var creditIn = tr.querySelector('.j-credit');
  var descIn = tr.querySelector('.j-desc');
  debitIn.name = 'debit[]';
  creditIn.name = 'credit[]';
  descIn.name = 'line_description[]';

  codeIn.addEventListener('blur', function() { resolveAccount(tr); });
  codeIn.addEventListener('keydown', handleCellKeydown);
  nameIn.addEventListener('keydown', handleCellKeydown);
  debitIn.addEventListener('keydown', handleCellKeydown);
  debitIn.addEventListener('input', recalcTotals);
  creditIn.addEventListener('keydown', handleCellKeydown);
  creditIn.addEventListener('input', recalcTotals);
  descIn.addEventListener('keydown', handleCellKeydown);
  tr.querySelector('.btn-remove-row').addEventListener('click', function() { removeRow(tr); });
  return tr;
}

function resolveAccount(tr) {
  var codeIn = tr.querySelector('.j-code');
  var nameIn = tr.querySelector('.j-name');
  var hidden = tr.querySelector('.j-account-id');
  var acc = findAccount(codeIn.value);
  if (!acc && nameIn.value) acc = findAccount(nameIn.value);
  if (acc) {
    hidden.value = acc.id;
    codeIn.value = acc.code;
    nameIn.value = acc.name;
    codeIn.classList.remove('is-invalid');
    nameIn.classList.remove('is-invalid');
  } else {
    hidden.value = '';
    if (codeIn.value.trim() || nameIn.value.trim()) {
      codeIn.classList.add('is-invalid');
      nameIn.classList.add('is-invalid');
    } else {
      codeIn.classList.remove('is-invalid');
      nameIn.classList.remove('is-invalid');
    }
  }
  recalcTotals();
}

function addRow(data) {
  var tbody = document.getElementById('journal-grid-body');
  var tr = createRow(data);
  tbody.appendChild(tr);
  renumberRows();
  return tr;
}
function removeRow(tr) {
  tr.parentNode.removeChild(tr);
  renumberRows();
  recalcTotals();
}
function renumberRows() {
  var rows = document.querySelectorAll('#journal-grid-body tr');
  rows.forEach(function(tr, i) { tr.querySelector('.row-num').textContent = i + 1; });
}

function focusCell(row, colIndex) {
  var input = row.querySelectorAll('td')[colIndex]?.querySelector('input:not([readonly])');
  if (input) { input.focus(); input.select(); }
}

function handleCellKeydown(e) {
  var cell = e.target.closest('td');
  var row = cell.parentNode;
  var rows = Array.from(document.querySelectorAll('#journal-grid-body tr'));
  var cells = Array.from(row.querySelectorAll('td'));
  var rowIndex = rows.indexOf(row);
  var colIndex = cells.indexOf(cell);
  if (e.key === 'Tab') {
    if (!e.shiftKey && colIndex === cells.length - 2) {
      e.preventDefault();
      if (rows[rowIndex + 1]) focusCell(rows[rowIndex + 1], 1);
      else { addRow(); focusCell(rows[rows.length - 1], 1); }
    } else if (e.shiftKey && colIndex === 1) {
      if (rows[rowIndex - 1]) { e.preventDefault(); focusCell(rows[rowIndex - 1], cells.length - 2); }
    }
  } else if (e.key === 'ArrowDown') {
    e.preventDefault();
    if (rows[rowIndex + 1]) focusCell(rows[rowIndex + 1], colIndex);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    if (rows[rowIndex - 1]) focusCell(rows[rowIndex - 1], colIndex);
  } else if (e.key === 'ArrowRight') {
    if (e.target.selectionEnd === e.target.value.length && colIndex < cells.length - 2) {
      e.preventDefault();
      focusCell(row, colIndex + 1);
    }
  } else if (e.key === 'ArrowLeft') {
    if (e.target.selectionStart === 0 && colIndex > 1) {
      e.preventDefault();
      focusCell(row, colIndex - 1);
    }
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (rows[rowIndex + 1]) focusCell(rows[rowIndex + 1], colIndex);
    else { addRow(); focusCell(rows[rows.length - 1], colIndex); }
  }
}

function fillRowFromPaste(tr, cols, startCol) {
  var codeIn = tr.querySelector('.j-code');
  var nameIn = tr.querySelector('.j-name');
  var debitIn = tr.querySelector('.j-debit');
  var creditIn = tr.querySelector('.j-credit');
  var descIn = tr.querySelector('.j-desc');
  if (startCol <= 1 && cols[1 - startCol] !== undefined) codeIn.value = cols[1 - startCol];
  if (startCol <= 2 && cols[2 - startCol] !== undefined) nameIn.value = cols[2 - startCol];
  if (startCol <= 3 && cols[3 - startCol] !== undefined) debitIn.value = parseAmount(cols[3 - startCol]) || '';
  if (startCol <= 4 && cols[4 - startCol] !== undefined) creditIn.value = parseAmount(cols[4 - startCol]) || '';
  if (startCol <= 5 && cols[5 - startCol] !== undefined) descIn.value = cols[5 - startCol];
  resolveAccount(tr);
}

function recalcTotals() {
  var rows = document.querySelectorAll('#journal-grid-body tr');
  var totalDr = 0, totalCr = 0, valid = 0;
  rows.forEach(function(tr) {
    var dr = parseAmount(tr.querySelector('.j-debit').value);
    var cr = parseAmount(tr.querySelector('.j-credit').value);
    var hasAcc = !!tr.querySelector('.j-account-id').value;
    totalDr += dr; totalCr += cr;
    if (hasAcc && (dr || cr)) valid++;
    var drIn = tr.querySelector('.j-debit');
    var crIn = tr.querySelector('.j-credit');
    if (dr && cr) { drIn.classList.add('is-invalid'); crIn.classList.add('is-invalid'); }
    else { drIn.classList.remove('is-invalid'); crIn.classList.remove('is-invalid'); }
  });
  document.getElementById('total-debit').textContent = formatMoney(totalDr);
  document.getElementById('total-credit').textContent = formatMoney(totalCr);
  var diff = totalDr - totalCr;
  var statusEl = document.getElementById('balance-status');
  var saveBtn = document.getElementById('btn-save-entry');
  if (Math.abs(diff) < 0.001) {
    statusEl.textContent = 'متزن';
    statusEl.className = 'status-ok';
  } else {
    statusEl.textContent = 'فرق: ' + formatMoney(Math.abs(diff)) + (diff > 0 ? ' مدين' : ' دائن');
    statusEl.className = 'status-error';
  }
  saveBtn.disabled = !(Math.abs(diff) < 0.001 && valid >= 2);
  saveBtn.title = saveBtn.disabled ? 'القيد غير متزن أو يحتوي على أقل من بندين صالحين' : '';
}

document.addEventListener('DOMContentLoaded', function() {
  var initialLines = (typeof JOURNAL_INITIAL_LINES !== 'undefined' && JOURNAL_INITIAL_LINES.length) ? JOURNAL_INITIAL_LINES : null;
  if (initialLines) {
    initialLines.forEach(function(l){ addRow(l); });
  } else {
    for (var i = 0; i < 5; i++) addRow();
  }
  recalcTotals();

  document.getElementById('btn-add-row').addEventListener('click', function() { addRow(); });
  document.getElementById('btn-clear-grid').addEventListener('click', function() {
    document.getElementById('journal-grid-body').innerHTML = '';
    for (var i = 0; i < 5; i++) addRow();
    recalcTotals();
  });
  document.getElementById('btn-paste-excel').addEventListener('click', function() {
    alert('انسخ البيانات من Excel ثم اضغط Ctrl+V داخل أي خلية في الجدول');
  });

  var grid = document.getElementById('journal-grid');
  grid.addEventListener('paste', function(e) {
    var target = e.target;
    if (!target.closest('#journal-grid-body')) return;
    var text = e.clipboardData.getData('text/plain');
    if (!text) return;
    var lines = text.split(/\r\n|\n|\r/).filter(function(l){ return l.trim() !== ''; });
    if (lines.length < 1) return;
    if (lines.length === 1 && lines[0].indexOf('\t') === -1) return;
    e.preventDefault();

    var cell = target.closest('td');
    var row = cell.parentNode;
    var rows = Array.from(document.querySelectorAll('#journal-grid-body tr'));
    var cells = Array.from(row.querySelectorAll('td'));
    var startRow = rows.indexOf(row);
    var startCol = cells.indexOf(cell);
    var dataRows = lines.map(function(l){ return l.split('\t'); });

    while (rows.length < startRow + dataRows.length) { addRow(); rows = Array.from(document.querySelectorAll('#journal-grid-body tr')); }
    dataRows.forEach(function(cols, i) {
      fillRowFromPaste(rows[startRow + i], cols, startCol);
    });
    recalcTotals();
  });

  document.getElementById('journal-form').addEventListener('submit', function(e) {
    var rows = Array.from(document.querySelectorAll('#journal-grid-body tr'));
    rows.forEach(resolveAccount);
    var valid = 0;
    rows.forEach(function(tr) {
      var accId = tr.querySelector('.j-account-id').value;
      var dr = parseAmount(tr.querySelector('.j-debit').value);
      var cr = parseAmount(tr.querySelector('.j-credit').value);
      if (accId && (dr || cr)) valid++;
    });
    if (valid < 2) {
      e.preventDefault();
      alert('القيد يحتاج بندين على الأقل');
      return;
    }
    rows.forEach(function(tr) {
      var code = tr.querySelector('.j-code').value.trim();
      var name = tr.querySelector('.j-name').value.trim();
      var dr = parseAmount(tr.querySelector('.j-debit').value);
      var cr = parseAmount(tr.querySelector('.j-credit').value);
      var desc = tr.querySelector('.j-desc').value.trim();
      if (!code && !name && !dr && !cr && !desc) tr.remove();
    });
    renumberRows();
  });
});
</script>

<div class="card">
  <h3>استيراد عدة قيود من Excel</h3>
  <form method="post" action="/accounting/journal/import" enctype="multipart/form-data" class="journal-header">
    <div class="form-row"><label>ملف Excel</label><input type="file" name="excel" accept=".xlsx,.xls,.csv" required></div>
    <div style="display:flex;gap:8px;align-items:flex-end">
      <button type="submit" class="btn btn-success"><i class="fa-solid fa-upload"></i> رفع واستيراد</button>
      <a href="/accounting/journal/template" class="btn btn-secondary"><i class="fa-solid fa-download"></i> تنزيل نموذج</a>
    </div>
  </form>
  <p class="journal-help" style="margin-top:8px">التنسيق: رقم القيد | التاريخ | الوصف | مركز التكلفة | كود الحساب | اسم الحساب | مدين | دائن | البيان</p>
</div>

<div class="card">
  <h3>القيود المسجلة</h3>
  <table>
    <tr><th>رقم القيد</th><th>التاريخ</th><th>الوصف</th><th>مركز التكلفة</th><th>المبلغ</th><th>الحالة</th><th></th></tr>
    <?php foreach ($entries as $e):
      $total = Database::query("SELECT SUM(debit) AS s FROM journal_lines WHERE entry_id = ?", [$e['id']])->fetch()['s'];
    ?>
    <tr>
      <td><?= Helpers::e($e['entry_no']) ?></td>
      <td><?= Helpers::e($e['entry_date']) ?></td>
      <td><?= Helpers::e($e['description']) ?></td>
      <td><?= Helpers::e($e['cost_center_name'] ?? '-') ?></td>
      <td><?= Helpers::money($total) ?></td>
      <td><span class="badge badge-<?= $e['status'] ?>"><?= $e['status'] ?></span></td>
      <td>
        <a href="/accounting/journal/<?= $e['id'] ?>/edit" class="btn btn-sm btn-primary">عرض/تعديل</a>
        <?php if ($e['status'] === 'posted'): ?>
        <form method="post" action="/accounting/journal/<?= $e['id'] ?>/reverse" style="display:inline">
          <input type="hidden" name="reason" value="">
          <button class="btn btn-sm btn-danger" onclick="return confirm('تأكيد عكس القيد؟')">عكس</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
