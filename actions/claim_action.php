<?php
/**
 * LOFO - Claim Action Handler
 *
 * CLAIM WORKFLOW
 * ---------------------------------------------------------
 * 1. Claimant provides:
 *      - Lost date
 *      - Written ownership proof
 *      - Optional security answers
 *      - Optional proof image/document
 *
 * 2. Actual found date is NEVER shown to claimant.
 *      - It is retrieved from the database.
 *      - It is compared internally with the claimed lost date.
 *
 * 3. Date verification:
 *      - Lost date <= Found date  -> logically possible
 *      - Lost date > Found date   -> impossible claim date
 *
 * 4. If claimant links an existing lost report:
 *      - Lost report must belong to claimant.
 *      - Entered lost date must match their lost report date.
 *
 * 5. Written ownership proof is required.
 *
 * 6. Proof image/document is optional.
 *
 * 7. Proof attachment present:
 *      - Admin can approve immediately.
 *
 * 8. No proof attachment:
 *      - 5-day competing claim period.
 *      - All users receive a claim alert.
 *      - Admin cannot approve before 5 days.
 *
 * 9. After admin approval:
 *      - Claim becomes Approved.
 *      - Claimant and finder can use private chat.
 *
 * 10. Finder uploads handover proof.
 *
 * 11. Admin verifies handover.
 *
 * 12. Only after successful handover verification:
 *      - Claim becomes Returned.
 *      - Found item becomes Returned.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/notifications.php';

$pdo = getDBConnection();


// =========================================================
// BASIC SECURITY
// =========================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('index.php'));
    exit;
}

requireLogin();


// =========================================================
// CURRENT USER
// =========================================================

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {

    $_SESSION['error'] =
        'Please login to continue.';

    header(
        'Location: ' .
        url('login.php')
    );

    exit;
}


// =========================================================
// ACTION
// =========================================================

$action = $_POST['action'] ?? '';


// =========================================================
// CSRF VALIDATION
// =========================================================

if (
    !isset($_POST['csrf_token']) ||
    !verifyCsrfToken($_POST['csrf_token'])
) {

    $_SESSION['error'] =
        'Invalid security token. Please try again.';

    /*
     * For claim submission, return to item page.
     * For admin actions, return to admin claims.
     */
    if ($action === 'submit_claim') {

        $csrfFoundId =
            (int)($_POST['found_id'] ?? 0);

        if ($csrfFoundId > 0) {

            header(
                'Location: ' .
                url(
                    'item-details.php?type=found&id=' .
                    $csrfFoundId
                )
            );

        } else {

            header(
                'Location: ' .
                url('index.php')
            );
        }

    } else {

        header(
            'Location: ' .
            url('admin/claims.php')
        );
    }

    exit;
}


// =========================================================
// SUBMIT CLAIM
// =========================================================

if ($action === 'submit_claim') {

    // -----------------------------------------------------
    // BASIC INPUT
    // -----------------------------------------------------

    $foundId =
        (int)($_POST['found_id'] ?? 0);

    $lostId =
        !empty($_POST['lost_id'])
            ? (int)$_POST['lost_id']
            : null;

    $lostDate =
        trim(
            $_POST['lost_date'] ?? ''
        );

    $proof =
        trim(
            $_POST['proof'] ?? ''
        );

    $stage1Answers =
        trim(
            $_POST['stage1_answers'] ?? ''
        );


    // -----------------------------------------------------
    // VALIDATE FOUND ID
    // -----------------------------------------------------

    if ($foundId <= 0) {

        $_SESSION['error'] =
            'Invalid found item.';

        header(
            'Location: ' .
            url('index.php')
        );

        exit;
    }


    // -----------------------------------------------------
    // LOST DATE REQUIRED
    // -----------------------------------------------------

    if ($lostDate === '') {

        $_SESSION['error'] =
            'Please provide the date on which you lost the item.';

        header(
            'Location: ' .
            url(
                'item-details.php?type=found&id=' .
                $foundId .
                '#claim'
            )
        );

        exit;
    }


    // -----------------------------------------------------
    // VALIDATE LOST DATE FORMAT
    // -----------------------------------------------------

    $lostDateObject =
        DateTime::createFromFormat(
            'Y-m-d',
            $lostDate
        );

    $lostDateErrors =
        DateTime::getLastErrors();

    /*
     * DateTime::getLastErrors() can return false
     * when there are no errors in some PHP versions.
     */
    $hasDateErrors =
        is_array($lostDateErrors)
        && (
            $lostDateErrors['warning_count'] > 0
            ||
            $lostDateErrors['error_count'] > 0
        );

    if (
        !$lostDateObject
        ||
        $hasDateErrors
        ||
        $lostDateObject->format('Y-m-d') !== $lostDate
    ) {

        $_SESSION['error'] =
            'Please enter a valid lost date.';

        header(
            'Location: ' .
            url(
                'item-details.php?type=found&id=' .
                $foundId .
                '#claim'
            )
        );

        exit;
    }


    // -----------------------------------------------------
    // LOST DATE CANNOT BE IN THE FUTURE
    // -----------------------------------------------------

    $today =
        date('Y-m-d');

    if ($lostDate > $today) {

        $_SESSION['error'] =
            'The lost date cannot be in the future.';

        header(
            'Location: ' .
            url(
                'item-details.php?type=found&id=' .
                $foundId .
                '#claim'
            )
        );

        exit;
    }


    // -----------------------------------------------------
    // WRITTEN PROOF REQUIRED
    // -----------------------------------------------------

    if ($proof === '') {

        $_SESSION['error'] =
            'Please provide details proving your ownership of the item.';

        header(
            'Location: ' .
            url(
                'item-details.php?type=found&id=' .
                $foundId .
                '#claim'
            )
        );

        exit;
    }


    try {

        $pdo->beginTransaction();


        // =================================================
        // GET FOUND ITEM
        // =================================================
        //
        // IMPORTANT:
        // date_found is retrieved only on the server.
        //
        // It is NOT sent to the claimant.
        //
        // =================================================

        $stmt =
            $pdo->prepare("
                SELECT
                    found_id,
                    user_id,
                    item_name,
                    date_found,
                    status
                FROM found_items
                WHERE found_id = ?
                LIMIT 1
            ");

        $stmt->execute([
            $foundId
        ]);

        $foundItem =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$foundItem) {

            throw new Exception(
                'Found item not found.'
            );
        }


        // =================================================
        // ITEM MUST BE AVAILABLE
        // =================================================

        if (
            $foundItem['status'] !==
            'Available'
        ) {

            throw new Exception(
                'This item is no longer available for claiming.'
            );
        }


        // =================================================
        // FINDER CANNOT CLAIM OWN ITEM
        // =================================================

        if (
            (int)$foundItem['user_id'] ===
            (int)$userId
        ) {

            throw new Exception(
                'You cannot claim an item that you reported as found.'
            );
        }


        // =================================================
        // INTERNAL DATE COMPARISON
        // =================================================
        //
        // Claimant does NOT see the found date.
        //
        // If claimant says:
        //
        // Lost Date > Found Date
        //
        // the claim date is impossible.
        //
        // =================================================

        $foundDate =
            $foundItem['date_found'];


        if (empty($foundDate)) {

            throw new Exception(
                'The found date is not available for verification.'
            );
        }


        if ($lostDate > $foundDate) {

            throw new Exception(
                'The lost date provided cannot be later than the date the item was reported found.'
            );
        }


        // =================================================
        // OPTIONAL LINKED LOST REPORT
        // =================================================
        //
        // If claimant links an existing lost report:
        //
        // 1. It MUST belong to the logged-in user.
        // 2. Its date_lost MUST match the entered lost date.
        //
        // This makes the date verification stronger.
        //
        // =================================================

        if ($lostId !== null) {

            $lostStmt =
                $pdo->prepare("
                    SELECT
                        lost_id,
                        user_id,
                        item_name,
                        date_lost,
                        status
                    FROM lost_items
                    WHERE lost_id = ?
                    LIMIT 1
                ");

            $lostStmt->execute([
                $lostId
            ]);

            $linkedLostItem =
                $lostStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$linkedLostItem) {

                throw new Exception(
                    'The selected lost item report was not found.'
                );
            }


            // -------------------------------------------------
            // LOST REPORT MUST BELONG TO CURRENT USER
            // -------------------------------------------------

            if (
                (int)$linkedLostItem['user_id'] !==
                (int)$userId
            ) {

                throw new Exception(
                    'You can only link your own lost item report.'
                );
            }


            // -------------------------------------------------
            // LOST REPORT DATE MUST MATCH CLAIMED DATE
            // -------------------------------------------------

            if (
                $linkedLostItem['date_lost'] !==
                $lostDate
            ) {

                throw new Exception(
                    'The lost date does not match the date in your selected lost item report.'
                );
            }
        }


        // =================================================
        // CHECK DUPLICATE CLAIM
        // =================================================

        $duplicateStmt =
            $pdo->prepare("
                SELECT
                    claim_id,
                    status
                FROM claims
                WHERE found_id = ?
                  AND user_id = ?
                  AND status IN (
                      'Pending',
                      'Under Review',
                      'Approved'
                  )
                LIMIT 1
            ");

        $duplicateStmt->execute([
            $foundId,
            $userId
        ]);

        $existingClaim =
            $duplicateStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if ($existingClaim) {

            throw new Exception(
                'You already have an active claim for this item.'
            );
        }


        // =================================================
        // OPTIONAL PROOF FILE
        // =================================================

        $proofImage =
            null;

        $hasProofImage =
            false;


        if (
            isset($_FILES['proof_image'])
            &&
            $_FILES['proof_image']['error'] !==
            UPLOAD_ERR_NO_FILE
        ) {

            // -------------------------------------------------
            // UPLOAD ERROR
            // -------------------------------------------------

            if (
                $_FILES['proof_image']['error'] !==
                UPLOAD_ERR_OK
            ) {

                throw new Exception(
                    'There was a problem uploading the proof file.'
                );
            }


            // -------------------------------------------------
            // MAX 5 MB
            // -------------------------------------------------

            $maxFileSize =
                5 * 1024 * 1024;


            if (
                $_FILES['proof_image']['size'] >
                $maxFileSize
            ) {

                throw new Exception(
                    'Proof file must be 5 MB or smaller.'
                );
            }


            // -------------------------------------------------
            // ORIGINAL FILE NAME
            // -------------------------------------------------

            $originalName =
                $_FILES['proof_image']['name'];


            $extension =
                strtolower(
                    pathinfo(
                        $originalName,
                        PATHINFO_EXTENSION
                    )
                );


            // -------------------------------------------------
            // ALLOWED EXTENSIONS
            // -------------------------------------------------

            $allowedExtensions = [
                'jpg',
                'jpeg',
                'png',
                'webp',
                'pdf'
            ];


            if (
                !in_array(
                    $extension,
                    $allowedExtensions,
                    true
                )
            ) {

                throw new Exception(
                    'Invalid proof file type. Allowed: JPG, JPEG, PNG, WEBP and PDF.'
                );
            }


            // -------------------------------------------------
            // MIME TYPE VALIDATION
            // -------------------------------------------------

            $finfo =
                new finfo(FILEINFO_MIME_TYPE);

            $mimeType =
                $finfo->file(
                    $_FILES['proof_image']['tmp_name']
                );


            $allowedMimeTypes = [
                'jpg'  => [
                    'image/jpeg'
                ],
                'jpeg' => [
                    'image/jpeg'
                ],
                'png'  => [
                    'image/png'
                ],
                'webp' => [
                    'image/webp'
                ],
                'pdf'  => [
                    'application/pdf'
                ]
            ];


            if (
                !isset(
                    $allowedMimeTypes[$extension]
                )
                ||
                !in_array(
                    $mimeType,
                    $allowedMimeTypes[$extension],
                    true
                )
            ) {

                throw new Exception(
                    'The uploaded proof file type is not valid.'
                );
            }


            // -------------------------------------------------
            // UPLOAD DIRECTORY
            // -------------------------------------------------

            $uploadDirectory =
                __DIR__ .
                '/../uploads/';


            if (
                !is_dir(
                    $uploadDirectory
                )
            ) {

                if (
                    !mkdir(
                        $uploadDirectory,
                        0777,
                        true
                    )
                ) {

                    throw new Exception(
                        'Unable to create upload directory.'
                    );
                }
            }


            // -------------------------------------------------
            // UNIQUE FILE NAME
            // -------------------------------------------------

            $newFileName =
                'claim_proof_' .
                $userId .
                '_' .
                $foundId .
                '_' .
                time() .
                '_' .
                bin2hex(
                    random_bytes(4)
                ) .
                '.' .
                $extension;


            $destination =
                $uploadDirectory .
                $newFileName;


            // -------------------------------------------------
            // MOVE FILE
            // -------------------------------------------------

            if (
                !move_uploaded_file(
                    $_FILES['proof_image']['tmp_name'],
                    $destination
                )
            ) {

                throw new Exception(
                    'Unable to save the proof file.'
                );
            }


            $proofImage =
                $newFileName;

            $hasProofImage =
                true;
        }


        // =================================================
        // 5-DAY COMPETING CLAIM PERIOD
        // =================================================

        $proofDeadline =
            null;


        if (!$hasProofImage) {

            $proofDeadline =
                date(
                    'Y-m-d H:i:s',
                    strtotime('+5 days')
                );
        }


        // =================================================
        // INSERT CLAIM
        // =================================================
        //
        // lost_date is now stored with the claim.
        //
        // =================================================

        $insertStmt =
            $pdo->prepare("
                INSERT INTO claims (
                    found_id,
                    lost_id,
                    user_id,
                    lost_date,
                    proof,
                    proof_image,
                    proof_deadline,
                    stage1_answers,
                    claim_date,
                    status,
                    handover_status
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    CURDATE(),
                    'Pending',
                    'Not Started'
                )
            ");


        $insertStmt->execute([
            $foundId,
            $lostId,
            $userId,
            $lostDate,
            $proof,
            $proofImage,
            $proofDeadline,
            $stage1Answers
        ]);


        $claimId =
            $pdo->lastInsertId();


        // =================================================
        // CLAIMANT NOTIFICATION
        // =================================================

        if ($hasProofImage) {

            createNotification(
                $pdo,
                $userId,
                'Claim Submitted for Review',
                "Your ownership claim for '" .
                $foundItem['item_name'] .
                "' has been submitted successfully and is now under review.",
                'Verification Pending',
                'user/my-claims.php'
            );
        }


        // =================================================
        // PUBLIC 5-DAY CLAIM ALERT
        // =================================================

        if (!$hasProofImage) {

            $deadlineFormatted =
                date(
                    'd M Y, h:i A',
                    strtotime(
                        $proofDeadline
                    )
                );


            $publicTitle =
                '5-Day Claim Alert: ' .
                $foundItem['item_name'];


            $publicMessage =
                "A claim has been submitted for '" .
                $foundItem['item_name'] .
                "' without a supporting photo or document. " .
                "If you believe this item belongs to you, " .
                "you may submit a competing claim within 5 days, " .
                "until " .
                $deadlineFormatted .
                ".";


            $allUsersStmt =
                $pdo->query("
                    SELECT user_id
                    FROM users
                ");


            $allUsers =
                $allUsersStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );


            foreach ($allUsers as $user) {

                createNotification(
                    $pdo,
                    $user['user_id'],
                    $publicTitle,
                    $publicMessage,
                    'Claim Alert',
                    'item-details.php?type=found&id=' .
                    $foundId
                );
            }
        }


        // =================================================
        // NOTIFY ADMINS
        // =================================================

        $adminStmt =
            $pdo->query("
                SELECT user_id
                FROM users
                WHERE role = 'admin'
            ");


        $admins =
            $adminStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        // -------------------------------------------------
        // DATE VERIFICATION INFORMATION
        // -------------------------------------------------

        /*
         * The actual found date is NOT shown to the claimant.
         *
         * It can be included in the admin notification because
         * this information is useful for administrative review.
         */

        $dateVerificationMessage =
            "Claimant reported lost date: " .
            date(
                'd M Y',
                strtotime($lostDate)
            ) .
            ". " .
            "System found-date record: " .
            date(
                'd M Y',
                strtotime($foundDate)
            ) .
            ".";


        if ($hasProofImage) {

            $adminMessage =
                "A new ownership claim with supporting " .
                "proof has been submitted for '" .
                $foundItem['item_name'] .
                "'. Please review the claim. " .
                $dateVerificationMessage;

        } else {

            $deadlineFormatted =
                date(
                    'd M Y, h:i A',
                    strtotime(
                        $proofDeadline
                    )
                );


            $adminMessage =
                "A new ownership claim without a " .
                "proof document/photo has been submitted " .
                "for '" .
                $foundItem['item_name'] .
                "'. A 5-day competing-claim period is " .
                "active until " .
                $deadlineFormatted .
                ". " .
                $dateVerificationMessage;
        }


        foreach ($admins as $admin) {

            createNotification(
                $pdo,
                $admin['user_id'],
                'New Ownership Claim Received',
                $adminMessage,
                'Verification Pending',
                'admin/claims.php'
            );
        }


        // =================================================
        // COMMIT
        // =================================================

        $pdo->commit();


        // =================================================
        // SUCCESS MESSAGE
        // =================================================

        if ($hasProofImage) {

            $_SESSION['success'] =
                'Your claim has been submitted successfully. ' .
                'The admin will review your ownership proof.';

        } else {

            $deadlineFormatted =
                date(
                    'd M Y, h:i A',
                    strtotime(
                        $proofDeadline
                    )
                );


            $_SESSION['success'] =
                'Your claim has been submitted successfully. ' .
                'A 5-day competing-claim period is now active until ' .
                $deadlineFormatted .
                '. Other users may submit competing claims during this period.';
        }


        header(
            'Location: ' .
            url(
                'item-details.php?type=found&id=' .
                $foundId
            )
        );

        exit;


    } catch (Throwable $e) {

        if (
            $pdo->inTransaction()
        ) {

            $pdo->rollBack();
        }


        error_log(
            'LOFO ERROR: submit_claim failed: ' .
            $e->getMessage()
        );


        $_SESSION['error'] =
            'Error submitting claim: ' .
            $e->getMessage();


        header(
            'Location: ' .
            url(
                'item-details.php?type=found&id=' .
                $foundId .
                '#claim'
            )
        );

        exit;
    }
}


// =========================================================
// ADMIN - VERIFY / APPROVE / REJECT CLAIM
// =========================================================

if ($action === 'verify_claim') {

    error_log(
        'LOFO DEBUG: verify_claim reached'
    );

    error_log(
        'LOFO DEBUG: claim_id = ' .
        ($_POST['claim_id'] ?? 'MISSING')
    );

    error_log(
        'LOFO DEBUG: decision = ' .
        ($_POST['decision'] ?? 'MISSING')
    );


    // =====================================================
    // ADMIN CHECK
    // =====================================================

    if (!isAdmin()) {

        $_SESSION['error'] =
            'Admin access required.';

        error_log(
            'LOFO DEBUG: Admin check failed. user_role=' .
            ($_SESSION['user_role'] ?? 'NOT SET')
        );


        header(
            'Location: ' .
            url('admin/claims.php')
        );

        exit;
    }


    // =====================================================
    // GET INPUT
    // =====================================================

    $claimId =
        (int)(
            $_POST['claim_id'] ??
            0
        );


    $decision =
        strtolower(
            trim(
                $_POST['decision'] ??
                ''
            )
        );


    $adminRemark =
        trim(
            $_POST['admin_remark'] ??
            ''
        );


    // =====================================================
    // VALIDATE CLAIM ID
    // =====================================================

    if ($claimId <= 0) {

        $_SESSION['error'] =
            'Invalid claim ID.';

        header(
            'Location: ' .
            url('admin/claims.php')
        );

        exit;
    }


    // =====================================================
    // VALIDATE DECISION
    // =====================================================

    if (
        !in_array(
            $decision,
            [
                'approve',
                'reject'
            ],
            true
        )
    ) {

        $_SESSION['error'] =
            'Invalid claim decision.';

        header(
            'Location: ' .
            url('admin/claims.php')
        );

        exit;
    }


    try {

        // =================================================
        // GET CLAIM
        // =================================================

        $claimStmt =
            $pdo->prepare("
                SELECT
                    c.*,
                    f.item_name,
                    f.status AS found_status,
                    f.user_id AS finder_id,
                    f.date_found,
                    u.name AS claimant_name,
                    u.email AS claimant_email
                FROM claims c

                INNER JOIN found_items f
                    ON c.found_id = f.found_id

                INNER JOIN users u
                    ON c.user_id = u.user_id

                WHERE c.claim_id = ?

                LIMIT 1
            ");


        $claimStmt->execute([
            $claimId
        ]);


        $claim =
            $claimStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$claim) {

            throw new Exception(
                'Claim not found.'
            );
        }


        error_log(
            'LOFO DEBUG: Claim found. ' .
            'claim_id=' .
            $claimId .
            ', status=' .
            ($claim['status'] ?? 'NULL') .
            ', proof_image=' .
            ($claim['proof_image'] ?? 'NULL') .
            ', deadline=' .
            ($claim['proof_deadline'] ?? 'NULL') .
            ', lost_date=' .
            ($claim['lost_date'] ?? 'NULL') .
            ', found_date=' .
            ($claim['date_found'] ?? 'NULL')
        );


        // =================================================
        // CLAIM DATE VERIFICATION
        // =================================================
        //
        // This is an ADMIN-SIDE verification.
        //
        // The claimant never receives the found date.
        //
        // =================================================

        $claimLostDate =
            $claim['lost_date'] ?? null;

        $actualFoundDate =
            $claim['date_found'] ?? null;


        if (
            !empty($claimLostDate)
            &&
            !empty($actualFoundDate)
            &&
            $claimLostDate >
            $actualFoundDate
        ) {

            throw new Exception(
                'This claim contains an invalid date sequence: the claimant reported losing the item after it was reported found.'
            );
        }


        // =================================================
        // CLAIM MUST BE PENDING / UNDER REVIEW
        // =================================================

        if (
            !in_array(
                $claim['status'],
                [
                    'Pending',
                    'Under Review'
                ],
                true
            )
        ) {

            throw new Exception(
                'This claim has already been processed.'
            );
        }


        // =================================================
        // APPROVE
        // =================================================

        if ($decision === 'approve') {

            // ---------------------------------------------
            // CHECK PROOF ATTACHMENT
            // ---------------------------------------------

            $hasProofAttachment =
                !empty(
                    $claim['proof_image']
                );


            // ---------------------------------------------
            // CHECK 5-DAY DEADLINE
            // ---------------------------------------------

            $proofDeadlineTimestamp =
                !empty(
                    $claim['proof_deadline']
                )
                ? strtotime(
                    (string)$claim['proof_deadline']
                )
                : null;


            // ---------------------------------------------
            // NO ATTACHMENT
            // ---------------------------------------------

            if (!$hasProofAttachment) {

                if (
                    $proofDeadlineTimestamp === null
                ) {

                    throw new Exception(
                        'This claim cannot be approved because no proof image/document was submitted and no 5-day deadline exists.'
                    );
                }


                if (
                    $proofDeadlineTimestamp >
                    time()
                ) {

                    $deadlineText =
                        date(
                            'd M Y, h:i A',
                            $proofDeadlineTimestamp
                        );


                    throw new Exception(
                        'Approval is locked because this claim has no proof image/document. The 5-day competing-claim period ends on ' .
                        $deadlineText .
                        '.'
                    );
                }
            }


            // =================================================
            // TRANSACTION
            // =================================================

            $pdo->beginTransaction();


            // =================================================
            // APPROVE CLAIM
            // =================================================

            $updateStmt =
                $pdo->prepare("
                    UPDATE claims
                    SET
                        status = 'Approved',
                        handover_status = 'Not Started',
                        admin_remark = ?,
                        verified_by = ?,
                        verification_date = NOW()
                    WHERE claim_id = ?
                ");


            $updateStmt->execute([
                $adminRemark,
                $userId,
                $claimId
            ]);


            if (
                $updateStmt->rowCount() !==
                1
            ) {

                throw new Exception(
                    'The claim could not be approved.'
                );
            }


            error_log(
                'LOFO DEBUG: Claim approved.'
            );


            // =================================================
            // REJECT OTHER ACTIVE CLAIMS
            // =================================================

            $rejectOthers =
                $pdo->prepare("
                    UPDATE claims
                    SET
                        status = 'Rejected',
                        admin_remark =
                            'Another claim for this item was approved.',
                        verified_by = ?,
                        verification_date = NOW()
                    WHERE found_id = ?
                      AND claim_id != ?
                      AND status IN (
                          'Pending',
                          'Under Review'
                      )
                ");


            $rejectOthers->execute([
                $userId,
                $claim['found_id'],
                $claimId
            ]);


            // =================================================
            // NOTIFY CLAIMANT
            // =================================================

            createNotification(
                $pdo,
                $claim['user_id'],
                'Claim Approved',
                "Your ownership claim for '" .
                $claim['item_name'] .
                "' has been approved. You can now contact the finder through private chat and arrange the handover.",
                'Claim Approval',
                'chat.php?claim_id=' .
                (int)$claimId
            );


            // =================================================
            // NOTIFY FINDER
            // =================================================

            if (
                !empty(
                    $claim['finder_id']
                )
            ) {

                createNotification(
                    $pdo,
                    $claim['finder_id'],
                    'Claim Approved - Handover Required',
                    "A claim for the found item '" .
                    $claim['item_name'] .
                    "' has been approved. Please contact the claimant through private chat and arrange the handover.",
                    'Handover',
                    'chat.php?claim_id=' .
                    (int)$claimId
                );
            }


            // =================================================
            // NOTIFY ADMIN
            // =================================================

            createNotification(
                $pdo,
                $userId,
                'Claim Approved Successfully',
                "The claim for '" .
                $claim['item_name'] .
                "' has been approved. The claimant and finder can now arrange the handover.",
                'Claim Approval',
                'admin/claims.php'
            );


            // =================================================
            // COMMIT
            // =================================================

            $pdo->commit();


            error_log(
                'LOFO DEBUG: Approval transaction committed.'
            );


            // =================================================
            // ADMIN SUCCESS
            // =================================================

            $_SESSION['success'] =
                'Claim approved successfully. The claimant and finder can now arrange the handover through private chat.';


            // =================================================
            // ADMIN STAYS ON CLAIMS PAGE
            // =================================================

            header(
                'Location: ' .
                url('admin/claims.php')
            );

            exit;
        }


        // =================================================
        // REJECT CLAIM
        // =================================================

        if ($decision === 'reject') {

            $pdo->beginTransaction();


            $rejectStmt =
                $pdo->prepare("
                    UPDATE claims
                    SET
                        status = 'Rejected',
                        admin_remark = ?,
                        verified_by = ?,
                        verification_date = NOW()
                    WHERE claim_id = ?
                ");


            $rejectStmt->execute([
                $adminRemark,
                $userId,
                $claimId
            ]);


            if (
                $rejectStmt->rowCount() !==
                1
            ) {

                throw new Exception(
                    'The claim could not be rejected.'
                );
            }


            $message =
                "Your ownership claim for '" .
                $claim['item_name'] .
                "' has been rejected.";


            if ($adminRemark !== '') {

                $message .=
                    ' Admin remark: ' .
                    $adminRemark;
            }


            createNotification(
                $pdo,
                $claim['user_id'],
                'Claim Rejected',
                $message,
                'Claim Rejected',
                'user/my-claims.php'
            );


            $pdo->commit();


            $_SESSION['success'] =
                'Claim rejected successfully.';


            header(
                'Location: ' .
                url('admin/claims.php')
            );

            exit;
        }


    } catch (Throwable $e) {

        if (
            $pdo->inTransaction()
        ) {

            $pdo->rollBack();
        }


        error_log(
            'LOFO ERROR: verify_claim failed: ' .
            $e->getMessage()
        );


        $_SESSION['error'] =
            'Error processing claim: ' .
            $e->getMessage();


        header(
            'Location: ' .
            url('admin/claims.php')
        );

        exit;
    }
}


// =========================================================
// UNKNOWN ACTION
// =========================================================

$_SESSION['error'] =
    'Invalid claim action.';

header(
    'Location: ' .
    url('admin/claims.php')
);

exit;