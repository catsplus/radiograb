<?php
/**
 * RadioGrab - Add Station
 *
 * This file provides the web interface for adding a new radio station to the system.
 * It includes a form for entering station details and integrates with a backend
 * discovery service to automatically find streaming information, logos, and calendar URLs.
 *
 * Key Variables:
 * - `$name`: The name of the station.
 * - `$website_url`: The URL of the station's website.
 * - `$stream_url`: The discovered or manually entered stream URL.
 * - `$logo_url`: The discovered or manually entered logo URL.
 * - `$calendar_url`: The discovered or manually entered calendar URL.
 * - `$call_letters`: The discovered or manually entered call letters.
 * - `$errors`: An array to store any validation or database errors.
 *
 * Inter-script Communication:
 * - This script executes shell commands to call `backend/services/station_discovery.py`
 *   for auto-discovery of station information.
 * - It uses `includes/database.php` for database connection and `includes/functions.php` for helper functions.
 * - JavaScript functions interact with `/api/discover-station.php` for real-time discovery.
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid security token');
        header('Location: /add-station.php');
        exit;
    }
    
    $name = trim($_POST['name'] ?? '');
    $website_url = trim($_POST['website_url'] ?? '');
    $stream_url = trim($_POST['stream_url'] ?? '');
    $logo_url = trim($_POST['logo_url'] ?? '');
    $calendar_url = trim($_POST['calendar_url'] ?? '');
    $call_letters = trim($_POST['call_letters'] ?? '');
    
    $errors = [];
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Station name is required';
    }
    
    if (empty($website_url)) {
        $errors[] = 'Website URL is required';
    } elseif (!isValidUrl($website_url)) {
        $errors[] = 'Invalid website URL format';
    }
    
    if (!empty($stream_url) && !isValidUrl($stream_url)) {
        $errors[] = 'Invalid stream URL format';
    }
    
    if (!empty($logo_url) && !isValidUrl($logo_url)) {
        $errors[] = 'Invalid logo URL format';
    }
    
    if (!empty($calendar_url) && !isValidUrl($calendar_url)) {
        $errors[] = 'Invalid calendar URL format';
    }
    
    if (empty($errors)) {
        try {
            // Check if station already exists
            $existing = $db->fetchOne("SELECT id FROM stations WHERE website_url = ?", [$website_url]);
            if ($existing) {
                $errors[] = 'A station with this website URL already exists';
            } else {
                // Auto-generate call letters if not provided
                if (empty($call_letters)) {
                    // Try to extract call letters from name or website
                    if (preg_match('/\b([A-Z]{3,5})\b/', $name, $matches)) {
                        $call_letters = $matches[1];
                    } else {
                        // Generate from domain (e.g., wext.com -> WEXT)
                        $parsed_url = parse_url($website_url);
                        $domain = $parsed_url['host'] ?? '';
                        $domain_part = preg_replace('/^www\./', '', $domain);
                        $domain_name = strtoupper(preg_replace('/\.[^.]+$/', '', $domain_part));
                        if (strlen($domain_name) >= 3 && strlen($domain_name) <= 5) {
                            $call_letters = $domain_name;
                        } else {
                            // Fallback: use first 4 chars of station name
                            $call_letters = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 4));
                        }
                    }
                }
                
                // Insert new station
                $station_id = $db->insert('stations', [
                    'name' => $name,
                    'website_url' => $website_url,
                    'stream_url' => $stream_url ?: null,
                    'logo_url' => $logo_url ?: null,
                    'calendar_url' => $calendar_url ?: null,
                    'call_letters' => $call_letters ?: null,
                    'status' => 'active'
                ]);
                
                redirectWithMessage('/stations.php', 'success', 'Station added successfully!');
            }
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<?php
// Set page variables for shared template
$page_title = 'Add Station';
$active_nav = 'stations';

require_once '../includes/header.php';
?>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/stations.php">Stations</a></li>
                        <li class="breadcrumb-item active">Add Station</li>
                    </ol>
                </nav>
                <h1><i class="fas fa-plus"></i> Add Radio Station</h1>
                <p class="text-muted">Add a new radio station to start recording shows</p>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <!-- Add Station Form -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-broadcast-tower"></i> Station Information</h5>
                    </div>
                    <div class="card-body">
                        <form id="add-station-form" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="mb-3">
                                <label for="website_url" class="form-label">Station Website URL *</label>
                                <div class="input-group">
                                    <input type="url" 
                                           class="form-control" 
                                           id="website_url" 
                                           name="website_url" 
                                           value="<?= h($_POST['website_url'] ?? '') ?>"
                                           placeholder="https://example-radio.com"
                                           required>
                                    <button type="button" 
                                            id="discover-station" 
                                            class="btn btn-outline-secondary">
                                        <i class="fas fa-search"></i> Discover
                                    </button>
                                </div>
                                <div class="form-text">
                                    Enter the radio station's website URL. We'll try to automatically find streaming information.
                                </div>
                            </div>

                            <!-- Discovery Results -->
                            <div id="discovery-results" class="alert alert-info d-none">
                                <h6><i class="fas fa-magic"></i> Auto-Discovery Results</h6>
                                <div id="discovery-content"></div>
                                <div class="mt-2">
                                    <button type="button" id="apply-discovery" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i> Apply Suggestions
                                    </button>
                                    <button type="button" id="dismiss-discovery" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-times"></i> Dismiss
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="name" class="form-label">Station Name *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="name" 
                                       name="name" 
                                       value="<?= h($_POST['name'] ?? '') ?>"
                                       placeholder="KEXP 90.3 FM"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="call_letters" class="form-label">Call Letters</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="call_letters" 
                                       name="call_letters" 
                                       value="<?= h($_POST['call_letters'] ?? '') ?>"
                                       placeholder="WEXT"
                                       maxlength="5"
                                       pattern="[A-Za-z]{2,5}"
                                       style="text-transform: uppercase;">
                                <div class="form-text">
                                    Station call letters (2-5 letters). Will be auto-generated if not provided.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="stream_url" class="form-label">Stream URL</label>
                                <input type="url" 
                                       class="form-control" 
                                       id="stream_url" 
                                       name="stream_url" 
                                       value="<?= h($_POST['stream_url'] ?? '') ?>"
                                       placeholder="http://stream.example.com:8000/live">
                                <div class="form-text">
                                    Direct link to the audio stream. This will be auto-discovered if possible.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="logo_url" class="form-label">Logo URL</label>
                                <input type="url" 
                                       class="form-control" 
                                       id="logo_url" 
                                       name="logo_url" 
                                       value="<?= h($_POST['logo_url'] ?? '') ?>"
                                       placeholder="https://example.com/logo.png">
                                <div class="form-text">
                                    URL to the station's logo image. Will be auto-discovered if possible.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="calendar_url" class="form-label">Schedule/Calendar URL</label>
                                <input type="url" 
                                       class="form-control" 
                                       id="calendar_url" 
                                       name="calendar_url" 
                                       value="<?= h($_POST['calendar_url'] ?? '') ?>"
                                       placeholder="https://example.com/schedule">
                                <div class="form-text">
                                    URL to the station's programming schedule. Will be auto-discovered if possible.
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="/stations.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Station
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Help Card -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-question-circle"></i> How it Works</h5>
                    </div>
                    <div class="card-body">
                        <ol class="list-group list-group-numbered list-group-flush">
                            <li class="list-group-item border-0">
                                <strong>Enter Website URL</strong><br>
                                <small class="text-muted">Provide the radio station's main website</small>
                            </li>
                            <li class="list-group-item border-0">
                                <strong>Auto-Discovery</strong><br>
                                <small class="text-muted">We'll scan the site for streaming URLs and station info</small>
                            </li>
                            <li class="list-group-item border-0">
                                <strong>Review & Save</strong><br>
                                <small class="text-muted">Verify the information and add the station</small>
                            </li>
                            <li class="list-group-item border-0">
                                <strong>Add Shows</strong><br>
                                <small class="text-muted">Create recording schedules for your favorite shows</small>
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- Tips Card -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-lightbulb"></i> Tips</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i>
                                Look for "Listen Live" links on station websites
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i>
                                Most stations have streaming URLs ending in .mp3 or .m3u
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i>
                                Check the station's mobile app for stream links
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-check text-success"></i>
                                Contact the station if stream info isn't public
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
$additional_js = '<script src="/assets/js/radiograb.js"></script>';
require_once '../includes/footer.php';
?>