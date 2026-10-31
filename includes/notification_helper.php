<?php

function notifyAllUsers(
    PDO $pdo,
    string $message,
    string $type = 'general',
    ?int $relatedId = null
): void {
    $users = $pdo->query("
        SELECT user_id
        FROM users
    ")->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("
        INSERT INTO notifications
        (
            user_id,
            message,
            notification_type,
            related_id,
            is_read,
            created_at
        )
        VALUES (?, ?, ?, ?, 0, NOW())
    ");

    foreach ($users as $userId) {
        $stmt->execute([
            $userId,
            $message,
            $type,
            $relatedId
        ]);
    }
}