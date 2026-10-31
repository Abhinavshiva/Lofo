<?php
// LOFO - Navigation Bar Component

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/notifications.php';

$pdo = getDBConnection();
$currentUser = currentUser();

$unreadCount = $currentUser
    ? getUnreadNotificationCount($pdo, $currentUser['id'])
    : 0;

$latestNotifs = $currentUser
    ? getUserNotifications($pdo, $currentUser['id'], 5)
    : [];


/*
|--------------------------------------------------------------------------
| Notification URL Helper
|--------------------------------------------------------------------------
| Handles both:
|
| 1. Relative links:
|    chat.php?claim_id=9
|
| 2. Full links from older notifications:
|    http://localhost/lofo/chat.php?claim_id=9
|
| This prevents:
|    http://localhost/lofo/http://localhost/lofo/...
|--------------------------------------------------------------------------
*/
function getNotificationUrl($link)
{
    $link = trim((string)$link);

    // No notification link
    if ($link === '') {
        return url('user/notifications.php');
    }

    // Anchor links such as #something
    if (str_starts_with($link, '#')) {
        return $link;
    }

    // Already a full HTTP/HTTPS URL
    if (preg_match('#^https?://#i', $link)) {
        return $link;
    }

    // Relative application path
    return url($link);
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark-primary shadow-sm sticky-top">


<div class="container">

    <!-- Brand -->

    <a class="navbar-brand d-flex align-items-center gap-2"
       href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>">

        <div class="brand-icon">
            <i class="fa-solid fa-compass-drafting text-primary-light"></i>
        </div>

        <div>

            <span class="fw-bold tracking-tight text-white fs-4">
                LOFO
            </span>

            <span class="badge bg-secondary-subtle text-secondary ms-1 fs-xs border">
                UCC Aluva
            </span>

            <div class="brand-subtext d-none d-md-block text-white-50 fs-xs">
                Lost &amp; Found Management System
            </div>

        </div>

    </a>


    <!-- Mobile Toggle -->

    <button class="navbar-toggler border-0"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarMain"
            aria-controls="navbarMain"
            aria-expanded="false"
            aria-label="Toggle navigation">

        <span class="navbar-toggler-icon"></span>

    </button>


    <div class="collapse navbar-collapse" id="navbarMain">

        <!-- Main Navigation -->

        <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3 gap-1">

            <!-- Browse Items -->

            <li class="nav-item">

                <a class="nav-link text-white-hover px-3"
                   href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>">

                    <i class="fa-solid fa-magnifying-glass me-1"></i>
                    Browse Items

                </a>

            </li>


            <!-- Report Lost -->

            <li class="nav-item">

                <a class="nav-link text-white-hover px-3"
                   href="<?= htmlspecialchars(url('user/report-lost.php'), ENT_QUOTES, 'UTF-8') ?>">

                    <i class="fa-solid fa-circle-question text-warning me-1"></i>
                    Report Lost

                </a>

            </li>


            <!-- Report Found -->

            <li class="nav-item">

                <a class="nav-link text-white-hover px-3"
                   href="<?= htmlspecialchars(url('user/report-found.php'), ENT_QUOTES, 'UTF-8') ?>">

                    <i class="fa-solid fa-hand-holding-heart text-success me-1"></i>
                    Report Found

                </a>

            </li>


            <!-- Admin Console -->

            <?php if (isAdmin()): ?>

                <li class="nav-item dropdown">

                    <a class="nav-link dropdown-toggle text-info fw-semibold px-3"
                       href="#"
                       id="adminDropdown"
                       role="button"
                       data-bs-toggle="dropdown"
                       aria-expanded="false">

                        <i class="fa-solid fa-shield-halved me-1"></i>
                        Admin Console

                    </a>


                    <ul class="dropdown-menu dropdown-menu-dark shadow"
                        aria-labelledby="adminDropdown">

                        <li>

                            <a class="dropdown-item"
                               href="<?= htmlspecialchars(url('admin/index.php'), ENT_QUOTES, 'UTF-8') ?>">

                                <i class="fa-solid fa-gauge me-2 text-primary"></i>
                                Dashboard

                            </a>

                        </li>


                        <li>

                            <a class="dropdown-item"
                               href="<?= htmlspecialchars(url('admin/claims.php'), ENT_QUOTES, 'UTF-8') ?>">

                                <i class="fa-solid fa-stamp me-2 text-warning"></i>
                                Verify Claims

                            </a>

                        </li>


                        <li>

                            <a class="dropdown-item"
                               href="<?= htmlspecialchars(url('admin/reports.php'), ENT_QUOTES, 'UTF-8') ?>">

                                <i class="fa-solid fa-list-check me-2 text-info"></i>
                                All Reports

                            </a>

                        </li>


                        <li>

                            <a class="dropdown-item"
                               href="<?= htmlspecialchars(url('admin/matches.php'), ENT_QUOTES, 'UTF-8') ?>">

                                <i class="fa-solid fa-wand-magic-sparkles me-2 text-purple"></i>
                                Smart Matches

                            </a>

                        </li>


                        <li>
                            <hr class="dropdown-divider">
                        </li>


                        <li>

                            <a class="dropdown-item"
                               href="<?= htmlspecialchars(url('admin/categories.php'), ENT_QUOTES, 'UTF-8') ?>">

                                <i class="fa-solid fa-tags me-2"></i>
                                Categories

                            </a>

                        </li>


                        <li>

                            <a class="dropdown-item"
                               href="<?= htmlspecialchars(url('admin/locations.php'), ENT_QUOTES, 'UTF-8') ?>">

                                <i class="fa-solid fa-location-dot me-2"></i>
                                Campus Locations

                            </a>

                        </li>


                        <li>

                            <a class="dropdown-item"
                               href="<?= htmlspecialchars(url('admin/users.php'), ENT_QUOTES, 'UTF-8') ?>">

                                <i class="fa-solid fa-users-gear me-2"></i>
                                Manage Users

                            </a>

                        </li>


                        <li>

                            <a class="dropdown-item"
                               href="<?= htmlspecialchars(url('admin/analytics.php'), ENT_QUOTES, 'UTF-8') ?>">

                                <i class="fa-solid fa-chart-pie me-2 text-success"></i>
                                Analytics &amp; Reports

                            </a>

                        </li>

                    </ul>

                </li>

            <?php endif; ?>

        </ul>


        <!-- Right Side -->

        <ul class="navbar-nav ms-auto align-items-lg-center gap-2">

            <?php if ($currentUser): ?>

                <!-- Notification Bell -->

                <li class="nav-item dropdown me-2">

                    <a class="nav-link position-relative px-3 text-white-hover"
                       href="#"
                       id="notifDropdown"
                       role="button"
                       data-bs-toggle="dropdown"
                       aria-expanded="false">

                        <i class="fa-solid fa-bell fs-5"></i>


                        <?php if ($unreadCount > 0): ?>

                            <span class="position-absolute top-1 start-100 translate-middle badge rounded-pill bg-danger border border-dark">

                                <?= $unreadCount > 9 ? '9+' : $unreadCount ?>

                                <span class="visually-hidden">
                                    unread notifications
                                </span>

                            </span>

                        <?php endif; ?>

                    </a>


                    <div class="dropdown-menu dropdown-menu-end shadow notification-dropdown p-0"
                         aria-labelledby="notifDropdown"
                         style="width:320px;max-width:90vw;">

                        <!-- Notification Header -->

                        <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">

                            <span class="fw-bold text-dark fs-6">

                                <i class="fa-solid fa-bell me-1 text-primary"></i>
                                Notifications

                            </span>


                            <?php if ($unreadCount > 0): ?>

                                <span class="badge bg-primary rounded-pill">
                                    <?= $unreadCount ?> New
                                </span>

                            <?php endif; ?>

                        </div>


                        <!-- Notification List -->

                        <div class="list-group list-group-flush notif-list"
                             style="max-height:280px;overflow-y:auto;">

                            <?php if (empty($latestNotifs)): ?>

                                <div class="p-3 text-center text-muted fs-sm">

                                    <i class="fa-regular fa-bell-slash fs-4 d-block mb-1 opacity-50"></i>

                                    No notifications yet.

                                </div>

                            <?php else: ?>

                                <?php foreach ($latestNotifs as $n): ?>

                                    <?php
                                    /*
                                     * IMPORTANT:
                                     * Use getNotificationUrl() instead of
                                     * directly calling url($n['link']).
                                     *
                                     * This safely handles both old full URLs
                                     * and new relative URLs.
                                     */
                                    $notificationUrl = getNotificationUrl(
                                        $n['link'] ?? ''
                                    );
                                    ?>

                                    <a href="<?= htmlspecialchars(
                                        $notificationUrl,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                       class="list-group-item list-group-item-action p-2 px-3 <?= !empty($n['is_read'])
                                           ? 'text-muted'
                                           : 'bg-light-subtle fw-medium' ?>">

                                        <div class="d-flex w-100 justify-content-between">

                                            <small class="fw-bold text-primary">

                                                <?= htmlspecialchars(
                                                    $n['title'] ?? '',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </small>


                                            <small class="text-muted fs-xs">

                                                <?= !empty($n['created_at'])
                                                    ? date(
                                                        'M d',
                                                        strtotime($n['created_at'])
                                                    )
                                                    : '' ?>

                                            </small>

                                        </div>


                                        <p class="mb-1 text-truncate fs-xs text-dark">

                                            <?= htmlspecialchars(
                                                $n['message'] ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </p>

                                    </a>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </div>


                        <!-- View All -->

                        <div class="p-2 text-center bg-light border-top">

                            <a href="<?= htmlspecialchars(
                                url('user/notifications.php'),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                               class="btn btn-link btn-sm text-decoration-none py-0 text-primary">

                                View All Notifications &rarr;

                            </a>

                        </div>

                    </div>

                </li>


                <!-- User Account Dropdown -->

                <li class="nav-item dropdown">

                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 text-white px-2 py-1 rounded bg-dark-soft"
                       href="#"
                       id="userMenuDropdown"
                       role="button"
                       data-bs-toggle="dropdown"
                       aria-expanded="false">

                        <div class="user-avatar-sm">

                            <i class="fa-solid fa-user"></i>

                        </div>


                        <div class="text-start d-none d-sm-block">

                            <div class="fs-xs fw-bold text-white lh-1">

                                <?= htmlspecialchars(
                                    $currentUser['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </div>


                            <span class="badge bg-<?= $currentUser['role'] === 'admin'
                                ? 'danger'
                                : ($currentUser['role'] === 'faculty'
                                    ? 'info'
                                    : 'primary') ?>
                                fs-xxs mt-1 text-uppercase">

                                <?= htmlspecialchars(
                                    $currentUser['role'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        </div>

                    </a>


                    <ul class="dropdown-menu dropdown-menu-end shadow"
                        aria-labelledby="userMenuDropdown">

                        <!-- User Information -->

                        <li class="px-3 py-2 border-bottom">

                            <div class="fw-bold text-dark">

                                <?= htmlspecialchars(
                                    $currentUser['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </div>


                            <small class="text-muted fs-xs">

                                <?= htmlspecialchars(
                                    $currentUser['email'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </small>


                            <div class="mt-1">

                                <span class="badge bg-secondary-subtle text-secondary border fs-xxs">

                                    <?= htmlspecialchars(
                                        $currentUser['department']
                                            ?: 'Union Christian College',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </span>

                            </div>

                        </li>


                        <!-- My Dashboard -->

                        <li>

                            <a class="dropdown-item py-2"
                               href="<?= htmlspecialchars(url('user/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>">

                                <i class="fa-solid fa-table-columns me-2 text-primary"></i>
                                My Dashboard

                            </a>

                        </li>


                        <!-- Edit Profile -->

                        <li>

                            <a class="dropdown-item py-2"
                               href="<?= htmlspecialchars(url('user/profile.php'), ENT_QUOTES, 'UTF-8') ?>">

                                <i class="fa-solid fa-user-pen me-2 text-primary"></i>
                                Edit Profile

                            </a>

                        </li>


                        <!-- My Reported Items -->

                        <li>

                            <a class="dropdown-item py-2"
                               href="<?= htmlspecialchars(url('user/my-items.php'), ENT_QUOTES, 'UTF-8') ?>">

                                <i class="fa-solid fa-boxes-stacked me-2 text-success"></i>
                                My Reported Items

                            </a>

                        </li>


                        <!-- My Claims -->

                        <li>

                            <a class="dropdown-item py-2"
                               href="<?= htmlspecialchars(url('user/my-claims.php'), ENT_QUOTES, 'UTF-8') ?>">

                                <i class="fa-solid fa-file-shield me-2 text-warning"></i>
                                My Claims &amp; Tracking

                            </a>

                        </li>


                        <!-- Notifications -->

                        <li>

                            <a class="dropdown-item py-2"
                               href="<?= htmlspecialchars(url('user/notifications.php'), ENT_QUOTES, 'UTF-8') ?>">

                                <i class="fa-solid fa-bell me-2 text-info"></i>
                                Notification History

                            </a>

                        </li>


                        <li>
                            <hr class="dropdown-divider">
                        </li>


                        <!-- Sign Out -->

                        <li>

                            <a class="dropdown-item py-2 text-danger"
                               href="<?= htmlspecialchars(url('logout.php'), ENT_QUOTES, 'UTF-8') ?>">

                                <i class="fa-solid fa-right-from-bracket me-2"></i>
                                Sign Out

                            </a>

                        </li>

                    </ul>

                </li>


            <?php else: ?>

                <!-- Sign In -->

                <li class="nav-item">

                    <a class="btn btn-outline-light btn-sm px-3"
                       href="<?= htmlspecialchars(url('login.php'), ENT_QUOTES, 'UTF-8') ?>">

                        <i class="fa-solid fa-right-to-bracket me-1"></i>
                        Sign In

                    </a>

                </li>


                <!-- Register -->

                <li class="nav-item">

                    <a class="btn btn-primary btn-sm px-3 fw-semibold shadow-sm"
                       href="<?= htmlspecialchars(url('register.php'), ENT_QUOTES, 'UTF-8') ?>">

                        <i class="fa-solid fa-user-plus me-1"></i>
                        Register

                    </a>

                </li>

            <?php endif; ?>

        </ul>

    </div>

</div>


</nav>
