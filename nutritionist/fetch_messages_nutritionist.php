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

$query = "SELECT sender_id, receiver_id, message, sender_type, created_at 
          FROM messages 
          WHERE (sender_id = ? AND receiver_id = ?) 
             OR (sender_id = ? AND receiver_id = ?) 
          ORDER BY created_at ASC";

$stmt = $conn->prepare($query);
$stmt->execute([$nutri_id, $user_id, $user_id, $nutri_id]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Gamitin ang sender_type. Kung NULL pa yung sa luma, gagamitin ang fallback.
    $is_nutritionist = ($row['sender_type'] === 'nutritionist');
    
    if (empty($row['sender_type'])) {
        $is_nutritionist = ($row['sender_id'] == $nutri_id && strpos($row['message'], 'Kabi') === false);
    }

    $formatted_time = date('g:i A', strtotime($row['created_at']));

    if ($is_nutritionist) {
        echo '<div class="msg-wrapper nutritionist-row" style="display: flex; width: 100%; justify-content: flex-end; margin-bottom: 8px;">';
        echo '  <div class="message nutritionist-bubble" style="background: #0d9488 !important; color: #ffffff !important; padding: 12px 16px; border-radius: 18px; border-bottom-right-radius: 4px; max-width: 70%; word-break: break-word;">';
        echo '    <div style="font-size: 14px; line-height: 1.5;">' . htmlspecialchars_decode($row['message'], ENT_QUOTES) . '</div>';
        echo '    <div class="msg-meta" style="font-size: 10px; opacity: 0.8; margin-top: 4px; text-align: right; color: rgba(255,255,255,0.8);">' . $formatted_time . '</div>';
        echo '  </div>';
        echo '</div>';
    } else {
        echo '<div class="msg-wrapper user-row" style="display: flex; width: 100%; justify-content: flex-start; margin-bottom: 8px;">';
        echo '  <div class="message user-bubble" style="background: #e9e9eb !important; color: #112d25 !important; padding: 12px 16px; border-radius: 18px; border-bottom-left-radius: 4px; max-width: 70%; word-break: break-word; border: 1px solid #dbece5;">';
        echo '    <div style="font-size: 14px; line-height: 1.5;">' . htmlspecialchars_decode($row['message'], ENT_QUOTES) . '</div>';
        echo '    <div class="msg-meta" style="font-size: 10px; margin-top: 4px; text-align: right; color: #5c736c;">' . $formatted_time . '</div>';
        echo '  </div>';
        echo '</div>';
    }
}
?>