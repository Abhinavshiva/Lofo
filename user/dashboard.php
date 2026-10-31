<?php
// LOFO - User Dashboard
$pageTitle = "My Dashboard";
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

$pdo = getDBConnection();
$currentUser = currentUser();

// User Metrics
$myLostCount = $pdo->prepare("SELECT COUNT(*) FROM lost_items WHERE user_id = ?");
$myLostCount->execute([$currentUser['id']]);
$totalMyLost = $myLostCount->fetchColumn();

$myFoundCount = $pdo->prepare("SELECT COUNT(*) FROM found_items WHERE user_id = ?");
$myFoundCount->execute([$currentUser['id']]);
$totalMyFound = $myFoundCount->fetchColumn();

$myClaimsCount = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE user_id = ?");
$myClaimsCount->execute([$currentUser['id']]);
$totalMyClaims = $myClaimsCount->fetchColumn();

// Fetch smart matches for user's lost items
$matchStmt = $pdo->prepare("
    SELECT m.*, l.item_name as lost_name, f.found_id, f.item_name as found_name, f.location_found, f.date_found, f.image_path as found_image 
    FROM item_matches m 
    JOIN lost_items l ON m.lost_id = l.lost_id 
    JOIN found_items f ON m.found_id = f.found_id 
    WHERE l.user_id = ? AND m.status != 'Dismissed'
    ORDER BY m.confidence_score DESC
");
$matchStmt->execute([$currentUser['id']]);
$smartMatches = $matchStmt->fetchAll();

// Fetch user's recent reports
$recentLost = $pdo->prepare("SELECT * FROM lost_items WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
$recentLost->execute([$currentUser['id']]);
$userLostItems = $recentLost->fetchAll();

// Fetch user's claims
$claimsStmt = $pdo->prepare("
    SELECT c.*, f.item_name as found_name 
    FROM claims c 
    JOIN found_items f ON c.found_id = f.found_id 
    WHERE c.user_id = ? 
    ORDER BY c.created_at DESC LIMIT 3
");
$claimsStmt->execute([$currentUser['id']]);
$userClaims = $claimsStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- User Welcome Banner -->
    <div class="card shadow-sm border-0 mb-4 bg-dark-primary text-white">
        <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <span class="badge bg-primary px-3 py-1 mb-2 text-uppercase fs-xxs">
                    <?= htmlspecialchars($currentUser['role']) ?> Dashboard
                </span>
                <h2 class="fw-bold mb-1">Hello, <?= htmlspecialchars($currentUser['name']) ?></h2>
                <div class="text-white-50 fs-sm">
                    <?= htmlspecialchars($currentUser['department'] ?: 'Union Christian College') ?> &bull; <?= htmlspecialchars($currentUser['year_or_designation'] ?: 'UCC Member') ?>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= url('user/report-lost.php') ?>" class="btn btn-warning fw-semibold btn-sm px-3 shadow-sm text-dark">
                    <i class="fa-solid fa-plus-circle me-1"></i> Report Lost Item
                </a>
                <a href="<?= url('user/report-found.php') ?>" class="btn btn-success fw-semibold btn-sm px-3 shadow-sm">
                    <i class="fa-solid fa-hand-holding-heart me-1"></i> Report Found Item
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon stat-icon-amber">
                    <i class="fa-solid fa-circle-question"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold text-dark"><?= $totalMyLost ?></div>
                    <div class="fs-xs text-muted">My Lost Reports</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon stat-icon-green">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold text-dark"><?= $totalMyFound ?></div>
                    <div class="fs-xs text-muted">My Found Reports</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon stat-icon-blue">
                    <i class="fa-solid fa-file-shield"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold text-dark"><?= $totalMyClaims ?></div>
                    <div class="fs-xs text-muted">Submitted Claims</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon stat-icon-purple">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold text-dark"><?= count($smartMatches) ?></div>
                    <div class="fs-xs text-muted">Potential Matches</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Smart Match Recommendations Feed (If Any) -->
    <?php if (!empty($smartMatches)): ?>
        <div class="card border-primary border-opacity-50 shadow-sm mb-4">
            <div class="card-header bg-primary bg-opacity-10 py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-primary d-flex align-items-center gap-2">
                    <i class="fa-solid fa-wand-magic-sparkles text-primary"></i>
                    Smart Match Recommendations (Potential matches for items you lost!)
                </h6>
                <span class="badge bg-primary rounded-pill"><?= count($smartMatches) ?> Matches</span>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($smartMatches as $match): 
                    $conf = (int)$match['confidence_score'];
                    $confClass = $conf >= 80 ? 'confidence-high' : ($conf >= 60 ? 'confidence-med' : 'confidence-low');
                ?>
                    <div class="list-group-item p-3">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="confidence-pill <?= $confClass ?>"><?= $conf ?>% Match</span>
                                    <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($match['found_name']) ?></h6>
                                </div>
                                <div class="fs-xs text-muted mb-1">
                                    Matched with your report: <strong>"<?= htmlspecialchars($match['lost_name']) ?>"</strong>
                                </div>
                                <div class="fs-xxs text-secondary">
                                    <i class="fa-solid fa-circle-nodes me-1 text-primary"></i> <?= htmlspecialchars($match['match_reasons']) ?>
                                </div>
                            </div>
                            <div class="d-flex gap-2 flex-shrink-0">
                                <a href="<?= url('item-details.php?type=found&id=' . $match['found_id']) ?>" class="btn btn-outline-primary btn-sm fs-xs">
                                    <i class="fa-solid fa-eye me-1"></i> Inspect
                                </a>
                                <a href="<?= url('item-details.php?type=found&id=' . $match['found_id'] . '#claim') ?>" class="btn btn-primary btn-sm fs-xs">
                                    <i class="fa-solid fa-file-shield me-1"></i> Claim Item
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left: My Recent Lost Reports -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-circle-question text-danger me-1"></i> My Lost Item Reports
                    </h6>
                    <a href="<?= url('user/my-items.php') ?>" class="fs-xs text-decoration-none">View All &rarr;</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($userLostItems)): ?>
                        <div class="p-4 text-center text-muted fs-sm">
                            <i class="fa-regular fa-circle-check fs-2 text-success opacity-50 d-block mb-2"></i>
                            You have no active lost item reports.
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($userLostItems as $item): ?>
                                <a href="<?= url('item-details.php?type=lost&id=' . $item['lost_id']) ?>" class="list-group-item list-group-item-action p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-dark text-truncate"><?= htmlspecialchars($item['item_name']) ?></span>
                                        <span class="badge badge-status-<?= strtolower($item['status']) ?> fs-xxs">
                                            <?= htmlspecialchars($item['status']) ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between fs-xs text-muted">
                                        <span><i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($item['location_lost']) ?></span>
                                        <span><?= date('M d, Y', strtotime($item['date_lost'])) ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: My Submitted Claims -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-file-shield text-warning me-1"></i> My Claims & Verification Status
                    </h6>
                    <a href="<?= url('user/my-claims.php') ?>" class="fs-xs text-decoration-none">View All &rarr;</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($userClaims)): ?>
                        <div class="p-4 text-center text-muted fs-sm">
                            <i class="fa-solid fa-magnifying-glass fs-2 text-muted opacity-50 d-block mb-2"></i>
                            You haven't submitted any ownership claims yet.
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($userClaims as $claim): 
                                $statusBadge = 'badge-claim-pending';
                                if ($claim['status'] === 'Approved') $statusBadge = 'badge-claim-approved';
                                elseif ($claim['status'] === 'Rejected') $statusBadge = 'badge-claim-rejected';
                            ?>
                                <div class="list-group-item p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($claim['found_name']) ?></h6>
                                            <small class="text-muted fs-xs">Claimed on <?= date('d M Y', strtotime($claim['claim_date'])) ?></small>
                                        </div>
                                        <span class="badge <?= $statusBadge ?> fs-xxs">
                                            <?= htmlspecialchars($claim['status']) ?>
                                        </span>
                                    </div>
                                   <?php if ($claim['status'] === 'Approved'): ?>
                                   <div class="alert alert-success py-1 px-2 mb-0 mt-2 fs-xxs">
                                    <i class="fa-solid fa-circle-check me-1"></i>
                                    Claim Approved
                                    </div>
                                   <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
