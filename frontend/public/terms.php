<?php
/**
 * RadioGrab - Terms of Service
 * Issue #63 - Missing legal pages for registration form
 */

// Set page variables for shared template
$page_title = 'Terms of Service';
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
                        <li class="breadcrumb-item active">Terms of Service</li>
                    </ol>
                </nav>
                <h1><i class="fas fa-file-contract"></i> Terms of Service</h1>
                <p class="text-muted">Last updated: August 6, 2025</p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h2>1. Acceptance of Terms</h2>
                        <p>By accessing and using RadioGrab, you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by the above, please do not use this service.</p>

                        <h2>2. Description of Service</h2>
                        <p>RadioGrab is a radio recording and podcast generation system that allows users to:</p>
                        <ul>
                            <li>Record radio shows from publicly available internet streams</li>
                            <li>Generate RSS feeds from recorded content</li>
                            <li>Manage and organize radio recordings</li>
                            <li>Create playlists and upload audio content</li>
                        </ul>

                        <h2>3. User Responsibilities</h2>
                        <p>You are responsible for:</p>
                        <ul>
                            <li><strong>Legal Compliance:</strong> Ensuring all recordings comply with applicable copyright laws and broadcasting rights</li>
                            <li><strong>Personal Use:</strong> Using recorded content for personal, non-commercial purposes only unless you have appropriate licensing</li>
                            <li><strong>Content Ownership:</strong> Only uploading content you own or have permission to use</li>
                            <li><strong>Account Security:</strong> Maintaining the confidentiality of your account credentials</li>
                        </ul>

                        <h2>4. Copyright and Intellectual Property</h2>
                        <p>RadioGrab respects intellectual property rights. Users must:</p>
                        <ul>
                            <li>Only record content from publicly accessible radio streams</li>
                            <li>Respect copyright holders' rights and licensing terms</li>
                            <li>Use recordings for personal listening purposes only, unless otherwise licensed</li>
                            <li>Not distribute copyrighted material without proper authorization</li>
                        </ul>
                        
                        <div class="alert alert-warning">
                            <strong>Important:</strong> Recording radio broadcasts may be subject to copyright restrictions in your jurisdiction. Users are responsible for understanding and complying with applicable laws.
                        </div>

                        <h2>5. Prohibited Uses</h2>
                        <p>You may not use RadioGrab to:</p>
                        <ul>
                            <li>Violate any applicable laws or regulations</li>
                            <li>Infringe on copyrights or intellectual property rights</li>
                            <li>Redistribute copyrighted content without authorization</li>
                            <li>Attempt to gain unauthorized access to the system</li>
                            <li>Upload malicious content or malware</li>
                            <li>Use the service for commercial purposes without proper licensing</li>
                        </ul>

                        <h2>6. Content and Data</h2>
                        <p>Regarding your content and data:</p>
                        <ul>
                            <li>You retain ownership of content you upload or create</li>
                            <li>You grant RadioGrab permission to store and process your content for service operation</li>
                            <li>You are responsible for backing up important recordings</li>
                            <li>RadioGrab may delete inactive accounts or content per our retention policies</li>
                        </ul>

                        <h2>7. Privacy and Data Protection</h2>
                        <p>Your privacy is important to us. Please review our <a href="/privacy.php">Privacy Policy</a> to understand how we collect, use, and protect your information.</p>

                        <h2>8. Service Availability</h2>
                        <p>RadioGrab is provided "as is" and "as available." We do not guarantee:</p>
                        <ul>
                            <li>Uninterrupted service availability</li>
                            <li>Error-free operation</li>
                            <li>Compatibility with all radio streams</li>
                            <li>Permanent storage of recordings</li>
                        </ul>

                        <h2>9. Limitation of Liability</h2>
                        <p>RadioGrab and its operators shall not be liable for:</p>
                        <ul>
                            <li>Any direct, indirect, incidental, or consequential damages</li>
                            <li>Loss of data or recordings</li>
                            <li>Service interruptions or technical issues</li>
                            <li>Copyright infringement by users</li>
                        </ul>

                        <h2>10. Account Termination</h2>
                        <p>We reserve the right to terminate accounts that:</p>
                        <ul>
                            <li>Violate these terms of service</li>
                            <li>Engage in copyright infringement</li>
                            <li>Attempt to abuse or exploit the service</li>
                            <li>Remain inactive for extended periods</li>
                        </ul>

                        <h2>11. Changes to Terms</h2>
                        <p>We reserve the right to modify these terms at any time. Users will be notified of significant changes via email or through the service interface. Continued use constitutes acceptance of updated terms.</p>

                        <h2>12. Governing Law</h2>
                        <p>These terms shall be governed by and construed in accordance with applicable laws. Any disputes shall be resolved through appropriate legal channels.</p>

                        <h2>13. Contact Information</h2>
                        <p>For questions about these Terms of Service, please contact us through the RadioGrab support channels or via the contact information provided in the application.</p>

                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="text-muted">
                                <small>
                                    These terms are effective as of August 6, 2025 and apply to all users of the RadioGrab service.
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
                            <a class="nav-link" href="#acceptance">1. Acceptance of Terms</a>
                            <a class="nav-link" href="#service">2. Description of Service</a>
                            <a class="nav-link" href="#responsibilities">3. User Responsibilities</a>
                            <a class="nav-link" href="#copyright">4. Copyright & IP</a>
                            <a class="nav-link" href="#prohibited">5. Prohibited Uses</a>
                            <a class="nav-link" href="#content">6. Content and Data</a>
                            <a class="nav-link" href="#privacy">7. Privacy</a>
                            <a class="nav-link" href="#availability">8. Service Availability</a>
                            <a class="nav-link" href="#liability">9. Limitation of Liability</a>
                            <a class="nav-link" href="#termination">10. Account Termination</a>
                            <a class="nav-link" href="#changes">11. Changes to Terms</a>
                            <a class="nav-link" href="#law">12. Governing Law</a>
                            <a class="nav-link" href="#contact">13. Contact</a>
                        </nav>
                    </div>
                </div>

                <!-- Related Links -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-link"></i> Related</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="/privacy.php" class="btn btn-outline-primary">
                                <i class="fas fa-shield-alt"></i> Privacy Policy
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