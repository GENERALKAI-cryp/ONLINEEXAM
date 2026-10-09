<?php
// ====================================================================
// ONLINE EXAMINATION SYSTEM — STUDENT API CONTROLLER
// Code Entry, Exam Taking, JPEG Answer Uploads, Proctoring & Results
// ====================================================================

require_once __DIR__ . '/db.php';

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$db = get_db();

switch ($action) {
    case 'get_stats':
        get_student_stats($db);
        break;

    case 'verify_code':
        verify_exam_code($db);
        break;

    case 'get_my_exams':
        get_my_exams($db);
        break;

    case 'start_exam':
        start_exam($db);
        break;

    case 'save_answer':
        save_answer($db);
        break;

    case 'upload_jpeg':
        upload_jpeg_answer($db);
        break;

    case 'clear_answer':
        clear_answer($db);
        break;

    case 'submit_exam':
        submit_exam($db);
        break;

    case 'security_violation':
        record_security_violation($db);
        break;

    case 'get_results':
        get_results($db);
        break;

    case 'get_result_detail':
        get_result_detail($db);
        break;

    default:
        json_error('Invalid student action specified.');
}

/**
 * Get Student Portal Stats
 */
function get_student_stats($db) {
    $user = get_session_user();
    $studentId = $user ? (int)$user['id'] : 5; // default to demo johndoe

    $attended = (int)$db->query("SELECT COUNT(*) FROM `submissions` WHERE `student_id` = {$studentId}")->fetchColumn();
    $pending = (int)$db->query("SELECT COUNT(*) FROM `submissions` WHERE `student_id` = {$studentId} AND `status` IN ('Submitted', 'Under Correction', 'Attended')")->fetchColumn();
    $published = (int)$db->query("SELECT COUNT(*) FROM `submissions` WHERE `student_id` = {$studentId} AND `status` = 'Result Published'")->fetchColumn();
    $violations = (int)$db->query("SELECT COUNT(*) FROM `submissions` WHERE `student_id` = {$studentId} AND `status` = 'Failed — Security Violation'")->fetchColumn();

    json_response([
        'success' => true,
        'stats' => [
            'attended'   => $attended,
            'pending'    => $pending,
            'published'  => $published,
            'violations' => $violations
        ]
    ]);
}

/**
 * Verify Exam Access Code (Section 17 & 18)
 */
function verify_exam_code($db) {
    $code = isset($_GET['code']) ? strtoupper(trim($_GET['code'])) : '';
    if (empty($code)) {
        json_error('Please supply an examination access code.');
    }

    $stmt = $db->prepare("SELECT * FROM `exams` WHERE UPPER(`exam_code`) = ? LIMIT 1");
    $stmt->execute([$code]);
    $exam = $stmt->fetch();

    if (!$exam) {
        json_error('Invalid Examination Code. No active examination matches this code.', 404);
    }

    if ($exam['status'] === 'Draft') {
        json_error('This examination is currently in Draft status and is not yet open to students.', 403);
    }

    if ($exam['status'] === 'Closed') {
        json_error('This examination has concluded and is closed.', 403);
    }

    $qCount = (int)$db->query("SELECT COUNT(*) FROM `questions` WHERE `exam_id` = {$exam['id']}")->fetchColumn();

    // Secure payload: Do NOT expose answer keys to student
    json_response([
        'success' => true,
        'exam'    => [
            'id'            => (string)$exam['id'],
            'name'          => $exam['name'],
            'subject'       => $exam['subject'],
            'date'          => $exam['date'],
            'time'          => substr($exam['time'], 0, 5),
            'duration'      => (int)$exam['duration'],
            'totalMarks'    => (int)$exam['total_marks'],
            'examCode'      => $exam['exam_code'],
            'description'   => $exam['description'],
            'rules'         => $exam['rules'],
            'examType'      => $exam['exam_type'],
            'checkingMode'  => $exam['checking_mode'],
            'questionCount' => $qCount
        ]
    ]);
}

/**
 * Get Student's Submissions Roster
 */
function get_my_exams($db) {
    $user = get_session_user();
    $studentId = $user ? (int)$user['id'] : 5;

    $stmt = $db->prepare("
        SELECT s.*, e.`name` AS `exam_name`, e.`subject`, e.`exam_code`, e.`exam_type`, e.`date` AS `exam_date`, e.`score_sheet_status`
        FROM `submissions` s
        JOIN `exams` e ON s.`exam_id` = e.`id`
        WHERE s.`student_id` = ?
        ORDER BY s.`id` DESC
    ");
    $stmt->execute([$studentId]);
    $subs = $stmt->fetchAll();

    $formatted = array_map(function($s) {
        $isViolation = ($s['status'] === 'Failed — Security Violation');
        $isPublished = ($s['status'] === 'Result Published' || ($s['score_sheet_status'] === 'Result Published' && !$isViolation));

        return [
            'id'               => (string)$s['id'],
            'examId'           => (string)$s['exam_id'],
            'examName'         => $s['exam_name'],
            'subject'          => $s['subject'],
            'examCode'         => $s['exam_code'],
            'examType'         => $s['exam_type'],
            'totalMarks'       => (int)$s['total_marks'],
            'obtainedScore'    => $isPublished ? (int)$s['obtained_score'] : null, // HIDDEN if not published!
            'status'           => $s['status'],
            'scoreSheetStatus' => $s['score_sheet_status'],
            'submittedAt'      => $s['submitted_at'],
            'examDate'         => $s['exam_date']
        ];
    }, $subs);

    json_response(['success' => true, 'submissions' => $formatted]);
}

/**
 * Start Exam Session (Returns Questions & Sets Up Submission)
 */
function start_exam($db) {
    $input = get_json_input();
    $examId = (int)($input['examId'] ?? 0);

    $stmt = $db->prepare("SELECT * FROM `exams` WHERE `id` = ?");
    $stmt->execute([$examId]);
    $exam = $stmt->fetch();
    if (!$exam) {
        json_error('Examination not found.', 404);
    }

    $user = get_session_user();
    $studentId = $user ? (int)$user['id'] : 5;
    $studentName = $user ? $user['fullName'] : 'John Doe';
    $username = $user ? $user['username'] : 'johndoe';

    // Check existing submission
    $check = $db->prepare("SELECT * FROM `submissions` WHERE `exam_id` = ? AND `student_id` = ? ORDER BY `id` DESC LIMIT 1");
    $check->execute([$examId, $studentId]);
    $existing = $check->fetch();

    if ($existing) {
        if ($existing['status'] === 'Failed — Security Violation') {
            json_error('This examination attempt was terminated due to a security violation.', 403, [
                'securityViolation' => [
                    'reason'    => $existing['security_violation_reason'],
                    'timestamp' => $existing['security_violation_time']
                ]
            ]);
        }
        if (in_array($existing['status'], ['Submitted', 'Result Published', 'Under Correction'])) {
            json_error('You have already submitted this examination.', 403);
        }
        $submissionId = (int)$existing['id'];
    } else {
        $ins = $db->prepare("
            INSERT INTO `submissions` (`exam_id`, `student_id`, `student_name`, `username`, `total_marks`, `status`, `started_at`)
            VALUES (?, ?, ?, ?, ?, 'Attended', NOW())
        ");
        $ins->execute([$examId, $studentId, $studentName, $username, (int)$exam['total_marks']]);
        $submissionId = (int)$db->lastInsertId();
    }

    // Fetch Questions (strip correct_answer key for security!)
    $qStmt = $db->prepare("SELECT `id`, `question_number`, `text`, `option_a`, `option_b`, `option_c`, `option_d`, `marks`, `instruction` FROM `questions` WHERE `exam_id` = ? ORDER BY `question_number` ASC");
    $qStmt->execute([$examId]);
    $questions = $qStmt->fetchAll();

    $formattedQuestions = array_map(function($q) {
        return [
            'id'          => (string)$q['id'],
            'text'        => $q['text'],
            'optionA'     => $q['option_a'],
            'optionB'     => $q['option_b'],
            'optionC'     => $q['option_c'],
            'optionD'     => $q['option_d'],
            'marks'       => (int)$q['marks'],
            'instruction' => $q['instruction']
        ];
    }, $questions);

    // Fetch already answered keys
    $aStmt = $db->prepare("SELECT * FROM `submission_answers` WHERE `submission_id` = ?");
    $aStmt->execute([$submissionId]);
    $answers = $aStmt->fetchAll();
    $answersMap = [];
    foreach ($answers as $a) {
        $answersMap[(string)$a['question_id']] = [
            'answer'   => $a['answer_option'],
            'fileData' => $a['file_path'],
            'fileName' => $a['file_name']
        ];
    }

    json_response([
        'success'      => true,
        'submissionId' => (string)$submissionId,
        'exam'         => [
            'id'         => (string)$exam['id'],
            'name'       => $exam['name'],
            'subject'    => $exam['subject'],
            'duration'   => (int)$exam['duration'],
            'totalMarks' => (int)$exam['total_marks'],
            'examType'   => $exam['exam_type']
        ],
        'questions'    => $formattedQuestions,
        'savedAnswers' => $answersMap
    ]);
}

/**
 * Save Student Answer (MCQ)
 */
function save_answer($db) {
    $input = get_json_input();
    $subId = (int)($input['submissionId'] ?? 0);
    $qId   = (int)($input['questionId'] ?? 0);
    $opt   = isset($input['answer']) ? (int)$input['answer'] : null;

    // Verify submission is not terminated
    $checkSub = $db->prepare("SELECT status FROM `submissions` WHERE `id` = ?");
    $checkSub->execute([$subId]);
    $sub = $checkSub->fetch();
    if ($sub && $sub['status'] === 'Failed — Security Violation') {
        json_error('Session terminated for security violation.', 403);
    }

    $stmt = $db->prepare("SELECT id FROM `submission_answers` WHERE `submission_id` = ? AND `question_id` = ?");
    $stmt->execute([$subId, $qId]);
    if ($stmt->fetch()) {
        $upd = $db->prepare("UPDATE `submission_answers` SET `answer_option` = ?, `answered_at` = NOW() WHERE `submission_id` = ? AND `question_id` = ?");
        $upd->execute([$opt, $subId, $qId]);
    } else {
        $ins = $db->prepare("INSERT INTO `submission_answers` (`submission_id`, `question_id`, `answer_option`, `answered_at`) VALUES (?, ?, ?, NOW())");
        $ins->execute([$subId, $qId, $opt]);
    }

    json_response(['success' => true]);
}

/**
 * Upload Handwritten JPEG Sheet (Section 21)
 */
function upload_jpeg_answer($db) {
    $subId = (int)($_POST['submissionId'] ?? 0);
    $qId   = (int)($_POST['questionId'] ?? 0);

    if (!isset($_FILES['jpegFile']) || $_FILES['jpegFile']['error'] !== UPLOAD_ERR_OK) {
        json_error('No image file received or upload error occurred.');
    }

    $file = $_FILES['jpegFile'];
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/pjpeg'])) {
        json_error('Only valid JPEG image files are accepted.');
    }

    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeName = 'sheet_sub' . $subId . '_q' . $qId . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . $safeName;
    $relPath = 'uploads/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        json_error('Failed to store uploaded JPEG sheet.');
    }

    // Save in DB
    $stmt = $db->prepare("SELECT id FROM `submission_answers` WHERE `submission_id` = ? AND `question_id` = ?");
    $stmt->execute([$subId, $qId]);
    if ($stmt->fetch()) {
        $upd = $db->prepare("UPDATE `submission_answers` SET `file_path` = ?, `file_name` = ?, `file_size` = ?, `answered_at` = NOW() WHERE `submission_id` = ? AND `question_id` = ?");
        $upd->execute([$relPath, $file['name'], $file['size'], $subId, $qId]);
    } else {
        $ins = $db->prepare("INSERT INTO `submission_answers` (`submission_id`, `question_id`, `file_path`, `file_name`, `file_size`, `answered_at`) VALUES (?, ?, ?, ?, ?, NOW())");
        $ins->execute([$subId, $qId, $relPath, $file['name'], $file['size']]);
    }

    json_response([
        'success'  => true,
        'filePath' => $relPath,
        'fileName' => $file['name'],
        'fileSize' => $file['size']
    ]);
}

/**
 * Clear Student Answer
 */
function clear_answer($db) {
    $input = get_json_input();
    $subId = (int)($input['submissionId'] ?? 0);
    $qId   = (int)($input['questionId'] ?? 0);

    $del = $db->prepare("DELETE FROM `submission_answers` WHERE `submission_id` = ? AND `question_id` = ?");
    $del->execute([$subId, $qId]);
    json_response(['success' => true]);
}

/**
 * Submit Exam Attempt
 */
function submit_exam($db) {
    $input = get_json_input();
    $subId = (int)($input['submissionId'] ?? 0);

    $sStmt = $db->prepare("SELECT s.*, e.`exam_type`, e.`checking_mode` FROM `submissions` s JOIN `exams` e ON s.`exam_id` = e.`id` WHERE s.`id` = ?");
    $sStmt->execute([$subId]);
    $sub = $sStmt->fetch();

    if (!$sub) {
        json_error('Submission not found.');
    }

    if ($sub['status'] === 'Failed — Security Violation') {
        json_response(['success' => true, 'status' => 'Failed — Security Violation']);
    }

    $finalStatus = 'Submitted';
    $autoScore = 0;

    // Automatic Checking for MCQ
    if ($sub['exam_type'] === 'mcq' && $sub['checking_mode'] === 'auto') {
        $qStmt = $db->prepare("SELECT `id`, `correct_answer`, `marks` FROM `questions` WHERE `exam_id` = ?");
        $qStmt->execute([$sub['exam_id']]);
        $questions = $qStmt->fetchAll();

        foreach ($questions as $q) {
            $aStmt = $db->prepare("SELECT `answer_option` FROM `submission_answers` WHERE `submission_id` = ? AND `question_id` = ?");
            $aStmt->execute([$subId, $q['id']]);
            $ans = $aStmt->fetch();

            if ($ans && $ans['answer_option'] !== null && (int)$ans['answer_option'] === (int)$q['correct_answer']) {
                $qMarks = (int)$q['marks'];
                $autoScore += $qMarks;
                $updA = $db->prepare("UPDATE `submission_answers` SET `marks_awarded` = ?, `teacher_remarks` = 'Correct' WHERE `submission_id` = ? AND `question_id` = ?");
                $updA->execute([$qMarks, $subId, $q['id']]);
            } else {
                $updA = $db->prepare("UPDATE `submission_answers` SET `marks_awarded` = 0, `teacher_remarks` = 'Incorrect' WHERE `submission_id` = ? AND `question_id` = ?");
                $updA->execute([$subId, $q['id']]);
            }
        }
        $finalStatus = 'Submitted';
    } else {
        $finalStatus = 'Under Correction';
    }

    $upd = $db->prepare("UPDATE `submissions` SET `obtained_score` = ?, `status` = ?, `submitted_at` = NOW() WHERE `id` = ?");
    $upd->execute([$autoScore, $finalStatus, $subId]);

    json_response([
        'success' => true,
        'status'  => $finalStatus,
        'message' => 'Examination submitted successfully'
    ]);
}

/**
 * Record Tab Switching Violation (Section 22)
 */
function record_security_violation($db) {
    $input = get_json_input();
    $subId = (int)($input['submissionId'] ?? 0);
    $reason = trim($input['reason'] ?? 'Tab Switching Detected');

    $stmt = $db->prepare("
        UPDATE `submissions` 
        SET `status` = 'Failed — Security Violation', 
            `obtained_score` = 0, 
            `security_violation_reason` = ?, 
            `security_violation_time` = NOW(),
            `submitted_at` = NOW()
        WHERE `id` = ?
    ");
    $stmt->execute([$reason, $subId]);

    json_response([
        'success' => true,
        'status'  => 'Failed — Security Violation'
    ]);
}

/**
 * Get Student Results (Section 23 & 24)
 */
function get_results($db) {
    $user = get_session_user();
    $studentId = $user ? (int)$user['id'] : 5;

    $stmt = $db->prepare("
        SELECT s.*, e.`name` AS `exam_name`, e.`subject`, e.`exam_code`, e.`exam_type`, e.`date` AS `exam_date`, e.`score_sheet_status`
        FROM `submissions` s
        JOIN `exams` e ON s.`exam_id` = e.`id`
        WHERE s.`student_id` = ?
        ORDER BY s.`id` DESC
    ");
    $stmt->execute([$studentId]);
    $subs = $stmt->fetchAll();

    $formatted = array_map(function($s) {
        $isViolation = ($s['status'] === 'Failed — Security Violation');
        $isPublished = ($s['status'] === 'Result Published' || ($s['score_sheet_status'] === 'Result Published' && !$isViolation));

        return [
            'id'               => (string)$s['id'],
            'examId'           => (string)$s['exam_id'],
            'examName'         => $s['exam_name'],
            'subject'          => $s['subject'],
            'examCode'         => $s['exam_code'],
            'examType'         => $s['exam_type'],
            'totalMarks'       => (int)$s['total_marks'],
            // STRICT SECTION 24: Score only exposed if published or violation
            'obtainedScore'    => ($isPublished || $isViolation) ? (int)$s['obtained_score'] : null,
            'status'           => $s['status'],
            'scoreSheetStatus' => $s['score_sheet_status'],
            'isPublished'      => $isPublished,
            'isViolation'      => $isViolation,
            'securityViolation'=> $isViolation ? [
                'reason'    => $s['security_violation_reason'],
                'timestamp' => $s['security_violation_time']
            ] : null,
            'submittedAt'      => $s['submitted_at'],
            'examDate'         => $s['exam_date']
        ];
    }, $subs);

    json_response(['success' => true, 'results' => $formatted]);
}

/**
 * Get Score Sheet Breakdown (Section 24: ONLY if published)
 */
function get_result_detail($db) {
    $subId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    $stmt = $db->prepare("
        SELECT s.*, e.`name` AS `exam_name`, e.`subject`, e.`exam_code`, e.`exam_type`, e.`score_sheet_status`, e.`teacher_name`
        FROM `submissions` s
        JOIN `exams` e ON s.`exam_id` = e.`id`
        WHERE s.`id` = ?
    ");
    $stmt->execute([$subId]);
    $sub = $stmt->fetch();

    if (!$sub) {
        json_error('Result record not found.');
    }

    $isViolation = ($sub['status'] === 'Failed — Security Violation');
    $isPublished = ($sub['status'] === 'Result Published' || ($sub['score_sheet_status'] === 'Result Published' && !$isViolation));

    if (!$isPublished && !$isViolation) {
        json_error('Access Denied. This score sheet is pending instructor review and publication.', 403);
    }

    // Fetch questions and answers
    $qStmt = $db->prepare("SELECT * FROM `questions` WHERE `exam_id` = ? ORDER BY `question_number` ASC");
    $qStmt->execute([$sub['exam_id']]);
    $questions = $qStmt->fetchAll();

    $aStmt = $db->prepare("SELECT * FROM `submission_answers` WHERE `submission_id` = ?");
    $aStmt->execute([$subId]);
    $answers = $aStmt->fetchAll();

    $answersMap = [];
    foreach ($answers as $a) {
        $answersMap[$a['question_id']] = $a;
    }

    $itemized = [];
    foreach ($questions as $q) {
        $ans = $answersMap[$q['id']] ?? null;
        $itemized[] = [
            'questionId'     => (string)$q['id'],
            'questionNumber' => (int)$q['question_number'],
            'text'           => $q['text'],
            'optionA'        => $q['option_a'],
            'optionB'        => $q['option_b'],
            'optionC'        => $q['option_c'],
            'optionD'        => $q['option_d'],
            'correctAnswer'  => (int)$q['correct_answer'],
            'marks'          => (int)$q['marks'],
            'studentAnswer'  => $ans ? $ans['answer_option'] : null,
            'filePath'       => $ans ? $ans['file_path'] : null,
            'fileName'       => $ans ? $ans['file_name'] : null,
            'marksAwarded'   => $ans ? (int)$ans['marks_awarded'] : 0,
            'teacherRemarks' => $ans ? $ans['teacher_remarks'] : ''
        ];
    }

    json_response([
        'success'        => true,
        'submission'     => [
            'id'            => (string)$sub['id'],
            'examName'      => $sub['exam_name'],
            'studentName'   => $sub['student_name'],
            'studentId'     => 'STU-' . str_pad($sub['student_id'], 4, '0', STR_PAD_LEFT),
            'examCode'      => $sub['exam_code'],
            'totalMarks'    => (int)$sub['total_marks'],
            'obtainedScore' => (int)$sub['obtained_score'],
            'teacherName'   => $sub['teacher_name'],
            'examType'      => $sub['exam_type']
        ],
        'questionsBreakdown' => $itemized
    ]);
}
