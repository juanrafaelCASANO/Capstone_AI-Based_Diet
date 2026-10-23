<?php
session_start();
require_once '../config.php';

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'nutritionist') {
    $nutri_id = $_SESSION['user_id'];
    try {
        $stmt = $conn->prepare("UPDATE nutritionist SET last_active_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$nutri_id]);
        echo "success";
    } catch (Exception $e) {
        echo "error";
    }
}
?>