# سیستم چت پیشرفته ساینو - نسخه 2.0

## معرفی
سیستم چت پیشرفته با دستیار هوشمند ساینو که شامل قابلیت‌های مدرن و امنیت بالا می‌باشد.

## ویژگی‌های کلیدی

### 🎨 رابط کاربری
- **طراحی مدرن و ریسپانسیو** با Tailwind CSS
- **حالت تاریک/روشن** قابل تنظیم
- **انیمیشن‌های روان** و تعاملی
- **پشتیبانی از RTL** برای زبان فارسی
- **نمایش وضعیت آنلاین/آفلاین**

### 🤖 هوش مصنوعی
- **پشتیبانی از چندین سرویس AI:**
  - Google Gemini Pro
  - Google Gemini 1.5 Flash
  - BigModel GLM-4.5 Flash
  - OpenAI ChatGPT
  - DeepSeek AI
- **شخصیت‌های مختلف:** مهرداد (مرد) و مهرنوش (زن)
- **پرامپت سیستمی هوشمند** و شخصی‌سازی شده

### 🔒 امنیت
- **محافظت CSRF** با توکن‌های امنیتی
- **محدودیت نرخ درخواست** (30/دقیقه، 200/ساعت)
- **اعتبارسنجی ورودی** و پاکسازی داده‌ها
- **تشخیص اسپم** و محتوای مشکوک
- **لاگ‌گذاری امنیتی** کامل

### 🚨 سیستم اضطراری
- **تشخیص خودکار کلمات کلیدی اضطراری**
- **پاسخ‌های اضطراری** با شماره‌های کمکی
- **اعلان به مدیران** در موارد بحرانی
- **ثبت آمار هشدارها**

### 📊 آنالیز و آمار
- **ردیابی آمار گفتگو** روزانه
- **زمان پاسخ** و عملکرد سیستم
- **آنالیز استفاده** کاربران
- **گزارش‌های تفصیلی**

### 🎵 قابلیت‌های چندرسانه‌ای
- **ضبط و ارسال پیام صوتی**
- **آپلود تصاویر** (JPEG, PNG, GIF, WebP)
- **آپلود فایل‌ها** (PDF, DOC, DOCX, TXT)
- **پیش‌نمایش رسانه** قبل از ارسال

### 🔍 ابزارهای پیشرفته
- **جستجو در تاریخچه** با هایلایت
- **کلیدهای میانبر** (Ctrl+K, Ctrl+L, Ctrl+Enter)
- **پاک کردن چت** با تایید
- **تنظیمات شخصی‌سازی** کامل

## ساختار فایل‌ها

```
📁 Chat System/
├── 📄 chat.php                    # صفحه اصلی چت
├── 📄 chat_handler.php            # API مدیریت چت
├── 📄 update_preferences.php      # API تنظیمات کاربر
├── 📄 clear_chat.php              # API پاک کردن چت
├── 📄 chat_utils.js               # ابزارهای JavaScript
├── 📄 database_chat_upgrade.sql   # اسکریپت بهبود دیتابیس
└── 📄 README_CHAT_SYSTEM.md       # مستندات سیستم
```

## نصب و راه‌اندازی

### 1. بهبود دیتابیس
```sql
-- اجرای اسکریپت SQL
mysql -u username -p database_name < database_chat_upgrade.sql
```

### 2. تنظیم فایل‌ها
```bash
# کپی فایل‌ها به مسیرهای مناسب
cp chat.php templates/user/
cp chat_handler.php api/
cp update_preferences.php api/
cp clear_chat.php api/
cp chat_utils.js assets/js/
```

### 3. تنظیمات API
در جدول `settings` کلیدهای API مورد نیاز را اضافه کنید:

```sql
INSERT INTO settings (setting_key, setting_value) VALUES
('active_ai_service', 'gemini'),
('gemini_api_key', 'YOUR_GEMINI_API_KEY'),
('gemini_1_5_flash_api_key', 'YOUR_GEMINI_FLASH_KEY'),
('bigmodel_glm4_5_flash_api_key', 'YOUR_BIGMODEL_KEY'),
('chatgpt_api_key', 'YOUR_OPENAI_KEY'),
('deepseek_api_key', 'YOUR_DEEPSEEK_KEY');
```

### 4. تنظیمات وب سرور
```apache
# Apache .htaccess
RewriteEngine On
RewriteRule ^api/(.*)$ api/$1 [L]

# محدودیت حجم آپلود
php_value upload_max_filesize 10M
php_value post_max_size 10M
```

## استفاده

### 1. شروع چت
```php
// در صفحه chat.php
require_once 'chat.php';
```

### 2. ارسال پیام
```javascript
// JavaScript API
const response = await fetch('/api/chat_handler.php', {
    method: 'POST',
    body: formData
});
```

### 3. تنظیمات کاربر
```javascript
// به‌روزرسانی تنظیمات
const preferences = {
    ai_personality: 'mehrdad',
    theme: 'dark',
    font_size: 'large'
};

await fetch('/api/update_preferences.php', {
    method: 'POST',
    body: JSON.stringify(preferences)
});
```

## API Reference

### Chat Handler
**Endpoint:** `POST /api/chat_handler.php`

**Parameters:**
- `message` (string): متن پیام کاربر
- `csrf_token` (string): توکن امنیتی

**Response:**
```json
{
    "success": true,
    "reply": "پاسخ دستیار هوشمند",
    "is_emergency": false,
    "response_time": 1250.5,
    "new_csrf_token": "new_token_here"
}
```

### Update Preferences
**Endpoint:** `POST /api/update_preferences.php`

**Parameters:**
```json
{
    "ai_personality": "mehrdad|mehrnoosh",
    "theme": "light|dark|auto",
    "font_size": "small|medium|large",
    "auto_scroll": true,
    "notifications_enabled": true,
    "voice_enabled": false
}
```

### Clear Chat
**Endpoint:** `POST /api/clear_chat.php`

**Parameters:**
```json
{
    "csrf_token": "security_token"
}
```

## امنیت و محدودیت‌ها

### محدودیت‌های نرخ
- **30 درخواست در دقیقه**
- **200 درخواست در ساعت**
- **حداکثر 2000 کاراکتر در پیام**
- **حداکثر 10MB برای فایل‌ها**

### کلمات کلیدی اضطراری
```php
[
    'خودکشی', 'بکشم', 'مرگ', 'بمیرم', 'خطر', 'آسیب',
    'درد شدید', 'افسردگی شدید', 'تهدید', 'خشونت',
    'suicide', 'kill', 'death', 'die', 'harm', 'hurt'
]
```

### شماره‌های اضطراری
- **اورژانس اجتماعی:** 123
- **خط مشاوره بحران:** 1480
- **اورژانس پزشکی:** 115

## مانیتورینگ و لاگ‌ها

### فایل‌های لاگ
```bash
/var/log/seyno/emergency.log    # هشدارهای اضطراری
/var/log/apache2/error.log      # خطاهای سیستم
```

### آمار عملکرد
```sql
-- مشاهده آمار روزانه
SELECT * FROM chat_analytics 
WHERE user_id = ? AND date = CURDATE();

-- آمار کلی سیستم
SELECT 
    COUNT(*) as total_messages,
    AVG(response_time) as avg_response_time,
    COUNT(CASE WHEN is_emergency THEN 1 END) as emergency_count
FROM ai_chat_logs 
WHERE DATE(created_at) = CURDATE();
```

## عیب‌یابی

### مشکلات رایج

1. **خطای 403 در Gemini API:**
   ```bash
   # بررسی کلید API و URL
   curl -H "Content-Type: application/json" \
        "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=YOUR_API_KEY"
   ```

2. **خطای محدودیت نرخ:**
   ```sql
   -- بررسی تعداد درخواست‌ها
   SELECT COUNT(*) FROM ai_chat_logs 
   WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 HOUR;
   ```

3. **مشکل آپلود فایل:**
   ```php
   // بررسی تنظیمات PHP
   echo "Max upload: " . ini_get('upload_max_filesize') . "\n";
   echo "Max post: " . ini_get('post_max_size') . "\n";
   ```

### بهینه‌سازی عملکرد

1. **ایندکس‌گذاری دیتابیس:**
   ```sql
   SHOW INDEX FROM ai_chat_logs;
   ANALYZE TABLE ai_chat_logs;
   ```

2. **کش کردن تنظیمات:**
   ```php
   // استفاده از Redis یا Memcached
   $redis->setex("user_preferences_{$user_id}", 3600, $preferences);
   ```

3. **بهینه‌سازی تصاویر:**
   ```bash
   # نصب ImageMagick
   sudo apt-get install imagemagick php-imagick
   ```

## به‌روزرسانی و نگهداری

### بک‌آپ دیتابیس
```bash
# بک‌آپ روزانه
mysqldump -u username -p database_name \
    ai_chat_logs chat_sessions user_chat_preferences chat_analytics \
    > backup_$(date +%Y%m%d).sql
```

### پاکسازی داده‌های قدیمی
```sql
-- حذف پیام‌های قدیمی‌تر از 6 ماه
DELETE FROM ai_chat_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH) 
AND is_deleted = TRUE;
```

### مانیتورینگ سیستم
```bash
# بررسی وضعیت سرویس‌ها
systemctl status apache2
systemctl status mysql

# بررسی فضای دیسک
df -h /var/log/seyno/
```

## پشتیبانی

برای گزارش باگ یا درخواست ویژگی جدید:
- **ایمیل:** support@seyno.app
- **تلگرام:** @SeynoSupport

## لایسنس

این سیستم تحت لایسنس اختصاصی ساینو توسعه یافته است.

---

**تاریخ به‌روزرسانی:** 2024  
**نسخه:** 2.0  
**توسعه‌دهنده:** Seyno Development Team