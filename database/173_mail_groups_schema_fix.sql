-- =====================================================
-- 173: إصلاح مخطط mail_groups على البيئات التي كان فيها جدول قديم بهذا
-- الاسم من نظام بريد تجريبي مهجور (owner_id بدل created_by، بدون is_active)
-- سبق migration 172 — فتجاهلته CREATE TABLE IF NOT EXISTS وتركت الأعمدة
-- التي يعتمد عليها الكود الحالي ناقصة، مما كسر كل صفحات البريد بخطأ
-- "Unknown column 'g.is_active'". آمن بلا فقد بيانات (الجدول فارغ في هذه
-- الحالة لأن النظام التجريبي القديم لم يُستخدم فعلياً)، وغير مؤثر إطلاقاً
-- على البيئات التي أنشأت migration 172 الجدول بالمخطط الصحيح من البداية.
-- =====================================================
SET NAMES utf8mb4;
SET @db = DATABASE();

ALTER TABLE mail_groups ADD COLUMN created_by INT(11) NOT NULL DEFAULT 0 AFTER id;
ALTER TABLE mail_groups ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1;

-- ينقل owner_id القديم إلى created_by فقط إن كان العمود owner_id موجوداً فعلاً
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mail_groups' AND COLUMN_NAME = 'owner_id') > 0,
    'UPDATE mail_groups SET created_by = owner_id WHERE created_by = 0',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- يضيف القيد الأجنبي فقط إن لم يكن على created_by أي قيد أجنبي أصلاً
-- (تفادياً لتكرار قيدين مختلفي الاسم على نفس العمود على البيئات السليمة
-- التي أنشأت migration 172 العمود created_by بقيده الخاص من البداية)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mail_groups' AND COLUMN_NAME = 'created_by'
       AND REFERENCED_TABLE_NAME IS NOT NULL) = 0,
    'ALTER TABLE mail_groups ADD CONSTRAINT fk_mail_groups_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
