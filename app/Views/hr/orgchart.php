<?php
use App\Core\Helpers;

/** خيارات القوائم المنسدلة لاختيار المنصب الأب (مسطّحة مع مسافات بادئة حسب العمق) */
function orgFlattenOptions(array $nodes, int $depth = 0): array {
    $out = [];
    foreach ($nodes as $n) {
        $out[] = ['id' => $n['id'], 'label' => str_repeat('— ', $depth) . $n['title']];
        if (!empty($n['children'])) {
            $out = array_merge($out, orgFlattenOptions($n['children'], $depth + 1));
        }
    }
    return $out;
}
$flatOptions = orgFlattenOptions($tree);

/** عرض الشجرة بشكل متداخل قابل للطي */
function renderOrgNode(array $node, array $flatOptions, array $employees): void {
    $hasChildren = !empty($node['children']);
    ?>
    <li>
      <details open>
        <summary>
          <strong><?= Helpers::e($node['title']) ?></strong>
          <?php if ($node['employee_name'] || $node['linked_employee_name']): ?>
            <span class="badge badge-info"><?= Helpers::e($node['linked_employee_name'] ?: $node['employee_name']) ?></span>
          <?php endif; ?>
          <button type="button" class="btn btn-sm btn-secondary" onclick="toggleEdit(<?= $node['id'] ?>)">تعديل</button>
          <form method="post" action="/hr/orgchart/<?= $node['id'] ?>/delete" style="display:inline"
                onsubmit="return confirm('سيتم حذف هذا المنصب وكل ما يتفرع منه. متابعة؟')">
            <button class="btn btn-sm btn-danger">حذف</button>
          </form>
        </summary>

        <form method="post" action="/hr/orgchart/<?= $node['id'] ?>" id="edit-<?= $node['id'] ?>" class="form-inline" style="display:none;margin:10px 0">
          <div class="form-row"><label>اسم المنصب</label><input name="title" value="<?= Helpers::e($node['title']) ?>" required></div>
          <div class="form-row"><label>اسم الشاغل (نص حر)</label><input name="employee_name" value="<?= Helpers::e($node['employee_name'] ?? '') ?>"></div>
          <div class="form-row"><label>ربط بموظف مسجل</label>
            <select name="employee_id">
              <option value="">—</option>
              <?php foreach ($employees as $e): ?>
                <option value="<?= $e['id'] ?>" <?= (int)($node['employee_id'] ?? 0) === (int)$e['id'] ? 'selected' : '' ?>><?= Helpers::e($e['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row"><label>المنصب الأب</label>
            <select name="parent_id">
              <option value="">— (جذر الهيكل)</option>
              <?php foreach ($flatOptions as $opt): if ((int)$opt['id'] === (int)$node['id']) continue; ?>
                <option value="<?= $opt['id'] ?>" <?= (int)($node['parent_id'] ?? 0) === (int)$opt['id'] ? 'selected' : '' ?>><?= Helpers::e($opt['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row"><label>الترتيب</label><input type="number" name="sort_order" value="<?= (int)$node['sort_order'] ?>" style="width:80px"></div>
          <button class="btn btn-success">حفظ</button>
        </form>

        <?php if ($hasChildren): ?>
        <ul class="org-tree">
          <?php foreach ($node['children'] as $child): renderOrgNode($child, $flatOptions, $employees); endforeach; ?>
        </ul>
        <?php endif; ?>
      </details>
    </li>
    <?php
}
?>

<div class="card">
  <h3>إضافة منصب جديد إلى الهيكل التنظيمي</h3>
  <form method="post" action="/hr/orgchart" class="form-inline">
    <div class="form-row"><label>اسم المنصب</label><input name="title" placeholder="مثال: Sous Chef" required></div>
    <div class="form-row"><label>اسم الشاغل (نص حر)</label><input name="employee_name" placeholder="اسم الموظف"></div>
    <div class="form-row"><label>ربط بموظف مسجل (اختياري)</label>
      <select name="employee_id">
        <option value="">—</option>
        <?php foreach ($employees as $e): ?>
          <option value="<?= $e['id'] ?>"><?= Helpers::e($e['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>المنصب الأب</label>
      <select name="parent_id">
        <option value="">— (جذر الهيكل)</option>
        <?php foreach ($flatOptions as $opt): ?>
          <option value="<?= $opt['id'] ?>"><?= Helpers::e($opt['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الترتيب</label><input type="number" name="sort_order" value="0" style="width:80px"></div>
    <button class="btn btn-success">إضافة</button>
  </form>
</div>

<div class="card">
  <h3>الهيكل التنظيمي</h3>
  <?php if (!$tree): ?>
    <p>لا يوجد هيكل تنظيمي بعد.</p>
  <?php else: ?>
    <ul class="org-tree">
      <?php foreach ($tree as $node): renderOrgNode($node, $flatOptions, $employees); endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<script>
function toggleEdit(id) {
  const el = document.getElementById('edit-' + id);
  el.style.display = el.style.display === 'none' ? 'flex' : 'none';
}
</script>
