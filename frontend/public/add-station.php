<?php
/**
 * RadioGrab - Add Station
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
                // Insert new station
                $station_id = $db->insert('stations', [
                    'name' => $name,
                    'website_url' => $website_url,
                    'stream_url' => $stream_url ?: null,
                    'logo_url' => $logo_url ?: null,
                    'calendar_url' => $calendar_url ?: null,
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Station - RadioGrab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/radiograb.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="fas fa-radio"></i> RadioGrab
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/stations.php">Stations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/shows.php">Shows</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/recordings.php">Recordings</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/radiograb.js"></script>
    
    <script>
    // Station Discovery Functionality
    document.addEventListener('DOMContentLoaded', function() {
        const discoverBtn = document.getElementById('discover-station');
        const websiteUrlInput = document.getElementById('website_url');
        const discoveryResults = document.getElementById('discovery-results');
        const discoveryContent = document.getElementById('discovery-content');
        const applyBtn = document.getElementById('apply-discovery');
        const dismissBtn = document.getElementById('dismiss-discovery');
        
        let discoveryData = null;
        
        // Discovery button click handler
        discoverBtn.addEventListener('click', async function() {
            const websiteUrl = websiteUrlInput.value.trim();
            
            if (!websiteUrl) {
                alert('Please enter a website URL first');
                return;
            }
            
            // Validate URL format
            try {
                new URL(websiteUrl);
            } catch (e) {
                alert('Please enter a valid URL');
                return;
            }
            
            // Show loading state
            const originalHtml = discoverBtn.innerHTML;
            discoverBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Discovering...';
            discoverBtn.disabled = true;
            
            try {
                // First, try server-side discovery
                const response = await fetch('/api/discover-station.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        website_url: websiteUrl,
                        csrf_token: '<?= generateCSRFToken() ?>'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    discoveryData = result;
                    showDiscoveryResults(result);
                } else {
                    // Server-side failed, try client-side discovery
                    console.log('Server-side discovery failed, trying client-side...');
                    const clientResult = await tryClientSideDiscovery(websiteUrl);
                    
                    if (clientResult.success) {
                        discoveryData = clientResult;
                        showDiscoveryResults(clientResult);
                    } else {
                        alert('Discovery failed: ' + result.error + '\n\nClient-side discovery also failed. The website may be blocking automated requests or temporarily unavailable.');
                    }
                }
                
            } catch (error) {
                console.error('Discovery error:', error);
                alert('Network error occurred during discovery');
            } finally {
                // Restore button state
                discoverBtn.innerHTML = originalHtml;
                discoverBtn.disabled = false;
            }
        });
        
        // Apply suggestions button handler
        applyBtn.addEventListener('click', function() {
            if (discoveryData && discoveryData.suggestions) {
                const suggestions = discoveryData.suggestions;
                
                // Apply discovered values to form fields
                if (suggestions.name) {
                    document.getElementById('name').value = suggestions.name;
                }
                if (suggestions.stream_url) {
                    document.getElementById('stream_url').value = suggestions.stream_url;
                }
                if (suggestions.calendar_url) {
                    document.getElementById('calendar_url').value = suggestions.calendar_url;
                }
                
                // Apply logo URL if discovered
                if (discoveryData.discovered.logo_url) {
                    document.getElementById('logo_url').value = discoveryData.discovered.logo_url;
                }
                
                // Hide discovery results
                discoveryResults.classList.add('d-none');
            }
        });
        
        // Dismiss button handler
        dismissBtn.addEventListener('click', function() {
            discoveryResults.classList.add('d-none');
        });
        
        async function tryClientSideDiscovery(websiteUrl) {
            console.log('Attempting client-side discovery for:', websiteUrl);
            
            try {
                // Parse URL to extract basic information
                const url = new URL(websiteUrl);
                const domain = url.hostname.toLowerCase().replace('www.', '');
                
                // Extract call letters from domain if possible
                const domainParts = domain.split('.');
                let callLetters = null;
                let stationName = null;
                
                for (const part of domainParts) {
                    if (/^[wk][a-z]{2,3}$/i.test(part)) {
                        callLetters = part.toUpperCase();
                        stationName = callLetters + ' Radio';
                        break;
                    }
                }
                
                // If no call letters found, create name from domain
                if (!stationName) {
                    const mainDomain = domainParts[0] || domain;
                    stationName = mainDomain.toUpperCase() + ' Radio';
                }
                
                // Try to test connectivity (limited by CORS)
                let isReachable = false;
                try {
                    // This will be blocked by CORS, but we can catch the error type
                    const testResponse = await fetch(websiteUrl, { 
                        method: 'HEAD', 
                        mode: 'no-cors',
                        cache: 'no-cache'
                    });
                    isReachable = true;
                } catch (e) {
                    // CORS block is expected, but if it's a network error, we'll know
                    console.log('Client-side connectivity test result:', e.message);
                    isReachable = !e.message.includes('NetworkError');
                }
                
                // Create basic discovery result
                const result = {
                    success: true,
                    discovered: {
                        station_name: stationName,
                        call_letters: callLetters,
                        frequency: null,
                        location: null,
                        description: 'Station information discovered from URL analysis (client-side)',
                        logo_url: null,
                        website_url: websiteUrl,
                        stream_url: null,
                        calendar_url: websiteUrl + '/schedule', // Common guess
                        social_links: {},
                        discovered_links: [
                            {
                                text: 'Schedule (guessed)',
                                url: websiteUrl + '/schedule',
                                likely_schedule: true
                            },
                            {
                                text: 'Listen Live (guessed)', 
                                url: websiteUrl + '/listen',
                                likely_schedule: false
                            }
                        ],
                        stream_urls: []
                    },
                    suggestions: {
                        name: stationName,
                        stream_url: '',
                        calendar_url: websiteUrl + '/schedule'
                    },
                    source: 'client-side'
                };
                
                return result;
                
            } catch (error) {
                console.error('Client-side discovery error:', error);
                return {
                    success: false,
                    error: 'Client-side discovery failed: ' + error.message
                };
            }
        }
        
        function showDiscoveryResults(result) {
            const discovered = result.discovered;
            const suggestions = result.suggestions;
            
            let html = '<div class="row">';
            
            // Show discovery method if client-side
            if (result.source === 'client-side') {
                html += '<div class="col-12 mb-2"><div class="alert alert-warning alert-sm py-1 px-2 small">';
                html += '<i class="fas fa-info-circle"></i> ';
                html += 'Server-side discovery failed, using client-side analysis. Some information may be limited.';
                html += '</div></div>';
            }
            
            // Left column - discovered info
            html += '<div class="col-md-6">';
            html += '<h6>Discovered Information:</h6>';
            html += '<ul class="list-unstyled small">';
            
            // Station Name
            if (discovered.station_name) {
                html += `<li><i class="fas fa-check text-success"></i> <strong>Name:</strong> ${discovered.station_name}</li>`;
            } else {
                html += `<li><i class="fas fa-times text-muted"></i> <strong>Name:</strong> Not found</li>`;
            }
            
            // Call Letters
            if (discovered.call_letters) {
                html += `<li><i class="fas fa-check text-success"></i> <strong>Call Letters:</strong> ${discovered.call_letters}</li>`;
            } else {
                html += `<li><i class="fas fa-times text-muted"></i> <strong>Call Letters:</strong> Not found</li>`;
            }
            
            // Frequency
            if (discovered.frequency) {
                html += `<li><i class="fas fa-check text-success"></i> <strong>Frequency:</strong> ${discovered.frequency}</li>`;
            } else {
                html += `<li><i class="fas fa-times text-muted"></i> <strong>Frequency:</strong> Not found</li>`;
            }
            
            // Logo
            if (discovered.logo_url) {
                html += `<li><i class="fas fa-check text-success"></i> <strong>Logo:</strong> <img src="${discovered.logo_url}" alt="Logo" style="max-height: 20px;"> Found</li>`;
            } else {
                html += `<li><i class="fas fa-times text-muted"></i> <strong>Logo:</strong> Not found</li>`;
            }
            
            // Schedule/Calendar
            if (discovered.calendar_url) {
                html += `<li><i class="fas fa-check text-success"></i> <strong>Schedule:</strong> <a href="${discovered.calendar_url}" target="_blank">Found</a></li>`;
            } else {
                html += `<li><i class="fas fa-times text-muted"></i> <strong>Schedule:</strong> Not found</li>`;
            }
            
            // Stream URLs and Testing
            if (discovered.stream_urls && discovered.stream_urls.length > 0) {
                html += `<li><i class="fas fa-check text-success"></i> <strong>Streams:</strong> ${discovered.stream_urls.length} found</li>`;
            } else {
                html += `<li><i class="fas fa-times text-muted"></i> <strong>Streams:</strong> Not found</li>`;
            }
            
            // Stream Test Results
            if (discovered.stream_test_results) {
                const testResults = discovered.stream_test_results;
                if (testResults.compatible) {
                    html += `<li><i class="fas fa-check text-success"></i> <strong>Stream Test:</strong> ✅ Compatible</li>`;
                    html += `<li><i class="fas fa-cog text-info"></i> <strong>Best Tool:</strong> ${testResults.recommended_tool}</li>`;
                } else {
                    html += `<li><i class="fas fa-times text-danger"></i> <strong>Stream Test:</strong> ❌ Not compatible</li>`;
                }
            }
            
            // Location
            if (discovered.location) {
                html += `<li><i class="fas fa-check text-success"></i> <strong>Location:</strong> ${discovered.location}</li>`;
            }
            
            html += '</ul>';
            html += '</div>';
            
            // Right column - suggestions
            html += '<div class="col-md-6">';
            html += '<h6>What Will Be Applied:</h6>';
            html += '<ul class="list-unstyled small">';
            html += `<li><strong>Station Name:</strong> ${suggestions.name}</li>`;
            
            if (suggestions.stream_url) {
                html += `<li><strong>Stream URL:</strong> <span class="text-truncate d-inline-block" style="max-width: 200px;">${suggestions.stream_url}</span></li>`;
            } else {
                html += `<li><strong>Stream URL:</strong> <span class="text-muted">Not found - enter manually</span></li>`;
            }
            
            if (discovered.logo_url) {
                html += `<li><strong>Logo URL:</strong> <img src="${discovered.logo_url}" alt="Logo" style="max-height: 20px;"> Will be applied</li>`;
            } else {
                html += `<li><strong>Logo URL:</strong> <span class="text-muted">Not found - enter manually</span></li>`;
            }
            
            if (suggestions.calendar_url) {
                html += `<li><strong>Calendar URL:</strong> <a href="${suggestions.calendar_url}" target="_blank" class="text-truncate d-inline-block" style="max-width: 200px;">${suggestions.calendar_url}</a></li>`;
            } else {
                html += `<li><strong>Calendar URL:</strong> <span class="text-muted">Not found - enter manually</span></li>`;
            }
            
            html += '</ul>';
            html += '</div>';
            
            html += '</div>';
            
            // Show social links if any
            if (discovered.social_links && Object.keys(discovered.social_links).length > 0) {
                html += '<div class="mt-2">';
                html += '<h6>Social Media:</h6>';
                for (const [platform, url] of Object.entries(discovered.social_links)) {
                    html += `<a href="${url}" target="_blank" class="btn btn-sm btn-outline-secondary me-1">${platform}</a>`;
                }
                html += '</div>';
            }
            
            discoveryContent.innerHTML = html;
            discoveryResults.classList.remove('d-none');
        }
    });
    </script>

    <!-- Footer -->
    <footer class="bg-light mt-5 py-3">
        <div class="container">
            <div class="row">
                <div class="col text-center text-muted">
                    <small>
                        RadioGrab - Radio Recorder | 
                        Version: <?= getVersionNumber() ?>
                    </small>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>