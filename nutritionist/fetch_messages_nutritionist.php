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
        // Mensahe mula kay Nutritionist (Kanan / Teal bubble)
        echo '<div class="msg-wrapper nutritionist-row">';
        echo '  <div class="message nutritionist-bubble" style="background: #0d9488 !important; color: #ffffff !important;">';
        echo '    <div style="font-size: 14px; line-height: 1.5;">' . htmlspecialchars_decode($row['message'], ENT_QUOTES) . '</div>';
        echo '    <div class="msg-meta" style="color: rgba(255,255,255,0.8);">' . $formatted_time . '</div>';
        echo '  </div>';
        echo '</div>';
    } else {
        // Mensahe mula kay Client/User (Kaliwa / Gray bubble)
        echo '<div class="msg-wrapper user-row">';
        echo '  <div class="message user-bubble" style="background: #e9e9eb !important; color: #112d25 !important; border: 1px solid #dbece5;">';
        echo '    <div style="font-size: 14px; line-height: 1.5;">' . htmlspecialchars_decode($row['message'], ENT_QUOTES) . '</div>';
        echo '    <div class="msg-meta" style="color: #5c736c;">' . $formatted_time . '</div>';
        echo '  </div>';
        echo '</div>';
    }
}
?>