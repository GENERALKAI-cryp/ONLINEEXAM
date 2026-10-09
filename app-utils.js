// ============================================================
// APP UTILS — Shared Components & UI Helpers
// ============================================================

const Toast = {
    container: null,

    init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    },

    show(message, type = 'info', duration = 3000) {
        this.init();
        const toast = document.createElement('div');
        toast.className = `toast-msg toast-${type}`;
        
        const icon = type === 'success' ? '✓' : type === 'danger' ? '✕' : type === 'warning' ? '⚠' : 'ℹ';
        toast.innerHTML = `<span style="font-weight: bold; font-size: 16px;">${icon}</span> <span>${message}</span>`;
        
        this.container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
};

const AppNav = {
    render(activeKey = '') {
        const user = ExamStore.getCurrentUser() || { fullName: 'Demo User', role: 'student', username: 'guest' };
        const header = document.querySelector('header');
        if (!header) return;

        let navLinks = '';
        if (user.role === 'admin') {
            navLinks = `
                <a href="admin-dashboard.html" class="nav-link ${activeKey === 'admin-dashboard' ? 'active' : ''}">Dashboard</a>
                <a href="admin-dashboard.html#students" class="nav-link ${activeKey === 'admin-students' ? 'active' : ''}">Students</a>
                <a href="admin-dashboard.html#teachers" class="nav-link ${activeKey === 'admin-teachers' ? 'active' : ''}">Teachers</a>
                <a href="admin-dashboard.html#exams" class="nav-link ${activeKey === 'admin-exams' ? 'active' : ''}">Exams</a>
                <a href="admin-dashboard.html#submissions" class="nav-link ${activeKey === 'admin-submissions' ? 'active' : ''}">Attempts</a>
                <a href="admin-dashboard.html#results" class="nav-link ${activeKey === 'admin-results' ? 'active' : ''}">Results</a>
            `;
        } else if (user.role === 'teacher') {
            navLinks = `
                <a href="teacher-dashboard.html" class="nav-link ${activeKey === 'teacher-dashboard' ? 'active' : ''}">Dashboard</a>
                <a href="teacher-create-exam.html" class="nav-link ${activeKey === 'teacher-create-exam' ? 'active' : ''}">+ Create Exam</a>
                <a href="teacher-exams.html" class="nav-link ${activeKey === 'teacher-exams' ? 'active' : ''}">Exams</a>
                <a href="teacher-correct-exam.html" class="nav-link ${activeKey === 'teacher-correct-exam' ? 'active' : ''}">Correct Exams</a>
                <a href="teacher-results.html" class="nav-link ${activeKey === 'teacher-results' ? 'active' : ''}">Past Results</a>
                <a href="teacher-scoresheet.html" class="nav-link ${activeKey === 'teacher-scoresheet' ? 'active' : ''}">Score Sheets</a>
            `;
        } else {
            // Student
            navLinks = `
                <a href="student-dashboard.html" class="nav-link ${activeKey === 'student-dashboard' ? 'active' : ''}">Dashboard</a>
                <a href="student-enter-exam.html" class="nav-link ${activeKey === 'student-enter-exam' ? 'active' : ''}">Enter Exam</a>
                <a href="student-dashboard.html#my-exams" class="nav-link ${activeKey === 'student-my-exams' ? 'active' : ''}">My Exams</a>
                <a href="student-results.html" class="nav-link ${activeKey === 'student-results' ? 'active' : ''}">Results</a>
            `;
        }

        const roleClass = `role-${user.role}`;
        const roleLabel = user.role.toUpperCase();

        header.className = 'app-header';
        header.innerHTML = `
            <div class="header-top-row">
                <a href="${user.role === 'admin' ? 'admin-dashboard.html' : user.role === 'teacher' ? 'teacher-dashboard.html' : 'student-dashboard.html'}" class="logo-area" style="text-decoration: none; color: inherit;">
                    <div class="logo-placeholder">U</div>
                    <div>
                        <h2 style="font-size: 17px; margin: 0; line-height: 1.2;">University Portal</h2>
                        <span style="font-size: 11px; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px;">Examination System</span>
                    </div>
                </a>

                <div class="header-right-actions">
                    <button class="mobile-nav-toggle" id="mobileNavToggle" aria-label="Toggle navigation menu">
                        <span class="hamburger-icon">☰</span>
                    </button>
                </div>
            </div>

            <div class="nav-collapsible-wrapper" id="navCollapsibleWrapper">
                <nav class="app-nav">
                    ${navLinks}
                </nav>

                <div class="nav-user-section">
                    <div class="user-profile-chip">
                        <span class="user-avatar-sm">${(user.fullName || user.username || 'U')[0].toUpperCase()}</span>
                        <span class="user-name-text">${escapeHtml(user.fullName || user.username)}</span>
                        <span class="role-badge ${roleClass}">${roleLabel}</span>
                    </div>
                    <button onclick="ExamStore.logout()" class="btn btn-secondary btn-sm btn-logout" title="Sign out">Logout</button>
                </div>
            </div>
        `;

        // Mobile menu toggle event
        const toggleBtn = header.querySelector('#mobileNavToggle');
        const navWrapper = header.querySelector('#navCollapsibleWrapper');
        if (toggleBtn && navWrapper) {
            toggleBtn.onclick = (e) => {
                e.stopPropagation();
                const isOpen = navWrapper.classList.toggle('nav-open');
                toggleBtn.querySelector('.hamburger-icon').textContent = isOpen ? '✕' : '☰';
            };

            // Close when clicking nav links
            navWrapper.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    navWrapper.classList.remove('nav-open');
                    const icon = toggleBtn.querySelector('.hamburger-icon');
                    if (icon) icon.textContent = '☰';
                });
            });

            // Close when clicking outside
            document.addEventListener('click', (e) => {
                if (!header.contains(e.target)) {
                    navWrapper.classList.remove('nav-open');
                    const icon = toggleBtn.querySelector('.hamburger-icon');
                    if (icon) icon.textContent = '☰';
                }
            });
        }
    },

    getStatusBadge(status) {
        let badgeClass = 'badge-draft';
        if (status === 'Published / Active' || status === 'Active') badgeClass = 'badge-active';
        else if (status === 'Completed') badgeClass = 'badge-completed';
        else if (status === 'Closed' || status === 'Inactive') badgeClass = 'badge-closed';
        else if (status === 'Submitted' || status === 'Attended') badgeClass = 'badge-submitted';
        else if (status === 'Under Correction' || status === 'Evaluation in Progress') badgeClass = 'badge-correction';
        else if (status === 'Result Published' || status === 'Evaluated') badgeClass = 'badge-published';
        else if (status === 'Failed — Security Violation') badgeClass = 'badge-violation';

        return `<span class="status-badge ${badgeClass}">${status}</span>`;
    }
};

// Global escape helper
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
