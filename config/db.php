<?php
// config/db.php

$host = 'localhost';
$dbname = 'ppa_db';           // we'll create this database soon
$username = 'root';
$password = '';                // default XAMPP – change later for security

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Database connected!"; // uncomment for testing
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}