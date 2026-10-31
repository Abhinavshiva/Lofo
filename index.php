<?php
// LOFO - Home Page & Search Portal
$pageTitle = "Campus Lost & Found Portal";
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

$pdo = getDBConnection();

// Fetch summary metrics
$totalLost = $pdo->query("SELECT COUNT(*) FROM lost_items")->fetchColumn();
$totalFound = $pdo->query("SELECT COUNT(*) FROM found_items")->fetchColumn();
$totalResolved = $pdo->query("SELECT COUNT(*) FROM found_items WHERE status IN ('Claimed', 'Returned')")->fetchColumn();
$activeClaims = $pdo->query("SELECT COUNT(*) FROM claims WHERE status = 'Pending'")->fetchColumn();

// Fetch categories for filtering
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Fetch all items (combining Lost & Found for unified browsing)
$lostSql = "SELECT lost_id as id, 'lost' as item_type, item_name, description, location_lost as location, 
            date_lost as event_date, image_path, status, created_at, category_id 
            FROM lost_items";

$foundSql = "SELECT found_id as id, 'found' as item_type, item_name, description, location_found as location, 
             date_found as event_date, image_path, status, created_at, category_id 
             FROM found_items";

$allItems = $pdo->query("($lostSql) UNION ALL ($foundSql) ORDER BY created_at DESC")->fetchAll();

// Category lookup map
$categoryMap = [];
foreach ($categories as $cat) {
    $categoryMap[$cat['category_id']] = $cat['name'];
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner -->
<section class="hero-banner">
    <div class="container position-relative">
        <div class="row align-items-center justify-content-between g-4">
            <div class="col-lg-7">
                <span class="badge bg-primary-subtle text-white border border-primary border-opacity-50 px-3 py-2 rounded-pill fs-xs mb-3">
                    <i class="fa-solid fa-graduation-cap me-1 text-warning"></i> Union Christian College, Aluva
                </span>
                <h1 class="display-5 fw-extrabold mb-3 lh-tight">
                    Campus Lost & Found <br><span class="text-primary-light" style="color: #60a5fa;">Management System</span>
                </h1>
                <p class="lead fs-6 text-white-50 mb-4 pe-lg-4">
                    Fast, transparent item recovery powered by smart attribute matching and a secure two-stage ownership claim verification process.
                </p>
                
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= url('user/report-lost.php') ?>" class="btn btn-warning fw-semibold px-4 py-2 shadow-sm text-dark">
                        <i class="fa-solid fa-circle-question me-1"></i> I Lost Something
                    </a>
                    <a href="<?= url('user/report-found.php') ?>" class="btn btn-success fw-semibold px-4 py-2 shadow-sm">
                        <i class="fa-solid fa-hand-holding-heart me-1"></i> I Found Something
                    </a>
                </div>
            </div>

            <!-- Stats Showcase Widget -->
            <div class="col-lg-5">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="stat-card p-3 bg-dark-soft border-secondary border-opacity-50 text-white">
                            <div class="stat-icon stat-icon-amber" style="width:44px;height:44px;font-size:1.1rem;">
                                <i class="fa-solid fa-circle-exclamation"></i>
                            </div>
                            <div>
                                <div class="fs-4 fw-bold mb-0 text-white"><?= $totalLost ?></div>
                                <div class="fs-xs text-white-50">Items Lost</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card p-3 bg-dark-soft border-secondary border-opacity-50 text-white">
                            <div class="stat-icon stat-icon-green" style="width:44px;height:44px;font-size:1.1rem;">
                                <i class="fa-solid fa-box-archive"></i>
                            </div>
                            <div>
                                <div class="fs-4 fw-bold mb-0 text-white"><?= $totalFound ?></div>
                                <div class="fs-xs text-white-50">Items Found</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card p-3 bg-dark-soft border-secondary border-opacity-50 text-white">
                            <div class="stat-icon stat-icon-blue" style="width:44px;height:44px;font-size:1.1rem;">
                                <i class="fa-solid fa-handshake-angle"></i>
                            </div>
                            <div>
                                <div class="fs-4 fw-bold mb-0 text-white"><?= $totalResolved ?></div>
                                <div class="fs-xs text-white-50">Recovered</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card p-3 bg-dark-soft border-secondary border-opacity-50 text-white">
                            <div class="stat-icon stat-icon-purple" style="width:44px;height:44px;font-size:1.1rem;">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>
                            <div>
                                <div class="fs-4 fw-bold mb-0 text-white">2-Stage</div>
                                <div class="fs-xs text-white-50">Verified Claims</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Search & Catalog Section -->
<section class="py-5">
    <div class="container">
        <!-- Interactive Search Controls -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3 p-md-4">
                <div class="row g-3 align-items-center">
                    <!-- Search Input -->
                    <!-- <div class="col-lg-5 col-md-12">
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted border-end-0"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" id="catalogSearchInput" class="form-control border-start-0 ps-0" placeholder="Search by item name, description, campus location...">
                        </div>
                    </div> -->

                    <!-- Category Selector -->
                    <div class="col-lg-4 col-md-6">
                        <select id="catalogCategoryFilter" class="form-select">
                            <option value="all">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Type / Status Filter Dropdown -->
                    <div class="col-lg-3 col-md-6">
                        <select id="catalogStatusFilter" class="form-select">
                            <option value="all">All Reports</option>
                            <option value="found">Found Items (Available to Claim)</option>
                            <option value="lost">Lost Item Reports</option>
                            <option value="claimed">Claimed & Returned</option>
                        </select>
                    </div>
                </div>

                <!-- Quick Filter Buttons Row -->
                <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top align-items-center">
                    <span class="fs-xs fw-semibold text-muted me-1">Quick Filter:</span>
                    <button class="btn btn-sm btn-outline-secondary catalog-pill-btn active" data-filter-val="all">All Items</button>
                    <button class="btn btn-sm btn-outline-success catalog-pill-btn" data-filter-val="found"><i class="fa-solid fa-hand-holding-heart me-1"></i> Available Found Items</button>
                    <button class="btn btn-sm btn-outline-danger catalog-pill-btn" data-filter-val="lost"><i class="fa-solid fa-circle-question me-1"></i> Reported Lost</button>
                    <button class="btn btn-sm btn-outline-primary catalog-pill-btn" data-filter-val="claimed"><i class="fa-solid fa-circle-check me-1"></i> Claimed / Recovered</button>
                    <div class="ms-auto fs-xs text-muted">
                        Showing <strong id="catalogVisibleCount" class="text-dark"><?= count($allItems) ?></strong> items
                    </div>
                </div>
            </div>
        </div>

        <!-- Catalog Grid -->
        <div class="row g-4" id="catalogGrid">
            <?php if (empty($allItems)): ?>
                <div class="col-12 text-center py-5">
                    <div class="stat-icon stat-icon-blue mx-auto mb-3" style="width:64px;height:64px;font-size:1.8rem;">
                        <i class="fa-regular fa-folder-open"></i>
                    </div>
                    <h5>No Items Reported Yet</h5>
                    <p class="text-muted fs-sm">Be the first to log a lost or found item on campus.</p>
                </div>
            <?php else: ?>
                <?php foreach ($allItems as $item): 
                    $catName = $categoryMap[$item['category_id']] ?? 'Uncategorized';
                    $isLost = ($item['item_type'] === 'lost');
                    $statusClass = 'badge-status-available';
                    if ($item['status'] === 'Lost') $statusClass = 'badge-status-lost';
                    elseif ($item['status'] === 'Matched') $statusClass = 'badge-status-matched';
                    elseif ($item['status'] === 'Claimed' || $item['status'] === 'Returned') $statusClass = 'badge-status-claimed';
                ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 catalog-item-card" 
                         data-name="<?= htmlspecialchars($item['item_name']) ?>" 
                         data-desc="<?= htmlspecialchars($item['description']) ?>" 
                         data-location="<?= htmlspecialchars($item['location']) ?>" 
                         data-category="<?= $item['category_id'] ?>" 
                         data-status="<?= $item['status'] ?>" 
                         data-type="<?= $item['item_type'] ?>">
                        
                        <div class="card h-100 card-hover shadow-sm">
                            <!-- Image Frame -->
                            <div class="item-img-container">
                                <?php if (!empty($item['image_path']) && file_exists(__DIR__ . '/' . $item['image_path'])): ?>
                                <?php else: ?>
                                    <div class="item-img-placeholder">
                                        <i class="fa-solid <?= $isLost ? 'fa-magnifying-glass' : 'fa-box-open' ?> fs-1 mb-2 opacity-50"></i>
                                        <span class="fs-xs text-muted">No photo uploaded</span>
                                    </div>
                                <?php endif; ?>

                                <!-- Type Badge (LOST / FOUND) -->
                                <span class="position-absolute top-0 start-0 m-2 badge <?= $isLost ? 'bg-danger' : 'bg-success' ?> shadow-sm text-uppercase fs-xxs">
                                    <i class="fa-solid <?= $isLost ? 'fa-circle-question' : 'fa-hand-holding-heart' ?> me-1"></i>
                                    <?= $isLost ? 'Lost Item' : 'Found Item' ?>
                                </span>

                                <!-- Status Badge -->
                                <span class="position-absolute top-0 end-0 m-2 badge <?= $statusClass ?> shadow-sm fs-xxs">
                                    <?= htmlspecialchars($item['status']) ?>
                                </span>
                            </div>

                            <!-- Card Body -->
                            <div class="card-body p-3 d-flex flex-column">
                                <div class="mb-2">
                                    <span class="badge bg-light text-secondary border fs-xxs mb-1">
                                        <i class="fa-solid fa-tag me-1"></i> <?= htmlspecialchars($catName) ?>
                                    </span>
                                    <h6 class="card-title fw-bold text-dark mb-1 text-truncate" title="<?= htmlspecialchars($item['item_name']) ?>">
                                        <?= htmlspecialchars($item['item_name']) ?>
                                    </h6>
                                    <p class="card-text text-muted fs-xs line-clamp-2 mb-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 38px;">
                                        <?= htmlspecialchars($item['description']) ?>
                                    </p>
                                </div>

                                <div class="mt-auto pt-2 border-top fs-xs text-muted space-y-1">
                                    <div class="d-flex align-items-center gap-1 text-truncate mb-1" title="<?= htmlspecialchars($item['location']) ?>">
                                        <i class="fa-solid fa-location-dot text-danger flex-shrink-0"></i>
                                        <span class="text-truncate"><?= htmlspecialchars($item['location']) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <!-- <span><i class="fa-regular fa-calendar me-1"></i> <?= date('d M Y', strtotime($item['event_date'])) ?></span> -->
                                        <span class="text-muted fs-xxs">#<?= $item['id'] ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Footer Action -->
                            <div class="card-footer bg-white border-top p-2 px-3 d-flex justify-content-between align-items-center">
                                <a href="<?= url('item-details.php?type=' . $item['item_type'] . '&id=' . $item['id']) ?>" class="btn btn-outline-primary btn-sm fs-xs w-100">
                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View Details
                                </a>
                                <?php if (!$isLost && $item['status'] === 'Available'): ?>
                                    <a href="<?= url('item-details.php?type=found&id=' . $item['id'] . '#claim') ?>" class="btn btn-success btn-sm fs-xs ms-2 flex-shrink-0">
                                        Claim
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- No Results Found Dynamic Container -->
        <div id="noCatalogResults" class="text-center py-5 d-none">
            <i class="fa-solid fa-magnifying-glass fs-1 text-muted opacity-50 mb-3"></i>
            <h5>No matching items found</h5>
            <p class="text-muted fs-sm">Try broadening your search keywords or clearing category filters.</p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
