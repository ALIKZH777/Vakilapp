<?php
/**
 * فایل api/clear_chat.php
 * API برای پاک کردن تاریخچه چت
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
    $user_id = $_SESSION['user_id'];
    
    // دریافت لیست فایل‌های مرتبط برای حذف
    $stmt = $pdo->prepare("SELECT file_path FROM ai_chat_logs WHERE user_id = ? AND file_path IS NOT NULL");
    $stmt->execute([$user_id]);
    $files = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // حذف فایل‌های مرتبط
    foreach ($files as $file_path) {
        if (!empty($file_path) && file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // حذف تمام پیام‌های کاربر
    $stmt = $pdo->prepare("DELETE FROM ai_chat_logs WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    $deleted_count = $stmt->rowCount();
    
    // حذف واکنش‌های مرتبط (اگر جدول وجود دارد)
    try {
        $stmt = $pdo->prepare("DELETE FROM message_reactions WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        // اگر جدول وجود نداشت، مشکلی نیست
    }
    
    // حذف پیام‌های ذخیره شده (اگر جدول وجود دارد)
    try {
        $stmt = $pdo->prepare("DELETE FROM saved_messages WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        // اگر جدول وجود نداشت، مشکلی نیست
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Chat history cleared successfully',
        'deleted_messages' => $deleted_count,
        'deleted_files' => count($files)
    ]);
    
} catch (Exception $e) {
    error_log('Clear chat error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>