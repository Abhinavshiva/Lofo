<?php
// LOFO - Delete Lost Item Action

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('user/my-items.php'));
    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF Validation
|--------------------------------------------------------------------------
*/

$csrf = $_POST['csrf_token'] ?? '';

if (!verifyCsrfToken($csrf)) {
    setFlash(
        'danger',
        'Security validation failed. Please try again.'
    );

    header('Location: ' . url('user/my-items.php'));
    exit;
}


/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

$currentUser = currentUser();

if (!$currentUser || empty($currentUser['id'])) {
    setFlash(
        'danger',
        'User session expired. Please log in again.'
    );

    header('Location: ' . url('login.php'));
    exit;
}

$currentUserId = (int) $currentUser['id'];


/*
|--------------------------------------------------------------------------
| Get Lost Report ID
|--------------------------------------------------------------------------
*/

$lostId = (int) (
    $_POST['lost_item_id'] ?? 0
);

if ($lostId <= 0) {
    setFlash(
        'danger',
        'Invalid lost item selected.'
    );

    header('Location: ' . url('user/my-items.php'));
    exit;
}


/*
|--------------------------------------------------------------------------
| Delete Only the Logged-in User's Report
|--------------------------------------------------------------------------
*/

try {
    $pdo = getDBConnection();

    /*
    | Your table uses lost_id, not lost_item_id.
    */

    $checkStmt = $pdo->prepare("
        SELECT image_path
        FROM lost_items
        WHERE lost_id = ?
        AND user_id = ?
        LIMIT 1
    ");

    $checkStmt->execute([
        $lostId,
        $currentUserId
    ]);

    $item = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        setFlash(
            'danger',
            'The report was not found or you are not allowed to delete it.'
        );

        header('Location: ' . url('user/my-items.php'));
        exit;
    }


    /*
    | Delete the report.
    */

    $deleteStmt = $pdo->prepare("
        DELETE FROM lost_items
        WHERE lost_id = ?
        AND user_id = ?
    ");

    $deleteStmt->execute([
        $lostId,
        $currentUserId
    ]);


    /*
    | Delete the uploaded image, if available.
    */

    if (!empty($item['image_path'])) {
        $imageFile = __DIR__ . '/../' . $item['image_path'];

        if (is_file($imageFile)) {
            unlink($imageFile);
        }
    }


    /*
    | Success Message
    */

    setFlash(
        'success',
        'Your lost item report was deleted successfully.'
    );

    header('Location: ' . url('user/my-items.php'));
    exit;

} catch (PDOException $e) {
    setFlash(
        'danger',
        'Unable to delete the lost item report: ' . $e->getMessage()
    );

    header('Location: ' . url('user/my-items.php'));
    exit;
}