<?php
/**
 * RadioGrab - Show Metadata Admin Interface
 * Review and manage auto-detected show metadata
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Handle bulk metadata actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid security token');
        header('Location: /admin-metadata.php');
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'bulk_detect') {
        $station_id = isset($_POST['station_id']) ? (int)$_POST['station_id'] : null;
        $force_update = isset($_POST['force_update']);
        
        // Execute bulk metadata detection
        $python_script = dirname(dirname(__DIR__)) . '/backend/services/show_metadata_cli.py';
        
        if ($station_id) {
            $command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python {$python_script} detect-station {$station_id}" . ($force_update ? ' --force' : '');
        } else {
            $command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python {$python_script} detect-all" . ($force_update ? ' --force' : '');
        }
        
        $output = shell_exec($command . ' 2>&1');
        
        if ($output && !preg_match('/error|exception|failed/i', $output)) {
            setFlashMessage('success', 'Bulk metadata detection completed successfully');
        } else {
            setFlashMessage('danger', 'Bulk metadata detection failed: ' . ($output ?: 'Unknown error'));
        }
        
        header('Location: /admin-metadata.php');
        exit;
    }
}

// Get metadata statistics and show info
try {
    // Get metadata status overview
    $metadata_stats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_shows,
            COUNT(CASE WHEN description IS NOT NULL AND description != '' THEN 1 END) as shows_with_description,
            COUNT(CASE WHEN image_url IS NOT NULL AND image_url != '' THEN 1 END) as shows_with_image,
            COUNT(CASE WHEN host IS NOT NULL AND host != '' THEN 1 END) as shows_with_host,
            COUNT(CASE WHEN description_source = 'calendar' THEN 1 END) as calendar_descriptions,
            COUNT(CASE WHEN description_source = 'website' THEN 1 END) as website_descriptions,
            COUNT(CASE WHEN description_source = 'generated' THEN 1 END) as generated_descriptions,
            COUNT(CASE WHEN description_source = 'manual' THEN 1 END) as manual_descriptions,
            COUNT(CASE WHEN image_source = 'calendar' THEN 1 END) as calendar_images,
            COUNT(CASE WHEN image_source = 'website' THEN 1 END) as website_images,
            COUNT(CASE WHEN image_source = 'station' THEN 1 END) as station_images,
            COUNT(CASE WHEN image_source = 'default' THEN 1 END) as default_images
        FROM shows 
        WHERE active = 1
    ");
    
    // Get shows with metadata details
    $shows = $db->fetchAll("
        SELECT s.*, st.name as station_name, st.call_letters,
               COUNT(r.id) as recording_count
        FROM shows s 
        JOIN stations st ON s.station_id = st.id 
        LEFT JOIN recordings r ON s.id = r.show_id
        WHERE s.active = 1
        GROUP BY s.id 
        ORDER BY s.metadata_updated DESC, s.name
    ");
    
    // Get stations for filter
    $stations = $db->fetchAll("SELECT id, name, call_letters FROM stations WHERE status = 'active' ORDER BY name");
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $metadata_stats = [];
    $shows = [];
    $stations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metadata Admin - RadioGrab</title>
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
                        <a class="nav-link" href="/stations.php">Stations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/shows.php">Shows</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/recordings.php">Recordings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/admin-metadata.php">Metadata Admin</a>
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
                <h1><i class="fas fa-tags"></i> Show Metadata Administration</h1>
                <p class="text-muted">Review and manage automatically detected show metadata</p>
            </div>
        </div>

        <!-- Metadata Statistics -->
        <?php if ($metadata_stats): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Metadata Coverage Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Overall Coverage</h6>
                                    <div class="row text-center mb-3">
                                        <div class="col-4">
                                            <div class="bg-primary text-white rounded p-2">
                                                <div class="h4 mb-0"><?= $metadata_stats['total_shows'] ?></div>
                                                <small>Total Shows</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="bg-success text-white rounded p-2">
                                                <div class="h4 mb-0"><?= $metadata_stats['shows_with_description'] ?></div>
                                                <small>With Description</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="bg-info text-white rounded p-2">
                                                <div class="h4 mb-0"><?= $metadata_stats['shows_with_image'] ?></div>
                                                <small>With Images</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Description Sources</h6>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span><i class="fas fa-calendar text-success"></i> Calendar</span>
                                            <span class="badge bg-success"><?= $metadata_stats['calendar_descriptions'] ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span><i class="fas fa-globe text-info"></i> Website</span>
                                            <span class="badge bg-info"><?= $metadata_stats['website_descriptions'] ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span><i class="fas fa-user-edit text-primary"></i> Manual</span>
                                            <span class="badge bg-primary"><?= $metadata_stats['manual_descriptions'] ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span><i class="fas fa-robot text-secondary"></i> Generated</span>
                                            <span class="badge bg-secondary"><?= $metadata_stats['generated_descriptions'] ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Bulk Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cogs"></i> Bulk Metadata Operations</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" onsubmit="return confirm('This may take several minutes. Continue?');">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="bulk_detect">
                            
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Station Filter</label>
                                    <select class="form-select" name="station_id">
                                        <option value="">All Stations</option>
                                        <?php foreach ($stations as $station): ?>
                                            <option value="<?= $station['id'] ?>">
                                                <?= h($station['call_letters'] ? $station['call_letters'] . ' - ' . $station['name'] : $station['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Options</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="force_update" id="force_update">
                                        <label class="form-check-label" for="force_update">
                                            Force update existing metadata
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-sync"></i> Run Bulk Detection
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shows Metadata Review -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Show Metadata Review</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($shows)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-microphone fa-3x text-muted mb-3"></i>
                                <h5>No shows found</h5>
                                <p class="text-muted">Add stations and shows to manage metadata</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Show</th>
                                            <th>Station</th>
                                            <th>Description</th>
                                            <th>Image</th>
                                            <th>Host</th>
                                            <th>Last Updated</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($shows as $show): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?= h($show['name']) ?></div>
                                                    <?php if ($show['genre']): ?>
                                                        <small class="badge bg-light text-dark"><?= h($show['genre']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= h($show['call_letters'] ?: $show['station_name']) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($show['description']): ?>
                                                        <div class="small text-truncate" style="max-width: 200px;" title="<?= h($show['description']) ?>">
                                                            <?= h($show['description']) ?>
                                                        </div>
                                                        <?php if ($show['description_source']): ?>
                                                            <small class="badge bg-<?= $show['description_source'] === 'calendar' ? 'success' : ($show['description_source'] === 'website' ? 'info' : ($show['description_source'] === 'manual' ? 'primary' : 'secondary')) ?>">
                                                                <i class="fas fa-<?= $show['description_source'] === 'calendar' ? 'calendar' : ($show['description_source'] === 'website' ? 'globe' : ($show['description_source'] === 'manual' ? 'user-edit' : 'robot')) ?>"></i>
                                                                <?= ucfirst($show['description_source']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No description</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($show['image_url']): ?>
                                                        <div class="d-flex align-items-center">
                                                            <img src="<?= h($show['image_url']) ?>" 
                                                                 alt="<?= h($show['name']) ?>" 
                                                                 class="rounded"
                                                                 style="width: 32px; height: 32px; object-fit: cover;"
                                                                 onerror="this.src='/assets/images/default-show.png'">
                                                            <div class="ms-2">
                                                                <small class="badge bg-<?= $show['image_source'] === 'calendar' ? 'success' : ($show['image_source'] === 'website' ? 'info' : ($show['image_source'] === 'station' ? 'warning' : 'secondary')) ?>">
                                                                    <?= ucfirst($show['image_source']) ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">No image</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= h($show['host'] ?: '') ?>
                                                    <?php if (!$show['host']): ?>
                                                        <span class="text-muted">Unknown</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($show['metadata_updated']): ?>
                                                        <small><?= timeAgo($show['metadata_updated']) ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Never</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" 
                                                                onclick="refreshMetadata(<?= $show['id'] ?>)"
                                                                title="Refresh metadata">
                                                            <i class="fas fa-sync"></i>
                                                        </button>
                                                        <a href="/edit-show.php?id=<?= $show['id'] ?>" 
                                                           class="btn btn-outline-secondary"
                                                           title="Edit show">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Refresh metadata function
        function refreshMetadata(showId) {
            const btn = event.target.closest('button');
            const originalContent = btn.innerHTML;
            
            // Show loading state
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
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
                    // Show success and reload page
                    btn.innerHTML = '<i class="fas fa-check"></i>';
                    btn.className = 'btn btn-success btn-sm';
                    
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