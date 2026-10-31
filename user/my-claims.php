<?php
// LOFO - My Claims & Tracking
$pageTitle = "My Claims & Verification";
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

$pdo = getDBConnection();
$currentUser = currentUser();

$stmt = $pdo->prepare("
    SELECT c.*, f.item_name as found_name, f.location_found, f.date_found, f.image_path as found_image,
           l.item_name as linked_lost_name, u.name as verifier_name
    FROM claims c 
    JOIN found_items f ON c.found_id = f.found_id 
    LEFT JOIN lost_items l ON c.lost_id = l.lost_id 
    LEFT JOIN users u ON c.verified_by = u.user_id 
    WHERE c.user_id = ? 
    ORDER BY c.created_at DESC
");
$stmt->execute([$currentUser['id']]);
$claims = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">My Ownership Claims</h3>
            <p class="text-muted fs-sm mb-0">Track the two-stage verification progress of your claims.</p>
        </div>
        <a href="<?= url('index.php') ?>" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-magnifying-glass me-1"></i> Browse Found Items
        </a>
    </div>

    <?php if (empty($claims)): ?>
        <div class="card shadow-sm border-0 p-5 text-center">
            <i class="fa-solid fa-file-shield fs-1 text-muted opacity-50 mb-3"></i>
            <h5>No Ownership Claims Submitted</h5>
            <p class="text-muted fs-sm">If you spot an item in the catalog that belongs to you, submit a Stage 1 verification claim.</p>
            <div><a href="<?= url('index.php') ?>" class="btn btn-primary btn-sm">Browse Available Items</a></div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($claims as $claim): 
                $statusBadge = 'badge-claim-pending';
                if ($claim['status'] === 'Approved') $statusBadge = 'badge-claim-approved';
                elseif ($claim['status'] === 'Rejected') $statusBadge = 'badge-claim-rejected';
            ?>
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="badge bg-secondary-subtle text-secondary border fs-xxs mb-1">Claim #<?= $claim['claim_id'] ?></span>
                                <h5 class="fw-bold text-dark mb-0">
                                    <a href="<?= url('item-details.php?type=found&id=' . $claim['found_id']) ?>" class="text-dark text-decoration-none hover-primary">
                                        <?= htmlspecialchars($claim['found_name']) ?>
                                    </a>
                                </h5>
                            </div>
                            <span class="badge <?= $statusBadge ?> fs-xs px-3 py-1">
                                <?= htmlspecialchars($claim['status']) ?>
                            </span>
                        </div>

                        <!-- 2-Stage Timeline UI -->
                        <div class="bg-light rounded-3 p-3 mb-3 border">
                            <h6 class="fs-xs fw-bold text-uppercase text-muted mb-3"><i class="fa-solid fa-timeline me-1 text-primary"></i> Verification Progress</h6>
                            <div class="claim-timeline ms-2">
                                <!-- Stage 1 -->
                                <div class="timeline-step completed">
                                    <div class="timeline-step-indicator"><i class="fa-solid fa-check"></i></div>
                                    <strong class="fs-xs d-block text-dark">Stage 1: Ownership Proof Submitted</strong>
                                    <small class="text-muted fs-xxs">Submitted on <?= date('F d, Y', strtotime($claim['claim_date'])) ?></small>
                                </div>

                                <!-- Stage 2 -->
                                <?php
$status = strtolower(trim($claim['status'] ?? ''));

$isApproved = in_array($status, ['approved', 'returned'], true);
$isRejected = ($status === 'rejected');
?>

<div class="timeline-step <?= $isApproved ? 'completed' : ($isRejected ? 'rejected' : 'active') ?>">

    <div class="timeline-step-indicator">

        <?php if ($isApproved): ?>

            <i class="fa-solid fa-check"></i>

        <?php elseif ($isRejected): ?>

            <i class="fa-solid fa-xmark"></i>

        <?php else: ?>

            <i class="fa-solid fa-spinner fa-spin"></i>

        <?php endif; ?>

    </div>
                                    <strong class="fs-xs d-block text-dark">Stage 2: Administrative Review & Decision</strong>
                                    <small class="text-muted fs-xxs">
                                        <?php if ($claim['status'] === 'Approved'): ?>

    Verified & Approved by
    <?= htmlspecialchars($claim['verifier_name'] ?? 'Admin') ?>
    on
    <?= date('M d, Y', strtotime($claim['verification_date'])) ?>

    <div class="mt-2">
        <a href="<?= url('chat.php?claim_id=' . $claim['claim_id']) ?>"
           class="btn btn-primary btn-sm">

            <i class="fa-solid fa-comments me-1"></i>
            Chat with Finder
        </a>
    </div>

<?php elseif ($claim['status'] === 'Rejected'): ?>

    Rejected by
    <?= htmlspecialchars($claim['verifier_name'] ?? 'Admin') ?>
    on
    <?= date('M d, Y', strtotime($claim['verification_date'])) ?>

<?php else: ?>

    Pending admin inspection of physical item and proof details

<?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Your Submitted Proof Summary -->
                        <div class="mb-3">
                            <small class="text-muted fw-semibold fs-xxs text-uppercase d-block mb-1">Your Submitted Proof Details:</small>
                            <div class="p-2 bg-white rounded border fs-xs text-secondary">
                                <?= nl2br(htmlspecialchars($claim['proof'])) ?>
                            </div>
                        </div>

                        <!-- Approved Collection Alert -->
                        
                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                            <span class="fs-xs text-muted">Found at <?= htmlspecialchars($claim['location_found']) ?></span>
                            <a href="<?= url('item-details.php?type=found&id=' . $claim['found_id']) ?>" class="btn btn-outline-secondary btn-sm fs-xs">
                                View Found Item &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
