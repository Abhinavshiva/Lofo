<?php
// LOFO - Delete Found Item
// Allows a user to delete their own found-item report
// only when no claim exists.

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

requireLogin();

$pdo = getDBConnection();
$currentUser = currentUser();


// ------------------------------------------------------------
// ONLY POST REQUESTS
// ------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: ' . url('user/my-items.php'));
    exit;
}


// ------------------------------------------------------------
// CSRF VALIDATION
// ------------------------------------------------------------

$csrf = $_POST['csrf_token'] ?? '';

if (!verifyCsrfToken($csrf)) {

    setFlash(
        'danger',
        'Security validation failed. Please try again.'
    );

    header('Location: ' . url('user/my-items.php'));
    exit;
}


// ------------------------------------------------------------
// GET FOUND ITEM ID
// ------------------------------------------------------------

$foundId = (int)($_POST['found_item_id'] ?? 0);

if ($foundId <= 0) {

    setFlash(
        'danger',
        'Invalid found item.'
    );

    header('Location: ' . url('user/my-items.php'));
    exit;
}


$userId = (int)$currentUser['id'];


try {

    // --------------------------------------------------------
    // CHECK OWNERSHIP AND EXISTING CLAIMS
    // --------------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT
            f.found_id,
            f.item_name,
            f.user_id,

            (
                SELECT COUNT(*)
                FROM claims c
                WHERE c.found_id = f.found_id
            ) AS claim_count

        FROM found_items f

        WHERE f.found_id = ?
          AND f.user_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $foundId,
        $userId
    ]);

    $item = $stmt->fetch(PDO::FETCH_ASSOC);


    // --------------------------------------------------------
    // ITEM NOT FOUND / NOT OWNED
    // --------------------------------------------------------

    if (!$item) {

        setFlash(
            'danger',
            'Found item not found or you do not have permission to delete it.'
        );

        header('Location: ' . url('user/my-items.php'));
        exit;
    }


    // --------------------------------------------------------
    // DO NOT DELETE IF ANY CLAIM EXISTS
    // --------------------------------------------------------

    if ((int)$item['claim_count'] > 0) {

        setFlash(
            'warning',
            'This found item cannot be deleted because a claim already exists for it.'
        );

        header('Location: ' . url('user/my-items.php'));
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

    $deleteItem = $pdo->prepare("
        DELETE FROM found_items
        WHERE found_id = ?
          AND user_id = ?
    ");

    $deleteItem->execute([
        $foundId,
        $userId
    ]);


    if ($deleteItem->rowCount() === 0) {

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
        'Found item "' . $item['item_name'] . '" deleted successfully.'
    );


} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'LOFO delete found item error: ' . $e->getMessage()
    );

    setFlash(
        'danger',
        'Unable to delete the found item. Please try again.'
    );
}


// ------------------------------------------------------------
// RETURN TO MY ITEMS
// ------------------------------------------------------------

header('Location: ' . url('user/my-items.php'));
exit;