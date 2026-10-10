-- =====================================================
-- 170: ربط ظهور أنواع الطلبات (قوالب سير العمل) بصلاحية كل دور تحديداً
-- =====================================================
-- الكود كان يسمح بظهور/إنشاء أي قالب لأي دور "إداري" (مستوى >= 60، أو اسم
-- الدور يحتوي على "مدير")، أو لأي دور يملك صلاحية عامة (workflow.create أو
-- mobile.approvals) — بصرف النظر عن القيم الفعلية في template_allowed_roles
-- الخاصة بكل قالب على حدة. هذا كان يُبطل فعلياً تأثير شاشة "تعديل الدور"
-- (قوالب سير العمل) في لوحة التحكم.
--
-- بعد هذا التحديث، template_allowed_roles.can_create هو المصدر الوحيد
-- (باستثناء "مدير النظام" الذي يملك كل الصلاحيات دائماً، وطلبي الإجازة/السلفة
-- المتاحين دائماً للجميع كحق ذاتي). هذا الاستعلام "يُثبّت" الصلاحيات الفعلية
-- الحالية (التي كانت تُمنح ضمنياً عبر الكود) كصفوف صريحة، حتى لا يفقد أي دور
-- وصوله الحالي فور هذا التحديث. من الآن فصاعداً، استخدم شاشة "تعديل الدور"
-- لتقييد ظهور أي نوع طلب عن دور معين.
SET NAMES utf8mb4;

INSERT IGNORE INTO template_allowed_roles (template_id, role_id, can_create, can_view_all)
SELECT t.id, r.id, 1, 0
FROM approval_templates t
CROSS JOIN roles r
WHERE t.is_active = 1
  AND (
    r.name = 'مدير النظام'
    OR COALESCE(r.level, 0) >= 60
    OR r.name LIKE '%مدير%'
    OR EXISTS (
      SELECT 1 FROM role_permissions rp
      JOIN permissions p ON p.id = rp.permission_id
      WHERE rp.role_id = r.id AND p.code IN ('workflow.create', 'mobile.approvals')
    )
  );
