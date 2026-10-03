<?php use App\Core\Helpers; ?>
<div class="grid grid-2">
  <div class="card">
    <h3>إضافة أصل ثابت</h3>
    <form method="post" action="/accounting/assets">
      <div class="form-row"><label>الاسم</label><input type="text" name="name" required></div>
      <div class="form-row"><label>الفئة</label>
        <select name="category_id"><option value="">بدون</option>
          <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= Helpers::e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>تاريخ الشراء</label><input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>" required></div>
      <div class="form-row"><label>القيمة</label><input type="number" name="purchase_cost" step="0.01" required></div>
      <div class="form-row"><label>قيمة البقاءة</label><input type="number" name="salvage_value" step="0.01" value="0"></div>
      <div class="form-row"><label>العمر (سنوات)</label><input type="number" name="life_years" value="5" required></div>
      <div class="form-row"><label>طريقة الإهلاك</label>
        <select name="depreciation_method"><option value="straight_line">القسط الثابت</option><option value="declining_balance">المتناقص</option></select>
      </div>
      <div class="form-row"><label>مركز التكلفة</label>
        <select name="cost_center_id"><option value="">بدون</option>
          <?php foreach ($costCenters as $cc): ?><option value="<?= $cc['id'] ?>"><?= Helpers::e($cc['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>الفرع</label>
        <select name="branch_id"><option value="">بدون</option>
          <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= Helpers::e($b['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>ملاحظات</label><textarea name="notes" rows="2"></textarea></div>
      <button class="btn btn-success">حفظ الأصل</button>
    </form>
  </div>
  <div class="card">
    <h3>الأصول الثابتة</h3>
    <form method="post" action="/accounting/assets/depreciate" class="form-inline" style="margin-bottom:14px">
      <div class="form-row"><label>الفترة</label><input type="month" name="period" value="<?= date('Y-m') ?>" required></div>
      <button class="btn btn-secondary">إحلال إهلاك الفترة</button>
    </form>
    <table>
      <tr><th>رقم</th><th>الاسم</th><th>القيمة</th><th>الحالة</th></tr>
      <?php foreach ($assets as $a): ?>
      <tr>
        <td><?= Helpers::e($a['asset_no']) ?></td>
        <td><?= Helpers::e($a['name']) ?></td>
        <td><?= Helpers::money($a['purchase_cost']) ?></td>
        <td><span class="badge badge-<?= $a['status'] ?>"><?= $a['status'] ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
