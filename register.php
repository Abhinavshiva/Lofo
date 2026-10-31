<?php
// LOFO - Registration Page
$pageTitle = "Create an Account";
require_once __DIR__ . '/config/app.php';

if (isLoggedIn()) {
    header('Location: ' . url('user/dashboard.php'));
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5 my-auto">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-9">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-sm-5">
                    <div class="text-center mb-4">
                        <div class="brand-icon mx-auto mb-3" style="width:48px;height:48px;font-size:1.5rem;">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>
                        <h3 class="fw-bold text-dark mb-1">Create LOFO Account</h3>
                        <p class="text-muted fs-sm">Register as a Student or Faculty member of Union Christian College</p>
                    </div>

                    <form action="<?= url('actions/auth_action.php') ?>" method="POST">
                        <input type="hidden" name="action" value="register">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label for="name" class="form-label fs-sm fw-medium">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" placeholder="e.g. name" required>
                            </div>
                            <div class="col-sm-6">
                                <label for="role" class="form-label fs-sm fw-medium">Institutional Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="student" selected>Student</option>
                                    <option value="faculty">Faculty / Staff</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label for="email" class="form-label fs-sm fw-medium">College Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="name@ucc.edu.in" required>
                            </div>
                            <div class="col-sm-6">
                                <label for="phone" class="form-label fs-sm fw-medium">Contact Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" placeholder="+91 98470 00000" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label for="department" class="form-label fs-sm fw-medium">Department</label>
                                <input type="text" class="form-control" id="department" name="department" placeholder="e.g. Computer Applications (MCA)">
                            </div>
                            <div class="col-sm-6">
                                <label for="year_or_designation" class="form-label fs-sm fw-medium">Roll No / Designation</label>
                                <input type="text" class="form-control" id="year_or_designation" name="year_or_designation" placeholder="e.g. Roll 32 / Asst. Professor">
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label for="password" class="form-label fs-sm fw-medium">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Min. 6 characters" required>
                            </div>
                            <div class="col-sm-6">
                                <label for="password_confirm" class="form-label fs-sm fw-medium">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Re-type password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                            <i class="fa-solid fa-check-circle me-2"></i> Register Account
                        </button>
                    </form>

                    <hr class="my-4">

                    <div class="text-center fs-sm">
                        <span class="text-muted">Already have an account?</span>
                        <a href="<?= url('login.php') ?>" class="fw-semibold text-primary text-decoration-none ms-1">Sign In here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
