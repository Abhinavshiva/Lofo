<?php
// LOFO - Application Configuration & Helpers
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

define('APP_NAME', 'LOFO');
define('APP_SUBTITLE', 'Lost & Found Management System');
define('COLLEGE_NAME', 'Union Christian College, Aluva');
define('COLLEGE_DEPT', 'Department of Computer Applications (MCA)');

// Dynamically determine Base URL
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    
    // Normalize path to root of project
    $parts = explode('/', trim($scriptDir, '/'));
    // If inside user/ or admin/ or actions/, trim to project root
    $cleaned = [];
    foreach ($parts as $part) {
        if (in_array($part, ['user', 'admin', 'actions', 'config', 'includes'])) {
            break;
        }
        if ($part !== '') {
            $cleaned[] = $part;
        }
    }
    $basePath = !empty($cleaned) ? '/' . implode('/', $cleaned) : '';
    return rtrim($protocol . $host . $basePath, '/');
}

function url($path = '') {
    $base = getBaseUrl();
    $path = ltrim($path, '/');
    return $path === '' ? $base : $base . '/' . $path;
}

function asset($path = '') {
    return url('assets/' . ltrim($path, '/'));
}

function uploadUrl($path = '') {
    return url('uploads/' . ltrim($path, '/'));
}

// Security & sanitization helpers
function sanitize($data) {
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'info', 'warning'
        'message' => $message
    ];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function currentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'student',
        'department' => $_SESSION['user_department'] ?? '',
        'year_or_designation' => $_SESSION['user_year'] ?? ''
    ];
}

function isAdmin() {
    return isLoggedIn() && (($_SESSION['user_role'] ?? '') === 'admin');
}

function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('warning', 'Please sign in to access this page.');
        header('Location: ' . url('login.php'));
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        setFlash('danger', 'Access denied. Administrator privileges required.');
        header('Location: ' . url('user/dashboard.php'));
        exit;
    }
}

// Generate CSRF token
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}
