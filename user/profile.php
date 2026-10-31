<?php
// LOFO - Edit Profile

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$pdo = getDBConnection();

/*
|--------------------------------------------------------------------------
| Get Logged-in User ID
|--------------------------------------------------------------------------
*/

$currentUserId = isset($_SESSION['user_id'])
    ? (int) $_SESSION['user_id']
    : 0;

if ($currentUserId <= 0) {
    session_destroy();
    header('Location: ' . url('login.php'));
    exit;
}

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Load User
|--------------------------------------------------------------------------
*/

function getProfileUser(PDO $pdo, int $userId)
{
    $stmt = $pdo->prepare("
        SELECT
            user_id,
            name,
            email,
            role,
            department
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$userId]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$user = getProfileUser($pdo, $currentUserId);

if (!$user) {
    die('User not found.');
}

/*
|--------------------------------------------------------------------------
| Update Profile
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        /*
        |--------------------------------------------------------------------------
        | Validate Name
        |--------------------------------------------------------------------------
        */

        if ($name === '') {
            $error = 'Name is required.';
        } elseif (strlen($name) < 2) {
            $error = 'Name must contain at least 2 characters.';
        } elseif (strlen($name) > 100) {
            $error = 'Name cannot exceed 100 characters.';
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Email
        |--------------------------------------------------------------------------
        */

        elseif ($email === '') {
            $error = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($email) > 150) {
            $error = 'Email cannot exceed 150 characters.';
        }

        /*
        |--------------------------------------------------------------------------
        | Update User
        |--------------------------------------------------------------------------
        */

        else {
            try {
                $check = $pdo->prepare("
                    SELECT user_id
                    FROM users
                    WHERE email = ?
                    AND user_id != ?
                    LIMIT 1
                ");

                $check->execute([
                    $email,
                    $currentUserId
                ]);

                if ($check->fetch(PDO::FETCH_ASSOC)) {
                    $error = 'This email address is already being used by another user.';
                } else {
                    $update = $pdo->prepare("
                        UPDATE users
                        SET
                            name = ?,
                            email = ?
                        WHERE user_id = ?
                    ");

                    $update->execute([
                        $name,
                        $email,
                        $currentUserId
                    ]);

                    $updatedUser = getProfileUser($pdo, $currentUserId);

                    if (!$updatedUser) {
                        $error = 'Profile was updated but could not be reloaded.';
                    } else {
                        $user = $updatedUser;

                        $_SESSION['user_name'] = $updatedUser['name'];
                        $_SESSION['user_email'] = $updatedUser['email'];
                        $_SESSION['user_role'] = $updatedUser['role'];
                        $_SESSION['user_department'] = $updatedUser['department'];

                        $success = 'Profile updated successfully.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Database error while updating profile: ' . $e->getMessage();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Profile Initial
|--------------------------------------------------------------------------
*/

$profileName = $user['name'] ?? 'User';
$initial = strtoupper(substr(trim($profileName), 0, 1));

if ($initial === '') {
    $initial = 'U';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Edit Profile - <?= htmlspecialchars(APP_NAME) ?>
    </title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <!-- LOFO CSS -->
    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(asset('css/style.css')) ?>"
    >

    <style>
        /* Edit Profile Background */
        .profile-page {
            min-height: 100vh;
            background-image:
                linear-gradient(
                    135deg,
                    rgba(15, 23, 42, 0.82),
                    rgba(228, 233, 243, 0.6)
                ),
                url("../assets/images/hero-bg.jpg");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            padding-top: 1px;
            padding-bottom: 30px;
        }

        .profile-page .card {
            background-color: rgba(255, 255, 255, 0.97);
        }

        .profile-page .card-header {
            background-color: rgba(255, 255, 255, 0.98) !important;
        }

        .profile-page .form-control {
            border-radius: 8px;
        }

        .profile-page .btn {
            border-radius: 8px;
        }
    </style>
</head>

<body class="bg-light">

<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<div class="profile-page">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-md-9">

                <div class="card border-0 shadow-sm">

                    <!-- Header -->
                    <div class="card-header bg-white border-0 p-4">
                        <div class="d-flex align-items-center">
                            <div
                                class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3"
                                style="width:60px;height:60px;font-size:24px;"
                            >
                                <?= htmlspecialchars($initial) ?>
                            </div>

                            <div>
                                <h4 class="mb-1">
                                    Edit Profile
                                </h4>

                                <p class="text-muted mb-0">
                                    Update your personal information
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="card-body p-4">

                        <!-- Success Message -->
                        <?php if ($success !== ''): ?>
                            <div
                                class="alert alert-success alert-dismissible fade show"
                                role="alert"
                            >
                                <i class="fa-solid fa-circle-check me-2"></i>
                                <?= htmlspecialchars($success) ?>

                                <button
                                    type="button"
                                    class="btn-close"
                                    data-bs-dismiss="alert"
                                ></button>
                            </div>
                        <?php endif; ?>

                        <!-- Error Message -->
                        <?php if ($error !== ''): ?>
                            <div
                                class="alert alert-danger alert-dismissible fade show"
                                role="alert"
                            >
                                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                                <?= htmlspecialchars($error) ?>

                                <button
                                    type="button"
                                    class="btn-close"
                                    data-bs-dismiss="alert"
                                ></button>
                            </div>
                        <?php endif; ?>

                        <!-- Profile Form -->
                        <form method="POST" action="">

                            <!-- CSRF Token -->
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(csrfToken()) ?>"
                            >

                            <!-- Name -->
                            <div class="mb-4">
                                <label
                                    for="name"
                                    class="form-label fw-semibold"
                                >
                                    <i class="fa-solid fa-user me-1 text-primary"></i>
                                    Full Name
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="name"
                                    name="name"
                                    value="<?= htmlspecialchars($user['name'] ?? '') ?>"
                                    maxlength="100"
                                    required
                                >
                            </div>

                            <!-- Email -->
                            <div class="mb-4">
                                <label
                                    for="email"
                                    class="form-label fw-semibold"
                                >
                                    <i class="fa-solid fa-envelope me-1 text-primary"></i>
                                    Email Address
                                </label>

                                <input
                                    type="email"
                                    class="form-control"
                                    id="email"
                                    name="email"
                                    value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                    maxlength="150"
                                    required
                                >
                            </div>

                            <!-- Department -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="fa-solid fa-building me-1 text-primary"></i>
                                    Department
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    value="<?= htmlspecialchars($user['department'] ?? 'Union Christian College') ?>"
                                    readonly
                                >

                                <small class="text-muted">
                                    Contact an administrator if your department needs to be changed.
                                </small>
                            </div>

                            <!-- Role -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="fa-solid fa-shield-halved me-1 text-primary"></i>
                                    Account Type
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    value="<?= htmlspecialchars(ucfirst($user['role'] ?? 'User')) ?>"
                                    readonly
                                >

                                <small class="text-muted">
                                    Account type can only be changed by an administrator.
                                </small>
                            </div>

                            <!-- Buttons -->
                            <div class="d-flex gap-2">
                                <button
                                    type="submit"
                                    class="btn btn-primary px-4"
                                >
                                    <i class="fa-solid fa-floppy-disk me-1"></i>
                                    Save Changes
                                </button>

                                <a
                                    href="<?= htmlspecialchars(url('user/dashboard.php')) ?>"
                                    class="btn btn-outline-secondary px-4"
                                >
                                    <i class="fa-solid fa-arrow-left me-1"></i>
                                    Cancel
                                </a>
                            </div>
                        </form>

                        <!-- Password Section -->
                        <hr class="my-4">

                        <div class="d-flex justify-content-between align-items-center gap-3">
                            <div>
                                <h6 class="mb-1">
                                    <i class="fa-solid fa-lock me-1 text-warning"></i>
                                    Password
                                </h6>

                                <p class="text-muted small mb-0">
                                    Change your account password separately.
                                </p>
                            </div>

                            <a
                                href="<?= htmlspecialchars(url('user/change_password.php')) ?>"
                                class="btn btn-outline-primary btn-sm"
                            >
                                Change Password
                            </a>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>
</html>