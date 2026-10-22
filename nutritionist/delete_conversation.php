<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

$nutri_id = $_SESSION['user_id'];
$user_id = intval($_POST['user_id'] ?? 0);

if ($user_id > 0) {
    try {
        $del_stmt = $conn->prepare("
            DELETE FROM messages 
            WHERE (sender_id = ? AND receiver_id = ?) 
               OR (sender_id = ? AND receiver_id = ?)
        ");
        $del_stmt->execute([$nutri_id, $user_id, $user_id, $nutri_id]);
        echo 'success';
    } catch (Exception $e) {
        echo 'Database error: ' . $e->getMessage();
    }
} else {
    echo 'Invalid user ID';
}
?>