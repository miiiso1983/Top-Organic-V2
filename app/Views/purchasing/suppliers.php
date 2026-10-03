<?php use App\Core\{Helpers, ImportHelper}; ?>
<?php $importErrors = ImportHelper::getErrors(); ?>
<?php if ($importErrors): ?>
<div class="alert alert-danger">
  <strong>أخطاء الاستيراد:</strong>
  <ul><?php foreach ($importErrors as $err): ?><li><?= Helpers::e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>
<div class="card">
  <h3>إضافة مورد جديد (مع بيانات العقد)</h3>
  <form method="post" action="/suppliers" class="form-inline">
    <div class="form-row"><label>الاسم</label><input name="name" required></div>
    <div class="form-row"><label>الهاتف</label><input name="phone"></div>
    <div class="form-row"><label>البريد</label><input type="email" name="email"></div>
    <div class="form-row"><label>العنوان</label><input name="address"></div>
    <div class="form-row"><label>بداية العقد</label><input type="date" name="contract_start"></div>
    <div class="form-row"><label>نهاية العقد</label><input type="date" name="contract_end"></div>
    <button class="btn btn-success">إضافة</button>
  </form>

  <hr style="margin:20px 0">
  <h4>استيراد ملف Excel</h4>
  <a href="/suppliers/template" class="btn btn-sm btn-secondary">تنزيل النموذج</a>
  <form method="post" action="/suppliers/import" enctype="multipart/form-data" style="margin-top:10px">
    <div class="form-row"><input type="file" name="import_file" accept=".xlsx,.xls" required></div>
    <button class="btn btn-sm btn-primary">رفع واستيراد</button>
  </form>
</div>

<div class="card">
  <h3>الموردون وتقييم الأداء</h3>
  <table>
    <tr><th>الاسم</th><th>الهاتف</th><th>العقد</th><th>أوامر الشراء</th><th>الرصيد</th><th>التقييم</th><th>تقييم جديد</th></tr>
    <?php foreach ($suppliers as $s): ?>
    <tr>
      <td><?= Helpers::e($s['name']) ?></td>
      <td><?= Helpers::e($s['phone'] ?? '-') ?></td>
      <td>
        <?php if ($s['contract_end']): ?>
          <?= $s['contract_start'] ?> → <?= $s['contract_end'] ?>
          <?php if ($s['contract_end'] < date('Y-m-d')): ?><span class="badge badge-danger">منتهي</span><?php endif; ?>
        <?php else: ?>-<?php endif; ?>
      </td>
      <td><?= $s['orders_count'] ?></td>
      <td><?= Helpers::money($s['balance']) ?></td>
      <td><?= str_repeat('<i class="fa-solid fa-star"></i>', (int)$s['rating']) . str_repeat('<i class="fa-regular fa-star"></i>', 5 - (int)$s['rating']) ?>
          <?php if ($s['avg_rating']): ?><small>(<?= $s['avg_rating'] ?>)</small><?php endif; ?></td>
      <td>
        <form method="post" action="/suppliers/<?= $s['id'] ?>/evaluate" class="form-inline">
          <select name="quality" title="الجودة">
            <?php for ($i=1;$i<=5;$i++): ?><option value="<?= $i ?>" <?= $i===3?'selected':'' ?>>جودة <?= $i ?></option><?php endfor; ?>
          </select>
          <select name="delivery" title="التسليم">
            <?php for ($i=1;$i<=5;$i++): ?><option value="<?= $i ?>" <?= $i===3?'selected':'' ?>>تسليم <?= $i ?></option><?php endfor; ?>
          </select>
          <select name="pricing" title="الأسعار">
            <?php for ($i=1;$i<=5;$i++): ?><option value="<?= $i ?>" <?= $i===3?'selected':'' ?>>سعر <?= $i ?></option><?php endfor; ?>
          </select>
          <button class="btn btn-sm">حفظ</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$suppliers): ?><tr><td colspan="7">لا يوجد موردون بعد</td></tr><?php endif; ?>
  </table>
</div>
