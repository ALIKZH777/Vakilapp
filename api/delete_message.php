<?php
/**
 * فایل api/delete_message.php
 * API برای حذف پیام‌های کاربر
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
    
    if (!$input || !isset($input['message_id'])) {
        throw new Exception('Message ID is required');
    }
    
    $user_id = $_SESSION['user_id'];
    $message_id = intval($input['message_id']);
    
    // بررسی اینکه پیام متعلق به این کاربر است و از نوع user
    $stmt = $pdo->prepare("SELECT id, file_path FROM ai_chat_logs WHERE id = ? AND user_id = ? AND sender = 'user'");
    $stmt->execute([$message_id, $user_id]);
    $message = $stmt->fetch();
    
    if (!$message) {
        throw new Exception('Message not found or cannot be deleted');
    }
    
    // حذف فایل مرتبط اگر وجود دارد
    if (!empty($message['file_path']) && file_exists($message['file_path'])) {
        unlink($message['file_path']);
    }
    
    // حذف پیام از دیتابیس
    $stmt = $pdo->prepare("DELETE FROM ai_chat_logs WHERE id = ?");
    $stmt->execute([$message_id]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Message deleted successfully'
    ]);
    
} catch (Exception $e) {
    error_log('Delete message error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>