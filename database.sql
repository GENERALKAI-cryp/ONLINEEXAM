-- ====================================================================
-- ONLINE EXAMINATION SYSTEM DATABASE SCHEMA
-- Compatible with MariaDB / MySQL 5.7+ / MySQL 8.0+ / XAMPP phpMyAdmin
-- Database: online_exam_db
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `online_exam_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `online_exam_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `submission_answers`;
DROP TABLE IF EXISTS `submissions`;
DROP TABLE IF EXISTS `questions`;
DROP TABLE IF EXISTS `exams`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------------------
-- 1. TABLE: users
-- Roles: admin, teacher, student
-- --------------------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'teacher', 'student') NOT NULL,
  `department` VARCHAR(100) DEFAULT 'Computer Science',
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_role` (`role`),
  INDEX `idx_user_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 2. TABLE: exams
-- Types: mcq, jpeg
-- Statuses: Draft, Published / Active, Completed, Closed
-- Score Sheet Statuses: Not Evaluated, Evaluation in Progress, Result Published
-- --------------------------------------------------------------------
CREATE TABLE `exams` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `subject` VARCHAR(100) NOT NULL,
  `date` DATE NOT NULL,
  `time` TIME NOT NULL,
  `duration` INT NOT NULL COMMENT 'Duration in minutes',
  `total_marks` INT NOT NULL DEFAULT 50,
  `exam_code` VARCHAR(20) NOT NULL UNIQUE,
  `description` TEXT,
  `rules` TEXT,
  `exam_type` ENUM('mcq', 'jpeg') NOT NULL DEFAULT 'mcq',
  `checking_mode` ENUM('auto', 'manual') NOT NULL DEFAULT 'auto',
  `status` ENUM('Draft', 'Published / Active', 'Completed', 'Closed') DEFAULT 'Draft',
  `score_sheet_status` ENUM('Not Evaluated', 'Evaluation in Progress', 'Result Published') DEFAULT 'Not Evaluated',
  `teacher_id` INT DEFAULT NULL,
  `teacher_name` VARCHAR(100) DEFAULT 'Prof. Robert Smith',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_exam_code` (`exam_code`),
  INDEX `idx_exam_status` (`status`),
  CONSTRAINT `fk_exams_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 3. TABLE: questions
-- Multi-choice options or handwritten prompts
-- --------------------------------------------------------------------
CREATE TABLE `questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_id` INT NOT NULL,
  `question_number` INT NOT NULL,
  `text` TEXT NOT NULL,
  `option_a` TEXT,
  `option_b` TEXT,
  `option_c` TEXT,
  `option_d` TEXT,
  `correct_answer` INT DEFAULT 0 COMMENT '0 for Option A, 1 for B, 2 for C, 3 for D',
  `marks` INT NOT NULL DEFAULT 10,
  `instruction` TEXT,
  INDEX `idx_question_exam` (`exam_id`),
  CONSTRAINT `fk_questions_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 4. TABLE: submissions
-- Candidate attempt headers & proctoring logs
-- --------------------------------------------------------------------
CREATE TABLE `submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `student_name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `total_marks` INT NOT NULL,
  `obtained_score` INT DEFAULT 0,
  `status` ENUM('Attended', 'Submitted', 'Under Correction', 'Result Published', 'Failed — Security Violation') DEFAULT 'Attended',
  `security_violation_reason` VARCHAR(255) DEFAULT NULL,
  `security_violation_time` DATETIME DEFAULT NULL,
  `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `submitted_at` DATETIME DEFAULT NULL,
  INDEX `idx_sub_exam` (`exam_id`),
  INDEX `idx_sub_student` (`student_id`),
  INDEX `idx_sub_status` (`status`),
  CONSTRAINT `fk_submissions_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 5. TABLE: submission_answers
-- Itemized question answers, JPEG image paths, awarded marks & remarks
-- --------------------------------------------------------------------
CREATE TABLE `submission_answers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `submission_id` INT NOT NULL,
  `question_id` INT NOT NULL,
  `answer_option` INT DEFAULT NULL COMMENT '0, 1, 2, 3 for MCQ',
  `file_path` VARCHAR(255) DEFAULT NULL COMMENT 'Relative path to uploaded JPEG sheet',
  `file_name` VARCHAR(150) DEFAULT NULL,
  `file_size` INT DEFAULT NULL,
  `marks_awarded` INT DEFAULT NULL,
  `teacher_remarks` VARCHAR(255) DEFAULT NULL,
  `answered_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ans_sub` (`submission_id`),
  INDEX `idx_ans_question` (`question_id`),
  CONSTRAINT `fk_answers_sub` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- SEED INITIAL PRODUCTION DATA
-- Passwords below default to "password" (hashed using password_hash('password', PASSWORD_BCRYPT))
-- ====================================================================

-- 1. Users (Admin, Teachers, Students)
-- Hash: $2y$10$4y9pB2z23sBvW98sXmJgG.fF1jJzH1p7L4y2B8yQhLqWkZmC6Q8y2 (equals "password")
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `role`, `department`, `status`) VALUES
(1, 'admin', '$2y$10$4y9pB2z23sBvW98sXmJgG.fF1jJzH1p7L4y2B8yQhLqWkZmC6Q8y2', 'Dr. Arthur Vance (Dean)', 'admin@university.edu', 'admin', 'Academic Administration', 'Active'),
(2, 'rsmith', '$2y$10$4y9pB2z23sBvW98sXmJgG.fF1jJzH1p7L4y2B8yQhLqWkZmC6Q8y2', 'Prof. Robert Smith', 'r.smith@university.edu', 'teacher', 'Computer Science', 'Active'),
-- 1. Users (Standard university roles)
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `role`, `department`, `status`) VALUES
(1, 'admin', '$2y$10$4y9pB2z23sBvW98sXmJgG.fF1jJzH1p7L4y2B8yQhLqWkZmC6Q8y2', 'Dean / Admin Office', 'admin@university.edu', 'admin', 'Academic Administration', 'Active'),
(2, 'rsmith', '$2y$10$4y9pB2z23sBvW98sXmJgG.fF1jJzH1p7L4y2B8yQhLqWkZmC6Q8y2', 'Prof. Robert Smith', 'r.smith@university.edu', 'teacher', 'Computer Science', 'Active'),
(3, 'erostova', '$2y$10$4y9pB2z23sBvW98sXmJgG.fF1jJzH1p7L4y2B8yQhLqWkZmC6Q8y2', 'Dr. Elena Rostova', 'e.rostova@university.edu', 'teacher', 'Information Security', 'Active'),
(4, 'johndoe', '$2y$10$4y9pB2z23sBvW98sXmJgG.fF1jJzH1p7L4y2B8yQhLqWkZmC6Q8y2', 'John Doe', 'john.doe@student.university.edu', 'student', 'B.Tech Computer Science', 'Active'),
(5, 'janesmith', '$2y$10$4y9pB2z23sBvW98sXmJgG.fF1jJzH1p7L4y2B8yQhLqWkZmC6Q8y2', 'Jane Smith', 'jane.smith@student.university.edu', 'student', 'B.Tech Information Technology', 'Active');

-- 2. Official Scheduled Exams
INSERT INTO `exams` (`id`, `name`, `subject`, `date`, `time`, `duration`, `total_marks`, `exam_code`, `description`, `rules`, `exam_type`, `checking_mode`, `status`, `score_sheet_status`, `teacher_id`, `teacher_name`) VALUES
(1, 'Database Management Systems Midterm', 'Database Systems', '2026-10-15', '10:00:00', 30, 50, 'DBMS2027', 
 'Mid-term assessment covering relational algebra, SQL normal forms, and ACID transaction properties.', 
 '1. Examination duration is strictly 30 minutes.\n2. All 5 multiple choice questions are mandatory (10 marks each).\n3. Tab switching or navigating away from this window will automatically fail and terminate your examination.\n4. No negative marking.', 
 'mcq', 'auto', 'Published / Active', 'Not Evaluated', 2, 'Prof. Robert Smith'),

(2, 'Network Security & Cryptography Assessment', 'Information Security', '2026-10-18', '14:00:00', 45, 40, 'NETSEC99', 
 'Descriptive examination requiring handwritten answers, cryptographic diagrams, and manual upload of JPEG sheets.', 
 '1. Write your answers clearly on clean white paper using blue or black ink.\n2. Take clear JPEG photographs or scan your answer sheets and upload them for each corresponding question.\n3. Maximum image size is 5MB per upload.\n4. Ensure proper orientation and legibility before final submission.\n5. Switching browser tabs will result in automatic termination for Security Violation.', 
 'jpeg', 'manual', 'Published / Active', 'Not Evaluated', 3, 'Dr. Elena Rostova');

-- 3. Exam Questions
-- DBMS2027 Questions (MCQ)
INSERT INTO `questions` (`id`, `exam_id`, `question_number`, `text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `marks`, `instruction`) VALUES
(1, 1, 1, 'Which normal form eliminates partial functional dependencies on the primary key?', 'First Normal Form (1NF)', 'Second Normal Form (2NF)', 'Third Normal Form (3NF)', 'Boyce-Codd Normal Form (BCNF)', 1, 10, NULL),
(2, 1, 2, 'In the ACID properties of database transactions, what does the "I" stand for?', 'Integrity', 'Indexation', 'Isolation', 'Immutability', 2, 10, NULL),
(3, 1, 3, 'Which SQL clause is used to filter group results after aggregation?', 'WHERE', 'HAVING', 'GROUP BY', 'FILTER', 1, 10, NULL),
(4, 1, 4, 'Which data structure is most commonly used in database storage indexing?', 'B+ Tree', 'Binary Search Tree', 'Stack', 'Breadth-First Graph', 0, 10, NULL),
(5, 1, 5, 'What is a foreign key in relational databases?', 'A primary key of an external database', 'An encrypted security token', 'A column referencing the primary key of another table', 'A key imported from a foreign jurisdiction', 2, 10, NULL),

-- NETSEC99 Questions (JPEG handwritten)
(6, 2, 1, 'Explain the working of the RSA asymmetric cryptographic algorithm with a mathematical derivation and diagram. Upload your handwritten sheet as JPEG.', NULL, NULL, NULL, NULL, 0, 20, 'Provide complete mathematical proof, key generation, encryption, and decryption steps.'),
(7, 2, 2, 'Illustrate the SSL/TLS 1.3 Handshake Protocol. Show the messages exchanged between client and server for session key agreement.', NULL, NULL, NULL, NULL, 0, 20, 'Draw the sequence diagram with Diffie-Hellman parameters exchange and certificate verification.');

-- Note: submissions and submission_answers start completely clean and empty for live operations.

