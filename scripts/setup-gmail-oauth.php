<?php
/**
 * Gmail OAuth2 Setup Helper for RadioGrab
 * This script helps generate the necessary OAuth2 credentials for Gmail
 */

echo "Gmail OAuth2 Setup for RadioGrab\n";
echo "================================\n\n";

echo "To set up OAuth2 for Gmail, you need to:\n\n";

echo "1. Create a Google Cloud Project:\n";
echo "   - Go to https://console.cloud.google.com/\n";
echo "   - Create a new project or select existing one\n";
echo "   - Enable the Gmail API\n\n";

echo "2. Create OAuth2 Credentials:\n";
echo "   - Go to 'Credentials' in the Google Cloud Console\n";
echo "   - Click 'Create Credentials' → 'OAuth client ID'\n";
echo "   - Choose 'Web application'\n";
echo "   - Add authorized redirect URI: https://radiograb.svaha.com/oauth-callback\n";
echo "   - Save the Client ID and Client Secret\n\n";

echo "3. Get Refresh Token:\n";
echo "   Run this script to get the authorization URL:\n\n";

// Check if we have client credentials
if (empty($argv[1]) || empty($argv[2])) {
    echo "Usage: php setup-gmail-oauth.php CLIENT_ID CLIENT_SECRET\n\n";
    echo "Example:\n";
    echo "php setup-gmail-oauth.php 123456-abcdef.apps.googleusercontent.com your-client-secret\n\n";
    exit(1);
}

$clientId = $argv[1];
$clientSecret = $argv[2];
$redirectUri = 'https://radiograb.svaha.com/oauth-callback';
$scope = 'https://mail.google.com/';

// Generate authorization URL
$authUrl = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
    'response_type' => 'code',
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'scope' => $scope,
    'access_type' => 'offline',
    'prompt' => 'consent'
]);

echo "Step 1: Visit this URL to authorize the application:\n";
echo "$authUrl\n\n";

echo "Step 2: After authorization, you'll be redirected to:\n";
echo "https://radiograb.svaha.com/oauth-callback?code=AUTHORIZATION_CODE\n\n";

echo "Step 3: Copy the authorization code and run:\n";
echo "php get-refresh-token.php CLIENT_ID CLIENT_SECRET AUTHORIZATION_CODE\n\n";

// Create the callback handler
$callbackContent = '<?php
/**
 * OAuth2 Callback Handler for Gmail Setup
 */
if (isset($_GET["code"])) {
    $authCode = $_GET["code"];
    echo "<h1>Authorization Successful!</h1>";
    echo "<p>Authorization Code: <code>" . htmlspecialchars($authCode) . "</code></p>";
    echo "<p>Copy this code and use it with the get-refresh-token.php script.</p>";
} else {
    echo "<h1>Authorization Failed</h1>";
    echo "<p>No authorization code received.</p>";
}
?>';

file_put_contents(__DIR__ . '/../frontend/public/oauth-callback.php', $callbackContent);

echo "Created oauth-callback.php for handling the authorization response.\n\n";
echo "Next, create the refresh token script by running:\n";
echo "php create-refresh-token-script.php\n";