<?php
// LOFO - Statistical Analytics & Reports Generator
$pageTitle = "Statistical Analytics & Reports";
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

requireAdmin();
$pdo = getDBConnection();

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=lofo_ucc_report_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Report ID', 'Type', 'Item Name', 'Category', 'Location', 'Date', 'Status', 'Reported By', 'Created At']);

    $lost = $pdo->query("SELECT l.lost_id, 'Lost' as type, l.item_name, c.name as category, l.location_lost, l.date_lost, l.status, u.name as user_name, l.created_at FROM lost_items l JOIN categories c ON l.category_id = c.category_id JOIN users u ON l.user_id = u.user_id")->fetchAll();
    foreach ($lost as $row) {
        fputcsv($output, $row);
    }

    $found = $pdo->query("SELECT f.found_id, 'Found' as type, f.item_name, c.name as category, f.location_found, f.date_found, f.status, u.name as user_name, f.created_at FROM found_items f JOIN categories c ON f.category_id = c.category_id JOIN users u ON f.user_id = u.user_id")->fetchAll();
    foreach ($found as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Analytics Metrics
$totalLost = (int)$pdo->query("SELECT COUNT(*) FROM lost_items")->fetchColumn();
$totalFound = (int)$pdo->query("SELECT COUNT(*) FROM found_items")->fetchColumn();
$totalApprovedClaims = (int)$pdo->query("SELECT COUNT(*) FROM claims WHERE status = 'Approved'")->fetchColumn();
$totalRejectedClaims = (int)$pdo->query("SELECT COUNT(*) FROM claims WHERE status = 'Rejected'")->fetchColumn();
$totalPendingClaims = (int)$pdo->query("SELECT COUNT(*) FROM claims WHERE status = 'Pending'")->fetchColumn();
$totalReturned = (int)$pdo->query("SELECT COUNT(*) FROM found_items WHERE status IN ('Claimed', 'Returned')")->fetchColumn();

$recoveryRate = ($totalLost + $totalFound) > 0 ? round(($totalReturned * 2 / ($totalLost + $totalFound)) * 100, 1) : 0;

// Category Breakdown
$categoryStats = $pdo->query("
    SELECT c.name, 
           (SELECT COUNT(*) FROM lost_items l WHERE l.category_id = c.category_id) as lost_count,
           (SELECT COUNT(*) FROM found_items f WHERE f.category_id = c.category_id) as found_count,
           (SELECT COUNT(*) FROM found_items f WHERE f.category_id = c.category_id AND f.status IN ('Claimed', 'Returned')) as recovered_count
    FROM categories c 
    ORDER BY (lost_count + found_count) DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 d-print-none">
        <div>
            <span class="badge bg-success px-3 py-1 mb-2 text-uppercase fs-xxs">Official Reporting</span>
            <h3 class="fw-bold text-dark mb-1">Statistical Analytics & Reports</h3>
            <p class="text-muted fs-sm mb-0">System performance metrics and recovery statistics for Union Christian College.</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-dark btn-sm shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Report
            </button>
            <a href="<?= url('admin/analytics.php?export=csv') ?>" class="btn btn-primary btn-sm shadow-sm">
                <i class="fa-solid fa-file-csv me-1"></i> Export CSV Spreadsheet
            </a>
        </div>
    </div>

    <!-- Official Printable Header (Visible on print) -->
    <div class="d-none d-print-block mb-4 text-center border-bottom pb-3">
        <h3 class="fw-bold mb-0 text-uppercase">Union Christian College, Aluva</h3>
        <h5 class="text-secondary mb-1">Department of Computer Applications (MCA)</h5>
        <h6 class="fw-bold text-primary">LOFO: Lost & Found Management System - Official Audit Report</h6>
        <small class="text-muted">Generated on: <?= date('F d, Y - h:i A') ?></small>
    </div>

    <!-- KPI Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon stat-icon-amber"><i class="fa-solid fa-circle-question"></i></div>
                <div>
                    <div class="fs-4 fw-bold text-dark"><?= $totalLost ?></div>
                    <div class="fs-xs text-muted">Total Lost Reported</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon stat-icon-green"><i class="fa-solid fa-box-archive"></i></div>
                <div>
                    <div class="fs-4 fw-bold text-dark"><?= $totalFound ?></div>
                    <div class="fs-xs text-muted">Total Found Registered</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon stat-icon-blue"><i class="fa-solid fa-handshake-angle"></i></div>
                <div>
                    <div class="fs-4 fw-bold text-dark"><?= $totalReturned ?></div>
                    <div class="fs-xs text-muted">Items Safely Recovered</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon stat-icon-purple"><i class="fa-solid fa-chart-line"></i></div>
                <div>
                    <div class="fs-4 fw-bold text-dark"><?= $recoveryRate ?>%</div>
                    <div class="fs-xs text-muted">Campus Recovery Rate</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Two-Stage Claim Resolution Distribution -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-shield-halved text-primary me-1"></i> Two-Stage Verification Status
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fs-sm text-muted">Stage 1 Claims Pending Review:</span>
                        <span class="badge bg-warning text-dark fs-xs px-2"><?= $totalPendingClaims ?></span>
                    </div>
                    <div class="progress mb-4" style="height: 10px;">
                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?= ($totalPendingClaims + $totalApprovedClaims + $totalRejectedClaims) > 0 ? round(($totalPendingClaims / ($totalPendingClaims + $totalApprovedClaims + $totalRejectedClaims)) * 100) : 0 ?>%"></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fs-sm text-muted">Stage 2 Verified & Approved:</span>
                        <span class="badge bg-success fs-xs px-2"><?= $totalApprovedClaims ?></span>
                    </div>
                    <div class="progress mb-4" style="height: 10px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= ($totalPendingClaims + $totalApprovedClaims + $totalRejectedClaims) > 0 ? round(($totalApprovedClaims / ($totalPendingClaims + $totalApprovedClaims + $totalRejectedClaims)) * 100) : 0 ?>%"></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fs-sm text-muted">Claims Rejected (Fraud Prevention):</span>
                        <span class="badge bg-danger fs-xs px-2"><?= $totalRejectedClaims ?></span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?= ($totalPendingClaims + $totalApprovedClaims + $totalRejectedClaims) > 0 ? round(($totalRejectedClaims / ($totalPendingClaims + $totalApprovedClaims + $totalRejectedClaims)) * 100) : 0 ?>%"></div>
                    </div>

                    <div class="alert alert-info py-2 px-3 mt-4 mb-0 fs-xxs">
                        <i class="fa-solid fa-circle-check text-info me-1"></i>
                        The 2-Stage Verification effectively filters out unverified or fraudulent claims by matching submitted characteristics against physical custody.
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Distribution Table -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-tags text-primary me-1"></i> Breakdown by Category
                    </h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 fs-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Category Name</th>
                                <th class="text-center">Lost</th>
                                <th class="text-center">Found</th>
                                <th class="text-center">Recovered</th>
                                <th class="text-end">Success</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categoryStats as $cs): 
                                $catTotal = $cs['lost_count'] + $cs['found_count'];
                                $catRate = $catTotal > 0 ? round(($cs['recovered_count'] * 2 / $catTotal) * 100) : 0;
                            ?>
                                <tr>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($cs['name']) ?></td>
                                    <td class="text-center"><span class="badge bg-danger-subtle text-danger fs-xxs"><?= $cs['lost_count'] ?></span></td>
                                    <td class="text-center"><span class="badge bg-success-subtle text-success fs-xxs"><?= $cs['found_count'] ?></span></td>
                                    <td class="text-center"><span class="badge bg-primary-subtle text-primary fs-xxs"><?= $cs['recovered_count'] ?></span></td>
                                    <td class="text-end fw-bold text-success fs-xs"><?= $catRate ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
