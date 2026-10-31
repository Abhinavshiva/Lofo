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
    header('Location: ' . url('user/my-items.php'));
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Claim and Finder Information
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
        c.handover_remark,

        f.item_name,
        f.image_path,
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
    header('Location: ' . url('user/my-items.php'));
    exit;
}

/*
|--------------------------------------------------------------------------
| Only Finder Can Upload Handover Proof
|--------------------------------------------------------------------------
*/
if ($currentUserId !== (int)$claim['finder_id']) {

    http_response_code(403);

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>

        <meta charset="UTF-8">
        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

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

                            <i
                                class="fa-solid fa-lock fa-3x text-danger mb-3"
                            ></i>

                            <h4 class="mb-3">
                                Access Denied
                            </h4>

                            <p class="text-muted">
                                Only the finder of this item can upload
                                the handover proof.
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
| Claim Must Be Approved
|--------------------------------------------------------------------------
|
| Ownership must be approved before handover can happen.
|
*/
if ($claim['status'] !== 'Approved') {

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>Handover Unavailable</title>

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

                            <i
                                class="fa-solid fa-handshake-slash fa-3x text-warning mb-3"
                            ></i>

                            <h4 class="mb-3">
                                Handover Unavailable
                            </h4>

                            <p class="text-muted">
                                Handover proof can only be uploaded after
                                the claim has been approved by the admin.
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
| If Already Verified
|--------------------------------------------------------------------------
*/
if (($claim['handover_status'] ?? '') === 'Verified') {

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>Handover Verified</title>

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

                            <i
                                class="fa-solid fa-circle-check fa-3x text-success mb-3"
                            ></i>

                            <h4 class="mb-3">
                                Handover Already Verified
                            </h4>

                            <p class="text-muted">
                                The administrator has already verified
                                the handover of this item.
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
| Current Handover Status
|--------------------------------------------------------------------------
*/
$handoverStatus =
    $claim['handover_status'] ?? 'Not Started';

$isProofSubmitted =
    $handoverStatus === 'Proof Submitted';

$isRejected =
    $handoverStatus === 'Rejected';

/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/includes/header.php';

?>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-8">

            <div class="card shadow-sm">

                <!-- Header -->
                <div class="card-header bg-success text-white">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <h5 class="mb-0">

                                <i
                                    class="fa-solid fa-handshake me-2"
                                ></i>

                                Item Handover

                            </h5>

                            <small>

                                Upload proof after physically
                                handing over the item.

                            </small>

                        </div>

                        <span class="badge bg-light text-success">

                            Approved

                        </span>

                    </div>

                </div>

                <!-- Body -->
                <div class="card-body">

                    <!-- Item Information -->
                    <div class="row mb-4">

                        <div class="col-md-4">

                            <?php if (!empty($claim['image_path'])): ?>

                                <img
                                    src="<?= uploadUrl(
                                        $claim['image_path']
                                    ) ?>"
                                    class="img-fluid rounded border"
                                    alt="Item image"
                                    style="
                                        max-height:220px;
                                        width:100%;
                                        object-fit:cover;
                                    "
                                >

                            <?php else: ?>

                                <div
                                    class="
                                        border
                                        rounded
                                        d-flex
                                        align-items-center
                                        justify-content-center
                                        bg-light
                                    "
                                    style="height:200px;"
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-image
                                            fa-3x
                                            text-muted
                                        "
                                    ></i>

                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="col-md-8">

                            <h4 class="mb-3">

                                <?= htmlspecialchars(
                                    $claim['item_name']
                                ) ?>

                            </h4>

                            <p class="mb-2">

                                <strong>
                                    Finder:
                                </strong>

                                <?= htmlspecialchars(
                                    $claim['finder_name']
                                ) ?>

                            </p>

                            <p class="mb-2">

                                <strong>
                                    Claimant:
                                </strong>

                                <?= htmlspecialchars(
                                    $claim['claimant_name']
                                ) ?>

                            </p>

                            <p class="mb-2">

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

                                <?php endif; ?>

                            </p>

                        </div>

                    </div>

                    <hr>

                    <!-- Instructions -->
                    <div class="alert alert-info">

                        <h6>

                            <i
                                class="fa-solid fa-circle-info me-2"
                            ></i>

                            Handover Instructions

                        </h6>

                        <ol class="mb-0">

                            <li>
                                Contact the claimant using the private chat.
                            </li>

                            <li>
                                Agree on a suitable date, time and location.
                            </li>

                            <li>
                                Physically hand over the item to the claimant.
                            </li>

                            <li>
                                Take a clear photo showing the handover.
                            </li>

                            <li>
                                Upload the photo below.
                            </li>

                            <li>
                                Wait for the administrator to verify the proof.
                            </li>

                        </ol>

                    </div>

                    <!-- Rejection Message -->
                    <?php if ($isRejected): ?>

                        <div class="alert alert-danger">

                            <h6>

                                <i
                                    class="fa-solid fa-circle-exclamation me-2"
                                ></i>

                                Previous Proof Was Rejected

                            </h6>

                            <?php if (!empty($claim['handover_remark'])): ?>

                                <p class="mb-0">

                                    <strong>
                                        Admin Remark:
                                    </strong>

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $claim['handover_remark']
                                        )
                                    ) ?>

                                </p>

                            <?php else: ?>

                                <p class="mb-0">

                                    Please complete the handover again
                                    and upload a new proof image.

                                </p>

                            <?php endif; ?>

                        </div>

                    <?php endif; ?>

                    <!-- Waiting Status -->
                    <?php if ($isProofSubmitted): ?>

                        <div class="alert alert-warning">

                            <i
                                class="fa-solid fa-clock me-2"
                            ></i>

                            <strong>
                                Handover proof already submitted.
                            </strong>

                            Please wait for the administrator to review it.

                        </div>

                        <?php if (!empty($claim['handover_image'])): ?>

                            <div class="text-center mb-3">

                                <h6 class="mb-3">
                                    Submitted Handover Proof
                                </h6>

                                <img
                                    src="<?= uploadUrl(
                                        $claim['handover_image']
                                    ) ?>"
                                    class="img-fluid rounded border"
                                    alt="Handover proof"
                                    style="
                                        max-height:400px;
                                        object-fit:contain;
                                    "
                                >

                                <?php if (!empty(
                                    $claim['handover_uploaded_at']
                                )): ?>

                                    <p class="text-muted small mt-2">

                                        Uploaded on:

                                        <?= date(
                                            'M d, Y h:i A',
                                            strtotime(
                                                $claim[
                                                    'handover_uploaded_at'
                                                ]
                                            )
                                        ) ?>

                                    </p>

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>

                    <?php else: ?>

                        <!-- Upload Form -->
                        <form
                            method="POST"
                            action="<?= url(
                                'actions/handover_action.php'
                            ) ?>"
                            enctype="multipart/form-data"
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

                            <div class="mb-4">

                                <label
                                    for="handover_image"
                                    class="form-label fw-bold"
                                >

                                    <i
                                        class="fa-solid fa-camera me-1"
                                    ></i>

                                    Handover Proof Image

                                </label>

                                <input
                                    type="file"
                                    name="handover_image"
                                    id="handover_image"
                                    class="form-control"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                    required
                                >

                                <div class="form-text">

                                    Accepted formats:
                                    JPG, JPEG, PNG and WEBP.
                                    Maximum size: 5 MB.

                                </div>

                            </div>

                            <!-- Preview -->
                            <div
                                id="imagePreviewContainer"
                                class="text-center mb-4"
                                style="display:none;"
                            >

                                <p class="fw-bold">
                                    Image Preview
                                </p>

                                <img
                                    id="imagePreview"
                                    src=""
                                    alt="Preview"
                                    class="img-fluid rounded border"
                                    style="
                                        max-height:350px;
                                        object-fit:contain;
                                    "
                                >

                            </div>

                            <div class="alert alert-warning">

                                <i
                                    class="fa-solid fa-triangle-exclamation me-2"
                                ></i>

                                <strong>Important:</strong>

                                Only upload the proof after the item
                                has actually been handed over to the claimant.
                                The administrator will verify the image
                                before marking the claim as
                                <strong>Returned</strong>.

                            </div>

                            <div
                                class="
                                    d-flex
                                    justify-content-between
                                    align-items-center
                                    flex-wrap
                                    gap-2
                                "
                            >

                                <a
                                    href="<?= url(
                                        'chat.php?claim_id='
                                        . $claimId
                                    ) ?>"
                                    class="btn btn-outline-primary"
                                >

                                    <i
                                        class="fa-solid fa-comments me-1"
                                    ></i>

                                    Open Chat

                                </a>

                                <button
                                    type="submit"
                                    class="btn btn-success"
                                >

                                    <i
                                        class="fa-solid fa-upload me-1"
                                    ></i>

                                    Submit Handover Proof

                                </button>

                            </div>

                        </form>

                    <?php endif; ?>

                </div>

                <!-- Footer -->
                <div class="card-footer bg-white">

                    <a
                        href="<?= url('user/my-items.php') ?>"
                        class="btn btn-outline-secondary btn-sm"
                    >

                        <i
                            class="fa-solid fa-arrow-left me-1"
                        ></i>

                        Back to My Items

                    </a>

                </div>

            </div>

        </div>

    </div>

</div>

<script>

document.addEventListener('DOMContentLoaded', function () {

    const imageInput =
        document.getElementById('handover_image');

    const previewContainer =
        document.getElementById(
            'imagePreviewContainer'
        );

    const preview =
        document.getElementById('imagePreview');

    if (imageInput) {

        imageInput.addEventListener(
            'change',
            function () {

                const file = this.files[0];

                if (!file) {

                    previewContainer.style.display =
                        'none';

                    preview.src = '';

                    return;
                }

                /*
                 * Check file size.
                 * Maximum = 5 MB.
                 */
                const maxSize =
                    5 * 1024 * 1024;

                if (file.size > maxSize) {

                    alert(
                        'The selected image is larger than 5 MB.'
                    );

                    this.value = '';

                    previewContainer.style.display =
                        'none';

                    preview.src = '';

                    return;
                }

                /*
                 * Check image type.
                 */
                const allowedTypes = [
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ];

                if (
                    !allowedTypes.includes(
                        file.type
                    )
                ) {

                    alert(
                        'Please select a JPG, PNG or WEBP image.'
                    );

                    this.value = '';

                    previewContainer.style.display =
                        'none';

                    preview.src = '';

                    return;
                }

                /*
                 * Show preview.
                 */
                const reader =
                    new FileReader();

                reader.onload =
                    function (event) {

                        preview.src =
                            event.target.result;

                        previewContainer.style.display =
                            'block';

                    };

                reader.readAsDataURL(file);

            }
        );

    }

});

</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>