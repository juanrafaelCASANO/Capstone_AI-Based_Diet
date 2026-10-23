<?php
// Supabase Production & Local Fallback Configuration[cite: 19]
$host = getenv('DB_HOST') ?: "db.mbsjtesmrowfilvxcaqb.supabase.co";[cite: 19]
$port = getenv('DB_PORT') ?: "5432";[cite: 19]
$dbname = getenv('DB_NAME') ?: "postgres";[cite: 19]
$user = getenv('DB_USER') ?: "postgres";[cite: 19]
$password = getenv('DB_PASS') ?: "8-ps7u,_LDe,ppj"; // Ilagay dito ang iyong Supabase password[cite: 19]

try {
    // Koneksyon gamit ang PDO para sa PostgreSQL na may SSL mode para sa Supabase[cite: 19]
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";[cite: 19]

    $conn = new PDO($dsn, $user, $password);[cite: 19]
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);[cite: 19]
    
    // Itakda ang timezone ng database sa Philippine Standard Time
    $conn->exec("SET timezone = 'Asia/Manila';");
    
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());[cite: 19]
}

// Itakda ang timezone ng PHP sa Philippine Standard Time
date_default_timezone_set('Asia/Manila');

// ============================================================
// FIREBASE 2FA / AUTH CONFIGURATION
// ============================================================

define('FIREBASE_API_KEY', getenv('FIREBASE_API_KEY') ?: 'YOUR_FIREBASE_API_KEY');[cite: 19]
define('FIREBASE_AUTH_DOMAIN', 'ai-based-diet.firebaseapp.com');[cite: 19]
define('FIREBASE_PROJECT_ID', 'ai-based-diet');[cite: 19]
define('FIREBASE_STORAGE_BUCKET', 'ai-based-diet.firebasestorage.app');[cite: 19]
define('FIREBASE_MESSAGING_SENDER_ID', '285856196933');[cite: 19]
define('FIREBASE_APP_ID', '1:285856196933:web:4cce0bcbd56aa4c8bf762c');[cite: 19]

// ============================================================
// TEXTBEE SMS API CONFIGURATION
// ============================================================
define('TEXTBEE_API_KEY', getenv('TEXTBEE_API_KEY') ?: 'txb_3fsPevHJvqEIoVVr9cR2QmVxKsB4wRm4');[cite: 19]
?>