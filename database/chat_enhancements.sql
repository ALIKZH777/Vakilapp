-- فایل database/chat_enhancements.sql
-- جداول مورد نیاز برای قابلیت‌های پیشرفته چت

-- جدول تنظیمات کاربران
CREATE TABLE IF NOT EXISTS user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    setting_key VARCHAR(50) NOT NULL,
    setting_value JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_setting (user_id, setting_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_setting (user_id, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول پیام‌های ذخیره شده/نشانه‌گذاری شده
CREATE TABLE IF NOT EXISTS saved_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message_id INT NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT NULL COMMENT 'یادداشت اختیاری کاربر',
    UNIQUE KEY unique_save (user_id, message_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES ai_chat_logs(id) ON DELETE CASCADE,
    INDEX idx_user_saved (user_id, saved_at),
    INDEX idx_message_saved (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول واکنش‌های پیام‌ها
CREATE TABLE IF NOT EXISTS message_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message_id INT NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reaction (user_id, message_id, emoji),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES ai_chat_logs(id) ON DELETE CASCADE,
    INDEX idx_message_reactions (message_id, emoji),
    INDEX idx_user_reactions (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- بروزرسانی جدول ai_chat_logs برای پشتیبانی از قابلیت‌های جدید
ALTER TABLE ai_chat_logs 
ADD COLUMN IF NOT EXISTS message_type ENUM('text', 'image', 'file', 'audio', 'video') DEFAULT 'text' AFTER sender,
ADD COLUMN IF NOT EXISTS file_path VARCHAR(500) NULL AFTER message_type,
ADD COLUMN IF NOT EXISTS file_name VARCHAR(255) NULL AFTER file_path,
ADD COLUMN IF NOT EXISTS file_size INT NULL AFTER file_name,
ADD COLUMN IF NOT EXISTS file_mime_type VARCHAR(100) NULL AFTER file_size,
ADD COLUMN IF NOT EXISTS is_edited BOOLEAN DEFAULT FALSE AFTER file_mime_type,
ADD COLUMN IF NOT EXISTS edited_at TIMESTAMP NULL AFTER is_edited,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER edited_at;

-- افزودن ایندکس‌های مفید
ALTER TABLE ai_chat_logs 
ADD INDEX IF NOT EXISTS idx_user_timestamp (user_id, timestamp),
ADD INDEX IF NOT EXISTS idx_message_type (message_type),
ADD INDEX IF NOT EXISTS idx_sender_timestamp (sender, timestamp);

-- جدول لاگ فعالیت‌های چت (اختیاری)
CREATE TABLE IF NOT EXISTS chat_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type ENUM('message_sent', 'message_edited', 'message_deleted', 'file_uploaded', 'reaction_added', 'settings_changed') NOT NULL,
    target_id INT NULL COMMENT 'ID پیام یا فایل مرتبط',
    details JSON NULL COMMENT 'جزئیات اضافی عملیات',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_activity (user_id, created_at),
    INDEX idx_action_type (action_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول آمار استفاده از چت
CREATE TABLE IF NOT EXISTS chat_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    messages_sent INT DEFAULT 0,
    messages_received INT DEFAULT 0,
    files_uploaded INT DEFAULT 0,
    reactions_given INT DEFAULT 0,
    total_characters INT DEFAULT 0,
    session_duration INT DEFAULT 0 COMMENT 'مدت زمان در ثانیه',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_date (user_id, date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_date_stats (date),
    INDEX idx_user_stats (user_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- View برای نمایش آمار کلی کاربران
CREATE OR REPLACE VIEW user_chat_summary AS
SELECT 
    u.id as user_id,
    u.full_name,
    COUNT(DISTINCT acl.id) as total_messages,
    COUNT(DISTINCT CASE WHEN acl.sender = 'user' THEN acl.id END) as messages_sent,
    COUNT(DISTINCT CASE WHEN acl.sender = 'ai' THEN acl.id END) as messages_received,
    COUNT(DISTINCT CASE WHEN acl.file_path IS NOT NULL THEN acl.id END) as files_shared,
    COUNT(DISTINCT sm.id) as saved_messages,
    COUNT(DISTINCT mr.id) as reactions_given,
    MAX(acl.timestamp) as last_message_at,
    DATEDIFF(CURDATE(), MAX(acl.timestamp)) as days_since_last_message
FROM users u
LEFT JOIN ai_chat_logs acl ON u.id = acl.user_id
LEFT JOIN saved_messages sm ON u.id = sm.user_id
LEFT JOIN message_reactions mr ON u.id = mr.user_id
WHERE u.role = 'user'
GROUP BY u.id, u.full_name;

-- Trigger برای به‌روزرسانی آمار روزانه
DELIMITER //
CREATE TRIGGER IF NOT EXISTS update_daily_stats 
AFTER INSERT ON ai_chat_logs
FOR EACH ROW
BEGIN
    IF NEW.sender = 'user' THEN
        INSERT INTO chat_statistics (user_id, date, messages_sent, total_characters)
        VALUES (NEW.user_id, DATE(NEW.timestamp), 1, CHAR_LENGTH(NEW.message))
        ON DUPLICATE KEY UPDATE 
            messages_sent = messages_sent + 1,
            total_characters = total_characters + CHAR_LENGTH(NEW.message),
            updated_at = CURRENT_TIMESTAMP;
    ELSE
        INSERT INTO chat_statistics (user_id, date, messages_received)
        VALUES (NEW.user_id, DATE(NEW.timestamp), 1)
        ON DUPLICATE KEY UPDATE 
            messages_received = messages_received + 1,
            updated_at = CURRENT_TIMESTAMP;
    END IF;
END//
DELIMITER ;

-- Trigger برای لاگ کردن فعالیت‌ها
DELIMITER //
CREATE TRIGGER IF NOT EXISTS log_message_activity 
AFTER INSERT ON ai_chat_logs
FOR EACH ROW
BEGIN
    IF NEW.sender = 'user' THEN
        INSERT INTO chat_activity_logs (user_id, action_type, target_id, details)
        VALUES (NEW.user_id, 'message_sent', NEW.id, JSON_OBJECT(
            'message_type', NEW.message_type,
            'has_file', IF(NEW.file_path IS NOT NULL, TRUE, FALSE),
            'message_length', CHAR_LENGTH(NEW.message)
        ));
    END IF;
END//
DELIMITER ;

-- Stored Procedure برای پاک کردن داده‌های قدیمی
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS CleanupOldChatData(IN days_to_keep INT)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE old_date DATE;
    
    SET old_date = DATE_SUB(CURDATE(), INTERVAL days_to_keep DAY);
    
    -- حذف لاگ‌های فعالیت قدیمی
    DELETE FROM chat_activity_logs WHERE created_at < old_date;
    
    -- حذف آمار قدیمی (اختیاری)
    DELETE FROM chat_statistics WHERE date < old_date;
    
    -- گزارش نتیجه
    SELECT 
        'Cleanup completed' as status,
        old_date as cutoff_date,
        ROW_COUNT() as records_deleted;
END//
DELIMITER ;

-- Function برای محاسبه امتیاز فعالیت کاربر
DELIMITER //
CREATE FUNCTION IF NOT EXISTS CalculateUserActivityScore(user_id INT) 
RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE score INT DEFAULT 0;
    DECLARE msg_count, reaction_count, file_count INT;
    
    -- تعداد پیام‌های ارسالی در 30 روز گذشته
    SELECT COUNT(*) INTO msg_count 
    FROM ai_chat_logs 
    WHERE user_id = user_id 
      AND sender = 'user' 
      AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- تعداد واکنش‌های داده شده
    SELECT COUNT(*) INTO reaction_count 
    FROM message_reactions 
    WHERE user_id = user_id 
      AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- تعداد فایل‌های به اشتراک گذاشته شده
    SELECT COUNT(*) INTO file_count 
    FROM ai_chat_logs 
    WHERE user_id = user_id 
      AND file_path IS NOT NULL 
      AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- محاسبه امتیاز
    SET score = (msg_count * 2) + (reaction_count * 1) + (file_count * 3);
    
    RETURN score;
END//
DELIMITER ;

-- داده‌های نمونه برای تست
INSERT IGNORE INTO user_settings (user_id, setting_key, setting_value) VALUES 
(1, 'chat_theme', '"light"'),
(1, 'chat_sound', 'true'),
(1, 'chat_auto_scroll', 'true'),
(1, 'chat_timestamps', 'false');

-- نمایش وضعیت جداول ایجاد شده
SELECT 
    TABLE_NAME as 'جدول',
    TABLE_ROWS as 'تعداد رکورد',
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as 'حجم (MB)'
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME IN ('user_settings', 'saved_messages', 'message_reactions', 'chat_activity_logs', 'chat_statistics')
ORDER BY TABLE_NAME;