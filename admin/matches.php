<?php
// LOFO - Smart Matches Queue
$pageTitle = "Smart Matches Queue";
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

requireAdmin();
$pdo = getDBConnection();

$stmt = $pdo->query("
    SELECT m.*, 
           l.item_name as lost_name, l.location_lost, l.date_lost, ul.name as lost_user_name,
           f.item_name as found_name, f.location_found, f.date_found, uf.name as found_user_name
    FROM item_matches m 
    JOIN lost_items l ON m.lost_id = l.lost_id 
    JOIN found_items f ON m.found_id = f.found_id 
    JOIN users ul ON l.user_id = ul.user_id 
    JOIN users uf ON f.user_id = uf.user_id 
    ORDER BY (m.status = 'Pending Review') DESC, m.confidence_score DESC, m.created_at DESC
");
$matches = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <span class="badge bg-purple text-white px-3 py-1 mb-2 text-uppercase fs-xxs" style="background:#7c3aed;">
                <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Automated AI Matching Engine
            </span>
            <h3 class="fw-bold text-dark mb-1">Smart Item Matches Queue</h3>
            <p class="text-muted fs-sm mb-0">System-calculated match candidates based on category, keywords, campus location, and date proximity.</p>
        </div>
    </div>

    <?php if (empty($matches)): ?>
        <div class="card shadow-sm border-0 p-5 text-center">
            <i class="fa-solid fa-wand-magic-sparkles fs-1 text-muted opacity-50 mb-3"></i>
            <h5>No Candidate Matches</h5>
            <p class="text-muted fs-sm">As students and faculty log items, matches with $\ge 50\%$ confidence will appear here.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($matches as $m): 
                $conf = (int)$m['confidence_score'];
                $confClass = $conf >= 80 ? 'confidence-high' : ($conf >= 60 ? 'confidence-med' : 'confidence-low');
                $isPending = ($m['status'] === 'Pending Review');
            ?>
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="confidence-pill <?= $confClass ?>">
                                <i class="fa-solid fa-bullseye me-1"></i> <?= $conf ?>% Confidence Score
                            </span>
                            <span class="badge bg-secondary-subtle text-secondary border fs-xxs">
                                <?= htmlspecialchars($m['status']) ?>
                            </span>
                        </div>

                        <!-- Match Pair Visualizer -->
                        <div class="row g-2 mb-3 align-items-center">
                            <div class="col-5">
                                <div class="p-2 rounded bg-danger-subtle border border-danger border-opacity-25 text-center">
                                    <span class="badge bg-danger fs-xxs mb-1">Lost Item</span>
                                    <div class="fw-bold fs-xs text-dark text-truncate" title="<?= htmlspecialchars($m['lost_name']) ?>">
                                        <?= htmlspecialchars($m['lost_name']) ?>
                                    </div>
                                    <small class="text-muted fs-xxs d-block"><?= htmlspecialchars($m['lost_user_name']) ?></small>
                                </div>
                            </div>
                            <div class="col-2 text-center text-muted">
                                <i class="fa-solid fa-arrows-left-right fs-5 text-primary"></i>
                            </div>
                            <div class="col-5">
                                <div class="p-2 rounded bg-success-subtle border border-success border-opacity-25 text-center">
                                    <span class="badge bg-success fs-xxs mb-1">Found Item</span>
                                    <div class="fw-bold fs-xs text-dark text-truncate" title="<?= htmlspecialchars($m['found_name']) ?>">
                                        <?= htmlspecialchars($m['found_name']) ?>
                                    </div>
                                    <small class="text-muted fs-xxs d-block"><?= htmlspecialchars($m['found_user_name']) ?></small>
                                </div>
                            </div>
                        </div>

                        <!-- Match Reasons -->
                        <div class="p-3 bg-light rounded-3 mb-3 border fs-xs">
                            <strong class="text-dark d-block mb-1"><i class="fa-solid fa-circle-nodes me-1 text-primary"></i> System Match Criteria:</strong>
                            <span class="text-secondary"><?= htmlspecialchars($m['match_reasons']) ?></span>
                        </div>

                        <!-- Action buttons -->
                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                            <div class="d-flex gap-2">
                                <a href="<?= url('item-details.php?type=lost&id=' . $m['lost_id']) ?>" target="_blank" class="btn btn-outline-danger btn-xs py-1 px-2 fs-xxs">
                                    View Lost
                                </a>
                                <a href="<?= url('item-details.php?type=found&id=' . $m['found_id']) ?>" target="_blank" class="btn btn-outline-success btn-xs py-1 px-2 fs-xxs">
                                    View Found
                                </a>
                            </div>

                            <?php if ($isPending): ?>
                                <form action="<?= url('actions/admin_actions.php') ?>" method="POST" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="review_match">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="match_id" value="<?= $m['match_id'] ?>">
                                    <button type="submit" name="decision" value="confirm" class="btn btn-primary btn-xs py-1 px-2 fs-xxs">
                                        <i class="fa-solid fa-check me-1"></i> Confirm Match
                                    </button>
                                    <button type="submit" name="decision" value="dismiss" class="btn btn-outline-secondary btn-xs py-1 px-2 fs-xxs">
                                        Dismiss
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
