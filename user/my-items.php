<?php
// LOFO - My Reported Items

$pageTitle = "My Reported Items";

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

$pdo = getDBConnection();
$currentUser = currentUser();

if (!$currentUser || empty($currentUser['id'])) {
    header('Location: ' . url('login.php'));
    exit;
}

$currentUserId = (int) $currentUser['id'];


/*
|--------------------------------------------------------------------------
| Fetch User's Lost Reports
|--------------------------------------------------------------------------
*/

$lostStmt = $pdo->prepare("
    SELECT
        l.*,
        c.name AS category_name
    FROM lost_items l
    JOIN categories c
        ON l.category_id = c.category_id
    WHERE l.user_id = ?
    ORDER BY l.created_at DESC
");

$lostStmt->execute([
    $currentUserId
]);

$lostItems = $lostStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Fetch User's Found Reports
|--------------------------------------------------------------------------
*/

$foundStmt = $pdo->prepare("
    SELECT
        f.*,
        c.name AS category_name,
        cl.claim_id AS approved_claim_id,
        cl.user_id AS claimant_id
    FROM found_items f
    JOIN categories c
        ON f.category_id = c.category_id
    LEFT JOIN claims cl
        ON f.found_id = cl.found_id
        AND cl.status = 'Approved'
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
");

$foundStmt->execute([
    $currentUserId
]);

$foundItems = $foundStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

        <div>
            <h3 class="fw-bold text-dark mb-1">
                My Reported Items
            </h3>

            <p class="text-muted fs-sm mb-0">
                Track and manage your submitted lost and found item reports.
            </p>
        </div>

        <div class="d-flex gap-2">

            <a
                href="<?= url('user/report_lost.php') ?>"
                class="btn btn-warning btn-sm fw-semibold text-dark shadow-sm"
            >
                <i class="fa-solid fa-plus-circle me-1"></i>
                Report Lost
            </a>

            <a
                href="<?= url('user/report-found.php') ?>"
                class="btn btn-success btn-sm fw-semibold shadow-sm"
            >
                <i class="fa-solid fa-hand-holding-heart me-1"></i>
                Report Found
            </a>

        </div>
    </div>


    <!-- Navigation Tabs -->
    <ul
        class="nav nav-pills mb-4 border-bottom pb-3"
        id="itemTabs"
        role="tablist"
    >

        <li class="nav-item" role="presentation">

            <button
                class="nav-link active fw-semibold"
                id="lost-tab"
                data-bs-toggle="pill"
                data-bs-target="#lost-pane"
                type="button"
                role="tab"
            >
                <i class="fa-solid fa-circle-question me-1 text-danger"></i>
                Lost Items (<?= count($lostItems) ?>)
            </button>

        </li>

        <li class="nav-item" role="presentation">

            <button
                class="nav-link fw-semibold"
                id="found-tab"
                data-bs-toggle="pill"
                data-bs-target="#found-pane"
                type="button"
                role="tab"
            >
                <i class="fa-solid fa-box-open me-1 text-success"></i>
                Found Items (<?= count($foundItems) ?>)
            </button>

        </li>

    </ul>


    <div class="tab-content" id="itemTabsContent">

        <!-- =========================================================
             LOST ITEMS
        ========================================================== -->

        <div
            class="tab-pane fade show active"
            id="lost-pane"
            role="tabpanel"
        >

            <?php if (empty($lostItems)): ?>

                <div class="card shadow-sm border-0 p-5 text-center">

                    <i class="fa-regular fa-clipboard fs-1 text-muted opacity-50 mb-3"></i>

                    <h5>No Lost Items Reported</h5>

                    <p class="text-muted fs-sm">
                        If you lose an item on campus, report it here so the community can help you recover it.
                    </p>

                    <div>
                        <a
                            href="<?= url('user/report_lost.php') ?>"
                            class="btn btn-warning text-dark btn-sm fw-semibold"
                        >
                            Report a Lost Item
                        </a>
                    </div>

                </div>

            <?php else: ?>

                <div class="row g-3">

                    <?php foreach ($lostItems as $item): ?>

                        <div class="col-lg-6">

                            <div class="card shadow-sm border-0 h-100 p-3 card-hover">

                                <!-- Item Header -->
                                <div class="d-flex justify-content-between align-items-start mb-2">

                                    <div>

                                        <span class="badge bg-secondary-subtle text-secondary border fs-xxs mb-1">
                                            <?= htmlspecialchars($item['category_name']) ?>
                                        </span>

                                        <h5 class="fw-bold text-dark mb-1">
                                            <?= htmlspecialchars($item['item_name']) ?>
                                        </h5>

                                    </div>

                                    <span class="badge badge-status-<?= strtolower(htmlspecialchars($item['status'])) ?> fs-xs">
                                        <?= htmlspecialchars($item['status']) ?>
                                    </span>

                                </div>


                                <!-- Description -->
                                <p class="text-muted fs-xs mb-3 text-truncate-2">
                                    <?= htmlspecialchars($item['description']) ?>
                                </p>


                                <!-- Location and Date -->
                                <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center fs-xs text-muted">

                                    <span>
                                        <i class="fa-solid fa-location-dot text-danger me-1"></i>
                                        <?= htmlspecialchars($item['location_lost']) ?>
                                    </span>

                                    <span>
                                        <i class="fa-regular fa-calendar me-1"></i>
                                        <?= date('d M Y', strtotime($item['date_lost'])) ?>
                                    </span>

                                </div>


                                <!-- Action Buttons -->
                                <div class="mt-3 d-flex gap-2">

                                    <a
                                        href="<?= url('item-details.php?type=lost&id=' . (int) $item['lost_id']) ?>"
                                        class="btn btn-outline-primary btn-sm flex-grow-1"
                                    >
                                        <i class="fa-solid fa-eye me-1"></i>
                                        View Details & Smart Matches
                                    </a>


                                    <!-- Delete Form -->
                                    <form
                                        method="POST"
                                        action="<?= url('actions/delete_lost_item.php') ?>"
                                        onsubmit="return confirm('Are you sure you want to delete this lost item report?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars(csrfToken()) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="lost_item_id"
                                            value="<?= (int) $item['lost_id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-outline-danger btn-sm"
                                            title="Delete Report"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </button>

                                    </form>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>


        <!-- =========================================================
             FOUND ITEMS
        ========================================================== -->

        <div
            class="tab-pane fade"
            id="found-pane"
            role="tabpanel"
        >

            <?php if (empty($foundItems)): ?>

                <div class="card shadow-sm border-0 p-5 text-center">

                    <i class="fa-solid fa-hand-holding-heart fs-1 text-muted opacity-50 mb-3"></i>

                    <h5>No Found Items Reported</h5>

                    <p class="text-muted fs-sm">
                        When you find an unattended item on campus, report it to ensure it is kept safely and returned.
                    </p>

                    <div>
                        <a
                            href="<?= url('user/report-found.php') ?>"
                            class="btn btn-success btn-sm fw-semibold"
                        >
                            Report a Found Item
                        </a>
                    </div>

                </div>

            <?php else: ?>

                <div class="row g-3">

                    <?php foreach ($foundItems as $item): ?>

                        <div class="col-lg-6">

                            <div class="card shadow-sm border-0 h-100 p-3 card-hover">

                                <!-- Item Header -->
                                <div class="d-flex justify-content-between align-items-start mb-2">

                                    <div>

                                        <span class="badge bg-secondary-subtle text-secondary border fs-xxs mb-1">
                                            <?= htmlspecialchars($item['category_name']) ?>
                                        </span>

                                        <h5 class="fw-bold text-dark mb-1">
                                            <?= htmlspecialchars($item['item_name']) ?>
                                        </h5>

                                    </div>

                                    <span class="badge badge-status-<?= strtolower(htmlspecialchars($item['status'])) ?> fs-xs">
                                        <?= htmlspecialchars($item['status']) ?>
                                    </span>

                                </div>


                                <!-- Description -->
                                <p class="text-muted fs-xs mb-2 text-truncate-2">
                                    <?= htmlspecialchars($item['description']) ?>
                                </p>


                                <!-- Location and Date -->
                                <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center fs-xs text-muted">

                                    <span>
                                        <i class="fa-solid fa-location-dot text-success me-1"></i>
                                        <?= htmlspecialchars($item['location_found']) ?>
                                    </span>

                                    <span>
                                        <i class="fa-regular fa-calendar me-1"></i>
                                        <?= date('d M Y', strtotime($item['date_found'])) ?>
                                    </span>

                                </div>


                                <!-- Found Item Actions -->
<div class="mt-3 d-flex gap-2">

    <a
        href="<?= url('item-details.php?type=found&id=' . (int) $item['found_id']) ?>"
        class="btn btn-outline-success btn-sm flex-grow-1"
    >
        <i class="fa-solid fa-eye me-1"></i>
        View Details & Claims
    </a>


    <?php if (!empty($item['approved_claim_id'])): ?>

        <!-- Chat available after claim approval -->
        <a
            href="<?= url('chat.php?claim_id=' . (int) $item['approved_claim_id']) ?>"
            class="btn btn-primary btn-sm"
            title="Chat with claimant"
        >
            <i class="fa-solid fa-comments"></i>
            Chat
        </a>

    <?php else: ?>

        <!-- Delete Found Item -->
        <form
            method="POST"
            action="<?= url('actions/delete_found_item.php') ?>"
            onsubmit="return confirm('Are you sure you want to delete this found item report? This action cannot be undone.');"
            class="d-inline"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(csrfToken()) ?>"
            >

            <input
                type="hidden"
                name="found_item_id"
                value="<?= (int) $item['found_id'] ?>"
            >

            <button
                type="submit"
                class="btn btn-outline-danger btn-sm"
                title="Delete Found Item"
            >
                <i class="fa-solid fa-trash"></i>
            </button>

        </form>

    <?php endif; ?>

</div>


                                <!-- Handover Status -->
                                <?php if (!empty($item['approved_claim_id'])): ?>

                                    <?php
                                    $handoverStatus = $item['handover_status'] ?? 'Not Started';
                                    ?>

                                    <?php if ($handoverStatus === 'Not Started'): ?>

                                        <div class="alert alert-success mt-3 mb-0 py-2 px-3 fs-xs">

                                            <i class="fa-solid fa-circle-check me-1"></i>

                                            <strong>Claim Approved.</strong>
                                            You can now chat with the claimant and arrange the handover.

                                            <a
                                                href="<?= url('handover.php?claim_id=' . (int) $item['approved_claim_id']) ?>"
                                                class="btn btn-success btn-sm w-100 mt-2"
                                            >
                                                <i class="fa-solid fa-handshake me-1"></i>
                                                Upload Handover Proof
                                            </a>

                                        </div>

                                    <?php elseif ($handoverStatus === 'Proof Submitted'): ?>

                                        <div class="alert alert-warning mt-3 mb-0 py-2 px-3 fs-xs">

                                            <i class="fa-solid fa-clock me-1"></i>

                                            <strong>Handover Proof Submitted.</strong>

                                            <br>

                                            Waiting for admin verification.

                                        </div>

                                    <?php elseif ($handoverStatus === 'Rejected'): ?>

                                        <div class="alert alert-danger mt-3 mb-0 py-2 px-3 fs-xs">

                                            <i class="fa-solid fa-circle-xmark me-1"></i>

                                            <strong>Handover Proof Rejected.</strong>

                                            <br>

                                            Please arrange the handover again and upload new proof.

                                            <a
                                                href="<?= url('handover.php?claim_id=' . (int) $item['approved_claim_id']) ?>"
                                                class="btn btn-danger btn-sm w-100 mt-2"
                                            >
                                                <i class="fa-solid fa-upload me-1"></i>
                                                Upload New Handover Proof
                                            </a>

                                        </div>

                                    <?php elseif ($handoverStatus === 'Verified'): ?>

                                        <div class="alert alert-success mt-3 mb-0 py-2 px-3 fs-xs">

                                            <i class="fa-solid fa-circle-check me-1"></i>

                                            <strong>Handover Verified.</strong>

                                            <br>

                                            The item has been successfully returned and verified by admin.

                                        </div>

                                    <?php endif; ?>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>