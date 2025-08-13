<?php
/**
 * Get Refresh Token for Gmail OAuth2
 * Usage: php get-refresh-token.php CLIENT_ID CLIENT_SECRET AUTHORIZATION_CODE
 */

if (count($argv) < 4) {
    echo "Usage: php get-refresh-token.php CLIENT_ID CLIENT_SECRET AUTHORIZATION_CODE\n";
    exit(1);
}

$clientId = $argv[1];
$clientSecret = $argv[2];
$authCode = $argv[3];
$redirectUri = 'https://radiograb.svaha.com/oauth-callback';

echo "Exchanging authorization code for refresh token...\n";

$tokenUrl = 'https://oauth2.googleapis.com/token';

$postData = [
    'code' => $authCode,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code'
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
    
    echo "Success! Here are your OAuth2 credentials:\n\n";
    echo "GMAIL_CLIENT_ID={$clientId}\n";
    echo "GMAIL_CLIENT_SECRET={$clientSecret}\n";
    echo "GMAIL_REFRESH_TOKEN={$data['refresh_token']}\n";
    echo "SMTP_USERNAME=your-gmail-address@gmail.com\n\n";
    
    echo "Add these to your .env file and restart the containers.\n";
    
    if (isset($data['access_token'])) {
        echo "\nTesting access token...\n";
        
        // Test the access token
        $testUrl = 'https://gmail.googleapis.com/gmail/v1/users/me/profile';
        $testCh = curl_init();
        curl_setopt($testCh, CURLOPT_URL, $testUrl);
        curl_setopt($testCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($testCh, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $data['access_token']
        ]);
        
        $testResponse = curl_exec($testCh);
        $testHttpCode = curl_getinfo($testCh, CURLINFO_HTTP_CODE);
        curl_close($testCh);
        
        if ($testHttpCode === 200) {
            $profile = json_decode($testResponse, true);
            echo "Gmail API test successful! Email: {$profile['emailAddress']}\n";
        } else {
            echo "Gmail API test failed. HTTP Code: $testHttpCode\n";
        }
    }
    
} else {
    echo "Failed to get refresh token. HTTP Code: $httpCode\n";
    echo "Response: $response\n";
}