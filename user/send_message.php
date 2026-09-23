<?php
session_start();
require_once '../config.php';

$sender_id = $_POST['user_id'] ?? 0;
$receiver_id = $_POST['nutritionist_id'] ?? 0;
$message = trim($_POST['message'] ?? '');
$type = isset($_POST['type']) ? $_POST['type'] : 'user'; 

if(!empty($message) && $sender_id && $receiver_id) {
    // Kunin ang susunod na id at current timestamp para sa PostgreSQL compatibility
    $idStmt = $conn->query("SELECT COALESCE(MAX(id), 0) + 1 FROM messages");
    $nextId = $idStmt->fetchColumn();
    $current_time = date('Y-m-d H:i:s');

    // PDO Prepared Statement kasama ang id at created_at
    $stmt = $conn->prepare("INSERT INTO messages (id, sender_id, receiver_id, message, sender_type, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nextId, $sender_id, $receiver_id, $message, $type, $current_time]);
}
?>