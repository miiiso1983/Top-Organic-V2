<?php use App\Core\{Helpers, Database}; ?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<title>تشخيص الصلاحيات</title>
<style>
body{font-family:'Cairo',Tahoma,Arial,sans-serif;background:#f2f4f7;padding:24px;line-height:1.6}
.card{background:#fff;border-radius:10px;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
h2,h3{margin-top:0}
table{width:100%;border-collapse:collapse;margin-top:10px}
th,td{padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:right;font-size:14px}
th{background:#f9fafb}
.has{color:#16a34a;font-weight:bold}
.missing{color:#dc2626}
pre{background:#f3f4f6;padding:10px;border-radius:6px;overflow:auto}
</style>
</head>
<body>
  <h2>تشخيص الصلاحيات للمستخدم الحالي</h2>

  <div class="card">
    <h3>بيانات الجلسة</h3>
    <table>
      <tr><th>المفتاح</th><th>القيمة</th></tr>
      <tr><td>user_id</td><td><?= (int)($_SESSION['user_id'] ?? 0) ?></td></tr>
      <tr><td>user_name</td><td><?= Helpers::e($_SESSION['user_name'] ?? '') ?></td></tr>
      <tr><td>role_id</td><td><?= (int)($_SESSION['role_id'] ?? 0) ?></td></tr>
      <tr><td>role_name</td><td><?= Helpers::e($_SESSION['role_name'] ?? '') ?></td></tr>
      <tr><td>branch_id</td><td><?= (int)($_SESSION['branch_id'] ?? 0) ?></td></tr>
    </table>
  </div>

  <div class="card">
    <h3>الصلاحيات في الجلسة (<?= count($_SESSION['perms'] ?? []) ?>)</h3>
    <pre><?= Helpers::e(implode("\n", $_SESSION['perms'] ?? [])) ?></pre>
  </div>

  <div class="card">
    <h3>صلاحيات الدور من قاعدة البيانات</h3>
    <?php
    $roleId = (int)($_SESSION['role_id'] ?? 0);
    $dbPerms = [];
    if ($roleId) {
        $rows = Database::query(
            "SELECT p.code, p.name, p.module FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ? ORDER BY p.module, p.name",
            [$roleId]
        )->fetchAll();
        $dbPerms = array_column($rows, 'code');
    }
    ?>
    <table>
      <tr><th>الكود</th><th>الاسم</th><th>الموديل</th><th>في الجلسة</th></tr>
      <?php foreach ($rows as $p): ?>
      <tr>
        <td><?= Helpers::e($p['code']) ?></td>
        <td><?= Helpers::e($p['name']) ?></td>
        <td><?= Helpers::e($p['module']) ?></td>
        <td class="has"><i class="fa-solid fa-check"></i></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="4">لا توجد صلاحيات مسندة للدور</td></tr><?php endif; ?>
    </table>
  </div>

  <div class="card">
    <h3>فحص مهم: sales.view</h3>
    <p>حالة الصلاحية في الجلسة: <strong class="<?= in_array('sales.view', $_SESSION['perms'] ?? [], true) ? 'has' : 'missing' ?>"><?= in_array('sales.view', $_SESSION['perms'] ?? [], true) ? 'موجودة' : 'غير موجودة' ?></strong></p>
    <p>حالة الصلاحية في قاعدة البيانات: <strong class="<?= in_array('sales.view', $dbPerms, true) ? 'has' : 'missing' ?>"><?= in_array('sales.view', $dbPerms, true) ? 'موجودة' : 'غير موجودة' ?></strong></p>
  </div>
</body>
</html>
