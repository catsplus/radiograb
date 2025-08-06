<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check if user exists
            $user = $db->fetchOne("SELECT id, username, email FROM users WHERE email = ? AND is_active = 1", [$email]);
            
            if ($user) {
                // Generate reset token
                $reset_token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store reset token (create table if needed)
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS password_reset_tokens (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        token VARCHAR(255) NOT NULL UNIQUE,
                        expires_at DATETIME NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        used_at DATETIME NULL,
                        INDEX idx_user_id (user_id),
                        INDEX idx_token (token),
                        INDEX idx_expires_at (expires_at),
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                } catch (Exception $table_error) {
                    // Table might already exist, continue
                }
                
                $db->execute("INSERT INTO password_reset_tokens (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?, created_at = NOW()", [$user['id'], $reset_token, $expires_at, $reset_token, $expires_at]);
                
                // Send email
                $reset_link = "https://radiograb.svaha.com/reset-password.php?token=" . $reset_token;
                $subject = "RadioGrab Password Reset";
                
                // Create HTML email
                $html_body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <title>RadioGrab Password Reset</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 5px 5px; }
                        .button { background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 15px 0; }
                        .footer { text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 14px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>🔐 Password Reset Request</h2>
                        </div>
                        <div class='content'>
                            <p>Hello <strong>" . htmlspecialchars($user['username']) . "</strong>,</p>
                            
                            <p>You requested a password reset for your RadioGrab account. Click the button below to reset your password:</p>
                            
                            <p style='text-align: center;'>
                                <a href='" . htmlspecialchars($reset_link) . "' class='button'>Reset My Password</a>
                            </p>
                            
                            <p>Or copy and paste this link into your browser:</p>
                            <p style='word-break: break-all; background: #e9ecef; padding: 10px; border-radius: 3px; font-family: monospace;'>" . htmlspecialchars($reset_link) . "</p>
                            
                            <p><strong>⏰ This link will expire in 1 hour.</strong></p>
                            
                            <p>If you didn't request this password reset, please ignore this email. Your password will remain unchanged.</p>
                        </div>
                        <div class='footer'>
                            <p>Best regards,<br>The RadioGrab Team</p>
                            <p><em>RadioGrab - Your Personal Radio Recording System</em></p>
                        </div>
                    </div>
                </body>
                </html>";
                
                // Headers for HTML email
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "From: RadioGrab <noreply@radiograb.svaha.com>\r\n";
                $headers .= "Reply-To: noreply@radiograb.svaha.com\r\n";
                $headers .= "X-Mailer: RadioGrab\r\n";
                
                // Log email attempt for debugging
                error_log("Attempting to send password reset email to: " . $email);
                
                if (mail($email, $subject, $html_body, $headers)) {
                    error_log("Password reset email sent successfully to: " . $email);
                    $message = 'Password reset instructions have been sent to your email address.';
                } else {
                    error_log("Failed to send password reset email to: " . $email);
                    $error = 'Failed to send email. Please try again or contact support.';
                }
            } else {
                // Don't reveal if email exists or not for security
                $message = 'If that email address exists in our system, you will receive reset instructions.';
            }
        }
    }
}

$page_title = 'Forgot Password';
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
                        <h3 class="mb-0"><i class="fas fa-key"></i> Reset Password</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?= h($message) ?>
                            </div>
                            <div class="text-center">
                                <a href="/login.php" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt"></i> Back to Login
                                </a>
                            </div>
                        <?php else: ?>
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle"></i> <?= h($error) ?>
                                </div>
                            <?php endif; ?>
                            
                            <p class="text-muted mb-4">
                                Enter your email address and we'll send you a link to reset your password.
                            </p>
                            
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" id="email" name="email" class="form-control" 
                                               placeholder="Enter your email" required>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100 mb-3">
                                    <i class="fas fa-paper-plane"></i> Send Reset Link
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
</body>
</html>