<?php
// ====================================================================
// ONLINE EXAMINATION SYSTEM — ADMIN API CONTROLLER
// Students, Teachers, Exams, Submissions, Violations & Results
// ====================================================================

require_once __DIR__ . '/db.php';

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$db = get_db();

switch ($action) {
    case 'get_stats':
        get_stats($db);
        break;

    case 'get_students':
        get_students($db);
        break;

    case 'save_student':
        save_student($db);
        break;

    case 'toggle_student_status':
        toggle_user_status($db, 'student');
        break;

    case 'delete_student':
        delete_user($db, 'student');
        break;

    case 'get_teachers':
        get_teachers($db);
        break;

    case 'save_teacher':
        save_teacher($db);
        break;

    case 'toggle_teacher_status':
        toggle_user_status($db, 'teacher');
        break;

    case 'delete_teacher':
        delete_user($db, 'teacher');
        break;

    case 'get_exams':
        get_exams($db);
        break;

    case 'update_exam_status':
        update_exam_status($db);
        break;

    case 'delete_exam':
        delete_exam($db);
        break;

    case 'get_submissions':
        get_submissions($db);
        break;

    case 'get_submission_detail':
        get_submission_detail($db);
        break;

    case 'get_results':
        get_results($db);
        break;

    default:
        json_error('Invalid admin action specified.');
}

/**
 * Get Global Admin Statistics
 */
function get_stats($db) {
    $students = (int)$db->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'student'")->fetchColumn();
    $teachers = (int)$db->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'teacher'")->fetchColumn();
    $exams = (int)$db->query("SELECT COUNT(*) FROM `exams`")->fetchColumn();
    $submissions = (int)$db->query("SELECT COUNT(*) FROM `submissions`")->fetchColumn();
    $violations = (int)$db->query("SELECT COUNT(*) FROM `submissions` WHERE `status` = 'Failed — Security Violation'")->fetchColumn();

    json_response([
        'success' => true,
        'counts' => [
            'students'    => $students,
            'teachers'    => $teachers,
            'exams'       => $exams,
            'submissions' => $submissions,
            'violations'  => $violations
        ]
    ]);
}

/**
 * Get Students List
 */
function get_students($db) {
    $stmt = $db->query("
        SELECT `id`, `username`, `full_name` AS `fullName`, `email`, `department`, `status`, `created_at` AS `createdAt`
        FROM `users`
        WHERE `role` = 'student'
        ORDER BY `id` DESC
    ");
    json_response([
        'success'  => true,
        'students' => $stmt->fetchAll()
    ]);
}

/**
 * Save Student (Add / Edit)
 */
function save_student($db) {
    $input = get_json_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $fullName = trim($input['fullName'] ?? '');
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? ($username . '@student.university.edu'));
    $dept = trim($input['department'] ?? 'B.Tech Computer Science');

    if (empty($fullName) || empty($username)) {
        json_error('Name and username are required.');
    }

    if ($id > 0) {
        $stmt = $db->prepare("UPDATE `users` SET `full_name` = ?, `username` = ?, `email` = ?, `department` = ? WHERE `id` = ? AND `role` = 'student'");
        $stmt->execute([$fullName, $username, $email, $dept, $id]);
        json_response(['success' => true, 'message' => 'Student updated successfully']);
    } else {
        $check = $db->prepare("SELECT id FROM `users` WHERE LOWER(`username`) = LOWER(?)");
        $check->execute([$username]);
        if ($check->fetch()) {
            json_error('Username already taken.');
        }

        $hash = password_hash('password', PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO `users` (`full_name`, `username`, `email`, `department`, `role`, `password`, `status`) VALUES (?, ?, ?, ?, 'student', ?, 'Active')");
        $stmt->execute([$fullName, $username, $email, $dept, $hash]);
        json_response(['success' => true, 'message' => 'Student registered successfully', 'id' => (int)$db->lastInsertId()]);
    }
}

/**
 * Get Teachers List
 */
function get_teachers($db) {
    $stmt = $db->query("
        SELECT `id`, `username`, `full_name` AS `fullName`, `email`, `department`, `status`, `created_at` AS `createdAt`
        FROM `users`
        WHERE `role` = 'teacher'
        ORDER BY `id` DESC
    ");
    json_response([
        'success'  => true,
        'teachers' => $stmt->fetchAll()
    ]);
}

/**
 * Save Teacher (Add / Edit)
 */
function save_teacher($db) {
    $input = get_json_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $fullName = trim($input['fullName'] ?? '');
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? ($username . '@university.edu'));
    $dept = trim($input['department'] ?? 'Computer Science');

    if (empty($fullName) || empty($username)) {
        json_error('Name and username are required.');
    }

    if ($id > 0) {
        $stmt = $db->prepare("UPDATE `users` SET `full_name` = ?, `username` = ?, `email` = ?, `department` = ? WHERE `id` = ? AND `role` = 'teacher'");
        $stmt->execute([$fullName, $username, $email, $dept, $id]);
        json_response(['success' => true, 'message' => 'Teacher updated successfully']);
    } else {
        $check = $db->prepare("SELECT id FROM `users` WHERE LOWER(`username`) = LOWER(?)");
        $check->execute([$username]);
        if ($check->fetch()) {
            json_error('Username already taken.');
        }

        $hash = password_hash('password', PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO `users` (`full_name`, `username`, `email`, `department`, `role`, `password`, `status`) VALUES (?, ?, ?, ?, 'teacher', ?, 'Active')");
        $stmt->execute([$fullName, $username, $email, $dept, $hash]);
        json_response(['success' => true, 'message' => 'Teacher profile added successfully', 'id' => (int)$db->lastInsertId()]);
    }
}

/**
 * Toggle User Active/Inactive
 */
function toggle_user_status($db, $role) {
    $input = get_json_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    $stmt = $db->prepare("SELECT `status` FROM `users` WHERE `id` = ? AND `role` = ?");
    $stmt->execute([$id, $role]);
    $user = $stmt->fetch();
    if (!$user) {
        json_error('User not found.');
    }

    $newStatus = ($user['status'] === 'Active') ? 'Inactive' : 'Active';
    $update = $db->prepare("UPDATE `users` SET `status` = ? WHERE `id` = ?");
    $update->execute([$newStatus, $id]);

    json_response(['success' => true, 'newStatus' => $newStatus]);
}

/**
 * Delete User
 */
function delete_user($db, $role) {
    $input = get_json_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    $stmt = $db->prepare("DELETE FROM `users` WHERE `id` = ? AND `role` = ?");
    $stmt->execute([$id, $role]);
    json_response(['success' => true, 'message' => 'User deleted successfully']);
}

/**
 * Get All Examinations
 */
function get_exams($db) {
    $stmt = $db->query("
        SELECT e.*, 
               (SELECT COUNT(*) FROM `questions` q WHERE q.`exam_id` = e.`id`) AS `question_count`,
               (SELECT COUNT(*) FROM `submissions` s WHERE s.`exam_id` = e.`id`) AS `submission_count`
        FROM `exams` e
        ORDER BY e.`id` DESC
    ");
    $exams = $stmt->fetchAll();

    // Map keys for frontend compatibility
    $formatted = array_map(function($e) {
        return [
            'id'               => (string)$e['id'],
            'name'             => $e['name'],
            'subject'          => $e['subject'],
            'date'             => $e['date'],
            'time'             => substr($e['time'], 0, 5),
            'duration'         => (int)$e['duration'],
            'totalMarks'       => (int)$e['total_marks'],
            'examCode'         => $e['exam_code'],
            'description'      => $e['description'],
            'rules'            => $e['rules'],
            'examType'         => $e['exam_type'],
            'checkingMode'     => $e['checking_mode'],
            'status'           => $e['status'],
            'scoreSheetStatus' => $e['score_sheet_status'],
            'teacherName'      => $e['teacher_name'],
            'questionCount'    => (int)$e['question_count'],
            'submissionCount'  => (int)$e['submission_count']
        ];
    }, $exams);

    json_response(['success' => true, 'exams' => $formatted]);
}

/**
 * Update Exam Status (Draft, Published / Active, Completed, Closed)
 */
function update_exam_status($db) {
    $input = get_json_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $status = trim($input['status'] ?? '');

    $allowed = ['Draft', 'Published / Active', 'Completed', 'Closed'];
    if (!in_array($status, $allowed)) {
        json_error('Invalid exam status.');
    }

    $stmt = $db->prepare("UPDATE `exams` SET `status` = ? WHERE `id` = ?");
    $stmt->execute([$status, $id]);
    json_response(['success' => true, 'message' => 'Exam status updated to ' . $status]);
}

/**
 * Delete Exam
 */
function delete_exam($db) {
    $input = get_json_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    $stmt = $db->prepare("DELETE FROM `exams` WHERE `id` = ?");
    $stmt->execute([$id]);
    json_response(['success' => true, 'message' => 'Exam deleted successfully']);
}

/**
 * Get All Submissions (Attempts)
 */
function get_submissions($db) {
    $stmt = $db->query("
        SELECT s.*, e.`name` AS `exam_name`, e.`exam_code`, e.`exam_type`
        FROM `submissions` s
        JOIN `exams` e ON s.`exam_id` = e.`id`
        ORDER BY s.`id` DESC
    ");
    $subs = $stmt->fetchAll();

    $formatted = array_map(function($s) {
        return [
            'id'            => (string)$s['id'],
            'examId'        => (string)$s['exam_id'],
            'examName'      => $s['exam_name'],
            'examCode'      => $s['exam_code'],
            'examType'      => $s['exam_type'],
            'studentName'   => $s['student_name'],
            'studentId'     => 'STU-' . str_pad($s['student_id'], 4, '0', STR_PAD_LEFT),
            'username'      => $s['username'],
            'totalMarks'    => (int)$s['total_marks'],
            'obtainedScore' => (int)$s['obtained_score'],
            'status'        => $s['status'],
            'securityViolation' => !empty($s['security_violation_reason']) ? [
                'reason'    => $s['security_violation_reason'],
                'timestamp' => $s['security_violation_time']
            ] : null,
            'startedAt'     => $s['started_at'],
            'submittedAt'   => $s['submitted_at']
        ];
    }, $subs);

    json_response(['success' => true, 'submissions' => $formatted]);
}

/**
 * Get Single Submission Detail with Question Answers
 */
function get_submission_detail($db) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    $stmt = $db->prepare("
        SELECT s.*, e.`name` AS `exam_name`, e.`exam_code`, e.`exam_type`, e.`total_marks` AS `exam_total_marks`
        FROM `submissions` s
        JOIN `exams` e ON s.`exam_id` = e.`id`
        WHERE s.`id` = ?
    ");
    $stmt->execute([$id]);
    $sub = $stmt->fetch();
    if (!$sub) {
        json_error('Submission not found.');
    }

    // Fetch Questions
    $qStmt = $db->prepare("SELECT * FROM `questions` WHERE `exam_id` = ? ORDER BY `question_number` ASC");
    $qStmt->execute([$sub['exam_id']]);
    $questions = $qStmt->fetchAll();

    // Fetch Answers
    $aStmt = $db->prepare("SELECT * FROM `submission_answers` WHERE `submission_id` = ?");
    $aStmt->execute([$id]);
    $answers = $aStmt->fetchAll();

    $answersMap = [];
    foreach ($answers as $a) {
        $answersMap[$a['question_id']] = [
            'answer'        => $a['answer_option'],
            'filePath'      => $a['file_path'],
            'fileName'      => $a['file_name'],
            'fileSize'      => $a['file_size'],
            'marksAwarded'  => $a['marks_awarded'],
            'teacherRemarks'=> $a['teacher_remarks']
        ];
    }

    json_response([
        'success'    => true,
        'submission' => $sub,
        'questions'  => $questions,
        'answers'    => $answersMap
    ]);
}

/**
 * Get Results Overview for Admin
 */
function get_results($db) {
    $stmt = $db->query("
        SELECT e.*, 
               (SELECT COUNT(*) FROM `submissions` s WHERE s.`exam_id` = e.`id`) AS `total_candidates`,
               (SELECT COUNT(*) FROM `submissions` s WHERE s.`exam_id` = e.`id` AND s.`status` = 'Result Published') AS `published_candidates`,
               (SELECT AVG(`obtained_score`) FROM `submissions` s WHERE s.`exam_id` = e.`id` AND s.`status` = 'Result Published') AS `avg_score`
        FROM `exams` e
        ORDER BY e.`id` DESC
    ");
    $results = $stmt->fetchAll();

    json_response(['success' => true, 'results' => $results]);
}
