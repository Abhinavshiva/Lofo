```php
<?php
// LOFO - All Reports Master Table
$pageTitle = "All Reports Master Table";

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

requireAdmin();

$pdo = getDBConnection();

$typeFilter = $_GET['type'] ?? 'all';

$lostReports = [];
$foundReports = [];

/* =========================================================
   GET LOST REPORTS
   ========================================================= */
if ($typeFilter === 'all' || $typeFilter === 'lost') {

    $stmt = $pdo->query("
        SELECT 
            l.lost_id as id,
            'lost' as report_type,
            l.item_name,
            l.location_lost as location,
            l.date_lost as event_date,
            l.status,
            l.created_at,
            c.name as category_name,
            u.name as user_name,
            u.email as user_email
        FROM lost_items l
        JOIN categories c ON l.category_id = c.category_id
        JOIN users u ON l.user_id = u.user_id
        ORDER BY l.created_at DESC
    ");

    $lostReports = $stmt->fetchAll();
}


/* =========================================================
   GET FOUND REPORTS
   ========================================================= */
if ($typeFilter === 'all' || $typeFilter === 'found') {

    $stmt = $pdo->query("
        SELECT 
            f.found_id as id,
            'found' as report_type,
            f.item_name,
            f.location_found as location,
            f.date_found as event_date,
            f.status,
            f.created_at,
            c.name as category_name,
            u.name as user_name,
            u.email as user_email
        FROM found_items f
        JOIN categories c ON f.category_id = c.category_id
        JOIN users u ON f.user_id = u.user_id
        ORDER BY f.created_at DESC
    ");

    $foundReports = $stmt->fetchAll();
}


/* =========================================================
   COMBINE LOST + FOUND REPORTS
   ========================================================= */
$all = array_merge($lostReports, $foundReports);


/* =========================================================
   SORT BY CREATED DATE
   ========================================================= */
usort($all, function ($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});


require_once __DIR__ . '/../includes/header.php';
?>


<div class="container py-4">

    <!-- =====================================================
         PAGE HEADER
         ===================================================== -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

        <div>
            <h3 class="fw-bold text-dark mb-1">
                Campus Reports Master Registry
            </h3>

            <p class="text-muted fs-sm mb-0">
                Overview and status administration of all lost and found entries at UCC.
            </p>
        </div>


        <!-- FILTER BUTTONS -->
        <div class="d-flex gap-2">

            <a href="<?= url('admin/reports.php?type=all') ?>"
               class="btn btn-sm <?= $typeFilter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                All Entries (<?= count($all) ?>)
            </a>

            <a href="<?= url('admin/reports.php?type=lost') ?>"
               class="btn btn-sm <?= $typeFilter === 'lost' ? 'btn-danger' : 'btn-outline-danger' ?>">
                Lost Only
            </a>

            <a href="<?= url('admin/reports.php?type=found') ?>"
               class="btn btn-sm <?= $typeFilter === 'found' ? 'btn-success' : 'btn-outline-success' ?>">
                Found Only
            </a>

        </div>

    </div>


    <!-- =====================================================
         MASTER TABLE
         ===================================================== -->
    <div class="card shadow-sm border-0">

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0 fs-sm">

                <thead class="table-light">

                    <tr>
                        <th>Type</th>
                        <th>Item Details</th>
                        <th>Category</th>
                        <th>Location & Date</th>
                        <th>Reported By</th>
                        <th>Status</th>
                        <th class="text-end">Update Status</th>
                    </tr>

                </thead>


                <tbody>

                    <?php if (empty($all)): ?>

                        <tr>
                            <td colspan="7"
                                class="text-center py-4 text-muted">

                                No reports found matching your criteria.

                            </td>
                        </tr>


                    <?php else: ?>


                        <?php foreach ($all as $item):

                            $isLost = ($item['report_type'] === 'lost');
                            $isFound = ($item['report_type'] === 'found');

                        ?>

                            <tr>

                                <!-- =================================================
                                     TYPE
                                     ================================================= -->
                                <td>

                                    <span class="badge <?= $isLost ? 'bg-danger' : 'bg-success' ?> fs-xxs text-uppercase">

                                        <?= htmlspecialchars($item['report_type']) ?>

                                    </span>

                                </td>


                                <!-- =================================================
                                     ITEM DETAILS
                                     ================================================= -->
                                <td>

                                    <a href="<?= url(
                                        'item-details.php?type=' .
                                        $item['report_type'] .
                                        '&id=' .
                                        $item['id']
                                    ) ?>"
                                       class="fw-bold text-dark text-decoration-none">

                                        <?= htmlspecialchars($item['item_name']) ?>

                                    </a>

                                </td>


                                <!-- =================================================
                                     CATEGORY
                                     ================================================= -->
                                <td>

                                    <span class="badge bg-secondary-subtle text-secondary border fs-xxs">

                                        <?= htmlspecialchars($item['category_name']) ?>

                                    </span>

                                </td>


                                <!-- =================================================
                                     LOCATION + DATE
                                     ================================================= -->
                                <td>

                                    <div>

                                        <i class="fa-solid fa-location-dot text-danger me-1 fs-xxs"></i>

                                        <?= htmlspecialchars($item['location']) ?>

                                    </div>

                                    <small class="text-muted fs-xxs">

                                        <?= date(
                                            'd M Y',
                                            strtotime($item['event_date'])
                                        ) ?>

                                    </small>

                                </td>


                                <!-- =================================================
                                     USER
                                     ================================================= -->
                                <td>

                                    <div>
                                        <?= htmlspecialchars($item['user_name']) ?>
                                    </div>

                                    <small class="text-muted fs-xxs">

                                        <?= htmlspecialchars($item['user_email']) ?>

                                    </small>

                                </td>


                                <!-- =================================================
                                     STATUS
                                     ================================================= -->
                                <td>

                                    <span class="badge badge-status-<?= strtolower($item['status']) ?> fs-xxs">

                                        <?= htmlspecialchars($item['status']) ?>

                                    </span>

                                </td>


                                <!-- =================================================
                                     ACTIONS
                                     ================================================= -->
                                <td class="text-end">

                                    <div class="d-flex justify-content-end align-items-center gap-2">

                                        <!-- STATUS UPDATE -->
                                        <form action="<?= url('actions/admin_actions.php') ?>"
                                              method="POST"
                                              class="d-inline-flex gap-1">

                                            <input type="hidden"
                                                   name="action"
                                                   value="update_report_status">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?= csrfToken() ?>">

                                            <input type="hidden"
                                                   name="report_type"
                                                   value="<?= htmlspecialchars($item['report_type']) ?>">

                                            <input type="hidden"
                                                   name="report_id"
                                                   value="<?= (int)$item['id'] ?>">


                                            <select name="new_status"
                                                    class="form-select form-select-sm fs-xs py-0"
                                                    style="width: 120px;"
                                                    onchange="this.form.submit()">

                                                <?php if ($isLost): ?>

                                                    <option value="Lost"
                                                        <?= $item['status'] === 'Lost' ? 'selected' : '' ?>>
                                                        Lost
                                                    </option>

                                                    <option value="Matched"
                                                        <?= $item['status'] === 'Matched' ? 'selected' : '' ?>>
                                                        Matched
                                                    </option>

                                                    <option value="Returned"
                                                        <?= $item['status'] === 'Returned' ? 'selected' : '' ?>>
                                                        Returned
                                                    </option>

                                                <?php else: ?>

                                                    <option value="Available"
                                                        <?= $item['status'] === 'Available' ? 'selected' : '' ?>>
                                                        Available
                                                    </option>

                                                    <option value="Claimed"
                                                        <?= $item['status'] === 'Claimed' ? 'selected' : '' ?>>
                                                        Claimed
                                                    </option>

                                                    <option value="Returned"
                                                        <?= $item['status'] === 'Returned' ? 'selected' : '' ?>>
                                                        Returned
                                                    </option>

                                                <?php endif; ?>

                                            </select>

                                        </form>


                                        <!-- =================================================
                                             DELETE FOUND ITEM
                                             Only shown for Found reports
                                             ================================================= -->
                                        <?php if ($isFound): ?>

                                            <form action="<?= url('actions/admin_actions.php') ?>"
                                                  method="POST"
                                                  class="d-inline">

                                                <input type="hidden"
                                                       name="action"
                                                       value="delete_found_item">

                                                <input type="hidden"
                                                       name="csrf_token"
                                                       value="<?= csrfToken() ?>">

                                                <input type="hidden"
                                                       name="found_id"
                                                       value="<?= (int)$item['id'] ?>">


                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="Delete Found Item"
                                                        onclick="return confirm('Are you sure you want to permanently delete this found item? This action cannot be undone.');">

                                                    <i class="fa-solid fa-trash"></i>

                                                </button>

                                            </form>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>

### 2. Add the delete handler to `actions/admin_actions.php`

The button above sends:

```text
action = delete_found_item
found_id = ID of the found item
csrf_token = ...
```

So your `admin_actions.php` also needs a handler for that action.

Add this **inside your existing action-processing logic**, after your CSRF/admin checks:


/* =========================================================
   DELETE FOUND ITEM
   ========================================================= */
if ($action === 'delete_found_item') {

    $foundId = (int)($_POST['found_id'] ?? 0);

    if ($foundId <= 0) {
        $_SESSION['error'] = "Invalid found item ID.";
        header("Location: " . url('admin/reports.php'));
        exit;
    }

    try {

        // Check whether the found item exists
        $checkStmt = $pdo->prepare("
            SELECT found_id, item_name
            FROM found_items
            WHERE found_id = ?
        ");

        $checkStmt->execute([$foundId]);

        $foundItem = $checkStmt->fetch();

        if (!$foundItem) {

            $_SESSION['error'] = "Found item not found.";

            header("Location: " . url('admin/reports.php'));
            exit;
        }


        /*
         * Delete related smart matches first.
         * This prevents foreign-key constraint problems
         * if item_matches.found_id references found_items.
         */
        $deleteMatchesStmt = $pdo->prepare("
            DELETE FROM item_matches
            WHERE found_id = ?
        ");

        $deleteMatchesStmt->execute([$foundId]);


        /*
         * Delete the found item.
         */
        $deleteStmt = $pdo->prepare("
            DELETE FROM found_items
            WHERE found_id = ?
        ");

        $deleteStmt->execute([$foundId]);


        $_SESSION['success'] =
            "Found item '" .
            htmlspecialchars($foundItem['item_name']) .
            "' deleted successfully.";


    } catch (PDOException $e) {

        $_SESSION['error'] =
            "Unable to delete the found item. " .
            $e->getMessage();
    }


    header("Location: " . url('admin/reports.php'));
    exit;
}
