<?php
session_start();
require_once '../config.php';

// Para makita kung may error
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    // 1. Sender ay ang LOGGED-IN NUTRITIONIST
    $sender_id = intval($_SESSION['user_id']); 
    
    // 2. Receiver ay ang CLIENT/USER
    $receiver_id = intval($_POST['receiver_id'] ?? 0); 
    $message = trim($_POST['message'] ?? '');

    if (!empty($message) && $sender_id > 0 && $receiver_id > 0) {
        try {
            // Inalis na natin ang 'sender_type' at 'status' sa query na nagdudulot ng error
            $stmt = $conn->prepare("
                INSERT INTO messages (sender_id, receiver_id, message, created_at) 
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ");
            
            if ($stmt->execute([$sender_id, $receiver_id, $message])) {
                echo "success";
                exit;
            }
        } catch (PDOException $e) {
            echo "Database Error: " . $e->getMessage();
            exit;
        }
    }
}
echo "Invalid Request";
?>