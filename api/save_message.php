<?php
/**
 * فایل api/save_message.php
 * API برای ذخیره/نشانه‌گذاری پیام‌ها
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
    
    // بررسی اینکه پیام متعلق به این کاربر است
    $stmt = $pdo->prepare("SELECT id FROM ai_chat_logs WHERE id = ? AND user_id = ?");
    $stmt->execute([$message_id, $user_id]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Message not found or access denied');
    }
    
    // ذخیره پیام در جدول پیام‌های ذخیره شده
    try {
        $stmt = $pdo->prepare("
            INSERT INTO saved_messages (user_id, message_id, saved_at) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE saved_at = NOW()
        ");
        $stmt->execute([$user_id, $message_id]);
        
        echo json_encode(['success' => true, 'message' => 'Message saved successfully']);
        
    } catch (PDOException $e) {
        // اگر جدول وجود نداشت، آن را ایجاد کن
        if ($e->getCode() == '42S02') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS saved_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    message_id INT NOT NULL,
                    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_save (user_id, message_id),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (message_id) REFERENCES ai_chat_logs(id) ON DELETE CASCADE
                )
            ");
            
            // سعی مجدد
            $stmt = $pdo->prepare("
                INSERT INTO saved_messages (user_id, message_id, saved_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE saved_at = NOW()
            ");
            $stmt->execute([$user_id, $message_id]);
            
            echo json_encode(['success' => true, 'message' => 'Message saved successfully']);
        } else {
            throw $e;
        }
    }
    
} catch (Exception $e) {
    error_log('Save message error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>