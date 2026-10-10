-- =====================================================
-- 171: البريد الداخلي / المراسلات الداخلية (Internal Mail)
-- =====================================================
-- رسائل حقيقية بين مستخدمي النظام (إرسال/استقبال/رد)، وليست إشعارات بريد
-- إلكتروني خارجية. متاحة لكل مستخدم مسجّل دخول بلا شرط صلاحية (مثل مركز
-- الإشعارات)، في الويب (/mail) وتطبيق الموبايل (تبويب البريد).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS internal_mails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_by_sender TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sender (sender_id, deleted_by_sender)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS internal_mail_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mail_id INT NOT NULL,
    recipient_id INT NOT NULL,
    read_at DATETIME NULL,
    deleted_by_recipient TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (mail_id) REFERENCES internal_mails(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_recipient (recipient_id, deleted_by_recipient, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
