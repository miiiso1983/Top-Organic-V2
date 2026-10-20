<?php
/**
 * حقول نموذج "مرحلة موافقة" — تُستخدم مرتين في steps.php: مرة لإضافة مرحلة جديدة (prefix=add)
 * ومرة داخل نافذة التعديل (prefix=edit)، لتفادي تكرار الحقول وإبقاء الاثنين متطابقين دوماً.
 * المتغيرات المتوقعة من الصفحة المستدعية: $prefix ('add'|'edit')، $template, $roles, $positions
 */
use App\Core\Helpers;
$prefix = $prefix ?? 'add';
?>
<div class="form-row"><label>عنوان المرحلة</label><input name="title" id="<?= $prefix ?>_title" placeholder="مثال: موافقة المدير المباشر" required></div>
<div class="form-row"><label>من يعتمد هذه المرحلة؟</label>
  <select name="approver_type" id="<?= $prefix ?>_approver_type" onchange="toggleApproverFields('<?= $prefix ?>')">
    <option value="position">صاحب مسمى وظيفي معيّن (حسب الهيكل التنظيمي)</option>
    <option value="role">أي مستخدم يحمل دوراً معيّناً</option>
    <option value="direct_manager">المدير المباشر لمقدّم الطلب (تلقائي حسب الهيكل)</option>
    <option value="department_manager">مدير قسم مقدّم الطلب (تلقائي حسب الهيكل)</option>
  </select>
</div>
<div class="form-row" id="<?= $prefix ?>_position_row"><label>المسمى الوظيفي</label>
  <select name="job_title_id" id="<?= $prefix ?>_job_title_id"><option value="">—</option>
    <?php foreach ($positions as $p): ?><option value="<?= $p['id'] ?>"><?= Helpers::e($p['title']) ?></option><?php endforeach; ?>
  </select>
</div>
<div class="form-row" id="<?= $prefix ?>_role_row"><label>الدور</label>
  <select name="role_id" id="<?= $prefix ?>_role_id"><option value="">—</option>
    <?php foreach ($roles as $role): ?><option value="<?= $role['id'] ?>"><?= Helpers::e($role['name']) ?></option><?php endforeach; ?>
  </select>
</div>
<div class="form-row" id="<?= $prefix ?>_manager_note" style="display:none;min-width:100%">
  <small style="color:#6b7280"><i class="fa-solid fa-circle-info"></i> سيُحدَّد المعتمد تلقائياً من الهيكل التنظيمي لصاحب الطلب — لا حاجة لاختياره يدوياً.</small>
</div>

<div class="form-row"><label>الحد الأدنى للمبلغ <small style="color:#9ca3af">(0 = مطلوبة دائماً)</small></label><input type="number" name="min_amount" id="<?= $prefix ?>_min_amount" value="0"></div>
<div class="form-row"><label>نوع المرحلة</label>
  <select name="step_type" id="<?= $prefix ?>_step_type" onchange="toggleStepTypeFields('<?= $prefix ?>')">
    <option value="sequential">موافقة شخص واحد</option>
    <option value="parallel">موافقة جماعية (عدة أشخاص)</option>
  </select>
</div>
<div class="form-row" id="<?= $prefix ?>_required_count_row" style="display:none">
  <label>عدد الموافقات المطلوبة <small style="color:#9ca3af">(0 = الجميع)</small></label>
  <input type="number" name="required_count" id="<?= $prefix ?>_required_count" value="1">
</div>

<div class="form-row" style="min-width:100%">
  <label>شروط تفعيل هذه المرحلة <small style="color:#9ca3af">(اختياري — تُطبَّق المرحلة فقط عند تحقق الشروط على بيانات الطلب)</small></label>
  <div id="<?= $prefix ?>_conditions_box"></div>
  <button type="button" class="btn btn-sm btn-secondary" onclick="addConditionRow('<?= $prefix ?>')">+ إضافة شرط</button>
  <input type="hidden" name="condition" id="<?= $prefix ?>_condition_hidden" value="{}">
</div>

<div class="form-row" style="min-width:100%">
  <label style="display:inline; margin-left:12px"><input type="checkbox" name="can_return_for_edit" id="<?= $prefix ?>_can_return" value="1" checked> سماح بإعادة الطلب للتعديل</label>
  <label style="display:inline; margin-left:12px"><input type="checkbox" name="is_final" id="<?= $prefix ?>_is_final" value="1"> هذه آخر مرحلة قبل الاعتماد النهائي</label>
</div>
<div class="form-row" style="min-width:100%">
  <label>إشعار المعتمد عبر</label>
  <label style="display:inline; margin-left:12px"><input type="checkbox" name="notify_push" id="<?= $prefix ?>_notify_push" value="1" checked> إشعار فوري (Push)</label>
  <label style="display:inline; margin-left:12px"><input type="checkbox" name="notify_email" id="<?= $prefix ?>_notify_email" value="1"> بريد إلكتروني</label>
  <label style="display:inline; margin-left:12px"><input type="checkbox" name="notify_whatsapp" id="<?= $prefix ?>_notify_whatsapp" value="1"> واتساب</label>
</div>
