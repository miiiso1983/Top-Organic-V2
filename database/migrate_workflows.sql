-- =====================================================
-- ترحيل نظام سير العمل إلى الهيكل الجديد
-- يحتفظ بالبيانات القديمة تحت الجداول legacy_approval_*
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- نقل الجداول القديمة (بدون البادئة legacy_) إلى أسماء legacy_*
-- فقط إذا كانت موجودة ولم تُنشأ الأسماء الجديدة بعد.
-- إذا لم تكن الجداول القديمة موجودة، يتم تنفيذ SELECT 1 فارغة.
SET @rename_sql = (
    SELECT GROUP_CONCAT(
        CONCAT('RENAME TABLE ', t.table_name, ' TO legacy_', t.table_name)
        SEPARATOR ', '
    )
    FROM information_schema.tables t
    LEFT JOIN information_schema.tables lt
        ON lt.table_schema = DATABASE()
        AND lt.table_name = CONCAT('legacy_', t.table_name)
    WHERE t.table_schema = DATABASE()
      AND t.table_name IN ('approval_workflows','approval_steps','approval_requests','approval_actions')
      AND lt.table_name IS NULL
);
SET @rename_sql = IFNULL(@rename_sql, 'SELECT 1');
PREPARE stmt FROM @rename_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- الآن ننفذ الملف 15_workflows.sql لإنشاء الجداول الجديدة
-- =====================================================
SOURCE database/15_workflows.sql;
