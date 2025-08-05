<?php
/**
 * فایل api/save_settings.php
 * API برای ذخیره تنظیمات چت کاربر
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

// بررسی متد درخواست
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// بررسی احراز هویت
if (!is_logged_in() || get_user_role() !== 'user') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON data');
    }
    
    $user_id = $_SESSION['user_id'];
    $allowed_settings = ['chat_theme', 'chat_sound', 'chat_auto_scroll', 'chat_timestamps'];
    
    foreach ($input as $key => $value) {
        if (in_array($key, $allowed_settings)) {
            $_SESSION[$key] = $value;
            
            // ذخیره در دیتابیس (اختیاری)
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO user_settings (user_id, setting_key, setting_value) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->execute([$user_id, $key, json_encode($value)]);
            } catch (PDOException $e) {
                // اگر جدول وجود نداشت، ادامه بده
                error_log('Settings save error: ' . $e->getMessage());
            }
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
    
} catch (Exception $e) {
    error_log('Save settings error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>