<?php
/**
 * RadioGrab - User Registration
 * Issue #6 - User Authentication & Admin Access
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

$auth = new UserAuth($db);

// Redirect if already logged in
if ($auth->isAuthenticated()) {
    header('Location: /dashboard.php');
    exit;
}

$errors = [];
$success_message = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        
        // Validate password confirmation
        if ($password !== $password_confirm) {
            $errors[] = 'Passwords do not match';
        }
        
        if (empty($errors)) {
            $result = $auth->register($email, $username, $password, $first_name, $last_name);
            
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $errors = $result['errors'];
            }
        }
    }
}

// Set page variables
$page_title = 'Register';
$active_nav = 'register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?> - RadioGrab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/radiograb.css" rel="stylesheet">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .auth-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .auth-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .auth-body {
            padding: 2rem;
        }
        .form-floating {
            margin-bottom: 1rem;
        }
        .password-strength {
            height: 4px;
            margin-top: 5px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        .strength-weak { background-color: #dc3545; }
        .strength-medium { background-color: #ffc107; }
        .strength-strong { background-color: #28a745; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="auth-card">
                        <div class="auth-header">
                            <h2><i class="fas fa-user-plus"></i> Create Account</h2>
                            <p class="mb-0">Join RadioGrab to start recording radio shows</p>
                        </div>
                        
                        <div class="auth-body">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= h($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success_message): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> <?= h($success_message) ?>
                                    <hr>
                                    <p class="mb-0">
                                        <strong>Next steps:</strong>
                                        <br>1. Check your email for a verification link
                                        <br>2. Click the link to activate your account
                                        <br>3. <a href="/login.php">Log in</a> to start using RadioGrab
                                    </p>
                                </div>
                            <?php else: ?>
                                <form method="POST" id="register-form">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="first_name" 
                                                       name="first_name" 
                                                       value="<?= h($_POST['first_name'] ?? '') ?>"
                                                       placeholder="First Name">
                                                <label for="first_name">First Name</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="last_name" 
                                                       name="last_name" 
                                                       value="<?= h($_POST['last_name'] ?? '') ?>"
                                                       placeholder="Last Name">
                                                <label for="last_name">Last Name</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-floating">
                                        <input type="email" 
                                               class="form-control" 
                                               id="email" 
                                               name="email" 
                                               value="<?= h($_POST['email'] ?? '') ?>"
                                               placeholder="Email Address"
                                               required>
                                        <label for="email">Email Address *</label>
                                    </div>
                                    
                                    <div class="form-floating">
                                        <input type="text" 
                                               class="form-control" 
                                               id="username" 
                                               name="username" 
                                               value="<?= h($_POST['username'] ?? '') ?>"
                                               placeholder="Username"
                                               pattern="[a-zA-Z0-9_]+"
                                               title="Username can only contain letters, numbers, and underscores"
                                               required>
                                        <label for="username">Username *</label>
                                        <div class="form-text">Letters, numbers, and underscores only</div>
                                    </div>
                                    
                                    <div class="form-floating">
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               placeholder="Password"
                                               minlength="10"
                                               required>
                                        <label for="password">Password *</label>
                                        <div class="password-strength" id="password-strength"></div>
                                        <div class="form-text">Minimum 10 characters required</div>
                                    </div>
                                    
                                    <div class="form-floating">
                                        <input type="password" 
                                               class="form-control" 
                                               id="password_confirm" 
                                               name="password_confirm" 
                                               placeholder="Confirm Password"
                                               required>
                                        <label for="password_confirm">Confirm Password *</label>
                                        <div id="password-match" class="form-text"></div>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input type="checkbox" class="form-check-input" id="terms" required>
                                        <label class="form-check-label" for="terms">
                                            I agree to the <a href="/terms.php" target="_blank">Terms of Service</a> 
                                            and <a href="/privacy.php" target="_blank">Privacy Policy</a> *
                                        </label>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg" id="register-btn">
                                            <i class="fas fa-user-plus"></i> Create Account
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                            
                            <hr>
                            <div class="text-center">
                                <p class="mb-0">
                                    Already have an account? 
                                    <a href="/login.php" class="text-decoration-none">Sign In</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const passwordConfirmInput = document.getElementById('password_confirm');
            const passwordStrength = document.getElementById('password-strength');
            const passwordMatch = document.getElementById('password-match');
            const registerBtn = document.getElementById('register-btn');
            
            // Password strength indicator
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = getPasswordStrength(password);
                
                passwordStrength.className = 'password-strength';
                if (password.length > 0) {
                    passwordStrength.classList.add('strength-' + strength.level);
                    passwordStrength.style.width = strength.score + '%';
                } else {
                    passwordStrength.style.width = '0%';
                }
            });
            
            // Password confirmation match
            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirm = passwordConfirmInput.value;
                
                if (confirm.length > 0) {
                    if (password === confirm) {
                        passwordMatch.innerHTML = '<span class="text-success"><i class="fas fa-check"></i> Passwords match</span>';
                        return true;
                    } else {
                        passwordMatch.innerHTML = '<span class="text-danger"><i class="fas fa-times"></i> Passwords do not match</span>';
                        return false;
                    }
                } else {
                    passwordMatch.innerHTML = '';
                    return false;
                }
            }
            
            passwordConfirmInput.addEventListener('input', checkPasswordMatch);
            passwordInput.addEventListener('input', checkPasswordMatch);
            
            // Form validation
            document.getElementById('register-form').addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirm = passwordConfirmInput.value;
                
                if (password.length < 10) {
                    e.preventDefault();
                    alert('Password must be at least 10 characters long');
                    return;
                }
                
                if (password !== confirm) {
                    e.preventDefault();
                    alert('Passwords do not match');
                    return;
                }
                
                registerBtn.disabled = true;
                registerBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
            });
            
            function getPasswordStrength(password) {
                let score = 0;
                let level = 'weak';
                
                // Length
                if (password.length >= 10) score += 25;
                if (password.length >= 12) score += 25;
                
                // Character types
                if (/[a-z]/.test(password)) score += 10;
                if (/[A-Z]/.test(password)) score += 10;
                if (/[0-9]/.test(password)) score += 10;
                if (/[^A-Za-z0-9]/.test(password)) score += 20;
                
                if (score >= 70) level = 'strong';
                else if (score >= 40) level = 'medium';
                
                return { score: Math.min(score, 100), level: level };
            }
        });
    </script>
</body>
</html>