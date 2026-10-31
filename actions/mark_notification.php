<?php
// LOFO - Mark Notifications Action
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notifications.php';

requireLogin();
$pdo = getDBConnection();
$currentUser = currentUser();

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'read_all') {
    markAllNotificationsAsRead($pdo, $currentUser['id']);
    setFlash('success', 'All notifications marked as read.');
} elseif ($action === 'read' && $id > 0) {
    markNotificationAsRead($pdo, $id, $currentUser['id']);
}

header('Location: ' . url('user/notifications.php'));
exit;
