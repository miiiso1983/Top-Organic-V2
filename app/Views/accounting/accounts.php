<?php use App\Core\{Helpers, AccountingService, ImportHelper}; ?>
<?php $importErrors = ImportHelper::getErrors(); ?>
<?php if ($importErrors): ?>
<div class="alert alert-danger">
  <strong>أخطاء الاستيراد:</strong>
  <ul><?php foreach ($importErrors as $err): ?><li><?= Helpers::e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>
<style>
.accounts-layout { grid-template-columns: 3fr minmax(300px, 1fr); }
@media (max-width: 900px) { .accounts-layout { grid-template-columns: 1fr; } }
.account-tree td { vertical-align: middle; }
.account-tree .toggle-btn {
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  width: 22px;
  height: 22px;
  line-height: 18px;
  padding: 0;
  margin-left: 6px;
  cursor: pointer;
  font-weight: 700;
  color: #374151;
}
.account-tree .toggle-btn:hover { background: #e5e7eb; }
.account-tree .account-name { display: inline-flex; align-items: center; }
.account-tree .level-1 td:first-child { padding-right: 32px; }
.account-tree .level-2 td:first-child { padding-right: 56px; }
.account-tree .level-3 td:first-child { padding-right: 80px; }
.account-tree .level-4 td:first-child { padding-right: 104px; }
.account-tree .level-5 td:first-child { padding-right: 128px; }
.account-tree .parent-row { background: #f9fafb; cursor: pointer; }
.account-tree .parent-row:hover { background: #f3f4f6; }
</style>

<div class="grid accounts-layout">
  <div class="card">
    <h3>شجرة الحسابات</h3>
    <form method="get" action="/accounting/accounts" style="margin-bottom:15px">
      <div class="form-row" style="display:flex;gap:8px">
        <input type="text" name="search" value="<?= Helpers::e($search ?? '') ?>" placeholder="بحث بالكود أو الاسم" style="flex:1">
        <button class="btn btn-sm btn-primary">بحث</button>
        <?php if (($search ?? '') !== ''): ?>
          <a href="/accounting/accounts" class="btn btn-sm">إلغاء</a>
        <?php endif; ?>
      </div>
    </form>
    <?php
    $accountMap = [];
    foreach ($accounts as $a) { $accountMap[$a['id']] = $a; }
    function buildTree(array $list, ?int $parentId): array {
        $tree = [];
        foreach ($list as $a) {
            if (($a['parent_id'] ?? null) == $parentId) {
                $a['children'] = buildTree($list, $a['id']);
                $tree[] = $a;
            }
        }
        return $tree;
    }
    function renderTreeRows(array $tree, int $level = 0): void {
        foreach ($tree as $a) {
            $hasChildren = !empty($a['children']);
            $rowClass = $hasChildren ? 'parent-row' : '';
            $hidden = $level > 0 ? 'display:none' : '';
            ?>
            <tr class="account-row level-<?= $level ?> <?= $rowClass ?>"
                data-account-id="<?= (int)$a['id'] ?>"
                data-parent-id="<?= Helpers::e($a['parent_id'] ?? '') ?>"
                style="<?= $hidden ?>">
              <td>
                <span class="account-name">
                  <?php if ($hasChildren): ?>
                    <button type="button" class="toggle-btn" data-account-id="<?= (int)$a['id'] ?>" data-expanded="0">+</button>
                  <?php else: ?>
                    <span style="display:inline-block;width:22px;margin-left:6px"></span>
                  <?php endif; ?>
                  <?= Helpers::e($a['code']) ?>
                </span>
              </td>
              <td><?= Helpers::e($a['name']) ?></td>
              <td><span class="badge badge-info"><?= $a['type'] ?></span></td>
              <td><?= Helpers::money(\App\Core\AccountingService::balance((int)$a['id'])) ?></td>
              <td>
                <a href="/accounting/accounts/<?= (int)$a['id'] ?>/edit" class="btn btn-xs">تعديل</a>
                <form method="post" action="/accounting/accounts/<?= (int)$a['id'] ?>/delete" style="display:inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الحساب؟')">
                  <button type="submit" class="btn btn-xs btn-danger">حذف</button>
                </form>
              </td>
            </tr>
            <?php
            if ($hasChildren) {
                renderTreeRows($a['children'], $level + 1);
            }
        }
    }
    $tree = buildTree($accountMap, null);
    ?>
    <?php if ($search ?? ''): ?>
    <table class="account-tree">
      <tr><th>الكود</th><th>الاسم</th><th>النوع</th><th>الرصيد</th><th>إجراءات</th></tr>
      <?php foreach ($accounts as $a): ?>
      <tr>
        <td><?= Helpers::e($a['code']) ?></td>
        <td><?= Helpers::e($a['name']) ?></td>
        <td><span class="badge badge-info"><?= $a['type'] ?></span></td>
        <td><?= Helpers::money(AccountingService::balance((int)$a['id'])) ?></td>
        <td>
          <a href="/accounting/accounts/<?= (int)$a['id'] ?>/edit" class="btn btn-xs">تعديل</a>
          <form method="post" action="/accounting/accounts/<?= (int)$a['id'] ?>/delete" style="display:inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الحساب؟')">
            <button type="submit" class="btn btn-xs btn-danger">حذف</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
    <table class="account-tree">
      <tr><th>الكود</th><th>الاسم</th><th>النوع</th><th>الرصيد</th><th>إجراءات</th></tr>
      <?php renderTreeRows($tree, 0); ?>
    </table>
    <?php endif; ?>
  </div>
  <div class="card">
    <h3>إضافة حساب</h3>
    <form method="post" action="/accounting/accounts">
      <div class="form-row">
        <label>الحساب الأب</label>
        <select name="parent_id" required>
          <?php foreach ($parents as $p): ?>
            <option value="<?= $p['id'] ?>"><?= Helpers::e($p['code'] . ' - ' . $p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label>اسم الحساب</label>
        <input type="text" name="name" required>
      </div>
      <div class="form-row">
        <label>النوع</label>
        <select name="type" required>
          <option value="asset">أصل</option>
          <option value="liability">خصم</option>
          <option value="equity">حقوق ملكية</option>
          <option value="revenue">إيراد</option>
          <option value="expense">مصروف</option>
        </select>
      </div>
      <div class="form-row">
        <label>المجموع</label>
        <input type="text" name="account_group" placeholder="current_assets">
      </div>
      <div class="form-row">
        <label>رصيد افتتاحي</label>
        <input type="number" name="opening_balance" step="0.01" value="0">
      </div>
      <button class="btn btn-success">إضافة حساب</button>
    </form>

    <hr style="margin:20px 0">
    <h4>استيراد ملف Excel (النموذج العام)</h4>
    <a href="/accounting/accounts/template" class="btn btn-sm">تحميل النموذج</a>
    <form method="post" action="/accounting/accounts/import" enctype="multipart/form-data" style="margin-top:10px">
      <div class="form-row">
        <input type="file" name="import_file" accept=".xlsx,.xls" required>
      </div>
      <button class="btn btn-sm btn-primary">رفع واستيراد</button>
    </form>

    <hr style="margin:20px 0">
    <h4>استيراد شجرة حسابات TOP</h4>
    <p class="text-muted" style="font-size:12px">الأعمدة المطلوبة: الحساب | الحساب الرئيسي | ختامي</p>
    <form method="post" action="/accounting/accounts/import-coa" enctype="multipart/form-data" style="margin-top:10px">
      <div class="form-row">
        <input type="file" name="import_file" accept=".xlsx,.xls" required>
      </div>
      <button class="btn btn-sm btn-primary">رفع واستيراد شجرة الحسابات</button>
    </form>
  </div>
</div>

<script>
(function(){
  function toggleChildren(parentId, show) {
    document.querySelectorAll('.account-tree tr[data-parent-id="' + parentId + '"]').forEach(function(row) {
      row.style.display = show ? '' : 'none';
      if (!show) {
        var childId = row.getAttribute('data-account-id');
        if (childId) {
          toggleChildren(childId, false);
          var btn = row.querySelector('.toggle-btn');
          if (btn) {
            btn.setAttribute('data-expanded', '0');
            btn.textContent = '+';
          }
        }
      }
    });
  }

  document.querySelectorAll('.account-tree .toggle-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      var parentId = this.getAttribute('data-account-id');
      var expanded = this.getAttribute('data-expanded') === '1';
      toggleChildren(parentId, !expanded);
      this.setAttribute('data-expanded', expanded ? '0' : '1');
      this.textContent = expanded ? '+' : '−';
    });
  });

  document.querySelectorAll('.account-tree .parent-row').forEach(function(row) {
    row.addEventListener('click', function(e) {
      if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || e.target.closest('form')) return;
      var btn = this.querySelector('.toggle-btn');
      if (btn) btn.click();
    });
  });
})();
</script>
