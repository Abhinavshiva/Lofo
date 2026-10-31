<?php
// LOFO - Login Page
$pageTitle = "Sign In";
require_once __DIR__ . '/config/app.php';

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? url('admin/index.php') : url('user/dashboard.php')));
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>
<style>
    /* LOFO Login Background */
    .auth-page {
        min-height: 100vh;

        display: flex;
        align-items: center;
        justify-content: center;

        background-image:
            linear-gradient(
                135deg,
                rgba(15, 23, 42, 0.75),
                rgba(37, 99, 235, 0.55)
            ),
            url("assets/images/login-bg.jpg");

        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;

        padding: 30px 15px;
    }

    /* Login Card */
    .auth-card {
        width: 100%;
        max-width: 450px;

        background: rgba(255, 255, 255, 0.97);

        border: 1px solid var(--card-border);

        border-radius: 16px;

        box-shadow: var(--shadow-lg);

        padding: 35px;
    }

    .auth-logo {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 25px;
    }
</style>

<div class="auth-page">

    <div class="container py-5 my-auto">

        <div class="row justify-content-center">

            <div class="col-lg-5 col-md-8">

                <div class="card auth-card shadow-sm border-0">

                    <div class="card-body p-4 p-sm-5">

                        <!-- Logo and Welcome -->
                        <div class="text-center mb-4">

                            <div class="brand-icon mx-auto mb-3"
                                 style="width:48px;height:48px;font-size:1.5rem;">

                                <i class="fa-solid fa-compass-drafting"></i>

                            </div>

                            <h3 class="fw-bold text-dark mb-1">
                                Welcome to LOFO
                            </h3>

                            <p class="text-muted fs-sm">
                                Sign in with your credentials
                            </p>

                        </div>

                        <!-- Quick Demo Credentials Box -->
                        <!-- <div class="p-3 bg-light rounded-3 mb-4 border">

                            <small class="text-uppercase fw-bold text-muted fs-xxs d-block mb-2">

                                <i class="fa-solid fa-bolt text-warning me-1"></i>
                                1-Click Quick Demo Login:

                            </small>

                            <div class="d-flex flex-wrap gap-2">

                                <button type="button"
                                        class="btn btn-outline-primary btn-xs py-1 px-2 fs-xs"
                                        onclick="fillCredentials('admin@ucc.edu.in', 'admin123')">

                                    <i class="fa-solid fa-shield-halved me-1"></i>
                                    Admin

                                </button>

                                <button type="button"
                                        class="btn btn-outline-info btn-xs py-1 px-2 fs-xs"
                                        onclick="fillCredentials('faculty@ucc.edu.in', 'faculty123')">

                                    <i class="fa-solid fa-chalkboard-user me-1"></i>
                                    Faculty

                                </button>

                                <button type="button"
                                        class="btn btn-outline-success btn-xs py-1 px-2 fs-xs"
                                        onclick="fillCredentials('student@ucc.edu.in', 'student123')">

                                    <i class="fa-solid fa-user-graduate me-1"></i>
                                    Student

                                </button>

                            </div>

                        </div> -->

                        <!-- Login Form -->
                        <form action="<?= url('actions/auth_action.php') ?>"
                              method="POST">

                            <input type="hidden"
                                   name="action"
                                   value="login">

                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?= csrfToken() ?>">

                            <!-- Email -->
                            <div class="mb-3">

                                <label for="emailInput"
                                       class="form-label fs-sm fw-medium text-dark">

                                    Email Address

                                </label>

                                <div class="input-group">

                                    <span class="input-group-text bg-white text-muted">
                                        <i class="fa-regular fa-envelope"></i>
                                    </span>

                                    <input type="email"
                                           class="form-control"
                                           id="emailInput"
                                           name="email"
                                           placeholder="e.g. rollno@ucc.edu.in"
                                           required
                                           autofocus>

                                </div>

                            </div>

                            <!-- Password -->
                            <div class="mb-4">

                                <div class="d-flex justify-content-between align-items-center mb-1">

                                    <label for="passwordInput"
                                           class="form-label fs-sm fw-medium text-dark mb-0">

                                        Password

                                    </label>

                                </div>

                                <div class="input-group">

                                    <span class="input-group-text bg-white text-muted">
                                        <i class="fa-solid fa-lock"></i>
                                    </span>

                                    <input type="password"
                                           class="form-control"
                                           id="passwordInput"
                                           name="password"
                                           placeholder="Enter your password"
                                           required>

                                    <button class="btn btn-outline-secondary"
                                            type="button"
                                            id="togglePasswordBtn"
                                            onclick="togglePasswordVisibility()">

                                        <i class="fa-regular fa-eye"
                                           id="togglePasswordIcon"></i>

                                    </button>

                                </div>

                            </div>

                            <!-- Sign In Button -->
                            <button type="submit"
                                    class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">

                                <i class="fa-solid fa-right-to-bracket me-2"></i>
                                Sign In to Account

                            </button>

                        </form>

                        <hr class="my-4">

                        <!-- Register -->
                        <div class="text-center fs-sm">

                            <span class="text-muted">
                                Don't have an account yet?
                            </span>

                            <a href="<?= url('register.php') ?>"
                               class="fw-semibold text-primary text-decoration-none ms-1">

                                Register Now

                            </a>

                        </div>

                    </div>

                </div>

                <!-- Footer -->
                <div class="text-center mt-3 text-white fs-xs">

                    Union Christian College, Aluva &bull; UCC

                </div>

            </div>

        </div>

    </div>

</div>

<script>
function fillCredentials(email, password) {

    document.getElementById('emailInput').value = email;
    document.getElementById('passwordInput').value = password;

}

function togglePasswordVisibility() {

    const input = document.getElementById('passwordInput');
    const icon = document.getElementById('togglePasswordIcon');

    if (input.type === 'password') {

        input.type = 'text';

        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');

    } else {

        input.type = 'password';

        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');

    }

}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>