<?php
require_once __DIR__ . '/../frontend/includes/database.php';

// Ensure $pdo is available
global $pdo;

$username = 'admin';
$password = 'password'; // You should change this!
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Check if user already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);
if ($stmt->fetch()) {
    echo "Admin user already exists.\n";
    exit;
}

$stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
$stmt->execute([$username, $password_hash]);

echo "Admin user created successfully.\n";
?>