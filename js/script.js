// Initialize Lucide icons
lucide.createIcons();

// Database Module for localStorage
const Database = {
    _get(key) {
        try {
            const data = localStorage.getItem(key);
            return data ? JSON.parse(data) : null;
        } catch (e) {
            console.error(`Error reading from localStorage key "${key}":`, e);
            return null;
        }
    },
    _set(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (e) {
            console.error(`Error writing to localStorage key "${key}":`, e);
        }
    },

    init() {
        if (!this._get('users')) {
            this._set('users', [
                { id: 1, name: 'مدیر سیستم', email: 'admin@vekalapp.com', password: 'admin', role: 'admin', phone: '09120000000' },
                { id: 2, name: 'دکتر احمد محمدی', email: 'lawyer1@vekalapp.com', password: 'password', role: 'lawyer', specialty: 'حقوق کیفری', experience: 15, rating: 4.8, phone: '09123456789' },
                { id: 3, name: 'خانم دکتر فاطمه احمدی', email: 'lawyer2@vekalapp.com', password: 'password', role: 'lawyer', specialty: 'حقوق تجاری', experience: 10, rating: 4.6, phone: '09123456788' },
                { id: 4, name: 'علی احمدی', email: 'client@vekalapp.com', password: 'password', role: 'client', phone: '09121111111' },
            ]);
        }
        if (!this._get('issues')) {
            this._set('issues', []);
        }
        if (!this._get('appointments')) {
            this._set('appointments', []);
        }
        if (!this._get('chats')) {
            this._set('chats', []);
        }
    },

    getUsers() { return this._get('users') || []; },
    getUserById(id) { return this.getUsers().find(u => u.id === id); },
    getUserByEmail(email) { return this.getUsers().find(user => user.email === email); },
    addUser(user) {
        const users = this.getUsers();
        user.id = users.length > 0 ? Math.max(...users.map(u => u.id)) + 1 : 1;
        users.push(user);
        this._set('users', users);
        return user;
    },

    getIssues() { return this._get('issues') || []; },
    getIssueById(id) { return this.getIssues().find(i => i.id === id); },
    addIssue(issue) {
        const issues = this.getIssues();
        issue.id = issues.length > 0 ? Math.max(...issues.map(i => i.id)) + 1 : 1;
        issue.createdAt = new Date().toISOString();
        issues.push(issue);
        this._set('issues', issues);
        return issue;
    },
    updateIssueStatus(issueId, newStatus) {
        const issues = this.getIssues();
        const issueIndex = issues.findIndex(i => i.id === issueId);
        if (issueIndex !== -1) {
            issues[issueIndex].status = newStatus;
            this._set('issues', issues);
        }
    },

    getLawyers() { return this.getUsers().filter(u => u.role === 'lawyer'); },

    getAppointments() { return this._get('appointments') || []; },
    addAppointment(appointment) {
        const appointments = this.getAppointments();
        appointment.id = appointments.length > 0 ? Math.max(...appointments.map(a => a.id)) + 1 : 1;
        appointment.status = 'pending'; // Lawyers must confirm the time
        appointments.push(appointment);
        this._set('appointments', appointments);
        return appointment;
    },
    updateAppointmentStatus(appointmentId, newStatus) {
        const appointments = this.getAppointments();
        const appointmentIndex = appointments.findIndex(a => a.id === appointmentId);
        if (appointmentIndex !== -1) {
            appointments[appointmentIndex].status = newStatus;
            this._set('appointments', appointments);
        }
    }
};

// Global Application State
const AppState = {
    currentUser: null,
    currentIssue: null,
    selectedLawyerId: null,
    currentPanel: 'landing'
};

// Authentication Module
const AuthModule = {
    showLoginModal() { ModalModule.show('login-modal'); },
    showRegisterModal() { ModalModule.show('register-modal'); },

    handleLogin(event) {
        event.preventDefault();
        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;
        const user = Database.getUserByEmail(email);

        if (user && user.password === password) {
            AppState.currentUser = user;
            ModalModule.hide('login-modal');
            PanelModule.show(user.role);
            NotificationModule.show('با موفقیت وارد شدید');
        } else {
            NotificationModule.show('ایمیل یا رمز عبور اشتباه است', 'error');
        }
    },

    handleRegister(event) {
        event.preventDefault();
        const role = document.getElementById('register-role').value;
        const name = document.getElementById('register-name').value;
        const email = document.getElementById('register-email').value;
        const password = document.getElementById('register-password').value;
        const phone = document.getElementById('register-phone').value;

        if (Database.getUserByEmail(email)) {
            NotificationModule.show('کاربری با این ایمیل قبلاً ثبت‌نام کرده است', 'error');
            return;
        }

        const newUser = Database.addUser({ name, email, password, role, phone });
        AppState.currentUser = newUser;
        ModalModule.hide('register-modal');
        PanelModule.show(role);
        NotificationModule.show('حساب کاربری با موفقیت ایجاد شد');
    },

    logout() {
        AppState.currentUser = null;
        PanelModule.show('landing');
        NotificationModule.show('با موفقیت خارج شدید');
    },

    updateUserInfo() {
        if (!AppState.currentUser) return;

        const { role, name, email } = AppState.currentUser;
        const nameElement = document.getElementById(`${role}-name`);
        const emailElement = document.getElementById(`${role}-email`);
        const welcomeMessage = document.querySelector(`#${role}-panel h2`);

        if (nameElement) nameElement.textContent = name;
        if (emailElement) emailElement.textContent = email;
        if (welcomeMessage) welcomeMessage.textContent = `خوش آمدید، ${name}`;
    }
};

// Panel Management Module
const PanelModule = {
    show(panelName) {
        document.querySelectorAll('.panel, #landing-page').forEach(el => { el.style.display = 'none'; });
        const elementId = panelName === 'landing' ? 'landing-page' : `${panelName}-panel`;
        const panel = document.getElementById(elementId);

        if (panel) {
            panel.style.display = 'block';
            panel.classList.add('animate-fade-in');

            AuthModule.updateUserInfo();
            if (panelName === 'client') this.renderClientPanel();
            if (panelName === 'lawyer') this.renderLawyerPanel();
            if (panelName === 'admin') this.renderAdminPanel();
        }

        AppState.currentPanel = panelName;
        setTimeout(() => { lucide.createIcons(); }, 100);
    },

    renderClientPanel() {
        if (!AppState.currentUser) return;
        const issues = Database.getIssues().filter(i => i.clientId === AppState.currentUser.id);
        const appointments = Database.getAppointments().filter(a => a.clientId === AppState.currentUser.id);

        document.querySelector('#client-panel-stats-issues').textContent = issues.length;
        document.querySelector('#client-panel-stats-appointments').textContent = appointments.length;

        const issuesContainer = document.getElementById('client-issues-container');
        if (issuesContainer) {
            issuesContainer.innerHTML = issues.length > 0 ? issues.map(issue => {
                const lawyer = Database.getUserById(issue.lawyerId);
                return `
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-start justify-between mb-3">
                        <h4 class="font-medium text-gray-900">${issue.title}</h4>
                        <span class="px-3 py-1 rounded-full text-xs font-medium status-${issue.status}">${issue.status}</span>
                    </div>
                    <p class="text-sm text-gray-600 mb-3">${issue.description.substring(0, 100)}...</p>
                    <div class="flex items-center justify-between text-xs text-gray-500">
                        <span>وکیل: ${lawyer ? lawyer.name : 'نامشخص'}</span>
                        ${issue.status === 'awaiting-appointment' ? `<button onclick="AppointmentModule.showModal(${issue.id})" class="bg-blue-600 text-white px-3 py-1 rounded-md">رزرو نوبت</button>` : ''}
                    </div>
                </div>`;
            }).join('') : '<p class="text-gray-500">مسئله‌ای برای نمایش وجود ندارد.</p>';
        }

        const appointmentsContainer = document.querySelector('#client-appointments-container');
        if (appointmentsContainer) {
            appointmentsContainer.innerHTML = appointments.length > 0 ? appointments.map(apt => {
                const lawyer = Database.getUserById(apt.lawyerId);
                return `
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-start justify-between mb-3">
                        <h4 class="font-medium text-gray-900">نوبت با ${lawyer.name}</h4>
                        <span class="px-3 py-1 rounded-full text-xs font-medium status-${apt.status}">${apt.status}</span>
                    </div>
                    <p class="text-sm text-gray-600">تاریخ: ${new Date(apt.date).toLocaleDateString('fa-IR')} ساعت ${apt.time}</p>
                </div>`;
            }).join('') : '<p class="text-gray-500">نوبتی برای نمایش وجود ندارد.</p>';
        }
    },

    renderLawyerPanel() {
        if (!AppState.currentUser) return;
        const issues = Database.getIssues().filter(i => i.lawyerId === AppState.currentUser.id);
        const appointments = Database.getAppointments().filter(a => a.lawyerId === AppState.currentUser.id && a.status === 'pending');
        const issuesContainer = document.getElementById('lawyer-issues-container');

        if (issuesContainer) {
            let content = '<h3>پرونده‌های نیازمند اقدام</h3>';
            content += issues.filter(i => i.status === 'assigned').map(issue => {
                const client = Database.getUserById(issue.clientId);
                return `<div class="bg-white rounded-xl p-6 border"> ... <button onclick="LawyerModule.approveIssue(${issue.id})">...</button> ... </div>`;
            }).join('') || '<p>پرونده جدیدی وجود ندارد.</p>';

            content += '<h3 class="mt-8">نوبت‌های در انتظار تایید</h3>';
            content += appointments.map(apt => {
                 const client = Database.getUserById(apt.clientId);
                 return `<div class="bg-white rounded-xl p-6 border"> ... <button onclick="AppointmentModule.confirmAppointment(${apt.id})">...</button> ... </div>`;
            }).join('') || '<p>نوبت جدیدی برای تایید وجود ندارد.</p>';

            issuesContainer.innerHTML = content;
        }
    },

    renderAdminPanel() { /* ... unchanged ... */ }
};

// Modal Management Module
const ModalModule = { /* ... unchanged ... */ };

// Notification Module
const NotificationModule = { /* ... unchanged ... */ };

// Issue Management Module
const IssueModule = {
    showNewIssueModal() {
        document.getElementById('new-issue-form').reset();
        ModalModule.show('new-issue-modal');
    },
    handleNewIssue(event) {
        event.preventDefault();
        AppState.currentIssue = {
            title: document.getElementById('issue-title').value,
            category: document.getElementById('issue-category').value,
            description: document.getElementById('issue-description').value,
            clientId: AppState.currentUser.id
        };
        ModalModule.hide('new-issue-modal');
        LawyerModule.showSelectionModal();
    }
};

// Lawyer Management Module
const LawyerModule = {
    showSelectionModal() {
        const lawyers = Database.getLawyers();
        const container = document.getElementById('lawyer-selection-container');
        container.innerHTML = lawyers.map(lawyer => `...`).join('');
        ModalModule.show('lawyer-selection-modal');
    },
    selectLawyer(element, lawyerId) {
        // ... selection logic ...
        AppState.selectedLawyerId = parseInt(lawyerId, 10);
    },
    confirmSelection() {
        if (!AppState.selectedLawyerId) { /* ... error ... */ return; }
        Database.addIssue({ ...AppState.currentIssue, lawyerId: AppState.selectedLawyerId, status: 'assigned' });
        ModalModule.hide('lawyer-selection-modal');
        PanelModule.renderClientPanel();
    },
    approveIssue(issueId) {
        Database.updateIssueStatus(issueId, 'awaiting-appointment');
        NotificationModule.show('پرونده تایید شد. منتظر رزرو نوبت توسط موکل بمانید.');
        PanelModule.renderLawyerPanel();
    },
    rejectIssue(issueId) {
        Database.updateIssueStatus(issueId, 'rejected');
        PanelModule.renderLawyerPanel();
    }
};

// Appointment Module
const AppointmentModule = {
    showModal(issueId) {
        document.getElementById('appointment-form').reset();
        document.getElementById('appointment-issue-id').value = issueId;
        ModalModule.show('appointment-modal');
    },
    handleBookAppointment(event) {
        event.preventDefault();
        const issueId = parseInt(document.getElementById('appointment-issue-id').value, 10);
        const issue = Database.getIssueById(issueId);

        Database.addAppointment({
            issueId,
            clientId: issue.clientId,
            lawyerId: issue.lawyerId,
            date: document.getElementById('appointment-date').value,
            time: document.getElementById('appointment-time').value,
            type: document.getElementById('appointment-type').value
        });

        Database.updateIssueStatus(issueId, 'appointment-set');
        ModalModule.hide('appointment-modal');
        NotificationModule.show('درخواست نوبت ارسال شد. منتظر تایید وکیل بمانید.');
        PanelModule.renderClientPanel();
    },
    confirmAppointment(appointmentId) {
        Database.updateAppointmentStatus(appointmentId, 'confirmed');
        NotificationModule.show('نوبت تایید شد.');
        PanelModule.renderLawyerPanel();
    },
    rejectAppointment(appointmentId) {
         Database.updateAppointmentStatus(appointmentId, 'rejected');
         PanelModule.renderLawyerPanel();
    }
};

// Chat Module
const ChatModule = { showModal() { NotificationModule.show('چت (در حال توسعه)'); } };

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    Database.init();
    PanelModule.show('landing');
    document.addEventListener('click', e => { if (e.target.classList.contains('modal')) e.target.classList.remove('active'); });
});
