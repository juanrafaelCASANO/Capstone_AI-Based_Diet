<?php
session_start();
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $nutritionist_id = (int)($_POST['nutritionist_id'] ?? 0);

    if ($user_id > 0 && $nutritionist_id > 0) {
        try {
            // Mark unread messages sent TO this user FROM the selected nutritionist as read
            $stmt = $conn->prepare("
                UPDATE messages 
                SET status = 1 
                WHERE sender_id = ? AND receiver_id = ? AND status = 0
            ");
            if ($stmt->execute([$nutritionist_id, $user_id])) {
                echo "success";
                exit;
            }
        } catch (Exception $e) {
            echo "error";
            exit;
        }
    }
}
echo "invalid";
?>