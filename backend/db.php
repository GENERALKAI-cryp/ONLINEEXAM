<?php
// ====================================================================
// ONLINE EXAMINATION SYSTEM — DATABASE & UTILITIES LAYER
// PHP & MariaDB / MySQL Configuration for XAMPP
// ====================================================================

// Set error reporting (for production/debugging)
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Start PHP session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Credentials (Standard XAMPP default)
define('DB_HOST', 'localhost');
define('DB_NAME', 'online_exam_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get active PDO connection to MariaDB/MySQL
 * @return PDO
 */
function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Database Connection Failed. Please ensure MariaDB/MySQL is started in XAMPP and "database.sql" is imported into phpMyAdmin.',
                'details' => $e->getMessage()
            ]);
            exit;
        }
    }
    return $pdo;
}

/**
 * Send JSON response and exit
 */
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send JSON error response and exit
 */
function json_error($message, $status_code = 400, $extra = []) {
    $payload = array_merge([
        'success' => false,
        'error'   => $message
    ], $extra);
    json_response($payload, $status_code);
}

/**
 * Parse incoming JSON payload or POST body
 */
function get_json_input() {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

/**
 * Get current session user
 */
function get_session_user() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

/**
 * Require valid session
 */
function require_session() {
    $user = get_session_user();
    if (!$user) {
        json_error('Unauthorized. Please log in.', 401);
    }
    return $user;
}

/**
 * Require specific user role
 */
function require_role($allowed_roles) {
    $user = require_session();
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    if (!in_array($user['role'], $allowed_roles)) {
        json_error('Access Forbidden. Insufficient permissions for role: ' . $user['role'], 403);
    }
    return $user;
}
