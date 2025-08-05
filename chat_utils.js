/**
 * فایل assets/js/chat_utils.js
 * ابزارهای جانبی برای سیستم چت پیشرفته
 * 
 * @version 2.0
 * @author Seyno Development Team
 */

class ChatUtils {
    constructor() {
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.isRecording = false;
        this.supportedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        this.supportedFileTypes = ['.pdf', '.doc', '.docx', '.txt', '.rtf'];
        this.maxFileSize = 10 * 1024 * 1024; // 10MB
        
        this.initializeEventListeners();
    }
    
    /**
     * مقداردهی اولیه رویدادها
     */
    initializeEventListeners() {
        // رویداد ضبط صدا
        const voiceBtn = document.getElementById('voice-record');
        if (voiceBtn) {
            voiceBtn.addEventListener('click', () => this.toggleVoiceRecording());
        }
        
        // رویداد آپلود فایل
        const fileInput = document.getElementById('file-input');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => this.handleFileUpload(e));
        }
        
        // رویداد آپلود تصویر
        const imageInput = document.getElementById('image-input');
        if (imageInput) {
            imageInput.addEventListener('change', (e) => this.handleImageUpload(e));
        }
        
        // رویداد پاک کردن چت
        const clearBtn = document.getElementById('clear-chat-btn');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => this.clearChatConfirm());
        }
        
        // رویداد جستجو
        const searchBtn = document.getElementById('search-btn');
        if (searchBtn) {
            searchBtn.addEventListener('click', () => this.openSearchModal());
        }
        
        // کلیدهای میانبر
        document.addEventListener('keydown', (e) => this.handleKeyboardShortcuts(e));
        
        // تشخیص حالت آفلاین/آنلاین
        window.addEventListener('online', () => this.updateConnectionStatus(true));
        window.addEventListener('offline', () => this.updateConnectionStatus(false));
    }
    
    /**
     * تغییر وضعیت ضبط صدا
     */
    async toggleVoiceRecording() {
        if (!this.isRecording) {
            await this.startVoiceRecording();
        } else {
            this.stopVoiceRecording();
        }
    }
    
    /**
     * شروع ضبط صدا
     */
    async startVoiceRecording() {
        try {
            // درخواست دسترسی به میکروفون
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            
            this.mediaRecorder = new MediaRecorder(stream);
            this.audioChunks = [];
            
            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    this.audioChunks.push(event.data);
                }
            };
            
            this.mediaRecorder.onstop = () => {
                this.processAudioRecording();
            };
            
            this.mediaRecorder.start();
            this.isRecording = true;
            
            // تغییر ظاهر دکمه
            const voiceBtn = document.getElementById('voice-record');
            voiceBtn.innerHTML = '<i class="fas fa-stop text-red-500"></i>';
            voiceBtn.classList.add('bg-red-100', 'dark:bg-red-900');
            
            this.showNotification('ضبط صدا شروع شد', 'info');
            
        } catch (error) {
            console.error('Voice recording error:', error);
            this.showNotification('خطا در دسترسی به میکروفون', 'error');
        }
    }
    
    /**
     * توقف ضبط صدا
     */
    stopVoiceRecording() {
        if (this.mediaRecorder && this.isRecording) {
            this.mediaRecorder.stop();
            this.isRecording = false;
            
            // بازگرداندن ظاهر دکمه
            const voiceBtn = document.getElementById('voice-record');
            voiceBtn.innerHTML = '<i class="fas fa-microphone text-gray-600 dark:text-gray-300"></i>';
            voiceBtn.classList.remove('bg-red-100', 'dark:bg-red-900');
            
            // توقف stream
            this.mediaRecorder.stream.getTracks().forEach(track => track.stop());
        }
    }
    
    /**
     * پردازش فایل صوتی ضبط شده
     */
    processAudioRecording() {
        const audioBlob = new Blob(this.audioChunks, { type: 'audio/wav' });
        
        // تبدیل به URL موقت برای پخش
        const audioUrl = URL.createObjectURL(audioBlob);
        
        // نمایش پیش‌نمایش صدا
        this.showAudioPreview(audioUrl, audioBlob);
    }
    
    /**
     * نمایش پیش‌نمایش صدا
     */
    showAudioPreview(audioUrl, audioBlob) {
        const modal = this.createModal('پیش‌نمایش پیام صوتی', `
            <div class="text-center p-4">
                <audio controls class="w-full mb-4">
                    <source src="${audioUrl}" type="audio/wav">
                </audio>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    مدت زمان: ${this.formatDuration(audioBlob.size)} | حجم: ${this.formatFileSize(audioBlob.size)}
                </p>
                <div class="flex justify-center space-x-4 space-x-reverse">
                    <button id="send-audio" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-all">
                        ارسال
                    </button>
                    <button id="cancel-audio" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all">
                        لغو
                    </button>
                </div>
            </div>
        `);
        
        // رویدادها
        modal.querySelector('#send-audio').addEventListener('click', () => {
            this.sendAudioMessage(audioBlob);
            this.closeModal(modal);
        });
        
        modal.querySelector('#cancel-audio').addEventListener('click', () => {
            URL.revokeObjectURL(audioUrl);
            this.closeModal(modal);
        });
    }
    
    /**
     * ارسال پیام صوتی
     */
    async sendAudioMessage(audioBlob) {
        try {
            const formData = new FormData();
            formData.append('audio', audioBlob, 'voice_message.wav');
            formData.append('type', 'voice');
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            
            const response = await fetch('/api/upload_media.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // افزودن پیام صوتی به چت
                this.addVoiceMessage(result.file_url, 'user');
                this.showNotification('پیام صوتی ارسال شد', 'success');
            } else {
                throw new Error(result.error || 'خطا در آپلود فایل صوتی');
            }
            
        } catch (error) {
            console.error('Send audio error:', error);
            this.showNotification('خطا در ارسال پیام صوتی', 'error');
        }
    }
    
    /**
     * مدیریت آپلود فایل
     */
    handleFileUpload(event) {
        const file = event.target.files[0];
        if (!file) return;
        
        // بررسی حجم فایل
        if (file.size > this.maxFileSize) {
            this.showNotification('حجم فایل نباید بیش از ۱۰ مگابایت باشد', 'error');
            return;
        }
        
        // بررسی نوع فایل
        const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
        if (!this.supportedFileTypes.includes(fileExtension)) {
            this.showNotification('نوع فایل پشتیبانی نمی‌شود', 'error');
            return;
        }
        
        this.uploadFile(file, 'file');
    }
    
    /**
     * مدیریت آپلود تصویر
     */
    handleImageUpload(event) {
        const file = event.target.files[0];
        if (!file) return;
        
        // بررسی نوع تصویر
        if (!this.supportedImageTypes.includes(file.type)) {
            this.showNotification('نوع تصویر پشتیبانی نمی‌شود', 'error');
            return;
        }
        
        // بررسی حجم
        if (file.size > this.maxFileSize) {
            this.showNotification('حجم تصویر نباید بیش از ۱۰ مگابایت باشد', 'error');
            return;
        }
        
        this.uploadFile(file, 'image');
    }
    
    /**
     * آپلود فایل
     */
    async uploadFile(file, type) {
        try {
            // نمایش نوار پیشرفت
            const progressModal = this.showProgressModal('در حال آپلود...');
            
            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', type);
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            
            const response = await fetch('/api/upload_media.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            this.closeModal(progressModal);
            
            if (result.success) {
                if (type === 'image') {
                    this.addImageMessage(result.file_url, file.name, 'user');
                } else {
                    this.addFileMessage(result.file_url, file.name, file.size, 'user');
                }
                this.showNotification('فایل با موفقیت آپلود شد', 'success');
            } else {
                throw new Error(result.error || 'خطا در آپلود فایل');
            }
            
        } catch (error) {
            console.error('Upload error:', error);
            this.showNotification('خطا در آپلود فایل', 'error');
        }
    }
    
    /**
     * پاک کردن چت با تایید
     */
    clearChatConfirm() {
        const modal = this.createModal('پاک کردن چت', `
            <div class="text-center p-4">
                <div class="text-red-500 text-6xl mb-4">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3 class="text-lg font-bold mb-2">آیا مطمئن هستید؟</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    تمام پیام‌های چت شما حذف خواهد شد و این عمل قابل بازگشت نیست.
                </p>
                <div class="flex justify-center space-x-4 space-x-reverse">
                    <button id="confirm-clear" class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-all">
                        بله، پاک کن
                    </button>
                    <button id="cancel-clear" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all">
                        لغو
                    </button>
                </div>
            </div>
        `);
        
        modal.querySelector('#confirm-clear').addEventListener('click', () => {
            this.clearChat();
            this.closeModal(modal);
        });
        
        modal.querySelector('#cancel-clear').addEventListener('click', () => {
            this.closeModal(modal);
        });
    }
    
    /**
     * پاک کردن چت
     */
    async clearChat() {
        try {
            const response = await fetch('/api/clear_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: document.querySelector('input[name="csrf_token"]').value
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // پاک کردن پیام‌ها از UI
                const chatWindow = document.getElementById('chat-window');
                const messages = chatWindow.querySelectorAll('.flex.items-start:not(#typing-indicator)');
                messages.forEach(msg => msg.remove());
                
                this.showNotification('چت پاک شد', 'success');
            } else {
                throw new Error(result.error || 'خطا در پاک کردن چت');
            }
            
        } catch (error) {
            console.error('Clear chat error:', error);
            this.showNotification('خطا در پاک کردن چت', 'error');
        }
    }
    
    /**
     * باز کردن مودال جستجو
     */
    openSearchModal() {
        const modal = this.createModal('جستجو در چت', `
            <div class="p-4">
                <div class="mb-4">
                    <input type="text" id="search-input" placeholder="متن مورد نظر را وارد کنید..." 
                           class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                </div>
                <div class="flex justify-between items-center mb-4">
                    <div class="flex space-x-2 space-x-reverse">
                        <button id="search-prev" class="p-2 bg-gray-200 dark:bg-gray-700 rounded disabled:opacity-50">
                            <i class="fas fa-chevron-up"></i>
                        </button>
                        <button id="search-next" class="p-2 bg-gray-200 dark:bg-gray-700 rounded disabled:opacity-50">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <span id="search-results" class="text-sm text-gray-600 dark:text-gray-400">۰ نتیجه</span>
                </div>
                <div class="flex justify-end space-x-2 space-x-reverse">
                    <button id="close-search" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all">
                        بستن
                    </button>
                </div>
            </div>
        `);
        
        const searchInput = modal.querySelector('#search-input');
        searchInput.addEventListener('input', (e) => this.performSearch(e.target.value));
        searchInput.focus();
        
        modal.querySelector('#close-search').addEventListener('click', () => {
            this.clearSearchHighlights();
            this.closeModal(modal);
        });
    }
    
    /**
     * انجام جستجو
     */
    performSearch(query) {
        this.clearSearchHighlights();
        
        if (query.length < 2) {
            document.getElementById('search-results').textContent = '۰ نتیجه';
            return;
        }
        
        const chatWindow = document.getElementById('chat-window');
        const messages = chatWindow.querySelectorAll('.message-content');
        let matches = 0;
        
        messages.forEach(message => {
            const text = message.textContent;
            if (text.toLowerCase().includes(query.toLowerCase())) {
                this.highlightText(message, query);
                matches++;
            }
        });
        
        document.getElementById('search-results').textContent = `${matches} نتیجه`;
    }
    
    /**
     * هایلایت متن
     */
    highlightText(element, query) {
        const text = element.innerHTML;
        const highlightedText = text.replace(
            new RegExp(query, 'gi'),
            `<mark class="bg-yellow-300 dark:bg-yellow-600">$&</mark>`
        );
        element.innerHTML = highlightedText;
        element.classList.add('search-highlighted');
    }
    
    /**
     * پاک کردن هایلایت‌های جستجو
     */
    clearSearchHighlights() {
        const highlighted = document.querySelectorAll('.search-highlighted');
        highlighted.forEach(element => {
            element.innerHTML = element.textContent;
            element.classList.remove('search-highlighted');
        });
    }
    
    /**
     * کلیدهای میانبر
     */
    handleKeyboardShortcuts(event) {
        // Ctrl/Cmd + K برای جستجو
        if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
            event.preventDefault();
            this.openSearchModal();
        }
        
        // Ctrl/Cmd + L برای پاک کردن چت
        if ((event.ctrlKey || event.metaKey) && event.key === 'l') {
            event.preventDefault();
            this.clearChatConfirm();
        }
    }
    
    /**
     * به‌روزرسانی وضعیت اتصال
     */
    updateConnectionStatus(isOnline) {
        const statusElements = document.querySelectorAll('.status-indicator');
        statusElements.forEach(element => {
            if (isOnline) {
                element.classList.remove('bg-red-500');
                element.classList.add('bg-green-500', 'status-online');
            } else {
                element.classList.remove('bg-green-500', 'status-online');
                element.classList.add('bg-red-500');
            }
        });
        
        const statusText = document.querySelector('.status-indicator').nextElementSibling;
        if (statusText) {
            statusText.textContent = isOnline ? 'آنلاین - آماده پاسخگویی' : 'آفلاین';
        }
        
        this.showNotification(
            isOnline ? 'اتصال برقرار شد' : 'اتصال قطع شد',
            isOnline ? 'success' : 'warning'
        );
    }
    
    /**
     * افزودن پیام صوتی
     */
    addVoiceMessage(audioUrl, sender) {
        // این تابع باید با تابع addMessage اصلی ادغام شود
        console.log('Voice message added:', audioUrl, sender);
    }
    
    /**
     * افزودن پیام تصویری
     */
    addImageMessage(imageUrl, fileName, sender) {
        // این تابع باید با تابع addMessage اصلی ادغام شود
        console.log('Image message added:', imageUrl, fileName, sender);
    }
    
    /**
     * افزودن پیام فایل
     */
    addFileMessage(fileUrl, fileName, fileSize, sender) {
        // این تابع باید با تابع addMessage اصلی ادغام شود
        console.log('File message added:', fileUrl, fileName, fileSize, sender);
    }
    
    /**
     * ایجاد مودال
     */
    createModal(title, content) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 modal-overlay flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full mx-4 transform transition-all">
                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">${title}</h3>
                    <button class="modal-close p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-all">
                        <i class="fas fa-times text-gray-600 dark:text-gray-300"></i>
                    </button>
                </div>
                <div class="modal-content">${content}</div>
            </div>
        `;
        
        // رویداد بستن
        modal.querySelector('.modal-close').addEventListener('click', () => {
            this.closeModal(modal);
        });
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.closeModal(modal);
            }
        });
        
        document.body.appendChild(modal);
        return modal;
    }
    
    /**
     * بستن مودال
     */
    closeModal(modal) {
        modal.remove();
    }
    
    /**
     * نمایش مودال پیشرفت
     */
    showProgressModal(message) {
        return this.createModal('در حال پردازش...', `
            <div class="text-center p-6">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-4"></div>
                <p class="text-gray-600 dark:text-gray-400">${message}</p>
            </div>
        `);
    }
    
    /**
     * نمایش اعلان
     */
    showNotification(message, type = 'info') {
        // این تابع باید با تابع showNotification اصلی ادغام شود
        console.log('Notification:', message, type);
    }
    
    /**
     * فرمت کردن مدت زمان
     */
    formatDuration(size) {
        // تخمین تقریبی بر اساس حجم
        const seconds = Math.floor(size / 16000); // 16kbps تقریبی
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    }
    
    /**
     * فرمت کردن حجم فایل
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 بایت';
        const k = 1024;
        const sizes = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}

// مقداردهی اولیه
document.addEventListener('DOMContentLoaded', () => {
    window.chatUtils = new ChatUtils();
});