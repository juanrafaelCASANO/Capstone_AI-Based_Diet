<?php
$host = "localhost";
$port = "5432";
$dbname = "postgres"; // Pangalan ng database mo
$user = "postgres";
$password = "aibased"; // Password mo sa pgAdmin

try {
    // Gumagamit ito ng PDO para pareho ng format sa MySQL niyo dati
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Pwede mo itong burahin kapag okay na
    // echo "Connected na sa PostgreSQL gamit ang PDO!"; 
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