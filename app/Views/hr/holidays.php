<?php use App\Core\Helpers; ?>
<?php
$weekDays = [
    0 => 'الأحد', 1 => 'الاثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء',
    4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت',
];
?>
<div class="card">
  <div style="display:flex;gap:10px;margin-bottom:10px">
    <a href="/hr/reports?type=achievement" class="btn btn-light"><i class="fa-solid fa-trophy"></i> تقرير الإنجاز</a>
  </div>
  <h3>عدد أيام العطل للشهر</h3>
  <p class="text-muted">عند تحديد عدد الأيام هنا، يتم استخدامه مباشرة في تقرير الإنجاز بدلاً من حساب العطل من التواريخ.</p>
  <form method="post" action="/hr/holidays/months" class="form-inline">
    <div class="form-row">
      <label>السنة</label>
      <input type="number" name="year" value="<?= date('Y') ?>" required>
    </div>
    <div class="form-row">
      <label>الشهر</label>
      <select name="month" required>
        <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= $m == (int)date('n') ? 'selected' : '' ?>><?= $m ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="form-row">
      <label>عدد أيام العطل</label>
      <input type="number" name="days" min="0" max="31" required>
    </div>
    <button class="btn btn-success">حفظ</button>
  </form>
</div>

<?php if (!empty($holidayMonths)): ?>
<div class="card">
  <h3>الأشهر المحددة</h3>
  <table class="table">
    <tr><th>الشهر</th><th>عدد أيام العطل</th><th></th></tr>
    <?php foreach ($holidayMonths as $hm): ?>
    <tr>
      <td><?= (int)$hm['year'] ?>-<?= str_pad((string)$hm['month'], 2, '0', STR_PAD_LEFT) ?></td>
      <td><?= (int)$hm['days'] ?></td>
      <td>
        <form method="post" action="/hr/holidays/months/<?= (int)$hm['id'] ?>/delete" style="display:inline" onsubmit="return confirm('تأكيد الحذف؟')">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <h3>إضافة عطلة رسمية</h3>
  <form method="post" action="/hr/holidays" class="form-inline">
    <div class="form-row">
      <label>التاريخ (اختياري)</label>
      <input type="date" name="date">
    </div>
    <div class="form-row">
      <label>يوم أسبوعي (اختياري)</label>
      <select name="day_of_week">
        <option value="">—</option>
        <?php foreach ($weekDays as $k => $v): ?>
        <option value="<?= $k ?>"><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <label>الاسم</label>
      <input type="text" name="name" required placeholder="مثال: عيد الفطر">
    </div>
    <button class="btn btn-success">إضافة</button>
  </form>
</div>

<div class="card">
  <h3>العطل المسجلة</h3>
  <table class="table">
    <tr>
      <th>التاريخ</th>
      <th>اليوم الأسبوعي</th>
      <th>الاسم</th>
      <th>الحالة</th>
      <th></th>
    </tr>
    <?php foreach ($holidays as $h): ?>
    <tr>
      <td><?= $h['date'] ? Helpers::e($h['date']) : '-' ?></td>
      <td><?= $h['day_of_week'] !== null ? $weekDays[(int)$h['day_of_week']] : '-' ?></td>
      <td><?= Helpers::e($h['name']) ?></td>
      <td>
        <span class="badge badge-<?= $h['is_active'] ? 'success' : 'secondary' ?>">
          <?= $h['is_active'] ? 'نشط' : 'معطل' ?>
        </span>
      </td>
      <td>
        <form method="post" action="/hr/holidays/<?= (int)$h['id'] ?>/delete" style="display:inline" onsubmit="return confirm('تأكيد الحذف؟')">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($holidays)): ?>
    <tr><td colspan="5" class="text-center">لا توجد عطل مسجلة</td></tr>
    <?php endif; ?>
  </table>
</div>
