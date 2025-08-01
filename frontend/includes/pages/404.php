<?php
/**
 * 404 Not Found Page Template
 * Displays a user-friendly 404 error page
 */

// Set page variables
$page_title = 'Page Not Found - RadioGrab';
$active_nav = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?></title>
    <meta name="description" content="The page you're looking for could not be found.">
    
    <link rel="icon" href="/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/radiograb.css" rel="stylesheet">
</head>
<body>
    <?php require_once '../includes/navbar.php'; ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <div class="py-5">
                    <i class="fas fa-search fa-5x text-muted mb-4"></i>
                    <h1 class="display-1 fw-bold text-primary">404</h1>
                    <h2 class="mb-3">Page Not Found</h2>
                    <p class="lead text-muted mb-4">
                        Sorry, the page you're looking for doesn't exist or may have been moved.
                    </p>
                    
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="/" class="btn btn-primary">
                            <i class="fas fa-home"></i> Go Home
                        </a>
                        <a href="/stations.php" class="btn btn-outline-primary">
                            <i class="fas fa-broadcast-tower"></i> View Stations
                        </a>
                        <a href="/shows.php" class="btn btn-outline-primary">
                            <i class="fas fa-microphone"></i> View Shows
                        </a>
                        <a href="/recordings.php" class="btn btn-outline-primary">
                            <i class="fas fa-file-audio"></i> View Recordings
                        </a>
                    </div>
                    
                    <div class="mt-5">
                        <h5>Popular Pages</h5>
                        <div class="list-group list-group-horizontal-md justify-content-center">
                            <a href="/stations.php" class="list-group-item list-group-item-action border-0">
                                <i class="fas fa-broadcast-tower text-primary"></i><br>
                                <small>Stations</small>
                            </a>
                            <a href="/shows.php" class="list-group-item list-group-item-action border-0">
                                <i class="fas fa-microphone text-success"></i><br>
                                <small>Shows</small>
                            </a>
                            <a href="/recordings.php" class="list-group-item list-group-item-action border-0">
                                <i class="fas fa-file-audio text-info"></i><br>
                                <small>Recordings</small>
                            </a>
                            <a href="/playlists.php" class="list-group-item list-group-item-action border-0">
                                <i class="fas fa-list text-warning"></i><br>
                                <small>Playlists</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/radiograb.js"></script>
</body>
</html>