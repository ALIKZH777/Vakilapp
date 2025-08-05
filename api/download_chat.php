<?php
/**
 * فایل api/download_chat.php
 * API برای دانلود تاریخچه چت
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

// بررسی احراز هویت
if (!is_logged_in() || get_user_role() !== 'user') {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['full_name'] ?? 'کاربر';
    
    // دریافت تاریخچه چت
    $stmt = $pdo->prepare("
        SELECT message, sender, timestamp, file_name 
        FROM ai_chat_logs 
        WHERE user_id = ? 
        ORDER BY timestamp ASC
    ");
    $stmt->execute([$user_id]);
    $chat_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($chat_history)) {
        echo 'هیچ پیامی برای دانلود وجود ندارد.';
        exit;
    }
    
    // تنظیم هدرهای دانلود
    $filename = 'chat_history_' . date('Y-m-d_H-i-s') . '.txt';
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    // ایجاد محتوای فایل
    echo "تاریخچه چت - " . $user_name . "\n";
    echo "تاریخ تولید: " . jdate('Y/m/d H:i:s') . "\n";
    echo str_repeat("=", 50) . "\n\n";
    
    foreach ($chat_history as $message) {
        $sender_name = ($message['sender'] === 'user') ? $user_name : 'دستیار هوشمند';
        $timestamp = jdate('Y/m/d H:i:s', strtotime($message['timestamp']));
        
        echo "[{$timestamp}] {$sender_name}:\n";
        echo $message['message'] . "\n";
        
        if (!empty($message['file_name'])) {
            echo "(فایل ضمیمه: {$message['file_name']})\n";
        }
        
        echo "\n" . str_repeat("-", 30) . "\n\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "پایان تاریخچه چت\n";
    echo "تعداد کل پیام‌ها: " . count($chat_history) . "\n";
    
} catch (Exception $e) {
    error_log('Download chat error: ' . $e->getMessage());
    http_response_code(500);
    echo 'خطا در تولید فایل دانلود.';
}

// تابع تاریخ شمسی ساده (اگر jdate وجود نداشت)
if (!function_exists('jdate')) {
    function jdate($format, $timestamp = null) {
        if ($timestamp === null) {
            $timestamp = time();
        }
        return date($format, $timestamp); // fallback به تاریخ میلادی
    }
}
?>