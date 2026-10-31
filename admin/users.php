<?php
// LOFO - User Accounts Management
$pageTitle = "User Accounts Management";
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

requireAdmin();
$pdo = getDBConnection();
$currentUser = currentUser();

$stmt = $pdo->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM lost_items l WHERE l.user_id = u.user_id) as lost_count,
           (SELECT COUNT(*) FROM found_items f WHERE f.user_id = u.user_id) as found_count,
           (SELECT COUNT(*) FROM claims c WHERE c.user_id = u.user_id) as claim_count
    FROM users u 
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">User Accounts Management</h3>
            <p class="text-muted fs-sm mb-0">Manage registered students, faculty members, and system administrators.</p>
        </div>
        <div>
            <span class="badge bg-primary rounded-pill px-3 py-2 fs-xs"><?= count($users) ?> Total Accounts</span>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-sm">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Role & Department</th>
                        <th>Contact</th>
                        <th>Activity</th>
                        <th>Account Status</th>
                        <th class="text-end">Role / Status Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): 
                        $isSelf = ($u['user_id'] == $currentUser['id']);
                        $isSuspended = ($u['account_status'] === 'suspended');
                    ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($u['name']) ?></div>
                                <small class="text-muted fs-xxs">ID #<?= $u['user_id'] ?> &bull; Registered <?= date('M d, Y', strtotime($u['created_at'])) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'faculty' ? 'info' : 'primary') ?> fs-xxs text-uppercase">
                                    <?= htmlspecialchars($u['role']) ?>
                                </span>
                                <div class="fs-xs text-muted mt-1"><?= htmlspecialchars($u['department'] ?: 'Union Christian College') ?></div>
                                <small class="text-secondary fs-xxs"><?= htmlspecialchars($u['year_or_designation']) ?></small>
                            </td>
                            <td>
                                <div><i class="fa-regular fa-envelope text-muted me-1 fs-xxs"></i> <?= htmlspecialchars($u['email']) ?></div>
                                <div class="fs-xxs text-muted"><i class="fa-solid fa-phone text-muted me-1"></i> <?= htmlspecialchars($u['phone']) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-danger-subtle text-danger fs-xxs"><?= $u['lost_count'] ?> Lost</span>
                                <span class="badge bg-success-subtle text-success fs-xxs"><?= $u['found_count'] ?> Found</span>
                                <span class="badge bg-warning-subtle text-warning fs-xxs"><?= $u['claim_count'] ?> Claims</span>
                            </td>
                            <td>
                                <span class="badge <?= $isSuspended ? 'bg-danger' : 'bg-success' ?> fs-xxs">
                                    <?= ucfirst($u['account_status']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if (!$isSelf): ?>
                                    <form action="<?= url('actions/admin_actions.php') ?>" method="POST" class="d-inline-flex gap-1 align-items-center">
                                        <input type="hidden" name="action" value="update_user_status">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">

                                        <select name="role" class="form-select form-select-sm fs-xxs py-0" style="width: 95px;">
                                            <option value="student" <?= $u['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                                            <option value="faculty" <?= $u['role'] === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                                            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        </select>

                                        <input type="hidden" name="account_status" value="<?= $isSuspended ? 'active' : 'suspended' ?>">
                                        <button type="submit" class="btn btn-xs py-1 px-2 fs-xxs <?= $isSuspended ? 'btn-success' : 'btn-outline-danger' ?>">
                                            <?= $isSuspended ? '<i class="fa-solid fa-unlock"></i> Activate' : '<i class="fa-solid fa-ban"></i> Suspend' ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border fs-xxs">Current Session</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
