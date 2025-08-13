<?php
/**
 * RadioGrab - Shared Navigation Bar
 */
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/branding.php';

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
        default:
            $active_nav = '';
    }
}
?>
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
                    <a class="nav-link <?= $active_nav === 'shows' ? 'active' : '' ?>" href="/shows.php">Shows</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active_nav === 'playlists' ? 'active' : '' ?>" href="/playlists.php">Playlists</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active_nav === 'recordings' ? 'active' : '' ?>" href="/recordings.php">Recordings</a>
                </li>
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