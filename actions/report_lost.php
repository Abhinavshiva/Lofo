<?php
// LOFO - Report Lost Item Action

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/matcher.php';


/*
|--------------------------------------------------------------------------
| Allow POST Requests Only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('user/report_lost.php'));
    exit;
}


/*
|--------------------------------------------------------------------------
| Check Login
|--------------------------------------------------------------------------
*/

requireLogin();


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

    header('Location: ' . url('user/report_lost.php'));
    exit;
}


/*
|--------------------------------------------------------------------------
| Database and Current User
|--------------------------------------------------------------------------
*/

$pdo = getDBConnection();
$currentUser = currentUser();


/*
|--------------------------------------------------------------------------
| Validate Current User
|
| The session array uses `id`.
| The database users table uses `user_id`.
|--------------------------------------------------------------------------
*/

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
| Read Form Data
|--------------------------------------------------------------------------
*/

$itemName = trim($_POST['item_name'] ?? '');

$categoryId = (int) (
    $_POST['category_id'] ?? 0
);

$description = trim(
    $_POST['description'] ?? ''
);

$locationLost = trim(
    $_POST['location_lost'] ?? ''
);

$dateLost = trim(
    $_POST['date_lost'] ?? ''
);


/*
|--------------------------------------------------------------------------
| Validate Required Fields
|--------------------------------------------------------------------------
*/

if (
    empty($itemName) ||
    $categoryId <= 0 ||
    empty($description) ||
    empty($locationLost) ||
    empty($dateLost)
) {
    setFlash(
        'danger',
        'Please fill in all mandatory fields.'
    );

    header('Location: ' . url('user/report_lost.php'));
    exit;
}


/*
|--------------------------------------------------------------------------
| Handle Image Upload
|--------------------------------------------------------------------------
*/

$imagePath = null;

if (
    isset($_FILES['item_image']) &&
    $_FILES['item_image']['error'] === UPLOAD_ERR_OK
) {
    $file = $_FILES['item_image'];

    $extension = strtolower(
        pathinfo(
            $file['name'],
            PATHINFO_EXTENSION
        )
    );

    $allowedExtensions = [
        'jpg',
        'jpeg',
        'png',
        'webp'
    ];

    $maximumFileSize = 5 * 1024 * 1024;

    if (
        in_array(
            $extension,
            $allowedExtensions,
            true
        ) &&
        $file['size'] <= $maximumFileSize
    ) {
        $filename =
            'lost_' .
            time() .
            '_' .
            bin2hex(random_bytes(4)) .
            '.' .
            $extension;

        $uploadDirectory = __DIR__ . '/../uploads/';

        if (!is_dir($uploadDirectory)) {
            mkdir(
                $uploadDirectory,
                0777,
                true
            );
        }

        $uploadPath = $uploadDirectory . $filename;

        if (
            move_uploaded_file(
                $file['tmp_name'],
                $uploadPath
            )
        ) {
            $imagePath = 'uploads/' . $filename;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Save Lost Item Report
|--------------------------------------------------------------------------
*/

try {
    $stmt = $pdo->prepare("
        INSERT INTO lost_items
        (
            user_id,
            item_name,
            category_id,
            description,
            location_lost,
            date_lost,
            image_path,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Lost')
    ");

    $stmt->execute([
        $currentUserId,
        $itemName,
        $categoryId,
        $description,
        $locationLost,
        $dateLost,
        $imagePath
    ]);

    $newLostId = (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Notification to Report Owner
    |--------------------------------------------------------------------------
    */

    createNotification(
        $pdo,
        $currentUserId,
        'Lost Item Report Confirmation',
        "Your report for '{$itemName}' has been logged successfully. "
        . "The system will scan for matching found items automatically.",
        'Report Confirmation',
        "item-details.php?type=lost&id={$newLostId}"
    );


    /*
    |--------------------------------------------------------------------------
    | Send 5-Day Proof Notification
    |
    | This runs only when no image was uploaded.
    |--------------------------------------------------------------------------
    */

    if (empty($imagePath)) {
        $publicMessage =
            "A lost item report for '{$itemName}' was submitted "
            . "without an image. If you have proof or useful information "
            . "about this item, please submit it within 5 days. "
            . "The admin will continue the verification process "
            . "after the deadline.";

        /*
        | The users table uses `user_id`, not `id`.
        */

        $usersStmt = $pdo->prepare("
            SELECT user_id
            FROM users
            WHERE user_id != ?
        ");

        $usersStmt->execute([
            $currentUserId
        ]);

        $users = $usersStmt->fetchAll(
            PDO::FETCH_COLUMN
        );

        foreach ($users as $userId) {
            createNotification(
                $pdo,
                (int) $userId,
                '5-Day Proof Notice',
                $publicMessage,
                'Proof Required',
                "item-details.php?type=lost&id={$newLostId}"
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Trigger Smart Matcher
    |--------------------------------------------------------------------------
    */

    $matcher = new SmartMatcher($pdo);

    $matchesCount = $matcher->matchLostItem(
        $newLostId
    );

    $message =
        'Your lost item report has been published.';

    if ($matchesCount > 0) {
        $message .=
            " We found {$matchesCount} potential match(es) "
            . "already in the database! Check your dashboard.";
    }


    /*
    |--------------------------------------------------------------------------
    | Success Redirect
    |--------------------------------------------------------------------------
    */

    setFlash(
        'success',
        $message
    );

    header('Location: ' . url('user/my-items.php'));
    exit;

} catch (PDOException $e) {

    setFlash(
        'danger',
        'Error saving lost item report: ' . $e->getMessage()
    );

    header('Location: ' . url('user/report_lost.php'));
    exit;
}