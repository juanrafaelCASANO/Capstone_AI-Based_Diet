<?php
session_start();
require_once '../config.php';

$sender_id = $_POST['user_id'] ?? 0;
$receiver_id = $_POST['nutritionist_id'] ?? 0;
$message = trim($_POST['message'] ?? '');
$type = $_POST['type'] ?? 'user'; 

if (!empty($message) && $sender_id && $receiver_id) {
    // Let PostgreSQL automatically generate the primary key and timestamp
    $stmt = $conn->prepare("
        INSERT INTO messages (sender_id, receiver_id, message, sender_type, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$sender_id, $receiver_id, $message, $type]);
}
?>