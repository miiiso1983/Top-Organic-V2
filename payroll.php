<?php use App\Core\Helpers; ?>
<?php $statusLabels = ['draft'=>'مسودة','approved'=>'معتمد','paid'=>'مدفوع']; ?>
<div class="card">
  <h3>الرواتب الشهرية</h3>
  <form method="get" action="/hr/payroll" class="form-inline">
    <div class="form-row"><label>الفترة</label><input type="month" name="period" value="<?= Helpers::e($period) ?>"></div>
    <div class="form-row"><label>الموظف</label>
      <select name="employee_id"><option value="0">الكل</option><?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>" <?= $e['id'] == $selected_employee ? 'selected' : '' ?>><?= Helpers::e($e['full_name']) ?></option><?php endforeach; ?></select>
    </div>
    <button class="btn">عرض</button>
  </form>
  <form method="post" action="/hr/payroll/generate" style="margin-top:10px">
    <input type="hidden" name="period" value="<?= Helpers::e($period) ?>">
    <button class="btn btn-success"><i class="fa-solid fa-gear"></i> توليد رواتب الفترة <?= Helpers::e($period) ?></button>
  </form>
  <p class="text-muted" style="margin-top:10px">
    <a href="/hr/payroll/allowances">إدارة البدلات الثابتة</a> |
    توليد الراتب يحسب: الأساسي + الزيادات + البدلات + الإضافي + المكافآت − الخصومات − الجزاءات − السلف − الغيابات − الضريبة.
  </p>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h3>كشف رواتب <?= Helpers::e($period) ?></h3>
    <div>
      <a href="?period=<?= Helpers::e($period) ?>&employee_id=<?= $selected_employee ?>&format=excel" class="btn btn-sm">تصدير Excel</a>
      <a href="?period=<?= Helpers::e($period) ?>&employee_id=<?= $selected_employee ?>&format=pdf" class="btn btn-sm">تصدير PDF</a>
      <button type="button" class="btn btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i> طباعة</button>
    </div>
  </div>
  <table>
    <tr><th>الموظف</th><th>القسم</th><th>أساسي</th><th>زيادات</th><th>بدلات</th><th>إضافي</th><th>مكافآت</th><th>خصومات</th><th>ضريبة</th><th>سلف</th><th>صافي</th><th>الحالة</th><th></th></tr>
    <?php $totalNet = 0; foreach ($payrolls as $p): $totalNet += (float)$p['net_salary']; ?>
    <tr>
      <td><?= Helpers::e($p['full_name']) ?></td>
      <td><?= Helpers::e($p['department'] ?? '-') ?></td>
      <td><?= Helpers::money($p['base_salary']) ?></td>
      <td style="color:#16a34a">+<?= Helpers::money($p['raises']) ?></td>
      <td style="color:#16a34a">+<?= Helpers::money($p['allowances'] ?? 0) ?></td>
      <td style="color:#16a34a">+<?= Helpers::money($p['overtime'] ?? 0) ?></td>
      <td style="color:#16a34a">+<?= Helpers::money($p['bonuses']) ?></td>
      <td style="color:#dc2626">−<?= Helpers::money($p['deductions']) ?></td>
      <td style="color:#dc2626">−<?= Helpers::money($p['tax_amount'] ?? 0) ?></td>
      <td style="color:#dc2626">−<?= Helpers::money($p['advances']) ?></td>
      <td style="font-weight:bold"><?= Helpers::money($p['net_salary']) ?></td>
      <td><span class="badge badge-<?= $p['status'] === 'paid' ? 'success' : ($p['status'] === 'approved' ? 'info' : 'warning') ?>"><?= $statusLabels[$p['status']] ?></span></td>
      <td>
        <a href="/hr/payroll/<?= $p['id'] ?>/slip" class="btn btn-sm">ورقة</a>
        <?php if ($p['status'] === 'draft' && \App\Core\Auth::can('payroll.approve')): ?>
        <form method="post" action="/hr/payroll/<?= $p['id'] ?>/approve" style="display:inline">
          <button class="btn btn-sm btn-info">اعتماد</button>
        </form>
        <?php endif; ?>
        <?php if ($p['status'] !== 'draft' && $p['status'] !== 'paid' && \App\Core\Auth::can('payroll.overtime')): ?>
        <button class="btn btn-sm btn-secondary" onclick="document.getElementById('ot-<?= $p['id'] ?>').style.display='block'">+ إضافي</button>
        <?php endif; ?>
        <?php if ($p['status'] === 'approved' && \App\Core\Auth::can('payroll.manage')): ?>
        <form method="post" action="/hr/payroll/<?= $p['id'] ?>/pay" style="display:inline">
          <button class="btn btn-sm btn-success">صرف</button>
        </form>
        <?php endif; ?>
        <div id="ot-<?= $p['id'] ?>" style="display:none;margin-top:8px">
          <form method="post" action="/hr/payroll/<?= $p['id'] ?>/overtime" class="form-inline">
            <input type="number" name="hours" step="0.01" placeholder="ساعات" required>
            <input type="number" name="hourly_rate" step="0.01" placeholder="سعر الساعة" required>
            <input type="number" name="multiplier" step="0.01" value="1.0" placeholder="مضاعف" required>
            <input name="notes" placeholder="ملاحظات">
            <button class="btn btn-sm">حفظ</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if ($payrolls): ?>
    <tr style="font-weight:bold"><td colspan="10">الإجمالي</td><td><?= Helpers::money($totalNet) ?></td><td colspan="2"></td></tr>
    <?php else: ?>
    <tr><td colspan="13">لم تولَّد رواتب لهذه الفترة بعد</td></tr>
    <?php endif; ?>
  </table>
</div>
