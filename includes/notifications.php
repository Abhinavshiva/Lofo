<?php
// LOFO - Notification Service Helper
// Manages real-time in-app alerts and notifications

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';


/**
 * Create a notification for a user.
 *
 * IMPORTANT:
 * Notification links are stored as relative application paths.
 *
 * Example:
 *     chat.php?claim_id=9
 *
 * NOT:
 *     http://localhost/lofo/chat.php?claim_id=9
 *
 * The notification display code should use url()
 * when generating the clickable link.
 */
function createNotification(
    $pdo,
    $userId,
    $title,
    $message,
    $type = 'General',
    $link = null
) {

    try {

        // -----------------------------------------------------
        // NORMALIZE NOTIFICATION LINK
        // -----------------------------------------------------

        if (!empty($link)) {

            /*
             * If a full LoFo URL was accidentally supplied,
             * convert it back into a relative application path.
             *
             * This prevents:
             *
             * http://localhost/lofo/http://localhost/lofo/...
             */

            if (preg_match('#^https?://#i', $link)) {

                $parsedUrl = parse_url($link);

                $path =
                    $parsedUrl['path'] ?? '';

                $query =
                    isset($parsedUrl['query'])
                    ? '?' . $parsedUrl['query']
                    : '';

                $link =
                    ltrim(
                        $path . $query,
                        '/'
                    );
            }

            // Remove an accidental leading slash
            $link = ltrim($link, '/');
        }


        // -----------------------------------------------------
        // INSERT NOTIFICATION
        // -----------------------------------------------------

        $stmt = $pdo->prepare("
            INSERT INTO notifications
            (
                user_id,
                title,
                message,
                type,
                link,
                is_read,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");


        return $stmt->execute([
            $userId,
            $title,
            $message,
            $type,
            $link
        ]);


    } catch (PDOException $e) {

        error_log(
            'Failed to create notification: ' .
            $e->getMessage()
        );

        return false;
    }
}


/**
 * Get unread notification count.
 */
function getUnreadNotificationCount(
    $pdo,
    $userId
) {

    if (!$userId) {
        return 0;
    }


    try {

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = ?
              AND is_read = 0
        ");


        $stmt->execute([
            $userId
        ]);


        return (int)$stmt->fetchColumn();


    } catch (PDOException $e) {

        error_log(
            'Failed to get unread notification count: ' .
            $e->getMessage()
        );

        return 0;
    }
}


/**
 * Get user's notifications.
 */
function getUserNotifications(
    $pdo,
    $userId,
    $limit = 15
) {

    if (!$userId) {
        return [];
    }


    try {

        $stmt = $pdo->prepare("
            SELECT *
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");


        $stmt->bindValue(
            1,
            $userId,
            PDO::PARAM_INT
        );


        $stmt->bindValue(
            2,
            (int)$limit,
            PDO::PARAM_INT
        );


        $stmt->execute();


        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    } catch (PDOException $e) {

        error_log(
            'Failed to get user notifications: ' .
            $e->getMessage()
        );

        return [];
    }
}


/**
 * Mark one notification as read.
 */
function markNotificationAsRead(
    $pdo,
    $notificationId,
    $userId
) {

    try {

        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE notification_id = ?
              AND user_id = ?
        ");


        return $stmt->execute([
            $notificationId,
            $userId
        ]);


    } catch (PDOException $e) {

        error_log(
            'Failed to mark notification as read: ' .
            $e->getMessage()
        );

        return false;
    }
}


/**
 * Mark all notifications as read.
 */
function markAllNotificationsAsRead(
    $pdo,
    $userId
) {

    try {

        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = ?
        ");


        return $stmt->execute([
            $userId
        ]);


    } catch (PDOException $e) {

        error_log(
            'Failed to mark all notifications as read: ' .
            $e->getMessage()
        );

        return false;
    }
}