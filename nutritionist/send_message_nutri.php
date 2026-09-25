<?php
session_start();
require_once '../config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $sender_id = intval($_SESSION['user_id']); // Nutritionist ID
    $receiver_id = intval($_POST['receiver_id'] ?? ($_POST['user_id'] ?? 0)); // Client User ID
    $message = trim($_POST['message'] ?? '');

    if (!empty($message) && $sender_id > 0 && $receiver_id > 0) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO messages (sender_id, receiver_id, message, sender_type, created_at, status) 
                VALUES (?, ?, ?, 'nutritionist', CURRENT_TIMESTAMP, 'unread')
            ");
            
            if ($stmt->execute([$sender_id, $receiver_id, $message])) {
                echo "success";
                exit;
            } else {
                echo "Failed to execute database query.";
                exit;
            }
        } catch (PDOException $e) {
            echo "Database Error: " . $e->getMessage();
            exit;
        }
    } else {
        echo "Empty message or invalid receiver ID.";
        exit;
    }
}

echo "Invalid Request";
?>