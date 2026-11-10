<?php
use App\Core\{Helpers, SystemActivityReport, SystemActivityService};
$e = static fn($v) => Helpers::e((string)($v ?? ''));
$url = static fn(array $change) => '/admin/activity?' . http_build_query(array_merge($filters, $change));
$actorLabel = '';
foreach ($actors as $actor) if ((int)$actor['id'] === $filters['user_id']) $actorLabel = $actor['full_name'].' (#'.$actor['id'].')';
?>
<style>
.activity-page{--a-bg:var(--surface,#fff);--a-text:var(--text,#182435);color:var(--a-text)}
.activity-head,.activity-tools,.activity-pages{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px}
.activity-page h2{margin:0 0 6px}.activity-note{font-size:13px;line-height:1.8;margin:6px 0 16px;color:inherit;opacity:.85}
.activity-panel{background:var(--a-bg);color:var(--a-text);border:1px solid #9aa9b755;border-radius:14px;padding:18px;margin-bottom:16px}
.activity-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}.activity-filters label{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:600}.activity-filters input,.activity-filters select{width:100%;min-width:0;padding:10px;border:1px solid #8a9aab;border-radius:8px;background:var(--a-bg);color:inherit;box-sizing:border-box}
.activity-wide{grid-column:span 2}.activity-tools{margin:14px 0 0}.activity-tools a,.activity-pages a{padding:7px 12px;border:1px solid #899bab66;border-radius:8px;text-decoration:none;color:inherit}.activity-table-wrap{overflow:auto}.activity-table{width:100%;border-collapse:collapse;font-size:13px;min-width:850px}.activity-table th,.activity-table td{padding:12px;text-align:right;vertical-align:top;border-bottom:1px solid #899bab44}.activity-table th{white-space:nowrap}.activity-table small{display:block;opacity:.8;margin-top:5px}.activity-table summary{cursor:pointer;color:inherit}.activity-details{min-width:260px;max-width:550px;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.8}.activity-badge{display:inline-block;padding:4px 8px;border-radius:8px;background:#64748b20}.activity-badge.error,.activity-badge.rejected{background:#d64b3420;color:#b42318}.activity-badge.accepted{background:#15803d20;color:#15803d}.activity-pages{margin:18px 0 0}.activity-empty{text-align:center;padding:40px}.activity-page code{direction:ltr;unicode-bidi:embed;overflow-wrap:anywhere}.activity-page button{cursor:pointer}.activity-active{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}.activity-active a{background:#15803d18;border-radius:16px;padding:5px 10px;color:inherit;text-decoration:none}
.dark .activity-page .activity-badge.rejected,.dark .activity-page .activity-badge.error{color:#ffb4ab}.dark .activity-page .activity-badge.accepted{color:#86efac}
html[data-theme="dark"] .activity-page,body.dark-mode .activity-page{--a-bg:#182435;--a-text:#e9eff7}
@media(max-width:600px){.activity-wide{grid-column:span 1}.activity-panel{padding:12px}}
</style>
<section class="activity-page">
 <div class="activity-head"><div><h2><i class="fa-solid fa-clock-rotate-left"></i> سجل أحداث النظام</h2><span>تتبّع العمليات والموافقات والتغييرات من مكان واحد</span></div><a class="btn btn-secondary" href="/module/management">العودة للإدارة</a></div>
 <div class="activity-panel">
 <form method="get" action="/admin/activity" data-activity-filters>
  <div class="activity-filters">
   <label class="activity-wide">بحث شامل<input name="q" value="<?= $e($filters['q']) ?>" placeholder="اسم المنفّذ، الموظف المتأثر، العملية أو التفاصيل…" maxlength="190"></label>
   <label>من تاريخ<input type="date" name="from" value="<?= $e($filters['from']) ?>"></label>
   <label>إلى تاريخ<input type="date" name="to" value="<?= $e($filters['to']) ?>"></label>
   <label>منفّذ الإجراء<input list="activity-actors" data-activity-actor value="<?= $e($actorLabel) ?>" placeholder="ابحث واختر اسم المستخدم" autocomplete="off"><input type="hidden" name="user_id" value="<?= $filters['user_id'] ?>"><datalist id="activity-actors"><?php foreach($actors as $actor): ?><option data-id="<?= (int)$actor['id'] ?>" value="<?= $e($actor['full_name'].' (#'.$actor['id'].')') ?>"></option><?php endforeach ?></datalist></label>
   <label>نوع السجل<select name="source"><option value="">كل الأنواع</option><?php foreach(SystemActivityReport::SOURCES as $v=>$label): ?><option value="<?= $e($v) ?>" <?= $filters['source']===$v?'selected':'' ?>><?= $e($label) ?></option><?php endforeach ?></select></label>
   <label>نتيجة الحدث<select name="result"><option value="">كل النتائج</option><?php foreach(SystemActivityReport::RESULTS as $v=>$label): ?><option value="<?= $e($v) ?>" <?= $filters['result']===$v?'selected':'' ?>><?= $e($label) ?></option><?php endforeach ?></select></label>
   <label>الفرع<select name="branch_id"><option value="0">كل الفروع</option><?php foreach($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $filters['branch_id']==$b['id']?'selected':'' ?>><?= $e($b['name']) ?></option><?php endforeach ?></select></label>
   <label>العملية (مطابقة تامة)<input name="action" value="<?= $e($filters['action']) ?>" placeholder="اختر العملية من أي صف" maxlength="190"></label>
   <label>الكيان / المسار<input name="entity" value="<?= $e($filters['entity']) ?>" placeholder="مثال: employees" maxlength="190"></label>
   <label>رقم السجل المتأثر<input type="number" name="entity_id" min="1" value="<?= $filters['entity_id'] ?: '' ?>" placeholder="رقم الموظف أو الطلب"></label>
   <label>عدد الصفوف<select name="per_page"><?php foreach([25,50,100] as $n): ?><option <?= $filters['per_page']===$n?'selected':'' ?>><?= $n ?></option><?php endforeach ?></select></label>
  </div>
  <div class="activity-tools"><div><button class="btn btn-primary" type="submit">بحث وتصفية</button> <a href="/admin/activity">مسح الفلاتر</a></div><div class="activity-tools" style="margin:0"><span>فترات سريعة:</span><a href="<?= $e($url(['from'=>date('Y-m-d'),'to'=>date('Y-m-d'),'page'=>1])) ?>">اليوم</a><a href="<?= $e($url(['from'=>date('Y-m-d',strtotime('-6 days')),'to'=>date('Y-m-d'),'page'=>1])) ?>">آخر ٧ أيام</a><a href="<?= $e($url(['from'=>date('Y-m-01'),'to'=>date('Y-m-d'),'page'=>1])) ?>">هذا الشهر</a><a href="<?= $e($url(['from'=>'','to'=>'','page'=>1])) ?>">كل الفترات</a><a href="<?= $e($url(['result'=>'rejected','page'=>1])) ?>">الطلبات المرفوضة</a></div></div>
 </form>
 <p class="activity-note">التوقيت: بغداد. طلبات الويب والموبايل تُسجَّل من تاريخ تفعيل هذه الشاشة؛ السجلات السابقة تعرض التفاصيل المحفوظة فقط. «استجابة طبيعية» تعني استجابة اتصال سليمة ولا تعني اعتماد طلب إداري. الفرع في السجلات القديمة هو الفرع الحالي لمنفّذ الإجراء. لا تُحفظ كلمات المرور أو صور الوجه أو محتويات الرسائل ضمن سجل الاتصال.</p>
 </div>
 <div class="activity-panel">
 <div class="activity-head"><strong><?= number_format($report['total']) ?> حدث مطابق</strong><span>الصفحة <?= $report['page'] ?> من <?= $report['pages'] ?></span></div>
 <div class="activity-table-wrap"><table class="activity-table"><thead><tr><th>الوقت</th><th>منفّذ الإجراء</th><th>نوع السجل</th><th>العملية / السجل المتأثر</th><th>النتيجة</th><th>التفاصيل</th></tr></thead><tbody>
 <?php foreach($report['rows'] as $row): ?>
 <tr><td><time dir="ltr"><?= $e($row['created_at']) ?></time><small>#<?= (int)$row['id'] ?></small></td>
 <td><?php if($row['user_id']): ?><a href="<?= $e($url(['user_id'=>(int)$row['user_id'],'page'=>1])) ?>"><?= $e($row['user_name']) ?></a><?php else: ?><?= $e($row['user_name']) ?><?php endif ?><small><?= $e($row['branch_name'] ?: 'فرع غير محدد') ?></small></td>
 <td><?= $e(SystemActivityReport::SOURCES[$row['source']]) ?></td>
 <td><a href="<?= $e($url(['action'=>$row['action'],'page'=>1])) ?>"><?= $e(SystemActivityReport::actionLabel($row['action'])) ?></a><small><code><?= $e($row['entity']) ?></code> <?= $row['entity_id'] ? '#'.(int)$row['entity_id'] : '' ?></small><?php if($row['target_name']): ?><small><?= $e($row['target_name']) ?></small><?php endif ?></td>
 <td><span class="activity-badge <?= $e($row['result']) ?>"><?= $e(SystemActivityReport::RESULTS[$row['result']] ?? $row['result']) ?></span><?php if($row['http_status']): ?><small>HTTP <?= (int)$row['http_status'] ?></small><?php endif ?></td>
 <td><details><summary>عرض التفاصيل</summary><div class="activity-details">
<?php if($row['source']==='employees'): ?><strong>الحقل:</strong> <?= $e($row['field_name']) ?>
<strong>قبل:</strong> <?= $e(SystemActivityService::safeDetails($row['old_value'],$row['field_name'])) ?>
<strong>بعد:</strong> <?= $e(SystemActivityService::safeDetails($row['new_value'],$row['field_name'])) ?>
<?php endif ?><?php if($row['details']): ?><?= $e(SystemActivityService::safeDetails($row['details'])) ?>
<?php endif ?><?php if($row['method']): ?><strong>نوع الطلب:</strong> <?= $e($row['method']) ?>
<strong>زمن الاستجابة:</strong> <?= (int)$row['duration_ms'] ?> مللي ثانية
<?php endif ?><?php if($row['ip']): ?><strong>عنوان الاتصال:</strong> <span dir="ltr"><?= $e($row['ip']) ?></span>
<?php endif ?><?php if($row['device']): ?><strong>الجهاز:</strong> <?= $e($row['device']) ?>
<?php endif ?><?php if(!$row['details'] && !$row['method'] && $row['source']!=='employees'): ?>لا توجد تفاصيل إضافية محفوظة لهذا الحدث.
<?php endif ?></div></details></td></tr>
 <?php endforeach ?>
 <?php if(!$report['rows']): ?><tr><td colspan="6" class="activity-empty">لا توجد أحداث مطابقة. جرّب توسيع الفترة أو إزالة أحد الفلاتر.</td></tr><?php endif ?>
 </tbody></table></div>
 <nav class="activity-pages" aria-label="صفحات سجل الأحداث"><div><?php if($report['page']>1): ?><a href="<?= $e($url(['page'=>1])) ?>">الأولى</a> <a href="<?= $e($url(['page'=>$report['page']-1])) ?>">السابق</a><?php endif ?></div><span><?= number_format($report['total']) ?> حدث — دون حد إجمالي للنتائج</span><div><?php if($report['page']<$report['pages']): ?><a href="<?= $e($url(['page'=>$report['page']+1])) ?>">التالي</a> <a href="<?= $e($url(['page'=>$report['pages']])) ?>">الأخيرة</a><?php endif ?></div></nav>
 </div>
</section>
<script>
(function(){
 const script=document.currentScript, root=script.previousElementSibling, form=root.querySelector('[data-activity-filters]'), input=form.querySelector('[data-activity-actor]'), hidden=form.querySelector('[name="user_id"]');
 input.addEventListener('input',function(){const option=Array.from(form.querySelectorAll('datalist option')).find(o=>o.value===input.value);hidden.value=option?option.dataset.id:'0';input.setCustomValidity(input.value&&!option?'اختر مستخدماً من القائمة، أو امسح الاسم للبحث في الجميع.':'');});
})();
</script>
