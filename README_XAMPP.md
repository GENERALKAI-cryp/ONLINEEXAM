# 🚀 XAMPP Deployment Guide — Online Examination System (PHP & MariaDB)

This guide walks you through deploying the **Online Examination System** on **XAMPP** with **Apache**, **PHP**, and **MariaDB / MySQL**.

---

## 📁 1. Project Directory Structure

Ensure the folder is placed inside your XAMPP web root (`htdocs`):

```
C:\xampp\htdocs\online-exam\
├── backend\
│   ├── config.php / db.php    # PDO connection to MariaDB & session management
│   ├── auth.php               # Login, registration, password recovery API
│   ├── admin.php              # Admin CRUD endpoints (students, faculty, exams)
│   ├── teacher.php            # Teacher exam authoring, grading & score sheets
│   └── student.php            # Exam codes, test hall, JPEG uploads & results
├── uploads\                   # Storage directory for student handwritten JPEG sheets
├── database.sql               # MariaDB / MySQL database schema & sample data
├── styles.css                 # Master design tokens & styling
├── app-styles.css             # Components, badges, cards, modals & dark mode
├── app-utils.js               # Navigation & UI components
├── exam-store.js              # Data layer & hybrid client fallback
├── script.js                  # Authentication scripts
├── index.html                 # University Portal Landing
├── login.html                 # Authentication Page
├── register.html              # Registration Page
├── forgot-password.html       # Account Recovery Page
├── admin-dashboard.html       # Administrator Console
├── teacher-dashboard.html     # Teacher Dashboard
├── teacher-create-exam.html   # Create Exam (MCQ & Handwritten JPEG)
├── teacher-exams.html         # Exam Management
├── teacher-correct-exam.html  # Correction & JPEG Inspection Console
├── teacher-scoresheet.html    # Score Sheets & Visibility Publishing
├── teacher-results.html       # Past Results Archive
├── student-dashboard.html     # Student Dashboard
├── student-enter-exam.html    # Access Code Verification & Rules Review
├── student-attend-exam.html   # Distraction-Free Exam Hall (Timer + Proctoring)
└── student-results.html       # Transcripts & Grade Sheets
```

---

## ⚙️ 2. Step-by-Step XAMPP Setup

### Step 1: Copy Project to `htdocs`
Copy the entire `online-exam` folder into:
```
C:\xampp\htdocs\online-exam
```

### Step 2: Start XAMPP Services
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL** (MariaDB).
4. Both modules should turn green.

### Step 3: Import the Database into MariaDB
1. Open your browser and navigate to:
   ```
   http://localhost/phpmyadmin
   ```
2. Click on the **Import** tab at the top menu.
3. Click **Choose File** and select:
   ```
   C:\xampp\htdocs\online-exam\database.sql
   ```
4. Click the **Import** button at the bottom of the page.
5. The database `online_exam_db` will be created with all 5 tables (`users`, `exams`, `questions`, `submissions`, `submission_answers`) pre-populated with academic demo data!

---

## 🔑 3. Database Connection Configuration

The database credentials are configured in `backend/db.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'online_exam_db');
define('DB_USER', 'root');
define('DB_PASS', '');       // Standard default for XAMPP
define('DB_CHARSET', 'utf8mb4');
```

> [!NOTE]
> If you have set a custom root password in phpMyAdmin, update `DB_PASS` in `backend/db.php` accordingly.

---

## 🌐 4. Accessing the Web Application

Open your browser and navigate to:
```
http://localhost/online-exam/
```
or directly to the login portal:
```
http://localhost/online-exam/login.html
```

---

## 👤 5. Pre-Seeded Demo Credentials

| Role | Username | Password | Notes |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `password` | Dean / Admin Console |
| **Teacher** | `rsmith` | `password` | Prof. Robert Smith (Database Systems) |
| **Teacher** | `erostova` | `password` | Dr. Elena Rostova (Network Security) |
| **Student** | `johndoe` | `password` | Candidate John Doe (B.Tech CS) |
| **Student** | `janesmith` | `password` | Candidate Jane Smith (B.Tech IT) |

---

## 🧪 6. Key Demonstration Flows

### 1. Student Exam Attendance Flow
1. Login as Student (`johndoe` / `password`).
2. Click **Enter Exam Code** and input `DBMS2027` (MCQ) or `NETSEC99` (Handwritten JPEG).
3. Review exam instructions and the **Dedicated Rules Box**.
4. Check the rules agreement checkbox and click **Proceed to Examination**.
5. Click **Begin Answering (Start Timer)** — the countdown timer begins!
6. Select your answers (or upload your handwritten answer sheet).
7. Submit the examination.
8. Go to **My Results** to track the status.

### 2. Tab-Switching Proctoring Verification
1. Enter an exam session in `student-attend-exam.html` and begin answering.
2. Switch to another browser tab or minimize the window.
3. The system immediately catches the visibility change, halts the exam, records a zero score, and locks the screen with:
   **Failed — Security Violation**.

### 3. Teacher Evaluation & Score Sheet Publishing
1. Login as Teacher (`rsmith` or `erostova` / `password`).
2. Go to **Correct Exams** to grade student answers, view uploaded JPEG sheets with zoom, and record remarks.
3. Open **Score Sheets** to inspect student totals.
4. Click **Publish Score Sheet to Students**.
5. Login back as Student (`johndoe`) and open **My Results** — the full score and question breakdown is now unlocked!
