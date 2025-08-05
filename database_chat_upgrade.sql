-- =====================================================
-- بهبود ساختار دیتابیس برای سیستم چت پیشرفته
-- نسخه: 2.0
-- تاریخ: 2024
-- =====================================================

-- 1. بهبود جدول ai_chat_logs
DROP TABLE IF EXISTS ai_chat_logs_backup;
CREATE TABLE ai_chat_logs_backup AS SELECT * FROM ai_chat_logs WHERE 1=1;

DROP TABLE IF EXISTS ai_chat_logs;
CREATE TABLE ai_chat_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    sender ENUM('user', 'ai') NOT NULL,
    message_type ENUM('text', 'image', 'file', 'voice', 'system') DEFAULT 'text',
    ai_service VARCHAR(50) DEFAULT NULL COMMENT 'نام سرویس AI استفاده شده',
    tokens_used INT DEFAULT 0 COMMENT 'تعداد توکن‌های مصرف شده',
    response_time DECIMAL(5,3) DEFAULT NULL COMMENT 'زمان پاسخ به ثانیه',
    is_emergency BOOLEAN DEFAULT FALSE COMMENT 'آیا پیام اضطراری است',
    is_deleted BOOLEAN DEFAULT FALSE COMMENT 'آیا پیام حذف شده',
    metadata JSON DEFAULT NULL COMMENT 'اطلاعات اضافی پیام',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_sender (sender),
    INDEX idx_emergency (is_emergency),
    INDEX idx_deleted (is_deleted),
    INDEX idx_message_type (message_type),
    FULLTEXT idx_message_search (message)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. جدول جلسات چت
CREATE TABLE IF NOT EXISTS chat_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    session_title VARCHAR(255) DEFAULT 'گفتگوی جدید',
    ai_personality ENUM('mehrdad', 'mehrnoosh') NOT NULL,
    total_messages INT DEFAULT 0,
    total_tokens INT DEFAULT 0,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    is_archived BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_last_activity (last_activity),
    INDEX idx_archived (is_archived)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. جدول فایل‌های آپلود شده در چت
CREATE TABLE IF NOT EXISTS chat_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chat_log_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    is_processed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (chat_log_id) REFERENCES ai_chat_logs(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (file_type),
    INDEX idx_processed (is_processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. جدول تنظیمات چت کاربران
CREATE TABLE IF NOT EXISTS user_chat_preferences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    ai_personality ENUM('mehrdad', 'mehrnoosh') NOT NULL DEFAULT 'mehrdad',
    voice_enabled BOOLEAN DEFAULT FALSE,
    notifications_enabled BOOLEAN DEFAULT TRUE,
    auto_scroll BOOLEAN DEFAULT TRUE,
    theme ENUM('light', 'dark', 'auto') DEFAULT 'light',
    font_size ENUM('small', 'medium', 'large') DEFAULT 'medium',
    language ENUM('fa', 'en') DEFAULT 'fa',
    emergency_contacts JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_personality (ai_personality)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. جدول آمار و گزارش‌های چت
CREATE TABLE IF NOT EXISTS chat_analytics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    total_messages INT DEFAULT 0,
    total_ai_responses INT DEFAULT 0,
    total_tokens_used INT DEFAULT 0,
    avg_response_time DECIMAL(5,3) DEFAULT NULL,
    emergency_alerts INT DEFAULT 0,
    active_minutes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_user_date (user_id, date),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. بازگردانی داده‌های قدیمی (در صورت وجود)
INSERT IGNORE INTO ai_chat_logs (user_id, message, sender, created_at)
SELECT user_id, message, sender, timestamp as created_at 
FROM ai_chat_logs_backup 
WHERE timestamp IS NOT NULL;

-- 7. ایجاد تریگر برای به‌روزرسانی آمار
DELIMITER //

CREATE TRIGGER update_chat_analytics 
AFTER INSERT ON ai_chat_logs
FOR EACH ROW
BEGIN
    INSERT INTO chat_analytics (user_id, date, total_messages, total_ai_responses)
    VALUES (NEW.user_id, DATE(NEW.created_at), 
            CASE WHEN NEW.sender = 'user' THEN 1 ELSE 0 END,
            CASE WHEN NEW.sender = 'ai' THEN 1 ELSE 0 END)
    ON DUPLICATE KEY UPDATE
        total_messages = total_messages + CASE WHEN NEW.sender = 'user' THEN 1 ELSE 0 END,
        total_ai_responses = total_ai_responses + CASE WHEN NEW.sender = 'ai' THEN 1 ELSE 0 END,
        total_tokens_used = total_tokens_used + COALESCE(NEW.tokens_used, 0),
        updated_at = CURRENT_TIMESTAMP;
END//

DELIMITER ;

-- 8. افزودن تنظیمات پیش‌فرض برای کاربران موجود
INSERT IGNORE INTO user_chat_preferences (user_id, ai_personality)
SELECT id, CASE WHEN gender = 'male' THEN 'mehrdad' ELSE 'mehrnoosh' END
FROM users 
WHERE role = 'user';

-- 9. بهینه‌سازی جداول
OPTIMIZE TABLE ai_chat_logs;
OPTIMIZE TABLE chat_sessions;
OPTIMIZE TABLE chat_attachments;
OPTIMIZE TABLE user_chat_preferences;
OPTIMIZE TABLE chat_analytics;

-- 10. نمایش وضعیت جداول
SHOW TABLE STATUS LIKE 'ai_chat_logs';
SHOW TABLE STATUS LIKE 'chat_sessions';
SHOW TABLE STATUS LIKE 'user_chat_preferences';

-- پایان اسکریپت SQL