// ============================================================
// SCRIPT.JS — Core Authentication & Portal Utilities
// Supports both XAMPP MariaDB Backend and Local Client Mode
// ============================================================

async function handleLogin() {
    const userType = document.getElementById('userType').value;
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;

    if (!userType) {
        alert('Please select your user type.');
        return;
    }

    if (!username) {
        alert('Please enter your username.');
        return;
    }

    // 1. If running under XAMPP / HTTP server, authenticate against MariaDB backend
    if (window.location.protocol.startsWith('http')) {
        try {
            const resp = await fetch('backend/auth.php?action=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password, userType })
            });
            const data = await resp.json();
            if (data.success && data.user) {
                ExamStore.setCurrentUser(data.user);
                redirectToDashboard(data.user.role || userType);
                return;
            } else if (data.error) {
                alert(data.error);
                return;
            }
        } catch (err) {
            console.warn('Backend API connection unavailable, falling back to local simulation:', err);
        }
    }

    // 2. Local fallback for file:/// protocol
    let users = ExamStore.getUsers();
    let existing = users.find(u => u.username.toLowerCase() === username.toLowerCase());

    let userSession;
    if (existing) {
        userSession = {
            id: existing.id,
            username: existing.username,
            fullName: existing.fullName,
            role: userType, // align with selected role
            email: existing.email,
            department: existing.department
        };
    } else {
        let defaultName = username.charAt(0).toUpperCase() + username.slice(1);
        if (userType === 'teacher') defaultName = 'Prof. ' + defaultName;
        if (userType === 'admin') defaultName = 'Administrator ' + defaultName;

        userSession = {
            id: 'usr_' + Date.now(),
            username: username,
            fullName: defaultName,
            role: userType,
            email: username + '@university.edu',
            department: 'Computer Science'
        };
        ExamStore.addUser(userSession);
    }

    ExamStore.setCurrentUser(userSession);
    redirectToDashboard(userType);
}

function redirectToDashboard(role) {
    if (role === 'admin') {
        window.location.href = 'admin-dashboard.html';
    } else if (role === 'teacher') {
        window.location.href = 'teacher-dashboard.html';
    } else {
        window.location.href = 'student-dashboard.html';
    }
}

async function handleRegister() {
    const userType = document.getElementById('regUserType').value;
    const fullName = document.getElementById('fullName').value.trim();
    const username = document.getElementById('regUsername').value.trim();
    const password = document.getElementById('regPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    if (!userType) {
        alert('Please select your user type.');
        return;
    }
    if (!fullName || !username) {
        alert('Please fill out all required fields.');
        return;
    }
    if (password !== confirmPassword) {
        alert("Passwords do not match!");
        return;
    }

    // If running under XAMPP / HTTP
    if (window.location.protocol.startsWith('http')) {
        try {
            const resp = await fetch('backend/auth.php?action=register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ fullName, username, password, userType })
            });
            const data = await resp.json();
            if (data.success) {
                alert("Registration successful! Please login.");
                window.location.href = 'login.html';
                return;
            } else {
                alert(data.error || 'Registration failed');
                return;
            }
        } catch (err) {
            console.warn('Backend unavailable, saving locally:', err);
        }
    }

    ExamStore.addUser({
        fullName,
        username,
        role: userType,
        email: username + '@university.edu',
        status: 'Active'
    });

    alert("Registration successful! Please login.");
    window.location.href = 'login.html';
}

async function handleForgotPassword() {
    const recoveryInput = document.getElementById('recoveryInput').value;
    if (!recoveryInput || recoveryInput.trim() === '') {
        alert("Please enter a valid email or username.");
        return;
    }

    if (window.location.protocol.startsWith('http')) {
        try {
            const resp = await fetch('backend/auth.php?action=forgot_password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ recoveryInput })
            });
            const data = await resp.json();
            alert(data.message || "Password reset instructions have been sent!");
            window.location.href = 'login.html';
            return;
        } catch (err) {
            console.warn('Backend error:', err);
        }
    }

    alert("Password reset instructions have been sent to your registered email!");
    window.location.href = 'login.html';
}

// Theme Toggle
function initTheme() {
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', currentTheme);

    if (!document.querySelector('.theme-toggle-btn')) {
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'theme-toggle-btn';
        toggleBtn.innerHTML = currentTheme === 'dark' ? '☀️' : '🌙';
        toggleBtn.title = 'Toggle Theme';
        toggleBtn.onclick = () => {
            const theme = document.documentElement.getAttribute('data-theme');
            const newTheme = theme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            toggleBtn.innerHTML = newTheme === 'dark' ? '☀️' : '🌙';
        };
        document.body.appendChild(toggleBtn);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
});
