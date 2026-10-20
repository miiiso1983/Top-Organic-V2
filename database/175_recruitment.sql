-- 175: نظام التقديم الوظيفي (استمارة طلب توظيف عامة + إدارة المتقدمين لكل وظيفة)
-- مبني على أعمدة "فورمة التقديم الوظيفي.xlsx" الأصلية بالإضافة لحقول واردة
-- في نفس الملف ضمن قائمة النصوص المشتركة لكنها لم تُستخدم فعلياً في الخلايا
-- (رقم الهاتف، الوظيفة المستهدفة، هل متفرغ للعمل حالياً) — أُضيفت لأنها
-- بيانات أساسية لا يمكن الاستغناء عنها في أي استمارة توظيف فعلية.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS job_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_no VARCHAR(30) NOT NULL UNIQUE COMMENT 'رقم الاستمارة - يُملأ من السستم تلقائياً',
    job_id INT NOT NULL COMMENT 'الوظيفة المستهدفة (من جدول jobs)',
    branch_id INT NULL COMMENT 'الفرع',
    full_name VARCHAR(150) NOT NULL COMMENT 'الاسم الثلاثي واللقب',
    phone VARCHAR(30) NOT NULL COMMENT 'رقم الهاتف',
    age INT NULL COMMENT 'العمر',
    nationality VARCHAR(60) NULL COMMENT 'الجنسية',
    marital_status VARCHAR(30) NULL COMMENT 'الحالة الاجتماعية',
    residence VARCHAR(150) NULL COMMENT 'محل السكن',
    education_major VARCHAR(150) NULL COMMENT 'التخصص الدراسي',
    graduation_year VARCHAR(10) NULL COMMENT 'سنة التخرج',
    total_experience_years VARCHAR(20) NULL COMMENT 'إجمالي سنوات الخبرة',
    expected_salary DECIMAL(12,2) NULL COMMENT 'الراتب المتوقع',
    has_restaurant_experience TINYINT(1) DEFAULT 0 COMMENT 'خبرة سابقة في المطاعم أو الفنادق',
    has_relatives_in_company TINYINT(1) DEFAULT 0 COMMENT 'أقارب يعملون في الشركة',
    is_available_now TINYINT(1) DEFAULT 1 COMMENT 'متفرغ للعمل حالياً',
    experiences_json TEXT NULL COMMENT 'الخبرات العملية السابقة (مصفوفة: company,title,duration,last_salary,leave_reason)',
    cv_path VARCHAR(255) NULL COMMENT 'مسار ملف السيرة الذاتية (PDF أو صورة) - اختياري',
    status ENUM('new','reviewed','shortlisted','rejected','hired') NOT NULL DEFAULT 'new',
    hr_notes TEXT NULL COMMENT 'ملاحظات داخلية (لا تظهر للمتقدم)',
    submitted_ip VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    INDEX idx_job (job_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.recruitment.manage', 'إدارة طلبات التوظيف (استعراض المتقدمين)', 'hr');

-- منح الصلاحية افتراضياً لمدير النظام والمدير العام وأي دور يحمل اسم "الموارد البشرية"
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE p.code = 'hr.recruitment.manage'
  AND (r.name = 'مدير النظام' OR r.name = 'مدير عام' OR r.name LIKE '%الموارد البشرية%' OR r.name LIKE '%موارد بشرية%');
