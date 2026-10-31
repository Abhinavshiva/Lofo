<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/notifications.php';

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/
$pdo = getDBConnection();

/*
|--------------------------------------------------------------------------
| Only POST Requests
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('user/dashboard.php'));
    exit;
}

/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/
$currentUser = currentUser();

if (!$currentUser) {
    header('Location: ' . url('login.php'));
    exit;
}

$currentUserId = (int)$currentUser['id'];

/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/
$csrfToken = $_POST['csrf_token'] ?? '';

if (empty($csrfToken)) {
    die('Security token is missing. Please go back and try again.');
}

verifyCsrfToken($csrfToken);

/*
|--------------------------------------------------------------------------
| Get Claim ID
|--------------------------------------------------------------------------
*/
$claimId = isset($_POST['claim_id'])
    ? (int)$_POST['claim_id']
    : 0;

if ($claimId <= 0) {
    die('Invalid claim.');
}

/*
|--------------------------------------------------------------------------
| Get Claim Details
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        c.claim_id,
        c.found_id,
        c.user_id AS claimant_id,
        c.status,
        c.handover_status,
        c.handover_image,

        f.user_id AS finder_id,
        f.item_name,

        claimant.name AS claimant_name,
        finder.name AS finder_name

    FROM claims c

    INNER JOIN found_items f
        ON c.found_id = f.found_id

    INNER JOIN users claimant
        ON c.user_id = claimant.user_id

    INNER JOIN users finder
        ON f.user_id = finder.user_id

    WHERE c.claim_id = ?

    LIMIT 1
");

$stmt->execute([$claimId]);

$claim = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$claim) {
    die('Claim not found.');
}

/*
|--------------------------------------------------------------------------
| Only Finder Can Submit Handover Proof
|--------------------------------------------------------------------------
*/
if ($currentUserId !== (int)$claim['finder_id']) {

    http_response_code(403);

    die(
        'Only the finder of the item can submit handover proof.'
    );
}

/*
|--------------------------------------------------------------------------
| Claim Must Be Approved
|--------------------------------------------------------------------------
*/
if ($claim['status'] !== 'Approved') {

    die(
        'The claim must be approved before submitting handover proof.'
    );
}

/*
|--------------------------------------------------------------------------
| Prevent Upload If Already Verified
|--------------------------------------------------------------------------
*/
if (($claim['handover_status'] ?? '') === 'Verified') {

    die(
        'The handover has already been verified by the administrator.'
    );
}

/*
|--------------------------------------------------------------------------
| Check Uploaded File
|--------------------------------------------------------------------------
*/
if (
    !isset($_FILES['handover_image']) ||
    $_FILES['handover_image']['error'] !== UPLOAD_ERR_OK
) {

    die(
        'Please select a valid handover image.'
    );
}

$file = $_FILES['handover_image'];

/*
|--------------------------------------------------------------------------
| Maximum File Size: 5 MB
|--------------------------------------------------------------------------
*/
$maxFileSize = 5 * 1024 * 1024;

if ($file['size'] <= 0) {

    die(
        'The uploaded image is empty.'
    );
}

if ($file['size'] > $maxFileSize) {

    die(
        'Image must be smaller than 5 MB.'
    );
}

/*
|--------------------------------------------------------------------------
| Validate MIME Type
|--------------------------------------------------------------------------
*/
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
];

if (!is_uploaded_file($file['tmp_name'])) {

    die(
        'Invalid uploaded file.'
    );
}

$finfo = new finfo(FILEINFO_MIME_TYPE);

$mime = $finfo->file(
    $file['tmp_name']
);

if (!isset($allowedTypes[$mime])) {

    die(
        'Only JPG, PNG and WEBP images are allowed.'
    );
}

/*
|--------------------------------------------------------------------------
| Create Upload Directory
|--------------------------------------------------------------------------
*/
$uploadDir = __DIR__ . '/../uploads/handover/';

if (!is_dir($uploadDir)) {

    if (!mkdir($uploadDir, 0755, true)) {

        die(
            'Unable to create the handover upload directory.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Generate Safe File Name
|--------------------------------------------------------------------------
*/
$extension = $allowedTypes[$mime];

$fileName =
    'handover_' .
    $claimId .
    '_' .
    time() .
    '_' .
    bin2hex(random_bytes(4)) .
    '.' .
    $extension;

$targetPath = $uploadDir . $fileName;

/*
|--------------------------------------------------------------------------
| Move Uploaded File
|--------------------------------------------------------------------------
*/
if (
    !move_uploaded_file(
        $file['tmp_name'],
        $targetPath
    )
) {

    die(
        'Failed to upload the handover image.'
    );
}

/*
|--------------------------------------------------------------------------
| Database Path
|--------------------------------------------------------------------------
*/
$dbPath =
    'uploads/handover/' . $fileName;

/*
|--------------------------------------------------------------------------
| Save Previous Image Path
|--------------------------------------------------------------------------
*/
$oldImage = $claim['handover_image'] ?? null;

/*
|--------------------------------------------------------------------------
| Update Claim
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| Uploading proof DOES NOT change the claim to Returned.
|
| Claim status:
| Approved
|
| Handover status:
| Proof Submitted
|
| Only the admin can change the final claim status
| to Returned after verifying the image.
|
|--------------------------------------------------------------------------
*/
try {

    $pdo->beginTransaction();

    $update = $pdo->prepare("
        UPDATE claims
        SET
            handover_image = ?,
            handover_uploaded_at = NOW(),
            handover_status = 'Proof Submitted',
            handover_verified_by = NULL,
            handover_verified_at = NULL,
            handover_remark = NULL
        WHERE claim_id = ?
          AND status = 'Approved'
    ");

    $update->execute([
        $dbPath,
        $claimId
    ]);

    if ($update->rowCount() === 0) {

        $pdo->rollBack();

        if (file_exists($targetPath)) {
            unlink($targetPath);
        }

        die(
            'Unable to update the claim.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Notify Admin Users
    |--------------------------------------------------------------------------
    */
    $adminStmt = $pdo->query("
        SELECT user_id
        FROM users
        WHERE role = 'Admin'
    ");

    $admins = $adminStmt->fetchAll(
        PDO::FETCH_COLUMN
    );

    foreach ($admins as $adminId) {

        createNotification(
            $pdo,
            (int)$adminId,
            'Handover Proof Submitted',
            'Handover proof has been submitted for "' .
            $claim['item_name'] .
            '". Please review and verify the handover.',
            'Handover',
            'admin/claims.php'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Notify Claimant
    |--------------------------------------------------------------------------
    */
    createNotification(
        $pdo,
        (int)$claim['claimant_id'],
        'Handover Proof Submitted',
        'The finder has submitted handover proof for "' .
        $claim['item_name'] .
        '". The administrator will review it.',
        'Handover',
        'user/my-claims.php'
    );

    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */
    $pdo->commit();

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Uploaded File If Database Operation Failed
    |--------------------------------------------------------------------------
    */
    if (file_exists($targetPath)) {
        unlink($targetPath);
    }

    die(
        'An error occurred while submitting the handover proof.'
    );
}

/*
|--------------------------------------------------------------------------
| Delete Old Rejected Proof
|--------------------------------------------------------------------------
|
| This happens only after the new proof has been
| successfully saved.
|--------------------------------------------------------------------------
*/
if (
    !empty($oldImage) &&
    $oldImage !== $dbPath
) {

    $oldImagePath =
        __DIR__ .
        '/../' .
        ltrim($oldImage, '/\\');

    if (
        file_exists($oldImagePath) &&
        is_file($oldImagePath)
    ) {

        @unlink($oldImagePath);
    }
}

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/
header(
    'Location: ' .
    url('user/my-items.php')
);

exit;