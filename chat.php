<?php

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/notifications.php';

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/
$pdo = getDBConnection();

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
| Get Claim ID
|--------------------------------------------------------------------------
*/
$claimId = isset($_GET['claim_id'])
    ? (int)$_GET['claim_id']
    : 0;

if ($claimId <= 0) {
    header('Location: ' . url('user/dashboard.php'));
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Claim Information
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
        c.handover_uploaded_at,

        f.item_name AS found_name,
        f.image_path AS found_image,
        f.user_id AS finder_id,

        finder.name AS finder_name,
        claimant.name AS claimant_name

    FROM claims c

    INNER JOIN found_items f
        ON c.found_id = f.found_id

    INNER JOIN users finder
        ON f.user_id = finder.user_id

    INNER JOIN users claimant
        ON c.user_id = claimant.user_id

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
    header('Location: ' . url('user/dashboard.php'));
    exit;
}

/*
|--------------------------------------------------------------------------
| Chat Available Only After Approval
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Approved = ownership verified.
| Returned = final handover verified.
|
*/
if ($claim['status'] !== 'Approved') {

    $message = 'Chat is available only after the claim has been approved by the admin.';

    if ($claim['status'] === 'Returned') {
        $message = 'This claim has already been returned and verified by the admin. The chat is now closed.';
    }

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Chat Unavailable</title>

        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
            rel="stylesheet"
        >

        <link
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
            rel="stylesheet"
        >
    </head>

    <body class="bg-light">

        <div class="container py-5">

            <div class="row justify-content-center">

                <div class="col-md-6">

                    <div class="card shadow-sm">

                        <div class="card-body text-center p-5">

                            <i class="fa-solid fa-comments-slash fa-3x text-muted mb-3"></i>

                            <h4 class="mb-3">
                                Chat Unavailable
                            </h4>

                            <p class="text-muted">
                                <?= htmlspecialchars($message) ?>
                            </p>

                            <a
                                href="<?= url('user/my-items.php') ?>"
                                class="btn btn-primary"
                            >
                                <i class="fa-solid fa-arrow-left me-1"></i>
                                Back to My Items
                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </body>
    </html>
    <?php

    exit;
}

/*
|--------------------------------------------------------------------------
| Check User Permission
|--------------------------------------------------------------------------
|
| Only:
| 1. Claimant
| 2. Finder
|
| can access this private chat.
|
*/
$isClaimant = (
    $currentUserId === (int)$claim['claimant_id']
);

$isFinder = (
    $currentUserId === (int)$claim['finder_id']
);

if (!$isClaimant && !$isFinder) {

    http_response_code(403);

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied</title>

        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
            rel="stylesheet"
        >

        <link
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
            rel="stylesheet"
        >
    </head>

    <body class="bg-light">

        <div class="container py-5">

            <div class="row justify-content-center">

                <div class="col-md-6">

                    <div class="card shadow-sm">

                        <div class="card-body text-center p-5">

                            <i class="fa-solid fa-lock fa-3x text-danger mb-3"></i>

                            <h4>
                                Access Denied
                            </h4>

                            <p class="text-muted">
                                You are not authorized to access this private chat.
                            </p>

                            <a
                                href="<?= url('user/dashboard.php') ?>"
                                class="btn btn-primary"
                            >
                                Back to Dashboard
                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </body>
    </html>
    <?php

    exit;
}

/*
|--------------------------------------------------------------------------
| Mark Received Messages as Read
|--------------------------------------------------------------------------
*/
$readStmt = $pdo->prepare("
    UPDATE messages
    SET is_read = 1
    WHERE claim_id = ?
      AND receiver_id = ?
");

$readStmt->execute([
    $claimId,
    $currentUserId
]);

/*
|--------------------------------------------------------------------------
| Get Messages
|--------------------------------------------------------------------------
*/
$msgStmt = $pdo->prepare("
    SELECT
        m.message_id,
        m.claim_id,
        m.sender_id,
        m.receiver_id,
        m.message,
        m.is_read,
        m.created_at,

        u.name AS sender_name

    FROM messages m

    INNER JOIN users u
        ON m.sender_id = u.user_id

    WHERE m.claim_id = ?

    ORDER BY m.created_at ASC, m.message_id ASC
");

$msgStmt->execute([$claimId]);

$messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Determine Other User
|--------------------------------------------------------------------------
*/
if ($isClaimant) {

    $otherUserName = $claim['finder_name'];
    $otherUserRole = 'Finder';

} else {

    $otherUserName = $claim['claimant_name'];
    $otherUserRole = 'Claimant';
}

/*
|--------------------------------------------------------------------------
| Handover Status
|--------------------------------------------------------------------------
*/
$handoverStatus = $claim['handover_status'] ?? 'Not Started';

require_once __DIR__ . '/includes/header.php';

?>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-8">

            <!-- Chat Card -->
            <div class="card shadow-sm">

                <!-- Header -->
                <div class="card-header bg-primary text-white">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <h5 class="mb-0">

                                <i class="fa-solid fa-comments me-2"></i>

                                Handover Chat

                            </h5>

                            <small>

                                <?= htmlspecialchars($claim['found_name']) ?>

                            </small>

                        </div>

                        <span class="badge bg-success">

                            Approved

                        </span>

                    </div>

                </div>

                <!-- Body -->
                <div class="card-body">

                    <!-- Participants -->
                    <div class="alert alert-light border">

                        <div class="row">

                            <div class="col-md-6">

                                <strong>
                                    <i class="fa-solid fa-user me-1"></i>
                                    You
                                </strong>

                                <br>

                                <span class="text-muted">

                                    <?= htmlspecialchars($currentUser['name']) ?>

                                </span>

                            </div>

                            <div class="col-md-6">

                                <strong>
                                    <i class="fa-solid fa-user me-1"></i>
                                    <?= htmlspecialchars($otherUserRole) ?>
                                </strong>

                                <br>

                                <span class="text-muted">

                                    <?= htmlspecialchars($otherUserName) ?>

                                </span>

                            </div>

                        </div>

                    </div>

                    <!-- Information -->
                    <div class="alert alert-info">

                        <i class="fa-solid fa-circle-info me-2"></i>

                        Use this private chat to arrange the
                        <strong>date, time and location</strong>
                        for handing over the item.

                    </div>

                    <!-- Handover Status -->
                    <div class="mb-3">

                        <strong>
                            Handover Status:
                        </strong>

                        <?php if ($handoverStatus === 'Not Started'): ?>

                            <span class="badge bg-secondary">
                                Not Started
                            </span>

                        <?php elseif ($handoverStatus === 'Proof Submitted'): ?>

                            <span class="badge bg-warning text-dark">
                                Proof Submitted
                            </span>

                        <?php elseif ($handoverStatus === 'Rejected'): ?>

                            <span class="badge bg-danger">
                                Rejected
                            </span>

                        <?php elseif ($handoverStatus === 'Verified'): ?>

                            <span class="badge bg-success">
                                Verified
                            </span>

                        <?php endif; ?>

                    </div>

                    <!-- Chat Messages -->
                    <div
                        class="border rounded p-3 mb-3 bg-light"
                        style="
                            height: 400px;
                            overflow-y: auto;
                        "
                        id="chatBox"
                    >

                        <?php if (empty($messages)): ?>

                            <div class="text-center text-muted mt-5">

                                <i
                                    class="fa-solid fa-comments fa-2x mb-3"
                                ></i>

                                <p class="mb-1">

                                    No messages yet.

                                </p>

                                <small>

                                    Start the conversation to arrange
                                    the handover.

                                </small>

                            </div>

                        <?php else: ?>

                            <?php foreach ($messages as $message): ?>

                                <?php

                                $isMine =
                                    (int)$message['sender_id']
                                    === $currentUserId;

                                ?>

                                <div
                                    class="mb-3
                                    <?= $isMine
                                        ? 'text-end'
                                        : 'text-start'
                                    ?>"
                                >

                                    <div
                                        class="
                                            d-inline-block
                                            p-3
                                            rounded
                                            shadow-sm
                                            <?= $isMine
                                                ? 'bg-primary text-white'
                                                : 'bg-white border'
                                            ?>
                                        "
                                        style="
                                            max-width: 75%;
                                            min-width: 120px;
                                        "
                                    >

                                        <!-- Sender -->
                                        <div class="small fw-bold mb-1">

                                            <?= htmlspecialchars(
                                                $message['sender_name']
                                            ) ?>

                                        </div>

                                        <!-- Message -->
                                        <div
                                            style="
                                                word-wrap: break-word;
                                                white-space: normal;
                                            "
                                        >

                                            <?= nl2br(
                                                htmlspecialchars(
                                                    $message['message']
                                                )
                                            ) ?>

                                        </div>

                                        <!-- Time -->
                                        <div class="small opacity-75 mt-1">

                                            <?= date(
                                                'M d, Y h:i A',
                                                strtotime(
                                                    $message['created_at']
                                                )
                                            ) ?>

                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>

                    <!-- Send Message -->
                    <form
                        method="POST"
                        action="<?= url('send-message.php') ?>"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                csrfToken()
                            ) ?>"
                        >

                        <input
                            type="hidden"
                            name="claim_id"
                            value="<?= $claimId ?>"
                        >

                        <div class="input-group">

                            <textarea
                                name="message"
                                id="message"
                                class="form-control"
                                rows="2"
                                maxlength="2000"
                                placeholder="Type your message..."
                                required
                            ></textarea>

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >

                                <i
                                    class="fa-solid fa-paper-plane me-1"
                                ></i>

                                Send

                            </button>

                        </div>

                        <div class="form-text">

                            Maximum 2000 characters.

                        </div>

                    </form>

                </div>

                <!-- Footer -->
                <div class="card-footer bg-white">

                    <div
                        class="d-flex justify-content-between
                        align-items-center flex-wrap gap-2"
                    >

                        <a
                            href="<?= url('user/my-items.php') ?>"
                            class="btn btn-outline-secondary btn-sm"
                        >

                            <i
                                class="fa-solid fa-arrow-left me-1"
                            ></i>

                            Back to My Items

                        </a>

                        <?php if ($isFinder): ?>

                            <?php if ($handoverStatus === 'Not Started'): ?>

                                <a
                                    href="<?= url(
                                        'handover.php?claim_id='
                                        . $claimId
                                    ) ?>"
                                    class="btn btn-success btn-sm"
                                >

                                    <i
                                        class="fa-solid fa-handshake me-1"
                                    ></i>

                                    Upload Handover Proof

                                </a>

                            <?php elseif ($handoverStatus === 'Rejected'): ?>

                                <a
                                    href="<?= url(
                                        'handover.php?claim_id='
                                        . $claimId
                                    ) ?>"
                                    class="btn btn-warning btn-sm"
                                >

                                    <i
                                        class="fa-solid fa-upload me-1"
                                    ></i>

                                    Upload New Proof

                                </a>

                            <?php elseif ($handoverStatus === 'Proof Submitted'): ?>

                                <span class="text-warning small">

                                    <i
                                        class="fa-solid fa-clock me-1"
                                    ></i>

                                    Waiting for admin verification

                                </span>

                            <?php elseif ($handoverStatus === 'Verified'): ?>

                                <span class="text-success small">

                                    <i
                                        class="fa-solid fa-circle-check me-1"
                                    ></i>

                                    Handover verified

                                </span>

                            <?php endif; ?>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const chatBox = document.getElementById('chatBox');
    const messageBox = document.getElementById('message');

    /*
     * Scroll to the latest message.
     */
    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    /*
     * Allow Ctrl + Enter to send the message.
     */
    if (messageBox) {

        messageBox.addEventListener('keydown', function (event) {

            if (
                event.ctrlKey &&
                event.key === 'Enter'
            ) {

                event.preventDefault();

                this.closest('form').submit();

            }

        });

    }

});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>