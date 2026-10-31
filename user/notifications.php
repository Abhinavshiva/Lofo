<?php
// LOFO - Notifications History & Inbox
$pageTitle = "Notifications";
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/notifications.php';

$pdo = getDBConnection();
$currentUser = currentUser();

$notifications = getUserNotifications($pdo, $currentUser['id'], 50);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold text-dark mb-1">Notification Center</h3>
                    <p class="text-muted fs-sm mb-0">Stay informed about matches, claim status updates, and report confirmations.</p>
                </div>
                <?php if (!empty($notifications)): ?>
                    <a href="<?= url('actions/mark_notification.php?action=read_all') ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-check-double me-1"></i> Mark All as Read
                    </a>
                <?php endif; ?>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="card shadow-sm border-0 p-5 text-center">
                    <i class="fa-regular fa-bell-slash fs-1 text-muted opacity-50 mb-3"></i>
                    <h5>No Notifications Yet</h5>
                    <p class="text-muted fs-sm">You will receive alerts when potential matches are detected or when your claim is processed.</p>
                </div>
            <?php else: ?>
                <div class="card shadow-sm border-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications as $n): 
                            $icon = 'fa-bell text-primary';
                            if ($n['type'] === 'Match Found') $icon = 'fa-wand-magic-sparkles text-purple';
                            elseif ($n['type'] === 'Claim Approved') $icon = 'fa-circle-check text-success';
                            elseif ($n['type'] === 'Claim Rejected') $icon = 'fa-circle-xmark text-danger';
                            elseif ($n['type'] === 'Report Confirmation') $icon = 'fa-paper-plane text-info';
                            elseif ($n['type'] === 'Verification Pending') $icon = 'fa-clock text-warning';
                        ?>
                            <div class="list-group-item p-3 d-flex gap-3 align-items-start <?= $n['is_read'] ? 'text-muted' : 'bg-light-subtle' ?>">
                                <div class="stat-icon stat-icon-blue flex-shrink-0" style="width:40px;height:40px;font-size:1.1rem;">
                                    <i class="fa-solid <?= $icon ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                                        <h6 class="fw-bold mb-0 <?= $n['is_read'] ? 'text-dark' : 'text-primary' ?>"><?= htmlspecialchars($n['title']) ?></h6>
                                        <small class="text-muted fs-xs"><?= date('M d, Y - h:i A', strtotime($n['created_at'])) ?></small>
                                    </div>
                                    <p class="fs-sm mb-2 text-secondary"><?= htmlspecialchars($n['message']) ?></p>
                                    <div class="d-flex gap-3 align-items-center">
                                        <?php if ($n['link']): ?>
                                            <a href="<?= url($n['link']) ?>" class="btn btn-sm btn-link p-0 text-decoration-none fs-xs fw-semibold">
                                                View Relevant Record &rarr;
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!$n['is_read']): ?>
                                            <a href="<?= url('actions/mark_notification.php?action=read&id=' . $n['notification_id']) ?>" class="btn btn-link p-0 text-muted text-decoration-none fs-xs">
                                                Mark as read
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
