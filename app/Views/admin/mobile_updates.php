<?php use App\Core\Helpers; $e=static fn($v)=>Helpers::e((string)$v); ?>
<div class="card"><h2>تحديثات تطبيق الموظفين</h2><p>لكل منصة إعداد مستقل. انتظار التوفر لا يمنع الموظفين؛ يتفعّل إلزام أندرويد فقط بعد تحقق الخادم من نشر النسخة بالكامل في Google Play. آيفون يبقى مستقلاً.</p>
<p>النسخ القديمة التي لم تعرّف جهازها بعد لا يمكن تصنيفها بأمان. القائمة تعرض آخر اتصال خلال ٣٠ يوماً، ولا تعني أن جميع أجهزة الموظف محدّثة.</p></div>
<?php foreach($platforms as $platform=>$p): ?>
<form class="card" method="post" action="/admin/mobile-updates">
<h3><?= $platform==='android'?'أندرويد — Google Play':'آيفون — App Store' ?></h3>
<input type="hidden" name="csrf" value="<?= $e($_SESSION['mobile_update_csrf']) ?>"><input type="hidden" name="platform" value="<?= $e($platform) ?>">
<div class="form-row"><label>الإصدار المطلوب</label><input name="version" value="<?= $e($p['version']) ?>" placeholder="1.3.8" required dir="ltr"></div>
<div class="form-row"><label>رقم البناء الأدنى</label><input name="min_build" type="number" min="1" value="<?= $e($p['min_build']) ?>" required></div>
<div class="form-row"><label>حالة الإلزام</label><select name="mode"><?php foreach(['off'=>'متوقف','pending'=>'انتظار التوفر ثم التفعيل تلقائياً (أندرويد)','required'=>'إلزامي بعد التحقق من المتجر'] as $value=>$label): if($platform==='ios' && $value==='pending')continue; ?><option value="<?= $value ?>" <?= $p['mode']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></div>
<p>آخر تحقق ناجح: <?= $e($p['verified_at'] ?: 'لم يتم بعد') ?> — البناء المتاح: <?= $e($p['verified_build'] ?: 'غير متحقق') ?></p>
<button class="btn btn-primary">حفظ إعداد <?= $platform==='android'?'أندرويد':'آيفون' ?></button>
</form><?php endforeach ?>
<div class="card" style="overflow:auto"><h3>متابعة النسخ حسب آخر اتصال</h3><table class="table"><thead><tr><th>الموظف / المستخدم</th><th>المنصة</th><th>الإصدار</th><th>البناء</th><th>الحالة</th><th>آخر اتصال</th></tr></thead><tbody>
<?php foreach($sessions as $s): $p=$platforms[$s['platform']]??[]; ?><tr><td><?= $e($s['full_name']) ?></td><td><?= $s['platform']==='android'?'أندرويد':'آيفون' ?></td><td><?= $e($s['version_name'] ?: 'نسخة قديمة غير معرّفة') ?></td><td><?= $e($s['build_number'] ?: 'غير معروف') ?></td><td><?= $s['ambiguous']?'جلسة مشتركة — لا يُفرض منع بالتخمين':(App\Core\MobileUpdateService::required($p,(int)$s['build_number'])?'يلزم التحديث':'مسموح حالياً') ?></td><td><?= $e($s['last_seen']) ?></td></tr><?php endforeach ?>
</tbody></table><?php if($page>1): ?><a href="?page=<?= $page-1 ?>">السابق</a><?php endif ?> <?php if($more): ?><a href="?page=<?= $page+1 ?>">التالي</a><?php endif ?></div>
