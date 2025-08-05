<?php
/**
 * فایل api/add_reaction.php
 * API برای افزودن واکنش به پیام‌ها
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
    
    if (!$input || !isset($input['message_id']) || !isset($input['emoji'])) {
        throw new Exception('Message ID and emoji are required');
    }
    
    $user_id = $_SESSION['user_id'];
    $message_id = intval($input['message_id']);
    $emoji = trim($input['emoji']);
    
    // لیست اموجی‌های مجاز
    $allowed_emojis = ['👍', '👎', '❤️', '😊', '😢', '😡', '🤔', '🎉', '🔥', '💯'];
    
    if (!in_array($emoji, $allowed_emojis)) {
        throw new Exception('Invalid emoji');
    }
    
    // بررسی اینکه پیام متعلق به این کاربر است
    $stmt = $pdo->prepare("SELECT id FROM ai_chat_logs WHERE id = ? AND user_id = ?");
    $stmt->execute([$message_id, $user_id]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Message not found or access denied');
    }
    
    // بررسی وجود واکنش قبلی
    try {
        $stmt = $pdo->prepare("SELECT id FROM message_reactions WHERE user_id = ? AND message_id = ? AND emoji = ?");
        $stmt->execute([$user_id, $message_id, $emoji]);
        
        if ($stmt->fetch()) {
            // حذف واکنش اگر از قبل وجود دارد
            $stmt = $pdo->prepare("DELETE FROM message_reactions WHERE user_id = ? AND message_id = ? AND emoji = ?");
            $stmt->execute([$user_id, $message_id, $emoji]);
            $action = 'removed';
        } else {
            // افزودن واکنش جدید
            $stmt = $pdo->prepare("INSERT INTO message_reactions (user_id, message_id, emoji, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$user_id, $message_id, $emoji]);
            $action = 'added';
        }
        
    } catch (PDOException $e) {
        // اگر جدول وجود نداشت، آن را ایجاد کن
        if ($e->getCode() == '42S02') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS message_reactions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    message_id INT NOT NULL,
                    emoji VARCHAR(10) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_reaction (user_id, message_id, emoji),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (message_id) REFERENCES ai_chat_logs(id) ON DELETE CASCADE
                )
            ");
            
            // سعی مجدد
            $stmt = $pdo->prepare("INSERT INTO message_reactions (user_id, message_id, emoji, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$user_id, $message_id, $emoji]);
            $action = 'added';
        } else {
            throw $e;
        }
    }
    
    // دریافت تعداد کل واکنش‌ها برای این پیام
    $stmt = $pdo->prepare("
        SELECT emoji, COUNT(*) as count 
        FROM message_reactions 
        WHERE message_id = ? 
        GROUP BY emoji
    ");
    $stmt->execute([$message_id]);
    $reactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'action' => $action,
        'emoji' => $emoji,
        'reactions' => $reactions
    ]);
    
} catch (Exception $e) {
    error_log('Add reaction error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>