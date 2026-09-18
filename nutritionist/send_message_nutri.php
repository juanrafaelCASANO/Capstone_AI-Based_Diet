<?php
session_start();
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    $sender_id = $_SESSION['user_id'];
    $receiver_id = intval($_POST['receiver_id']);
    // real_escape_string prevents SQL injection
    $message = $conn->real_escape_string($_POST['message']);

    $sql = "INSERT INTO messages (sender_id, receiver_id, message) 
            VALUES ($sender_id, $receiver_id, '$message')";
    
    if ($conn->query($sql)) {
        // Redirect back to the chat room after sending
        header("Location: nutritionist_chat.php?user_id=" . $receiver_id);
        exit;
    } else {
        echo "Database Error: " . $conn->error;
    }
} else {
    echo "Invalid Request or Session Expired.";
}
?>