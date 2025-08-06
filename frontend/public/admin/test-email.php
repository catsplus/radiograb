<?php
/**
 * RadioGrab - Email Configuration Test
 * Admin utility to test email functionality
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

$auth = new UserAuth($db);

// Require admin authentication
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: /login.php');
    exit;
}

$test_result = null;
$test_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $test_error = 'Invalid security token. Please try again.';
    } else {
        $test_email = trim($_POST['test_email'] ?? '');
        
        if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            $test_error = 'Please enter a valid email address.';
        } else {
            // Test email sending
            $subject = "RadioGrab Email Test - " . date('Y-m-d H:i:s');
            $html_body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>RadioGrab Email Test</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                    .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 5px 5px; }
                    .footer { text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 14px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>✅ Email Test Successful!</h2>
                    </div>
                    <div class='content'>
                        <p><strong>Congratulations!</strong></p>
                        
                        <p>Your RadioGrab email system is working correctly. This test email was sent at:</p>
                        <p><strong>" . date('Y-m-d H:i:s T') . "</strong></p>
                        
                        <p>Server Information:</p>
                        <ul>
                            <li><strong>Server:</strong> " . gethostname() . "</li>
                            <li><strong>PHP Version:</strong> " . phpversion() . "</li>
                            <li><strong>Mail Method:</strong> msmtp (via Docker container)</li>
                        </ul>
                        
                        <p>Your users should now be able to receive:</p>
                        <ul>
                            <li>Password reset emails</li>
                            <li>Email verification messages</li>
                            <li>Account notifications</li>
                        </ul>
                    </div>
                    <div class='footer'>
                        <p>RadioGrab Admin Email Test</p>
                        <p><em>RadioGrab - Your Personal Radio Recording System</em></p>
                    </div>
                </div>
            </body>
            </html>";
            
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: RadioGrab Admin <noreply@radiograb.svaha.com>\r\n";
            $headers .= "Reply-To: noreply@radiograb.svaha.com\r\n";
            $headers .= "X-Mailer: RadioGrab Email Test\r\n";
            
            error_log("Admin email test: attempting to send to " . $test_email);
            
            if (mail($test_email, $subject, $html_body, $headers)) {
                error_log("Admin email test: SUCCESS - sent to " . $test_email);
                $test_result = "Email test successful! Check your inbox at " . htmlspecialchars($test_email) . " for the test message.";
            } else {
                error_log("Admin email test: FAILED - could not send to " . $test_email);
                $test_error = "Email test failed. Check server logs for more details.";
            }
        }
    }
}

$page_title = 'Email Test';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?> - RadioGrab Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-envelope-open-text"></i> Email System Test</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($test_result): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?= h($test_result) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($test_error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?= h($test_error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>📧 Email Configuration Status</h5>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <p class="mb-2"><strong>Mail System:</strong> msmtp</p>
                                        <p class="mb-2"><strong>SMTP Host:</strong> <?= htmlspecialchars($_ENV['SMTP_HOST'] ?? 'localhost') ?></p>
                                        <p class="mb-2"><strong>SMTP Port:</strong> <?= htmlspecialchars($_ENV['SMTP_PORT'] ?? '587') ?></p>
                                        <p class="mb-2"><strong>From Address:</strong> <?= htmlspecialchars($_ENV['SMTP_FROM'] ?? 'noreply@radiograb.svaha.com') ?></p>
                                        <p class="mb-0"><strong>Authentication:</strong> <?= !empty($_ENV['SMTP_USERNAME']) ? 'Configured (' . htmlspecialchars($_ENV['SMTP_USERNAME']) . ')' : 'Not configured' ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>🔍 System Information</h5>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <p class="mb-2"><strong>Server:</strong> <?= htmlspecialchars(gethostname()) ?></p>
                                        <p class="mb-2"><strong>PHP Version:</strong> <?= phpversion() ?></p>
                                        <p class="mb-2"><strong>Mail Function:</strong> <?= function_exists('mail') ? '✅ Available' : '❌ Not Available' ?></p>
                                        <p class="mb-0"><strong>Sendmail Path:</strong> <?= htmlspecialchars(ini_get('sendmail_path')) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h5>🧪 Send Test Email</h5>
                        <p class="text-muted">Enter an email address to send a test email and verify the configuration is working.</p>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="mb-3">
                                <label for="test_email" class="form-label">Test Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" id="test_email" name="test_email" class="form-control" 
                                           placeholder="Enter email to test" required
                                           value="<?= h($_POST['test_email'] ?? '') ?>">
                                </div>
                                <div class="form-text">The test email will be sent to this address.</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Test Email
                            </button>
                            
                            <a href="/admin/" class="btn btn-secondary ms-2">
                                <i class="fas fa-arrow-left"></i> Back to Admin
                            </a>
                        </form>
                        
                        <hr class="my-4">
                        
                        <h5>📋 Troubleshooting</h5>
                        <div class="accordion" id="troubleshootingAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingOne">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                        Email not working? Common solutions
                                    </button>
                                </h2>
                                <div id="collapseOne" class="accordion-collapse collapse">
                                    <div class="accordion-body">
                                        <ol>
                                            <li><strong>Check SMTP credentials:</strong> Ensure SMTP_USERNAME and SMTP_PASSWORD are correctly set in environment variables.</li>
                                            <li><strong>Verify SMTP server:</strong> Confirm SMTP_HOST and SMTP_PORT are correct for your email provider.</li>
                                            <li><strong>Check server logs:</strong> Look at /var/log/msmtp.log for detailed error messages.</li>
                                            <li><strong>Test from command line:</strong> SSH into container and run: <code>echo "test" | msmtp your@email.com</code></li>
                                            <li><strong>Check firewall:</strong> Ensure container can access SMTP port (usually 587 or 25).</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingTwo">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                        Environment Variables Setup
                                    </button>
                                </h2>
                                <div id="collapseTwo" class="accordion-collapse collapse">
                                    <div class="accordion-body">
                                        <p>Add these to your <code>.env</code> file or docker-compose environment:</p>
                                        <pre class="bg-light p-3 rounded">
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_FROM=noreply@yourdomain.com
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password</pre>
                                        <p><strong>Note:</strong> For Gmail, use an App Password, not your regular password.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>