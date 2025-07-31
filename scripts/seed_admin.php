<?php
require_once __DIR__ . '/../frontend/includes/database.php';

$username = 'admin';
$password = 'password'; // You should change this!
$password_hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
$stmt->execute([$username, $password_hash]);

echo "Admin user created successfully.\n";
?>