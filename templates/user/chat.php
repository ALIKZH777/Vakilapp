<?php
/**
 * فایل templates/user/chat.php
 *
 * رابط چت پیشرفته کاربر با دستیار هوشمند ساینو
 * قابلیت‌ها: 
 * - بارگذاری تاریخچه چت با صفحه‌بندی
 * - ارسال و دریافت پیام به صورت زنده
 * - پشتیبانی از فایل و تصاویر
 * - حالت تاریک/روشن
 * - جستجو در تاریخچه
 * - ذخیره پیام‌ها
 * - اموجی و استیکر
 * - پیش‌نمایش لینک
 * - اعلان‌های صوتی
 */

// فراخوانی فایل‌های ضروری
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session.php';

// اطمینان از اینکه فقط کاربر عادی به این صفحه دسترسی دارد
if (get_user_role() !== 'user') {
    redirect('/index.php');
}

$user_id = $_SESSION['user_id'];

// تعیین نام و تصویر دستیار هوشمند بر اساس جنسیت کاربر
$ai_name = ($_SESSION['gender'] === 'male') ? 'مهرداد' : 'مهرنوش';
$ai_pic = ($_SESSION['gender'] === 'male') ? 'https://psynoland.ir/mehrdad.webp' : 'https://psynoland.ir/mehnoosh.webp';
$user_pic = $_SESSION['profile_pic_url'] ?? '/assets/images/default_avatar.png';
$user_name = $_SESSION['full_name'] ?? 'کاربر';

// خواندن تاریخچه چت از دیتابیس با محدودیت
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

try {
    // شمارش کل پیام‌ها
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_chat_logs WHERE user_id = ?");
    $count_stmt->execute([$user_id]);
    $total_messages = $count_stmt->fetchColumn();
    
    // دریافت پیام‌ها
    $stmt = $pdo->prepare("
        SELECT id, message, sender, timestamp, message_type, file_path, file_name 
        FROM ai_chat_logs 
        WHERE user_id = ? 
        ORDER BY timestamp DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user_id, $limit, $offset]);
    $chat_history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    
} catch (PDOException $e) {
    error_log('Chat History Error: ' . $e->getMessage());
    set_flash_message('error', 'خطا در بارگذاری تاریخچه گفتگو.');
    $chat_history = [];
    $total_messages = 0;
}

// تنظیمات کاربر
$user_settings = [
    'theme' => $_SESSION['chat_theme'] ?? 'light',
    'sound_enabled' => $_SESSION['chat_sound'] ?? true,
    'auto_scroll' => $_SESSION['chat_auto_scroll'] ?? true,
    'show_timestamps' => $_SESSION['chat_timestamps'] ?? false
];
?>

<!-- استایل‌های CSS پیشرفته -->
<style>
:root {
    --primary-color: #3b82f6;
    --primary-hover: #2563eb;
    --success-color: #10b981;
    --danger-color: #ef4444;
    --warning-color: #f59e0b;
    --dark-bg: #1f2937;
    --dark-surface: #374151;
    --dark-text: #f9fafb;
    --light-bg: #ffffff;
    --light-surface: #f8fafc;
    --light-text: #1f2937;
}

.theme-dark {
    --bg-primary: var(--dark-bg);
    --bg-secondary: var(--dark-surface);
    --text-primary: var(--dark-text);
    --text-secondary: #d1d5db;
    --border-color: #4b5563;
    --message-bg-user: var(--primary-color);
    --message-bg-ai: #4b5563;
}

.theme-light {
    --bg-primary: var(--light-bg);
    --bg-secondary: var(--light-surface);
    --text-primary: var(--light-text);
    --text-secondary: #6b7280;
    --border-color: #e5e7eb;
    --message-bg-user: var(--primary-color);
    --message-bg-ai: #f3f4f6;
}

.chat-container {
    background: var(--bg-primary);
    color: var(--text-primary);
    transition: all 0.3s ease;
}

.message-bubble {
    animation: messageSlideIn 0.3s ease-out;
    transition: all 0.2s ease;
}

.message-bubble:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

@keyframes messageSlideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-10px); }
}

.typing-dot {
    animation: typing 1.4s infinite;
}

.typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-dot:nth-child(3) { animation-delay: 0.4s; }

.emoji-picker {
    display: none;
    position: absolute;
    bottom: 100%;
    right: 0;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
}

.emoji-grid {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 8px;
}

.emoji-item {
    padding: 8px;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.2s;
    text-align: center;
    font-size: 18px;
}

.emoji-item:hover {
    background: var(--bg-secondary);
}

.file-preview {
    max-width: 300px;
    border-radius: 12px;
    overflow: hidden;
    margin-top: 8px;
}

.file-preview img {
    width: 100%;
    height: auto;
    display: block;
}

.message-actions {
    opacity: 0;
    transition: opacity 0.2s;
    display: flex;
    gap: 8px;
    margin-top: 8px;
}

.message-bubble:hover .message-actions {
    opacity: 1;
}

.action-btn {
    padding: 4px 8px;
    border-radius: 6px;
    background: rgba(0,0,0,0.1);
    border: none;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}

.action-btn:hover {
    background: rgba(0,0,0,0.2);
}

.search-highlight {
    background: yellow;
    padding: 1px 3px;
    border-radius: 3px;
}

.voice-recording {
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    z-index: 1000;
    animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.notification.success { background: var(--success-color); }
.notification.error { background: var(--danger-color); }
.notification.warning { background: var(--warning-color); }

.loading-spinner {
    width: 20px;
    height: 20px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.quick-replies {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    flex-wrap: wrap;
}

.quick-reply-btn {
    padding: 8px 16px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
}

.quick-reply-btn:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.message-timestamp {
    font-size: 11px;
    color: var(--text-secondary);
    margin-top: 4px;
    opacity: 0.7;
}

.chat-header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

.header-btn {
    padding: 8px;
    border-radius: 8px;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: background-color 0.2s;
    color: var(--text-primary);
}

.header-btn:hover {
    background: var(--bg-secondary);
}

.sidebar {
    position: fixed;
    top: 0;
    right: -400px;
    width: 400px;
    height: 100vh;
    background: var(--bg-primary);
    border-left: 1px solid var(--border-color);
    transition: right 0.3s ease;
    z-index: 1000;
    overflow-y: auto;
}

.sidebar.open {
    right: 0;
}

.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.sidebar-overlay.active {
    opacity: 1;
    visibility: visible;
}

@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        right: -100%;
    }
    
    .chat-container {
        height: calc(100vh - 120px);
    }
    
    .emoji-picker {
        position: fixed;
        bottom: 80px;
        right: 10px;
        left: 10px;
        width: auto;
    }
}

.message-reactions {
    display: flex;
    gap: 4px;
    margin-top: 8px;
    flex-wrap: wrap;
}

.reaction {
    padding: 2px 6px;
    background: var(--bg-secondary);
    border-radius: 12px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.reaction:hover {
    background: var(--primary-color);
    color: white;
}

.reaction.active {
    background: var(--primary-color);
    color: white;
}
</style>

<!-- ساختار HTML پیشرفته -->
<div class="chat-container theme-<?php echo $user_settings['theme']; ?>" id="chat-container">
    <!-- Sidebar برای تنظیمات و جستجو -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <div class="sidebar" id="settings-sidebar">
        <div class="p-6 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold">تنظیمات چت</h3>
                <button class="header-btn" onclick="closeSidebar()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6 space-y-6">
            <!-- تغییر تم -->
            <div>
                <label class="block text-sm font-medium mb-3">حالت نمایش</label>
                <div class="flex gap-2">
                    <button class="flex-1 p-3 rounded-lg border text-center theme-btn" data-theme="light">
                        <i class="fas fa-sun mb-2"></i>
                        <div>روشن</div>
                    </button>
                    <button class="flex-1 p-3 rounded-lg border text-center theme-btn" data-theme="dark">
                        <i class="fas fa-moon mb-2"></i>
                        <div>تاریک</div>
                    </button>
                </div>
            </div>
            
            <!-- تنظیمات صدا -->
            <div>
                <label class="flex items-center justify-between">
                    <span class="text-sm font-medium">اعلان‌های صوتی</span>
                    <input type="checkbox" id="sound-toggle" class="toggle" <?php echo $user_settings['sound_enabled'] ? 'checked' : ''; ?>>
                </label>
            </div>
            
            <!-- تنظیمات اسکرول خودکار -->
            <div>
                <label class="flex items-center justify-between">
                    <span class="text-sm font-medium">اسکرول خودکار</span>
                    <input type="checkbox" id="auto-scroll-toggle" class="toggle" <?php echo $user_settings['auto_scroll'] ? 'checked' : ''; ?>>
                </label>
            </div>
            
            <!-- نمایش زمان -->
            <div>
                <label class="flex items-center justify-between">
                    <span class="text-sm font-medium">نمایش زمان پیام‌ها</span>
                    <input type="checkbox" id="timestamps-toggle" class="toggle" <?php echo $user_settings['show_timestamps'] ? 'checked' : ''; ?>>
                </label>
            </div>
            
            <!-- جستجو در تاریخچه -->
            <div>
                <label class="block text-sm font-medium mb-2">جستجو در تاریخچه</label>
                <div class="relative">
                    <input type="text" id="search-input" placeholder="جستجو در پیام‌ها..." 
                           class="w-full px-4 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500">
                    <button class="absolute left-2 top-1/2 -translate-y-1/2" onclick="searchMessages()">
                        <i class="fas fa-search text-gray-400"></i>
                    </button>
                </div>
            </div>
            
            <!-- پاک کردن تاریخچه -->
            <div>
                <button onclick="clearChatHistory()" class="w-full p-3 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">
                    <i class="fas fa-trash-alt ml-2"></i>
                    پاک کردن تاریخچه
                </button>
            </div>
            
            <!-- دانلود تاریخچه -->
            <div>
                <button onclick="downloadChatHistory()" class="w-full p-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition">
                    <i class="fas fa-download ml-2"></i>
                    دانلود تاریخچه
                </button>
            </div>
        </div>
    </div>

    <div class="flex flex-col h-[calc(100vh-120px)] max-w-6xl mx-auto bg-white dark:bg-gray-900 rounded-2xl shadow-2xl overflow-hidden">
        <!-- هدر چت پیشرفته -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-blue-50 to-purple-50 dark:from-gray-800 dark:to-gray-900">
            <div class="flex items-center">
                <div class="relative">
                    <img src="<?php echo e($ai_pic); ?>" alt="<?php echo e($ai_name); ?>" 
                         class="w-14 h-14 rounded-full object-cover border-3 border-white shadow-lg">
                    <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-green-500 rounded-full border-2 border-white animate-pulse"></div>
                </div>
                <div class="mr-4">
                    <h2 class="font-bold text-xl text-gray-800 dark:text-white"><?php echo e($ai_name); ?></h2>
                    <p class="text-sm text-green-500 flex items-center">
                        <span class="w-2 h-2 bg-green-500 rounded-full inline-block ml-1 animate-pulse"></span>
                        آنلاین - آماده پاسخگویی
                    </p>
                    <p class="text-xs text-gray-500" id="typing-status"></p>
                </div>
            </div>
            
            <div class="chat-header-actions">
                <!-- دکمه جستجو -->
                <button class="header-btn" onclick="toggleSearch()" title="جستجو">
                    <i class="fas fa-search"></i>
                </button>
                
                <!-- دکمه تنظیمات -->
                <button class="header-btn" onclick="openSidebar()" title="تنظیمات">
                    <i class="fas fa-cog"></i>
                </button>
                
                <!-- دکمه تمام صفحه -->
                <button class="header-btn" onclick="toggleFullscreen()" title="تمام صفحه">
                    <i class="fas fa-expand"></i>
                </button>
                
                <!-- نشانگر آنلاین بودن کاربران -->
                <div class="flex -space-x-2">
                    <img src="<?php echo e($user_pic); ?>" alt="<?php echo e($user_name); ?>" 
                         class="w-8 h-8 rounded-full border-2 border-white" title="<?php echo e($user_name); ?>">
                </div>
            </div>
        </div>

        <!-- نوار جستجو (مخفی) -->
        <div id="search-bar" class="hidden p-4 bg-yellow-50 border-b border-yellow-200">
            <div class="flex items-center gap-3">
                <div class="flex-1 relative">
                    <input type="text" id="live-search" placeholder="جستجو در پیام‌ها..." 
                           class="w-full px-4 py-2 rounded-lg border focus:ring-2 focus:ring-yellow-400">
                    <button class="absolute left-2 top-1/2 -translate-y-1/2" onclick="performSearch()">
                        <i class="fas fa-search text-gray-400"></i>
                    </button>
                </div>
                <button onclick="clearSearch()" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                    پاک کردن
                </button>
                <button onclick="toggleSearch()" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">
                    بستن
                </button>
            </div>
            <div id="search-results" class="mt-2 text-sm text-gray-600"></div>
        </div>

        <!-- بدنه پیام‌ها با قابلیت‌های پیشرفته -->
        <div id="chat-window" class="flex-grow p-6 space-y-6 overflow-y-auto bg-gradient-to-b from-gray-50 to-white dark:from-gray-900 dark:to-gray-800">
            <?php if (empty($chat_history)): ?>
                <!-- پیام خوش‌آمدگویی پیشرفته -->
                <div class="flex items-start message-bubble">
                    <img src="<?php echo e($ai_pic); ?>" alt="<?php echo e($ai_name); ?>" 
                         class="w-12 h-12 rounded-full object-cover ml-3 flex-shrink-0 border-2 border-blue-200">
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-2xl rounded-tr-none max-w-lg shadow-md">
                        <p class="text-gray-800">سلام <?php echo e($user_name); ?> عزیز! 👋</p>
                        <p class="text-gray-700 mt-2">من <?php echo e($ai_name); ?> هستم، دستیار هوشمند شما. خوشحالم که اینجا هستی! 🌟</p>
                        <p class="text-gray-600 mt-2 text-sm">چطور می‌تونم کمکت کنم؟ می‌تونی از من هر سوالی بپرسی یا درباره هر موضوعی صحبت کنی. 💬</p>
                        
                        <!-- پیشنهادات سریع -->
                        <div class="quick-replies">
                            <button class="quick-reply-btn" onclick="sendQuickReply('سلام، حالت چطوره؟')">
                                سلام بگو 👋
                            </button>
                            <button class="quick-reply-btn" onclick="sendQuickReply('راهنمایی‌ات رو می‌خوام')">
                                راهنمایی 🤔
                            </button>
                            <button class="quick-reply-btn" onclick="sendQuickReply('یه جوک بگو')">
                                جوک بگو 😄
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($chat_history as $message): ?>
                    <div class="message-item" data-message-id="<?php echo $message['id']; ?>">
                        <?php if ($message['sender'] === 'ai'): ?>
                            <div class="flex items-start message-bubble">
                                <img src="<?php echo e($ai_pic); ?>" alt="<?php echo e($ai_name); ?>" 
                                     class="w-12 h-12 rounded-full object-cover ml-3 flex-shrink-0 border-2 border-gray-200">
                                <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-4 rounded-2xl rounded-tr-none max-w-lg shadow-md">
                                    <p class="text-gray-800"><?php echo nl2br(e($message['message'])); ?></p>
                                    
                                    <?php if (!empty($message['file_path'])): ?>
                                        <div class="file-preview mt-3">
                                            <?php if (in_array(strtolower(pathinfo($message['file_name'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                                <img src="<?php echo e($message['file_path']); ?>" alt="<?php echo e($message['file_name']); ?>" class="rounded-lg">
                                            <?php else: ?>
                                                <div class="p-3 bg-gray-200 rounded-lg flex items-center">
                                                    <i class="fas fa-file ml-2"></i>
                                                    <span><?php echo e($message['file_name']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($user_settings['show_timestamps']): ?>
                                        <div class="message-timestamp">
                                            <?php echo jdate('H:i', strtotime($message['timestamp'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- عملیات پیام -->
                                    <div class="message-actions">
                                        <button class="action-btn" onclick="copyMessage(<?php echo $message['id']; ?>)" title="کپی">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <button class="action-btn" onclick="shareMessage(<?php echo $message['id']; ?>)" title="اشتراک‌گذاری">
                                            <i class="fas fa-share"></i>
                                        </button>
                                        <button class="action-btn" onclick="saveMessage(<?php echo $message['id']; ?>)" title="ذخیره">
                                            <i class="fas fa-bookmark"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- واکنش‌ها -->
                                    <div class="message-reactions" id="reactions-<?php echo $message['id']; ?>">
                                        <span class="reaction" onclick="addReaction(<?php echo $message['id']; ?>, '👍')">👍</span>
                                        <span class="reaction" onclick="addReaction(<?php echo $message['id']; ?>, '❤️')">❤️</span>
                                        <span class="reaction" onclick="addReaction(<?php echo $message['id']; ?>, '😊')">😊</span>
                                        <span class="reaction" onclick="addReaction(<?php echo $message['id']; ?>, '🤔')">🤔</span>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="flex items-start justify-end message-bubble">
                                <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white p-4 rounded-2xl rounded-bl-none max-w-lg shadow-md">
                                    <p><?php echo nl2br(e($message['message'])); ?></p>
                                    
                                    <?php if (!empty($message['file_path'])): ?>
                                        <div class="file-preview mt-3">
                                            <?php if (in_array(strtolower(pathinfo($message['file_name'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                                <img src="<?php echo e($message['file_path']); ?>" alt="<?php echo e($message['file_name']); ?>" class="rounded-lg">
                                            <?php else: ?>
                                                <div class="p-3 bg-blue-400 rounded-lg flex items-center">
                                                    <i class="fas fa-file ml-2"></i>
                                                    <span><?php echo e($message['file_name']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($user_settings['show_timestamps']): ?>
                                        <div class="message-timestamp text-blue-100">
                                            <?php echo jdate('H:i', strtotime($message['timestamp'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- عملیات پیام -->
                                    <div class="message-actions">
                                        <button class="action-btn text-white" onclick="editMessage(<?php echo $message['id']; ?>)" title="ویرایش">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn text-white" onclick="deleteMessage(<?php echo $message['id']; ?>)" title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <img src="<?php echo e($user_pic); ?>" alt="<?php echo e($user_name); ?>" 
                                     class="w-12 h-12 rounded-full object-cover mr-3 flex-shrink-0 border-2 border-blue-200">
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- نشانگر "در حال نوشتن..." پیشرفته -->
            <div id="typing-indicator" class="hidden flex items-start message-bubble">
                <img src="<?php echo e($ai_pic); ?>" alt="<?php echo e($ai_name); ?>" 
                     class="w-12 h-12 rounded-full object-cover ml-3 flex-shrink-0 border-2 border-gray-200">
                <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-4 rounded-2xl rounded-tr-none max-w-lg shadow-md">
                    <div class="flex items-center space-x-1 space-x-reverse">
                        <span class="text-sm text-gray-600 ml-2"><?php echo e($ai_name); ?> در حال نوشتن</span>
                        <div class="flex space-x-1 space-x-reverse">
                            <div class="w-2 h-2 bg-gray-400 rounded-full typing-dot"></div>
                            <div class="w-2 h-2 bg-gray-400 rounded-full typing-dot"></div>
                            <div class="w-2 h-2 bg-gray-400 rounded-full typing-dot"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- فرم ورودی پیام پیشرفته -->
        <div class="p-6 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <!-- نوار ابزار -->
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <button class="header-btn" onclick="toggleEmojiPicker()" title="اموجی">
                        <i class="fas fa-smile"></i>
                    </button>
                    <button class="header-btn" onclick="attachFile()" title="ضمیمه فایل">
                        <i class="fas fa-paperclip"></i>
                    </button>
                    <button class="header-btn" onclick="toggleVoiceRecording()" title="ضبط صدا" id="voice-btn">
                        <i class="fas fa-microphone"></i>
                    </button>
                    <button class="header-btn" onclick="takeScreenshot()" title="اسکرین‌شات">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
                
                <div class="text-sm text-gray-500">
                    <span id="char-counter">0/1000</span>
                </div>
            </div>
            
            <form id="chat-form" class="relative">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="file" id="file-input" class="hidden" accept="image/*,application/pdf,.doc,.docx,.txt" multiple>
                
                <!-- منطقه drag & drop -->
                <div id="drop-zone" class="hidden absolute inset-0 bg-blue-50 border-2 border-dashed border-blue-300 rounded-2xl flex items-center justify-center z-10">
                    <div class="text-center">
                        <i class="fas fa-cloud-upload-alt text-4xl text-blue-400 mb-2"></i>
                        <p class="text-blue-600">فایل‌ها را اینجا رها کنید</p>
                    </div>
                </div>
                
                <!-- پیش‌نمایش فایل‌های انتخاب شده -->
                <div id="file-preview" class="hidden mb-4 p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium">فایل‌های انتخاب شده:</span>
                        <button type="button" onclick="clearFiles()" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div id="file-list" class="space-y-2"></div>
                </div>
                
                <div class="relative">
                    <textarea id="message-input" name="message" 
                              placeholder="پیامت رو بنویس... (Shift + Enter برای خط جدید)" 
                              autocomplete="off" rows="1"
                              class="w-full pr-16 pl-16 py-4 bg-gray-50 dark:bg-gray-800 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none transition-all duration-200 max-h-32 overflow-y-auto"></textarea>
                    
                    <!-- دکمه ارسال -->
                    <button type="submit" id="send-btn" 
                            class="absolute left-2 bottom-2 w-12 h-12 flex items-center justify-center bg-blue-500 text-white rounded-full transition hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                    
                    <!-- نشانگر ضبط صدا -->
                    <div id="recording-indicator" class="hidden absolute right-4 bottom-4 flex items-center text-red-500">
                        <div class="w-3 h-3 bg-red-500 rounded-full mr-2 voice-recording"></div>
                        <span class="text-sm">در حال ضبط... <span id="recording-time">00:00</span></span>
                    </div>
                </div>
                
                <!-- انتخابگر اموجی -->
                <div class="emoji-picker" id="emoji-picker">
                    <div class="emoji-grid">
                        <span class="emoji-item" onclick="insertEmoji('😀')">😀</span>
                        <span class="emoji-item" onclick="insertEmoji('😃')">😃</span>
                        <span class="emoji-item" onclick="insertEmoji('😄')">😄</span>
                        <span class="emoji-item" onclick="insertEmoji('😁')">😁</span>
                        <span class="emoji-item" onclick="insertEmoji('😊')">😊</span>
                        <span class="emoji-item" onclick="insertEmoji('😍')">😍</span>
                        <span class="emoji-item" onclick="insertEmoji('🤔')">🤔</span>
                        <span class="emoji-item" onclick="insertEmoji('😢')">😢</span>
                        <span class="emoji-item" onclick="insertEmoji('😭')">😭</span>
                        <span class="emoji-item" onclick="insertEmoji('😡')">😡</span>
                        <span class="emoji-item" onclick="insertEmoji('👍')">👍</span>
                        <span class="emoji-item" onclick="insertEmoji('👎')">👎</span>
                        <span class="emoji-item" onclick="insertEmoji('❤️')">❤️</span>
                        <span class="emoji-item" onclick="insertEmoji('💔')">💔</span>
                        <span class="emoji-item" onclick="insertEmoji('🎉')">🎉</span>
                        <span class="emoji-item" onclick="insertEmoji('🔥')">🔥</span>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- صدای اعلان -->
<audio id="notification-sound" preload="auto">
    <source src="/assets/sounds/notification.mp3" type="audio/mpeg">
    <source src="/assets/sounds/notification.ogg" type="audio/ogg">
</audio>

<!-- JavaScript پیشرفته -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // متغیرهای اصلی
    const chatWindow = document.getElementById('chat-window');
    const chatForm = document.getElementById('chat-form');
    const messageInput = document.getElementById('message-input');
    const typingIndicator = document.getElementById('typing-indicator');
    const csrfTokenInput = chatForm.querySelector('input[name="csrf_token"]');
    const fileInput = document.getElementById('file-input');
    const dropZone = document.getElementById('drop-zone');
    const filePreview = document.getElementById('file-preview');
    const fileList = document.getElementById('file-list');
    const charCounter = document.getElementById('char-counter');
    const sendBtn = document.getElementById('send-btn');
    const notificationSound = document.getElementById('notification-sound');
    
    const aiPic = "<?php echo e($ai_pic); ?>";
    const aiName = "<?php echo e($ai_name); ?>";
    const userPic = "<?php echo e($user_pic); ?>";
    const userName = "<?php echo e($user_name); ?>";
    
    let selectedFiles = [];
    let isRecording = false;
    let mediaRecorder = null;
    let recordingStartTime = null;
    let typingTimer = null;
    let lastActivity = Date.now();
    
    // تنظیمات کاربر
    let settings = {
        theme: '<?php echo $user_settings['theme']; ?>',
        soundEnabled: <?php echo $user_settings['sound_enabled'] ? 'true' : 'false'; ?>,
        autoScroll: <?php echo $user_settings['auto_scroll'] ? 'true' : 'false'; ?>,
        showTimestamps: <?php echo $user_settings['show_timestamps'] ? 'true' : 'false'; ?>
    };

    // تابع برای اسکرول به پایین
    function scrollToBottom(smooth = true) {
        if (!settings.autoScroll) return;
        
        chatWindow.scrollTo({
            top: chatWindow.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto'
        });
    }

    // تابع برای پخش صدای اعلان
    function playNotificationSound() {
        if (settings.soundEnabled && notificationSound) {
            notificationSound.play().catch(e => console.log('Could not play notification sound'));
        }
    }

    // تابع برای نمایش اعلان
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    // تابع برای افزودن پیام جدید
    function addMessage(message, sender, messageId = null, fileData = null) {
        typingIndicator.classList.add('hidden');
        
        const messageWrapper = document.createElement('div');
        messageWrapper.className = 'message-item message-bubble';
        if (messageId) messageWrapper.setAttribute('data-message-id', messageId);
        
        const timestamp = settings.showTimestamps ? 
            `<div class="message-timestamp ${sender === 'user' ? 'text-blue-100' : ''}">${new Date().toLocaleTimeString('fa-IR', {hour: '2-digit', minute: '2-digit'})}</div>` : '';
        
        const fileContent = fileData ? createFilePreview(fileData) : '';
        
        if (sender === 'ai') {
            messageWrapper.innerHTML = `
                <div class="flex items-start">
                    <img src="${aiPic}" alt="${aiName}" class="w-12 h-12 rounded-full object-cover ml-3 flex-shrink-0 border-2 border-gray-200">
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-4 rounded-2xl rounded-tr-none max-w-lg shadow-md">
                        <p class="text-gray-800">${message}</p>
                        ${fileContent}
                        ${timestamp}
                        <div class="message-actions">
                            <button class="action-btn" onclick="copyMessage('${messageId}')" title="کپی">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button class="action-btn" onclick="shareMessage('${messageId}')" title="اشتراک‌گذاری">
                                <i class="fas fa-share"></i>
                            </button>
                            <button class="action-btn" onclick="saveMessage('${messageId}')" title="ذخیره">
                                <i class="fas fa-bookmark"></i>
                            </button>
                        </div>
                        <div class="message-reactions" id="reactions-${messageId}">
                            <span class="reaction" onclick="addReaction('${messageId}', '👍')">👍</span>
                            <span class="reaction" onclick="addReaction('${messageId}', '❤️')">❤️</span>
                            <span class="reaction" onclick="addReaction('${messageId}', '😊')">😊</span>
                            <span class="reaction" onclick="addReaction('${messageId}', '🤔')">🤔</span>
                        </div>
                    </div>
                </div>`;
        } else {
            messageWrapper.innerHTML = `
                <div class="flex items-start justify-end">
                    <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white p-4 rounded-2xl rounded-bl-none max-w-lg shadow-md">
                        <p>${message}</p>
                        ${fileContent}
                        ${timestamp}
                        <div class="message-actions">
                            <button class="action-btn text-white" onclick="editMessage('${messageId}')" title="ویرایش">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn text-white" onclick="deleteMessage('${messageId}')" title="حذف">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <img src="${userPic}" alt="${userName}" class="w-12 h-12 rounded-full object-cover mr-3 flex-shrink-0 border-2 border-blue-200">
                </div>`;
        }
        
        chatWindow.insertBefore(messageWrapper, typingIndicator);
        scrollToBottom();
        
        if (sender === 'ai') {
            playNotificationSound();
        }
    }

    // تابع برای ایجاد پیش‌نمایش فایل
    function createFilePreview(fileData) {
        if (fileData.type.startsWith('image/')) {
            return `<div class="file-preview mt-3">
                <img src="${fileData.url}" alt="${fileData.name}" class="rounded-lg max-w-full">
            </div>`;
        } else {
            return `<div class="file-preview mt-3">
                <div class="p-3 bg-gray-200 dark:bg-gray-600 rounded-lg flex items-center">
                    <i class="fas fa-file ml-2"></i>
                    <span>${fileData.name}</span>
                </div>
            </div>`;
        }
    }

    // تابع برای نمایش نشانگر تایپ
    function showTypingIndicator() {
        typingIndicator.classList.remove('hidden');
        scrollToBottom();
    }

    // تابع برای مخفی کردن نشانگر تایپ
    function hideTypingIndicator() {
        typingIndicator.classList.add('hidden');
    }

    // رویداد تغییر اندازه textarea
    messageInput.addEventListener('input', function() {
        // تنظیم ارتفاع خودکار
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 128) + 'px';
        
        // شمارش کاراکتر
        const length = this.value.length;
        charCounter.textContent = `${length}/1000`;
        
        if (length > 1000) {
            charCounter.classList.add('text-red-500');
            sendBtn.disabled = true;
        } else {
            charCounter.classList.remove('text-red-500');
            sendBtn.disabled = false;
        }
        
        // نمایش وضعیت تایپ
        clearTimeout(typingTimer);
        updateTypingStatus('در حال تایپ...');
        typingTimer = setTimeout(() => updateTypingStatus(''), 2000);
    });

    // تابع برای به‌روزرسانی وضعیت تایپ
    function updateTypingStatus(status) {
        const typingStatus = document.getElementById('typing-status');
        if (typingStatus) {
            typingStatus.textContent = status;
        }
    }

    // رویداد کلیدهای میانبر
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event('submit'));
        }
    });

    // رویداد ارسال فرم
    chatForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const userMessage = messageInput.value.trim();

        if (userMessage === '' && selectedFiles.length === 0) return;

        // افزودن پیام کاربر به UI
        if (userMessage) {
            addMessage(userMessage, 'user', Date.now());
        }
        
        // نمایش فایل‌های ارسالی
        if (selectedFiles.length > 0) {
            selectedFiles.forEach(file => {
                addMessage('', 'user', Date.now(), {
                    type: file.type,
                    name: file.name,
                    url: URL.createObjectURL(file)
                });
            });
        }

        messageInput.value = '';
        messageInput.style.height = 'auto';
        clearFiles();
        showTypingIndicator();

        // آماده‌سازی داده برای ارسال
        const formData = new FormData();
        formData.append('message', userMessage);
        formData.append('csrf_token', csrfTokenInput.value);
        
        selectedFiles.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });

        try {
            const response = await fetch('/api/chat_handler.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Network response was not ok.');
            }

            const result = await response.json();

            if (result.success) {
                addMessage(result.reply, 'ai', result.message_id);
                
                // به‌روزرسانی توکن CSRF
                if (result.new_csrf_token) {
                    csrfTokenInput.value = result.new_csrf_token;
                }
                
                // نمایش پیشنهادات سریع اگر وجود دارد
                if (result.quick_replies) {
                    showQuickReplies(result.quick_replies);
                }
                
            } else {
                addMessage(result.error || 'متاسفانه خطایی رخ داد.', 'ai');
                showNotification('خطا در ارسال پیام', 'error');
            }

        } catch (error) {
            console.error('Fetch error:', error);
            addMessage('مشکل در ارتباط با سرور. لطفاً اتصال اینترنت خود را بررسی کنید.', 'ai');
            showNotification('خطا در ارتباط با سرور', 'error');
        } finally {
            hideTypingIndicator();
            updateTypingStatus('');
        }
    });

    // مدیریت فایل‌ها
    fileInput.addEventListener('change', function(e) {
        handleFiles(Array.from(e.target.files));
    });

    // Drag & Drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });

    function highlight() {
        dropZone.classList.remove('hidden');
    }

    function unhighlight() {
        dropZone.classList.add('hidden');
    }

    dropZone.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = Array.from(dt.files);
        handleFiles(files);
    });

    function handleFiles(files) {
        selectedFiles = [...selectedFiles, ...files].slice(0, 5); // حداکثر 5 فایل
        updateFilePreview();
    }

    function updateFilePreview() {
        if (selectedFiles.length === 0) {
            filePreview.classList.add('hidden');
            return;
        }

        filePreview.classList.remove('hidden');
        fileList.innerHTML = '';

        selectedFiles.forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'flex items-center justify-between p-2 bg-white rounded border';
            
            let fileIcon = 'fa-file';
            if (file.type.startsWith('image/')) fileIcon = 'fa-image';
            else if (file.type.includes('pdf')) fileIcon = 'fa-file-pdf';
            else if (file.type.includes('word')) fileIcon = 'fa-file-word';
            
            fileItem.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${fileIcon} text-gray-500 ml-2"></i>
                    <span class="text-sm">${file.name}</span>
                    <span class="text-xs text-gray-400 mr-2">(${formatFileSize(file.size)})</span>
                </div>
                <button type="button" onclick="removeFile(${index})" class="text-red-500 hover:text-red-700">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            fileList.appendChild(fileItem);
        });
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // اسکرول به پایین در زمان بارگذاری
    scrollToBottom(false);

    // تابع‌های عمومی برای استفاده در HTML
    window.chatFunctions = {
        // تغییر تم
        changeTheme: function(theme) {
            settings.theme = theme;
            document.getElementById('chat-container').className = `chat-container theme-${theme}`;
            
            // ذخیره تنظیمات
            fetch('/api/save_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ chat_theme: theme })
            });
            
            showNotification(`تم به ${theme === 'dark' ? 'تاریک' : 'روشن'} تغییر یافت`);
        },

        // تنظیمات صدا
        toggleSound: function() {
            settings.soundEnabled = !settings.soundEnabled;
            document.getElementById('sound-toggle').checked = settings.soundEnabled;
            
            fetch('/api/save_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ chat_sound: settings.soundEnabled })
            });
            
            showNotification(`اعلان‌های صوتی ${settings.soundEnabled ? 'فعال' : 'غیرفعال'} شد`);
        },

        // تنظیمات اسکرول خودکار
        toggleAutoScroll: function() {
            settings.autoScroll = !settings.autoScroll;
            document.getElementById('auto-scroll-toggle').checked = settings.autoScroll;
            
            fetch('/api/save_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ chat_auto_scroll: settings.autoScroll })
            });
            
            showNotification(`اسکرول خودکار ${settings.autoScroll ? 'فعال' : 'غیرفعال'} شد`);
        },

        // تنظیمات نمایش زمان
        toggleTimestamps: function() {
            settings.showTimestamps = !settings.showTimestamps;
            document.getElementById('timestamps-toggle').checked = settings.showTimestamps;
            
            // نمایش/مخفی کردن زمان‌های موجود
            const timestamps = document.querySelectorAll('.message-timestamp');
            timestamps.forEach(ts => {
                ts.style.display = settings.showTimestamps ? 'block' : 'none';
            });
            
            fetch('/api/save_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ chat_timestamps: settings.showTimestamps })
            });
            
            showNotification(`نمایش زمان پیام‌ها ${settings.showTimestamps ? 'فعال' : 'غیرفعال'} شد`);
        }
    };

    // Event listeners برای تنظیمات
    document.querySelectorAll('.theme-btn').forEach(btn => {
        btn.addEventListener('click', () => chatFunctions.changeTheme(btn.dataset.theme));
    });

    document.getElementById('sound-toggle').addEventListener('change', chatFunctions.toggleSound);
    document.getElementById('auto-scroll-toggle').addEventListener('change', chatFunctions.toggleAutoScroll);
    document.getElementById('timestamps-toggle').addEventListener('change', chatFunctions.toggleTimestamps);
});

// تابع‌های عمومی
function openSidebar() {
    document.getElementById('settings-sidebar').classList.add('open');
    document.getElementById('sidebar-overlay').classList.add('active');
}

function closeSidebar() {
    document.getElementById('settings-sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('active');
}

function toggleSearch() {
    const searchBar = document.getElementById('search-bar');
    const liveSearch = document.getElementById('live-search');
    
    if (searchBar.classList.contains('hidden')) {
        searchBar.classList.remove('hidden');
        liveSearch.focus();
    } else {
        searchBar.classList.add('hidden');
        clearSearch();
    }
}

function performSearch() {
    const query = document.getElementById('live-search').value.trim();
    if (!query) return;
    
    const messages = document.querySelectorAll('.message-item');
    let found = 0;
    
    messages.forEach(msg => {
        const text = msg.textContent.toLowerCase();
        const highlight = text.includes(query.toLowerCase());
        
        if (highlight) {
            msg.style.display = 'block';
            // هایلایت کردن متن
            const content = msg.querySelector('p');
            if (content) {
                content.innerHTML = content.textContent.replace(
                    new RegExp(query, 'gi'),
                    match => `<span class="search-highlight">${match}</span>`
                );
            }
            found++;
        } else {
            msg.style.display = 'none';
        }
    });
    
    document.getElementById('search-results').textContent = `${found} نتیجه یافت شد`;
}

function clearSearch() {
    document.getElementById('live-search').value = '';
    document.getElementById('search-results').textContent = '';
    
    const messages = document.querySelectorAll('.message-item');
    messages.forEach(msg => {
        msg.style.display = 'block';
        const highlights = msg.querySelectorAll('.search-highlight');
        highlights.forEach(highlight => {
            highlight.outerHTML = highlight.textContent;
        });
    });
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

function toggleEmojiPicker() {
    const picker = document.getElementById('emoji-picker');
    picker.style.display = picker.style.display === 'block' ? 'none' : 'block';
}

function insertEmoji(emoji) {
    const input = document.getElementById('message-input');
    const start = input.selectionStart;
    const end = input.selectionEnd;
    const text = input.value;
    
    input.value = text.substring(0, start) + emoji + text.substring(end);
    input.focus();
    input.setSelectionRange(start + emoji.length, start + emoji.length);
    
    toggleEmojiPicker();
}

function attachFile() {
    document.getElementById('file-input').click();
}

function clearFiles() {
    selectedFiles = [];
    document.getElementById('file-preview').classList.add('hidden');
    document.getElementById('file-input').value = '';
}

function removeFile(index) {
    selectedFiles.splice(index, 1);
    updateFilePreview();
}

function sendQuickReply(message) {
    document.getElementById('message-input').value = message;
    document.getElementById('chat-form').dispatchEvent(new Event('submit'));
}

function copyMessage(messageId) {
    const message = document.querySelector(`[data-message-id="${messageId}"] p`);
    if (message) {
        navigator.clipboard.writeText(message.textContent);
        showNotification('پیام کپی شد');
    }
}

function shareMessage(messageId) {
    const message = document.querySelector(`[data-message-id="${messageId}"] p`);
    if (message && navigator.share) {
        navigator.share({
            title: 'پیام از چت ساینو',
            text: message.textContent
        });
    }
}

function saveMessage(messageId) {
    // ارسال درخواست ذخیره به سرور
    fetch('/api/save_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message_id: messageId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('پیام ذخیره شد');
        }
    });
}

function editMessage(messageId) {
    const messageElement = document.querySelector(`[data-message-id="${messageId}"] p`);
    if (messageElement) {
        const currentText = messageElement.textContent;
        const newText = prompt('ویرایش پیام:', currentText);
        
        if (newText && newText !== currentText) {
            fetch('/api/edit_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    message_id: messageId, 
                    new_message: newText 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageElement.textContent = newText;
                    showNotification('پیام ویرایش شد');
                }
            });
        }
    }
}

function deleteMessage(messageId) {
    if (confirm('آیا مطمئن هستید که می‌خواهید این پیام را حذف کنید؟')) {
        fetch('/api/delete_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message_id: messageId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelector(`[data-message-id="${messageId}"]`).remove();
                showNotification('پیام حذف شد');
            }
        });
    }
}

function addReaction(messageId, emoji) {
    const reactionContainer = document.getElementById(`reactions-${messageId}`);
    const existingReaction = reactionContainer.querySelector(`[onclick*="${emoji}"]`);
    
    if (existingReaction) {
        existingReaction.classList.toggle('active');
    }
    
    // ارسال به سرور
    fetch('/api/add_reaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            message_id: messageId, 
            emoji: emoji 
        })
    });
}

function clearChatHistory() {
    if (confirm('آیا مطمئن هستید که می‌خواهید کل تاریخچه چت را پاک کنید؟')) {
        fetch('/api/clear_chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
}

function downloadChatHistory() {
    window.open('/api/download_chat.php', '_blank');
}

// ضبط صدا
let mediaRecorder = null;
let recordingStartTime = null;

function toggleVoiceRecording() {
    if (!isRecording) {
        startRecording();
    } else {
        stopRecording();
    }
}

async function startRecording() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        
        const chunks = [];
        mediaRecorder.ondataavailable = e => chunks.push(e.data);
        
        mediaRecorder.onstop = () => {
            const blob = new Blob(chunks, { type: 'audio/wav' });
            const file = new File([blob], 'voice-message.wav', { type: 'audio/wav' });
            selectedFiles = [file];
            updateFilePreview();
        };
        
        mediaRecorder.start();
        isRecording = true;
        recordingStartTime = Date.now();
        
        document.getElementById('recording-indicator').classList.remove('hidden');
        document.getElementById('voice-btn').classList.add('voice-recording');
        
        // شروع تایمر
        const timer = setInterval(() => {
            if (!isRecording) {
                clearInterval(timer);
                return;
            }
            
            const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            document.getElementById('recording-time').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }, 1000);
        
    } catch (error) {
        console.error('Error accessing microphone:', error);
        showNotification('خطا در دسترسی به میکروفون', 'error');
    }
}

function stopRecording() {
    if (mediaRecorder && isRecording) {
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(track => track.stop());
        
        isRecording = false;
        document.getElementById('recording-indicator').classList.add('hidden');
        document.getElementById('voice-btn').classList.remove('voice-recording');
    }
}

// اسکرین‌شات
async function takeScreenshot() {
    try {
        const stream = await navigator.mediaDevices.getDisplayMedia({ video: true });
        const video = document.createElement('video');
        
        video.srcObject = stream;
        video.play();
        
        video.addEventListener('loadedmetadata', () => {
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0);
            
            canvas.toBlob(blob => {
                const file = new File([blob], 'screenshot.png', { type: 'image/png' });
                selectedFiles = [file];
                updateFilePreview();
            });
            
            stream.getTracks().forEach(track => track.stop());
        });
        
    } catch (error) {
        console.error('Error taking screenshot:', error);
        showNotification('خطا در گرفتن اسکرین‌شات', 'error');
    }
}

// بستن sidebar با کلیک روی overlay
document.getElementById('sidebar-overlay').addEventListener('click', closeSidebar);

// بستن emoji picker با کلیک خارج از آن
document.addEventListener('click', function(e) {
    const emojiPicker = document.getElementById('emoji-picker');
    const emojiBtn = document.querySelector('[onclick="toggleEmojiPicker()"]');
    
    if (!emojiPicker.contains(e.target) && e.target !== emojiBtn) {
        emojiPicker.style.display = 'none';
    }
});

// تابع کمکی برای نمایش اعلان
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 5000);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>