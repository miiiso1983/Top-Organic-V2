-- 150: ربط خطة الأعداد بالوظيفة (Job) بدلاً من الاعتماد على القسم فقط
-- عند اختيار الوظيفة يُشتق القسم تلقائياً من ربط الوظيفة بالقسم.
SET @db = DATABASE();

-- 1) إضافة عمود job_id
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='hr_headcount_plans' AND column_name='job_id';
SET @sql = IF(@exists=0, 'ALTER TABLE hr_headcount_plans ADD COLUMN job_id INT NULL AFTER department_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) المفتاح الخارجي للوظيفة (شرطي)
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.table_constraints
 WHERE table_schema=@db AND table_name='hr_headcount_plans' AND constraint_name='fk_headcount_job';
SET @sql = IF(@exists=0, 'ALTER TABLE hr_headcount_plans ADD CONSTRAINT fk_headcount_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) إضافة فهرس عادي على department_id أولاً (يستخدمه المفتاح الخارجي الحالي)
--    حتى يمكن حذف الفهرس الفريد القديم دون كسر قيد المفتاح الخارجي
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.statistics
 WHERE table_schema=@db AND table_name='hr_headcount_plans' AND index_name='idx_headcount_department';
SET @sql = IF(@exists=0, 'ALTER TABLE hr_headcount_plans ADD INDEX idx_headcount_department (department_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) إزالة الفهرس الفريد القديم (قسم + فترة) إن وُجد
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.statistics
 WHERE table_schema=@db AND table_name='hr_headcount_plans' AND index_name='uniq_dept_period';
SET @sql = IF(@exists>0, 'ALTER TABLE hr_headcount_plans DROP INDEX uniq_dept_period', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) فهرس فريد جديد (وظيفة + فترة)
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.statistics
 WHERE table_schema=@db AND table_name='hr_headcount_plans' AND index_name='uniq_job_period';
SET @sql = IF(@exists=0, 'ALTER TABLE hr_headcount_plans ADD UNIQUE KEY uniq_job_period (job_id, period)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
