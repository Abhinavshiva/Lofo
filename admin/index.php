<?php
// LOFO - Administrator Control Center
$pageTitle = "Admin Dashboard";
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

requireAdmin();
$pdo = getDBConnection();
$currentUser = currentUser();

// KPI Metrics per SDS Section 2.4.1.B
$totalLost = (int)$pdo->query("SELECT COUNT(*) FROM lost_items")->fetchColumn();
$totalFound = (int)$pdo->query("SELECT COUNT(*) FROM found_items")->fetchColumn();
$pendingClaims = (int)$pdo->query("SELECT COUNT(*) FROM claims WHERE status = 'Pending'")->fetchColumn();
$returnedItems = (int)$pdo->query("SELECT COUNT(*) FROM found_items WHERE status IN ('Claimed', 'Returned')")->fetchColumn();
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$pendingMatches = (int)$pdo->query("SELECT COUNT(*) FROM item_matches WHERE status = 'Pending Review'")->fetchColumn();

$recoveryRate = ($totalLost + $totalFound) > 0 ? round(($returnedItems * 2 / ($totalLost + $totalFound)) * 100, 1) : 0;

// Claims Management Table (Pending & Recent Claims)
$claimsStmt = $pdo->query("
    SELECT c.*, f.item_name as found_name, u.name as claimant_name, u.email as claimant_email, u.phone as claimant_phone, u.role as claimant_role 
    FROM claims c 
    JOIN found_items f ON c.found_id = f.found_id 
    JOIN users u ON c.user_id = u.user_id 
    ORDER BY (c.status = 'Pending') DESC, c.created_at DESC 
    LIMIT 6
");
$claims = $claimsStmt->fetchAll();

// Recent reports feed
$recentReports = $pdo->query("
    (SELECT lost_id as id, 'Lost' as type, item_name, location_lost as location, created_at, status FROM lost_items)
    UNION ALL
    (SELECT found_id as id, 'Found' as type, item_name, location_found as location, created_at, status FROM found_items)
    ORDER BY created_at DESC LIMIT 5
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Admin Header Banner -->
    <div class="card shadow-sm border-0 mb-4 bg-dark-primary text-white">
        <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <span class="badge bg-danger px-3 py-1 mb-2 text-uppercase fs-xxs">
                    <i class="fa-solid fa-shield-halved me-1"></i> Administrator Console
                </span>
                <h2 class="fw-bold mb-1">LOFO Management Control</h2>
                <div class="text-white-50 fs-sm">
                    Union Christian College, Aluva &bull; Supervision & 2-Stage Verification Center
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= url('admin/claims.php') ?>" class="btn btn-warning fw-semibold btn-sm px-3 shadow-sm text-dark position-relative">
                    <i class="fa-solid fa-stamp me-1"></i> Claims Queue
                    <?php if ($pendingClaims > 0): ?>
                        <span class="badge bg-danger ms-1"><?= $pendingClaims ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= url('admin/analytics.php') ?>" class="btn btn-primary fw-semibold btn-sm px-3 shadow-sm">
                    <i class="fa-solid fa-chart-pie me-1"></i> Analytics & Reports
                </a>
            </div>
        </div>
    </div>

    <!-- KPI Metric Cards (SDS 2.4.1.B) -->
    <div class="row g-3 mb-4">
        <!-- Total Lost -->
        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon stat-icon-amber">
                    <i class="fa-solid fa-circle-question"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold text-dark"><?= $totalLost ?></div>
                    <div class="fs-xs text-muted">Total Lost Items</div>
                </div>
            </div>
        </div>

        <!-- Total Found -->
        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon stat-icon-green">
                    <i class="fa-solid fa-box-archive"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold text-dark"><?= $totalFound ?></div>
                    <div class="fs-xs text-muted">Total Found Items</div>
                </div>
            </div>
        </div>

        <!-- Pending Claims -->
        <div class="col-xl-3 col-md-6">
            <div class="stat-card <?= $pendingClaims > 0 ? 'border-warning bg-warning-subtle' : '' ?>">
                <div class="stat-icon stat-icon-red">
                    <i class="fa-solid fa-stamp"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold text-dark"><?= $pendingClaims ?></div>
                    <div class="fs-xs text-muted">Pending Verification Claims</div>
                </div>
            </div>
        </div>

        <!-- Returned Items -->
        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon stat-icon-blue">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold text-dark"><?= $returnedItems ?></div>
                    <div class="fs-xs text-muted">Returned Items (<?= $recoveryRate ?>% Rate)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Navigation Toolbars -->
    <!-- <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3 d-flex flex-wrap gap-2 align-items-center">
                    <span class="fs-xs fw-bold text-uppercase text-muted me-2"><i class="fa-solid fa-sliders me-1"></i> Admin Modules:</span>
                    <a href="<?= url('admin/claims.php') ?>" class="btn btn-outline-dark btn-sm fs-xs"><i class="fa-solid fa-stamp text-warning me-1"></i> Claims Verification</a>
                    <a href="<?= url('admin/reports.php') ?>" class="btn btn-outline-dark btn-sm fs-xs"><i class="fa-solid fa-list-check text-info me-1"></i> All Reports Master Table</a>
                    <a href="<?= url('admin/matches.php') ?>" class="btn btn-outline-dark btn-sm fs-xs"><i class="fa-solid fa-wand-magic-sparkles text-purple me-1"></i> Smart Match Engine</a>
                    <a href="<?= url('admin/categories.php') ?>" class="btn btn-outline-dark btn-sm fs-xs"><i class="fa-solid fa-tags text-primary me-1"></i> Categories</a>
                    <a href="<?= url('admin/locations.php') ?>" class="btn btn-outline-dark btn-sm fs-xs"><i class="fa-solid fa-location-dot text-danger me-1"></i> Campus Locations</a>
                    <a href="<?= url('admin/users.php') ?>" class="btn btn-outline-dark btn-sm fs-xs"><i class="fa-solid fa-users text-success me-1"></i> Users & Roles</a>
                    <a href="<?= url('admin/analytics.php') ?>" class="btn btn-outline-dark btn-sm fs-xs ms-auto"><i class="fa-solid fa-file-export me-1"></i> Export Data</a>
                </div>
            </div>
        </div>
    </div> -->

    <div class="row g-4">
        <!-- Claims Management Table (SDS Section 2.4.1.B) -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-file-signature text-warning me-1"></i> Claims Management Queue
                    </h6>
                    <a href="<?= url('admin/claims.php') ?>" class="fs-xs text-decoration-none">View Full Verification Center &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 fs-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Claim ID</th>
                                <th>Item Name</th>
                                <th>Claimant</th>
                                <th>Status</th>
                                <th class="text-end">Verification Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($claims)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No claims logged in the system.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($claims as $c): 
                                    $sBadge = 'badge-claim-pending';
                                    if ($c['status'] === 'Approved') $sBadge = 'badge-claim-approved';
                                    elseif ($c['status'] === 'Rejected') $sBadge = 'badge-claim-rejected';
                                ?>
                                    <tr>
                                        <td class="fw-bold">#<?= $c['claim_id'] ?></td>
                                        <td>
                                            <a href="<?= url('item-details.php?type=found&id=' . $c['found_id']) ?>" class="text-dark fw-medium text-decoration-none">
                                                <?= htmlspecialchars($c['found_name']) ?>
                                            </a>
                                            <div class="fs-xxs text-muted"><i class="fa-solid fa-building me-1"></i> </div>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($c['claimant_name']) ?></div>
                                            <small class="text-muted fs-xxs"><?= htmlspecialchars($c['claimant_email']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?= $sBadge ?> fs-xxs"><?= htmlspecialchars($c['status']) ?></span>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= url('admin/claims.php?highlight=' . $c['claim_id']) ?>" class="btn btn-sm btn-primary py-1 px-2 fs-xs">
                                                <i class="fa-solid fa-stamp me-1"></i> Verify (Stage 2)
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Campus Reports Feed -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-clock-rotate-left text-primary me-1"></i> Recent Campus Activity
                    </h6>
                    <a href="<?= url('admin/reports.php') ?>" class="fs-xs text-decoration-none">All Reports</a>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($recentReports)): ?>
                        <div class="p-4 text-center text-muted fs-sm">No activity recorded yet.</div>
                    <?php else: ?>
                        <?php foreach ($recentReports as $r): 
                            $isLost = ($r['type'] === 'Lost');
                        ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge <?= $isLost ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?> fs-xxs">
                                        <?= $r['type'] ?>
                                    </span>
                                    <span class="badge bg-secondary-subtle text-secondary border fs-xxs"><?= htmlspecialchars($r['status']) ?></span>
                                </div>
                                <h6 class="fw-bold mb-1 fs-sm text-truncate text-dark"><?= htmlspecialchars($r['item_name']) ?></h6>
                                <div class="d-flex justify-content-between fs-xxs text-muted">
                                    <span><i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($r['location']) ?></span>
                                    <span><?= date('M d', strtotime($r['created_at'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
