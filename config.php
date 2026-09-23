<?php

// ============================================================
// DATABASE CONFIGURATION
// ============================================================
$servername = "localhost"; //[cite: 5]
$username   = "postgres"; // Default user ng PostgreSQL
$password   = "aibased"; // Ilagay ang iyong PostgreSQL password dito
$dbname     = "ai_based_planner"; //[cite: 5]

try {
    $dsn = "pgsql:host=$servername;port=5432;dbname=$dbname;";
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    // Katumbas ito ng lumang set_charset("utf8mb4")[cite: 5]
    $conn->exec("SET NAMES 'UTF8'"); 
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage()); //[cite: 5]
}


// ============================================================
// FIREBASE 2FA / AUTH CONFIGURATION (Free Testing / Production)
// ============================================================
define('FIREBASE_API_KEY', 'AIzaSyAPNvRVv9SKnbYYRejVVyojN9AQtwdihIQ'); //[cite: 5]
define('FIREBASE_AUTH_DOMAIN', 'ai-based-diet.firebaseapp.com'); //[cite: 5]
define('FIREBASE_PROJECT_ID', 'ai-based-diet'); //[cite: 5]
define('FIREBASE_STORAGE_BUCKET', 'ai-based-diet.firebasestorage.app'); //[cite: 5]
define('FIREBASE_MESSAGING_SENDER_ID', '285856196933'); //[cite: 5]
define('FIREBASE_APP_ID', '1:285856196933:web:4cce0bcbd56aa4c8bf762c'); //[cite: 5]

?>