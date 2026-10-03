<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h2>الأصول الثابتة</h2>
  <form method="get" class="form-inline" style="gap:10px; flex-wrap:wrap">
    <input type="text" name="q" placeholder="بحث (اسم/رقم/باركود)" value="<?= Helpers::e($filters['q'] ?? '') ?>">
    <select name="status"><option value="">كل الحالات</option>
      <?php foreach (['active' => 'نشط', 'sold' => 'مباع', 'disposed' => 'مستبعد'] as $k => $v): ?>
      <option value="<?= $k ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <select name="branch_id"><option value="">كل الفروع</option>
      <?php foreach ($branches as $b): ?>
      <option value="<?= $b['id'] ?>" <?= ($filters['branch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="category_id"><option value="">كل الفئات</option>
      <?php foreach ($categories as $c): ?>
      <option value="<?= $c['id'] ?>" <?= ($filters['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= Helpers::e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-secondary">بحث</button>
    <a href="/accounting/fixed-assets/create" class="btn btn-success">+ أصل جديد</a>
    <a href="/accounting/fixed-assets/import" class="btn btn-secondary">استيراد Excel</a>
    <a href="/accounting/fixed-assets/alerts" class="btn btn-secondary">تنبيهات الصيانة</a>
    <a href="/accounting/fixed-assets/scan" class="btn btn-secondary">مسح QR</a>
  </form>
</div>
<div class="card">
  <div class="form-inline" style="justify-content:space-between; margin-bottom:12px">
    <h3>قائمة الأصول</h3>
    <form method="post" action="/accounting/fixed-assets/depreciate" class="form-inline">
      <input type="month" name="period" value="<?= date('Y-m') ?>" required>
      <button class="btn btn-secondary">إحلال إهلاك الفترة</button>
    </form>
  </div>
  <table>
    <tr><th>الرقم</th><th>الاسم</th><th>الفئة</th><th>الفرع</th><th>القسم</th><th>المسؤول</th><th>القيمة</th><th>الدفترية</th><th>الحالة</th><th></th></tr>
    <?php foreach ($assets as $a): ?>
    <tr>
      <td><?= Helpers::e($a['asset_no']) ?></td>
      <td><?= Helpers::e($a['name']) ?></td>
      <td><?= Helpers::e($a['category_name'] ?? '') ?></td>
      <td><?= Helpers::e($a['branch_name'] ?? '') ?></td>
      <td><?= Helpers::e($a['department_name'] ?? '') ?></td>
      <td><?= Helpers::e($a['responsible_name'] ?? '') ?></td>
      <td><?= Helpers::money($a['purchase_cost']) ?></td>
      <td><?= Helpers::money($a['book_value']) ?></td>
      <td><span class="badge badge-<?= $a['status'] ?>"><?= $a['status'] ?></span></td>
      <td><a href="/accounting/fixed-assets/<?= $a['id'] ?>" class="btn btn-sm">عرض</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
