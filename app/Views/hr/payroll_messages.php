<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>توليد رسائل كشف الرواتب - <?= Helpers::e($period) ?></h3>
  <form method="get" action="/hr/payroll/messages" class="form-inline">
    <div class="form-row"><label>الفترة</label><input type="month" name="period" value="<?= Helpers::e($period) ?>" required></div>
    <button class="btn">عرض</button>
  </form>
</div>

<?php if ($payrolls): ?>
<div class="card">
  <form method="post" action="/hr/payroll/messages/send" onsubmit="return confirm('تأكيد الإرسال؟')">
    <input type="hidden" name="period" value="<?= Helpers::e($period) ?>">
    <div class="form-row" style="align-items:flex-start;flex-direction:column">
      <label>نص الرسالة (استخدم {name} {period} {net} {base} {deductions})</label>
      <textarea name="template" rows="3" style="width:100%"><?= Helpers::e($template) ?></textarea>
    </div>
    <div class="form-row">
      <label>قناة الإرسال</label>
      <select name="channel">
        <option value="whatsapp">WhatsApp</option>
        <option value="sms">SMS</option>
        <option value="email">Email</option>
      </select>
    </div>
    <button class="btn btn-success" type="button" onclick="document.querySelectorAll('.emp-check').forEach(c=>c.checked=true)">تحديد الكل</button>
    <button class="btn btn-light" type="button" onclick="document.querySelectorAll('.emp-check').forEach(c=>c.checked=false)">إلغاء التحديد</button>
    <button class="btn btn-primary" style="margin-inline-start:10px">إرسال الرسائل</button>

    <table style="margin-top:15px">
      <tr><th><input type="checkbox" onclick="document.querySelectorAll('.emp-check').forEach(c=>c.checked=this.checked)"></th><th>الموظف</th><th>الصافي</th><th>القناة المتاحة</th><th>معاينة</th></tr>
      <?php foreach ($payrolls as $p): ?>
      <?php
        $preview = str_replace(
            ['{name}', '{period}', '{net}', '{base}', '{deductions}'],
            [Helpers::e($p['full_name']), $period, Helpers::money($p['net_salary']), Helpers::money($p['base_salary']), Helpers::money($p['total_deductions'])],
            $template
        );
        $available = $p['user_id'] ? 'مستخدم' : ($p['phone'] ? 'هاتف' : ($p['email'] ? 'بريد' : 'لا يوجد'));
      ?>
      <tr>
        <td><input type="checkbox" name="employee_ids[]" value="<?= $p['employee_id'] ?>" class="emp-check" <?= $p['phone'] || $p['email'] || $p['user_id'] ? 'checked' : '' ?>></td>
        <td><?= Helpers::e($p['full_name']) ?></td>
        <td><?= Helpers::money($p['net_salary']) ?></td>
        <td><?= $available ?></td>
        <td><small style="white-space:pre-line"><?= Helpers::e($preview) ?></small></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </form>
</div>
<?php else: ?>
<div class="card"><p>لا توجد رواتب مولّدة للفترة.</p></div>
<?php endif; ?>

<p style="margin-top:10px">
  <a href="/hr/payroll?period=<?= urlencode($period) ?>">← العودة إلى كشف الرواتب</a>
</p>
