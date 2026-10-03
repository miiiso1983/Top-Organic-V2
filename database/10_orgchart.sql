-- =====================================================
-- 10: الهيكل التنظيمي (Org Chart) - شجرة مناصب قابلة للتعديل والإضافة
-- =====================================================

CREATE TABLE IF NOT EXISTS org_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NULL COMMENT 'المنصب الأعلى مباشرة (NULL = جذر الهيكل)',
    title VARCHAR(150) NOT NULL COMMENT 'اسم المنصب/القسم',
    employee_name VARCHAR(150) NULL COMMENT 'اسم شاغل المنصب (نص حر)',
    employee_id INT NULL COMMENT 'ربط اختياري بملف موظف فعلي',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES org_positions(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL,
    INDEX idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- بذر الهيكل الأولي: Top Organic Structure – Baghdad
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (NULL, 'Top Organic Structure – Baghdad', NULL, 1);
SET @root := LAST_INSERT_ID();

INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@root, 'Agency Supervisor', 'Weam Alhusain', 1);
SET @agency_supervisor := LAST_INSERT_ID();

INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@agency_supervisor, 'Branch Manager', 'Dlovan Bako', 1);
SET @branch_manager := LAST_INSERT_ID();

-- الفروع الثلاثة تحت مدير الفرع: الصالة، المطبخ، العمليات والمالية
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@branch_manager, 'FOH Manager', "Deyaa'a Abdula", 1);
SET @foh_manager := LAST_INSERT_ID();
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@branch_manager, 'BOH Manager', NULL, 2);
SET @boh_manager := LAST_INSERT_ID();
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@branch_manager, 'Operation And Finance', 'Hayder Salih', 3);
SET @op_finance := LAST_INSERT_ID();

-- تحت FOH Manager
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES
(@foh_manager, 'Reception', NULL, 1),
(@foh_manager, 'Server', NULL, 2),
(@foh_manager, 'Customer Survey', NULL, 3),
(@foh_manager, 'Call Center', NULL, 4),
(@foh_manager, 'Housekeeping', NULL, 5),
(@foh_manager, 'Tea', NULL, 6);

-- تحت BOH Manager
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@boh_manager, 'Silver', 'Abdulfatah Khalaf', 1);
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@boh_manager, 'Executive Chef', 'Sayed abu Alward', 2);
SET @exec_chef := LAST_INSERT_ID();
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@boh_manager, 'Steward', 'Shadi Jawher', 3);
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@boh_manager, 'Bar', 'Fareed Al Zaeem', 4);
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@boh_manager, 'Pastry Department', 'Ayad Qatarey', 5);
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@boh_manager, 'Hookah', "Mustafa Nafee'", 6);

-- سلسلة قيادة المطبخ
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@exec_chef, 'Executive Sous Chef', 'Ibraheem Nasr', 1);
SET @exec_sous_chef := LAST_INSERT_ID();
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES
(@exec_sous_chef, 'Sous Chefs', 'Mahmoud - Haithem - Bahlul - Mouhammed Ulaby - Talhat.sh', 1);
SET @sous_chefs := LAST_INSERT_ID();

-- محطات وأقسام التحضير تحت الشيفات المساعدين
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES
(@sous_chefs, 'Appetizer Preparation', 'Muneer Jewiir', 1),
(@sous_chefs, 'Oriental Preparation', 'Ali Basheer', 2),
(@sous_chefs, 'Butchery Preparation', 'Abdullah Fareed', 3),
(@sous_chefs, 'Vegetable Preparation', 'Haider Aqeel', 4),
(@sous_chefs, 'Bakery Preparation', 'Omar Al-Bouti', 5),
(@sous_chefs, 'Pizza Section', 'Rakan Al-Safa', 6),
(@sous_chefs, 'Oriental Section', 'Abdulrahman Fareed', 7),
(@sous_chefs, 'Grill Section', 'Shadi Al-Zeini', 8),
(@sous_chefs, 'Western Section', 'Ali Marwan', 9),
(@sous_chefs, 'Steak Section', 'Mohammed Jbawi', 10),
(@sous_chefs, 'Salad Section', 'Mohammed Shukri', 11);

-- تحت العمليات والمالية
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@op_finance, 'Accounting Supervisor', 'Mahdy Shahoud', 1);
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@op_finance, 'Cashier Supervisor', 'Abdullah', 2);
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@op_finance, 'Supply Chain', 'Wesam Aljubory', 3);
SET @supply_chain := LAST_INSERT_ID();
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@op_finance, 'Hr Coordinator', 'Osama Hameed', 4);
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@op_finance, 'Security Department', NULL, 5);
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES (@op_finance, 'Maintenance', 'Mustafa Khalid', 6);

-- تحت سلسلة التوريد
INSERT INTO org_positions (parent_id, title, employee_name, sort_order) VALUES
(@supply_chain, 'Procurements', NULL, 1),
(@supply_chain, 'Store keeper', 'Muhammed Talal', 2);
