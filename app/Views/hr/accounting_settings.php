<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>إعدادات المحاسبة للمورد البشري</h3>
  <form method="post" action="/hr/accounting-settings">
    <?php $keys = [
      'hr_account_salary' => 'حساب مصروف الرواتب والأجور',
      'hr_account_bonuses' => 'حساب المكافآت والحوافز',
      'hr_account_deductions' => 'حساب الخصومات والجزاءات',
      'hr_account_travel_tickets' => 'حساب تذاكر السفر',
      'hr_account_advances' => 'حساب سلف الموظفين',
      'hr_account_salary_payable' => 'حساب الرواتب المستحقة',
      'hr_account_tax_payable' => 'حساب الضريبة المستحقة',
      'hr_account_insurance_payable' => 'حساب التأمين المستحق',
      'hr_account_social_security_payable' => 'حساب الضمان الاجتماعي المستحق',
      'hr_account_loadings_payable' => 'حساب تحميلات الموظفين المستحقة',
      'hr_account_tips_payable' => 'حساب التيبس المستحق',
      'hr_account_security_deposit_payable' => 'حساب أمانات الموظفين المستحقة',
      'hr_account_cash' => 'حساب الصندوق/البنك للصرف',
      'hr_account_custody_receivable' => 'حساب ذمم عهدة الموظفين',
      'hr_account_custody_penalty' => 'حساب غرامات العهدة',
      'hr_account_inventory' => 'حساب المخزن',
      'custody_alert_days_before' => 'أيام التنبيه قبل الاستحقاق',
    ]; ?>
    <?php foreach ($keys as $k => $label): ?>
    <div class="form-row"><label><?= $label ?></label>
      <?php if ($k === 'custody_alert_days_before'): ?>
        <input type="number" name="<?= $k ?>" value="<?= Helpers::e($settings[$k] ?? '7') ?>" min="1" max="90">
      <?php else: ?>
      <select name="<?= $k ?>">
        <option value="">—</option>
        <?php foreach ($accounts as $a): ?>
        <option value="<?= Helpers::e($a['code']) ?>" <?= ($settings[$k] ?? '') === $a['code'] ? 'selected' : '' ?>><?= Helpers::e($a['code']) ?> - <?= Helpers::e($a['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <button class="btn btn-success">حفظ الإعدادات</button>
  </form>
</div>
