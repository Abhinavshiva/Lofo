<?php
// LOFO - Item Details & Claim Submission Page
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

$pdo = getDBConnection();

$type = $_GET['type'] ?? 'found';
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0 || !in_array($type, ['lost', 'found'])) {
    setFlash('danger', 'Invalid item request.');
    header('Location: ' . url('index.php'));
    exit;
}

$currentUser = currentUser();

// Query item based on type
if ($type === 'lost') {
    $stmt = $pdo->prepare("
        SELECT l.*, c.name as category_name, c.icon as category_icon, u.name as user_name, u.role as user_role, u.department as user_department 
        FROM lost_items l 
        JOIN categories c ON l.category_id = c.category_id 
        JOIN users u ON l.user_id = u.user_id 
        WHERE l.lost_id = ?
    ");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) {
        setFlash('danger', 'Lost item report not found.');
        header('Location: ' . url('index.php'));
        exit;
    }
    $pageTitle = "Lost: " . $item['item_name'];

    // Get any matches for this lost item
    $matchesStmt = $pdo->prepare("
        SELECT m.*, f.found_id, f.item_name as found_name, f.location_found, f.date_found, f.image_path as found_image 
        FROM item_matches m 
        JOIN found_items f ON m.found_id = f.found_id 
        WHERE m.lost_id = ? 
        ORDER BY m.confidence_score DESC
    ");
    $matchesStmt->execute([$id]);
    $matches = $matchesStmt->fetchAll();

} else {
    $stmt = $pdo->prepare("
        SELECT f.*, c.name as category_name, c.icon as category_icon, u.name as user_name, u.role as user_role, u.department as user_department 
        FROM found_items f 
        JOIN categories c ON f.category_id = c.category_id 
        JOIN users u ON f.user_id = u.user_id 
        WHERE f.found_id = ?
    ");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) {
        setFlash('danger', 'Found item record not found.');
        header('Location: ' . url('index.php'));
        exit;
    }
    $pageTitle = "Found: " . $item['item_name'];

    // Check if current user has already claimed this item
    $userClaim = null;
    if ($currentUser) {
        $claimCheck = $pdo->prepare("SELECT * FROM claims WHERE found_id = ? AND user_id = ?");
        $claimCheck->execute([$id, $currentUser['id']]);
        $userClaim = $claimCheck->fetch();

        // Fetch user's active lost items to link
        $userLostReports = $pdo->prepare("SELECT lost_id, item_name, date_lost FROM lost_items WHERE user_id = ? AND status IN ('Lost', 'Matched')");
        $userLostReports->execute([$currentUser['id']]);
        $myLostItems = $userLostReports->fetchAll();
    }

    // Get any matches for this found item
    $matchesStmt = $pdo->prepare("
        SELECT m.*, l.lost_id, l.item_name as lost_name, l.location_lost, l.date_lost, l.image_path as lost_image 
        FROM item_matches m 
        JOIN lost_items l ON m.lost_id = l.lost_id 
        WHERE m.found_id = ? 
        ORDER BY m.confidence_score DESC
    ");
    $matchesStmt->execute([$id]);
    $matches = $matchesStmt->fetchAll();
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb fs-sm">
            <li class="breadcrumb-item"><a href="<?= url('index.php') ?>" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= url('index.php') ?>" class="text-decoration-none"><?= $type === 'lost' ? 'Lost Items' : 'Found Items' ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($item['item_name']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Left Column: Image & Status -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 overflow-hidden mb-4">
                <div style="height: 320px; background-color: #f8fafc; display:flex; align-items:center; justify-content:center; position:relative;">
                    <?php if (!empty($item['image_path']) && file_exists(__DIR__ . '/' . $item['image_path'])): ?>
                        
                    <?php else: ?>
                        <div class="text-center text-muted">
                            <i class="fa-solid <?= $type === 'lost' ? 'fa-circle-question' : 'fa-box-open' ?> fs-1 opacity-50 mb-2"></i>
                            <div class="fs-sm">No photo available</div>
                        </div>
                    <?php endif; ?>

                    <span class="position-absolute top-0 start-0 m-3 badge <?= $type === 'lost' ? 'bg-danger' : 'bg-success' ?> fs-xs text-uppercase shadow-sm">
                        <?= $type === 'lost' ? 'Lost Report' : 'Found Item' ?>
                    </span>
                    <span class="position-absolute top-0 end-0 m-3 badge bg-dark fs-xs shadow-sm">
                        Status: <?= htmlspecialchars($item['status']) ?>
                    </span>
                </div>

                <div class="card-body p-4 bg-light border-top">
                    <h6 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-circle-info text-primary me-2"></i> Report Metadata</h6>
                    <ul class="list-unstyled fs-sm space-y-2 mb-0">
                        <li class="mb-2 d-flex justify-content-between">
                            <span class="text-muted">Report ID:</span>
                            <span class="fw-semibold">#<?= $id ?></span>
                        </li>
                        <li class="mb-2 d-flex justify-content-between">
                            <span class="text-muted">Category:</span>
                            <span class="badge bg-secondary-subtle text-secondary border">
                                <i class="fa-solid <?= htmlspecialchars($item['category_icon'] ?? 'fa-tag') ?> me-1"></i>
                                <?= htmlspecialchars($item['category_name']) ?>
                            </span>
                        </li>
                        
                        <li class="d-flex justify-content-between">
                            <span class="text-muted">Reported By:</span>
                            <span class="fw-medium text-dark"><?= htmlspecialchars($item['user_name']) ?> (<?= ucfirst($item['user_role']) ?>)</span>
                        </li>
                    </ul>
                </div>
            </div>

            
        </div>

        <!-- Right Column: Details & Claim Workflow -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <h3 class="fw-bold text-dark mb-2"><?= htmlspecialchars($item['item_name']) ?></h3>
                    
                    <div class="d-flex align-items-center gap-2 text-muted fs-sm mb-4">
                        <i class="fa-solid fa-location-dot text-danger"></i>
                        <span>Location: <strong><?= htmlspecialchars($item[$type === 'lost' ? 'location_lost' : 'location_found']) ?></strong></span>
                    </div>

                    <h6 class="fw-bold text-dark mb-2">Item Description & Identifying Characteristics:</h6>
                    <div class="p-3 bg-light rounded-3 text-secondary mb-4 fs-sm lh-base border">
                        <?= nl2br(htmlspecialchars($item['description'])) ?>
                    </div>

                    <!-- Actions / Claim Section for Found Items -->
                    <?php if ($type === 'found'): ?>
                        <div id="claim" class="mt-4 pt-4 border-top">
                            <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                <i class="fa-solid fa-shield-halved text-primary"></i> Two-Stage Ownership Claim Workflow
                            </h5>

                            <?php if ($item['status'] !== 'Available'): ?>
                                <div class="alert alert-secondary py-3">
                                    <i class="fa-solid fa-lock me-2 text-muted"></i>
                                    <strong>This item is marked as <?= htmlspecialchars($item['status']) ?>.</strong> No further ownership claims can be submitted at this time.
                                </div>

                            <?php elseif ($userClaim): ?>
                                <!-- User Already Claimed Card -->
                                <div class="card border-warning bg-warning-subtle p-3">
                                    <h6 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-clock-rotate-left text-warning"></i>
                                        Your Claim Status: <span class="badge bg-warning text-dark"><?= htmlspecialchars($userClaim['status']) ?></span>
                                    </h6>
                                    <p class="fs-sm mb-2">You submitted an ownership claim on <?= date('d M Y', strtotime($userClaim['claim_date'])) ?>.</p>
                                    
                                    <!-- Timeline status -->
                                    <div class="claim-timeline mt-3 ms-2">
                                        <div class="timeline-step completed">
                                            <div class="timeline-step-indicator"><i class="fa-solid fa-check"></i></div>
                                            <strong class="fs-xs d-block">Stage 1: Claim & Proof Submitted</strong>
                                            <small class="text-muted fs-xxs"><?= date('M d, Y', strtotime($userClaim['created_at'])) ?></small>
                                        </div>
                                        <div class="timeline-step <?= $userClaim['status'] !== 'Pending' ? 'completed' : 'active' ?>">
                                            <div class="timeline-step-indicator"><?= $userClaim['status'] !== 'Pending' ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-spinner fa-spin"></i>' ?></div>
                                            <strong class="fs-xs d-block">Stage 2: Administrative Verification</strong>
                                            <small class="text-muted fs-xxs">
                                                <?= $userClaim['status'] === 'Pending' ? 'Under review by UCC administrator' : 'Processed: ' . htmlspecialchars($userClaim['status']) ?>
                                            </small>
                                        </div>
                                    </div>

                                    <?php if (!empty($userClaim['admin_remark'])): ?>
                                        <div class="mt-3 p-2 bg-white rounded border fs-xs">
                                            <strong>Admin Remarks:</strong> <?= htmlspecialchars($userClaim['admin_remark']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mt-3">
                                        <a href="<?= url('user/my-claims.php') ?>" class="btn btn-outline-dark btn-sm">View in My Claims Center &rarr;</a>
                                    </div>
                                </div>

                            <?php elseif ($currentUser): ?>
                                <!-- Claim Submission Form (Stage 1) -->
                                <div class="card border-primary border-opacity-25 bg-light p-3 p-md-4">
                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <span class="badge bg-primary rounded-pill">Stage 1</span>
                                        <h6 class="fw-bold text-dark mb-0">Submit Ownership Verification Details</h6>
                                    </div>
                                    <p class="fs-xs text-muted mb-3">
                                        To prevent fraudulent claims, describe confidential details not displayed publicly (e.g. serial numbers, inner items, distinctive scratches, stickers, screen lock passwords, or receipts).
                                    </p>

                                    <form action="<?= url('actions/claim_action.php') ?>" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="submit_claim">
                                        
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="found_id" value="<?= $id ?>">

                                        <!-- Optional: Link with existing lost report -->
                                        <?php if (!empty($myLostItems)): ?>
                                            <div class="mb-3">
                                                <label for="lost_id" class="form-label fs-sm fw-medium">Link with your previously reported lost item (Optional):</label>
                                                <select class="form-select fs-sm" id="lost_id" name="lost_id">
                                                    <option value="">-- Select matching report (if any) --</option>
                                                    <?php foreach ($myLostItems as $lost): ?>
                                                        <option value="<?= $lost['lost_id'] ?>">#<?= $lost['lost_id'] ?> - <?= htmlspecialchars($lost['item_name']) ?> (Lost on <?= $lost['date_lost'] ?>)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        <?php endif; ?>
                                        <div class="mb-3">
    <label for="lost_date" class="form-label fs-sm fw-medium text-dark">
        Date You Lost the Item <span class="text-danger">*</span>
    </label>

    <input
        type="date"
        class="form-control fs-sm"
        id="lost_date"
        name="lost_date"
        required
        max="<?= date('Y-m-d') ?>"
    >

    <div class="form-text fs-xxs">
        Enter the date on which you believe you lost this item.
        This will be compared with the reported found date.
    </div>
</div>
                                        <div class="mb-3">
                                            <label for="proof" class="form-label fs-sm fw-medium text-dark">
                                                Detailed Proof of Ownership <span class="text-danger">*</span>
                                            </label>
                                            <textarea class="form-control fs-sm" id="proof" name="proof" rows="3" placeholder="Provide distinct characteristics: serial numbers, unique marks, brand model, exact color, contents, phone wallpaper..." required></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label for="stage1_answers" class="form-label fs-sm fw-medium text-dark">
                                                Security Verification Questions / Additional Details:
                                            </label>
                                            <textarea class="form-control fs-sm" id="stage1_answers" name="stage1_answers" rows="2" placeholder="Where/when did you notice it missing? Any other witness or faculty present?"></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label for="proof_image" class="form-label fs-sm fw-medium text-dark">
                                                Supporting Document / Proof Photo (Optional):
                                            </label>
                                            <input class="form-control fs-sm" type="file" id="proof_image" name="proof_image" accept="image/*,.pdf" data-preview-target="#proofPreview">
                                            <div class="form-text fs-xxs">Upload purchase receipt, warranty card, or photo of the item (JPG, PNG, PDF max 5MB).</div>
                                            <div id="proofPreview"></div>
                                        </div>

                                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                                            <i class="fa-solid fa-paper-plane me-1"></i> Submit Stage 1 Ownership Claim
                                        </button>
                                    </form>
                                </div>

                            <?php else: ?>
                                <!-- Guest User Prompt -->
                                <div class="card border-secondary border-opacity-25 bg-light p-4 text-center">
                                    <i class="fa-solid fa-shield-cat fs-2 text-primary mb-2"></i>
                                    <h6 class="fw-bold text-dark">Claiming this item requires authentication</h6>
                                    <p class="fs-xs text-muted mb-3">Please sign in with your UCC student or faculty account to submit an ownership verification claim.</p>
                                    <div>
                                        <a href="<?= url('login.php') ?>" class="btn btn-primary btn-sm px-4 fw-medium">Sign In to Claim</a>
                                        <a href="<?= url('register.php') ?>" class="btn btn-outline-secondary btn-sm px-3 ms-2">Register</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Lost Item Assistance Box -->
                        <div class="mt-4 pt-4 border-top">
                            <div class="card bg-warning-subtle border-warning p-3">
                                <div class="d-flex gap-3 align-items-center">
                                    <div class="stat-icon stat-icon-amber" style="width:40px;height:40px;font-size:1.1rem;">
                                        <i class="fa-solid fa-hand-holding-heart"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold text-dark mb-1">Did you find this item?</h6>
                                        <p class="fs-xs text-muted mb-2">Help your fellow college student or faculty member recover it safely.</p>
                                        <a href="<?= url('user/report-found.php') ?>" class="btn btn-warning btn-sm fw-semibold text-dark">
                                            <i class="fa-solid fa-plus-circle me-1"></i> Report as Found Item
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Potential Matches Card -->
            <?php if (!empty($matches)): ?>
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                            <i class="fa-solid fa-wand-magic-sparkles text-primary"></i>
                            Smart Automated Matches (<?= count($matches) ?> Detected)
                        </h6>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($matches as $m): 
                            $conf = (int)$m['confidence_score'];
                            $confClass = $conf >= 80 ? 'confidence-high' : ($conf >= 60 ? 'confidence-med' : 'confidence-low');
                            $matchTargetId = ($type === 'lost') ? $m['found_id'] : $m['lost_id'];
                            $matchTargetType = ($type === 'lost') ? 'found' : 'lost';
                            $matchTargetName = ($type === 'lost') ? $m['found_name'] : $m['lost_name'];
                        ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="fw-bold mb-1">
                                            <a href="<?= url("item-details.php?type=$matchTargetType&id=$matchTargetId") ?>" class="text-dark text-decoration-none hover-primary">
                                                <?= htmlspecialchars($matchTargetName) ?>
                                            </a>
                                        </h6>
                                        <small class="text-muted fs-xs">
                                            <i class="fa-solid fa-circle-nodes me-1 text-primary"></i> <?= htmlspecialchars($m['match_reasons']) ?>
                                        </small>
                                    </div>
                                    <span class="confidence-pill <?= $confClass ?>">
                                        <?= $conf ?>% Match
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top fs-xs">
                                    <span class="badge bg-secondary-subtle text-secondary border"><?= htmlspecialchars($m['status']) ?></span>
                                    <a href="<?= url("item-details.php?type=$matchTargetType&id=$matchTargetId") ?>" class="btn btn-outline-primary btn-xs py-0 px-2 fs-xs">
                                        Inspect Candidate &rarr;
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
