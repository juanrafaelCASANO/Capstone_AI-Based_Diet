<?php
session_start();
require_once '../config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $sender_id = intval($_SESSION['user_id']); 
    $receiver_id = intval($_POST['receiver_id'] ?? 0); 
    $message = trim($_POST['message'] ?? '');

    if (!empty($message) && $sender_id > 0 &&$receiver_id > 0) {
        try {
            $stmt =$conn->prepare("
                INSERT INTO messages (sender_id, receiver_id, message, sender_type, created_at) 
                VALUES (?, ?, ?, 'nutritionist', CURRENT_TIMESTAMP)
            ");
            
            if ($stmt->execute([$sender_id, $receiver_id,$message])) {
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