<?php
/**
 * فایل templates/user/chat.php
 * سیستم چت پیشرفته با دستیار هوشمند ساینو
 * 
 * نسخه: 2.0
 * قابلیت‌ها:
 * - رابط کاربری مدرن و ریسپانسیو
 * - پشتیبانی از فایل، تصویر و صدا
 * - نمایش وضعیت آنلاین/آفلاین
 * - تنظیمات شخصی‌سازی شده
 * - انیمیشن‌های روان
 * - پشتیبانی از حالت تاریک
 * - جستجو در تاریخچه
 * - امنیت بالا
 * 
 * @author Seyno Development Team
 * @version 2.0
 */

// فراخوانی فایل‌های ضروری
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session.php';

// بررسی دسترسی کاربر
if (get_user_role() !== 'user') {
    redirect('/index.php');
}

// متغیرهای اصلی
$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'کاربر';
$user_pic = $_SESSION['profile_pic_url'] ?? '/assets/images/default_avatar.png';

// دریافت تنظیمات کاربر
try {
    $preferences_stmt = $pdo->prepare("
        SELECT ai_personality, voice_enabled, notifications_enabled, 
               auto_scroll, theme, font_size, language 
        FROM user_chat_preferences 
        WHERE user_id = ?
    ");
    $preferences_stmt->execute([$user_id]);
    $preferences = $preferences_stmt->fetch(PDO::FETCH_ASSOC);
    
    // اگر تنظیمات وجود نداشت، ایجاد کن
    if (!$preferences) {
        $default_personality = ($_SESSION['gender'] === 'male') ? 'mehrdad' : 'mehrnoosh';
        $insert_pref = $pdo->prepare("
            INSERT INTO user_chat_preferences (user_id, ai_personality) VALUES (?, ?)
        ");
        $insert_pref->execute([$user_id, $default_personality]);
        
        $preferences = [
            'ai_personality' => $default_personality,
            'voice_enabled' => false,
            'notifications_enabled' => true,
            'auto_scroll' => true,
            'theme' => 'light',
            'font_size' => 'medium',
            'language' => 'fa'
        ];
    }
} catch (PDOException $e) {
    error_log('Preferences Error: ' . $e->getMessage());
    $preferences = [
        'ai_personality' => ($_SESSION['gender'] === 'male') ? 'mehrdad' : 'mehrnoosh',
        'voice_enabled' => false,
        'notifications_enabled' => true,
        'auto_scroll' => true,
        'theme' => 'light',
        'font_size' => 'medium',
        'language' => 'fa'
    ];
}

// تعیین اطلاعات دستیار هوشمند
$ai_personalities = [
    'mehrdad' => [
        'name' => 'مهرداد',
        'image' => 'https://psynoland.ir/mehrdad.webp',
        'description' => 'دستیار هوشمند مرد'
    ],
    'mehrnoosh' => [
        'name' => 'مهرنوش',
        'image' => 'https://psynoland.ir/mehnoosh.webp',
        'description' => 'دستیار هوشمند زن'
    ]
];

$current_ai = $ai_personalities[$preferences['ai_personality']];
$ai_name = $current_ai['name'];
$ai_pic = $current_ai['image'];

// خواندن تاریخچه چت
try {
    $history_stmt = $pdo->prepare("
        SELECT id, message, sender, message_type, created_at, metadata,
               ai_service, response_time, is_emergency
        FROM ai_chat_logs 
        WHERE user_id = ? AND is_deleted = FALSE 
        ORDER BY created_at ASC 
        LIMIT 100
    ");
    $history_stmt->execute([$user_id]);
    $chat_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Chat History Error: ' . $e->getMessage());
    set_flash_message('error', 'خطا در بارگذاری تاریخچه گفتگو.');
    $chat_history = [];
}

// آمار کاربر
try {
    $stats_stmt = $pdo->prepare("
        SELECT COUNT(*) as total_messages,
               SUM(CASE WHEN sender = 'user' THEN 1 ELSE 0 END) as user_messages,
               SUM(CASE WHEN sender = 'ai' THEN 1 ELSE 0 END) as ai_messages
        FROM ai_chat_logs 
        WHERE user_id = ? AND is_deleted = FALSE
    ");
    $stats_stmt->execute([$user_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total_messages' => 0, 'user_messages' => 0, 'ai_messages' => 0];
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?php echo e($preferences['theme']); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چت با <?php echo e($ai_name); ?> - ساینو</title>
    
    <!-- CSS اصلی -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- CSS سفارشی -->
    <style>
        :root {
            --primary-color: #3B82F6;
            --secondary-color: #EF4444;
            --success-color: #10B981;
            --warning-color: #F59E0B;
            --info-color: #06B6D4;
            --light-bg: #F8FAFC;
            --dark-bg: #1E293B;
            --text-primary: #1F2937;
            --text-secondary: #6B7280;
        }

        [data-theme="dark"] {
            --light-bg: #1E293B;
            --dark-bg: #0F172A;
            --text-primary: #F1F5F9;
            --text-secondary: #CBD5E1;
        }

        body {
            font-family: 'Vazir', 'Tahoma', sans-serif;
            background: var(--light-bg);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .chat-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .chat-container {
            background: var(--dark-bg);
            border: 1px solid #374151;
        }

        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            margin-bottom: 12px;
            position: relative;
            animation: slideIn 0.3s ease-out;
        }

        .message-user {
            background: linear-gradient(135deg, var(--primary-color), #60A5FA);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 6px;
        }

        .message-ai {
            background: #F3F4F6;
            color: var(--text-primary);
            border-bottom-left-radius: 6px;
        }

        [data-theme="dark"] .message-ai {
            background: #374151;
            color: var(--text-primary);
        }

        .message-emergency {
            background: linear-gradient(135deg, var(--secondary-color), #F87171) !important;
            color: white !important;
            border: 2px solid #DC2626;
            animation: pulse 2s infinite;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 8px 12px;
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            background: #9CA3AF;
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }

        @keyframes typing {
            0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
            40% { transform: scale(1.2); opacity: 1; }
        }

        .input-container {
            background: white;
            border-radius: 25px;
            padding: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .input-container {
            background: #374151;
            border: 1px solid #4B5563;
        }

        .input-field {
            border: none;
            outline: none;
            background: transparent;
            padding: 12px 16px;
            font-size: 16px;
            flex: 1;
        }

        .btn-send {
            background: linear-gradient(135deg, var(--primary-color), #60A5FA);
            color: white;
            border: none;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-send:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 15px rgba(59, 130, 246, 0.4);
        }

        .btn-send:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            position: relative;
        }

        .status-online {
            background: var(--success-color);
            animation: pulse-green 2s infinite;
        }

        @keyframes pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .font-small { font-size: 14px; }
        .font-medium { font-size: 16px; }
        .font-large { font-size: 18px; }

        .attachment-preview {
            max-width: 200px;
            border-radius: 12px;
            margin-top: 8px;
        }

        .voice-wave {
            display: flex;
            align-items: center;
            gap: 2px;
            height: 30px;
        }

        .wave-bar {
            width: 3px;
            background: var(--primary-color);
            border-radius: 2px;
            animation: wave 1s ease-in-out infinite;
        }

        .wave-bar:nth-child(1) { height: 10px; animation-delay: 0s; }
        .wave-bar:nth-child(2) { height: 20px; animation-delay: 0.1s; }
        .wave-bar:nth-child(3) { height: 15px; animation-delay: 0.2s; }
        .wave-bar:nth-child(4) { height: 25px; animation-delay: 0.3s; }
        .wave-bar:nth-child(5) { height: 12px; animation-delay: 0.4s; }

        @keyframes wave {
            0%, 100% { height: 10px; }
            50% { height: 25px; }
        }

        .modal-overlay {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            transform: translateX(400px);
            transition: all 0.3s ease;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success { background: var(--success-color); }
        .notification.error { background: var(--secondary-color); }
        .notification.warning { background: var(--warning-color); }
        .notification.info { background: var(--info-color); }

        .scroll-to-bottom {
            position: fixed;
            bottom: 100px;
            right: 30px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
            transition: all 0.3s ease;
            opacity: 0;
            visibility: hidden;
        }

        .scroll-to-bottom.show {
            opacity: 1;
            visibility: visible;
        }

        .scroll-to-bottom:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.6);
        }
    </style>
</head>

<body class="font-<?php echo e($preferences['font_size']); ?>">
    <!-- کانتینر اصلی چت -->
    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800 p-4">
        <div class="max-w-6xl mx-auto">
            <!-- هدر چت -->
            <div class="chat-container mb-4">
                <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center space-x-4 space-x-reverse">
                        <div class="relative">
                            <img src="<?php echo e($ai_pic); ?>" 
                                 alt="<?php echo e($ai_name); ?>" 
                                 class="w-16 h-16 rounded-full object-cover border-4 border-blue-200 dark:border-blue-700">
                            <div class="status-indicator status-online absolute -bottom-1 -right-1 border-2 border-white dark:border-gray-800"></div>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo e($ai_name); ?></h1>
                            <p class="text-sm text-green-500 flex items-center">
                                <span class="status-indicator status-online ml-2"></span>
                                آنلاین - آماده پاسخگویی
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <?php echo number_format($stats['total_messages']); ?> پیام تبادل شده
                            </p>
                        </div>
                    </div>
                    
                    <!-- دکمه‌های کنترل -->
                    <div class="flex items-center space-x-2 space-x-reverse">
                        <button id="search-btn" class="p-3 rounded-full bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-all">
                            <i class="fas fa-search text-gray-600 dark:text-gray-300"></i>
                        </button>
                        <button id="settings-btn" class="p-3 rounded-full bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-all">
                            <i class="fas fa-cog text-gray-600 dark:text-gray-300"></i>
                        </button>
                        <button id="clear-chat-btn" class="p-3 rounded-full bg-red-100 dark:bg-red-900 hover:bg-red-200 dark:hover:bg-red-800 transition-all">
                            <i class="fas fa-trash text-red-600 dark:text-red-400"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- پنجره چت -->
            <div class="chat-container flex flex-col" style="height: calc(100vh - 200px);">
                <!-- بدنه پیام‌ها -->
                <div id="chat-window" class="flex-1 p-6 overflow-y-auto space-y-4" style="scroll-behavior: smooth;">
                    <?php if (empty($chat_history)): ?>
                        <!-- پیام خوش‌آمدگویی -->
                        <div class="flex items-start space-x-3 space-x-reverse">
                            <img src="<?php echo e($ai_pic); ?>" alt="<?php echo e($ai_name); ?>" class="w-12 h-12 rounded-full object-cover flex-shrink-0">
                            <div class="message-bubble message-ai">
                                <div class="flex items-center mb-2">
                                    <span class="font-semibold text-sm"><?php echo e($ai_name); ?></span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 mr-2">الان</span>
                                </div>
                                <p>سلام <?php echo e(explode(' ', $user_name)[0]); ?> عزیز! 👋</p>
                                <p class="mt-2">من <?php echo e($ai_name); ?> هستم، دستیار هوشمند شما در ساینو. خوشحالم که اینجا هستی.</p>
                                <p class="mt-2">چطور می‌تونم کمکت کنم؟ 😊</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($chat_history as $message): ?>
                            <div class="flex items-start space-x-3 space-x-reverse <?php echo $message['sender'] === 'user' ? 'justify-end' : ''; ?>">
                                <?php if ($message['sender'] === 'ai'): ?>
                                    <img src="<?php echo e($ai_pic); ?>" alt="<?php echo e($ai_name); ?>" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                                <?php endif; ?>
                                
                                <div class="message-bubble <?php echo $message['sender'] === 'user' ? 'message-user' : 'message-ai'; ?> <?php echo $message['is_emergency'] ? 'message-emergency' : ''; ?>">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-semibold text-sm">
                                            <?php echo $message['sender'] === 'user' ? 'شما' : e($ai_name); ?>
                                        </span>
                                        <span class="text-xs opacity-70">
                                            <?php echo jdate('H:i', strtotime($message['created_at'])); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($message['message_type'] === 'text'): ?>
                                        <div class="message-content">
                                            <?php echo nl2br(e($message['message'])); ?>
                                        </div>
                                    <?php elseif ($message['message_type'] === 'image'): ?>
                                        <img src="<?php echo e($message['message']); ?>" alt="تصویر" class="attachment-preview">
                                    <?php elseif ($message['message_type'] === 'voice'): ?>
                                        <div class="voice-wave">
                                            <div class="wave-bar"></div>
                                            <div class="wave-bar"></div>
                                            <div class="wave-bar"></div>
                                            <div class="wave-bar"></div>
                                            <div class="wave-bar"></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($message['sender'] === 'ai' && $message['ai_service']): ?>
                                        <div class="text-xs opacity-50 mt-2">
                                            <i class="fas fa-robot ml-1"></i>
                                            <?php echo e($message['ai_service']); ?>
                                            <?php if ($message['response_time']): ?>
                                                • <?php echo number_format($message['response_time'], 2); ?>s
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($message['sender'] === 'user'): ?>
                                    <img src="<?php echo e($user_pic); ?>" alt="شما" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- نشانگر در حال تایپ -->
                    <div id="typing-indicator" class="hidden flex items-start space-x-3 space-x-reverse">
                        <img src="<?php echo e($ai_pic); ?>" alt="<?php echo e($ai_name); ?>" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                        <div class="message-bubble message-ai">
                            <div class="typing-indicator">
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- فرم ورودی -->
                <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                    <form id="chat-form" class="space-y-3">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <!-- نوار ابزار -->
                        <div class="flex items-center space-x-2 space-x-reverse">
                            <button type="button" id="attach-file" class="p-2 rounded-full bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-all">
                                <i class="fas fa-paperclip text-gray-600 dark:text-gray-300"></i>
                            </button>
                            <button type="button" id="attach-image" class="p-2 rounded-full bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-all">
                                <i class="fas fa-image text-gray-600 dark:text-gray-300"></i>
                            </button>
                            <button type="button" id="voice-record" class="p-2 rounded-full bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-all">
                                <i class="fas fa-microphone text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                        
                        <!-- فیلد ورودی -->
                        <div class="input-container flex items-center">
                            <input type="text" 
                                   id="message-input" 
                                   name="message" 
                                   placeholder="پیامت رو بنویس..." 
                                   autocomplete="off" 
                                   class="input-field"
                                   maxlength="2000">
                            <button type="submit" id="send-btn" class="btn-send">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                        
                        <!-- نمایش تعداد کاراکتر -->
                        <div class="text-xs text-gray-500 dark:text-gray-400 text-left">
                            <span id="char-count">0</span>/2000
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- دکمه اسکرول به پایین -->
    <button id="scroll-to-bottom" class="scroll-to-bottom">
        <i class="fas fa-chevron-down"></i>
    </button>

    <!-- فایل‌های مخفی برای آپلود -->
    <input type="file" id="file-input" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif" style="display: none;">
    <input type="file" id="image-input" accept="image/*" style="display: none;">

    <!-- مودال تنظیمات -->
    <div id="settings-modal" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 max-w-md w-full mx-4 transform transition-all">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-800 dark:text-white">تنظیمات چت</h3>
                <button id="close-settings" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-all">
                    <i class="fas fa-times text-gray-600 dark:text-gray-300"></i>
                </button>
            </div>
            
            <div class="space-y-4">
                <!-- انتخاب دستیار -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">دستیار هوشمند</label>
                    <select id="ai-personality" class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                        <option value="mehrdad" <?php echo $preferences['ai_personality'] === 'mehrdad' ? 'selected' : ''; ?>>مهرداد (مرد)</option>
                        <option value="mehrnoosh" <?php echo $preferences['ai_personality'] === 'mehrnoosh' ? 'selected' : ''; ?>>مهرنوش (زن)</option>
                    </select>
                </div>
                
                <!-- حالت تاریک -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">حالت نمایش</label>
                    <select id="theme-select" class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                        <option value="light" <?php echo $preferences['theme'] === 'light' ? 'selected' : ''; ?>>روشن</option>
                        <option value="dark" <?php echo $preferences['theme'] === 'dark' ? 'selected' : ''; ?>>تاریک</option>
                        <option value="auto" <?php echo $preferences['theme'] === 'auto' ? 'selected' : ''; ?>>خودکار</option>
                    </select>
                </div>
                
                <!-- اندازه فونت -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">اندازه متن</label>
                    <select id="font-size" class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                        <option value="small" <?php echo $preferences['font_size'] === 'small' ? 'selected' : ''; ?>>کوچک</option>
                        <option value="medium" <?php echo $preferences['font_size'] === 'medium' ? 'selected' : ''; ?>>متوسط</option>
                        <option value="large" <?php echo $preferences['font_size'] === 'large' ? 'selected' : ''; ?>>بزرگ</option>
                    </select>
                </div>
                
                <!-- سوئیچ‌ها -->
                <div class="space-y-3">
                    <label class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">اسکرول خودکار</span>
                        <input type="checkbox" id="auto-scroll" <?php echo $preferences['auto_scroll'] ? 'checked' : ''; ?> class="toggle-switch">
                    </label>
                    
                    <label class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">اعلان‌ها</span>
                        <input type="checkbox" id="notifications" <?php echo $preferences['notifications_enabled'] ? 'checked' : ''; ?> class="toggle-switch">
                    </label>
                    
                    <label class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">پشتیبانی صوتی</span>
                        <input type="checkbox" id="voice-enabled" <?php echo $preferences['voice_enabled'] ? 'checked' : ''; ?> class="toggle-switch">
                    </label>
                </div>
            </div>
            
            <div class="flex justify-end mt-6 space-x-2 space-x-reverse">
                <button id="save-settings" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-all">
                    ذخیره
                </button>
            </div>
        </div>
    </div>

    <!-- اسکریپت‌های جاوا اسکریپت -->
    <script>
        // متغیرهای سراسری
        const chatWindow = document.getElementById('chat-window');
        const chatForm = document.getElementById('chat-form');
        const messageInput = document.getElementById('message-input');
        const sendBtn = document.getElementById('send-btn');
        const typingIndicator = document.getElementById('typing-indicator');
        const charCount = document.getElementById('char-count');
        const scrollToBottomBtn = document.getElementById('scroll-to-bottom');
        const csrfTokenInput = chatForm.querySelector('input[name="csrf_token"]');
        
        // اطلاعات AI
        const aiData = {
            name: "<?php echo e($ai_name); ?>",
            image: "<?php echo e($ai_pic); ?>"
        };
        
        // تنظیمات کاربر
        let userPreferences = <?php echo json_encode($preferences); ?>;
        
        // تابع اسکرول به پایین
        function scrollToBottom(smooth = true) {
            if (userPreferences.auto_scroll) {
                chatWindow.scrollTo({
                    top: chatWindow.scrollHeight,
                    behavior: smooth ? 'smooth' : 'auto'
                });
            }
        }
        
        // تابع افزودن پیام جدید
        function addMessage(message, sender, messageType = 'text', metadata = null) {
            const messageWrapper = document.createElement('div');
            const timestamp = new Date().toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
            const isUser = sender === 'user';
            
            messageWrapper.className = `flex items-start space-x-3 space-x-reverse ${isUser ? 'justify-end' : ''}`;
            
            let avatarImg = '';
            if (!isUser) {
                avatarImg = `<img src="${aiData.image}" alt="${aiData.name}" class="w-10 h-10 rounded-full object-cover flex-shrink-0">`;
            }
            
            let messageContent = '';
            if (messageType === 'text') {
                messageContent = `<div class="message-content">${message.replace(/\n/g, '<br>')}</div>`;
            } else if (messageType === 'image') {
                messageContent = `<img src="${message}" alt="تصویر" class="attachment-preview">`;
            }
            
            messageWrapper.innerHTML = `
                ${!isUser ? avatarImg : ''}
                <div class="message-bubble ${isUser ? 'message-user' : 'message-ai'}">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-semibold text-sm">${isUser ? 'شما' : aiData.name}</span>
                        <span class="text-xs opacity-70">${timestamp}</span>
                    </div>
                    ${messageContent}
                </div>
                ${isUser ? `<img src="<?php echo e($user_pic); ?>" alt="شما" class="w-10 h-10 rounded-full object-cover flex-shrink-0">` : ''}
            `;
            
            // حذف نشانگر تایپ قبل از افزودن پیام جدید
            typingIndicator.classList.add('hidden');
            chatWindow.insertBefore(messageWrapper, typingIndicator);
            scrollToBottom();
            
            // اعلان در صورت فعال بودن
            if (userPreferences.notifications_enabled && sender === 'ai' && 'Notification' in window) {
                if (Notification.permission === 'granted') {
                    new Notification(`پیام جدید از ${aiData.name}`, {
                        body: message.substring(0, 100) + (message.length > 100 ? '...' : ''),
                        icon: aiData.image
                    });
                }
            }
        }
        
        // تابع نمایش نشانگر تایپ
        function showTypingIndicator() {
            typingIndicator.classList.remove('hidden');
            scrollToBottom();
        }
        
        // تابع مخفی کردن نشانگر تایپ
        function hideTypingIndicator() {
            typingIndicator.classList.add('hidden');
        }
        
        // تابع نمایش اعلان
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => notification.classList.add('show'), 100);
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
        
        // رویداد ارسال فرم
        chatForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const userMessage = messageInput.value.trim();
            if (!userMessage) return;
            
            // غیرفعال کردن فرم
            sendBtn.disabled = true;
            messageInput.disabled = true;
            
            // افزودن پیام کاربر
            addMessage(userMessage, 'user');
            messageInput.value = '';
            charCount.textContent = '0';
            
            // نمایش نشانگر تایپ
            showTypingIndicator();
            
            // آماده‌سازی داده
            const formData = new FormData();
            formData.append('message', userMessage);
            formData.append('csrf_token', csrfTokenInput.value);
            
            try {
                const startTime = Date.now();
                const response = await fetch('/api/chat_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                const responseTime = ((Date.now() - startTime) / 1000).toFixed(2);
                
                if (result.success) {
                    // افزودن پاسخ AI
                    addMessage(result.reply, 'ai', 'text', { responseTime });
                    
                    // به‌روزرسانی توکن CSRF
                    if (result.new_csrf_token) {
                        csrfTokenInput.value = result.new_csrf_token;
                    }
                    
                    // بررسی پیام اضطراری
                    if (result.is_emergency) {
                        showNotification('پیام اضطراری شناسایی شد. لطفاً با متخصص تماس بگیرید.', 'warning');
                    }
                } else {
                    addMessage(result.error || 'متاسفانه خطایی رخ داد.', 'ai');
                    showNotification('خطا در ارسال پیام', 'error');
                }
                
            } catch (error) {
                console.error('Chat Error:', error);
                addMessage('مشکل در ارتباط با سرور. لطفاً اتصال اینترنت خود را بررسی کنید.', 'ai');
                showNotification('خطا در ارتباط با سرور', 'error');
            } finally {
                // فعال کردن مجدد فرم
                sendBtn.disabled = false;
                messageInput.disabled = false;
                messageInput.focus();
                hideTypingIndicator();
            }
        });
        
        // شمارش کاراکتر
        messageInput.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = length;
            
            if (length > 1800) {
                charCount.style.color = '#EF4444';
            } else if (length > 1500) {
                charCount.style.color = '#F59E0B';
            } else {
                charCount.style.color = '#6B7280';
            }
        });
        
        // نمایش/مخفی کردن دکمه اسکرول
        chatWindow.addEventListener('scroll', function() {
            const isAtBottom = chatWindow.scrollHeight - chatWindow.scrollTop <= chatWindow.clientHeight + 100;
            scrollToBottomBtn.classList.toggle('show', !isAtBottom);
        });
        
        // کلیک روی دکمه اسکرول
        scrollToBottomBtn.addEventListener('click', () => scrollToBottom());
        
        // مدیریت فایل‌ها
        document.getElementById('attach-file').addEventListener('click', () => {
            document.getElementById('file-input').click();
        });
        
        document.getElementById('attach-image').addEventListener('click', () => {
            document.getElementById('image-input').click();
        });
        
        // مدیریت تنظیمات
        const settingsModal = document.getElementById('settings-modal');
        const settingsBtn = document.getElementById('settings-btn');
        const closeSettingsBtn = document.getElementById('close-settings');
        const saveSettingsBtn = document.getElementById('save-settings');
        
        settingsBtn.addEventListener('click', () => {
            settingsModal.classList.remove('hidden');
            settingsModal.classList.add('flex');
        });
        
        closeSettingsBtn.addEventListener('click', () => {
            settingsModal.classList.add('hidden');
            settingsModal.classList.remove('flex');
        });
        
        // ذخیره تنظیمات
        saveSettingsBtn.addEventListener('click', async () => {
            const newPreferences = {
                ai_personality: document.getElementById('ai-personality').value,
                theme: document.getElementById('theme-select').value,
                font_size: document.getElementById('font-size').value,
                auto_scroll: document.getElementById('auto-scroll').checked,
                notifications_enabled: document.getElementById('notifications').checked,
                voice_enabled: document.getElementById('voice-enabled').checked
            };
            
            try {
                const response = await fetch('/api/update_preferences.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(newPreferences)
                });
                
                if (response.ok) {
                    userPreferences = { ...userPreferences, ...newPreferences };
                    
                    // اعمال تنظیمات
                    document.documentElement.setAttribute('data-theme', newPreferences.theme);
                    document.body.className = `font-${newPreferences.font_size}`;
                    
                    showNotification('تنظیمات ذخیره شد', 'success');
                    settingsModal.classList.add('hidden');
                    settingsModal.classList.remove('flex');
                    
                    // بارگذاری مجدد در صورت تغییر دستیار
                    if (newPreferences.ai_personality !== userPreferences.ai_personality) {
                        setTimeout(() => location.reload(), 1000);
                    }
                } else {
                    throw new Error('خطا در ذخیره تنظیمات');
                }
            } catch (error) {
                showNotification('خطا در ذخیره تنظیمات', 'error');
            }
        });
        
        // درخواست اجازه اعلان
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
        
        // اسکرول اولیه
        setTimeout(() => scrollToBottom(false), 100);
        
        // فوکوس روی ورودی
        messageInput.focus();
        
        // کلیدهای میانبر
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + Enter برای ارسال
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                chatForm.dispatchEvent(new Event('submit'));
            }
            
            // Escape برای بستن مودال‌ها
            if (e.key === 'Escape') {
                settingsModal.classList.add('hidden');
                settingsModal.classList.remove('flex');
            }
        });
    </script>
</body>
</html>