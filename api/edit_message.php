<?php
/**
 * فایل api/edit_message.php
 * API برای ویرایش پیام‌های کاربر
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
    
    if (!$input || !isset($input['message_id']) || !isset($input['new_message'])) {
        throw new Exception('Message ID and new message text are required');
    }
    
    $user_id = $_SESSION['user_id'];
    $message_id = intval($input['message_id']);
    $new_message = trim($input['new_message']);
    
    if (empty($new_message)) {
        throw new Exception('Message cannot be empty');
    }
    
    if (mb_strlen($new_message) > 1000) {
        throw new Exception('Message is too long (max 1000 characters)');
    }
    
    // بررسی اینکه پیام متعلق به این کاربر است و از نوع user
    $stmt = $pdo->prepare("SELECT id FROM ai_chat_logs WHERE id = ? AND user_id = ? AND sender = 'user'");
    $stmt->execute([$message_id, $user_id]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Message not found or cannot be edited');
    }
    
    // ویرایش پیام
    $stmt = $pdo->prepare("UPDATE ai_chat_logs SET message = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_message, $message_id]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Message updated successfully',
        'new_message' => $new_message
    ]);
    
} catch (Exception $e) {
    error_log('Edit message error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>