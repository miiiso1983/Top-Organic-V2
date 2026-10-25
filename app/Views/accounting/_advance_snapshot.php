<?php use App\Core\Helpers; ?>
<div class="card"><h3>كشف الاستحقاق والمديونية — <?= Helpers::e($advanceSnapshot['as_of_date'] ?? '') ?></h3>
<?php if (!empty($advanceSnapshot['error'])): ?><p><?= Helpers::e($advanceSnapshot['error']) ?></p><?php endif; ?>
<table><tbody>
<?php foreach (['salary'=>'الراتب الأساسي','salary_cap'=>'25% من الراتب','accrued_earnings'=>'الاستحقاق حتى تاريخ الكشف','deductions'=>'الاستقطاعات دون تكرار خصم أقساط السلف','outstanding_debt'=>'كامل المديونية غير المسددة','net_entitlement'=>'صافي الاستحقاق بعد المديونية والاستقطاعات','used'=>'طلبات قائمة محجوزة','remaining'=>'المتاح للسلفة الشهرية أو الاستثنائية'] as $key=>$label): ?>
<tr><td><?= $label ?></td><td><?= Helpers::money($advanceSnapshot[$key] ?? 0) ?></td></tr>
<?php endforeach; ?>
<tr><td>أيام الحضور / الإجازة المدفوعة</td><td><?= (int)($advanceSnapshot['working_days'] ?? 0) ?> / <?= (int)($advanceSnapshot['leave_days'] ?? 0) ?></td></tr>
</tbody></table><p>السلفة طويلة الأجل تعرض نفس الكشف للمراجعة وتخضع لمسار موافقاتها وجدول الأقساط.</p></div>

<?php if (!empty($advanceSnapshot['previous_advances'])): ?>
<details class="card"><summary>السلف السابقة والأقساط المتبقية</summary><table><tr><th>السلفة</th><th>المبلغ</th><th>المسدد</th><th>كامل المتبقي</th><th>عدد الأقساط المتبقية</th><th>قيمة القسط المحدد</th></tr>
<?php foreach ($advanceSnapshot['previous_advances'] as $debt): ?>
<tr><td>ADV-<?= (int)$debt['id'] ?></td><td><?= Helpers::money($debt['amount']) ?></td><td><?= Helpers::money($debt['paid_amount']) ?></td><td><?= Helpers::money($debt['remaining_amount']) ?></td><td><?= (int)$debt['remaining_installments'] ?></td><td><?= $debt['installment_amount'] ? Helpers::money($debt['installment_amount']) : 'حسب جدول الأقساط' ?></td></tr>
<?php endforeach; ?></table></details>
<?php endif; ?>
