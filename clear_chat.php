<?php
/**
 * فایل api/clear_chat.php
 * API برای پاک کردن تاریخچه چت کاربر
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
    if (!$input || !isset($input['csrf_token']) || !validate_csrf_token($input['csrf_token'])) {
        $response['error'] = 'توکن امنیتی نامعتبر.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $user_id = (int)$_SESSION['user_id'];
    
    // شروع تراکنش
    $pdo->beginTransaction();
    
    try {
        // نرم‌حذف پیام‌ها (تغییر وضعیت به حذف شده)
        $stmt = $pdo->prepare("
            UPDATE ai_chat_logs 
            SET is_deleted = TRUE, updated_at = CURRENT_TIMESTAMP 
            WHERE user_id = ? AND is_deleted = FALSE
        ");
        $stmt->execute([$user_id]);
        
        $deleted_count = $stmt->rowCount();
        
        // به‌روزرسانی آمار
        $today = date('Y-m-d');
        $stats_stmt = $pdo->prepare("
            INSERT INTO chat_analytics (user_id, date, total_messages, total_ai_responses)
            VALUES (?, ?, -?, -?)
            ON DUPLICATE KEY UPDATE
                total_messages = GREATEST(0, total_messages - ?),
                total_ai_responses = GREATEST(0, total_ai_responses - ?),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $user_messages = floor($deleted_count / 2);
        $ai_messages = $deleted_count - $user_messages;
        
        $stats_stmt->execute([
            $user_id, $today, $user_messages, $ai_messages, $user_messages, $ai_messages
        ]);
        
        // تایید تراکنش
        $pdo->commit();
        
        $response['success'] = true;
        $response['message'] = "تاریخچه چت پاک شد ({$deleted_count} پیام).";
        $response['deleted_count'] = $deleted_count;
        unset($response['error']);
        
        // ثبت لاگ
        error_log("CHAT_CLEARED - User ID: {$user_id}, Messages: {$deleted_count}");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log('Clear Chat DB Error: ' . $e->getMessage());
    $response['error'] = 'خطا در پاک کردن چت.';
} catch (Exception $e) {
    error_log('Clear Chat Error: ' . $e->getMessage());
    $response['error'] = 'خطای داخلی سرور.';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>