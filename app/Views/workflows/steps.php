<?php use App\Core\Helpers; ?>
<?php
$approverTypeLabels = [
    'position' => 'مسمى وظيفي',
    'role' => 'دور',
    'direct_manager' => 'المدير المباشر',
    'department_manager' => 'مدير القسم',
];
$opLabels = ['==' => '=', '!=' => '≠', '>' => '>', '>=' => '≥', '<' => '<', '<=' => '≤'];
$fieldLabelByName = [];
foreach (($template['fields'] ?? []) as $f) { $fieldLabelByName[$f['name']] = $f['label'] ?? $f['name']; }

function wf_condition_summary(array $cond, array $fieldLabelByName, array $opLabels): string
{
    if (!$cond) return '';
    $parts = [];
    foreach ($cond as $key => $val) {
        $label = $fieldLabelByName[$key] ?? $key;
        if (is_array($val)) {
            if (array_is_list($val)) {
                $parts[] = $label . ' ضمن [' . implode('، ', $val) . ']';
            } else {
                $opKey = array_key_first($val);
                $opText = $opLabels[$opKey] ?? $opKey;
                $parts[] = $label . ' ' . $opText . ' ' . $val[$opKey];
            }
        } else {
            $parts[] = $label . ' = ' . $val;
        }
    }
    return implode(' و ', $parts);
}

// تجميع الخطوات إلى "مراحل" (stage): صفوف متتالية تشترك بنفس step_order تمثّل مرحلة واحدة
// ذات بدائل/شروط متعددة (مثال فعلي من بيانات النظام: قالب الإجازات يفرّع المعتمد حسب نوع
// الإجازة ضمن نفس المرحلة عبر شرط مختلف لكل بديل) — لذا تُسحَب المراحل ككتلة واحدة متماسكة
// حفاظاً على هذا الترابط، لا الصفوف منفردة.
$stages = [];
$lastOrder = null;
foreach ($steps as $s) {
    if ($lastOrder === null || (int)$s['step_order'] !== $lastOrder) {
        $stages[] = [];
        $lastOrder = (int)$s['step_order'];
    }
    $stages[count($stages) - 1][] = $s;
}
?>
<style>
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; justify-content:center; align-items:center; }
.modal-overlay.active { display:flex; }
.modal-content { background:#fff; border-radius:8px; width:92%; max-width:760px; max-height:88vh; overflow-y:auto; box-shadow:0 10px 25px rgba(0,0,0,.2); }
.modal-header { display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid #e5e7eb; }
.modal-header h4 { margin:0; }
.modal-close { cursor:pointer; font-size:20px; color:#6b7280; }
.modal-body { padding:16px; }

#stepsFlow { position:relative; }
.stage-card { background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; margin-bottom:12px; padding:10px 14px; cursor:grab; }
.stage-card.dragging { opacity:.4; }
.stage-head { display:flex; align-items:center; gap:10px; margin-bottom:6px; }
.drag-handle { color:#9ca3af; }
.step-num { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:50%; background:#2563eb; color:#fff; font-size:13px; font-weight:bold; flex-shrink:0; }
.stage-title { font-weight:600; flex:1; }
.stage-or { text-align:center; color:#9ca3af; font-size:12px; margin:2px 0; }
.step-row { display:flex; justify-content:space-between; align-items:center; gap:10px; background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:8px 10px; margin-top:4px; }
.step-row-main { font-size:13px; }
.step-meta { color:#6b7280; display:block; font-size:12px; margin-top:2px; }
.step-row-actions { display:flex; gap:4px; flex-shrink:0; }
.empty-state { text-align:center; color:#9ca3af; padding:30px; border:2px dashed #e5e7eb; border-radius:10px; }
.cond-row select, .cond-row input { padding:4px 6px; font-size:13px; }
</style>

<div class="page-head">
  <h2>خطوات الموافقة: <?= Helpers::e($template['name']) ?></h2>
  <a href="/workflows/templates" class="btn btn-secondary">رجوع</a>
</div>

<div class="card">
  <h3>مسار الموافقة</h3>
  <p style="color:#6b7280;font-size:13px;margin-top:-4px">
    كل مرحلة تمثّل مستوى موافقة مستقلاً وتُعتمد بالترتيب من الأعلى للأسفل.
    اسحب <i class="fa-solid fa-grip-vertical"></i> لإعادة ترتيب المراحل، أو أضف "خياراً بديلاً" داخل مرحلة قائمة (مثال: معتمد مختلف حسب شرط معيّن في بيانات الطلب).
  </p>
  <div id="stepsFlow">
    <?php if (!$stages): ?>
    <div class="empty-state">لم تُضف أي مرحلة بعد — استخدم النموذج أدناه لإضافة أول مرحلة.</div>
    <?php endif; ?>
    <?php foreach ($stages as $i => $stage): ?>
    <div class="stage-card" draggable="true"
         data-step-order="<?= (int)$stage[0]['step_order'] ?>"
         data-step-ids="<?= implode(',', array_column($stage, 'id')) ?>">
      <div class="stage-head">
        <span class="drag-handle"><i class="fa-solid fa-grip-vertical"></i></span>
        <span class="step-num"><?= $i + 1 ?></span>
        <span class="stage-title"><?= count($stage) > 1 ? 'مرحلة متعددة الخيارات (' . count($stage) . ')' : Helpers::e($stage[0]['title']) ?></span>
        <button type="button" class="btn btn-sm btn-light" onclick="addAlternative(<?= (int)$stage[0]['step_order'] ?>)"><i class="fa-solid fa-plus"></i> خيار بديل</button>
      </div>
      <?php foreach ($stage as $k => $s): ?>
      <?php if ($k > 0): ?><div class="stage-or">أو</div><?php endif; ?>
      <?php
        $cond = json_decode($s['condition_json'] ?? '{}', true) ?: [];
        $ch = json_decode($s['notify_channels'] ?? '{}', true) ?: [];
        $condSummary = wf_condition_summary($cond, $fieldLabelByName, $opLabels);
        $stepJson = Helpers::e(json_encode([
            'id' => $s['id'], 'title' => $s['title'], 'approver_type' => $s['approver_type'],
            'job_title_id' => $s['job_title_id'], 'role_id' => $s['role_id'], 'min_amount' => $s['min_amount'],
            'step_type' => $s['step_type'], 'required_count' => $s['required_count'],
            'can_return_for_edit' => $s['can_return_for_edit'], 'is_final' => $s['is_final'],
            'condition' => $cond, 'notify_channels' => $ch,
        ], JSON_UNESCAPED_UNICODE));
      ?>
      <div class="step-row">
        <div class="step-row-main">
          <strong><?= Helpers::e($s['title']) ?></strong>
          <span class="step-meta">
            <?= Helpers::e($approverTypeLabels[$s['approver_type']] ?? $s['approver_type']) ?><?= ($s['job_title'] ?? $s['role_name'] ?? null) ? ': ' . Helpers::e($s['job_title'] ?? $s['role_name']) : '' ?>
            <?php if ((float)$s['min_amount'] > 0): ?> · ابتداءً من <?= number_format((float)$s['min_amount'], 0) ?><?php endif; ?>
            <?php if ($s['step_type'] === 'parallel'): ?> · موافقة جماعية (<?= $s['required_count'] ?: 'الكل' ?>)<?php endif; ?>
            <?= ($ch['push'] ?? 0) ? ' · <i class="fa-solid fa-bell" title="Push"></i>' : '' ?>
            <?= ($ch['email'] ?? 0) ? ' <i class="fa-solid fa-envelope" title="Email"></i>' : '' ?>
            <?= ($ch['whatsapp'] ?? 0) ? ' <i class="fa-solid fa-mobile-screen" title="WhatsApp"></i>' : '' ?>
            <?php if ($condSummary): ?><br>شرط: <?= Helpers::e($condSummary) ?><?php endif; ?>
          </span>
        </div>
        <div class="step-row-actions">
          <button type="button" class="btn btn-sm btn-light" onclick='openEditStep(<?= $stepJson ?>)'>تعديل</button>
          <form method="post" action="/workflows/templates/<?= $template['id'] ?>/steps/<?= $s['id'] ?>/duplicate" style="display:inline">
            <button class="btn btn-sm btn-light">نسخ</button>
          </form>
          <form method="post" action="/workflows/templates/<?= $template['id'] ?>/steps/<?= $s['id'] ?>/delete" style="display:inline" onsubmit="return confirm('حذف هذا الخيار؟')">
            <button class="btn btn-sm btn-danger">حذف</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card" id="stepFormCard">
  <h3 id="stepFormTitle">إضافة مرحلة جديدة</h3>
  <div id="altNotice" style="display:none;background:#eff6ff;border:1px solid #bfdbfe;padding:8px 12px;border-radius:6px;margin-bottom:10px;font-size:13px">
    سيُضاف هذا كخيار بديل ضمن المرحلة رقم <b id="altStageNum"></b> — يُستخدم لتفريع المعتمد حسب شرط معيّن ضمن نفس المستوى.
    <a href="#" onclick="cancelAlternative(); return false;">إلغاء، وإضافة كمرحلة جديدة بدلاً من ذلك</a>
  </div>
  <form method="post" action="/workflows/templates/<?= $template['id'] ?>/steps" id="addStepForm" class="form-inline" onsubmit="return buildConditionJson('add')">
    <input type="hidden" name="step_order" id="add_step_order" value="">
    <?php $prefix = 'add'; include __DIR__ . '/_step_fields.php'; ?>
    <button class="btn btn-success">حفظ المرحلة</button>
  </form>
</div>

<div id="editStepModal" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header"><h4>تعديل المرحلة</h4><span class="modal-close" onclick="closeEditModal()">&times;</span></div>
    <div class="modal-body">
      <form method="post" id="editStepForm" class="form-inline" onsubmit="return buildConditionJson('edit')">
        <?php $prefix = 'edit'; include __DIR__ . '/_step_fields.php'; ?>
        <button class="btn btn-success">حفظ التعديلات</button>
      </form>
    </div>
  </div>
</div>

<script>
var TEMPLATE_FIELDS = <?= json_encode(array_map(fn($f) => ['name' => $f['name'], 'label' => $f['label'] ?? $f['name']], $template['fields'] ?? [])) ?>;
var REORDER_URL = '/workflows/templates/<?= (int)$template['id'] ?>/steps/reorder';
var OP_LABELS = {'==':'يساوي','!=':'لا يساوي','>':'أكبر من','>=':'أكبر أو يساوي','<':'أصغر من','<=':'أصغر أو يساوي'};

function toggleApproverFields(prefix) {
  var type = document.getElementById(prefix + '_approver_type').value;
  document.getElementById(prefix + '_position_row').style.display = (type === 'position') ? '' : 'none';
  document.getElementById(prefix + '_role_row').style.display = (type === 'role') ? '' : 'none';
  document.getElementById(prefix + '_manager_note').style.display = (type === 'direct_manager' || type === 'department_manager') ? '' : 'none';
}
function toggleStepTypeFields(prefix) {
  var type = document.getElementById(prefix + '_step_type').value;
  document.getElementById(prefix + '_required_count_row').style.display = (type === 'parallel') ? '' : 'none';
}

function addConditionRow(prefix, field, op, value) {
  field = field || ''; op = op || '=='; value = (value === undefined || value === null) ? '' : value;
  var box = document.getElementById(prefix + '_conditions_box');
  var row = document.createElement('div');
  row.className = 'cond-row form-inline';
  row.style.cssText = 'gap:6px;margin-bottom:6px;align-items:center';
  var fieldOptions = '<option value="">اختر حقلاً...</option>' + TEMPLATE_FIELDS.map(function (f) {
    return '<option value="' + f.name + '"' + (f.name === field ? ' selected' : '') + '>' + f.label + '</option>';
  }).join('');
  var opOptions = Object.keys(OP_LABELS).map(function (o) {
    return '<option value="' + o + '"' + (o === op ? ' selected' : '') + '>' + OP_LABELS[o] + '</option>';
  }).join('');
  row.innerHTML =
    '<select class="cond-field">' + fieldOptions + '</select>' +
    '<select class="cond-op">' + opOptions + '</select>' +
    '<input class="cond-value" placeholder="القيمة">' +
    '<button type="button" class="btn btn-sm btn-danger">×</button>';
  box.appendChild(row);
  row.querySelector('.cond-value').value = value;
  row.querySelectorAll('select, input').forEach(function (el) { el.addEventListener('input', function () { buildConditionJson(prefix); }); });
  row.querySelector('button').addEventListener('click', function () { row.remove(); buildConditionJson(prefix); });
  buildConditionJson(prefix);
}

function buildConditionJson(prefix) {
  var rows = document.querySelectorAll('#' + prefix + '_conditions_box .cond-row');
  var result = {};
  rows.forEach(function (row) {
    var field = row.querySelector('.cond-field').value;
    var op = row.querySelector('.cond-op').value;
    var val = row.querySelector('.cond-value').value;
    if (!field || val === '') return;
    if (op === '==') { result[field] = val; } else { result[field] = {}; result[field][op] = val; }
  });
  var hidden = document.getElementById(prefix + '_condition_hidden');
  if (hidden) hidden.value = JSON.stringify(result);
  return true;
}

function populateConditionRows(prefix, cond) {
  document.getElementById(prefix + '_conditions_box').innerHTML = '';
  Object.keys(cond || {}).forEach(function (k) {
    var v = cond[k], op = '==', val = v;
    if (v !== null && typeof v === 'object' && !Array.isArray(v)) {
      var opKeys = Object.keys(v);
      if (opKeys.length) { op = opKeys[0]; val = v[opKeys[0]]; }
    } else if (Array.isArray(v)) {
      op = '=='; val = v.join(',');
    }
    addConditionRow(prefix, k, op, val);
  });
}

function openEditStep(s) {
  document.getElementById('editStepForm').action = '/workflows/templates/<?= (int)$template['id'] ?>/steps/' + s.id + '/update';
  document.getElementById('edit_title').value = s.title || '';
  document.getElementById('edit_approver_type').value = s.approver_type || 'position';
  document.getElementById('edit_job_title_id').value = s.job_title_id || '';
  document.getElementById('edit_role_id').value = s.role_id || '';
  document.getElementById('edit_min_amount').value = s.min_amount || 0;
  document.getElementById('edit_step_type').value = s.step_type || 'sequential';
  document.getElementById('edit_required_count').value = s.required_count || 1;
  document.getElementById('edit_can_return').checked = !!Number(s.can_return_for_edit);
  document.getElementById('edit_is_final').checked = !!Number(s.is_final);
  var ch = s.notify_channels || {};
  document.getElementById('edit_notify_push').checked = !!ch.push;
  document.getElementById('edit_notify_email').checked = !!ch.email;
  document.getElementById('edit_notify_whatsapp').checked = !!ch.whatsapp;
  toggleApproverFields('edit');
  toggleStepTypeFields('edit');
  populateConditionRows('edit', s.condition || {});
  document.getElementById('editStepModal').classList.add('active');
}
function closeEditModal() { document.getElementById('editStepModal').classList.remove('active'); }
document.getElementById('editStepModal').addEventListener('click', function (e) {
  if (e.target.id === 'editStepModal') closeEditModal();
});

function stageNumberByOrder(order) {
  var cards = document.querySelectorAll('#stepsFlow .stage-card');
  for (var i = 0; i < cards.length; i++) { if (parseInt(cards[i].dataset.stepOrder, 10) === order) return i + 1; }
  return '؟';
}
function addAlternative(stepOrder) {
  document.getElementById('add_step_order').value = stepOrder;
  document.getElementById('altNotice').style.display = '';
  document.getElementById('altStageNum').textContent = stageNumberByOrder(stepOrder);
  document.getElementById('stepFormTitle').textContent = 'إضافة خيار بديل للمرحلة';
  document.getElementById('stepFormCard').scrollIntoView({ behavior: 'smooth' });
}
function cancelAlternative() {
  document.getElementById('add_step_order').value = '';
  document.getElementById('altNotice').style.display = 'none';
  document.getElementById('stepFormTitle').textContent = 'إضافة مرحلة جديدة';
}

// سحب وإفلات لإعادة ترتيب المراحل (HTML5 drag & drop، بلا أي مكتبة خارجية)
(function () {
  var flow = document.getElementById('stepsFlow');
  if (!flow) return;
  var dragEl = null;
  flow.addEventListener('dragstart', function (e) {
    var card = e.target.closest('.stage-card');
    if (!card) return;
    dragEl = card;
    e.dataTransfer.effectAllowed = 'move';
    setTimeout(function () { card.classList.add('dragging'); }, 0);
  });
  flow.addEventListener('dragend', function () {
    if (dragEl) dragEl.classList.remove('dragging');
    dragEl = null;
    renumberStages();
  });
  flow.addEventListener('dragover', function (e) {
    e.preventDefault();
    if (!dragEl) return;
    var after = getDragAfterElement(flow, e.clientY);
    if (after == null) flow.appendChild(dragEl); else flow.insertBefore(dragEl, after);
  });
  flow.addEventListener('drop', function (e) {
    e.preventDefault();
    renumberStages();
    saveStagesOrder();
  });
  function getDragAfterElement(container, y) {
    var els = Array.prototype.slice.call(container.querySelectorAll('.stage-card:not(.dragging)'));
    return els.reduce(function (closest, child) {
      var box = child.getBoundingClientRect();
      var offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) return { offset: offset, element: child };
      return closest;
    }, { offset: -Infinity }).element;
  }
  function renumberStages() {
    document.querySelectorAll('#stepsFlow .stage-card .step-num').forEach(function (el, i) { el.textContent = i + 1; });
  }
  function saveStagesOrder() {
    var groups = Array.prototype.map.call(document.querySelectorAll('#stepsFlow .stage-card'), function (card) {
      return card.dataset.stepIds.split(',').map(function (x) { return parseInt(x, 10); });
    });
    fetch(REORDER_URL, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ groups: groups })
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (!res.ok) alert('تعذّر حفظ الترتيب، يُرجى إعادة تحميل الصفحة');
    }).catch(function () { alert('تعذّر الاتصال بالخادم لحفظ الترتيب'); });
  }
})();

toggleApproverFields('add');
toggleStepTypeFields('add');
</script>
