-- =====================================================
-- 172: تعزيزات البريد الداخلي — مرفقات، إرسال لقسم كامل، مجموعات بريدية
-- =====================================================
SET NAMES utf8mb4;

-- مرفقات رسائل البريد الداخلي (ملف واحد أو أكثر لكل رسالة)
CREATE TABLE IF NOT EXISTS internal_mail_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mail_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    mime_type VARCHAR(100) NULL,
    file_size INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mail_id) REFERENCES internal_mails(id) ON DELETE CASCADE,
    INDEX idx_mail (mail_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- سجل الهدف الأصلي للإرسال (قسم كامل أو مجموعة بريدية) لعرضه لاحقاً في
-- صندوق الصادر — المستلمون الفعليون يبقون مفصّلين دوماً في
-- internal_mail_recipients بغض النظر عن طريقة اختيارهم.
CREATE TABLE IF NOT EXISTS internal_mail_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mail_id INT NOT NULL,
    target_type ENUM('department','group') NOT NULL,
    target_id INT NOT NULL,
    target_label VARCHAR(150) NOT NULL,
    FOREIGN KEY (mail_id) REFERENCES internal_mails(id) ON DELETE CASCADE,
    INDEX idx_mail (mail_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- مجموعات البريد (قوائم توزيع مخصّصة) — أي مستخدم يمكنه إنشاء مجموعة
-- واستخدامها عند الإرسال، لكن التعديل/الحذف مقصوران على منشئها.
CREATE TABLE IF NOT EXISTS mail_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    created_by INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mail_group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    FOREIGN KEY (group_id) REFERENCES mail_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_member (group_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
