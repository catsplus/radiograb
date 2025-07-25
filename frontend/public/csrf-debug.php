<?php 
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSRF Debug Test</title>
</head>
<body>
    <h1>CSRF Token Debug Test</h1>
    
    <div id="result"></div>
    
    <button id="testBtn">Test CSRF Token</button>
    
    <script>
    document.getElementById('testBtn').addEventListener('click', async function() {
        const resultDiv = document.getElementById('result');
        resultDiv.innerHTML = '<p>Testing...</p>';
        
        try {
            // Step 1: Get CSRF token
            const tokenResponse = await fetch('/api/get-csrf-token.php');
            const tokenData = await tokenResponse.json();
            
            resultDiv.innerHTML += `<p><strong>Token Response:</strong> ${JSON.stringify(tokenData)}</p>`;
            
            // Step 2: Use token in test recording
            const testResponse = await fetch('/api/test-recording.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                credentials: 'same-origin',
                body: new URLSearchParams({
                    action: 'test_recording',
                    station_id: '1',
                    csrf_token: tokenData.csrf_token
                })
            });
            
            const testText = await testResponse.text();
            resultDiv.innerHTML += `<p><strong>Test Response Status:</strong> ${testResponse.status}</p>`;
            resultDiv.innerHTML += `<p><strong>Test Response:</strong> ${testText}</p>`;
            
            // Step 3: Debug info
            const debugResponse = await fetch('/api/debug-csrf.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                credentials: 'same-origin',
                body: new URLSearchParams({
                    action: 'debug',
                    csrf_token: tokenData.csrf_token
                })
            });
            
            const debugData = await debugResponse.json();
            resultDiv.innerHTML += `<p><strong>Debug Info:</strong> ${JSON.stringify(debugData, null, 2)}</p>`;
            
        } catch (error) {
            resultDiv.innerHTML += `<p><strong>Error:</strong> ${error.message}</p>`;
        }
    });
    </script>
</body>
</html>