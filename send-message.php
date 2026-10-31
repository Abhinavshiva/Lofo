
<?php

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/notifications.php';


/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
| database.php provides getDBConnection(), so we must actually call it
| and store the returned PDO connection in $pdo.
*/
$pdo = getDBConnection();


/*
|--------------------------------------------------------------------------
| Only POST requests are allowed
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('user/dashboard.php'));
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Current User
|--------------------------------------------------------------------------
*/
$currentUser = currentUser();

if (!$currentUser) {
    http_response_code(403);
    die('You must be logged in to send a message.');
}

$currentUserId = (int)($currentUser['id'] ?? 0);

if ($currentUserId <= 0) {
    http_response_code(403);
    die('Invalid user session.');
}


/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/
$csrfToken = $_POST['csrf_token'] ?? '';

if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    die('Invalid CSRF token.');
}


/*
|--------------------------------------------------------------------------
| Get Claim ID
|--------------------------------------------------------------------------
*/
$claimId = isset($_POST['claim_id'])
    ? (int)$_POST['claim_id']
    : 0;


/*
|--------------------------------------------------------------------------
| Get Message
|--------------------------------------------------------------------------
*/
$message = trim($_POST['message'] ?? '');


/*
|--------------------------------------------------------------------------
| Validate Input
|--------------------------------------------------------------------------
*/
if ($claimId <= 0 || $message === '') {
    die('Invalid message.');
}

if (strlen($message) > 2000) {
    die('Message is too long.');
}


/*
|--------------------------------------------------------------------------
| Get Claim and Participants
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        c.claim_id,
        c.status,
        c.user_id AS claimant_id,
        f.user_id AS finder_id,
        f.item_name
    FROM claims c
    JOIN found_items f
        ON c.found_id = f.found_id
    WHERE c.claim_id = ?
    LIMIT 1
");

$stmt->execute([$claimId]);

$claim = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Claim Not Found
|--------------------------------------------------------------------------
*/
if (!$claim) {
    die('Claim not found.');
}


/*
|--------------------------------------------------------------------------
| Normalize Claim Status
|--------------------------------------------------------------------------
*/
$claimStatus = strtolower(
    trim((string)($claim['status'] ?? ''))
);


/*
|--------------------------------------------------------------------------
| Chat Available Only After Approval
|--------------------------------------------------------------------------
*/
if ($claimStatus !== 'approved') {
    die('Chat is not available for this claim.');
}


/*
|--------------------------------------------------------------------------
| Get Participants
|--------------------------------------------------------------------------
*/
$claimantId = (int)$claim['claimant_id'];
$finderId   = (int)$claim['finder_id'];


/*
|--------------------------------------------------------------------------
| Determine Receiver
|--------------------------------------------------------------------------
*/
if ($currentUserId === $claimantId) {

    // Claimant is sending message to finder.
    $receiverId = $finderId;

} elseif ($currentUserId === $finderId) {

    // Finder is sending message to claimant.
    $receiverId = $claimantId;

} else {

    http_response_code(403);
    die('You are not authorized to send messages in this chat.');
}


/*
|--------------------------------------------------------------------------
| Validate Receiver
|--------------------------------------------------------------------------
*/
if ($receiverId <= 0) {
    die('Invalid receiver.');
}


/*
|--------------------------------------------------------------------------
| Insert Message
|--------------------------------------------------------------------------
*/
$insert = $pdo->prepare("
    INSERT INTO messages
    (
        claim_id,
        sender_id,
        receiver_id,
        message,
        is_read,
        created_at
    )
    VALUES (?, ?, ?, ?, 0, NOW())
");

$insert->execute([
    $claimId,
    $currentUserId,
    $receiverId,
    $message
]);


/*
|--------------------------------------------------------------------------
| Send Notification
|--------------------------------------------------------------------------
*/
createNotification(
    $pdo,
    $receiverId,
    'New Handover Message',
    ($currentUser['name'] ?? 'User') .
    ' sent you a message regarding the handover of "' .
    $claim['item_name'] .
    '".',
    'Chat Message',
    'chat.php?claim_id=' . $claimId
);


/*
|--------------------------------------------------------------------------
| Redirect Back to Chat
|--------------------------------------------------------------------------
*/
header(
    'Location: ' .
    url('chat.php?claim_id=' . $claimId)
);

exit;

