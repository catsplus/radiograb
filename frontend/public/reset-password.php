<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

$token = $_GET['token'] ?? '';
$message = '';
$error = '';
$valid_token = false;
$user_id = null;

// Validate token
if ($token) {
    $stmt = $db->prepare("
        SELECT prt.user_id, u.username, u.email 
        FROM password_reset_tokens prt 
        JOIN users u ON prt.user_id = u.id 
        WHERE prt.token = ? AND prt.expires_at > NOW() AND prt.used_at IS NULL
    ");
    $stmt->execute([$token]);
    $reset_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($reset_data) {
        $valid_token = true;
        $user_id = $reset_data['user_id'];
    } else {
        $error = 'Invalid or expired reset token. Please request a new password reset.';
    }
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($password) || empty($confirm_password)) {
            $error = 'Both password fields are required.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            // Update password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            
            if ($stmt->execute([$password_hash, $user_id])) {
                // Mark token as used
                $stmt = $db->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE token = ?");
                $stmt->execute([$token]);
                
                $message = 'Your password has been successfully reset. You can now log in with your new password.';
                $valid_token = false; // Hide form
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        }
    }
}

$page_title = 'Reset Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?> - RadioGrab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center" style="min-height: 100vh; align-items: center;">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h3 class="mb-0"><i class="fas fa-lock"></i> Reset Password</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?= h($message) ?>
                            </div>
                            <div class="text-center">
                                <a href="/login.php" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt"></i> Login Now
                                </a>
                            </div>
                        <?php elseif (!$token): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> 
                                No reset token provided. Please request a password reset.
                            </div>
                            <div class="text-center">
                                <a href="/forgot-password.php" class="btn btn-primary">
                                    <i class="fas fa-key"></i> Request Password Reset
                                </a>
                            </div>
                        <?php elseif (!$valid_token): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?= h($error) ?>
                            </div>
                            <div class="text-center">
                                <a href="/forgot-password.php" class="btn btn-primary">
                                    <i class="fas fa-key"></i> Request New Reset
                                </a>
                            </div>
                        <?php else: ?>
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle"></i> <?= h($error) ?>
                                </div>
                            <?php endif; ?>
                            
                            <p class="text-muted mb-4">
                                Enter your new password below.
                            </p>
                            
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" id="password" name="password" class="form-control" 
                                               placeholder="Enter new password" required minlength="8">
                                    </div>
                                    <small class="text-muted">Must be at least 8 characters long</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                               placeholder="Confirm new password" required minlength="8">
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100 mb-3">
                                    <i class="fas fa-save"></i> Update Password
                                </button>
                            </form>
                            
                            <div class="text-center">
                                <a href="/login.php" class="text-decoration-none">
                                    <i class="fas fa-arrow-left"></i> Back to Login
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePasswords() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            password?.addEventListener('change', validatePasswords);
            confirmPassword?.addEventListener('keyup', validatePasswords);
        });
    </script>
</body>
</html>