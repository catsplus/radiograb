<?php
/**
 * RadioGrab - Shared Header Template
 */
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'database.php'; // Load database connection
require_once 'branding.php'; // Load branding functions
require_once 'functions.php'; // Load utility functions
require_once 'auth.php'; // Load authentication functions

// Initialize authentication system
$auth = new UserAuth($db);
$is_authenticated = $auth->isAuthenticated();

// If title is not set, use default
if (!isset($page_title)) {
    $page_title = get_setting('site_title', 'RadioGrab');
}

// If active_nav is not set, try to determine from current page
if (!isset($active_nav)) {
    $current_page = basename($_SERVER['PHP_SELF']);
    switch ($current_page) {
        case 'index.php':
            $active_nav = 'dashboard';
            break;
        case 'stations.php':
            $active_nav = 'stations';
            break;
        case 'shows.php':
            $active_nav = 'shows';
            break;
        case 'playlists.php':
            $active_nav = 'playlists';
            break;
        case 'recordings.php':
            $active_nav = 'recordings';
            break;
        case 'feeds.php':
            $active_nav = 'feeds';
            break;
        case 'browse-templates.php':
            $active_nav = 'browse-templates';
            break;
        default:
            $active_nav = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?> - <?= h(get_setting('site_title', 'RadioGrab')) ?></title>
    <link rel="icon" href="/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/radiograb.css" rel="stylesheet">
    <style>
        :root {
            --brand-color: <?= h(get_setting('brand_color', '#343a40')); ?>;
        }
        .bg-primary {
            background-color: var(--brand-color) !important;
        }
    </style>
    <?php if (isset($additional_css)): ?>
        <?= $additional_css ?>
    <?php endif; ?>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/">
                <img src="<?= h(get_setting('site_logo', '/assets/images/radiograb-logo.png')) ?>" alt="<?= h(get_setting('site_title', 'RadioGrab')) ?> Logo" style="max-height: 30px; margin-right: 10px;">
                <?= h(get_setting('site_title', 'RadioGrab')) ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= $active_nav === 'dashboard' ? 'active' : '' ?>" href="/">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_nav === 'stations' ? 'active' : '' ?>" href="/stations.php">Stations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_nav === 'browse-templates' ? 'active' : '' ?>" href="/browse-templates.php">
                            <i class="fas fa-clone"></i> Browse Templates
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_nav === 'shows' ? 'active' : '' ?>" href="/shows.php">Shows</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_nav === 'playlists' ? 'active' : '' ?>" href="/playlists.php">Playlists</a>
                    </li>
                    <?php if ($is_authenticated): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_nav === 'recordings' ? 'active' : '' ?>" href="/recordings.php">Recordings</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_nav === 'feeds' ? 'active' : '' ?>" href="/feeds.php">RSS Feeds</a>
                    </li>
                    <?php if ($is_authenticated): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_nav === 'api-keys' ? 'active' : '' ?>" href="/settings/api-keys.php">
                            <i class="fas fa-key"></i> API Keys
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <?php if ($is_authenticated): ?>
                        <li class="nav-item">
                            <span class="navbar-text me-3">
                                <i class="fas fa-user"></i> Welcome, <?= h($auth->getCurrentUser()['first_name'] ?? $auth->getCurrentUser()['username'] ?? 'User') ?>!
                            </span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/login.php">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php foreach (getFlashMessages() as $flash): ?>
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
            <?= h($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>

    <!-- Main Content Container -->
    <div class="container mt-4">