<?php

// ============================================================
// DATABASE CONFIGURATION
// ============================================================

$servername = "localhost";
$username   = "postgres";
$password   = "aibased";
$dbname     = "postgres";

try {
    $dsn = "pgsql:host=$servername;port=5432;dbname=$dbname";

    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}


// ============================================================
// FIREBASE 2FA / AUTH CONFIGURATION
// ============================================================

define('FIREBASE_API_KEY', 'YOUR_FIREBASE_API_KEY');
define('FIREBASE_AUTH_DOMAIN', 'ai-based-diet.firebaseapp.com');
define('FIREBASE_PROJECT_ID', 'ai-based-diet');
define('FIREBASE_STORAGE_BUCKET', 'ai-based-diet.firebasestorage.app');
define('FIREBASE_MESSAGING_SENDER_ID', '285856196933');
define('FIREBASE_APP_ID', '1:285856196933:web:4cce0bcbd56aa4c8bf762c');

?>