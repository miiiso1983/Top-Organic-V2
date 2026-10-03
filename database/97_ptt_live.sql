-- =====================================================
-- 97: PTT البث المباشر (Walkie-Talkie عبر LiveKit)
-- =====================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS ptt_live_sessions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL,
    room_name VARCHAR(120) NOT NULL,
    started_by INT NOT NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    FOREIGN KEY (channel_id) REFERENCES ptt_channels(id) ON DELETE CASCADE,
    FOREIGN KEY (started_by) REFERENCES users(id),
    INDEX idx_channel_active (channel_id, ended_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
