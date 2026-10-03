-- =====================================================
-- 09: البيانات الأولية
-- =====================================================

INSERT INTO branches (name, address, phone) VALUES
('الفرع الرئيسي', 'بغداد', '07700000000');

INSERT INTO roles (name, level, description) VALUES
('مدير النظام', 100, 'صلاحيات كاملة'),
('مدير عام', 90, 'الإدارة العليا'),
('مدير مالي', 80, 'الإدارة المالية'),
('مدير مطعم', 70, 'إدارة الفرع'),
('محاسب', 60, 'الحسابات'),
('مسؤول مشتريات', 50, 'المشتريات'),
('أمين مخزن', 40, 'المخازن'),
('كاشير', 30, 'نقطة البيع'),
('شيف', 30, 'المطبخ'),
('موظف', 10, 'موظف عادي');

INSERT INTO permissions (code, name, module) VALUES
('sales.view','عرض المبيعات','sales'),
('sales.create','إنشاء طلبات','sales'),
('sales.return','مرتجعات وإلغاء','sales'),
('pos.use','استخدام نقطة البيع','sales'),
('reservations.manage','إدارة الحجوزات','sales'),
('kitchen.view','شاشة المطبخ','kitchen'),
('kitchen.manage','إدارة المطبخ','kitchen'),
('recipes.manage','إدارة الوصفات','recipes'),
('production.manage','إدارة الإنتاج','recipes'),
('inventory.view','عرض المخزون','inventory'),
('inventory.manage','إدارة المخزون','inventory'),
('inventory.transfer','التحويلات المخزنية','inventory'),
('inventory.stocktake','الجرد','inventory'),
('custody.manage','إدارة العهدة','inventory'),
('purchasing.view','عرض المشتريات','purchasing'),
('purchasing.manage','إدارة المشتريات','purchasing'),
('suppliers.manage','إدارة الموردين','purchasing'),
('finance.view','عرض المالية','finance'),
('finance.manage','إدارة المالية','finance'),
('hr.view','عرض الموارد البشرية','hr'),
('hr.manage','إدارة الموارد البشرية','hr'),
('payroll.manage','إدارة الرواتب','hr'),
('approvals.decide','اتخاذ قرارات الموافقة','approvals'),
('reports.view','عرض التقارير','reports'),
('admin.settings','إعدادات النظام','admin'),
('admin.users','إدارة المستخدمين','admin');

-- منح كل الصلاحيات لمدير النظام
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

INSERT INTO units (name, base_unit_id, factor) VALUES
('كغم', NULL, 1), ('غم', 1, 0.001), ('لتر', NULL, 1), ('مل', 3, 0.001), ('حبة', NULL, 1);

INSERT INTO item_categories (name, type) VALUES
('مواد أولية', 'raw'), ('مواد محضرة', 'prepared'),
('موجودات', 'asset'), ('أدوات', 'tool'), ('ملابس', 'clothing');

INSERT INTO warehouses (branch_id, name, location) VALUES
(1, 'المخزن الرئيسي', 'الطابق الأرضي'),
(1, 'مخزن المطبخ', 'المطبخ');

INSERT INTO kitchen_stations (branch_id, name) VALUES
(1, 'الشواية'), (1, 'المقالي'), (1, 'السلطات'), (1, 'الحلويات والمشروبات');

INSERT INTO dining_tables (branch_id, name, seats) VALUES
(1,'طاولة 1',4),(1,'طاولة 2',4),(1,'طاولة 3',6),(1,'طاولة 4',2),
(1,'طاولة 5',8),(1,'طاولة 6',4),(1,'طاولة 7',4),(1,'طاولة 8',6);

INSERT INTO menu_categories (name, sort_order) VALUES
('المشاوي', 1), ('الوجبات الرئيسية', 2), ('المقبلات', 3), ('المشروبات', 4);

INSERT INTO cash_registers (branch_id, name) VALUES (1, 'الصندوق الرئيسي');

INSERT INTO cost_centers (name) VALUES
('المطبخ'), ('الصالة'), ('التوصيل'), ('الإدارة');

INSERT INTO accounts (code, name, type) VALUES
('1000','الصندوق','asset'),
('1100','المخزون','asset'),
('2000','الموردون','liability'),
('4000','إيرادات المبيعات','revenue'),
('5000','تكلفة المبيعات','expense'),
('5100','الرواتب','expense'),
('5200','مصاريف تشغيلية','expense');

INSERT INTO legacy_approval_workflows (entity_type, name) VALUES
('purchase_request','طلبات الشراء'),
('leave_request','الإجازات'),
('expense','المصروفات'),
('salary_advance','السلف'),
('salary_adjustment','المكافآت والخصومات'),
('discount','الخصومات'),
('stock_transfer','المناقلات المخزنية'),
('quotation','عروض الأسعار');

-- مسار افتراضي: موافقة مدير المطعم ثم المدير العام
INSERT INTO legacy_approval_steps (workflow_id, step_order, role_id, min_amount)
SELECT id, 1, 4, 0 FROM legacy_approval_workflows;
INSERT INTO legacy_approval_steps (workflow_id, step_order, role_id, min_amount)
SELECT id, 2, 2, 0 FROM legacy_approval_workflows;
