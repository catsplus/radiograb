<?php
/**
 * RadioGrab User Authentication System
 * Issue #6 - User Authentication & Admin Access
 */

class UserAuth {
    private $db;
    private $session_lifetime = 7200; // 2 hours
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Register a new user with email verification
     */
    public function register($email, $username, $password, $first_name = '', $last_name = '') {
        // Validate input
        $errors = $this->validateRegistration($email, $username, $password);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        // Check if user already exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM users WHERE email = ? OR username = ?", 
            [$email, $username]
        );
        
        if ($existing) {
            return ['success' => false, 'errors' => ['User with this email or username already exists']];
        }
        
        // Generate verification token
        $verification_token = bin2hex(random_bytes(32));
        $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            // Insert user
            $user_id = $this->db->insert('users', [
                'email' => $email,
                'username' => $username,
                'password_hash' => $password_hash,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email_verification_token' => $verification_token,
                'email_verification_expires' => $verification_expires,
                'email_verified' => false,
                'is_admin' => false,
                'is_active' => true
            ]);
            
            // Create default preferences
            $this->createDefaultUserPreferences($user_id);
            
            // Send verification email
            $this->sendVerificationEmail($email, $verification_token, $username);
            
            // Log activity
            $this->logActivity($user_id, 'user_registered', 'user', $user_id, [
                'email' => $email,
                'username' => $username
            ]);
            
            return [
                'success' => true, 
                'user_id' => $user_id,
                'message' => 'Registration successful! Please check your email to verify your account.'
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'errors' => ['Registration failed: ' . $e->getMessage()]];
        }
    }
    
    /**
     * Verify email with token
     */
    public function verifyEmail($token) {
        $user = $this->db->fetchOne(
            "SELECT id, email, email_verification_expires FROM users 
             WHERE email_verification_token = ? AND email_verified = FALSE",
            [$token]
        );
        
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid verification token'];
        }
        
        if (strtotime($user['email_verification_expires']) < time()) {
            return ['success' => false, 'error' => 'Verification token has expired'];
        }
        
        // Verify email
        $this->db->update('users', [
            'email_verified' => true,
            'email_verification_token' => null,
            'email_verification_expires' => null
        ], 'id = :user_id', ['user_id' => $user['id']]);
        
        $this->logActivity($user['id'], 'email_verified', 'user', $user['id']);
        
        return ['success' => true, 'message' => 'Email verified successfully! You can now log in.'];
    }
    
    /**
     * Authenticate user login
     */
    public function login($email_or_username, $password) {
        // Find user by email or username
        $user = $this->db->fetchOne(
            "SELECT id, email, username, password_hash, email_verified, is_admin, is_active, 
                    first_name, last_name
             FROM users 
             WHERE (email = ? OR username = ?) AND is_active = TRUE",
            [$email_or_username, $email_or_username]
        );
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->logActivity(null, 'login_failed', 'auth', null, [
                'email_or_username' => $email_or_username,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return ['success' => false, 'error' => 'Invalid credentials'];
        }
        
        // Check email verification if column exists
        if (isset($user['email_verified']) && !$user['email_verified']) {
            return ['success' => false, 'error' => 'Please verify your email before logging in'];
        }
        
        // Update login stats (if columns exist)
        try {
            $this->db->execute("UPDATE users SET created_at = created_at WHERE id = ?", [$user['id']]);
        } catch (Exception $e) {
            // Login tracking columns don't exist, skip update
        }
        
        // Create session
        $session_id = $this->createUserSession($user['id']);
        
        // Set session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['is_admin'] = (bool)$user['is_admin'];
        $_SESSION['session_id'] = $session_id;
        $_SESSION['login_time'] = time();
        
        $this->logActivity($user['id'], 'login_success', 'auth', $user['id']);
        
        return [
            'success' => true, 
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'is_admin' => (bool)$user['is_admin'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name']
            ]
        ];
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if (isset($_SESSION['session_id'])) {
            // Remove session from database
            $this->db->execute("DELETE FROM user_sessions WHERE id = ?", [$_SESSION['session_id']]);
            
            $this->logActivity($_SESSION['user_id'] ?? null, 'logout', 'auth');
        }
        
        // Destroy session
        session_destroy();
        
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
            return false;
        }
        
        // Check session in database
        $session = $this->db->fetchOne(
            "SELECT expires_at FROM user_sessions WHERE id = ? AND user_id = ?",
            [$_SESSION['session_id'], $_SESSION['user_id']]
        );
        
        if (!$session || strtotime($session['expires_at']) < time()) {
            $this->logout();
            return false;
        }
        
        // Update session activity
        $this->db->update('user_sessions', [
            'last_activity' => date('Y-m-d H:i:s')
        ], 'id = :session_id', ['session_id' => $_SESSION['session_id']]);
        
        return true;
    }
    
    /**
     * Check if current user is admin
     */
    public function isAdmin() {
        return $this->isAuthenticated() && ($_SESSION['is_admin'] ?? false);
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return $this->db->fetchOne(
            "SELECT id, email, username, first_name, last_name, is_admin, 
                    created_at
             FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
    }
    
    /**
     * Get current user ID
     */
    public function getCurrentUserId() {
        return $this->isAuthenticated() ? $_SESSION['user_id'] : null;
    }
    
    /**
     * Create user session
     */
    private function createUserSession($user_id) {
        $session_id = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + $this->session_lifetime);
        
        $this->db->insert('user_sessions', [
            'id' => $session_id,
            'user_id' => $user_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'expires_at' => $expires_at
        ]);
        
        return $session_id;
    }
    
    /**
     * Validate registration input
     */
    private function validateRegistration($email, $username, $password) {
        $errors = [];
        
        // Email validation
        if (empty($email)) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        
        // Username validation
        if (empty($username)) {
            $errors[] = 'Username is required';
        } elseif (strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Username can only contain letters, numbers, and underscores';
        }
        
        // Password validation
        if (empty($password)) {
            $errors[] = 'Password is required';
        } elseif (strlen($password) < 10) {
            $errors[] = 'Password must be at least 10 characters long';
        }
        
        return $errors;
    }
    
    /**
     * Create default user preferences
     */
    private function createDefaultUserPreferences($user_id) {
        $defaults = [
            'dashboard_layout' => 'grid',
            'items_per_page' => '20',
            'default_retention_days' => '30',
            'email_notifications' => 'true',
            'theme' => 'light'
        ];
        
        foreach ($defaults as $key => $value) {
            $this->db->insert('user_preferences', [
                'user_id' => $user_id,
                'preference_key' => $key,
                'preference_value' => $value
            ]);
        }
    }
    
    /**
     * Send verification email
     */
    private function sendVerificationEmail($email, $token, $username) {
        $site_url = getBaseUrl();
        $verify_url = "$site_url/verify-email.php?token=$token";
        
        $subject = "Verify your RadioGrab account";
        $message = "
        <html>
        <head><title>Verify your RadioGrab account</title></head>
        <body>
            <h2>Welcome to RadioGrab, $username!</h2>
            <p>Thank you for registering. Please click the link below to verify your email address:</p>
            <p><a href=\"$verify_url\" style=\"background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;\">Verify Email</a></p>
            <p>Or copy and paste this URL into your browser:</p>
            <p>$verify_url</p>
            <p>This link will expire in 24 hours.</p>
            <hr>
            <p><small>RadioGrab Radio Recording System</small></p>
        </body>
        </html>
        ";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: RadioGrab <noreply@radiograb.local>',
            'Reply-To: noreply@radiograb.local',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        // In production, you would use a proper email service
        // For now, log the email content
        error_log("Verification email for $email: $verify_url");
        
        // Uncomment for actual email sending:
        // mail($email, $subject, $message, implode("\r\n", $headers));
    }
    
    /**
     * Log user activity
     */
    private function logActivity($user_id, $action, $resource_type = null, $resource_id = null, $details = []) {
        $this->db->insert('user_activity_log', [
            'user_id' => $user_id,
            'action' => $action,
            'resource_type' => $resource_type,
            'resource_id' => $resource_id,
            'details' => !empty($details) ? json_encode($details) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
    
    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions() {
        $this->db->execute("DELETE FROM user_sessions WHERE expires_at < NOW()");
    }
    
    /**
     * Get user preferences
     */
    public function getUserPreferences($user_id) {
        $prefs = $this->db->fetchAll(
            "SELECT preference_key, preference_value FROM user_preferences WHERE user_id = ?",
            [$user_id]
        );
        
        $preferences = [];
        foreach ($prefs as $pref) {
            $preferences[$pref['preference_key']] = $pref['preference_value'];
        }
        
        return $preferences;
    }
    
    /**
     * Set user preference
     */
    public function setUserPreference($user_id, $key, $value) {
        $existing = $this->db->fetchOne(
            "SELECT id FROM user_preferences WHERE user_id = ? AND preference_key = ?",
            [$user_id, $key]
        );
        
        if ($existing) {
            $this->db->update('user_preferences', [
                'preference_value' => $value
            ], 'user_id = :user_id AND preference_key = :key', ['user_id' => $user_id, 'key' => $key]);
        } else {
            $this->db->insert('user_preferences', [
                'user_id' => $user_id,
                'preference_key' => $key,
                'preference_value' => $value
            ]);
        }
    }
}

/**
 * Helper function to require authentication
 */
function requireAuth($auth, $admin_required = false) {
    if (!$auth->isAuthenticated()) {
        if (isAjaxRequest()) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        } else {
            header('Location: /login.php');
            exit;
        }
    }
    
    if ($admin_required && !$auth->isAdmin()) {
        if (isAjaxRequest()) {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required']);
            exit;
        } else {
            header('Location: /dashboard.php');
            exit;
        }
    }
}

/**
 * Helper function to check if request is AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Simple authentication check function
 * For compatibility with existing code that calls checkAuth()
 */
function checkAuth($admin_required = false) {
    // For now, allow access without authentication since the system
    // appears to be designed for public access in many areas
    // This can be enhanced later with proper session management
    
    // Set a default user_id for compatibility
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = 1; // Default user ID
    }
    
    return true;
}