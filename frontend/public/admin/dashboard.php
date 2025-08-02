<?php
/**
 * RadioGrab - Admin Dashboard
 * Issue #6 - User Authentication & Admin Access
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

$auth = new UserAuth($db);

// Require admin authentication
requireAuth($auth, true);

$current_user = $auth->getCurrentUser();

// Get system statistics
try {
    $stats = [
        'total_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'],
        'active_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'],
        'total_stations' => $db->fetchOne("SELECT COUNT(*) as count FROM stations")['count'],
        'total_shows' => $db->fetchOne("SELECT COUNT(*) as count FROM shows")['count'],
        'total_recordings' => $db->fetchOne("SELECT COUNT(*) as count FROM recordings")['count'],
        'total_storage' => $db->fetchOne("SELECT COALESCE(SUM(file_size_bytes), 0) as size FROM recordings")['size']
    ];
    
    // Template system statistics
    $template_stats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_templates,
            SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) as pending_templates,
            SUM(usage_count) as total_copies
        FROM stations_master
    ") ?: ['total_templates' => 0, 'pending_templates' => 0, 'total_copies' => 0];
    
    // Recent user activity
    $recent_activity = $db->fetchAll("
        SELECT ua.*, u.username, u.email
        FROM user_activity_log ua
        LEFT JOIN users u ON ua.user_id = u.id
        ORDER BY ua.created_at DESC
        LIMIT 10
    ");
    
    // User list
    $users = $db->fetchAll("
        SELECT u.*, 
               COUNT(DISTINCT s.id) as station_count,
               COUNT(DISTINCT sh.id) as show_count,
               COUNT(DISTINCT r.id) as recording_count
        FROM users u
        LEFT JOIN stations s ON u.id = s.user_id
        LEFT JOIN shows sh ON u.id = sh.user_id
        LEFT JOIN recordings r ON sh.id = r.show_id
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT 20
    ");
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $stats = array_fill_keys(['total_users', 'active_users', 'total_stations', 'total_shows', 'total_recordings', 'total_storage'], 0);
    $recent_activity = [];
    $users = [];
}

$page_title = 'Admin Dashboard';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= h($page_title) ?> - RadioGrab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/radiograb.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/admin/dashboard.php">
                <i class="fas fa-shield-alt"></i> RadioGrab Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/dashboard.php">
                    <i class="fas fa-user"></i> User Dashboard
                </a>
                <a class="nav-link" href="/logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= h($error) ?>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <h1><i class="fas fa-shield-alt"></i> Admin Dashboard</h1>
                <p class="text-muted">System administration and user management</p>
            </div>
        </div>

        <!-- System Statistics -->
        <div class="row mb-4">
            <div class="col-md-2 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h3><?= $stats['total_users'] ?></h3>
                        <p class="mb-0">Total Users</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-user-check fa-2x mb-2"></i>
                        <h3><?= $stats['active_users'] ?></h3>
                        <p class="mb-0">Active Users</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-broadcast-tower fa-2x mb-2"></i>
                        <h3><?= $stats['total_stations'] ?></h3>
                        <p class="mb-0">Stations</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-microphone fa-2x mb-2"></i>
                        <h3><?= $stats['total_shows'] ?></h3>
                        <p class="mb-0">Shows</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-file-audio fa-2x mb-2"></i>
                        <h3><?= $stats['total_recordings'] ?></h3>
                        <p class="mb-0">Recordings</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card bg-dark text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-hdd fa-2x mb-2"></i>
                        <h3><?= formatBytes($stats['total_storage']) ?></h3>
                        <p class="mb-0">Storage</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Quick Actions -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-clone"></i> Template Management</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="h5 text-primary"><?= $template_stats['total_templates'] ?></div>
                                <small>Total</small>
                            </div>
                            <div class="col-4">
                                <div class="h5 text-warning"><?= $template_stats['pending_templates'] ?></div>
                                <small>Pending</small>
                            </div>
                            <div class="col-4">
                                <div class="h5 text-success"><?= $template_stats['total_copies'] ?></div>
                                <small>Copies</small>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="/admin/template-management.php" class="btn btn-primary btn-sm w-100">
                                <i class="fas fa-cogs"></i> Manage Templates
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-users"></i> User Overview</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h5 text-primary"><?= $stats['total_users'] ?></div>
                                <small>Total Users</small>
                            </div>
                            <div class="col-6">
                                <div class="h5 text-success"><?= $stats['active_users'] ?></div>
                                <small>Active</small>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="#userManagement" class="btn btn-info btn-sm w-100">
                                <i class="fas fa-users-cog"></i> View Users Below
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card border-secondary">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-bar"></i> System Stats</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h6 text-primary"><?= $stats['total_stations'] ?></div>
                                <small>Stations</small>
                            </div>
                            <div class="col-6">
                                <div class="h6 text-success"><?= $stats['total_shows'] ?></div>
                                <small>Shows</small>
                            </div>
                        </div>
                        <div class="row text-center mt-2">
                            <div class="col-6">
                                <div class="h6 text-info"><?= $stats['total_recordings'] ?></div>
                                <small>Recordings</small>
                            </div>
                            <div class="col-6">
                                <div class="h6 text-dark"><?= formatBytes($stats['total_storage']) ?></div>
                                <small>Storage</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- User Management -->
            <div id="userManagement"></div>
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-users"></i> User Management</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Stations</th>
                                        <th>Shows</th>
                                        <th>Recordings</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div>
                                                        <strong><?= h($user['username']) ?></strong>
                                                        <?php if ($user['is_admin']): ?>
                                                            <span class="badge bg-warning ms-1">Admin</span>
                                                        <?php endif; ?>
                                                        <?php if ($user['first_name']): ?>
                                                            <br><small class="text-muted"><?= h($user['first_name'] . ' ' . $user['last_name']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?= h($user['email']) ?>
                                                <?php if (!$user['email_verified']): ?>
                                                    <br><small class="text-warning">Unverified</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $user['station_count'] ?></td>
                                            <td><?= $user['show_count'] ?></td>
                                            <td><?= $user['recording_count'] ?></td>
                                            <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-warning" title="Edit User">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($user['id'] != $current_user['id']): ?>
                                                        <button class="btn btn-outline-danger" title="Disable User">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_activity as $activity): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?= h($activity['action']) ?></h6>
                                            <p class="mb-1 text-muted">
                                                <?= $activity['username'] ? h($activity['username']) : 'System' ?>
                                                <?php if ($activity['resource_type']): ?>
                                                    • <?= h($activity['resource_type']) ?>
                                                <?php endif; ?>
                                            </p>
                                            <small class="text-muted">
                                                <?= timeAgo($activity['created_at']) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>