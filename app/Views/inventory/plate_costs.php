<?php use App\Core\Helpers; ?>
<div class="card">
  <h2><i class="fa-solid fa-book"></i> تكاليف الأطباق — خارطة طريق التنفيذ</h2>
  <p>هذه الصفحة تعرض مراحل بناء محرك تكلفة الأطباق داخل قسم المخزون، خطوة بخطوة.</p>
  <form method="get" action="/inventory/plate-costs" class="form-inline" style="margin-top:8px">
    <div class="form-row"><label>التاريخ</label>
      <input type="date" name="date" value="<?= Helpers::e($date ?? date('Y-m-d')) ?>">
    </div>
    <button class="btn">تحديث</button>
  </form>
</div>

<div class="steps-grid">
  <?php foreach (($phases ?? []) as $i => $phase): ?>
  <div class="step-card">
    <div class="step-badge">مرحلة <?= $i+1 ?></div>
    <h3><?= Helpers::e($phase['title']) ?></h3>
    <ul>
      <?php foreach (($phase['points'] ?? []) as $p): ?>
      <li><?= Helpers::e($p) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endforeach; ?>
</div>

<style>
.steps-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px}
.step-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px}
.step-badge{display:inline-block;background:#e8f5e9;color:#1b5e20;border-radius:999px;padding:4px 10px;font-weight:600;margin-bottom:6px}
.step-card h3{margin:6px 0 10px 0}
.step-card ul{margin:0;padding-inline-start:18px}
.step-card li{margin:6px 0}
</style>

<?php if (!empty($live)): ?>
<div class="card" style="margin-top:16px">
  <h3>حسابات مباشرة (حسب التاريخ)</h3>
  <div style="overflow:auto">
    <table>
      <tr>
        <th>الصنف</th><th>الباركود</th><th>الوحدة الناتجة</th>
        <th>تكلفة/وحدة (<?= Helpers::e($date ?? date('Y-m-d')) ?>)</th>
        <th>سعر البيع</th><th>Food Cost%</th><th>مجمل الربح</th><th>GP%</th>
      </tr>
      <?php foreach ($live as $r): ?>
      <tr>
        <td><?= Helpers::e($r['item_name']) ?></td>
        <td><?= Helpers::e($r['barcode'] ?? '-') ?></td>
        <td><?= (float)$r['yield_qty'] . ' ' . Helpers::e($r['yield_unit_name']) ?></td>
        <td><?= number_format((float)$r['unit_cost'], 2) ?></td>
        <td><?= number_format((float)$r['sale_price'], 2) ?></td>
        <td><?= isset($r['food_cost_pct']) ? number_format((float)$r['food_cost_pct'], 1) . '%' : '-' ?></td>
        <td><?= number_format((float)$r['gp'], 2) ?></td>
        <td><?= isset($r['gp_pct']) ? number_format((float)$r['gp_pct'], 1) . '%' : '-' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php else: ?>
  <div class="card" style="margin-top:16px">
    <h3>حسابات مباشرة</h3>
    <p>لا توجد قوالب تكلفة مُسجلة بعد لعرض التكلفة التاريخية. يمكنك إنشاء القوالب من: المخزون → قوالب تكلفة الأصناف.</p>
  </div>
<?php endif; ?>

<?php if (!empty($menuLive)): ?>
<div class="card" style="margin-top:16px">
  <h3>تكلفة أطباق المنيو (حسب التاريخ)</h3>
  <div style="overflow:auto">
    <table>
      <tr>
        <th>طبق المنيو</th><th>تكلفة/حصة (<?= Helpers::e($date ?? date('Y-m-d')) ?>)</th>
        <th>سعر البيع</th><th>Food Cost%</th><th>مجمل الربح</th><th>GP%</th>
      </tr>
      <?php foreach ($menuLive as $r): ?>
      <tr>
        <td><?= Helpers::e($r['name']) ?></td>
        <td><?= number_format((float)$r['unit_cost'], 2) ?></td>
        <td><?= number_format((float)$r['sale_price'], 2) ?></td>
        <td><?= isset($r['food_cost_pct']) ? number_format((float)$r['food_cost_pct'], 1) . '%' : '-' ?></td>
        <td><?= number_format((float)$r['gp'], 2) ?></td>
        <td><?= isset($r['gp_pct']) ? number_format((float)$r['gp_pct'], 1) . '%' : '-' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php endif; ?>
