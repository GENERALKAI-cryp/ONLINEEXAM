// ============================================================
// EXAM STORE — Central Frontend Data Simulation Layer
// Pure Vanilla JavaScript & localStorage
// Supports Admin, Teacher, and Student workflows
// ============================================================

const ExamStore = {
    KEYS: {
        USERS: 'oes_users',
        EXAMS: 'oes_exams',
        SUBMISSIONS: 'oes_submissions',
        CURRENT_USER: 'currentUser'
    },

    // Initialize Store with Rich Demo Data if empty
    init() {
        if (!localStorage.getItem(this.KEYS.USERS)) {
            this.seedUsers();
        }
        if (!localStorage.getItem(this.KEYS.EXAMS)) {
            this.seedExams();
        }
        if (!localStorage.getItem(this.KEYS.SUBMISSIONS)) {
            this.seedSubmissions();
        }
    },

    // --- USER MANAGEMENT ---
    getUsers() {
        return JSON.parse(localStorage.getItem(this.KEYS.USERS) || '[]');
    },

    saveUsers(users) {
        localStorage.setItem(this.KEYS.USERS, JSON.stringify(users));
    },

    getStudents() {
        return this.getUsers().filter(u => u.role === 'student');
    },

    getTeachers() {
        return this.getUsers().filter(u => u.role === 'teacher');
    },

    addUser(userData) {
        const users = this.getUsers();
        const newUser = {
            id: 'usr_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
            username: userData.username,
            fullName: userData.fullName,
            email: userData.email || (userData.username + '@university.edu'),
            role: userData.role, // 'admin', 'teacher', 'student'
            status: userData.status || 'Active', // 'Active', 'Inactive'
            department: userData.department || 'Computer Science',
            createdAt: new Date().toISOString()
        };
        users.push(newUser);
        this.saveUsers(users);
        return newUser;
    },

    updateUser(id, updates) {
        const users = this.getUsers();
        const idx = users.findIndex(u => u.id === id);
        if (idx !== -1) {
            users[idx] = { ...users[idx], ...updates, updatedAt: new Date().toISOString() };
            this.saveUsers(users);
            return users[idx];
        }
        return null;
    },

    deleteUser(id) {
        const users = this.getUsers().filter(u => u.id !== id);
        this.saveUsers(users);
    },

    toggleUserStatus(id) {
        const users = this.getUsers();
        const user = users.find(u => u.id === id);
        if (user) {
            user.status = user.status === 'Active' ? 'Inactive' : 'Active';
            this.saveUsers(users);
            return user.status;
        }
        return null;
    },

    // --- CURRENT SESSION ---
    getCurrentUser() {
        return JSON.parse(localStorage.getItem(this.KEYS.CURRENT_USER) || 'null');
    },

    setCurrentUser(user) {
        localStorage.setItem(this.KEYS.CURRENT_USER, JSON.stringify(user));
    },

    logout() {
        if (window.location.protocol.startsWith('http')) {
            try { fetch('backend/auth.php?action=logout').catch(() => {}); } catch(e) {}
        }
        localStorage.removeItem(this.KEYS.CURRENT_USER);
        window.location.href = 'login.html';
    },

    // --- EXAM MANAGEMENT ---
    getExams() {
        return JSON.parse(localStorage.getItem(this.KEYS.EXAMS) || '[]');
    },

    saveExams(exams) {
        localStorage.setItem(this.KEYS.EXAMS, JSON.stringify(exams));
    },

    getExamById(id) {
        return this.getExams().find(e => e.id === id) || null;
    },

    getExamByCode(code) {
        if (!code) return null;
        const normalized = code.trim().toUpperCase();
        return this.getExams().find(e => e.examCode && e.examCode.toUpperCase() === normalized) || null;
    },

    createExam(examData) {
        const exams = this.getExams();
        const newExam = {
            id: 'ex_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5),
            name: examData.name || 'Untitled Exam',
            subject: examData.subject || 'General Studies',
            date: examData.date || new Date().toISOString().split('T')[0],
            time: examData.time || '10:00',
            duration: parseInt(examData.duration) || 60, // in minutes
            totalMarks: parseInt(examData.totalMarks) || 100,
            examCode: examData.examCode || this.generateExamCode(),
            description: examData.description || '',
            rules: examData.rules || this.getDefaultRules(),
            examType: examData.examType || 'mcq', // 'mcq' or 'jpeg'
            checkingMode: examData.checkingMode || (examData.examType === 'mcq' ? 'auto' : 'manual'), // 'auto' or 'manual'
            status: examData.status || 'Draft', // 'Draft', 'Published / Active', 'Completed', 'Closed'
            scoreSheetStatus: 'Not Evaluated', // 'Not Evaluated', 'Evaluation in Progress', 'Result Published'
            teacherName: examData.teacherName || 'Prof. Robert Smith',
            questions: examData.questions || [],
            createdAt: new Date().toISOString()
        };
        exams.unshift(newExam);
        this.saveExams(exams);
        return newExam;
    },

    updateExam(id, updates) {
        const exams = this.getExams();
        const idx = exams.findIndex(e => e.id === id);
        if (idx !== -1) {
            exams[idx] = { ...exams[idx], ...updates, updatedAt: new Date().toISOString() };
            this.saveExams(exams);
            return exams[idx];
        }
        return null;
    },

    deleteExam(id) {
        const exams = this.getExams().filter(e => e.id !== id);
        this.saveExams(exams);
    },

    generateExamCode(length = 6) {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        let code = '';
        for (let i = 0; i < length; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return code;
    },

    getDefaultRules() {
        return `1. Students must join the examination using their registered university credentials.
2. The examination timer starts automatically as soon as you begin answering.
3. TAB SWITCHING OR WINDOW MINIMIZATION IS STRICTLY MONITORED. Switching to another browser tab or window will immediately terminate your exam attempt and record a Security Violation failure.
4. Ensure a reliable power supply and stable internet connectivity.
5. All answers must be submitted before the countdown timer reaches zero.`;
    },

    // --- SUBMISSIONS & ATTEMPTS ---
    getSubmissions() {
        return JSON.parse(localStorage.getItem(this.KEYS.SUBMISSIONS) || '[]');
    },

    saveSubmissions(subs) {
        localStorage.setItem(this.KEYS.SUBMISSIONS, JSON.stringify(subs));
    },

    getSubmissionById(id) {
        return this.getSubmissions().find(s => s.id === id) || null;
    },

    getSubmissionsForExam(examId) {
        return this.getSubmissions().filter(s => s.examId === examId);
    },

    getSubmissionsForStudent(studentNameOrId) {
        return this.getSubmissions().filter(s => 
            s.studentName === studentNameOrId || s.studentId === studentNameOrId || s.username === studentNameOrId
        );
    },

    createSubmission(examId, student) {
        const exam = this.getExamById(examId);
        if (!exam) return null;

        const subs = this.getSubmissions();
        const newSub = {
            id: 'sub_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5),
            examId: exam.id,
            examName: exam.name,
            examCode: exam.examCode,
            examType: exam.examType,
            totalMarks: exam.totalMarks,
            studentName: student.fullName || student.username || 'Student Candidate',
            studentId: student.id || 'STU-2024-001',
            username: student.username || 'johndoe',
            answers: {}, // questionId -> { answer: string/index, fileData: string, fileName: string }
            questionScores: {}, // questionId -> marks
            questionRemarks: {}, // questionId -> string
            obtainedScore: 0,
            status: 'Attended', // 'Attended', 'Submitted', 'Under Correction', 'Result Published', 'Failed — Security Violation'
            securityViolation: null, // { reason: string, timestamp: string }
            startedAt: new Date().toISOString(),
            submittedAt: null
        };
        subs.push(newSub);
        this.saveSubmissions(subs);
        return newSub;
    },

    saveAnswer(submissionId, questionId, answerData) {
        const subs = this.getSubmissions();
        const sub = subs.find(s => s.id === submissionId);
        if (sub && sub.status !== 'Failed — Security Violation') {
            sub.answers[questionId] = answerData;
            this.saveSubmissions(subs);
        }
    },

    submitExam(submissionId) {
        const subs = this.getSubmissions();
        const sub = subs.find(s => s.id === submissionId);
        if (!sub) return null;

        if (sub.status === 'Failed — Security Violation') {
            return sub;
        }

        sub.status = 'Submitted';
        sub.submittedAt = new Date().toISOString();

        const exam = this.getExamById(sub.examId);
        if (exam) {
            // If MCQ with auto-checking, compute score automatically
            if (exam.examType === 'mcq' && exam.checkingMode === 'auto') {
                let autoScore = 0;
                exam.questions.forEach(q => {
                    const ans = sub.answers[q.id];
                    if (ans && parseInt(ans.answer) === parseInt(q.correctAnswer)) {
                        const marks = parseInt(q.marks) || 1;
                        sub.questionScores[q.id] = marks;
                        sub.questionRemarks[q.id] = 'Correct';
                        autoScore += marks;
                    } else {
                        sub.questionScores[q.id] = 0;
                        sub.questionRemarks[q.id] = 'Incorrect';
                    }
                });
                sub.obtainedScore = autoScore;
                // If auto checked, it is ready for review or published
                sub.status = 'Submitted';
            } else {
                // Requires manual correction (JPEG or Manual MCQ)
                sub.status = 'Under Correction';
            }
        }

        this.saveSubmissions(subs);
        return sub;
    },

    recordSecurityViolation(submissionId, reason = 'Tab Switching Detected') {
        const subs = this.getSubmissions();
        const sub = subs.find(s => s.id === submissionId);
        if (sub) {
            sub.status = 'Failed — Security Violation';
            sub.obtainedScore = 0;
            sub.securityViolation = {
                reason: reason,
                timestamp: new Date().toISOString()
            };
            sub.submittedAt = new Date().toISOString();
            this.saveSubmissions(subs);
            return sub;
        }
        return null;
    },

    saveCorrection(submissionId, scores, remarks, totalScore, markAsEvaluated = false) {
        const subs = this.getSubmissions();
        const sub = subs.find(s => s.id === submissionId);
        if (sub) {
            sub.questionScores = scores || sub.questionScores;
            sub.questionRemarks = remarks || sub.questionRemarks;
            sub.obtainedScore = totalScore !== undefined ? totalScore : sub.obtainedScore;
            if (markAsEvaluated) {
                // If the exam's score sheet is already published, show Result Published, else keep Under Correction
                const exam = this.getExamById(sub.examId);
                if (exam && exam.scoreSheetStatus === 'Result Published') {
                    sub.status = 'Result Published';
                } else {
                    sub.status = 'Under Correction';
                }
            }
            this.saveSubmissions(subs);
            return sub;
        }
        return null;
    },

    // --- SCORE SHEET & PUBLISHING ---
    publishScoreSheet(examId) {
        const exam = this.getExamById(examId);
        if (!exam) return false;

        exam.scoreSheetStatus = 'Result Published';
        this.updateExam(examId, { scoreSheetStatus: 'Result Published', status: 'Completed' });

        // Update all related non-violated submissions to "Result Published"
        const subs = this.getSubmissions();
        subs.forEach(s => {
            if (s.examId === examId && s.status !== 'Failed — Security Violation') {
                s.status = 'Result Published';
            }
        });
        this.saveSubmissions(subs);
        return true;
    },

    unpublishScoreSheet(examId) {
        const exam = this.getExamById(examId);
        if (!exam) return false;

        exam.scoreSheetStatus = 'Evaluation in Progress';
        this.updateExam(examId, { scoreSheetStatus: 'Evaluation in Progress' });

        const subs = this.getSubmissions();
        subs.forEach(s => {
            if (s.examId === examId && s.status === 'Result Published') {
                s.status = 'Under Correction';
            }
        });
        this.saveSubmissions(subs);
        return true;
    },

    // --- SEED DATA ---
    seedUsers() {
        const users = [
            { id: 'usr_admin', username: 'admin', fullName: 'Dr. Arthur Vance (Dean)', email: 'admin@university.edu', role: 'admin', status: 'Active', department: 'Academic Administration', createdAt: '2026-09-01T08:00:00Z' },
            { id: 'usr_t1', username: 'rsmith', fullName: 'Prof. Robert Smith', email: 'r.smith@university.edu', role: 'teacher', status: 'Active', department: 'Computer Science', createdAt: '2026-09-05T09:00:00Z' },
            { id: 'usr_t2', username: 'erostova', fullName: 'Dr. Elena Rostova', email: 'e.rostova@university.edu', role: 'teacher', status: 'Active', department: 'Information Security', createdAt: '2026-09-10T11:00:00Z' },
            { id: 'usr_t3', username: 'aturing', fullName: 'Prof. Alan Turing', email: 'a.turing@university.edu', role: 'teacher', status: 'Active', department: 'Algorithms & AI', createdAt: '2026-09-12T10:00:00Z' },
            { id: 'usr_s1', username: 'johndoe', fullName: 'John Doe', email: 'john.doe@student.university.edu', role: 'student', status: 'Active', department: 'B.Tech Computer Science', createdAt: '2026-09-15T12:00:00Z' },
            { id: 'usr_s2', username: 'janesmith', fullName: 'Jane Smith', email: 'jane.smith@student.university.edu', role: 'student', status: 'Active', department: 'B.Tech Information Technology', createdAt: '2026-09-15T12:30:00Z' },
            { id: 'usr_s3', username: 'arivera', fullName: 'Alex Rivera', email: 'a.rivera@student.university.edu', role: 'student', status: 'Active', department: 'B.Tech Cyber Security', createdAt: '2026-09-16T14:00:00Z' },
            { id: 'usr_s4', username: 'psharma', fullName: 'Priya Sharma', email: 'p.sharma@student.university.edu', role: 'student', status: 'Active', department: 'B.Tech Computer Science', createdAt: '2026-09-16T15:00:00Z' }
        ];
        this.saveUsers(users);
    },

    seedExams() {
        const sampleJpeg = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(`
            <svg xmlns="http://www.w3.org/2000/svg" width="600" height="400" viewBox="0 0 600 400">
                <rect width="600" height="400" fill="#fdfbf7" stroke="#333" stroke-width="2"/>
                <text x="30" y="50" font-family="Courier, monospace" font-size="20" font-weight="bold" fill="#1e3a8a">Handwritten Answer Sheet - Page 1</text>
                <line x1="30" y1="65" x2="570" y2="65" stroke="#1e3a8a" stroke-width="1.5"/>
                <text x="30" y="110" font-family="cursive" font-size="18" fill="#111827">Q1. RSA Encryption Algorithm Steps:</text>
                <text x="50" y="145" font-family="cursive" font-size="16" fill="#1f2937">1. Choose two large prime numbers p and q.</text>
                <text x="50" y="175" font-family="cursive" font-size="16" fill="#1f2937">2. Compute n = p * q and Euler totient phi(n) = (p-1)*(q-1).</text>
                <text x="50" y="205" font-family="cursive" font-size="16" fill="#1f2937">3. Select public exponent e coprime to phi(n).</text>
                <text x="50" y="235" font-family="cursive" font-size="16" fill="#1f2937">4. Calculate private key d = e^(-1) mod phi(n).</text>
                <text x="50" y="265" font-family="cursive" font-size="16" fill="#1f2937">5. Encryption: C = M^e mod n. Decryption: M = C^d mod n.</text>
                <rect x="40" y="300" width="520" height="60" fill="#e0f2fe" stroke="#38bdf8" rx="6"/>
                <text x="60" y="335" font-family="sans-serif" font-size="14" fill="#0369a1">[Verified handwritten diagram and mathematical derivation]</text>
            </svg>
        `);

        const exams = [
            // 1. MCQ Exam (Active) - Code: DBMS2027
            {
                id: 'ex_dbms_101',
                name: 'Database Management Systems Midterm',
                subject: 'Database Systems',
                date: '2026-10-15',
                time: '10:00',
                duration: 30,
                totalMarks: 50,
                examCode: 'DBMS2027',
                description: 'Mid-term assessment covering relational algebra, SQL normal forms, and ACID transaction properties.',
                rules: `• Examination duration is strictly 30 minutes.
• All 5 multiple choice questions are mandatory (10 marks each).
• Tab switching or navigating away from this window will automatically fail and terminate your examination.
• No negative marking.`,
                examType: 'mcq',
                checkingMode: 'auto',
                status: 'Published / Active',
                scoreSheetStatus: 'Not Evaluated',
                teacherName: 'Prof. Robert Smith',
                createdAt: '2026-10-01T10:00:00Z',
                questions: [
                    {
                        id: 'q1',
                        text: 'Which normal form eliminates partial functional dependencies on the primary key?',
                        optionA: 'First Normal Form (1NF)',
                        optionB: 'Second Normal Form (2NF)',
                        optionC: 'Third Normal Form (3NF)',
                        optionD: 'Boyce-Codd Normal Form (BCNF)',
                        correctAnswer: 1, // B
                        marks: 10
                    },
                    {
                        id: 'q2',
                        text: 'In the ACID properties of database transactions, what does the "I" stand for?',
                        optionA: 'Integrity',
                        optionB: 'Indexation',
                        optionC: 'Isolation',
                        optionD: 'Immutability',
                        correctAnswer: 2, // C
                        marks: 10
                    },
                    {
                        id: 'q3',
                        text: 'Which SQL clause is used to filter group results after aggregation?',
                        optionA: 'WHERE',
                        optionB: 'HAVING',
                        optionC: 'GROUP BY',
                        optionD: 'FILTER',
                        correctAnswer: 1, // B
                        marks: 10
                    },
                    {
                        id: 'q4',
                        text: 'Which data structure is most commonly used in database storage indexing?',
                        optionA: 'B+ Tree',
                        optionB: 'Binary Search Tree',
                        optionC: 'Stack',
                        optionD: 'Breadth-First Graph',
                        correctAnswer: 0, // A
                        marks: 10
                    },
                    {
                        id: 'q5',
                        text: 'What is a foreign key in relational databases?',
                        optionA: 'A primary key of an external database',
                        optionB: 'An encrypted security token',
                        optionC: 'A column referencing the primary key of another table',
                        optionD: 'A key imported from a foreign jurisdiction',
                        correctAnswer: 2, // C
                        marks: 10
                    }
                ]
            },

            // 2. JPEG Answer Exam (Active) - Code: NETSEC99
            {
                id: 'ex_netsec_202',
                name: 'Network Security & Cryptography Assessment',
                subject: 'Information Security',
                date: '2026-10-18',
                time: '14:00',
                duration: 45,
                totalMarks: 40,
                examCode: 'NETSEC99',
                description: 'Descriptive examination requiring handwritten answers, cryptographic diagrams, and manual upload of JPEG sheets.',
                rules: `• Write your answers clearly on clean white paper using blue or black ink.
• Take clear JPEG photographs or scan your answer sheets and upload them for each corresponding question.
• Maximum image size is 5MB per upload.
• Ensure proper orientation and legibility before final submission.
• Switching browser tabs will result in automatic termination for Security Violation.`,
                examType: 'jpeg',
                checkingMode: 'manual',
                status: 'Published / Active',
                scoreSheetStatus: 'Evaluation in Progress',
                teacherName: 'Dr. Elena Rostova',
                createdAt: '2026-10-02T11:30:00Z',
                questions: [
                    {
                        id: 'q_ns_1',
                        text: 'Explain the working of the RSA asymmetric cryptographic algorithm with a mathematical derivation and diagram. Upload your handwritten sheet as JPEG.',
                        marks: 20,
                        instruction: 'Provide complete mathematical proof, key generation, encryption, and decryption steps.'
                    },
                    {
                        id: 'q_ns_2',
                        text: 'Illustrate the SSL/TLS 1.3 Handshake Protocol. Show the messages exchanged between client and server for session key agreement.',
                        marks: 20,
                        instruction: 'Draw the sequence diagram with Diffie-Hellman parameters exchange and certificate verification.'
                    }
                ]
            },

            // 3. Completed Past Exam with Published Score Sheet - Code: DSA101
            {
                id: 'ex_dsa_303',
                name: 'Data Structures & Algorithms Final',
                subject: 'Algorithms',
                date: '2026-09-28',
                time: '09:00',
                duration: 60,
                totalMarks: 100,
                examCode: 'DSA101',
                description: 'Comprehensive evaluation of asymptotic analysis, dynamic programming, and graph algorithms.',
                rules: `• Answer all questions. Tab switching violation protocol is active.`,
                examType: 'mcq',
                checkingMode: 'auto',
                status: 'Completed',
                scoreSheetStatus: 'Result Published',
                teacherName: 'Prof. Alan Turing',
                createdAt: '2026-09-15T08:00:00Z',
                questions: [
                    { id: 'qd1', text: 'What is the worst-case time complexity of Quick Sort?', optionA: 'O(n log n)', optionB: 'O(n²)', optionC: 'O(n)', optionD: 'O(log n)', correctAnswer: 1, marks: 50 },
                    { id: 'qd2', text: 'Which data structure is primarily used in Dijkstra algorithm?', optionA: 'FIFO Queue', optionB: 'Min-Heap Priority Queue', optionC: 'Stack', optionD: 'Linked List', correctAnswer: 1, marks: 50 }
                ]
            },

            // 4. Draft Exam
            {
                id: 'ex_se_404',
                name: 'Software Engineering Best Practices',
                subject: 'Software Engineering',
                date: '2026-11-05',
                time: '11:00',
                duration: 40,
                totalMarks: 50,
                examCode: 'SE404',
                description: 'Agile methodologies, CI/CD pipelines, and software design patterns.',
                rules: `• Exam rules pending final department review.`,
                examType: 'mcq',
                checkingMode: 'auto',
                status: 'Draft',
                scoreSheetStatus: 'Not Evaluated',
                teacherName: 'Prof. Robert Smith',
                createdAt: '2026-10-05T09:00:00Z',
                questions: []
            }
        ];
        this.saveExams(exams);
    },

    seedSubmissions() {
        const sampleJpeg = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(`
            <svg xmlns="http://www.w3.org/2000/svg" width="600" height="400" viewBox="0 0 600 400">
                <rect width="600" height="400" fill="#fdfbf7" stroke="#333" stroke-width="2"/>
                <text x="30" y="50" font-family="Courier, monospace" font-size="20" font-weight="bold" fill="#1e3a8a">Handwritten Answer Sheet - Page 1</text>
                <line x1="30" y1="65" x2="570" y2="65" stroke="#1e3a8a" stroke-width="1.5"/>
                <text x="30" y="110" font-family="cursive" font-size="18" fill="#111827">Q1. RSA Encryption Algorithm Steps:</text>
                <text x="50" y="145" font-family="cursive" font-size="16" fill="#1f2937">1. Choose two large prime numbers p and q.</text>
                <text x="50" y="175" font-family="cursive" font-size="16" fill="#1f2937">2. Compute n = p * q and Euler totient phi(n) = (p-1)*(q-1).</text>
                <text x="50" y="205" font-family="cursive" font-size="16" fill="#1f2937">3. Select public exponent e coprime to phi(n).</text>
                <text x="50" y="235" font-family="cursive" font-size="16" fill="#1f2937">4. Calculate private key d = e^(-1) mod phi(n).</text>
                <text x="50" y="265" font-family="cursive" font-size="16" fill="#1f2937">5. Encryption: C = M^e mod n. Decryption: M = C^d mod n.</text>
                <rect x="40" y="300" width="520" height="60" fill="#e0f2fe" stroke="#38bdf8" rx="6"/>
                <text x="60" y="335" font-family="sans-serif" font-size="14" fill="#0369a1">[Verified handwritten diagram and mathematical derivation]</text>
            </svg>
        `);

        const submissions = [
            // 1. John Doe - DSA 101 - Result Published (Score: 100/100)
            {
                id: 'sub_dsa_john',
                examId: 'ex_dsa_303',
                examName: 'Data Structures & Algorithms Final',
                examCode: 'DSA101',
                examType: 'mcq',
                totalMarks: 100,
                studentName: 'John Doe',
                studentId: 'STU-2024-001',
                username: 'johndoe',
                answers: {
                    'qd1': { answer: 1 },
                    'qd2': { answer: 1 }
                },
                questionScores: { 'qd1': 50, 'qd2': 50 },
                questionRemarks: { 'qd1': 'Correct', 'qd2': 'Correct' },
                obtainedScore: 100,
                status: 'Result Published',
                startedAt: '2026-09-28T09:02:00Z',
                submittedAt: '2026-09-28T09:45:00Z'
            },

            // 2. Jane Smith - DSA 101 - Result Published (Score: 50/100)
            {
                id: 'sub_dsa_jane',
                examId: 'ex_dsa_303',
                examName: 'Data Structures & Algorithms Final',
                examCode: 'DSA101',
                examType: 'mcq',
                totalMarks: 100,
                studentName: 'Jane Smith',
                studentId: 'STU-2024-002',
                username: 'janesmith',
                answers: {
                    'qd1': { answer: 1 },
                    'qd2': { answer: 0 }
                },
                questionScores: { 'qd1': 50, 'qd2': 0 },
                questionRemarks: { 'qd1': 'Correct', 'qd2': 'Incorrect' },
                obtainedScore: 50,
                status: 'Result Published',
                startedAt: '2026-09-28T09:05:00Z',
                submittedAt: '2026-09-28T09:50:00Z'
            },

            // 3. John Doe - NETSEC 99 (JPEG Exam) - Under Correction (Pending Teacher Evaluation)
            {
                id: 'sub_netsec_john',
                examId: 'ex_netsec_202',
                examName: 'Network Security & Cryptography Assessment',
                examCode: 'NETSEC99',
                examType: 'jpeg',
                totalMarks: 40,
                studentName: 'John Doe',
                studentId: 'STU-2024-001',
                username: 'johndoe',
                answers: {
                    'q_ns_1': {
                        fileData: sampleJpeg,
                        fileName: 'john_doe_q1_rsa_derivation.jpg',
                        uploadedAt: '2026-10-06T14:20:00Z'
                    },
                    'q_ns_2': {
                        fileData: sampleJpeg,
                        fileName: 'john_doe_q2_tls_handshake.jpg',
                        uploadedAt: '2026-10-06T14:35:00Z'
                    }
                },
                questionScores: { 'q_ns_1': 18, 'q_ns_2': 17 },
                questionRemarks: { 'q_ns_1': 'Clear mathematical derivation and diagram.', 'q_ns_2': 'Good illustration of Key Exchange phases.' },
                obtainedScore: 35,
                status: 'Under Correction', // Not published yet -> student sees "Under Correction"
                startedAt: '2026-10-06T14:02:00Z',
                submittedAt: '2026-10-06T14:40:00Z'
            },

            // 4. Priya Sharma - NETSEC 99 (JPEG Exam) - Submitted
            {
                id: 'sub_netsec_priya',
                examId: 'ex_netsec_202',
                examName: 'Network Security & Cryptography Assessment',
                examCode: 'NETSEC99',
                examType: 'jpeg',
                totalMarks: 40,
                studentName: 'Priya Sharma',
                studentId: 'STU-2024-004',
                username: 'psharma',
                answers: {
                    'q_ns_1': {
                        fileData: sampleJpeg,
                        fileName: 'priya_sharma_rsa.jpeg',
                        uploadedAt: '2026-10-06T14:25:00Z'
                    }
                },
                questionScores: {},
                questionRemarks: {},
                obtainedScore: 0,
                status: 'Submitted',
                startedAt: '2026-10-06T14:01:00Z',
                submittedAt: '2026-10-06T14:38:00Z'
            },

            // 5. Alex Rivera - DBMS 2027 - Security Violation Failure!
            {
                id: 'sub_dbms_alex_violation',
                examId: 'ex_dbms_101',
                examName: 'Database Management Systems Midterm',
                examCode: 'DBMS2027',
                examType: 'mcq',
                totalMarks: 50,
                studentName: 'Alex Rivera',
                studentId: 'STU-2024-003',
                username: 'arivera',
                answers: {
                    'q1': { answer: 1 }
                },
                questionScores: { 'q1': 0 },
                questionRemarks: { 'q1': 'Void due to security violation' },
                obtainedScore: 0,
                status: 'Failed — Security Violation',
                securityViolation: {
                    reason: 'Tab Switching Detected (Attempted window change to external site)',
                    timestamp: '2026-10-05T10:14:22Z'
                },
                startedAt: '2026-10-05T10:05:00Z',
                submittedAt: '2026-10-05T10:14:22Z'
            }
        ];
        this.saveSubmissions(submissions);
    }
};

// Auto-initialize on file load
ExamStore.init();
