<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-chart-pie"></i> استهلاك المواد</h2>
</div>

<div class="card">
  <form method="get" class="form-inline" style="gap:12px; align-items:end">
    <input type="hidden" name="tab" value="<?= Helpers::e($tab) ?>">
    <div class="form-row">
      <label>من</label>
      <input type="date" name="from" value="<?= Helpers::e($filters['from']) ?>">
    </div>
    <div class="form-row">
      <label>إلى</label>
      <input type="date" name="to" value="<?= Helpers::e($filters['to']) ?>">
    </div>
    <div class="form-row">
      <label>المادة</label>
      <select name="item_id">
        <option value="0">الكل</option>
        <?php foreach ($items as $it): ?>
        <option value="<?= $it['id'] ?>" <?= $filters['item_id'] == $it['id'] ? 'selected' : '' ?>><?= Helpers::e($it['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($tab !== 'production' && $tab !== 'waste'): ?>
    <div class="form-row">
      <label>الفرع</label>
      <select name="branch_id">
        <option value="0">الكل</option>
        <?php foreach ($branches as $b): ?>
        <option value="<?= $b['id'] ?>" <?= $filters['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <?php if ($tab === 'department'): ?>
    <div class="form-row">
      <label>القسم</label>
      <select name="section_id">
        <option value="0">الكل</option>
        <?php foreach ($sections as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $filters['section_id'] == $s['id'] ? 'selected' : '' ?>><?= Helpers::e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <button class="btn btn-primary">تحديث</button>
  </form>
</div>

<div class="tabs" style="margin-top:20px">
  <?php $tabs = ['sales'=>'حسب البيع','recipe'=>'حسب الوصفة','production'=>'حسب التصنيع','department'=>'حسب القسم','variance'=>'الانحرافات','waste'=>'الهدر والفاقد']; ?>
  <?php foreach ($tabs as $k=>$label): ?>
    <a href="/accounting/material-consumption?tab=<?= $k ?>&from=<?= Helpers::e($filters['from']) ?>&to=<?= Helpers::e($filters['to']) ?>&item_id=<?= $filters['item_id'] ?>" class="btn btn-sm <?= $tab === $k ? 'btn-primary' : 'btn-secondary' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<div class="card" style="margin-top:20px">
  <?php if ($tab === 'sales'): ?>
    <h3>استهلاك المواد حسب البيع</h3>
    <table class="table">
      <thead><tr><th>المادة</th><th>الوحدة</th><th>النظري</th><th>الفعلي</th><th>الانحراف</th><th>تكلفة نظرية</th><th>تكلفة فعلية</th></tr></thead>
      <tbody>
      <?php foreach ($data as $r): ?>
      <tr>
        <td><?= Helpers::e($r['item_name']) ?></td>
        <td><?= Helpers::e($r['unit_name']) ?></td>
        <td><?= number_format((float)$r['theoretical_qty'], 3) ?></td>
        <td><?= number_format((float)$r['actual_qty'], 3) ?></td>
        <td class="<?= (float)$r['variance_qty'] > 0 ? 'text-danger' : ((float)$r['variance_qty'] < 0 ? 'text-success' : '') ?>"><?= number_format((float)$r['variance_qty'], 3) ?></td>
        <td><?= Helpers::money((float)$r['theoretical_cost']) ?></td>
        <td><?= Helpers::money((float)$r['actual_cost']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$data): ?><tr><td colspan="7">لا توجد بيانات</td></tr><?php endif; ?>
      </tbody>
    </table>
  <?php elseif ($tab === 'recipe'): ?>
    <h3>استهلاك المواد حسب الوصفة</h3>
    <table class="table">
      <thead><tr><th>الوجبة</th><th>المادة</th><th>الوحدة</th><th>كمية مباعة</th><th>استهلاك نظري</th><th>التكلفة</th></tr></thead>
      <tbody>
      <?php foreach ($data as $r): ?>
      <tr>
        <td><?= Helpers::e($r['menu_item_name']) ?></td>
        <td><?= Helpers::e($r['item_name']) ?></td>
        <td><?= Helpers::e($r['unit_name']) ?></td>
        <td><?= number_format((float)$r['sold_qty'], 2) ?></td>
        <td><?= number_format((float)$r['consumed_qty'], 3) ?></td>
        <td><?= Helpers::money((float)$r['consumed_cost']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$data): ?><tr><td colspan="6">لا توجد بيانات</td></tr><?php endif; ?>
      </tbody>
    </table>
  <?php elseif ($tab === 'production'): ?>
    <h3>استهلاك المواد حسب التصنيع</h3>
    <table class="table">
      <thead><tr><th>المادة</th><th>الوحدة</th><th>انتاج مخطط</th><th>انتاج فعلي</th><th>هدر انتاج</th><th>استهلاك</th><th>التكلفة</th></tr></thead>
      <tbody>
      <?php foreach ($data as $r): ?>
      <tr>
        <td><?= Helpers::e($r['item_name']) ?></td>
        <td><?= Helpers::e($r['unit_name']) ?></td>
        <td><?= number_format((float)$r['planned_output'], 3) ?></td>
        <td><?= number_format((float)$r['actual_output'], 3) ?></td>
        <td><?= number_format((float)$r['waste_output'], 3) ?></td>
        <td><?= number_format((float)$r['consumed_qty'], 3) ?></td>
        <td><?= Helpers::money((float)$r['consumed_cost']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$data): ?><tr><td colspan="7">لا توجد بيانات</td></tr><?php endif; ?>
      </tbody>
    </table>
  <?php elseif ($tab === 'department'): ?>
    <h3>استهلاك المواد حسب القسم</h3>
    <table class="table">
      <thead><tr><th>القسم</th><th>المادة</th><th>الوحدة</th><th>استهلاك نظري</th><th>التكلفة</th><th>أصناف مباعة</th></tr></thead>
      <tbody>
      <?php foreach ($data as $r): ?>
      <tr>
        <td><?= Helpers::e($r['section_name'] ?? 'بدون قسم') ?></td>
        <td><?= Helpers::e($r['item_name']) ?></td>
        <td><?= Helpers::e($r['unit_name']) ?></td>
        <td><?= number_format((float)$r['theoretical_qty'], 3) ?></td>
        <td><?= Helpers::money((float)$r['theoretical_cost']) ?></td>
        <td><?= number_format((float)$r['sold_items'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$data): ?><tr><td colspan="6">لا توجد بيانات</td></tr><?php endif; ?>
      </tbody>
    </table>
  <?php elseif ($tab === 'variance'): ?>
    <h3>تحليل الانحرافات</h3>
    <table class="table">
      <thead><tr><th>المادة</th><th>الوحدة</th><th>نظري</th><th>فعلي</th><th>انحراف</th><th>هدر</th><th>فاقد</th><th>تكلفة نظرية</th><th>تكلفة فعلية</th></tr></thead>
      <tbody>
      <?php foreach ($data as $r): ?>
      <tr>
        <td><?= Helpers::e($r['item_name']) ?></td>
        <td><?= Helpers::e($r['unit_name']) ?></td>
        <td><?= number_format((float)$r['theoretical_qty'], 3) ?></td>
        <td><?= number_format((float)$r['actual_qty'], 3) ?></td>
        <td class="<?= (float)$r['variance_qty'] > 0 ? 'text-danger' : '' ?>"><?= number_format((float)$r['variance_qty'], 3) ?></td>
        <td><?= number_format((float)$r['waste_qty'], 3) ?></td>
        <td class="<?= (float)$r['loss_qty'] > 0 ? 'text-danger' : '' ?>"><?= number_format((float)$r['loss_qty'], 3) ?></td>
        <td><?= Helpers::money((float)$r['theoretical_cost']) ?></td>
        <td><?= Helpers::money((float)$r['actual_cost']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$data): ?><tr><td colspan="9">لا توجد بيانات</td></tr><?php endif; ?>
      </tbody>
    </table>
  <?php elseif ($tab === 'waste'): ?>
    <h3>الهدر والفاقد</h3>
    <table class="table">
      <thead><tr><th>التاريخ</th><th>المادة</th><th>الوحدة</th><th>الكمية</th><th>السبب</th></tr></thead>
      <tbody>
      <?php foreach ($data as $r): ?>
      <tr>
        <td><?= Helpers::e($r['created_at']) ?></td>
        <td><?= Helpers::e($r['item_name']) ?></td>
        <td><?= Helpers::e($r['unit_name']) ?></td>
        <td><?= number_format((float)$r['qty'], 3) ?></td>
        <td><?= Helpers::e($r['reason']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$data): ?><tr><td colspan="5">لا توجد بيانات</td></tr><?php endif; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
