<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2>التفويضات والبدلاء</h2>
  <a href="/workflow-engine" class="btn btn-secondary">رجوع للمحرك</a>
</div>

<div class="card">
  <h3>تفويض جديد</h3>
  <form method="post" action="/workflow-engine/delegations" class="form-inline">
    <div class="form-row"><label>من (المفوِّض)</label>
      <select name="from_user_id" required>
        <option value="">— اختر —</option>
        <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= Helpers::e($u['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>إلى (المفوَّض إليه)</label>
      <select name="to_user_id" required>
        <option value="">— اختر —</option>
        <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= Helpers::e($u['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>النطاق</label>
      <select name="scope">
        <option value="all">كل الطلبات</option>
        <?php foreach ($definitions as $d): ?><option value="<?= Helpers::e($d['request_type']) ?>"><?= Helpers::e($d['request_type']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>من تاريخ</label><input type="date" name="valid_from" required></div>
    <div class="form-row"><label>إلى تاريخ</label><input type="date" name="valid_to" required></div>
    <button class="btn btn-success">إنشاء التفويض</button>
  </form>
  <small style="color:#6b7280">خلال فترة التفويض تُحوَّل مهام الموافقة تلقائياً من المفوِّض إلى المفوَّض إليه.</small>
</div>

<div class="card">
  <h3>التفويضات الحالية</h3>
  <table>
    <tr><th>من</th><th>إلى</th><th>النطاق</th><th>الفترة</th><th>الحالة</th><th></th></tr>
    <?php foreach ($delegations as $d): ?>
    <tr>
      <td><?= Helpers::e($d['from_name']) ?></td>
      <td><?= Helpers::e($d['to_name']) ?></td>
      <td><?= $d['scope'] === 'all' ? 'كل الطلبات' : Helpers::e($d['scope']) ?></td>
      <td><?= Helpers::e($d['valid_from']) ?> ← <?= Helpers::e($d['valid_to']) ?></td>
      <td><span class="badge badge-<?= $d['is_active'] ? 'success' : 'secondary' ?>"><?= $d['is_active'] ? 'فعّال' : 'موقوف' ?></span></td>
      <td style="white-space:nowrap">
        <form method="post" action="/workflow-engine/delegations/<?= $d['id'] ?>/toggle" style="display:inline">
          <button class="btn btn-sm btn-secondary"><?= $d['is_active'] ? 'إيقاف' : 'تفعيل' ?></button>
        </form>
        <form method="post" action="/workflow-engine/delegations/<?= $d['id'] ?>/delete" style="display:inline" onsubmit="return confirm('حذف التفويض؟')">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$delegations): ?><tr><td colspan="6">لا توجد تفويضات</td></tr><?php endif; ?>
  </table>
</div>
