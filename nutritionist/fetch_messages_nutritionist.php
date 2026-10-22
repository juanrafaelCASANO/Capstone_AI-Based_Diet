<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized access');
}

$nutri_id = (int)$_SESSION['user_id'];
$user_id = (int)($_POST['user_id'] ?? 0);

if (!$user_id || !$nutri_id) {
    exit('Missing IDs');
}

$query = "SELECT sender_id, receiver_id, message, created_at 
          FROM messages 
          WHERE (sender_id = ? AND receiver_id = ?) 
             OR (sender_id = ? AND receiver_id = ?) 
          ORDER BY created_at ASC";

$stmt = $conn->prepare($query);
$stmt->execute([$nutri_id, $user_id, $user_id, $nutri_id]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sender = (int)$row['sender_id'];
    $is_nutritionist = ($sender === $nutri_id);
    $formatted_time = date('g:i A', strtotime($row['created_at']));

    if ($is_nutritionist) {
        // Mensahe mula kay Nutritionist (Right side) - Gumagamit din ng htmlspecialchars_decode kung sakaling nag-send ang nutritionist ng card
        echo '<div style="display: flex; width: 100%; justify-content: flex-end; margin-bottom: 6px;">';
        echo '  <div style="background: #2d7a5d; color: #ffffff; padding: 10px 16px; border-radius: 16px; border-bottom-right-radius: 4px; max-width: 70%; word-break: break-word;">';
        echo '    <div style="font-size: 14px; line-height: 1.4;">' . htmlspecialchars_decode($row['message'], ENT_QUOTES) . '</div>';
        echo '    <div style="font-size: 10px; opacity: 0.8; margin-top: 4px; text-align: right;">' . $formatted_time . '</div>';
        echo '  </div>';
        echo '</div>';
    } else {
        // Mensahe mula kay Client (Left side) - HTML Card Render
        echo '<div style="display: flex; width: 100%; justify-content: flex-start; margin-bottom: 6px;">';
        echo '  <div style="background: #ffffff; color: #112d25; padding: 10px 16px; border-radius: 16px; border-bottom-left-radius: 4px; max-width: 70%; word-break: break-word; border: 1px solid #dbece5;">';
        echo '    <div style="font-size: 14px; line-height: 1.4;">' . htmlspecialchars_decode($row['message'], ENT_QUOTES) . '</div>';
        echo '    <div style="font-size: 10px; color: #5c736c; margin-top: 4px; text-align: right;">' . $formatted_time . '</div>';
        echo '  </div>';
        echo '</div>';
    }
}
?>