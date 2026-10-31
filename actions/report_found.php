<?php
// LOFO - Report Found Item Action
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/matcher.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('user/report-found.php'));
    exit;
}

requireLogin();
$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    setFlash('danger', 'Security validation failed (invalid CSRF token).');
    header('Location: ' . url('user/report-found.php'));
    exit;
}

$pdo = getDBConnection();
$currentUser = currentUser();

$itemName = trim($_POST['item_name'] ?? '');
$categoryId = (int)($_POST['category_id'] ?? 0);
$description = trim($_POST['description'] ?? '');
$locationFound = trim($_POST['location_found'] ?? '');
$dateFound = trim($_POST['date_found'] ?? '');

if (empty($itemName) || $categoryId <= 0 || empty($description) || empty($locationFound)  || empty($dateFound)) {
    setFlash('danger', 'Please fill in all mandatory fields.');
    header('Location: ' . url('user/report-found.php'));
    exit;
}

// Handle Image Upload
$imagePath = null;
if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['item_image'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if (in_array($ext, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
        $filename = 'found_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            $imagePath = 'uploads/' . $filename;
        }
    }
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO found_items (user_id, item_name, category_id, description, location_found, date_found, image_path, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Available')
    ");
    $stmt->execute([
        $currentUser['id'],
        $itemName,
        $categoryId,
        $description,
        $locationFound,
        $dateFound,
        $imagePath
    ]);
    $newFoundId = (int)$pdo->lastInsertId();

    // In-app Notification: Found Item Report Confirmation (SDS Section 2.4.2)
    createNotification(
        $pdo,
        $currentUser['id'],
        'Found Item Report Confirmation',
        "Thank you for reporting '{$itemName}'. The item has been registered as available for claimant verification.",
        'Report Confirmation',
        "item-details.php?type=found&id={$newFoundId}"
    );

    // Trigger Smart Matcher Engine
    $matcher = new SmartMatcher($pdo);
    $matchesCount = $matcher->matchFoundItem($newFoundId);

    $msg = "Thank you! The found item report has been logged.";
    if ($matchesCount > 0) {
        $msg .= " The system detected $matchesCount potential matching lost report(s) and notified the owners.";
    }

    setFlash('success', $msg);
    header('Location: ' . url('user/my-items.php'));
    exit;
} catch (PDOException $e) {
    setFlash('danger', 'Error saving found item record: ' . $e->getMessage());
    header('Location: ' . url('user/report-found.php'));
    exit;
}
