
<?php
// LOFO - Admin Actions Controller
// Manages categories, locations, users, reports, smart matches,
// ownership verification, and handover verification.

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('admin/index.php'));
    exit;
}

requireAdmin();

$csrf = $_POST['csrf_token'] ?? '';

if (!verifyCsrfToken($csrf)) {
    setFlash('danger', 'Security validation failed (invalid CSRF token).');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? url('admin/index.php')));
    exit;
}

$pdo = getDBConnection();
$currentUser = currentUser();
$action = $_POST['action'] ?? '';


// ============================================================
// 1. ADD / EDIT / DELETE CATEGORY
// ============================================================

if ($action === 'save_category') {

    $catId = (int)($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-box');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        setFlash('danger', 'Category name is required.');
        header('Location: ' . url('admin/categories.php'));
        exit;
    }

    try {

        if ($catId > 0) {

            $stmt = $pdo->prepare("
                UPDATE categories
                SET name = ?, icon = ?, description = ?
                WHERE category_id = ?
            ");

            $stmt->execute([
                $name,
                $icon,
                $description,
                $catId
            ]);

            setFlash(
                'success',
                "Category '$name' updated successfully."
            );

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO categories
                    (name, icon, description)
                VALUES
                    (?, ?, ?)
            ");

            $stmt->execute([
                $name,
                $icon,
                $description
            ]);

            setFlash(
                'success',
                "Category '$name' created successfully."
            );
        }

    } catch (PDOException $e) {

        setFlash(
            'danger',
            'Database error: ' . $e->getMessage()
        );
    }

    header('Location: ' . url('admin/categories.php'));
    exit;
}


if ($action === 'delete_category') {

    $catId = (int)($_POST['category_id'] ?? 0);

    try {

        $chkLost = $pdo->prepare("
            SELECT COUNT(*)
            FROM lost_items
            WHERE category_id = ?
        ");

        $chkLost->execute([$catId]);

        $chkFound = $pdo->prepare("
            SELECT COUNT(*)
            FROM found_items
            WHERE category_id = ?
        ");

        $chkFound->execute([$catId]);

        if (
            $chkLost->fetchColumn() > 0 ||
            $chkFound->fetchColumn() > 0
        ) {

            setFlash(
                'danger',
                'Cannot delete this category because active items are associated with it.'
            );

        } else {

            $pdo->prepare("
                DELETE FROM categories
                WHERE category_id = ?
            ")->execute([$catId]);

            setFlash(
                'success',
                'Category deleted successfully.'
            );
        }

    } catch (PDOException $e) {

        setFlash(
            'danger',
            'Error deleting category: ' . $e->getMessage()
        );
    }

    header('Location: ' . url('admin/categories.php'));
    exit;
}


// ============================================================
// 2. ADD / EDIT / DELETE CAMPUS LOCATION
// ============================================================

if ($action === 'save_location') {

    $locId = (int)($_POST['location_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? 'Academic');

    if (empty($name)) {

        setFlash(
            'danger',
            'Campus location name is required.'
        );

        header('Location: ' . url('admin/locations.php'));
        exit;
    }

    try {

        if ($locId > 0) {

            $stmt = $pdo->prepare("
                UPDATE locations
                SET name = ?, type = ?
                WHERE location_id = ?
            ");

            $stmt->execute([
                $name,
                $type,
                $locId
            ]);

            setFlash(
                'success',
                'Campus location updated.'
            );

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO locations
                    (name, type)
                VALUES
                    (?, ?)
            ");

            $stmt->execute([
                $name,
                $type
            ]);

            setFlash(
                'success',
                'Campus location added.'
            );
        }

    } catch (PDOException $e) {

        setFlash(
            'danger',
            'Database error: ' . $e->getMessage()
        );
    }

    header('Location: ' . url('admin/locations.php'));
    exit;
}


if ($action === 'delete_location') {

    $locId = (int)($_POST['location_id'] ?? 0);

    try {

        $pdo->prepare("
            DELETE FROM locations
            WHERE location_id = ?
        ")->execute([$locId]);

        setFlash(
            'success',
            'Campus location removed.'
        );

    } catch (PDOException $e) {

        setFlash(
            'danger',
            'Error deleting location: ' . $e->getMessage()
        );
    }

    header('Location: ' . url('admin/locations.php'));
    exit;
}


// ============================================================
// 3. MANAGE USER STATUS / ROLE
// ============================================================

if ($action === 'update_user_status') {

    $targetUserId = (int)($_POST['user_id'] ?? 0);

    $newStatus = in_array(
        $_POST['account_status'] ?? '',
        ['active', 'suspended']
    )
        ? $_POST['account_status']
        : 'active';

    $newRole = in_array(
        $_POST['role'] ?? '',
        ['student', 'faculty', 'admin']
    )
        ? $_POST['role']
        : null;

    if (
        $targetUserId === (int)$currentUser['id'] &&
        $newStatus === 'suspended'
    ) {

        setFlash(
            'danger',
            'You cannot suspend your own administrator account.'
        );

        header('Location: ' . url('admin/users.php'));
        exit;
    }

    try {

        if ($newRole) {

            $stmt = $pdo->prepare("
                UPDATE users
                SET account_status = ?, role = ?
                WHERE user_id = ?
            ");

            $stmt->execute([
                $newStatus,
                $newRole,
                $targetUserId
            ]);

        } else {

            $stmt = $pdo->prepare("
                UPDATE users
                SET account_status = ?
                WHERE user_id = ?
            ");

            $stmt->execute([
                $newStatus,
                $targetUserId
            ]);
        }

        setFlash(
            'success',
            "User account #$targetUserId updated successfully."
        );

    } catch (PDOException $e) {

        setFlash(
            'danger',
            'Error updating user: ' . $e->getMessage()
        );
    }

    header('Location: ' . url('admin/users.php'));
    exit;
}


// ============================================================
// 4. UPDATE REPORT STATUS
// ============================================================

if ($action === 'update_report_status') {

    $reportType = $_POST['report_type'] ?? '';
    $reportId = (int)($_POST['report_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';

    if (
        $reportId <= 0 ||
        !in_array($reportType, ['lost', 'found'])
    ) {

        setFlash(
            'danger',
            'Invalid report update request.'
        );

        header('Location: ' . url('admin/reports.php'));
        exit;
    }

    try {

        if ($reportType === 'lost') {

            $pdo->prepare("
                UPDATE lost_items
                SET status = ?
                WHERE lost_id = ?
            ")->execute([
                $newStatus,
                $reportId
            ]);

        } else {

            $pdo->prepare("
                UPDATE found_items
                SET status = ?
                WHERE found_id = ?
            ")->execute([
                $newStatus,
                $reportId
            ]);
        }

        setFlash(
            'success',
            "Report status updated to '$newStatus'."
        );

    } catch (PDOException $e) {

        setFlash(
            'danger',
            'Error updating report: ' . $e->getMessage()
        );
    }

    header('Location: ' . url('admin/reports.php'));
    exit;
}


// ============================================================
// 5. REVIEW SMART MATCH
// ============================================================

if ($action === 'review_match') {

    $matchId = (int)($_POST['match_id'] ?? 0);

    $matchDecision = $_POST['decision'] ?? '';

    if (
        $matchId > 0 &&
        in_array(
            $matchDecision,
            ['confirm', 'dismiss']
        )
    ) {

        $status =
            ($matchDecision === 'confirm')
            ? 'Confirmed'
            : 'Dismissed';

        $pdo->prepare("
            UPDATE item_matches
            SET status = ?
            WHERE match_id = ?
        ")->execute([
            $status,
            $matchId
        ]);

        setFlash(
            'success',
            "Smart match #$matchId status updated to '$status'."
        );
    }

    header('Location: ' . url('admin/matches.php'));
    exit;
}


// ============================================================
// 6. VERIFY HANDOVER
// ============================================================
// This is the FINAL verification step.
//
// Before this:
// claims.status = Approved
// claims.handover_status = Proof Submitted
//
// After this:
// claims.status = Returned
// claims.handover_status = Verified
// ============================================================

if ($action === 'verify_handover') {

    $claimId = (int)($_POST['claim_id'] ?? 0);

    if ($claimId <= 0) {

        setFlash(
            'danger',
            'Invalid claim ID.'
        );

        header('Location: ' . url('admin/claims.php'));
        exit;
    }

    try {

        // Get claim information
        $stmt = $pdo->prepare("
            SELECT
                c.claim_id,
                c.found_id,
                c.user_id AS claimant_id,
                c.status,
                c.handover_status,
                c.handover_image,
                f.item_name,
                f.user_id AS finder_id
            FROM claims c
            INNER JOIN found_items f
                ON c.found_id = f.found_id
            WHERE c.claim_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $claimId
        ]);

        $claim = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$claim) {

            setFlash(
                'danger',
                'Claim not found.'
            );

            header('Location: ' . url('admin/claims.php'));
            exit;
        }

        // Ownership must already be approved
        if ($claim['status'] !== 'Approved') {

            setFlash(
                'danger',
                'This claim must be Approved before the handover can be verified.'
            );

            header(
                'Location: ' .
                url('admin/claims.php?highlight=' . $claimId)
            );

            exit;
        }

        // Proof must have been submitted
        if ($claim['handover_status'] !== 'Proof Submitted') {

            setFlash(
                'danger',
                'Handover proof has not been submitted or has already been processed.'
            );

            header(
                'Location: ' .
                url('admin/claims.php?highlight=' . $claimId)
            );

            exit;
        }

        // Make sure an image exists
        if (empty($claim['handover_image'])) {

            setFlash(
                'danger',
                'No handover proof image was found.'
            );

            header(
                'Location: ' .
                url('admin/claims.php?highlight=' . $claimId)
            );

            exit;
        }


        // Start transaction
        $pdo->beginTransaction();


        // --------------------------------------------------------
        // FINAL CLAIM UPDATE
        // --------------------------------------------------------

        $update = $pdo->prepare("
            UPDATE claims
            SET
                status = 'Returned',
                handover_status = 'Verified',
                handover_verified_by = ?,
                handover_verified_at = NOW(),
                handover_remark = NULL
            WHERE claim_id = ?
              AND status = 'Approved'
              AND handover_status = 'Proof Submitted'
        ");

        $update->execute([
            (int)$currentUser['id'],
            $claimId
        ]);


        if ($update->rowCount() === 0) {

            throw new Exception(
                'The claim could not be updated. It may have already been processed.'
            );
        }


        // --------------------------------------------------------
        // UPDATE FOUND ITEM
        // --------------------------------------------------------

        $foundUpdate = $pdo->prepare("
            UPDATE found_items
            SET status = 'Claimed'
            WHERE found_id = ?
        ");

        $foundUpdate->execute([
            (int)$claim['found_id']
        ]);


        // --------------------------------------------------------
        // NOTIFY CLAIMANT
        // --------------------------------------------------------

        createNotification(
            $pdo,
            (int)$claim['claimant_id'],
            'Item Returned Successfully',
            'The administrator has verified the handover of "' .
            $claim['item_name'] .
            '". The item has been marked as returned.',
            'Handover',
            'user/my-claims.php'
        );


        // --------------------------------------------------------
        // NOTIFY FINDER
        // --------------------------------------------------------

        createNotification(
            $pdo,
            (int)$claim['finder_id'],
            'Handover Verified',
            'The administrator has verified the handover of "' .
            $claim['item_name'] .
            '". The item has been successfully returned.',
            'Handover',
            'user/my-items.php'
        );


        // Commit all changes
        $pdo->commit();


        setFlash(
            'success',
            'Handover verified successfully. Claim status is now Returned.'
        );

        header(
            'Location: ' .
            url('admin/claims.php?status=all&highlight=' . $claimId)
        );

        exit;


    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        setFlash(
            'danger',
            'Failed to verify handover: ' . $e->getMessage()
        );

        header(
            'Location: ' .
            url('admin/claims.php?status=all&highlight=' . $claimId)
        );

        exit;
    }
}


// ============================================================
// 7. REJECT HANDOVER
// ============================================================
// Claim stays Approved.
// Only handover_status becomes Rejected.
// Finder can upload a new handover proof.
// ============================================================

if ($action === 'reject_handover') {

    $claimId = (int)($_POST['claim_id'] ?? 0);

    $remark = trim(
        $_POST['handover_remark'] ?? ''
    );

    if ($claimId <= 0) {

        setFlash(
            'danger',
            'Invalid claim ID.'
        );

        header('Location: ' . url('admin/claims.php'));
        exit;
    }

    if ($remark === '') {
        $remark = 'Handover proof was rejected. Please submit a new proof.';
    }

    try {

        $stmt = $pdo->prepare("
            SELECT
                c.claim_id,
                c.found_id,
                c.user_id AS claimant_id,
                c.status,
                c.handover_status,
                f.item_name,
                f.user_id AS finder_id
            FROM claims c
            INNER JOIN found_items f
                ON c.found_id = f.found_id
            WHERE c.claim_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $claimId
        ]);

        $claim = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$claim) {

            setFlash(
                'danger',
                'Claim not found.'
            );

            header('Location: ' . url('admin/claims.php'));
            exit;
        }

        if ($claim['status'] !== 'Approved') {

            setFlash(
                'danger',
                'Only an approved claim can have its handover rejected.'
            );

            header(
                'Location: ' .
                url('admin/claims.php?highlight=' . $claimId)
            );

            exit;
        }

        if ($claim['handover_status'] !== 'Proof Submitted') {

            setFlash(
                'danger',
                'There is no pending handover proof to reject.'
            );

            header(
                'Location: ' .
                url('admin/claims.php?highlight=' . $claimId)
            );

            exit;
        }


        $pdo->beginTransaction();


        // Reject handover but KEEP ownership approved
        $update = $pdo->prepare("
            UPDATE claims
            SET
                handover_status = 'Rejected',
                handover_verified_by = ?,
                handover_verified_at = NOW(),
                handover_remark = ?
            WHERE claim_id = ?
              AND status = 'Approved'
              AND handover_status = 'Proof Submitted'
        ");

        $update->execute([
            (int)$currentUser['id'],
            $remark,
            $claimId
        ]);


        if ($update->rowCount() === 0) {

            throw new Exception(
                'Unable to reject the handover.'
            );
        }


        // Notify finder
        createNotification(
            $pdo,
            (int)$claim['finder_id'],
            'Handover Proof Rejected',
            'The administrator rejected the handover proof for "' .
            $claim['item_name'] .
            '". Reason: ' .
            $remark,
            'Handover',
            'user/my-items.php'
        );


        // Notify claimant
        createNotification(
            $pdo,
            (int)$claim['claimant_id'],
            'Handover Proof Rejected',
            'The administrator rejected the submitted handover proof for "' .
            $claim['item_name'] .
            '". The finder has been asked to submit new proof.',
            'Handover',
            'user/my-claims.php'
        );


        $pdo->commit();


        setFlash(
            'warning',
            'Handover proof rejected. The claim remains Approved.'
        );

        header(
            'Location: ' .
            url('admin/claims.php?status=all&highlight=' . $claimId)
        );

        exit;


    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        setFlash(
            'danger',
            'Failed to reject handover: ' . $e->getMessage()
        );

        header(
            'Location: ' .
            url('admin/claims.php?status=all&highlight=' . $claimId)
        );

        exit;
    }
}
if ($action === 'delete_found_item') {

    $foundId = (int)($_POST['found_id'] ?? 0);

    if ($foundId <= 0) {

        setFlash(
            'danger',
            'Invalid found item ID.'
        );

        header('Location: ' . url('admin/reports.php'));
        exit;
    }

    try {

        // --------------------------------------------------------
        // CHECK FOUND ITEM
        // --------------------------------------------------------

        $stmt = $pdo->prepare("
            SELECT
                found_id,
                item_name
            FROM found_items
            WHERE found_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $foundId
        ]);

        $foundItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$foundItem) {

            setFlash(
                'danger',
                'Found item not found.'
            );

            header('Location: ' . url('admin/reports.php'));
            exit;
        }


        // --------------------------------------------------------
        // START TRANSACTION
        // --------------------------------------------------------

        $pdo->beginTransaction();


        // --------------------------------------------------------
        // DELETE SMART MATCHES
        // --------------------------------------------------------

        $deleteMatches = $pdo->prepare("
            DELETE FROM item_matches
            WHERE found_id = ?
        ");

        $deleteMatches->execute([
            $foundId
        ]);


        // --------------------------------------------------------
        // DELETE FOUND ITEM
        // --------------------------------------------------------

        $deleteFound = $pdo->prepare("
            DELETE FROM found_items
            WHERE found_id = ?
        ");

        $deleteFound->execute([
            $foundId
        ]);


        // --------------------------------------------------------
        // CHECK DELETE RESULT
        // --------------------------------------------------------

        if ($deleteFound->rowCount() === 0) {

            throw new Exception(
                'The found item could not be deleted.'
            );
        }


        // --------------------------------------------------------
        // COMMIT
        // --------------------------------------------------------

        $pdo->commit();


        setFlash(
            'success',
            'Found item "' . $foundItem['item_name'] . '" deleted successfully.'
        );

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'LOFO delete_found_item error: ' . $e->getMessage()
        );

        setFlash(
            'danger',
            'Unable to delete the found item. It may be linked to another record.'
        );
    }


    header('Location: ' . url('admin/reports.php'));
    exit;
}


// ============================================================
// UNKNOWN ACTION
// ============================================================

setFlash(
    'danger',
    'Unknown administrator action.'
);

header('Location: ' . url('admin/index.php'));
exit;

// ============================================================
// UNKNOWN ACTION
// ============================================================

setFlash(
    'danger',
    'Unknown administrator action.'
);

header('Location: ' . url('admin/index.php'));
exit;

