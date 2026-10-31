<?php
// LOFO - Authentication Handler (Login & Register)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('login.php'));
    exit;
}

$action = $_POST['action'] ?? '';
$csrf = $_POST['csrf_token'] ?? '';

if (!verifyCsrfToken($csrf)) {
    setFlash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
    header('Location: ' . url('login.php'));
    exit;
}

$pdo = getDBConnection();

// --- 1. LOGIN HANDLER ---
if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        setFlash('danger', 'Please provide both your college email and password.');
        header('Location: ' . url('login.php'));
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            setFlash('danger', 'Invalid email address or password. Please verify your credentials.');
            header('Location: ' . url('login.php'));
            exit;
        }

        if ($user['account_status'] === 'suspended') {
            setFlash('danger', 'Your account has been suspended by the administrator. Please contact UCC administration.');
            header('Location: ' . url('login.php'));
            exit;
        }

        // Set session variables
        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_department'] = $user['department'];
        $_SESSION['user_year'] = $user['year_or_designation'];

        setFlash('success', "Welcome back, " . htmlspecialchars($user['name']) . "!");

        if ($user['role'] === 'admin') {
            header('Location: ' . url('admin/index.php'));
        } else {
            header('Location: ' . url('user/dashboard.php'));
        }
        exit;
    } catch (PDOException $e) {
        setFlash('danger', 'Database error: ' . $e->getMessage());
        header('Location: ' . url('login.php'));
        exit;
    }
}

// --- 2. REGISTRATION HANDLER ---
if ($action === 'register') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['student', 'faculty']) ? $_POST['role'] : 'student';
    $department = trim($_POST['department'] ?? '');
    $yearOrDesig = trim($_POST['year_or_designation'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    // Validations
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        setFlash('danger', 'All required fields must be filled.');
        header('Location: ' . url('register.php'));
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash('danger', 'Please enter a valid email address.');
        header('Location: ' . url('register.php'));
        exit;
    }

    if (strlen($password) < 6) {
        setFlash('danger', 'Password must be at least 6 characters long.');
        header('Location: ' . url('register.php'));
        exit;
    }

    if ($password !== $passwordConfirm) {
        setFlash('danger', 'Passwords do not match.');
        header('Location: ' . url('register.php'));
        exit;
    }

    try {
        // Check if email already registered
        $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            setFlash('danger', 'This email is already registered. Please sign in or use another email.');
            header('Location: ' . url('register.php'));
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, department, year_or_designation, account_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$name, $email, $phone, $hashedPassword, $role, $department, $yearOrDesig]);
        $newUserId = (int)$pdo->lastInsertId();

        // Welcome notification
        createNotification(
            $pdo,
            $newUserId,
            'Welcome to LOFO!',
            'Your account has been registered successfully. You can now report lost or found items and submit ownership claims.',
            'General',
            'user/dashboard.php'
        );

        // Auto login
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = $role;
        $_SESSION['user_department'] = $department;
        $_SESSION['user_year'] = $yearOrDesig;

        setFlash('success', "Registration successful! Welcome to LOFO, $name.");
        header('Location: ' . url('user/dashboard.php'));
        exit;

    } catch (PDOException $e) {
        setFlash('danger', 'Database error: ' . $e->getMessage());
        header('Location: ' . url('register.php'));
        exit;
    }
}

header('Location: ' . url('index.php'));
exit;
