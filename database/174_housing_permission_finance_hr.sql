-- منح صلاحية إدارة السكن (hr.housing.manage) للمدير المالي ومدير الموارد البشرية
-- (بالإضافة لمن يملكها أصلاً من database/165_housing_management.sql)
-- يستخدم LIKE على مسمى دور الموارد البشرية لتغطية الاختلاف المحتمل في التسمية
-- بين البيئات (مدير الموارد البشرية / مدير قسم الموارد البشرية / موارد بشرية).
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE p.code = 'hr.housing.manage'
  AND (r.name = 'مدير مالي' OR r.name LIKE '%الموارد البشرية%' OR r.name LIKE '%موارد بشرية%');
