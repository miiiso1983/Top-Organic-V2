<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>إضافة شريحة ضريبية</h3>
  <form method="post" action="/hr/payroll/tax-brackets" class="form-inline">
    <div class="form-row"><label>الحد الأدنى</label><input type="number" name="min_amount" step="0.01" min="0" value="0" required></div>
    <div class="form-row"><label>الحد الأعلى</label><input type="number" name="max_amount" step="0.01" min="0" placeholder="بلا حد أعلى"></div>
    <div class="form-row"><label>نسبة الضريبة (%)</label><input type="number" name="rate" step="0.01" min="0" max="100" value="0" required></div>
    <button class="btn btn-success">إضافة</button>
  </form>
</div>

<div class="card">
  <h4>الشرائح الضريبية الحالية</h4>
  <table>
    <tr><th>الحد الأدنى</th><th>الحد الأعلى</th><th>نسبة الضريبة</th><th>الحالة</th><th></th></tr>
    <?php foreach ($brackets as $b): ?>
    <tr>
      <td><?= Helpers::money($b['min_amount']) ?></td>
      <td><?= $b['max_amount'] !== null ? Helpers::money($b['max_amount']) : 'بلا حد أعلى' ?></td>
      <td><?= rtrim(rtrim(number_format($b['rate'] * 100, 2), '0'), '.') ?>%</td>
      <td><span class="badge badge-<?= $b['is_active'] ? 'success' : 'secondary' ?>"><?= $b['is_active'] ? 'فعالة' : 'معطلة' ?></span></td>
      <td>
        <form method="post" action="/hr/payroll/tax-brackets/<?= $b['id'] ?>/toggle" style="display:inline">
          <button class="btn btn-sm btn-<?= $b['is_active'] ? 'warning' : 'success' ?>"><?= $b['is_active'] ? 'إيقاف' : 'تفعيل' ?></button>
        </form>
        <form method="post" action="/hr/payroll/tax-brackets/<?= $b['id'] ?>/delete" style="display:inline" onsubmit="return confirm('تأكيد الحذف؟')">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$brackets): ?><tr><td colspan="5">لا توجد شرائح ضريبية مسجلة</td></tr><?php endif; ?>
  </table>
  <p style="color:#888;font-size:.9em;margin-top:8px">لتعديل شريحة: احذفها وأضف شريحة جديدة بالقيم الصحيحة.</p>
</div>

<p style="margin-top:10px">
  <a href="/hr/payroll">← العودة إلى كشف الرواتب</a>
</p>
