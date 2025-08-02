<?php
/**
 * RadioGrab - Email Verification
 * Issue #6 - User Authentication & Admin Access
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

$auth = new UserAuth($db);

$message = '';
$message_type = 'info';

// Handle email verification
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $result = $auth->verifyEmail($token);
    
    if ($result['success']) {
        $message = $result['message'];
        $message_type = 'success';
    } else {
        $message = $result['error'];
        $message_type = 'danger';
    }
} else {
    $message = 'Invalid verification link';
    $message_type = 'danger';
}

$page_title = 'Email Verification';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= h($page_title) ?> - RadioGrab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h3><i class="fas fa-envelope-check"></i> Email Verification</h3>
                    </div>
                    <div class="card-body text-center">
                        <div class="alert alert-<?= $message_type ?>">
                            <?php if ($message_type === 'success'): ?>
                                <i class="fas fa-check-circle fa-2x mb-3"></i><br>
                            <?php else: ?>
                                <i class="fas fa-exclamation-triangle fa-2x mb-3"></i><br>
                            <?php endif; ?>
                            <?= h($message) ?>
                        </div>
                        
                        <?php if ($message_type === 'success'): ?>
                            <a href="/login.php?verified=1" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt"></i> Login Now
                            </a>
                        <?php else: ?>
                            <a href="/register.php" class="btn btn-secondary">
                                <i class="fas fa-user-plus"></i> Register Again
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>