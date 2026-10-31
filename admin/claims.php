<?php
// LOFO - Claims & Two-Stage Verification Center
$pageTitle = "Claims Verification Center";

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/notifications.php';

requireAdmin();

$pdo = getDBConnection();

$filterStatus = $_GET['status'] ?? 'all';
$highlightId = (int)($_GET['highlight'] ?? 0);


/*
|--------------------------------------------------------------------------
| Fetch Claims
|--------------------------------------------------------------------------
| Claim status:
| Pending
| Under Review
| Approved
| Rejected
| Returned
|
| Handover status:
| Not Started
| Proof Submitted
| Verified
| Rejected
|--------------------------------------------------------------------------
*/

$query = "
    SELECT 
        c.*,

        /* Found item */
        f.item_name AS found_name,
        f.location_found,
        f.date_found,
        f.image_path AS found_image,
        f.user_id AS finder_id,

        finder.name AS founder_name,
finder.email AS founder_email,
finder.phone AS founder_phone,
finder.role AS founder_role,
finder.department AS founder_dept,

        /* Original linked lost item */
        l.item_name AS linked_lost_name,
        l.date_lost AS linked_lost_date,
        l.location_lost AS linked_lost_location,

        /* Claimant */
        u.name AS claimant_name,
        u.email AS claimant_email,
        u.phone AS claimant_phone,
        u.role AS claimant_role,
        u.department AS claimant_dept,

        /* Claim verifier */
        v.name AS verifier_name,

        /* Handover verifier */
        hv.name AS handover_verifier_name

    FROM claims c

    JOIN found_items f
        ON c.found_id = f.found_id

    LEFT JOIN users finder
    ON f.user_id = finder.user_id

    LEFT JOIN lost_items l
        ON c.lost_id = l.lost_id

    JOIN users u
        ON c.user_id = u.user_id

    LEFT JOIN users v
        ON c.verified_by = v.user_id

    LEFT JOIN users hv
        ON c.handover_verified_by = hv.user_id
";


$params = [];


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($filterStatus !== 'all') {

    $query .= "
        WHERE LOWER(TRIM(c.status)) =
              LOWER(TRIM(?))
    ";

    $params[] = $filterStatus;
}


/*
|--------------------------------------------------------------------------
| Ordering
|--------------------------------------------------------------------------
*/

$query .= "
    ORDER BY
        (LOWER(TRIM(c.status)) = 'pending') DESC,
        (LOWER(TRIM(c.handover_status)) = 'proof submitted') DESC,
        c.created_at DESC
";


$stmt = $pdo->prepare($query);
$stmt->execute($params);

$claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>


<div class="container py-4">

    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

        <div>

            <span class="badge bg-primary px-3 py-1 mb-2 text-uppercase fs-xxs">
                2-Stage Security Verification
            </span>

            <h3 class="fw-bold text-dark mb-1">
                Ownership Claims Verification
            </h3>

            <p class="text-muted fs-sm mb-0">
                Evaluate claimant evidence, verify physical handover,
                and authorize completed returns.
            </p>

        </div>


        <!-- =====================================================
             STATUS FILTERS
        ====================================================== -->

        <div class="d-flex gap-2 flex-wrap">

            <a
                href="<?= url('admin/claim.php?status=Pending') ?>"
                class="btn btn-warning btn-sm fw-semibold text-dark"
            >
                <i class="fa-solid fa-clock me-1"></i>
                Pending Verification
            </a>


            <a
                href="<?= url('admin/claim.php?status=Approved') ?>"
                class="btn btn-outline-success btn-sm"
            >
                <i class="fa-solid fa-circle-check me-1"></i>
                Approved
            </a>


            <a
                href="<?= url('admin/claim.php?status=Returned') ?>"
                class="btn btn-outline-primary btn-sm"
            >
                <i class="fa-solid fa-handshake me-1"></i>
                Returned
            </a>


            <a
                href="<?= url('admin/claim.php?status=all') ?>"
                class="btn btn-outline-secondary btn-sm"
            >
                View All
            </a>

        </div>

    </div>



    <!-- =========================================================
         EMPTY STATE
    ========================================================== -->

    <?php if (empty($claims)): ?>

        <div class="card shadow-sm border-0 p-5 text-center">

            <i class="fa-solid fa-stamp fs-1 text-muted opacity-50 mb-3"></i>

            <h5>
                No Claims in this Category
            </h5>

            <p class="text-muted fs-sm">
                All ownership claims have been addressed.
            </p>

            <div>

                <a
                    href="<?= url('admin/claim.php?status=all') ?>"
                    class="btn btn-primary btn-sm"
                >
                    View All Claims
                </a>

            </div>

        </div>

    <?php else: ?>


        <!-- =====================================================
             CLAIM CARDS
        ====================================================== -->

        <div class="row g-4">

            <?php foreach ($claims as $claim): ?>

                <?php

                // -------------------------------------------------
                // Normalize Claim Status
                // -------------------------------------------------

                $rawStatus = trim(
                    (string)($claim['status'] ?? '')
                );

                $status = strtolower($rawStatus);

                switch ($status) {

                    case 'pending':
                    case 'pending verification':

                        $normalizedStatus = 'pending';

                        break;

                    case 'under review':
                    case 'under_review':
                    case 'review':

                        $normalizedStatus = 'under_review';

                        break;

                    case 'approved':
                    case 'approve':

                        $normalizedStatus = 'approved';

                        break;

                    case 'rejected':
                    case 'reject':

                        $normalizedStatus = 'rejected';

                        break;

                    case 'returned':
                    case 'return':

                        $normalizedStatus = 'returned';

                        break;

                    default:

                        $normalizedStatus = '';

                        break;
                }


                // -------------------------------------------------
                // Claim Status Flags
                // -------------------------------------------------

                $isPending =
                    ($normalizedStatus === 'pending');

                $isApproved =
                    ($normalizedStatus === 'approved');

                $isReturned =
                    ($normalizedStatus === 'returned');

                $isRejected =
                    ($normalizedStatus === 'rejected');

                $isUnderReview =
                    ($normalizedStatus === 'under_review');


                // -------------------------------------------------
                // Normalize Handover Status
                // -------------------------------------------------

                $rawHandoverStatus = trim(
                    (string)($claim['handover_status'] ?? '')
                );

                $handoverStatus =
                    strtolower($rawHandoverStatus);

                $handoverSubmitted =
                    ($handoverStatus === 'proof submitted');

                $handoverVerified =
                    ($handoverStatus === 'verified');

                $handoverRejected =
                    ($handoverStatus === 'rejected');


                // -------------------------------------------------
                // Highlight Selected Claim
                // -------------------------------------------------

                $isHighlighted =
                    ($highlightId === (int)$claim['claim_id']);


                // -------------------------------------------------
                // Claim Status Badge
                // -------------------------------------------------

                switch ($normalizedStatus) {

                    case 'approved':

                        $badgeClass =
                            'bg-success text-white';

                        $statusText =
                            'Approved';

                        break;


                    case 'returned':

                        $badgeClass =
                            'bg-primary text-white';

                        $statusText =
                            'Returned';

                        break;


                    case 'rejected':

                        $badgeClass =
                            'bg-danger text-white';

                        $statusText =
                            'Rejected';

                        break;


                    case 'under_review':

                        $badgeClass =
                            'bg-warning text-dark';

                        $statusText =
                            'Under Review';

                        break;


                    case 'pending':

                        $badgeClass =
                            'bg-warning text-dark';

                        $statusText =
                            'Pending Verification';

                        break;


                    default:

                        $badgeClass =
                            'bg-secondary text-white';

                        if ($rawStatus === '') {

                            $statusText =
                                'Pending Verification';

                        } else {

                            $statusText =
                                ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $rawStatus
                                    )
                                );
                        }

                        break;
                }


                // -------------------------------------------------
                // ORIGINAL LOST DATE
                // -------------------------------------------------

                $originalLostDate = null;

                if (!empty($claim['linked_lost_date'])) {

                    $originalLostDate =
                        strtotime(
                            $claim['linked_lost_date']
                        );
                }


                // -------------------------------------------------
                // ORIGINAL FOUND DATE
                // -------------------------------------------------

                $originalFoundDate = null;

                if (!empty($claim['date_found'])) {

                    $originalFoundDate =
                        strtotime(
                            $claim['date_found']
                        );
                }

 
                // -------------------------------------------------
                // DATE CONSISTENCY CHECK
                // -------------------------------------------------

                $dateConsistency =
                    'not_available';

                if (
                    $originalLostDate !== null &&
                    $originalFoundDate !== null
                ) {

                    if (
                        $originalLostDate >
                        $originalFoundDate
                    ) {

                        $dateConsistency =
                            'conflict';

                    } else {

                        $dateConsistency =
                            'valid';
                    }
                }


                // -------------------------------------------------
                // Proof Attachment
                // -------------------------------------------------

                $hasProofAttachment =
                    !empty($claim['proof_image']);


                // -------------------------------------------------
                // Five-Day Proof Deadline
                // -------------------------------------------------

                $proofDeadline =
                    !empty($claim['proof_deadline'])
                        ? strtotime(
                            $claim['proof_deadline']
                        )
                        : null;


                /*
                 * Approval rules:
                 *
                 * 1. Proof image/document exists
                 *    -> approve immediately.
                 *
                 * 2. No proof attachment and
                 *    5-day period is active
                 *    -> approval locked.
                 *
                 * 3. No proof attachment and
                 *    5-day period expired
                 *    -> approve.
                 */

                $claimEligibleForApproval =
                    $hasProofAttachment ||
                    (
                        $proofDeadline !== null &&
                        $proofDeadline <= time()
                    );

                ?>


                <!-- =================================================
                     CLAIM CARD
                ================================================== -->

                <div class="col-lg-6">

                    <div
                        class="card shadow-sm border-0 h-100
                        <?= $isHighlighted
                            ? 'border-primary border-2 ring-2'
                            : ''
                        ?>
                        p-4"
                    >


                        <!-- =================================================
                             CLAIM HEADER
                        ================================================== -->

                        <div class="d-flex justify-content-between align-items-start mb-3">

                            <div>

                                <span class="badge bg-light text-secondary border fs-xxs mb-1">

                                    Claim ID
                                    #<?= (int)$claim['claim_id'] ?>

                                </span>


                                <h5 class="fw-bold text-dark mb-0">

                                    <?= htmlspecialchars(
                                        $claim['found_name']
                                    ) ?>

                                </h5>


                                <small class="text-muted fs-xs">

                                    Logged on

                                    <?= !empty($claim['claim_date'])
                                        ? date(
                                            'd M Y',
                                            strtotime(
                                                $claim['claim_date']
                                            )
                                        )
                                        : 'N/A'
                                    ?>

                                </small>

                            </div>


                            <!-- Claim Status -->

                            <span
                                class="badge <?= htmlspecialchars($badgeClass) ?> fs-xs px-3 py-1"
                                style="
                                    display:inline-block;
                                    min-width:110px;
                                    text-align:center;
                                    font-weight:600;
                                "
                            >
                                <?= htmlspecialchars(
                                    $statusText
                                ) ?>
                            </span>

                        </div>



                        <!-- =================================================
                             RETURNED NOTICE
                        ================================================== -->

                        <?php if ($isReturned): ?>

                            <div class="alert alert-primary py-2 px-3 fs-xs">

                                <i class="fa-solid fa-circle-check me-1"></i>

                                <strong>
                                    Item Returned Successfully
                                </strong>

                                <br>

                                The physical handover has been verified
                                by the administrator.

                            </div>

                        <?php endif; ?>



                        <!-- =================================================
                             CLAIMANT DETAILS
                        ================================================== -->

                        <div class="p-3 bg-light rounded-3 mb-3 border">

                            <h6 class="fs-xs fw-bold text-uppercase text-muted mb-2">

                                <i class="fa-solid fa-user text-primary me-1"></i>

                                Claimant Details

                            </h6>


                            <div class="row g-2 fs-xs">

                                <div class="col-sm-6">

                                    <span class="text-muted">
                                        Name:
                                    </span>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $claim['claimant_name']
                                        ) ?>
                                    </strong>

                                </div>


                                <div class="col-sm-6">

                                    <span class="text-muted">
                                        Role:
                                    </span>

                                    <span class="badge bg-secondary-subtle text-secondary border fs-xxs">

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $claim['claimant_role']
                                            )
                                        ) ?>

                                    </span>

                                </div>


                                <div class="col-sm-6">

                                    <span class="text-muted">
                                        Email:
                                    </span>

                                    <?= htmlspecialchars(
                                        $claim['claimant_email']
                                    ) ?>

                                </div>


                                <div class="col-sm-6">

                                    <span class="text-muted">
                                        Phone:
                                    </span>

                                    <?= htmlspecialchars(
                                        $claim['claimant_phone']
                                    ) ?>

                                </div>


                                <?php if (!empty($claim['claimant_dept'])): ?>

                                    <div class="col-12">

                                        <span class="text-muted">
                                            Department:
                                        </span>

                                        <?= htmlspecialchars(
                                            $claim['claimant_dept']
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                        <!-- =================================================
     FOUNDER DETAILS
================================================== -->

<div class="p-3 bg-light rounded-3 mb-3 border">

    <h6 class="fs-xs fw-bold text-uppercase text-muted mb-2">

        <i class="fa-solid fa-user-shield text-success me-1"></i>

        Founder Details

    </h6>

    <div class="row g-2 fs-xs">

        <div class="col-sm-6">

            <span class="text-muted">
                Name:
            </span>

            <strong>
                <?= htmlspecialchars(
                    $claim['founder_name'] ?? 'Not Available'
                ) ?>
            </strong>

        </div>

        <div class="col-sm-6">

            <span class="text-muted">
                Role:
            </span>

            <?php if (!empty($claim['founder_role'])): ?>

                <span class="badge bg-success-subtle text-success border fs-xxs">

                    <?= htmlspecialchars(
                        ucfirst($claim['founder_role'])
                    ) ?>

                </span>

            <?php else: ?>

                <span class="text-muted">
                    Not Available
                </span>

            <?php endif; ?>

        </div>

        <div class="col-sm-6">

            <span class="text-muted">
                Email:
            </span>

            <?= !empty($claim['founder_email'])
                ? htmlspecialchars($claim['founder_email'])
                : '<span class="text-muted">Not Available</span>' ?>

        </div>

        <div class="col-sm-6">

            <span class="text-muted">
                Phone:
            </span>

            <?= !empty($claim['founder_phone'])
                ? htmlspecialchars($claim['founder_phone'])
                : '<span class="text-muted">Not Available</span>' ?>

        </div>

        <?php if (!empty($claim['founder_dept'])): ?>

            <div class="col-12">

                <span class="text-muted">
                    Department:
                </span>

                <?= htmlspecialchars(
                    $claim['founder_dept']
                ) ?>

            </div>

        <?php endif; ?>

    </div>

</div>

                        <!-- =================================================
                             ORIGINAL ITEM INFORMATION
                        ================================================== -->

                        <div class="mb-3">

                            <h6 class="fs-xs fw-bold text-uppercase text-dark mb-2">

                                <i class="fa-solid fa-box-open text-primary me-1"></i>

                                Original Item Information

                            </h6>


                            <div class="p-3 bg-light rounded border">

                                <!-- Item Names -->

                                <div class="row g-2 fs-xs">

                                    <div class="col-sm-6">

                                        <span class="text-muted">
                                            Lost Report:
                                        </span>

                                        <strong>

                                            <?= !empty(
                                                $claim['linked_lost_name']
                                            )
                                                ? htmlspecialchars(
                                                    $claim['linked_lost_name']
                                                )
                                                : 'Not Linked'
                                            ?>

                                        </strong>

                                    </div>


                                    <div class="col-sm-6">

                                        <span class="text-muted">
                                            Found Report:
                                        </span>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $claim['found_name']
                                            ) ?>

                                        </strong>

                                    </div>

                                </div>


                                <!-- Dates -->

                                <div class="row g-3 mt-1">

                                    <!-- ORIGINAL LOST DATE -->
<!-- 
                                    <div class="col-md-6">

                                        <div class="p-3 bg-primary-subtle border border-primary rounded">

                                            <div class="fs-xxs fw-bold text-primary mb-1">

                                                <i class="fa-solid fa-calendar-minus me-1"></i>

                                                Original Lost Date

                                            </div>


                                            <?php if (
                                                $originalLostDate !== null
                                            ): ?>

                                                <div class="fs-sm fw-semibold text-dark">

                                                    <?= date(
                                                        'd M Y',
                                                        $originalLostDate
                                                    ) ?>

                                                </div>

                                            <?php else: ?>

                                                <div class="fs-sm text-muted">

                                                    Not Available

                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    </div> -->
                                    <div class="col-md-6">

    <div class="p-3 bg-warning-subtle border border-warning rounded">

        <div class="fs-xxs fw-bold text-warning-emphasis mb-1">

            <i class="fa-solid fa-calendar-plus me-1"></i>

            Claimant's Lost Date

        </div>

        <?php
        $claimantLostDate = !empty($claim['lost_date'])
            ? strtotime($claim['lost_date'])
            : null;
        ?>

        <?php if ($claimantLostDate !== null): ?>

            <div class="fs-sm fw-semibold text-dark">

                <?= date('d M Y', $claimantLostDate) ?>

            </div>

        <?php else: ?>

            <div class="fs-sm text-muted">

                Not Available

            </div>

        <?php endif; ?>

    </div>

</div>


                                    <!-- ORIGINAL FOUND DATE -->

                                    <div class="col-md-6">

                                        <div class="p-3 bg-success-subtle border border-success rounded">

                                            <div class="fs-xxs fw-bold text-success mb-1">

                                                <i class="fa-solid fa-calendar-plus me-1"></i>

                                                Original Found Date

                                            </div>


                                            <?php if (
                                                $originalFoundDate !== null
                                            ): ?>

                                                <div class="fs-sm fw-semibold text-dark">

                                                    <?= date(
                                                        'd M Y',
                                                        $originalFoundDate
                                                    ) ?>

                                                </div>

                                            <?php else: ?>

                                                <div class="fs-sm text-muted">

                                                    Not Available

                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                </div>
                               


                                <!-- Locations -->

                                <div class="row g-2 mt-2 fs-xs">

                                    <?php if (
                                        !empty(
                                            $claim['linked_lost_location']
                                        )
                                    ): ?>

                                        <div class="col-md-6">

                                            <span class="text-muted">
                                                Lost Location:
                                            </span>

                                            <?= htmlspecialchars(
                                                $claim[
                                                    'linked_lost_location'
                                                ]
                                            ) ?>

                                        </div>

                                    <?php endif; ?>


                                    <?php if (
                                        !empty(
                                            $claim['location_found']
                                        )
                                    ): ?>

                                        <div class="col-md-6">

                                            <span class="text-muted">
                                                Found Location:
                                            </span>

                                            <?= htmlspecialchars(
                                                $claim[
                                                    'location_found'
                                                ]
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                </div>


                                <!-- Date Consistency -->

                                <?php if (
                                    $dateConsistency === 'valid'
                                ): ?>

                                    <div class="mt-3">

                                        <span class="badge bg-success fs-xxs">

                                            <i class="fa-solid fa-circle-check me-1"></i>

                                            Date Consistent

                                        </span>

                                        <span class="text-muted fs-xxs ms-2">

                                            The lost date is on or before
                                            the found date.

                                        </span>

                                    </div>


                                <?php elseif (
                                    $dateConsistency === 'conflict'
                                ): ?>

                                    <div class="mt-3">

                                        <span class="badge bg-danger fs-xxs">

                                            <i class="fa-solid fa-triangle-exclamation me-1"></i>

                                            Date Conflict

                                        </span>


                                        <div class="mt-2 text-danger fs-xxs">

                                            <i class="fa-solid fa-circle-exclamation me-1"></i>

                                            The original lost date is later
                                            than the original found date.
                                            Review this claim carefully.

                                        </div>

                                    </div>


                                <?php else: ?>

                                    <div class="mt-3">

                                        <span class="badge bg-secondary fs-xxs">

                                            <i class="fa-solid fa-circle-question me-1"></i>

                                            Date Not Available

                                        </span>

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>



                        <!-- =================================================
                             STAGE 1 - OWNERSHIP EVIDENCE
                        ================================================== -->

                        <div class="mb-3">

                            <h6 class="fs-xs fw-bold text-uppercase text-dark mb-1">

                                <i class="fa-solid fa-clipboard-check text-success me-1"></i>

                                Stage 1: Submitted Ownership Evidence

                            </h6>


                            <!-- Written Ownership Proof -->

                            <div class="p-3 bg-white rounded border fs-xs text-secondary lh-base">

                                <?= nl2br(
                                    htmlspecialchars(
                                        $claim['proof'] ?? ''
                                    )
                                ) ?>

                            </div>


                            <!-- Stage 1 Answers -->

                            <?php if (
                                !empty(
                                    $claim['stage1_answers']
                                )
                            ): ?>

                                <div class="mt-2 p-2 bg-light rounded border fs-xxs text-muted">

                                    <strong>
                                        Additional Q&A / Notes:
                                    </strong>

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $claim[
                                                'stage1_answers'
                                            ]
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <!-- Proof Image -->

                            <?php if (
                                !empty(
                                    $claim['proof_image']
                                )
                            ): ?>

                                <div class="mt-2">

                                    <a
                                        href="<?= htmlspecialchars(
                                            url(
                                                'uploads/' .
                                                basename(
                                                    $claim[
                                                        'proof_image'
                                                    ]
                                                )
                                            )
                                        ) ?>"
                                        target="_blank"
                                        class="btn btn-outline-primary btn-xs py-1 px-2 fs-xxs"
                                    >

                                        <i class="fa-solid fa-paperclip me-1"></i>

                                        View Attached Proof

                                    </a>

                                </div>

                            <?php else: ?>

                                <div class="mt-2">

                                    <span class="badge bg-warning-subtle text-warning-emphasis border fs-xxs">

                                        <i class="fa-solid fa-clock me-1"></i>

                                        No Proof Attachment

                                    </span>

                                </div>

                            <?php endif; ?>


                            <!-- Found Item Image -->

                            <?php if (
                                !empty(
                                    $claim['found_image']
                                )
                            ): ?>

                                <div class="mt-2">

                                    <a
                                        href="<?= url(
                                            $claim[
                                                'found_image'
                                            ]
                                        ) ?>"
                                        target="_blank"
                                        class="btn btn-outline-success btn-xs py-1 px-2 fs-xxs"
                                    >

                                        <i class="fa-solid fa-image me-1"></i>

                                        View Found Item Image

                                    </a>

                                </div>

                            <?php endif; ?>

                        </div>




                        <!-- =================================================
                             STAGE 2 - OWNERSHIP VERIFICATION
                        ================================================== -->

                        <?php if ($isPending): ?>

                            <div class="mt-auto pt-3 border-top">

                                <form
                                    action="<?= url(
                                        'actions/claim_action.php'
                                    ) ?>"
                                    method="POST"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="verify_claim"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= csrfToken() ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="claim_id"
                                        value="<?= (int)$claim['claim_id'] ?>"
                                    >


                                    <!-- ADMIN REMARK -->

                                    <div class="mb-3">

                                        <label
                                            for="admin_remark_<?= (int)$claim['claim_id'] ?>"
                                            class="form-label fs-xs fw-bold text-dark"
                                        >

                                            Stage 2 Administrative Review
                                            Remarks / Collection Instructions:

                                        </label>


                                        <textarea
                                            class="form-control fs-xs"
                                            id="admin_remark_<?= (int)$claim['claim_id'] ?>"
                                            name="admin_remark"
                                            rows="2"
                                            placeholder="e.g. Identity verified via Student ID card."
                                            required
                                        ></textarea>

                                    </div>



                                    <!-- APPROVE / REJECT -->

                                    <div class="d-flex gap-2">


                                        <?php if (
                                            $claimEligibleForApproval
                                        ): ?>

                                            <button
                                                type="submit"
                                                name="decision"
                                                value="approve"
                                                class="btn btn-success btn-sm w-100 fw-semibold"
                                                onclick="return confirm('Confirm approval of this ownership claim? The claim will become Approved and the claimant/finder will be able to arrange the handover.');"
                                            >

                                                <i class="fa-solid fa-circle-check me-1"></i>

                                                Approve Claim

                                            </button>

                                        <?php else: ?>

                                            <button
                                                type="button"
                                                class="btn btn-secondary btn-sm w-100 fw-semibold"
                                                disabled
                                            >

                                                <i class="fa-solid fa-clock me-1"></i>

                                                Approval Locked — 5-Day Period Active

                                            </button>

                                        <?php endif; ?>


                                        <button
                                            type="submit"
                                            name="decision"
                                            value="reject"
                                            class="btn btn-outline-danger btn-sm w-100 fw-semibold"
                                            onclick="return confirm('Confirm rejection of this claim? The claimant will be notified with your remarks.');"
                                        >

                                            <i class="fa-solid fa-circle-xmark me-1"></i>

                                            Reject Claim

                                        </button>

                                    </div>

                                </form>

                            </div>



                        <?php elseif ($isApproved): ?>


                            <!-- =================================================
                                 APPROVED CLAIM
                            ================================================== -->

                            <div class="p-3 rounded border mb-3 fs-xs bg-success-subtle border-success">

                                <div class="fw-bold mb-1">

                                    <i class="fa-solid fa-circle-check text-success me-1"></i>

                                    Ownership Verified & Claim Approved

                                </div>


                                <div>

                                    <strong>
                                        Verifier:
                                    </strong>

                                    <?= htmlspecialchars(
                                        $claim['verifier_name']
                                        ?? 'Admin'
                                    ) ?>


                                    <?php if (
                                        !empty(
                                            $claim[
                                                'verification_date'
                                            ]
                                        )
                                    ): ?>

                                        (
                                        <?= date(
                                            'M d, Y H:i',
                                            strtotime(
                                                $claim[
                                                    'verification_date'
                                                ]
                                            )
                                        ) ?>
                                        )

                                    <?php endif; ?>

                                </div>


                                <div class="mt-1">

                                    <strong>
                                        Admin Remarks:
                                    </strong>

                                    <?= htmlspecialchars(
                                        $claim[
                                            'admin_remark'
                                        ]
                                        ?? ''
                                    ) ?>

                                </div>


                                <hr>


                                <div class="text-success">

                                    <i class="fa-solid fa-comments me-1"></i>

                                    <strong>
                                        Handover Stage Active
                                    </strong>

                                </div>


                                <div class="mt-1 text-muted">

                                    The claimant and finder can now communicate
                                    privately to arrange the date, time and
                                    location of the item handover.

                                </div>

                            </div>



                            <!-- =================================================
                                 HANDOVER STATUS
                            ================================================== -->

                            <?php if (
                                ($claim['handover_status']
                                    ?? 'Not Started')
                                === 'Not Started'
                            ): ?>

                                <div class="alert alert-secondary py-2 px-3 fs-xs">

                                    <i class="fa-solid fa-clock me-1"></i>

                                    <strong>
                                        Handover Not Completed
                                    </strong>

                                    <br>

                                    Waiting for the finder to complete the
                                    physical handover and upload proof.

                                </div>


                            <?php elseif ($handoverSubmitted): ?>


                                <!-- =================================================
                                     HANDOVER PROOF SUBMITTED
                                ================================================== -->

                                <div class="card border-warning mb-3">

                                    <div class="card-header bg-warning py-2">

                                        <strong class="fs-sm">

                                            <i class="fa-solid fa-handshake me-1"></i>

                                            Handover Verification Required

                                        </strong>

                                    </div>


                                    <div class="card-body">


                                        <div class="alert alert-warning py-2 px-3 fs-xs">

                                            <i class="fa-solid fa-triangle-exclamation me-1"></i>

                                            The finder has uploaded handover
                                            proof. Please verify the image
                                            before marking the item as returned.

                                        </div>



                                        <!-- Handover Image -->

                                        <?php if (
                                            !empty(
                                                $claim[
                                                    'handover_image'
                                                ]
                                            )
                                        ): ?>

                                            <div class="mb-3">

                                                <a
                                                    href="<?= url(
                                                        $claim[
                                                            'handover_image'
                                                        ]
                                                    ) ?>"
                                                    target="_blank"
                                                    class="btn btn-outline-primary btn-sm"
                                                >

                                                    <i class="fa-solid fa-image me-1"></i>

                                                    View Handover Image

                                                </a>

                                            </div>

                                        <?php endif; ?>



                                        <!-- Uploaded Date -->

                                        <?php if (
                                            !empty(
                                                $claim[
                                                    'handover_uploaded_at'
                                                ]
                                            )
                                        ): ?>

                                            <div class="text-muted fs-xs mb-3">

                                                <i class="fa-regular fa-calendar me-1"></i>

                                                Submitted on

                                                <strong>

                                                    <?= date(
                                                        'M d, Y h:i A',
                                                        strtotime(
                                                            $claim[
                                                                'handover_uploaded_at'
                                                            ]
                                                        )
                                                    ) ?>

                                                </strong>

                                            </div>

                                        <?php endif; ?>



                                        <!-- Admin Actions -->

                                        <div class="d-flex gap-2">


                                            <!-- VERIFY -->

                                            <form
                                                action="<?= url(
                                                    'actions/admin_actions.php'
                                                ) ?>"
                                                method="POST"
                                                class="flex-grow-1"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="verify_handover"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= csrfToken() ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="claim_id"
                                                    value="<?= (int)$claim['claim_id'] ?>"
                                                >


                                                <button
                                                    type="submit"
                                                    class="btn btn-success btn-sm w-100 fw-semibold"
                                                    onclick="return confirm('Verify this handover? This will mark the claim as Returned.');"
                                                >

                                                    <i class="fa-solid fa-circle-check me-1"></i>

                                                    Verify Handover

                                                </button>

                                            </form>



                                            <!-- REJECT -->

                                            <button
                                                type="button"
                                                class="btn btn-outline-danger btn-sm flex-grow-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#rejectHandoverModal<?= (int)$claim['claim_id'] ?>"
                                            >

                                                <i class="fa-solid fa-circle-xmark me-1"></i>

                                                Reject Handover

                                            </button>

                                        </div>

                                    </div>

                                </div>



                                <!-- =================================================
                                     REJECT HANDOVER MODAL
                                ================================================== -->

                                <div
                                    class="modal fade"
                                    id="rejectHandoverModal<?= (int)$claim['claim_id'] ?>"
                                    tabindex="-1"
                                    aria-hidden="true"
                                >

                                    <div class="modal-dialog">

                                        <div class="modal-content">


                                            <div class="modal-header">

                                                <h5 class="modal-title">

                                                    Reject Handover Proof

                                                </h5>

                                                <button
                                                    type="button"
                                                    class="btn-close"
                                                    data-bs-dismiss="modal"
                                                ></button>

                                            </div>


                                            <form
                                                action="<?= url(
                                                    'actions/admin_actions.php'
                                                ) ?>"
                                                method="POST"
                                            >

                                                <div class="modal-body">

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="reject_handover"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= csrfToken() ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="claim_id"
                                                        value="<?= (int)$claim['claim_id'] ?>"
                                                    >


                                                    <label
                                                        class="form-label fs-sm fw-semibold"
                                                    >

                                                        Reason for rejection

                                                    </label>


                                                    <textarea
                                                        name="handover_remark"
                                                        class="form-control"
                                                        rows="4"
                                                        placeholder="Explain why the handover proof was rejected..."
                                                        required
                                                    ></textarea>

                                                </div>


                                                <div class="modal-footer">

                                                    <button
                                                        type="button"
                                                        class="btn btn-secondary btn-sm"
                                                        data-bs-dismiss="modal"
                                                    >

                                                        Cancel

                                                    </button>


                                                    <button
                                                        type="submit"
                                                        class="btn btn-danger btn-sm"
                                                    >

                                                        <i class="fa-solid fa-xmark me-1"></i>

                                                        Reject Handover

                                                    </button>

                                                </div>

                                            </form>

                                        </div>

                                    </div>

                                </div>



                            <?php endif; ?>



                        <?php elseif ($isReturned): ?>


                            <!-- =================================================
                                 RETURNED / FINAL STATUS
                            ================================================== -->

                            <div class="p-3 rounded border mb-3 fs-xs bg-primary-subtle border-primary">

                                <div class="fw-bold mb-1 text-primary">

                                    <i class="fa-solid fa-circle-check me-1"></i>

                                    Handover Verified — Item Returned

                                </div>


                                <?php if (
                                    !empty(
                                        $claim[
                                            'handover_verified_at'
                                        ]
                                    )
                                ): ?>

                                    <div>

                                        <strong>
                                            Handover Verified:
                                        </strong>

                                        <?= date(
                                            'M d, Y h:i A',
                                            strtotime(
                                                $claim[
                                                    'handover_verified_at'
                                                ]
                                            )
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <div>

                                    <strong>
                                        Verified By:
                                    </strong>

                                    <?= htmlspecialchars(
                                        $claim[
                                            'handover_verifier_name'
                                        ]
                                        ?? 'Admin'
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $claim[
                                            'handover_remark'
                                        ]
                                    )
                                ): ?>

                                    <div class="mt-1">

                                        <strong>
                                            Handover Remark:
                                        </strong>

                                        <?= htmlspecialchars(
                                            $claim[
                                                'handover_remark'
                                            ]
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </div>



                            <!-- Final Handover Image -->

                            <?php if (
                                !empty(
                                    $claim[
                                        'handover_image'
                                    ]
                                )
                            ): ?>

                                <div class="mb-3">

                                    <a
                                        href="<?= url(
                                            $claim[
                                                'handover_image'
                                            ]
                                        ) ?>"
                                        target="_blank"
                                        class="btn btn-outline-primary btn-sm"
                                    >

                                        <i class="fa-solid fa-image me-1"></i>

                                        View Verified Handover Image

                                    </a>

                                </div>

                            <?php endif; ?>



                        <?php elseif ($isRejected): ?>


                            <!-- =================================================
                                 REJECTED CLAIM
                            ================================================== -->

                            <div class="p-3 rounded border mb-3 fs-xs bg-danger-subtle border-danger">

                                <div class="fw-bold mb-1 text-danger">

                                    <i class="fa-solid fa-circle-xmark me-1"></i>

                                    Claim Rejected

                                </div>


                                <div>

                                    <strong>
                                        Verifier:
                                    </strong>

                                    <?= htmlspecialchars(
                                        $claim[
                                            'verifier_name'
                                        ]
                                        ?? 'Admin'
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $claim[
                                            'verification_date'
                                        ]
                                    )
                                ): ?>

                                    <div>

                                        <strong>
                                            Date:
                                        </strong>

                                        <?= date(
                                            'M d, Y H:i',
                                            strtotime(
                                                $claim[
                                                    'verification_date'
                                                ]
                                            )
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $claim[
                                            'admin_remark'
                                        ]
                                    )
                                ): ?>

                                    <div class="mt-1">

                                        <strong>
                                            Admin Remarks:
                                        </strong>

                                        <?= htmlspecialchars(
                                            $claim[
                                                'admin_remark'
                                            ]
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>



                        <!-- =================================================
                             HANDOVER STATUS SUMMARY
                        ================================================== -->

                        <?php if (
                            $isApproved ||
                            $isReturned
                        ): ?>

                            <div class="border-top pt-3 mt-auto">

                                <div class="d-flex justify-content-between align-items-center">

                                    <span class="fs-xs fw-semibold text-muted">

                                        Handover Status

                                    </span>


                                    <?php

                                    $handoverStatus =
                                        $claim[
                                            'handover_status'
                                        ]
                                        ?? 'Not Started';

                                    if (
                                        $handoverStatus ===
                                        'Verified'
                                    ):

                                    ?>

                                        <span class="badge bg-success fs-xxs">

                                            <i class="fa-solid fa-check me-1"></i>

                                            Verified

                                        </span>


                                    <?php elseif (
                                        $handoverStatus ===
                                        'Proof Submitted'
                                    ):

                                    ?>

                                        <span class="badge bg-warning text-dark fs-xxs">

                                            <i class="fa-solid fa-clock me-1"></i>

                                            Proof Submitted

                                        </span>


                                    <?php elseif (
                                        $handoverStatus ===
                                        'Rejected'
                                    ):

                                    ?>

                                        <span class="badge bg-danger fs-xxs">

                                            <i class="fa-solid fa-xmark me-1"></i>

                                            Rejected

                                        </span>


                                    <?php else: ?>

                                        <span class="badge bg-secondary fs-xxs">

                                            Not Started

                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endif; ?>


                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>