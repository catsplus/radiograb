<?php
/**
 * RadioGrab Email Service with OAuth2 Support
 * Uses PHPMailer with Gmail OAuth2 authentication
 */

require_once __DIR__ . '/../../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class EmailService {
    private $mail;
    private $debug_enabled = false;
    
    public function __construct($debug = false) {
        $this->debug_enabled = $debug;
        $this->mail = new PHPMailer(true);
        $this->configureOAuth();
    }
    
    /**
     * Configure OAuth2 authentication for Gmail
     */
    private function configureOAuth() {
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
            $this->mail->SMTPAuth = true;
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = $_ENV['SMTP_PORT'] ?? 587;
            
            // Check if OAuth credentials are available
            if (!empty($_ENV['GMAIL_CLIENT_ID']) && 
                !empty($_ENV['GMAIL_CLIENT_SECRET']) && 
                !empty($_ENV['GMAIL_REFRESH_TOKEN'])) {
                
                // Use OAuth2 - set custom XOAUTH2 authentication
                $accessToken = $this->getAccessToken();
                if ($accessToken) {
                    // Custom OAuth2 implementation using SMTP authentication
                    $this->configureCustomOAuth($accessToken);
                } else {
                    throw new Exception('Failed to get OAuth2 access token');
                }
            } else {
                // Fallback to traditional authentication if available
                if (!empty($_ENV['SMTP_USERNAME']) && !empty($_ENV['SMTP_PASSWORD'])) {
                    $this->mail->Username = $_ENV['SMTP_USERNAME'];
                    $this->mail->Password = $_ENV['SMTP_PASSWORD'];
                } else {
                    throw new Exception('No email authentication configured');
                }
            }
            
            // Default sender
            $this->mail->setFrom($_ENV['SMTP_FROM'] ?? 'noreply@radiograb.svaha.com', 'RadioGrab');
            
            if ($this->debug_enabled) {
                $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $this->mail->Debugoutput = function($str, $level) {
                    error_log("PHPMailer Debug: $str");
                };
            }
            
        } catch (Exception $e) {
            error_log("EmailService configuration error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Configure custom OAuth2 authentication
     */
    private function configureCustomOAuth($accessToken) {
        // Override PHPMailer SMTP authentication to use OAuth2
        $this->mail->Username = $_ENV['SMTP_USERNAME'];
        $this->mail->Password = $accessToken;
        
        // Set custom authentication hook
        $xoauth2String = $this->buildXOAuth2String($_ENV['SMTP_USERNAME'], $accessToken);
        
        // Store for custom SMTP authentication
        $this->mail->oauth_token = $xoauth2String;
    }
    
    /**
     * Build XOAUTH2 authentication string
     */
    private function buildXOAuth2String($email, $accessToken) {
        return base64_encode("user=$email\001auth=Bearer $accessToken\001\001");
    }
    
    /**
     * Get access token using refresh token
     */
    private function getAccessToken() {
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        
        $postData = [
            'client_id' => $_ENV['GMAIL_CLIENT_ID'],
            'client_secret' => $_ENV['GMAIL_CLIENT_SECRET'],
            'refresh_token' => $_ENV['GMAIL_REFRESH_TOKEN'],
            'grant_type' => 'refresh_token'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['access_token'] ?? null;
        }
        
        error_log("OAuth token refresh failed: HTTP $httpCode - $response");
        return null;
    }
    
    /**
     * Send email with HTML support
     */
    public function sendEmail($to, $subject, $htmlBody, $plainBody = null) {
        try {
            // Recipients
            $this->mail->addAddress($to);
            
            // Content
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body = $htmlBody;
            
            if ($plainBody) {
                $this->mail->AltBody = $plainBody;
            } else {
                // Create plain text version from HTML
                $this->mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
            }
            
            $result = $this->mail->send();
            
            if ($this->debug_enabled) {
                error_log("Email sent successfully to: $to");
            }
            
            // Clear addresses for next email
            $this->mail->clearAddresses();
            
            return true;
            
        } catch (Exception $e) {
            error_log("Email send failed: " . $e->getMessage());
            if ($this->debug_enabled) {
                error_log("PHPMailer Error Info: " . $this->mail->ErrorInfo);
            }
            return false;
        }
    }
    
    /**
     * Test email configuration
     */
    public function testConfiguration() {
        try {
            // Test SMTP connection
            $this->mail->smtpConnect();
            $this->mail->smtpClose();
            return ['success' => true, 'message' => 'SMTP connection successful'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Check if OAuth2 is properly configured
     */
    public function isOAuthConfigured() {
        return !empty($_ENV['GMAIL_CLIENT_ID']) && 
               !empty($_ENV['GMAIL_CLIENT_SECRET']) && 
               !empty($_ENV['GMAIL_REFRESH_TOKEN']);
    }
    
    /**
     * Check if basic SMTP is configured
     */
    public function isSMTPConfigured() {
        return !empty($_ENV['SMTP_USERNAME']) && !empty($_ENV['SMTP_PASSWORD']);
    }
    
    /**
     * Get configuration status
     */
    public function getConfigurationStatus() {
        $status = [
            'oauth_configured' => $this->isOAuthConfigured(),
            'smtp_configured' => $this->isSMTPConfigured(),
            'any_configured' => $this->isOAuthConfigured() || $this->isSMTPConfigured()
        ];
        
        if ($status['oauth_configured']) {
            $status['auth_method'] = 'OAuth2';
        } elseif ($status['smtp_configured']) {
            $status['auth_method'] = 'SMTP';
        } else {
            $status['auth_method'] = 'None';
        }
        
        return $status;
    }
}