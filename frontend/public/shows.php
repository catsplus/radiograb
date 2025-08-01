<?php
/**
 * RadioGrab - Shows Management
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

/**
 * Convert schedule pattern to human-readable format
 */
function formatSchedulePattern($pattern) {
    if (empty($pattern)) {
        return '';
    }
    
    // Handle the "HH:MM:SS on day, day, day" format
    if (preg_match('/^(\d{2}):(\d{2}):(\d{2}) on (.+)$/', $pattern, $matches)) {
        $hour = (int)$matches[1];
        $minute = (int)$matches[2];
        $days_str = $matches[4];
        
        // Convert to 12-hour format
        $time_12hr = formatTime12Hour($hour, $minute);
        
        // Parse days
        $days = array_map('trim', explode(',', $days_str));
        $formatted_days = formatDaysList($days);
        
        return "{$formatted_days} at {$time_12hr}";
    }
    
    // Handle cron format: "minute hour day month day_of_week"
    if (preg_match('/^(\d+|\*) (\d+|\*) (\d+|\*) (\d+|\*) (\d+|\*|[\d,\-]+)$/', $pattern, $matches)) {
        $minute = $matches[1];
        $hour = $matches[2];
        $day_of_week = $matches[5];
        
        if ($minute === '*' || $hour === '*') {
            return 'Complex schedule';
        }
        
        $time_12hr = formatTime12Hour((int)$hour, (int)$minute);
        
        // Convert day of week from cron format
        $days_text = formatCronDays($day_of_week);
        
        return "{$days_text} at {$time_12hr}";
    }
    
    // If pattern doesn't match expected formats, return as is
    return $pattern;
}

/**
 * Convert hour/minute to 12-hour format
 */
function formatTime12Hour($hour, $minute) {
    if ($hour == 0) {
        return sprintf("12:%02d AM", $minute);
    } elseif ($hour < 12) {
        return sprintf("%d:%02d AM", $hour, $minute);
    } elseif ($hour == 12) {
        return sprintf("12:%02d PM", $minute);
    } else {
        return sprintf("%d:%02d PM", $hour - 12, $minute);
    }
}

/**
 * Format a list of day names
 */
function formatDaysList($days) {
    $day_map = [
        'monday' => 'Mondays', 'tuesday' => 'Tuesdays', 'wednesday' => 'Wednesdays',
        'thursday' => 'Thursdays', 'friday' => 'Fridays', 'saturday' => 'Saturdays', 'sunday' => 'Sundays'
    ];
    
    $formatted = [];
    foreach ($days as $day) {
        $day_lower = strtolower($day);
        $formatted[] = $day_map[$day_lower] ?? ucfirst($day);
    }
    
    if (count($formatted) == 1) {
        return $formatted[0];
    } elseif (count($formatted) == 2) {
        return $formatted[0] . ' and ' . $formatted[1];
    } else {
        return implode(', ', array_slice($formatted, 0, -1)) . ', and ' . end($formatted);
    }
}

/**
 * Calculate next air date based on cron pattern
 */
function getNextAirDate($schedule_pattern) {
    if (empty($schedule_pattern)) {
        return null;
    }
    
    // Handle cron format: "minute hour day month day_of_week"
    if (preg_match('/^(\d+|\*) (\d+|\*) (\d+|\*) (\d+|\*) (\d+|\*|[\d,\-]+)$/', $schedule_pattern, $matches)) {
        $minute = $matches[1];
        $hour = $matches[2];
        $day_of_week = $matches[5];
        
        if ($minute === '*' || $hour === '*') {
            return null; // Can't calculate next date for wildcard times
        }
        
        $target_hour = (int)$hour;
        $target_minute = (int)$minute;
        
        // Get current time in EST
        $now = new DateTime('now', new DateTimeZone('America/New_York'));
        $next_air = clone $now;
        
        // Parse day of week
        if ($day_of_week === '*') {
            // Every day - find next occurrence
            $next_air->setTime($target_hour, $target_minute, 0);
            if ($next_air <= $now) {
                $next_air->add(new DateInterval('P1D'));
            }
        } elseif (preg_match('/^(\d+)-(\d+)$/', $day_of_week, $dow_matches)) {
            // Range like "1-5" (Monday-Friday)
            $start_dow = (int)$dow_matches[1];
            $end_dow = (int)$dow_matches[2];
            
            $next_air->setTime($target_hour, $target_minute, 0);
            
            // Find next day in range
            $found = false;
            for ($i = 0; $i < 14; $i++) { // Check next 2 weeks
                $test_date = clone $next_air;
                $test_date->add(new DateInterval("P{$i}D"));
                $current_dow = (int)$test_date->format('w'); // 0=Sunday
                
                if ($current_dow >= $start_dow && $current_dow <= $end_dow) {
                    if ($test_date > $now) {
                        $next_air = $test_date;
                        $found = true;
                        break;
                    }
                }
            }
            
            if (!$found) return null;
        } elseif (strpos($day_of_week, ',') !== false) {
            // Comma-separated like "0,6" (Sunday,Saturday)
            $days = array_map('intval', explode(',', $day_of_week));
            
            $next_air->setTime($target_hour, $target_minute, 0);
            
            // Find next matching day
            $found = false;
            for ($i = 0; $i < 14; $i++) { // Check next 2 weeks
                $test_date = clone $next_air;
                $test_date->add(new DateInterval("P{$i}D"));
                $current_dow = (int)$test_date->format('w');
                
                if (in_array($current_dow, $days)) {
                    if ($test_date > $now) {
                        $next_air = $test_date;
                        $found = true;
                        break;
                    }
                }
            }
            
            if (!$found) return null;
        } else {
            // Single day
            $target_dow = (int)$day_of_week;
            $next_air->setTime($target_hour, $target_minute, 0);
            
            // Find next occurrence of this day
            $current_dow = (int)$next_air->format('w');
            
            if ($current_dow == $target_dow && $next_air > $now) {
                // Today and not passed yet
                return $next_air;
            } else {
                // Find next occurrence
                $days_ahead = ($target_dow - $current_dow + 7) % 7;
                if ($days_ahead == 0) $days_ahead = 7; // Next week
                $next_air->add(new DateInterval("P{$days_ahead}D"));
            }
        }
        
        return $next_air;
    }
    
    return null;
}

/**
 * Format next air date for display
 */
function formatNextAirDate($next_air_date) {
    if (!$next_air_date) {
        return null;
    }
    
    $now = new DateTime('now', new DateTimeZone('America/New_York'));
    $diff = $now->diff($next_air_date);
    
    // Format the date
    $date_str = $next_air_date->format('l, M j \a\t g:i A');
    
    // Add relative time
    if ($diff->days == 0) {
        $relative = 'Today';
    } elseif ($diff->days == 1) {
        $relative = 'Tomorrow';
    } elseif ($diff->days < 7) {
        $relative = 'in ' . $diff->days . ' day' . ($diff->days > 1 ? 's' : '');
    } else {
        $weeks = floor($diff->days / 7);
        $relative = 'in ' . $weeks . ' week' . ($weeks > 1 ? 's' : '');
    }
    
    return [
        'formatted' => $date_str,
        'relative' => $relative
    ];
}

/**
 * Format cron day of week values to human readable
 */
function formatCronDays($day_of_week) {
    if ($day_of_week === '*') {
        return 'Every day';
    }
    
    $day_map = [
        '0' => 'Sundays', '1' => 'Mondays', '2' => 'Tuesdays', '3' => 'Wednesdays',
        '4' => 'Thursdays', '5' => 'Fridays', '6' => 'Saturdays'
    ];
    
    // Handle ranges like "1-5"
    if (preg_match('/^(\d+)-(\d+)$/', $day_of_week, $matches)) {
        $start = (int)$matches[1];
        $end = (int)$matches[2];
        
        if ($start == 1 && $end == 5) {
            return 'Weekdays';
        } elseif ($start == 0 && $end == 6) {
            return 'Every day';
        }
    }
    
    // Handle comma-separated values like "0,6"
    if (strpos($day_of_week, ',') !== false) {
        $days = explode(',', $day_of_week);
        $day_names = [];
        foreach ($days as $day) {
            $day_names[] = $day_map[trim($day)] ?? 'Unknown';
        }
        
        if (count($day_names) == 1) {
            return $day_names[0];
        } elseif (count($day_names) == 2) {
            return $day_names[0] . ' and ' . $day_names[1];
        } else {
            return implode(', ', array_slice($day_names, 0, -1)) . ', and ' . end($day_names);
        }
    }
    
    // Single day
    return $day_map[$day_of_week] ?? 'Unknown';
}

// Handle show actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid security token');
        header('Location: /shows.php');
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete' && isset($_POST['show_id'])) {
        try {
            $show_id = (int)$_POST['show_id'];
            $db->delete('shows', 'id = ?', [$show_id]);
            setFlashMessage('success', 'Show deleted successfully');
        } catch (Exception $e) {
            setFlashMessage('danger', 'Failed to delete show');
        }
    }
    elseif ($action === 'toggle_status' && isset($_POST['show_id'])) {
        try {
            $show_id = (int)$_POST['show_id'];
            $current_status = $db->fetchOne("SELECT active FROM shows WHERE id = ?", [$show_id])['active'];
            $new_status = $current_status ? 0 : 1;
            
            $db->update('shows', ['active' => $new_status], 'id = ?', [$show_id]);
            
            $status_text = $new_status ? 'activated' : 'deactivated';
            setFlashMessage('success', "Show {$status_text} successfully");
        } catch (Exception $e) {
            setFlashMessage('danger', 'Failed to update show status');
        }
    }
    
    header('Location: /shows.php' . ($_GET ? '?' . http_build_query($_GET) : ''));
    exit;
}

// Get filter parameters
$station_id = isset($_GET['station_id']) ? (int)$_GET['station_id'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$genre = isset($_GET['genre']) ? trim($_GET['genre']) : '';
$tags = isset($_GET['tags']) ? trim($_GET['tags']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'desc' : 'asc';

// Build query - only show scheduled shows (not playlists)
$where_conditions = ["s.show_type = 'scheduled' OR s.show_type IS NULL"];
$params = [];

if ($station_id) {
    $where_conditions[] = "s.station_id = ?";
    $params[] = $station_id;
}

if ($status === 'active') {
    $where_conditions[] = "s.active = 1";
} elseif ($status === 'inactive') {
    $where_conditions[] = "s.active = 0";
}

if ($search) {
    $where_conditions[] = "(s.name LIKE ? OR s.description LIKE ? OR st.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($genre) {
    $where_conditions[] = "s.genre LIKE ?";
    $params[] = "%$genre%";
}

if ($tags) {
    $where_conditions[] = "s.tags LIKE ?";
    $params[] = "%$tags%";
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Build ORDER BY clause
$valid_sorts = ['name', 'station_name', 'genre', 'tags', 'next_air_date', 'recording_count', 'latest_recording'];
$sort_column = in_array($sort, $valid_sorts) ? $sort : 'name';

// Handle special sorting cases
if ($sort_column === 'next_air_date') {
    $order_clause = "ORDER BY (CASE WHEN s.schedule_pattern IS NULL OR s.schedule_pattern = '' THEN 1 ELSE 0 END), s.schedule_pattern $order";
} else {
    $order_clause = "ORDER BY $sort_column $order";
}

try {
    // Get shows with station and recording info
    $shows = $db->fetchAll("
        SELECT s.*, st.name as station_name, st.logo_url, st.call_letters, st.timezone as station_timezone,
               COUNT(r.id) as recording_count,
               MAX(r.recorded_at) as latest_recording,
               s.long_description, s.genre, s.image_url, s.website_url,
               s.description_source, s.image_source, s.metadata_updated,
               s.show_type, s.allow_uploads, s.max_file_size_mb
        FROM shows s 
        JOIN stations st ON s.station_id = st.id 
        LEFT JOIN recordings r ON s.id = r.show_id
        $where_clause
        GROUP BY s.id 
        $order_clause
    ", $params);
    
    // Get stations for filter
    $stations = $db->fetchAll("SELECT id, name FROM stations ORDER BY name");
    
    // Get station info if filtering by station
    $station_info = null;
    if ($station_id) {
        $station_info = $db->fetchOne("SELECT id, name, call_letters FROM stations WHERE id = ?", [$station_id]);
    }
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $shows = [];
    $stations = [];
    $station_info = null;
}

// Set page variables for shared template
$page_title = $station_info ? h($station_info['call_letters']) . ' Shows' : 'Shows';
$active_nav = 'shows';
$additional_css = '<link href="/assets/css/on-air.css" rel="stylesheet">';

require_once '../includes/header.php';
?>

    <!-- Flash Messages -->
    <?php foreach (getFlashMessages() as $flash): ?>
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
            <?= h($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= h($error) ?>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <?php if ($station_info): ?>
                    <div class="d-flex align-items-center mb-2">
                        <a href="/stations.php" class="btn btn-outline-secondary btn-sm me-2">
                            <i class="fas fa-arrow-left"></i> Back to Stations
                        </a>
                        <div>
                            <h1><i class="fas fa-microphone"></i> <?= h($station_info['call_letters']) ?> Shows</h1>
                            <p class="text-muted mb-0">Shows for <?= h($station_info['name']) ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <h1><i class="fas fa-microphone"></i> Radio Shows</h1>
                    <p class="text-muted">Manage your recorded radio shows and schedules</p>
                <?php endif; ?>
            </div>
            <div class="col-auto">
                <a href="/add-show.php<?= $station_id ? "?station_id=$station_id" : '' ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Show<?= $station_info ? ' to ' . h($station_info['call_letters']) : '' ?>
                </a>
            </div>
        </div>

        <!-- Next Recordings -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Next Recordings</h5>
                        <button class="btn btn-sm btn-outline-light" onclick="refreshNextRecordings()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="next-recordings-loading" class="text-center py-3">
                            <i class="fas fa-spinner fa-spin"></i> Loading next recordings...
                        </div>
                        <div id="next-recordings-content" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Enhanced Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <!-- Row 1: Basic Filters -->
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" 
                               value="<?= h($search) ?>" placeholder="Search shows...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Station</label>
                        <select class="form-select" name="station_id">
                            <option value="">All Stations</option>
                            <?php foreach ($stations as $station): ?>
                                <option value="<?= $station['id'] ?>" <?= $station_id == $station['id'] ? 'selected' : '' ?>>
                                    <?= h($station['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Shows</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active Only</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Genre</label>
                        <input type="text" class="form-control" name="genre" 
                               value="<?= h($genre) ?>" placeholder="Filter by genre...">
                    </div>
                    
                    <!-- Row 2: Additional Filters and Sorting -->
                    <div class="col-md-3">
                        <label class="form-label">Tags</label>
                        <input type="text" class="form-control" name="tags" 
                               value="<?= h($tags) ?>" placeholder="Filter by tags...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sort By</label>
                        <select class="form-select" name="sort">
                            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Show Name</option>
                            <option value="station_name" <?= $sort === 'station_name' ? 'selected' : '' ?>>Station</option>
                            <option value="genre" <?= $sort === 'genre' ? 'selected' : '' ?>>Genre</option>
                            <option value="tags" <?= $sort === 'tags' ? 'selected' : '' ?>>Tags</option>
                            <option value="next_air_date" <?= $sort === 'next_air_date' ? 'selected' : '' ?>>Next Air Date</option>
                            <option value="recording_count" <?= $sort === 'recording_count' ? 'selected' : '' ?>>Recording Count</option>
                            <option value="latest_recording" <?= $sort === 'latest_recording' ? 'selected' : '' ?>>Latest Recording</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Order</label>
                        <select class="form-select" name="order">
                            <option value="asc" <?= $order === 'asc' ? 'selected' : '' ?>>Ascending</option>
                            <option value="desc" <?= $order === 'desc' ? 'selected' : '' ?>>Descending</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="/shows.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Shows List -->
        <?php if (empty($shows)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-microphone fa-3x text-muted mb-3"></i>
                    <h3>No shows found</h3>
                    <?php if ($search || $station_id || $status): ?>
                        <p class="text-muted mb-4">Try adjusting your filters to see more results.</p>
                        <a href="/shows.php" class="btn btn-primary">Clear Filters</a>
                    <?php else: ?>
                        <p class="text-muted mb-4">Add stations and import their schedules to get started.</p>
                        <a href="/stations.php" class="btn btn-primary">Manage Stations</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($shows as $show): ?>
                    <?php 
                    // Skip On-Demand Recording shows with no recordings
                    if (strpos($show['name'], 'On-Demand Recordings') !== false && $show['recording_count'] == 0) {
                        continue;
                    }
                    ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card h-100 show-card" data-show-id="<?= $show['id'] ?>" data-station-call="<?= h($show['call_letters']) ?>">
                            <!-- Show Image Header -->
                            <?php if ($show['image_url']): ?>
                                <div class="card-img-top-container" style="height: 150px; overflow: hidden; position: relative;">
                                    <img src="<?= h($show['image_url']) ?>" 
                                         alt="<?= h($show['name']) ?>" 
                                         class="card-img-top" 
                                         style="width: 100%; height: 100%; object-fit: cover;"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <div class="fallback-header bg-gradient text-white d-flex align-items-center justify-content-center" 
                                         style="height: 100%; position: absolute; top: 0; left: 0; width: 100%; background: linear-gradient(135deg, #007bff, #0056b3); display: none;">
                                        <div class="text-center">
                                            <i class="fas fa-microphone fa-2x mb-2"></i>
                                            <h6 class="mb-0"><?= h($show['name']) ?></h6>
                                        </div>
                                    </div>
                                    <!-- Image source badge -->
                                    <?php if ($show['image_source']): ?>
                                        <span class="position-absolute top-0 end-0 m-2 badge bg-dark bg-opacity-75">
                                            <?php
                                            $source_icons = [
                                                'calendar' => 'fa-calendar',
                                                'website' => 'fa-globe',
                                                'station' => 'fa-building',
                                                'default' => 'fa-image'
                                            ];
                                            $icon = $source_icons[$show['image_source']] ?? 'fa-image';
                                            ?>
                                            <i class="fas <?= $icon ?>"></i> <?= ucfirst($show['image_source']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <div class="d-flex align-items-start mb-3">
                                    <?php if (!$show['image_url']): ?>
                                        <img src="<?= h(getStationLogo(['logo_url' => $show['logo_url']])) ?>" 
                                             alt="<?= h($show['station_name']) ?>" 
                                             class="station-logo me-3"
                                             onerror="this.src='/assets/images/default-station-logo.png'">
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-1">
                                            <?= h($show['name']) ?>
                                            <?php if ($show['genre']): ?>
                                                <small class="badge bg-light text-dark ms-2"><?= h($show['genre']) ?></small>
                                            <?php endif; ?>
                                        </h5>
                                        <small class="text-muted"><?= h($show['station_name']) ?></small>
                                        <?php if ($show['website_url']): ?>
                                            <div class="mt-1">
                                                <a href="<?= h($show['website_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-external-link-alt"></i> Show Page
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" 
                                               id="toggle<?= $show['id'] ?>"
                                               <?= $show['active'] ? 'checked' : '' ?>
                                               onchange="toggleShowStatus(<?= $show['id'] ?>)">
                                        <label class="form-check-label" for="toggle<?= $show['id'] ?>">
                                            <span class="badge <?= $show['active'] ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $show['active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Enhanced Description Section -->
                                <?php if ($show['description'] || $show['long_description']): ?>
                                    <div class="description-section mb-3">
                                        <?php 
                                        $display_description = $show['long_description'] ?: $show['description'];
                                        $is_truncated = strlen($display_description) > 150;
                                        $short_description = $is_truncated ? substr($display_description, 0, 150) . '...' : $display_description;
                                        ?>
                                        
                                        <div class="description-content">
                                            <div class="description-text" id="desc-short-<?= $show['id'] ?>">
                                                <p class="card-text text-muted small mb-2"><?= h($short_description) ?></p>
                                            </div>
                                            
                                            <?php if ($is_truncated): ?>
                                                <div class="description-text" id="desc-full-<?= $show['id'] ?>" style="display: none;">
                                                    <p class="card-text text-muted small mb-2"><?= h($display_description) ?></p>
                                                </div>
                                                <button class="btn btn-sm btn-link p-0 text-primary" id="desc-toggle-<?= $show['id'] ?>" onclick="toggleDescription(<?= $show['id'] ?>)">
                                                    <i class="fas fa-chevron-down"></i> Show more
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Description Source Badge -->
                                        <?php if ($show['description_source']): ?>
                                            <div class="mt-1">
                                                <small class="badge bg-light text-dark">
                                                    <?php
                                                    $source_icons = [
                                                        'calendar' => 'fa-calendar',
                                                        'website' => 'fa-globe',
                                                        'manual' => 'fa-user-edit',
                                                        'generated' => 'fa-robot'
                                                    ];
                                                    $source_colors = [
                                                        'calendar' => 'bg-success',
                                                        'website' => 'bg-info', 
                                                        'manual' => 'bg-primary',
                                                        'generated' => 'bg-secondary'
                                                    ];
                                                    $icon = $source_icons[$show['description_source']] ?? 'fa-question';
                                                    $color = $source_colors[$show['description_source']] ?? 'bg-light';
                                                    ?>
                                                    <i class="fas <?= $icon ?>"></i> <?= ucfirst($show['description_source']) ?> description
                                                </small>
                                                
                                                <?php if ($show['description_source'] === 'generated'): ?>
                                                    <button class="btn btn-sm btn-outline-warning ms-1" 
                                                            onclick="refreshMetadata(<?= $show['id'] ?>)" 
                                                            title="Auto-detect description from calendar or website">
                                                        <i class="fas fa-sync"></i> Auto-detect
                                                    </button>
                                                <?php elseif ($show['metadata_updated']): ?>
                                                    <small class="text-muted ms-2">
                                                        Updated <?= timeAgo($show['metadata_updated']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <!-- No Description - Show Auto-detect Button -->
                                    <div class="description-section mb-3">
                                        <div class="alert alert-light py-2 mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle"></i> No description available
                                            </small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="refreshMetadata(<?= $show['id'] ?>)" 
                                                title="Auto-detect description from calendar or website">
                                            <i class="fas fa-search"></i> Auto-detect metadata
                                        </button>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <div class="row text-center">
                                        <div class="col">
                                            <div class="fw-bold"><?= $show['recording_count'] ?></div>
                                            <small class="text-muted">Recordings</small>
                                        </div>
                                        <div class="col">
                                            <div class="fw-bold">
                                                <?= $show['latest_recording'] ? timeAgo($show['latest_recording']) : 'Never' ?>
                                            </div>
                                            <small class="text-muted">Latest</small>
                                        </div>
                                    </div>
                                    
                                    <?php 
                                    // Display schedule description or generate from pattern
                                    $schedule_display = '';
                                    if ($show['schedule_description']) {
                                        $schedule_display = $show['schedule_description'];
                                    } elseif ($show['schedule_pattern']) {
                                        $schedule_display = formatSchedulePattern($show['schedule_pattern']);
                                    }
                                    
                                    if ($schedule_display): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> 
                                                <?= h($schedule_display) ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Next Air Date -->
                                    <?php 
                                    if ($show['schedule_pattern']) {
                                        $next_air_date = getNextAirDate($show['schedule_pattern']);
                                        $next_air_formatted = formatNextAirDate($next_air_date);
                                        
                                        if ($next_air_formatted): ?>
                                            <div class="mt-1">
                                                <small class="text-primary">
                                                    <i class="fas fa-clock" title="Next scheduled recording time"></i> 
                                                    <strong>Next Recording:</strong> <?= h($next_air_formatted['formatted']) ?>
                                                    <span class="badge bg-light text-primary ms-1"><?= h($next_air_formatted['relative']) ?></span>
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <div class="mt-1">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar-times" title="No upcoming airings found in current calendar"></i> 
                                                    No airings in current calendar
                                                </small>
                                            </div>
                                        <?php endif;
                                    } ?>
                                    
                                    <!-- Schedule Status - Only show if no schedule configured -->
                                    <?php if (!$show['schedule_pattern']): ?>
                                        <div class="mt-1">
                                            <small class="text-warning">
                                                <i class="fas fa-exclamation-triangle"></i> 
                                                No schedule configured
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    
                                    <?php if ($show['duration_minutes']): ?>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <i class="fas fa-clock"></i> 
                                                <?= $show['duration_minutes'] ?> minutes
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($show['host']): ?>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <i class="fas fa-user"></i> 
                                                <?= h($show['host']) ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Tags Display -->
                                    <div class="mt-2">
                                        <div id="tags-display-<?= $show['id'] ?>">
                                            <?php if ($show['tags']): ?>
                                                <?php foreach (explode(',', $show['tags']) as $tag): ?>
                                                    <span class="badge bg-light text-dark me-1"><?= h(trim($tag)) ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <small class="text-muted">No tags</small>
                                            <?php endif; ?>
                                        </div>
                                        <div id="tags-edit-<?= $show['id'] ?>" style="display: none;">
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control" 
                                                       id="tags-input-<?= $show['id'] ?>" 
                                                       value="<?= h($show['tags'] ?? '') ?>"
                                                       placeholder="Enter tags separated by commas"
                                                       maxlength="255">
                                                <button class="btn btn-success" type="button" 
                                                        onclick="saveTags(<?= $show['id'] ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-secondary" type="button" 
                                                        onclick="cancelEditTags(<?= $show['id'] ?>)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <small class="text-muted">Use commas to separate tags</small>
                                        </div>
                                        <button class="btn btn-sm btn-link p-0 mt-1" 
                                                onclick="editTags(<?= $show['id'] ?>)"
                                                id="edit-tags-btn-<?= $show['id'] ?>">
                                            <i class="fas fa-edit"></i> Edit tags
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-footer bg-transparent">
                                <div class="btn-group w-100" role="group">
                                    <a href="/edit-show.php?id=<?= $show['id'] ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="/recordings.php?show_id=<?= $show['id'] ?>" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-file-audio"></i> Recordings
                                    </a>
                                    <button type="button" 
                                            class="btn btn-outline-secondary btn-sm schedule-manager"
                                            data-show-id="<?= $show['id'] ?>"
                                            data-show-name="<?= h($show['name']) ?>"
                                            title="Manage Schedule">
                                        <i class="fas fa-clock"></i>
                                    </button>
                                    <?php if ($show['recording_count'] > 0): ?>
                                        <a href="/feeds.php#show-<?= $show['id'] ?>" 
                                           class="btn btn-outline-success btn-sm"
                                           title="RSS Feed">
                                            <i class="fas fa-rss"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" 
                                            class="btn btn-outline-danger btn-sm delete-confirm"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal"
                                            data-show-id="<?= $show['id'] ?>"
                                            data-show-name="<?= h($show['name']) ?>"
                                            data-item="show">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Show</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the show <strong id="showName"></strong>?</p>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        This will also delete all associated recordings. This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="show_id" id="deleteShowId">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Show
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- File Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Audio File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="upload_file">
                        <input type="hidden" name="show_id" id="upload_show_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Audio File *</label>
                            <input type="file" class="form-control" name="audio_file" id="audio_file" 
                                   accept=".mp3,.wav,.m4a,.aac,.ogg,.flac,audio/*" required>
                            <div class="form-text">
                                Supported formats: MP3, WAV, M4A, AAC, OGG, FLAC | 
                                Max size: <span id="upload_max_size">100</span>MB
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="upload_title" class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" id="upload_title" 
                                   placeholder="Leave blank to use file metadata or filename">
                        </div>
                        
                        <div class="mb-3">
                            <label for="upload_description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="upload_description" 
                                      rows="3" placeholder="Optional description"></textarea>
                        </div>
                        
                        <div class="upload-progress" style="display: none;">
                            <div class="progress mb-2">
                                <div class="progress-bar" role="progressbar"></div>
                            </div>
                            <div class="upload-status"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="uploadButton">
                        <i class="fas fa-upload"></i> Upload File
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Playlist Management Modal -->
    <div class="modal fade" id="playlistModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Playlist Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Drag & Drop:</strong> Drag tracks by their left edge to reorder them in the playlist.
                        You can also manually edit track numbers.
                    </div>
                    
                    <div id="playlist-loading" class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-2">Loading playlist tracks...</p>
                    </div>
                    
                    <div id="playlist-content" style="display: none;">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="40">Order</th>
                                        <th width="60">Track #</th>
                                        <th>Title</th>
                                        <th width="100">Duration</th>
                                        <th width="100">Uploaded</th>
                                        <th width="80">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="playlist-tracks">
                                    <!-- Tracks loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="savePlaylistOrder">
                        <i class="fas fa-save"></i> Save Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden forms for AJAX actions -->
    <form id="toggleStatusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="show_id" id="toggleShowId">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    </form>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/radiograb.js"></script>
    <script src="/assets/js/on-air-status.js"></script>
    <script>
        let countdownIntervals = [];
        let nextRecordingsData = [];

        // Handle delete modal
        document.addEventListener('DOMContentLoaded', function() {
            const deleteModal = document.getElementById('deleteModal');
            deleteModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const showId = button.getAttribute('data-show-id');
                const showName = button.getAttribute('data-show-name');
                
                document.getElementById('deleteShowId').value = showId;
                document.getElementById('showName').textContent = showName;
            });

            // Load next recordings on page load
            loadNextRecordings();
        });
        
        // Toggle show status
        function toggleShowStatus(showId) {
            // Use AJAX for better UX
            const toggle = document.getElementById(`toggle${showId}`);
            const active = toggle.checked;
            
            fetch('/api/show-management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'toggle_active',
                    show_id: showId,
                    active: active,
                    csrf_token: '<?= generateCSRFToken() ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update badge
                    const badge = toggle.parentElement.querySelector('.badge');
                    if (active) {
                        badge.className = 'badge bg-success';
                        badge.textContent = 'Active';
                    } else {
                        badge.className = 'badge bg-secondary';
                        badge.textContent = 'Inactive';
                    }
                } else {
                    // Revert toggle on error
                    toggle.checked = !active;
                    alert('Failed to update show status: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                // Revert toggle on error
                toggle.checked = !active;
                alert('Network error: ' + error.message);
            });
        }
        
        // Description toggle function
        function toggleDescription(showId) {
            const shortDiv = document.getElementById(`desc-short-${showId}`);
            const fullDiv = document.getElementById(`desc-full-${showId}`);
            const toggleBtn = document.getElementById(`desc-toggle-${showId}`);
            
            if (fullDiv.style.display === 'none') {
                // Show full description
                shortDiv.style.display = 'none';
                fullDiv.style.display = 'block';
                toggleBtn.innerHTML = '<i class="fas fa-chevron-up"></i> Show less';
            } else {
                // Show short description
                shortDiv.style.display = 'block';
                fullDiv.style.display = 'none';
                toggleBtn.innerHTML = '<i class="fas fa-chevron-down"></i> Show more';
            }
        }
        
        // Refresh metadata function
        function refreshMetadata(showId) {
            const btn = document.querySelector(`[onclick="refreshMetadata(${showId})"]`);
            const originalContent = btn.innerHTML;
            
            // Show loading state
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Detecting...';
            
            fetch('/api/show-management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'refresh_metadata',
                    show_id: showId,
                    csrf_token: '<?= generateCSRFToken() ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success and reload page to display new metadata
                    btn.innerHTML = '<i class="fas fa-check"></i> Detected!';
                    btn.className = 'btn btn-sm btn-success';
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show error
                    btn.innerHTML = originalContent;
                    btn.disabled = false;
                    alert('Failed to refresh metadata: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                btn.innerHTML = originalContent;
                btn.disabled = false;
                alert('Network error: ' + error.message);
            });
        }
        
        // Tags editing functions
        function editTags(showId) {
            document.getElementById(`tags-display-${showId}`).style.display = 'none';
            document.getElementById(`tags-edit-${showId}`).style.display = 'block';
            document.getElementById(`edit-tags-btn-${showId}`).style.display = 'none';
            document.getElementById(`tags-input-${showId}`).focus();
        }
        
        function cancelEditTags(showId) {
            document.getElementById(`tags-display-${showId}`).style.display = 'block';
            document.getElementById(`tags-edit-${showId}`).style.display = 'none';
            document.getElementById(`edit-tags-btn-${showId}`).style.display = 'inline-block';
        }
        
        function saveTags(showId) {
            const input = document.getElementById(`tags-input-${showId}`);
            const tags = input.value.trim();
            
            fetch('/api/show-management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_tags',
                    show_id: showId,
                    tags: tags,
                    csrf_token: '<?= generateCSRFToken() ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update tags display
                    const tagsDisplay = document.getElementById(`tags-display-${showId}`);
                    if (tags) {
                        const tagList = tags.split(',').map(tag => 
                            `<span class="badge bg-light text-dark me-1">${tag.trim()}</span>`
                        ).join('');
                        tagsDisplay.innerHTML = tagList;
                    } else {
                        tagsDisplay.innerHTML = '<small class="text-muted">No tags</small>';
                    }
                    
                    // Hide edit mode
                    cancelEditTags(showId);
                } else {
                    alert('Failed to update tags: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Network error: ' + error.message);
            });
        }

        // Next recordings functions
        function loadNextRecordings() {
            const loading = document.getElementById('next-recordings-loading');
            const content = document.getElementById('next-recordings-content');
            
            loading.style.display = 'block';
            content.style.display = 'none';
            
            // Clear existing countdowns
            countdownIntervals.forEach(interval => clearInterval(interval));
            countdownIntervals = [];
            
            fetch('/api/show-management.php?action=get_next_recordings&limit=3')
                .then(response => response.json())
                .then(data => {
                    loading.style.display = 'none';
                    content.style.display = 'block';
                    
                    if (data.success && data.recordings && data.recordings.length > 0) {
                        nextRecordingsData = data.recordings;
                        displayNextRecordings(data.recordings);
                        startCountdowns();
                    } else {
                        content.innerHTML = `
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No upcoming recordings scheduled</h5>
                                <p class="text-muted mb-0">Add shows with schedules to see upcoming recordings</p>
                                <a href="/add-show.php<?= $station_id ? "?station_id=$station_id" : '' ?>" class="btn btn-primary mt-3">
                                    <i class="fas fa-plus"></i> Add Show
                                </a>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    loading.style.display = 'none';
                    content.style.display = 'block';
                    content.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Unable to load next recordings: ${error.message}
                        </div>
                    `;
                });
        }

        function displayNextRecordings(recordings) {
            const content = document.getElementById('next-recordings-content');
            
            let html = '<div class="row">';
            
            recordings.forEach((recording, index) => {
                const colClass = recordings.length === 1 ? 'col-12' : recordings.length === 2 ? 'col-md-6' : 'col-md-4';
                const badgeClass = index === 0 ? 'bg-success' : index === 1 ? 'bg-info' : 'bg-secondary';
                const position = index === 0 ? 'Next' : index === 1 ? '2nd' : '3rd';
                
                html += `
                    <div class="${colClass} mb-3">
                        <div class="card border-${index === 0 ? 'success' : index === 1 ? 'info' : 'secondary'} h-100">
                            <div class="card-body text-center">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="card-title text-start mb-0">${recording.title}</h6>
                                    <span class="badge ${badgeClass} ms-2">${position}</span>
                                </div>
                                <p class="card-text mb-2">
                                    <i class="fas fa-clock text-primary"></i> 
                                    <strong>${recording.next_run}</strong>
                                </p>
                                <div class="countdown-display" id="countdown-${index}">
                                    <div class="alert alert-${index === 0 ? 'success' : index === 1 ? 'info' : 'secondary'} py-2 mb-0">
                                        <div class="countdown-timer" data-target="${recording.next_run}">
                                            Calculating...
                                        </div>
                                    </div>
                                </div>
                                ${recording.tags ? `
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-tags"></i> ${recording.tags}
                                        </small>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            content.innerHTML = html;
        }

        function startCountdowns() {
            const countdownElements = document.querySelectorAll('.countdown-timer');
            
            countdownElements.forEach((element, index) => {
                const targetTime = new Date(element.dataset.target).getTime();
                
                const interval = setInterval(() => {
                    const now = new Date().getTime();
                    const distance = targetTime - now;
                    
                    if (distance < 0) {
                        element.innerHTML = '<strong class="text-danger">Recording should be active!</strong>';
                        clearInterval(interval);
                        return;
                    }
                    
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                    
                    let countdownText = '';
                    if (days > 0) {
                        countdownText = `${days}d ${hours}h ${minutes}m ${seconds}s`;
                    } else if (hours > 0) {
                        countdownText = `${hours}h ${minutes}m ${seconds}s`;
                    } else if (minutes > 0) {
                        countdownText = `${minutes}m ${seconds}s`;
                    } else {
                        countdownText = `${seconds}s`;
                        element.parentElement.classList.add('alert-warning');
                        element.parentElement.classList.remove('alert-success', 'alert-info', 'alert-secondary');
                    }
                    
                    element.innerHTML = `<strong>${countdownText}</strong>`;
                }, 1000);
                
                countdownIntervals.push(interval);
            });
        }

        function refreshNextRecordings() {
            loadNextRecordings();
        }


        // Load functionality on page load
        document.addEventListener('DOMContentLoaded', function() {
            setupUploadHandlers();
            setupPlaylistHandlers();
        });
        
        // Upload functionality
        function setupUploadHandlers() {
            // Handle upload button clicks
            document.querySelectorAll('.upload-file-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const showId = this.dataset.showId;
                    const showName = this.dataset.showName;
                    const maxSize = this.dataset.maxSize;
                    
                    document.getElementById('upload_show_id').value = showId;
                    document.getElementById('upload_max_size').textContent = maxSize;
                    document.querySelector('#uploadModal .modal-title').textContent = `Upload Audio - ${showName}`;
                    
                    // Reset form
                    document.getElementById('uploadForm').reset();
                    document.querySelector('.upload-progress').style.display = 'none';
                    
                    new bootstrap.Modal(document.getElementById('uploadModal')).show();
                });
            });
            
            // Handle upload form submission
            document.getElementById('uploadButton').addEventListener('click', function() {
                const form = document.getElementById('uploadForm');
                const formData = new FormData(form);
                const progressBar = document.querySelector('.upload-progress');
                const statusDiv = document.querySelector('.upload-status');
                
                // Show progress
                progressBar.style.display = 'block';
                statusDiv.textContent = 'Uploading...';
                document.querySelector('.progress-bar').style.width = '0%';
                
                // Upload file
                fetch('/api/upload.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        statusDiv.innerHTML = '<i class="fas fa-check-circle text-success"></i> Upload successful!';
                        document.querySelector('.progress-bar').style.width = '100%';
                        
                        setTimeout(() => {
                            bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
                            location.reload(); // Reload to show updated recording count
                        }, 1500);
                    } else {
                        statusDiv.innerHTML = `<i class="fas fa-times-circle text-danger"></i> ${data.error}`;
                    }
                })
                .catch(error => {
                    statusDiv.innerHTML = `<i class="fas fa-times-circle text-danger"></i> Upload failed: ${error.message}`;
                });
            });
        }
        
        // Playlist management functionality
        function setupPlaylistHandlers() {
            // Handle playlist management button clicks
            document.querySelectorAll('.manage-playlist-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const showId = this.dataset.showId;
                    const showName = this.dataset.showName;
                    
                    document.querySelector('#playlistModal .modal-title').textContent = `Manage Playlist - ${showName}`;
                    loadPlaylistTracks(showId);
                    
                    new bootstrap.Modal(document.getElementById('playlistModal')).show();
                });
            });
        }
        
        function loadPlaylistTracks(showId) {
            const loading = document.getElementById('playlist-loading');
            const content = document.getElementById('playlist-content');
            
            loading.style.display = 'block';
            content.style.display = 'none';
            
            fetch(`/api/playlist-tracks.php?show_id=${showId}`)
            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none';
                
                if (data.success) {
                    const tbody = document.getElementById('playlist-tracks');
                    tbody.innerHTML = '';
                    
                    data.tracks.forEach(track => {
                        const row = createTrackRow(track);
                        tbody.appendChild(row);
                    });
                    
                    // Initialize drag and drop
                    initializeDragDrop();
                    content.style.display = 'block';
                } else {
                    content.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                    content.style.display = 'block';
                }
            })
            .catch(error => {
                loading.style.display = 'none';
                content.innerHTML = `<div class="alert alert-danger">Failed to load tracks: ${error.message}</div>`;
                content.style.display = 'block';
            });
        }
        
        function createTrackRow(track) {
            const row = document.createElement('tr');
            row.dataset.recordingId = track.id;
            row.innerHTML = `
                <td class="drag-handle" style="cursor: move;">
                    <i class="fas fa-grip-vertical text-muted"></i>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm track-number" 
                           value="${track.track_number}" min="1" style="width: 60px;">
                </td>
                <td>
                    <strong>${escapeHtml(track.title)}</strong>
                    ${track.description ? `<br><small class="text-muted">${escapeHtml(track.description)}</small>` : ''}
                </td>
                <td>${formatDuration(track.duration_seconds)}</td>
                <td>${timeAgo(track.recorded_at)}</td>
                <td>
                    <button class="btn btn-sm btn-outline-danger delete-track" 
                            data-recording-id="${track.id}" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            return row;
        }
        
        function initializeDragDrop() {
            const tbody = document.getElementById('playlist-tracks');
            let draggedElement = null;
            
            // Add drag events to all rows
            tbody.querySelectorAll('tr').forEach(row => {
                row.draggable = true;
                
                row.addEventListener('dragstart', function(e) {
                    draggedElement = this;
                    this.style.opacity = '0.5';
                });
                
                row.addEventListener('dragend', function(e) {
                    this.style.opacity = '';
                });
                
                row.addEventListener('dragover', function(e) {
                    e.preventDefault();
                });
                
                row.addEventListener('drop', function(e) {
                    e.preventDefault();
                    if (draggedElement !== this) {
                        const rect = this.getBoundingClientRect();
                        const middle = rect.top + rect.height / 2;
                        
                        if (e.clientY < middle) {
                            this.parentNode.insertBefore(draggedElement, this);
                        } else {
                            this.parentNode.insertBefore(draggedElement, this.nextSibling);
                        }
                        
                        updateTrackNumbers();
                    }
                });
            });
        }
        
        function updateTrackNumbers() {
            const rows = document.querySelectorAll('#playlist-tracks tr');
            rows.forEach((row, index) => {
                const input = row.querySelector('.track-number');
                input.value = index + 1;
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDuration(seconds) {
            if (!seconds) return '--';
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }
    </script>

    <?php
$additional_js = '<script src="/assets/js/on-air-status.js"></script>';
require_once '../includes/footer.php';
?>