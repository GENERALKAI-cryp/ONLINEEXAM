<?php
// ====================================================================
// ONLINE EXAMINATION SYSTEM — TEACHER API CONTROLLER
// Exam Creation, Question Banks, Correction Console & Score Sheets
// ====================================================================

require_once __DIR__ . '/db.php';

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$db = get_db();

switch ($action) {
    case 'get_stats':
        get_teacher_stats($db);
        break;

    case 'get_exams':
        get_teacher_exams($db);
        break;

    case 'get_exam':
        get_single_exam($db);
        break;

    case 'create_exam':
        create_exam($db);
        break;

    case 'get_exam_submissions':
        get_exam_submissions($db);
        break;

    case 'save_correction':
        save_correction($db);
        break;

    case 'get_scoresheet':
        get_scoresheet($db);
        break;

    case 'update_scores':
        update_scores($db);
        break;

    case 'publish_scoresheet':
        publish_scoresheet($db);
        break;

    default:
        json_error('Invalid teacher action specified.');
}

/**
 * Get Teacher Dashboard Stats
 */
function get_teacher_stats($db) {
    $myExams = (int)$db->query("SELECT COUNT(*) FROM `exams`")->fetchColumn();
    $activeExams = (int)$db->query("SELECT COUNT(*) FROM `exams` WHERE `status` = 'Published / Active'")->fetchColumn();
    $pendingCorrections = (int)$db->query("SELECT COUNT(*) FROM `submissions` WHERE `status` IN ('Submitted', 'Under Correction')")->fetchColumn();
    $publishedSheets = (int)$db->query("SELECT COUNT(*) FROM `exams` WHERE `score_sheet_status` = 'Result Published'")->fetchColumn();

    json_response([
        'success' => true,
        'stats' => [
            'myExams'            => $myExams,
            'activeExams'        => $activeExams,
            'pendingCorrections' => $pendingCorrections,
            'publishedSheets'    => $publishedSheets
        ]
    ]);
}

/**
 * Get Exams Roster for Teacher
 */
function get_teacher_exams($db) {
    $stmt = $db->query("
        SELECT e.*, 
               (SELECT COUNT(*) FROM `questions` q WHERE q.`exam_id` = e.`id`) AS `question_count`,
               (SELECT COUNT(*) FROM `submissions` s WHERE s.`exam_id` = e.`id`) AS `submission_count`
        FROM `exams` e
        ORDER BY e.`id` DESC
    ");
    $exams = $stmt->fetchAll();

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
 * Get Single Exam with Questions
 */
function get_single_exam($db) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $stmt = $db->prepare("SELECT * FROM `exams` WHERE `id` = ?");
    $stmt->execute([$id]);
    $exam = $stmt->fetch();
    if (!$exam) {
        json_error('Exam not found.');
    }

    $qStmt = $db->prepare("SELECT * FROM `questions` WHERE `exam_id` = ? ORDER BY `question_number` ASC");
    $qStmt->execute([$id]);
    $questions = $qStmt->fetchAll();

    $formattedQuestions = array_map(function($q) {
        return [
            'id'            => (string)$q['id'],
            'text'          => $q['text'],
            'optionA'       => $q['option_a'],
            'optionB'       => $q['option_b'],
            'optionC'       => $q['option_c'],
            'optionD'       => $q['option_d'],
            'correctAnswer' => (int)$q['correct_answer'],
            'marks'         => (int)$q['marks'],
            'instruction'   => $q['instruction']
        ];
    }, $questions);

    $formattedExam = [
        'id'               => (string)$exam['id'],
        'name'             => $exam['name'],
        'subject'          => $exam['subject'],
        'date'             => $exam['date'],
        'time'             => substr($exam['time'], 0, 5),
        'duration'         => (int)$exam['duration'],
        'totalMarks'       => (int)$exam['total_marks'],
        'examCode'         => $exam['exam_code'],
        'description'      => $exam['description'],
        'rules'            => $exam['rules'],
        'examType'         => $exam['exam_type'],
        'checkingMode'     => $exam['checking_mode'],
        'status'           => $exam['status'],
        'scoreSheetStatus' => $exam['score_sheet_status'],
        'teacherName'      => $exam['teacher_name'],
        'questions'        => $formattedQuestions
    ];

    json_response(['success' => true, 'exam' => $formattedExam]);
}

/**
 * Create New Examination (MCQ or JPEG)
 */
function create_exam($db) {
    $input = get_json_input();
    $name = trim($input['name'] ?? 'Untitled Exam');
    $subject = trim($input['subject'] ?? 'General Studies');
    $date = trim($input['date'] ?? date('Y-m-d'));
    $time = trim($input['time'] ?? '10:00');
    $duration = (int)($input['duration'] ?? 45);
    $totalMarks = (int)($input['totalMarks'] ?? 50);
    $examCode = strtoupper(trim($input['examCode'] ?? 'EX' . rand(1000, 9999)));
    $description = trim($input['description'] ?? '');
    $rules = trim($input['rules'] ?? '');
    $examType = trim($input['examType'] ?? 'mcq');
    $checkingMode = trim($input['checkingMode'] ?? 'auto');
    $status = trim($input['status'] ?? 'Draft');
    $teacherName = trim($input['teacherName'] ?? 'Prof. Robert Smith');
    $questions = $input['questions'] ?? [];

    $user = get_session_user();
    $teacherId = $user ? $user['id'] : null;

    $stmt = $db->prepare("
        INSERT INTO `exams` 
        (`name`, `subject`, `date`, `time`, `duration`, `total_marks`, `exam_code`, `description`, `rules`, `exam_type`, `checking_mode`, `status`, `score_sheet_status`, `teacher_id`, `teacher_name`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Not Evaluated', ?, ?)
    ");
    $stmt->execute([
        $name, $subject, $date, $time, $duration, $totalMarks, $examCode, 
        $description, $rules, $examType, $checkingMode, $status, $teacherId, $teacherName
    ]);
    $examId = (int)$db->lastInsertId();

    // Insert Questions
    if (is_array($questions) && count($questions) > 0) {
        $qStmt = $db->prepare("
            INSERT INTO `questions` 
            (`exam_id`, `question_number`, `text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `marks`, `instruction`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($questions as $idx => $q) {
            $qStmt->execute([
                $examId,
                $idx + 1,
                trim($q['text'] ?? ''),
                $q['optionA'] ?? null,
                $q['optionB'] ?? null,
                $q['optionC'] ?? null,
                $q['optionD'] ?? null,
                (int)($q['correctAnswer'] ?? 0),
                (int)($q['marks'] ?? 10),
                $q['instruction'] ?? null
            ]);
        }
    }

    json_response([
        'success'  => true,
        'message'  => 'Examination successfully created',
        'examId'   => (string)$examId,
        'examCode' => $examCode
    ]);
}

/**
 * Get Submissions for Exam Correction
 */
function get_exam_submissions($db) {
    $examId = isset($_GET['examId']) ? (int)$_GET['examId'] : 0;

    $stmt = $db->prepare("
        SELECT s.*, e.`total_marks` AS `exam_total_marks`, e.`exam_type`
        FROM `submissions` s
        JOIN `exams` e ON s.`exam_id` = e.`id`
        WHERE s.`exam_id` = ?
        ORDER BY s.`id` ASC
    ");
    $stmt->execute([$examId]);
    $subs = $stmt->fetchAll();

    // Get answers for all these submissions
    $formatted = [];
    foreach ($subs as $s) {
        $subId = (int)$s['id'];
        $aStmt = $db->prepare("SELECT * FROM `submission_answers` WHERE `submission_id` = ?");
        $aStmt->execute([$subId]);
        $answers = $aStmt->fetchAll();

        $answersMap = [];
        $scoresMap = [];
        $remarksMap = [];
        foreach ($answers as $a) {
            $answersMap[$a['question_id']] = [
                'answer'   => $a['answer_option'],
                'fileData' => $a['file_path'],
                'fileName' => $a['file_name']
            ];
            if ($a['marks_awarded'] !== null) {
                $scoresMap[$a['question_id']] = (int)$a['marks_awarded'];
            }
            if (!empty($a['teacher_remarks'])) {
                $remarksMap[$a['question_id']] = $a['teacher_remarks'];
            }
        }

        $formatted[] = [
            'id'               => (string)$s['id'],
            'examId'           => (string)$s['exam_id'],
            'studentName'      => $s['student_name'],
            'studentId'        => 'STU-' . str_pad($s['student_id'], 4, '0', STR_PAD_LEFT),
            'username'         => $s['username'],
            'totalMarks'       => (int)$s['total_marks'],
            'obtainedScore'    => (int)$s['obtained_score'],
            'status'           => $s['status'],
            'answers'          => $answersMap,
            'questionScores'   => $scoresMap,
            'questionRemarks'  => $remarksMap,
            'securityViolation'=> !empty($s['security_violation_reason']) ? [
                'reason' => $s['security_violation_reason'],
                'timestamp' => $s['security_violation_time']
            ] : null,
            'startedAt'        => $s['started_at'],
            'submittedAt'      => $s['submitted_at']
        ];
    }

    json_response(['success' => true, 'submissions' => $formatted]);
}

/**
 * Save Active Candidate Correction
 */
function save_correction($db) {
    $input = get_json_input();
    $submissionId = (int)($input['submissionId'] ?? 0);
    $scores = $input['scores'] ?? [];
    $remarks = $input['remarks'] ?? [];
    $totalScore = (int)($input['totalScore'] ?? 0);
    $markAsEvaluated = !empty($input['markAsEvaluated']);

    // Update submission obtained score
    $subStmt = $db->prepare("SELECT * FROM `submissions` WHERE `id` = ?");
    $subStmt->execute([$submissionId]);
    $sub = $subStmt->fetch();
    if (!$sub) {
        json_error('Submission not found.');
    }

    $examStmt = $db->prepare("SELECT `score_sheet_status` FROM `exams` WHERE `id` = ?");
    $examStmt->execute([$sub['exam_id']]);
    $exam = $examStmt->fetch();

    $newStatus = $sub['status'];
    if ($markAsEvaluated && $sub['status'] !== 'Failed — Security Violation') {
        if ($exam && $exam['score_sheet_status'] === 'Result Published') {
            $newStatus = 'Result Published';
        } else {
            $newStatus = 'Under Correction';
        }
    }

    $upd = $db->prepare("UPDATE `submissions` SET `obtained_score` = ?, `status` = ? WHERE `id` = ?");
    $upd->execute([$totalScore, $newStatus, $submissionId]);

    // Save individual question scores and remarks
    foreach ($scores as $qid => $markVal) {
        $mark = (int)$markVal;
        $remark = $remarks[$qid] ?? '';

        // Check if answer row exists
        $check = $db->prepare("SELECT id FROM `submission_answers` WHERE `submission_id` = ? AND `question_id` = ?");
        $check->execute([$submissionId, $qid]);
        if ($check->fetch()) {
            $aUpd = $db->prepare("UPDATE `submission_answers` SET `marks_awarded` = ?, `teacher_remarks` = ? WHERE `submission_id` = ? AND `question_id` = ?");
            $aUpd->execute([$mark, $remark, $submissionId, $qid]);
        } else {
            $aIns = $db->prepare("INSERT INTO `submission_answers` (`submission_id`, `question_id`, `marks_awarded`, `teacher_remarks`) VALUES (?, ?, ?, ?)");
            $aIns->execute([$submissionId, $qid, $mark, $remark]);
        }
    }

    json_response([
        'success' => true,
        'message' => 'Grading saved successfully',
        'obtainedScore' => $totalScore,
        'status' => $newStatus
    ]);
}

/**
 * Get Score Sheet for an Examination
 */
function get_scoresheet($db) {
    $examId = isset($_GET['examId']) ? (int)$_GET['examId'] : 0;

    $stmt = $db->prepare("SELECT * FROM `exams` WHERE `id` = ?");
    $stmt->execute([$examId]);
    $exam = $stmt->fetch();
    if (!$exam) {
        json_error('Exam not found.');
    }

    $sStmt = $db->prepare("SELECT * FROM `submissions` WHERE `exam_id` = ? ORDER BY `id` ASC");
    $sStmt->execute([$examId]);
    $subs = $sStmt->fetchAll();

    $formattedSubs = array_map(function($s) use ($exam) {
        return [
            'id'            => (string)$s['id'],
            'studentName'   => $s['student_name'],
            'studentId'     => 'STU-' . str_pad($s['student_id'], 4, '0', STR_PAD_LEFT),
            'username'      => $s['username'],
            'obtainedScore' => (int)$s['obtained_score'],
            'totalMarks'    => (int)$exam['total_marks'],
            'status'        => $s['status']
        ];
    }, $subs);

    json_response([
        'success'          => true,
        'exam'             => [
            'id'               => (string)$exam['id'],
            'name'             => $exam['name'],
            'subject'          => $exam['subject'],
            'examCode'         => $exam['exam_code'],
            'totalMarks'       => (int)$exam['total_marks'],
            'scoreSheetStatus' => $exam['score_sheet_status']
        ],
        'submissions'      => $formattedSubs
    ]);
}

/**
 * Batch Update Candidate Scores
 */
function update_scores($db) {
    $input = get_json_input();
    $updates = $input['updates'] ?? [];

    $stmt = $db->prepare("UPDATE `submissions` SET `obtained_score` = ? WHERE `id` = ?");
    foreach ($updates as $u) {
        $subId = (int)($u['id'] ?? 0);
        $score = (int)($u['obtainedScore'] ?? 0);
        if ($subId > 0) {
            $stmt->execute([$score, $subId]);
        }
    }

    json_response(['success' => true, 'message' => 'Candidate scores updated successfully']);
}

/**
 * Publish / Unpublish Score Sheet (Strict Section 15 & 24 Compliance)
 */
function publish_scoresheet($db) {
    $input = get_json_input();
    $examId = (int)($input['examId'] ?? 0);
    $publish = !empty($input['publish']);

    $newSheetStatus = $publish ? 'Result Published' : 'Evaluation in Progress';
    $examStatus = $publish ? 'Completed' : 'Published / Active';

    $stmt = $db->prepare("UPDATE `exams` SET `score_sheet_status` = ?, `status` = ? WHERE `id` = ?");
    $stmt->execute([$newSheetStatus, $examStatus, $examId]);

    // Update submissions
    if ($publish) {
        $sStmt = $db->prepare("UPDATE `submissions` SET `status` = 'Result Published' WHERE `exam_id` = ? AND `status` != 'Failed — Security Violation'");
        $sStmt->execute([$examId]);
    } else {
        $sStmt = $db->prepare("UPDATE `submissions` SET `status` = 'Under Correction' WHERE `exam_id` = ? AND `status` = 'Result Published'");
        $sStmt->execute([$examId]);
    }

    json_response([
        'success'          => true,
        'message'          => $publish ? 'Score sheet officially published! Students can now view their scores.' : 'Score sheet retracted to Evaluation in Progress.',
        'scoreSheetStatus' => $newSheetStatus
    ]);
}
