<?php
// LOFO - Header Component
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/notifications.php';

$pdo = getDBConnection();
$currentUser = currentUser();
$unreadNotifs = $currentUser ? getUnreadNotificationCount($pdo, $currentUser['id']) : 0;
$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME . ' - ' . APP_SUBTITLE;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Favicon -->
    
    <link
        rel="icon"
        type="image/png"
        href="<?= htmlspecialchars(asset('images/favicon.png')) ?>"
    >
    

    <!-- Google Fonts: Inter & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- Bootstrap 5.3 CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Font Awesome 6 -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <!-- Custom Stylesheet -->
    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(asset('css/style.css')) ?>?v=<?= file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time() ?>"
    >
</head>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?> | LOFO</title>

    <!-- Favicon -->
    <!--
    <link
        rel="icon"
        type="image/png"
        href="<?= htmlspecialchars(asset('images/favicon.png')) ?>"
    >
    -->

    <!-- Google Fonts: Inter & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- Bootstrap 5.3 CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Font Awesome 6 -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <!-- Custom Stylesheet -->
    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(asset('css/style.css')) ?>?v=<?= file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time() ?>"
    >
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<?php require_once __DIR__ . '/navbar.php'; ?>

<!-- Flash Messages Container -->
<div class="container mt-3">
    <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show shadow-sm border-0 d-flex align-items-center" role="alert">
            <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-success' : ($flash['type'] === 'danger' ? 'fa-triangle-exclamation text-danger' : 'fa-circle-info text-primary') ?> me-2 fs-5"></i>
            <div><?= htmlspecialchars($flash['message']) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
</div>
