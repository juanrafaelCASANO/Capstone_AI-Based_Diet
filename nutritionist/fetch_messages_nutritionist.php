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
        // Mensahe mula kay Nutritionist (Dapat ay nasa Kanan / Nutritionist bubble)[cite: 21]
        echo '<div class="msg-wrapper nutritionist-row" style="display: flex; width: 100%; justify-content: flex-end; margin-bottom: 4px;">';
        echo '  <div class="message nutritionist-bubble" style="background: #007aff; color: #ffffff; padding: 12px 16px; border-radius: 18px; border-bottom-right-radius: 4px; max-width: 70%; word-break: break-word;">';
        echo '    <div style="font-size: 14px; line-height: 1.5;">' . htmlspecialchars_decode($row['message'], ENT_QUOTES) . '</div>';
        echo '    <div class="msg-meta" style="font-size: 10px; opacity: 0.75; margin-top: 4px; text-align: right; color: rgba(255,255,255,0.8);">' . $formatted_time . '</div>';
        echo '  </div>';
        echo '</div>';
    } else {
        // Mensahe mula kay Client/User (Dapat ay nasa Kaliwa / User bubble)[cite: 21]
        echo '<div class="msg-wrapper user-row" style="display: flex; width: 100%; justify-content: flex-start; margin-bottom: 4px;">';
        echo '  <div class="message user-bubble" style="background: #e9e9eb; color: #000000; padding: 12px 16px; border-radius: 18px; border-bottom-left-radius: 4px; max-width: 70%; word-break: break-word;">';
        echo '    <div style="font-size: 14px; line-height: 1.5;">' . htmlspecialchars_decode($row['message'], ENT_QUOTES) . '</div>';
        echo '    <div class="msg-meta" style="font-size: 10px; opacity: 0.75; margin-top: 4px; text-align: right; color: #666;">' . $formatted_time . '</div>';
        echo '  </div>';
        echo '</div>';
    }
}
?>