<?php
/**
 * فایل api/update_preferences.php
 * API برای به‌روزرسانی تنظیمات چت کاربر
 * 
 * @version 2.0
 * @author Seyno Development Team
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$response = ['success' => false, 'error' => 'درخواست نامعتبر.'];

try {
    // بررسی‌های امنیتی
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_logged_in() || get_user_role() !== 'user') {
        $response['error'] = 'دسترسی غیرمجاز.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // دریافت داده‌های JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $response['error'] = 'داده‌های نامعتبر.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $user_id = (int)$_SESSION['user_id'];
    
    // اعتبارسنجی داده‌ها
    $valid_personalities = ['mehrdad', 'mehrnoosh'];
    $valid_themes = ['light', 'dark', 'auto'];
    $valid_font_sizes = ['small', 'medium', 'large'];
    
    $ai_personality = in_array($input['ai_personality'] ?? '', $valid_personalities) ? $input['ai_personality'] : 'mehrdad';
    $theme = in_array($input['theme'] ?? '', $valid_themes) ? $input['theme'] : 'light';
    $font_size = in_array($input['font_size'] ?? '', $valid_font_sizes) ? $input['font_size'] : 'medium';
    $auto_scroll = (bool)($input['auto_scroll'] ?? true);
    $notifications_enabled = (bool)($input['notifications_enabled'] ?? true);
    $voice_enabled = (bool)($input['voice_enabled'] ?? false);
    
    // به‌روزرسانی تنظیمات
    $stmt = $pdo->prepare("
        INSERT INTO user_chat_preferences 
        (user_id, ai_personality, theme, font_size, auto_scroll, notifications_enabled, voice_enabled)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            ai_personality = VALUES(ai_personality),
            theme = VALUES(theme),
            font_size = VALUES(font_size),
            auto_scroll = VALUES(auto_scroll),
            notifications_enabled = VALUES(notifications_enabled),
            voice_enabled = VALUES(voice_enabled),
            updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->execute([
        $user_id, $ai_personality, $theme, $font_size,
        $auto_scroll, $notifications_enabled, $voice_enabled
    ]);
    
    $response['success'] = true;
    $response['message'] = 'تنظیمات با موفقیت ذخیره شد.';
    unset($response['error']);
    
} catch (PDOException $e) {
    error_log('Update Preferences DB Error: ' . $e->getMessage());
    $response['error'] = 'خطا در ذخیره تنظیمات.';
} catch (Exception $e) {
    error_log('Update Preferences Error: ' . $e->getMessage());
    $response['error'] = 'خطای داخلی سرور.';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>