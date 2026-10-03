<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>توزيع مصروف على مراكز الكلفة</h3>
  <form method="post" action="/finance/cost-centers/allocations">
    <div class="form-row"><label>المصروف</label>
      <select name="source_id" required>
        <?php foreach ($expenses as $e): ?>
        <option value="<?= $e['id'] ?>"><?= Helpers::e($e['expense_no']) ?> — <?= Helpers::e($e['category']) ?> — <?= Helpers::money($e['amount']) ?> (<?= Helpers::e($e['cost_center_name'] ?? '-') ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <h4>التوزيع (%)</h4>
    <?php foreach ($costCenters as $c): ?>
    <div class="form-row"><label><?= Helpers::e($c['name']) ?></label><input type="number" name="allocations[<?= $c['id'] ?>]" step="0.01" min="0" max="100" value="0" style="width:80px"> %</div>
    <?php endforeach; ?>
    <button class="btn btn-success">حفظ التوزيع</button>
  </form>
</div>

<div class="card">
  <h3>ملاحظة</h3>
  <p>مجموع النسب يجب ألا يتجاوز 100%. المبلغ يُحسب تلقائيًا من النسبة.</p>
</div>
