<?php
/**
 * فایل api/chat_handler.php
 * سیستم مدیریت چت پیشرفته با دستیار هوشمند ساینو
 * 
 * نسخه: 2.0
 * قابلیت‌ها:
 * - پشتیبانی از چندین سرویس AI
 * - سیستم امنیتی پیشرفته
 * - مدیریت خطا و لاگ‌گذاری
 * - بهینه‌سازی عملکرد
 * - پشتیبانی از انواع پیام
 * - سیستم اعلان اضطراری
 * - آنالیز احساسات
 * - محدودیت نرخ درخواست
 * 
 * @author Seyno Development Team
 * @version 2.0
 * @since 2024
 */

// تنظیم هدر خروجی
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// فراخوانی فایل‌های ضروری
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// کلاس اصلی مدیریت چت
class ChatHandler {
    private $pdo;
    private $user_id;
    private $response;
    private $start_time;
    private $rate_limiter;
    
    // تنظیمات سیستم
    private const MAX_MESSAGE_LENGTH = 2000;
    private const MAX_REQUESTS_PER_MINUTE = 30;
    private const MAX_REQUESTS_PER_HOUR = 200;
    private const EMERGENCY_KEYWORDS = [
        'خودکشی', 'بکشم', 'مرگ', 'بمیرم', 'خطر', 'آسیب', 'درد شدید',
        'افسردگی شدید', 'تهدید', 'خشونت', 'ضرب', 'کتک', 'اذیت',
        'suicide', 'kill', 'death', 'die', 'harm', 'hurt', 'pain'
    ];
    
    private const AI_SERVICES = [
        'gemini' => 'Google Gemini Pro',
        'gemini_1_5_flash' => 'Google Gemini 1.5 Flash',
        'bigmodel_glm4_5_flash' => 'BigModel GLM-4.5 Flash',
        'chatgpt' => 'OpenAI ChatGPT',
        'deepseek' => 'DeepSeek AI'
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->start_time = microtime(true);
        $this->response = [
            'success' => false,
            'error' => 'درخواست نامعتبر.',
            'timestamp' => date('c'),
            'request_id' => uniqid('req_', true)
        ];
        
        $this->initializeRateLimiter();
    }
    
    /**
     * تابع اصلی پردازش درخواست
     */
    public function handleRequest() {
        try {
            // 1. بررسی‌های اولیه امنیتی
            if (!$this->validateRequest()) {
                return $this->sendResponse();
            }
            
            // 2. بررسی محدودیت نرخ درخواست
            if (!$this->checkRateLimit()) {
                $this->response['error'] = 'تعداد درخواست‌های شما از حد مجاز گذشته است. لطفاً کمی صبر کنید.';
                return $this->sendResponse();
            }
            
            // 3. پردازش پیام کاربر
            $user_message = $this->sanitizeInput($_POST['message'] ?? '');
            if (!$this->validateMessage($user_message)) {
                return $this->sendResponse();
            }
            
            // 4. ذخیره پیام کاربر
            $message_id = $this->saveUserMessage($user_message);
            if (!$message_id) {
                $this->response['error'] = 'خطا در ذخیره پیام. لطفاً مجدداً تلاش کنید.';
                return $this->sendResponse();
            }
            
            // 5. تشخیص شرایط اضطراری
            $is_emergency = $this->detectEmergency($user_message);
            
            // 6. تولید پاسخ
            $ai_reply = '';
            if ($is_emergency) {
                $ai_reply = $this->generateEmergencyResponse($user_message);
                $this->logEmergencyAlert($user_message);
            } else {
                $ai_reply = $this->generateAIResponse($user_message);
            }
            
            // 7. ذخیره پاسخ AI
            $this->saveAIMessage($ai_reply, $is_emergency);
            
            // 8. آماده‌سازی پاسخ نهایی
            $this->response['success'] = true;
            $this->response['reply'] = $this->processMessageContent($ai_reply);
            $this->response['is_emergency'] = $is_emergency;
            $this->response['response_time'] = round((microtime(true) - $this->start_time) * 1000, 2);
            unset($this->response['error']);
            
            // 9. به‌روزرسانی آمار
            $this->updateUserStats();
            
        } catch (Exception $e) {
            $this->logError('Chat Handler Exception', $e);
            $this->response['error'] = 'خطای داخلی سرور. لطفاً دوباره تلاش کنید.';
        } catch (Throwable $t) {
            $this->logError('Chat Handler Fatal Error', $t);
            $this->response['error'] = 'خطای بحرانی در سرور.';
        }
        
        return $this->sendResponse();
    }
    
    /**
     * بررسی اعتبار درخواست
     */
    private function validateRequest(): bool {
        // بررسی متد درخواست
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response['error'] = 'متد درخواست نامعتبر است.';
            return false;
        }
        
        // بررسی ورود کاربر
        if (!is_logged_in()) {
            $this->response['error'] = 'لطفاً ابتدا وارد سیستم شوید.';
            return false;
        }
        
        // بررسی نقش کاربر
        if (get_user_role() !== 'user') {
            $this->response['error'] = 'دسترسی غیرمجاز.';
            return false;
        }
        
        // بررسی توکن CSRF
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            $this->response['error'] = 'توکن امنیتی نامعتبر است.';
            return false;
        }
        
        // تنظیم شناسه کاربر
        $this->user_id = (int)$_SESSION['user_id'];
        
        return true;
    }
    
    /**
     * بررسی محدودیت نرخ درخواست
     */
    private function checkRateLimit(): bool {
        try {
            // بررسی درخواست‌های دقیقه اخیر
            $minute_stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM ai_chat_logs 
                WHERE user_id = ? AND sender = 'user' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            ");
            $minute_stmt->execute([$this->user_id]);
            $minute_count = $minute_stmt->fetchColumn();
            
            if ($minute_count >= self::MAX_REQUESTS_PER_MINUTE) {
                $this->logRateLimitExceeded('minute', $minute_count);
                return false;
            }
            
            // بررسی درخواست‌های ساعت اخیر
            $hour_stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM ai_chat_logs 
                WHERE user_id = ? AND sender = 'user' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $hour_stmt->execute([$this->user_id]);
            $hour_count = $hour_stmt->fetchColumn();
            
            if ($hour_count >= self::MAX_REQUESTS_PER_HOUR) {
                $this->logRateLimitExceeded('hour', $hour_count);
                return false;
            }
            
            return true;
            
        } catch (PDOException $e) {
            $this->logError('Rate Limit Check Error', $e);
            return true; // در صورت خطا، اجازه ادامه بده
        }
    }
    
    /**
     * پاکسازی و اعتبارسنجی ورودی
     */
    private function sanitizeInput(string $input): string {
        // حذف فضاهای اضافی
        $input = trim($input);
        
        // حذف کاراکترهای کنترلی
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        // محدود کردن طول
        if (mb_strlen($input, 'UTF-8') > self::MAX_MESSAGE_LENGTH) {
            $input = mb_substr($input, 0, self::MAX_MESSAGE_LENGTH, 'UTF-8');
        }
        
        return $input;
    }
    
    /**
     * اعتبارسنجی پیام
     */
    private function validateMessage(string $message): bool {
        if (empty($message)) {
            $this->response['error'] = 'پیام نمی‌تواند خالی باشد.';
            return false;
        }
        
        if (mb_strlen($message, 'UTF-8') < 2) {
            $this->response['error'] = 'پیام باید حداقل ۲ کاراکتر باشد.';
            return false;
        }
        
        if (mb_strlen($message, 'UTF-8') > self::MAX_MESSAGE_LENGTH) {
            $this->response['error'] = 'پیام نمی‌تواند بیش از ' . self::MAX_MESSAGE_LENGTH . ' کاراکتر باشد.';
            return false;
        }
        
        // بررسی اسپم
        if ($this->isSpam($message)) {
            $this->response['error'] = 'پیام شما به عنوان اسپم شناسایی شد.';
            return false;
        }
        
        return true;
    }
    
    /**
     * تشخیص اسپم
     */
    private function isSpam(string $message): bool {
        // بررسی تکرار کاراکتر
        if (preg_match('/(.)\1{10,}/', $message)) {
            return true;
        }
        
        // بررسی لینک‌های مشکوک
        if (preg_match('/https?:\/\/[^\s]+/i', $message)) {
            // بررسی دامنه‌های مجاز
            $allowed_domains = ['psynoland.ir', 'seyno.app'];
            preg_match_all('/https?:\/\/([^\/\s]+)/i', $message, $matches);
            foreach ($matches[1] as $domain) {
                $is_allowed = false;
                foreach ($allowed_domains as $allowed) {
                    if (strpos($domain, $allowed) !== false) {
                        $is_allowed = true;
                        break;
                    }
                }
                if (!$is_allowed) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * ذخیره پیام کاربر
     */
    private function saveUserMessage(string $message): ?int {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO ai_chat_logs 
                (user_id, message, sender, message_type, metadata) 
                VALUES (?, ?, 'user', 'text', ?)
            ");
            
            $metadata = json_encode([
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'timestamp' => time(),
                'request_id' => $this->response['request_id']
            ]);
            
            $stmt->execute([$this->user_id, $message, $metadata]);
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            $this->logError('Save User Message Error', $e);
            return null;
        }
    }
    
    /**
     * تشخیص شرایط اضطراری
     */
    private function detectEmergency(string $message): bool {
        $message_lower = mb_strtolower($message, 'UTF-8');
        
        foreach (self::EMERGENCY_KEYWORDS as $keyword) {
            if (strpos($message_lower, $keyword) !== false) {
                return true;
            }
        }
        
        // تشخیص الگوهای اضطراری پیشرفته
        $emergency_patterns = [
            '/می\s*خوام\s*(بمیرم|خودکشی\s*کنم)/',
            '/نمی\s*تونم\s*زندگی\s*کنم/',
            '/دیگه\s*نمی\s*تونم/',
            '/خیلی\s*درد\s*دارم/',
            '/کمک\s*کنید/'
        ];
        
        foreach ($emergency_patterns as $pattern) {
            if (preg_match($pattern, $message_lower)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * تولید پاسخ اضطراری
     */
    private function generateEmergencyResponse(string $message): string {
        $emergency_responses = [
            "من متوجه شدم که در شرایط بسیار سختی قرار داری و این واقعاً نگران‌کننده است. 😟\n\n" .
            "🚨 لطفاً فوراً با این شماره‌ها تماس بگیر:\n" .
            "• اورژانس اجتماعی: ۱۲۳\n" .
            "• خط مشاوره بحران: ۱۴۸۰\n" .
            "• اورژانس پزشکی: ۱۱۵\n\n" .
            "تو تنها نیستی و کمک در دسترس است. متخصصان آموزش دیده‌ای هستند که می‌تونند بهت کمک کنن. 💙",
            
            "احساس می‌کنم که الان در موقعیت خطرناکی هستی. این خیلی جدی است و من نگرانتم. 😰\n\n" .
            "🆘 لطفاً همین الان این کارها رو انجام بده:\n" .
            "۱. با یکی از نزدیکانت تماس بگیر\n" .
            "۲. با اورژانس اجتماعی (۱۲۳) صحبت کن\n" .
            "۳. به نزدیک‌ترین مرکز درمانی برو\n\n" .
            "یادت باشه که این احساسات موقتی هستند و راه‌حل وجود داره. 🌟"
        ];
        
        return $emergency_responses[array_rand($emergency_responses)];
    }
    
    /**
     * تولید پاسخ هوش مصنوعی
     */
    private function generateAIResponse(string $user_message): string {
        // دریافت سرویس فعال
        $active_service = $this->getActiveSetting('active_ai_service', 'gemini');
        
        // دریافت تنظیمات کاربر
        $user_preferences = $this->getUserPreferences();
        
        // آماده‌سازی پرامپت سیستمی
        $system_prompt = $this->buildSystemPrompt($user_preferences);
        
        // دریافت تاریخچه چت
        $chat_history = $this->getChatHistory(25); // افزایش به 25 پیام
        
        // فراخوانی سرویس مناسب
        switch ($active_service) {
            case 'bigmodel_glm4_5_flash':
                return $this->callBigModelAPI($system_prompt, $chat_history, $user_message);
                
            case 'gemini_1_5_flash':
                return $this->callGeminiAPI($system_prompt, $chat_history, $user_message, 'gemini-1.5-flash-latest');
                
            case 'chatgpt':
                return $this->callChatGPTAPI($system_prompt, $chat_history, $user_message);
                
            case 'deepseek':
                return $this->callDeepSeekAPI($system_prompt, $chat_history, $user_message);
                
            case 'gemini':
            default:
                return $this->callGeminiAPI($system_prompt, $chat_history, $user_message, 'gemini-pro');
        }
    }
    
    /**
     * ساخت پرامپت سیستمی
     */
    private function buildSystemPrompt(array $preferences): string {
        $user_name = $_SESSION['full_name'] ?? 'کاربر';
        $first_name = explode(' ', $user_name)[0] ?? $user_name;
        $ai_name = ($preferences['ai_personality'] === 'mehrdad') ? 'مهرداد' : 'مهرنوش';
        
        $current_time = jdate('Y/m/d H:i');
        
        return "تو یک دستیار هوش مصنوعی پیشرفته و حرفه‌ای به نام '{$ai_name}' در اپلیکیشن 'ساینو' هستی. " .
               "با کاربری به نام '{$first_name}' در تاریخ {$current_time} صحبت می‌کنی.\n\n" .
               
               "ویژگی‌های شخصیت تو:\n" .
               "• لحن دوستانه، صمیمی و حمایتگر (از 'تو' استفاده کن)\n" .
               "• همدل، صبور و فهمیده\n" .
               "• علمی و دقیق در ارائه اطلاعات\n" .
               "• خلاق و انگیزه‌بخش\n\n" .
               
               "قوانین مهم:\n" .
               "• هرگز تشخیص پزشکی ندهی یا دارو تجویز نکنی\n" .
               "• در موارد اضطراری، به مراکز تخصصی ارجاع بده\n" .
               "• پاسخ‌هایت رو کوتاه و مفید نگه دار (حداکثر ۳ پاراگراف)\n" .
               "• از ایموجی مناسب استفاده کن\n" .
               "• تاریخچه مکالمه رو به خاطر داشته باش\n" .
               "• همیشه احترام و حریم شخصی رو رعایت کن\n\n" .
               
               "هدف اصلی تو ایجاد فضای امن، حمایتگر و بدون قضاوت برای '{$first_name}' است.";
    }
    
    /**
     * دریافت تنظیمات کاربر
     */
    private function getUserPreferences(): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ai_personality, voice_enabled, language, theme
                FROM user_chat_preferences 
                WHERE user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$preferences) {
                return [
                    'ai_personality' => ($_SESSION['gender'] === 'male') ? 'mehrdad' : 'mehrnoosh',
                    'voice_enabled' => false,
                    'language' => 'fa',
                    'theme' => 'light'
                ];
            }
            
            return $preferences;
            
        } catch (PDOException $e) {
            $this->logError('Get User Preferences Error', $e);
            return [
                'ai_personality' => 'mehrdad',
                'voice_enabled' => false,
                'language' => 'fa',
                'theme' => 'light'
            ];
        }
    }
    
    /**
     * دریافت تاریخچه چت
     */
    private function getChatHistory(int $limit = 20): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT message, sender, created_at
                FROM ai_chat_logs 
                WHERE user_id = ? AND is_deleted = FALSE 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$this->user_id, $limit]);
            return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
            
        } catch (PDOException $e) {
            $this->logError('Get Chat History Error', $e);
            return [];
        }
    }
    
    /**
     * فراخوانی API BigModel
     */
    private function callBigModelAPI(string $system_prompt, array $history, string $new_message): string {
        $api_key = $this->getActiveSetting('bigmodel_glm4_5_flash_api_key');
        if (empty($api_key)) {
            return "خطا: کلید API برای BigModel GLM-4.5 Flash تنظیم نشده است.";
        }
        
        $api_url = 'https://open.bigmodel.cn/api/paas/v4/chat/completions';
        
        // ساختار پیام‌ها
        $messages = [['role' => 'system', 'content' => $system_prompt]];
        foreach ($history as $msg) {
            $messages[] = [
                'role' => ($msg['sender'] === 'user') ? 'user' : 'assistant',
                'content' => $msg['message']
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $new_message];
        
        $payload = [
            'model' => 'glm-4.5-flash',
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1000,
            'top_p' => 0.9
        ];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ];
        
        $response = $this->sendCurlRequest($api_url, json_encode($payload), $headers);
        if (!$response) {
            return "متاسفانه ارتباط با سرویس BigModel برقرار نشد.";
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError('BigModel JSON Decode Error', new Exception(json_last_error_msg()));
            return "پاسخ نامعتبر از BigModel دریافت شد.";
        }
        
        if (isset($result['error'])) {
            $this->logError('BigModel API Error', new Exception(print_r($result['error'], true)));
            return "خطا در دریافت پاسخ از BigModel: " . ($result['error']['message'] ?? 'خطای نامشخص');
        }
        
        return $result['choices'][0]['message']['content'] ?? "پاسخی از BigModel دریافت نشد.";
    }
    
    /**
     * فراخوانی API Gemini
     */
    private function callGeminiAPI(string $system_prompt, array $history, string $new_message, string $model = 'gemini-pro'): string {
        $api_key_name = ($model === 'gemini-1.5-flash-latest') ? 'gemini_1_5_flash_api_key' : 'gemini_api_key';
        $api_key = $this->getActiveSetting($api_key_name);
        
        if (empty($api_key)) {
            return "خطا: کلید API برای مدل {$model} تنظیم نشده است.";
        }
        
        $api_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $api_key;
        
        // ساختار پیام‌ها برای Gemini
        $contents = [];
        
        // اضافه کردن system prompt
        $contents[] = ['role' => 'user', 'parts' => [['text' => $system_prompt]]];
        $contents[] = ['role' => 'model', 'parts' => [['text' => 'متوجه شدم. من آماده کمک به شما هستم.']]];
        
        foreach ($history as $msg) {
            $contents[] = [
                'role' => ($msg['sender'] === 'user') ? 'user' : 'model',
                'parts' => [['text' => $msg['message']]]
            ];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $new_message]]];
        
        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.9,
                'maxOutputTokens' => 1000
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ]
            ]
        ];
        
        $response = $this->sendCurlRequest($api_url, json_encode($payload), ['Content-Type: application/json']);
        if (!$response) {
            return "متاسفانه ارتباط با سرویس Gemini ({$model}) برقرار نشد.";
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError('Gemini JSON Decode Error', new Exception(json_last_error_msg()));
            return "پاسخ نامعتبر از Gemini ({$model}) دریافت شد.";
        }
        
        if (isset($result['error'])) {
            $this->logError('Gemini API Error', new Exception(print_r($result['error'], true)));
            return "خطا در دریافت پاسخ از Gemini ({$model}): " . ($result['error']['message'] ?? 'خطای نامشخص');
        }
        
        return $result['candidates'][0]['content']['parts'][0]['text'] ?? "پاسخی از Gemini ({$model}) دریافت نشد.";
    }
    
    /**
     * فراخوانی API ChatGPT
     */
    private function callChatGPTAPI(string $system_prompt, array $history, string $new_message): string {
        $api_key = $this->getActiveSetting('chatgpt_api_key');
        if (empty($api_key)) {
            return "خطا: کلید API برای ChatGPT تنظیم نشده است.";
        }
        
        $api_url = 'https://api.openai.com/v1/chat/completions';
        
        // ساختار پیام‌ها
        $messages = [['role' => 'system', 'content' => $system_prompt]];
        foreach ($history as $msg) {
            $messages[] = [
                'role' => ($msg['sender'] === 'user') ? 'user' : 'assistant',
                'content' => $msg['message']
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $new_message];
        
        $payload = [
            'model' => 'gpt-3.5-turbo',
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1000,
            'top_p' => 0.9,
            'frequency_penalty' => 0.1,
            'presence_penalty' => 0.1
        ];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ];
        
        $response = $this->sendCurlRequest($api_url, json_encode($payload), $headers);
        if (!$response) {
            return "متاسفانه ارتباط با سرویس ChatGPT برقرار نشد.";
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError('ChatGPT JSON Decode Error', new Exception(json_last_error_msg()));
            return "پاسخ نامعتبر از ChatGPT دریافت شد.";
        }
        
        if (isset($result['error'])) {
            $this->logError('ChatGPT API Error', new Exception(print_r($result['error'], true)));
            return "خطا در دریافت پاسخ از ChatGPT: " . ($result['error']['message'] ?? 'خطای نامشخص');
        }
        
        return $result['choices'][0]['message']['content'] ?? "پاسخی از ChatGPT دریافت نشد.";
    }
    
    /**
     * فراخوانی API DeepSeek
     */
    private function callDeepSeekAPI(string $system_prompt, array $history, string $new_message): string {
        $api_key = $this->getActiveSetting('deepseek_api_key');
        if (empty($api_key)) {
            return "خطا: کلید API برای DeepSeek تنظیم نشده است.";
        }
        
        $api_url = 'https://api.deepseek.com/v1/chat/completions';
        
        // ساختار پیام‌ها
        $messages = [['role' => 'system', 'content' => $system_prompt]];
        foreach ($history as $msg) {
            $messages[] = [
                'role' => ($msg['sender'] === 'user') ? 'user' : 'assistant',
                'content' => $msg['message']
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $new_message];
        
        $payload = [
            'model' => 'deepseek-chat',
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1000,
            'top_p' => 0.9
        ];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ];
        
        $response = $this->sendCurlRequest($api_url, json_encode($payload), $headers);
        if (!$response) {
            return "متاسفانه ارتباط با سرویس DeepSeek برقرار نشد.";
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError('DeepSeek JSON Decode Error', new Exception(json_last_error_msg()));
            return "پاسخ نامعتبر از DeepSeek دریافت شد.";
        }
        
        if (isset($result['error'])) {
            $this->logError('DeepSeek API Error', new Exception(print_r($result['error'], true)));
            return "خطا در دریافت پاسخ از DeepSeek: " . ($result['error']['message'] ?? 'خطای نامشخص');
        }
        
        return $result['choices'][0]['message']['content'] ?? "پاسخی از DeepSeek دریافت نشد.";
    }
    
    /**
     * ارسال درخواست cURL بهینه شده
     */
    private function sendCurlRequest(string $url, string $payload, array $headers = []): ?string {
        $ch = curl_init($url);
        
        // تنظیمات اصلی
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'SeynoApp/2.0 (Chat Handler)',
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        if ($error) {
            $this->logError('cURL Error for ' . $url, new Exception($error));
            curl_close($ch);
            return null;
        }
        
        if ($http_code !== 200) {
            $this->logError("HTTP Error {$http_code} for {$url}", new Exception($response));
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        return $response;
    }
    
    /**
     * ذخیره پاسخ AI
     */
    private function saveAIMessage(string $message, bool $is_emergency = false): void {
        try {
            $active_service = $this->getActiveSetting('active_ai_service', 'gemini');
            $response_time = round((microtime(true) - $this->start_time), 3);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO ai_chat_logs 
                (user_id, message, sender, message_type, ai_service, response_time, is_emergency, metadata) 
                VALUES (?, ?, 'ai', 'text', ?, ?, ?, ?)
            ");
            
            $metadata = json_encode([
                'service_used' => self::AI_SERVICES[$active_service] ?? $active_service,
                'response_time_ms' => round($response_time * 1000, 2),
                'emergency_detected' => $is_emergency,
                'timestamp' => time(),
                'request_id' => $this->response['request_id']
            ]);
            
            $stmt->execute([
                $this->user_id,
                $message,
                $active_service,
                $response_time,
                $is_emergency,
                $metadata
            ]);
            
        } catch (PDOException $e) {
            $this->logError('Save AI Message Error', $e);
        }
    }
    
    /**
     * پردازش محتوای پیام
     */
    private function processMessageContent(string $content): string {
        // تبدیل مارک‌داون ساده به HTML
        $content = $this->simpleMarkdownToHtml($content);
        
        // پاکسازی HTML
        $content = htmlspecialchars_decode($content, ENT_QUOTES);
        
        return $content;
    }
    
    /**
     * تبدیل مارک‌داون ساده به HTML
     */
    private function simpleMarkdownToHtml(string $text): string {
        // بولد
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        
        // ایتالیک
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
        
        // کد
        $text = preg_replace('/`(.*?)`/', '<code>$1</code>', $text);
        
        // لینک
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);
        
        // خط جدید
        $text = nl2br($text);
        
        return $text;
    }
    
    /**
     * به‌روزرسانی آمار کاربر
     */
    private function updateUserStats(): void {
        try {
            $today = date('Y-m-d');
            $response_time = round((microtime(true) - $this->start_time), 3);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO chat_analytics 
                (user_id, date, total_messages, total_ai_responses, avg_response_time, active_minutes)
                VALUES (?, ?, 1, 1, ?, 1)
                ON DUPLICATE KEY UPDATE
                    total_messages = total_messages + 1,
                    total_ai_responses = total_ai_responses + 1,
                    avg_response_time = (avg_response_time + ?) / 2,
                    active_minutes = active_minutes + 1,
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([$this->user_id, $today, $response_time, $response_time]);
            
        } catch (PDOException $e) {
            $this->logError('Update User Stats Error', $e);
        }
    }
    
    /**
     * ثبت هشدار اضطراری
     */
    private function logEmergencyAlert(string $message): void {
        try {
            // ثبت در جدول آمار
            $today = date('Y-m-d');
            $stmt = $this->pdo->prepare("
                INSERT INTO chat_analytics 
                (user_id, date, emergency_alerts)
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    emergency_alerts = emergency_alerts + 1,
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$this->user_id, $today]);
            
            // ثبت در لاگ سیستم
            error_log("EMERGENCY ALERT - User ID: {$this->user_id}, Message: " . substr($message, 0, 100));
            
            // ارسال اعلان به مدیران (در صورت نیاز)
            $this->notifyAdminsEmergency($message);
            
        } catch (PDOException $e) {
            $this->logError('Log Emergency Alert Error', $e);
        }
    }
    
    /**
     * اعلان اضطراری به مدیران
     */
    private function notifyAdminsEmergency(string $message): void {
        // این قسمت می‌تواند شامل ارسال ایمیل، SMS یا اعلان به پنل مدیریت باشد
        // برای امنیت، فقط اطلاعات محدود ارسال می‌شود
        
        $alert_data = [
            'user_id' => $this->user_id,
            'timestamp' => date('c'),
            'severity' => 'high',
            'type' => 'emergency_keyword_detected',
            'request_id' => $this->response['request_id']
        ];
        
        // ثبت در فایل لاگ ویژه
        error_log('EMERGENCY_ALERT: ' . json_encode($alert_data), 3, '/var/log/seyno/emergency.log');
    }
    
    /**
     * دریافت تنظیمات فعال
     */
    private function getActiveSetting(string $key, string $default = ''): string {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetchColumn();
            return $result ?: $default;
            
        } catch (PDOException $e) {
            $this->logError('Get Active Setting Error', $e);
            return $default;
        }
    }
    
    /**
     * ثبت محدودیت نرخ
     */
    private function logRateLimitExceeded(string $period, int $count): void {
        error_log("RATE_LIMIT_EXCEEDED - User ID: {$this->user_id}, Period: {$period}, Count: {$count}");
    }
    
    /**
     * مقداردهی اولیه محدودکننده نرخ
     */
    private function initializeRateLimiter(): void {
        // این تابع می‌تواند برای پیاده‌سازی سیستم پیشرفته‌تر محدودیت نرخ استفاده شود
        // مثل Redis یا Memcached
    }
    
    /**
     * ثبت خطا
     */
    private function logError(string $context, $error): void {
        $error_data = [
            'context' => $context,
            'user_id' => $this->user_id ?? 'unknown',
            'timestamp' => date('c'),
            'request_id' => $this->response['request_id'],
            'error' => $error instanceof Exception ? $error->getMessage() : (string)$error,
            'trace' => $error instanceof Exception ? $error->getTraceAsString() : ''
        ];
        
        error_log('CHAT_ERROR: ' . json_encode($error_data, JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * ارسال پاسخ نهایی
     */
    private function sendResponse(): void {
        // تولید توکن CSRF جدید
        $this->response['new_csrf_token'] = generate_csrf_token();
        
        // اضافه کردن اطلاعات اضافی
        $this->response['server_time'] = date('c');
        $this->response['processing_time'] = round((microtime(true) - $this->start_time) * 1000, 2) . 'ms';
        
        // ارسال پاسخ
        echo json_encode($this->response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }
}

// اجرای کلاس اصلی
try {
    $chatHandler = new ChatHandler($pdo);
    $chatHandler->handleRequest();
} catch (Exception $e) {
    error_log('Chat Handler Fatal Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'خطای داخلی سرور.',
        'new_csrf_token' => function_exists('generate_csrf_token') ? generate_csrf_token() : '',
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $t) {
    error_log('Chat Handler Critical Error: ' . $t->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'خطای بحرانی سرور.',
        'new_csrf_token' => function_exists('generate_csrf_token') ? generate_csrf_token() : '',
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
}
?>