<?php
// Supabase Production & Local Fallback Configuration
$host = getenv('DB_HOST') ?: "db.mbsjtesmrowfilvxcaqb.supabase.co";
$port = getenv('DB_PORT') ?: "5432";
$dbname = getenv('DB_NAME') ?: "postgres"; 
$user = getenv('DB_USER') ?: "postgres";
$password = getenv('DB_PASS') ?: "8-ps7u,_LDe,ppj"; // Ilagay dito ang iyong Supabase password

try {
    // Koneksyon gamit ang PDO para sa PostgreSQL na may SSL mode para sa Supabase
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

    $conn = new PDO($dsn, $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// ============================================================
// FIREBASE 2FA / AUTH CONFIGURATION
// ============================================================

define('FIREBASE_API_KEY', getenv('FIREBASE_API_KEY') ?: 'YOUR_FIREBASE_API_KEY');
define('FIREBASE_AUTH_DOMAIN', 'ai-based-diet.firebaseapp.com');
define('FIREBASE_PROJECT_ID', 'ai-based-diet');
define('FIREBASE_STORAGE_BUCKET', 'ai-based-diet.firebasestorage.app');
define('FIREBASE_MESSAGING_SENDER_ID', '285856196933');
define('FIREBASE_APP_ID', '1:285856196933:web:4cce0bcbd56aa4c8bf762c');

// ============================================================
// TEXTBEE SMS API CONFIGURATION
// ============================================================
define('TEXTBEE_API_KEY', getenv('TEXTBEE_API_KEY') ?: 'txb_3fsPevHJvqEIoVVr9cR2QmVxKsB4wRm4');
?>