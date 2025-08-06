<?php
/**
 * RadioGrab - Privacy Policy
 * Issue #63 - Missing legal pages for registration form
 */

// Set page variables for shared template
$page_title = 'Privacy Policy';
$active_nav = '';

require_once '../includes/header.php';
?>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/">Home</a></li>
                        <li class="breadcrumb-item active">Privacy Policy</li>
                    </ol>
                </nav>
                <h1><i class="fas fa-shield-alt"></i> Privacy Policy</h1>
                <p class="text-muted">Last updated: August 6, 2025</p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h2>1. Information We Collect</h2>
                        
                        <h3>Account Information</h3>
                        <p>When you create a RadioGrab account, we collect:</p>
                        <ul>
                            <li><strong>Personal Details:</strong> Name, email address, username</li>
                            <li><strong>Authentication Data:</strong> Encrypted password, login timestamps</li>
                            <li><strong>Profile Information:</strong> Any additional profile details you choose to provide</li>
                        </ul>

                        <h3>Usage Information</h3>
                        <p>To provide and improve our service, we collect:</p>
                        <ul>
                            <li><strong>Service Usage:</strong> Stations added, shows recorded, settings configured</li>
                            <li><strong>System Logs:</strong> Error logs, performance metrics, system diagnostics</li>
                            <li><strong>Technical Data:</strong> IP addresses, browser type, device information</li>
                            <li><strong>Content Data:</strong> Recordings, playlists, and metadata you create</li>
                        </ul>

                        <h2>2. How We Use Your Information</h2>
                        
                        <p>We use your information to:</p>
                        <ul>
                            <li><strong>Provide Service:</strong> Enable radio recording, playlist management, and RSS feeds</li>
                            <li><strong>Account Management:</strong> Authenticate users, manage preferences, and provide support</li>
                            <li><strong>Service Improvement:</strong> Monitor performance, fix bugs, and develop new features</li>
                            <li><strong>Security:</strong> Protect against unauthorized access and abuse</li>
                            <li><strong>Communication:</strong> Send service updates, security notices, and support messages</li>
                        </ul>

                        <div class="alert alert-info">
                            <strong>Note:</strong> We do not sell, rent, or share your personal information with third parties for marketing purposes.
                        </div>

                        <h2>3. Data Storage and Security</h2>
                        
                        <h3>Data Storage</h3>
                        <p>Your data is stored:</p>
                        <ul>
                            <li><strong>Locally:</strong> On the RadioGrab server infrastructure</li>
                            <li><strong>Cloud Storage:</strong> Audio files may be stored in cloud storage services (when configured)</li>
                            <li><strong>Backups:</strong> Regular backups are maintained for data protection</li>
                        </ul>

                        <h3>Security Measures</h3>
                        <p>We protect your data through:</p>
                        <ul>
                            <li><strong>Encryption:</strong> Passwords are encrypted using industry-standard methods</li>
                            <li><strong>Access Control:</strong> Restricted access to user data and systems</li>
                            <li><strong>HTTPS:</strong> All data transmission is encrypted via SSL/TLS</li>
                            <li><strong>Regular Updates:</strong> Security patches and system updates</li>
                        </ul>

                        <h2>4. Data Retention</h2>
                        <p>We retain your data as follows:</p>
                        <ul>
                            <li><strong>Account Data:</strong> Retained while your account is active</li>
                            <li><strong>Recordings:</strong> Subject to retention policies you configure (days, weeks, months, or indefinite)</li>
                            <li><strong>System Logs:</strong> Typically retained for 30-90 days for troubleshooting</li>
                            <li><strong>Inactive Accounts:</strong> May be deleted after extended periods of inactivity</li>
                        </ul>

                        <h2>5. Your Data Rights</h2>
                        
                        <p>You have the right to:</p>
                        <ul>
                            <li><strong>Access:</strong> Request a copy of your personal data</li>
                            <li><strong>Correction:</strong> Update or correct your account information</li>
                            <li><strong>Deletion:</strong> Request deletion of your account and associated data</li>
                            <li><strong>Export:</strong> Download your recordings and data</li>
                            <li><strong>Control:</strong> Manage privacy settings and data sharing preferences</li>
                        </ul>

                        <div class="alert alert-success">
                            <strong>Self-Service:</strong> Most data management can be done directly through your RadioGrab account settings.
                        </div>

                        <h2>6. Cookies and Tracking</h2>
                        
                        <h3>Essential Cookies</h3>
                        <p>We use cookies that are essential for service operation:</p>
                        <ul>
                            <li><strong>Session Cookies:</strong> Maintain your login session</li>
                            <li><strong>Security Cookies:</strong> CSRF protection and security measures</li>
                            <li><strong>Preference Cookies:</strong> Remember your settings and preferences</li>
                        </ul>

                        <h3>Optional Tracking</h3>
                        <p>RadioGrab does not use:</p>
                        <ul>
                            <li>Third-party analytics tracking</li>
                            <li>Advertising cookies</li>
                            <li>Social media tracking pixels</li>
                            <li>Cross-site tracking mechanisms</li>
                        </ul>

                        <h2>7. Third-Party Services</h2>
                        
                        <p>RadioGrab may interact with third-party services:</p>
                        <ul>
                            <li><strong>Radio Streams:</strong> Connect to public radio station streams</li>
                            <li><strong>Cloud Storage:</strong> Optional integration with storage providers</li>
                            <li><strong>Email Service:</strong> For account verification and notifications</li>
                            <li><strong>CDN Services:</strong> For faster content delivery</li>
                        </ul>

                        <p>Each third-party service has its own privacy policy and data handling practices.</p>

                        <h2>8. Children's Privacy</h2>
                        <p>RadioGrab is not intended for children under 13. We do not knowingly collect personal information from children under 13. If we become aware of such data collection, we will take steps to delete the information promptly.</p>

                        <h2>9. International Users</h2>
                        <p>RadioGrab may be accessed by users worldwide. By using the service, you consent to the transfer and processing of your information in accordance with this privacy policy, regardless of your location.</p>

                        <h2>10. Data Breaches</h2>
                        <p>In the unlikely event of a data breach:</p>
                        <ul>
                            <li>We will investigate and assess the impact immediately</li>
                            <li>Affected users will be notified promptly</li>
                            <li>Appropriate authorities will be notified if required</li>
                            <li>Steps will be taken to prevent future incidents</li>
                        </ul>

                        <h2>11. Changes to This Policy</h2>
                        <p>We may update this privacy policy periodically. When we do:</p>
                        <ul>
                            <li>The updated date will be revised</li>
                            <li>Users will be notified of material changes via email or service notification</li>
                            <li>Continued use of the service constitutes acceptance of updated terms</li>
                        </ul>

                        <h2>12. Contact Us</h2>
                        <p>For privacy-related questions or requests:</p>
                        <ul>
                            <li>Use the contact form within RadioGrab</li>
                            <li>Email us at the support address provided in the application</li>
                            <li>Submit a support ticket through the RadioGrab interface</li>
                        </ul>

                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="text-muted">
                                <small>
                                    This privacy policy is effective as of August 6, 2025 and applies to all users of RadioGrab.
                                </small>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Table of Contents -->
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Table of Contents</h5>
                    </div>
                    <div class="card-body">
                        <nav class="nav flex-column">
                            <a class="nav-link" href="#collect">1. Information We Collect</a>
                            <a class="nav-link" href="#use">2. How We Use Your Information</a>
                            <a class="nav-link" href="#storage">3. Data Storage & Security</a>
                            <a class="nav-link" href="#retention">4. Data Retention</a>
                            <a class="nav-link" href="#rights">5. Your Data Rights</a>
                            <a class="nav-link" href="#cookies">6. Cookies & Tracking</a>
                            <a class="nav-link" href="#third-party">7. Third-Party Services</a>
                            <a class="nav-link" href="#children">8. Children's Privacy</a>
                            <a class="nav-link" href="#international">9. International Users</a>
                            <a class="nav-link" href="#breaches">10. Data Breaches</a>
                            <a class="nav-link" href="#changes">11. Policy Changes</a>
                            <a class="nav-link" href="#contact">12. Contact Us</a>
                        </nav>
                    </div>
                </div>

                <!-- Privacy Summary -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-key"></i> Privacy Summary</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i>
                                No third-party tracking
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i>
                                No data selling or sharing
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i>
                                Strong encryption & security
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i>
                                You control your data
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-check text-success"></i>
                                Transparent data practices
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Related Links -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-link"></i> Related</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="/terms.php" class="btn btn-outline-primary">
                                <i class="fas fa-file-contract"></i> Terms of Service
                            </a>
                            <a href="/register.php" class="btn btn-outline-success">
                                <i class="fas fa-user-plus"></i> Create Account
                            </a>
                            <a href="/login.php" class="btn btn-outline-info">
                                <i class="fas fa-sign-in-alt"></i> Sign In
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php require_once '../includes/footer.php'; ?>