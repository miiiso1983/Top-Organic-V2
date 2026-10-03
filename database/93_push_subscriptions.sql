-- =====================================================
-- 93: تجهيز إشعارات Push (Web Push / FCM)
-- الاشتراكات تُخزن هنا؛ الإرسال الفعلي يتطلب مكتبة web-push أو FCM (يُفعّل لاحقاً)
-- =====================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    endpoint TEXT NOT NULL COMMENT 'رابط الاشتراك من المتصفح/التطبيق',
    p256dh VARCHAR(255) NULL COMMENT 'مفتاح التشفير العام',
    auth VARCHAR(255) NULL COMMENT 'مفتاح المصادقة',
    platform VARCHAR(30) DEFAULT 'web' COMMENT 'web | android | ios',
    user_agent VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- قناة push في طابور الإشعارات (للإرسال اللاحق عبر العامل/الكرون)
ALTER TABLE notification_queue
    MODIFY COLUMN channel ENUM('email','whatsapp','sms','push') NOT NULL;
