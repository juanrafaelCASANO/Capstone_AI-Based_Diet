<?php
session_start();
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    $sender_id = $_SESSION['user_id'];
    $receiver_id = intval($_POST['receiver_id']);
    $message = trim($_POST['message']);

    if (!empty($message)) {
        // Include created_at column explicitly with NOW()
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
        
        if ($stmt->execute([$sender_id, $receiver_id, $message])) {
            header("Location: nutritionist_chat.php?user_id=" . $receiver_id);
            exit;
        } else {
            echo "Database Error occurred.";
        }
    } else {
        header("Location: nutritionist_chat.php?user_id=" . $receiver_id);
        exit;
    }
} else {
    echo "Invalid Request or Session Expired.";
}
?>