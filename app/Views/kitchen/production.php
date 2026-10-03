<?php use App\Core\Helpers; ?>
<div class="grid grid-2">
  <div class="card">
    <h3>أمر إنتاج جديد</h3>
    <form method="post" action="/production" class="form-inline">
      <div class="form-row"><label>المادة المحضرة</label>
        <select name="item_id" required>
          <?php foreach ($preparedItems as $pi): ?>
            <option value="<?= $pi['id'] ?>"><?= Helpers::e($pi['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>الكمية المخططة</label><input type="number" name="planned_qty" step="0.001" min="0.001" required></div>
      <div class="form-row"><label>التاريخ</label><input type="date" name="production_date" value="<?= date('Y-m-d') ?>"></div>
      <button class="btn btn-success">إنشاء</button>
    </form>
    <?php if (!$preparedItems): ?>
      <p style="color:#d97706;margin-top:8px"><i class="fa-solid fa-triangle-exclamation"></i> أضف مواداً بتصنيف "مواد محضرة" في المخزون أولاً</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>تسجيل هدر</h3>
    <form method="post" action="/waste" class="form-inline">
      <div class="form-row"><label>المادة</label>
        <select name="item_id" required>
          <?php foreach ($allItems as $ai): ?>
            <option value="<?= $ai['id'] ?>"><?= Helpers::e($ai['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>الكمية</label><input type="number" name="qty" step="0.001" min="0.001" required></div>
      <div class="form-row"><label>السبب</label><input name="reason"></div>
      <button class="btn btn-warning">تسجيل</button>
    </form>
  </div>
</div>

<div class="card">
  <h3>أوامر الإنتاج</h3>
  <table>
    <tr><th>الرقم</th><th>المادة</th><th>المخطط</th><th>الفعلي</th><th>الهدر</th><th>الحالة</th><th>التاريخ</th><th></th></tr>
    <?php foreach ($productions as $p): ?>
    <tr>
      <td><?= Helpers::e($p['production_no']) ?></td>
      <td><?= Helpers::e($p['item_name']) ?></td>
      <td><?= (float)$p['planned_qty'] ?></td>
      <td><?= (float)$p['actual_qty'] ?></td>
      <td style="color:#dc2626"><?= (float)$p['waste_qty'] ?></td>
      <td><span class="badge badge-<?= $p['status'] ?>">
        <?= ['planned'=>'مخطط','in_progress'=>'قيد التنفيذ','done'=>'منجز','cancelled'=>'ملغي'][$p['status']] ?></span></td>
      <td><?= $p['production_date'] ?></td>
      <td>
        <?php if ($p['status'] === 'in_progress'): ?>
        <form method="post" action="/production/<?= $p['id'] ?>/complete" class="form-inline">
          <input type="number" name="actual_qty" placeholder="الكمية الفعلية" step="0.001" required style="width:120px">
          <button class="btn btn-sm btn-success">إتمام</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$productions): ?><tr><td colspan="8">لا توجد أوامر إنتاج</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <h3>سجل الهدر</h3>
  <table>
    <tr><th>المادة</th><th>الكمية</th><th>السبب</th><th>التاريخ</th></tr>
    <?php foreach ($waste as $w): ?>
    <tr><td><?= Helpers::e($w['item_name']) ?></td><td><?= (float)$w['qty'] ?></td>
        <td><?= Helpers::e($w['reason']) ?></td><td><?= date('m/d H:i', strtotime($w['created_at'])) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$waste): ?><tr><td colspan="4">لا يوجد هدر مسجل</td></tr><?php endif; ?>
  </table>
</div>
