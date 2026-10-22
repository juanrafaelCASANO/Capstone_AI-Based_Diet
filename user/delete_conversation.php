<?php
session_start();
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $nutritionist_id = intval($_POST['nutritionist_id'] ?? 0);

    if ($user_id > 0 && $nutritionist_id > 0) {
        try {
            // Burahin ang lahat ng mensahe sa pagitan ng client at nutritionist na ito
            $stmt = $conn->prepare("
                DELETE FROM messages 
                WHERE (sender_id = ? AND receiver_id = ?) 
                   OR (sender_id = ? AND receiver_id = ?)
            ");
            
            if ($stmt->execute([$user_id, $nutritionist_id, $nutritionist_id, $user_id])) {
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