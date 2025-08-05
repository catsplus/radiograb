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
    $success_messages = [];
    $warnings = [];
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Station name is required';
    }
    
    // Enhanced URL validation with auto-protocol testing (Issue #44)
    if (empty($website_url)) {
        $errors[] = 'Website URL is required';
    } else {
        $url_result = normalizeAndValidateUrl($website_url);
        if (!$url_result['valid']) {
            $errors[] = $url_result['error'];
        } else {
            // Use the normalized URL
            $website_url = $url_result['url'];
            
            // Show helpful message about protocol detection
            if (isset($url_result['protocol'])) {
                switch ($url_result['protocol']) {
                    case 'https_auto':
                        $success_messages[] = 'Automatically detected HTTPS protocol for website URL';
                        break;
                    case 'http_fallback':
                        $success_messages[] = 'Using HTTP protocol (HTTPS not available for this site)';
                        break;
                    case 'https_assumed':
                        if (isset($url_result['warning'])) {
                            $warnings[] = $url_result['warning'];
                        }
                        break;
                }
            }
        }
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

        <!-- Success Messages -->  
        <?php if (!empty($success_messages)): ?>
            <div class="alert alert-success">
                <ul class="mb-0">
                    <?php foreach ($success_messages as $message): ?>
                        <li><?= h($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Warning Messages -->
        <?php if (!empty($warnings)): ?>
            <div class="alert alert-warning">
                <ul class="mb-0">
                    <?php foreach ($warnings as $warning): ?>
                        <li><?= h($warning) ?></li>
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
                                           required
                                           autocomplete="url">
                                    <button type="button" 
                                            id="discover-station" 
                                            class="btn btn-outline-secondary"
                                            title="Automatically discover station information">
                                        <i class="fas fa-search"></i> Discover
                                    </button>
                                </div>
                                <div class="form-text">
                                    Enter the radio station's website URL. We'll try to automatically find streaming information.
                                    <br><strong>Example:</strong> https://kexp.org or https://wnyc.org
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

                            <!-- Discovery Error -->
                            <div id="discovery-error" class="alert alert-warning d-none">
                                <h6><i class="fas fa-exclamation-triangle"></i> Discovery Issue</h6>
                                <div id="discovery-error-message"></div>
                                <div class="mt-2">
                                    <button type="button" id="manual-entry" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Continue Manually
                                    </button>
                                    <button type="button" id="retry-discovery" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-redo"></i> Try Again
                                    </button>
                                </div>
                            </div>

                            <!-- Loading Indicator -->
                            <div id="discovery-loading" class="alert alert-info d-none">
                                <div class="d-flex align-items-center">
                                    <div class="spinner-border spinner-border-sm me-3" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Discovering Station Information...</h6>
                                        <small class="text-muted">This may take a few seconds</small>
                                    </div>
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
                                       required
                                       autocomplete="organization"
                                       maxlength="100">
                                <div class="form-text">
                                    Full station name including frequency if applicable
                                </div>
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
                                <div class="input-group">
                                    <input type="url" 
                                           class="form-control" 
                                           id="stream_url" 
                                           name="stream_url" 
                                           value="<?= h($_POST['stream_url'] ?? '') ?>"
                                           placeholder="http://stream.example.com:8000/live">
                                    <button type="button" 
                                            id="test-stream" 
                                            class="btn btn-outline-success"
                                            title="Test this stream URL"
                                            disabled>
                                        <i class="fas fa-play"></i> Test
                                    </button>
                                </div>
                                <div class="form-text">
                                    Direct link to the audio stream. Common formats: .mp3, .m3u, .pls
                                </div>
                                <div id="stream-test-result" class="mt-1"></div>
                            </div>

                            <div class="mb-3">
                                <label for="logo_url" class="form-label">Logo URL</label>
                                <div class="input-group">
                                    <input type="url" 
                                           class="form-control" 
                                           id="logo_url" 
                                           name="logo_url" 
                                           value="<?= h($_POST['logo_url'] ?? '') ?>"
                                           placeholder="https://example.com/logo.png">
                                    <button type="button" 
                                            id="preview-logo" 
                                            class="btn btn-outline-info"
                                            title="Preview this logo"
                                            disabled>
                                        <i class="fas fa-eye"></i> Preview
                                    </button>
                                </div>
                                <div class="form-text">
                                    URL to the station's logo image. Preferred formats: PNG, JPG, SVG
                                </div>
                                <div id="logo-preview" class="mt-2 d-none">
                                    <img id="logo-preview-img" src="" alt="Logo Preview" style="max-width: 100px; max-height: 100px; border-radius: 4px;">
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

                            <div class="d-flex justify-content-between align-items-center">
                                <a href="/stations.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Cancel
                                </a>
                                <div>
                                    <button type="button" id="validate-station" class="btn btn-outline-primary me-2">
                                        <i class="fas fa-check-circle"></i> Validate
                                    </button>
                                    <button type="submit" class="btn btn-primary" id="submit-station">
                                        <i class="fas fa-plus"></i> Add Station
                                    </button>
                                </div>
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
$additional_js = <<<'EOD'
<script src="/assets/js/radiograb.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const websiteUrl = document.getElementById("website_url");
    const discoverBtn = document.getElementById("discover-station");
    const testStreamBtn = document.getElementById("test-stream");
    const previewLogoBtn = document.getElementById("preview-logo");
    const validateBtn = document.getElementById("validate-station");
    const streamUrl = document.getElementById("stream_url");
    const logoUrl = document.getElementById("logo_url");
    
    // Auto-uppercase call letters
    const callLettersInput = document.getElementById("call_letters");
    callLettersInput.addEventListener("input", function(e) {
        e.target.value = e.target.value.toUpperCase();
    });
    
    // Enable/disable buttons based on input
    function updateButtonStates() {
        testStreamBtn.disabled = !streamUrl.value.trim();
        previewLogoBtn.disabled = !logoUrl.value.trim();
    }
    
    streamUrl.addEventListener("input", updateButtonStates);
    logoUrl.addEventListener("input", updateButtonStates);
    updateButtonStates();
    
    // Discovery functionality
    discoverBtn.addEventListener("click", function() {
        const url = websiteUrl.value.trim();
        if (!url) {
            showAlert("Please enter a website URL first", "warning");
            return;
        }
        
        // Allow URLs without protocol - backend will handle protocol detection (Issue #44)
        // Accept domains like: example.com, www.example.com, subdomain.example.com, or full URLs
        if (!url.match(/^https?:\/\//) && !url.match(/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/)) {
            showAlert("Please enter a valid website URL (e.g., wjffradio.org or www.example.com)", "warning");
            return;
        }
        
        performDiscovery(url);
    });
    
    // Test stream functionality
    testStreamBtn.addEventListener("click", function() {
        const url = streamUrl.value.trim();
        if (!url) return;
        
        testStream(url);
    });
    
    // Preview logo functionality
    previewLogoBtn.addEventListener("click", function() {
        const url = logoUrl.value.trim();
        if (!url) return;
        
        previewLogo(url);
    });
    
    // Validate station functionality
    validateBtn.addEventListener("click", function() {
        validateStationForm();
    });
    
    function performDiscovery(url) {
        hideAllAlerts();
        showElement("discovery-loading");
        discoverBtn.disabled = true;
        
        fetch("/api/discover-station.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: "url=" + encodeURIComponent(url) + "&csrf_token=" + getCSRFToken()
        })
        .then(response => response.json())
        .then(data => {
            hideElement("discovery-loading");
            discoverBtn.disabled = false;
            
            if (data.success) {
                // Show protocol detection info if available (Issue #44)
                if (data.protocol_info && data.protocol_info.message) {
                    showAlert(data.protocol_info.message, data.protocol_info.type || 'info');
                }
                showDiscoveryResults(data.results);
            } else {
                showDiscoveryError(data.error || "Discovery failed");
            }
        })
        .catch(error => {
            hideElement("discovery-loading");
            discoverBtn.disabled = false;
            showDiscoveryError("Failed to connect to discovery service");
        });
    }
    
    function testStream(url) {
        testStreamBtn.disabled = true;
        testStreamBtn.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i> Testing...";
        
        const resultDiv = document.getElementById("stream-test-result");
        resultDiv.innerHTML = "";
        
        fetch("/api/test-stream.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: "stream_url=" + encodeURIComponent(url) + "&csrf_token=" + getCSRFToken()
        })
        .then(response => response.json())
        .then(data => {
            testStreamBtn.disabled = false;
            testStreamBtn.innerHTML = "<i class=\"fas fa-play\"></i> Test";
            
            if (data.success) {
                resultDiv.innerHTML = "<small class=\"text-success\"><i class=\"fas fa-check\"></i> Stream is accessible</small>";
            } else {
                resultDiv.innerHTML = "<small class=\"text-danger\"><i class=\"fas fa-times\"></i> " + (data.error || "Stream test failed") + "</small>";
            }
        })
        .catch(error => {
            testStreamBtn.disabled = false;
            testStreamBtn.innerHTML = "<i class=\"fas fa-play\"></i> Test";
            resultDiv.innerHTML = "<small class=\"text-danger\"><i class=\"fas fa-times\"></i> Test failed</small>";
        });
    }
    
    function previewLogo(url) {
        const previewDiv = document.getElementById("logo-preview");
        const previewImg = document.getElementById("logo-preview-img");
        
        previewImg.onload = function() {
            previewDiv.classList.remove("d-none");
        };
        
        previewImg.onerror = function() {
            showAlert("Unable to load logo image", "warning");
        };
        
        previewImg.src = url;
    }
    
    function showDiscoveryResults(results) {
        const content = document.getElementById("discovery-content");
        let html = "<div class=\"row\">";
        
        if (results.name) {
            html += "<div class=\"col-md-6\"><strong>Name:</strong> " + results.name + "</div>";
        }
        if (results.call_letters) {
            html += "<div class=\"col-md-6\"><strong>Call Letters:</strong> " + results.call_letters + "</div>";
        }
        if (results.stream_url) {
            html += "<div class=\"col-12 mt-2\"><strong>Stream URL:</strong><br><code>" + results.stream_url + "</code></div>";
        }
        if (results.logo_url) {
            html += "<div class=\"col-12 mt-2\"><strong>Logo URL:</strong><br><code>" + results.logo_url + "</code></div>";
        }
        if (results.calendar_url) {
            html += "<div class=\"col-12 mt-2\"><strong>Calendar URL:</strong><br><code>" + results.calendar_url + "</code></div>";
        }
        
        html += "</div>";
        content.innerHTML = html;
        showElement("discovery-results");
        
        // Store results for applying
        window.discoveryResults = results;
    }
    
    function showDiscoveryError(message) {
        document.getElementById("discovery-error-message").textContent = message;
        showElement("discovery-error");
    }
    
    function validateStationForm() {
        const errors = [];
        
        if (!websiteUrl.value.trim()) {
            errors.push("Website URL is required");
        } else if (!isValidUrl(websiteUrl.value.trim())) {
            errors.push("Website URL must be a valid URL");
        }
        
        if (!document.getElementById("name").value.trim()) {
            errors.push("Station name is required");
        }
        
        if (streamUrl.value.trim() && !isValidUrl(streamUrl.value.trim())) {
            errors.push("Stream URL must be a valid URL");
        }
        
        if (logoUrl.value.trim() && !isValidUrl(logoUrl.value.trim())) {
            errors.push("Logo URL must be a valid URL");
        }
        
        if (errors.length > 0) {
            showAlert("Validation errors:\\n• " + errors.join("\\n• "), "danger");
        } else {
            showAlert("All fields are valid!", "success");
        }
    }
    
    // Apply discovery results
    document.getElementById("apply-discovery").addEventListener("click", function() {
        if (window.discoveryResults) {
            const results = window.discoveryResults;
            
            if (results.name) document.getElementById("name").value = results.name;
            if (results.call_letters) document.getElementById("call_letters").value = results.call_letters;
            if (results.stream_url) document.getElementById("stream_url").value = results.stream_url;
            if (results.logo_url) document.getElementById("logo_url").value = results.logo_url;
            if (results.calendar_url) document.getElementById("calendar_url").value = results.calendar_url;
            
            hideElement("discovery-results");
            updateButtonStates();
            showAlert("Discovery results applied successfully!", "success");
        }
    });
    
    // Dismiss discovery
    document.getElementById("dismiss-discovery").addEventListener("click", function() {
        hideElement("discovery-results");
    });
    
    // Manual entry
    document.getElementById("manual-entry").addEventListener("click", function() {
        hideElement("discovery-error");
        showAlert("You can now fill in the station information manually", "info");
    });
    
    // Retry discovery
    document.getElementById("retry-discovery").addEventListener("click", function() {
        hideElement("discovery-error");
        const url = websiteUrl.value.trim();
        if (url) {
            performDiscovery(url);
        }
    });
    
    // Helper functions
    function showElement(id) {
        document.getElementById(id).classList.remove("d-none");
    }
    
    function hideElement(id) {
        document.getElementById(id).classList.add("d-none");
    }
    
    function hideAllAlerts() {
        hideElement("discovery-results");
        hideElement("discovery-error");
        hideElement("discovery-loading");
    }
    
    function isValidUrl(string) {
        try {
            const url = new URL(string);
            return url.protocol === "http:" || url.protocol === "https:";
        } catch (_) {
            return false;
        }
    }
    
    function showAlert(message, type) {
        // Create a temporary alert
        const alertDiv = document.createElement("div");
        alertDiv.className = "alert alert-" + type + " alert-dismissible fade show";
        alertDiv.innerHTML = message.replace(/\\n/g, "<br>") + 
            "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>";
        
        const form = document.getElementById("add-station-form");
        form.parentNode.insertBefore(alertDiv, form);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
    
    function getCSRFToken() {
        return document.querySelector("input[name=csrf_token]").value;
    }
});
</script>
EOD;
require_once '../includes/footer.php';
?>