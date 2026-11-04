<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized access');
}

$user_id = intval($_SESSION['user_id']);
$nutri_id = intval($_POST['nutritionist_id'] ?? ($_POST['user_id'] ?? 0));

if (!$user_id || !$nutri_id) {
    exit();
}

$query = "SELECT sender_id, receiver_id, message, created_at 
          FROM messages 
          WHERE (sender_id = ? AND receiver_id = ?) 
             OR (sender_id = ? AND receiver_id = ?) 
          ORDER BY created_at ASC";

$stmt = $conn->prepare($query);
$stmt->execute([$user_id, $nutri_id, $nutri_id, $user_id]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sender = (int)$row['sender_id'];
    
    // Suriin kung ang nagpadala ay ang mismong user/client na naka-log in
    $is_user = ($sender === $user_id);
    $formatted_time = date('g:i A', strtotime($row['created_at']));
    
    $raw_message = htmlspecialchars_decode($row['message'], ENT_QUOTES);
    if ($is_user) {
        $raw_message = preg_replace('/<a\s+href="nutritionist_edit_plan\.php[^>]*>.*?<\/a>/i', '', $raw_message);
    }
    $message_content = nl2br($raw_message);

    if ($is_user) {
        // KANAN: Sariling mensahe ng User[cite: 27]
        echo '<div style="display: flex; width: 100%; justify-content: flex-end; margin-bottom: 8px;">';
        echo '  <div style="background: #0d9488; color: #ffffff; padding: 12px 16px; border-radius: 18px; border-bottom-right-radius: 4px; max-width: 70%; word-break: break-word;">';
        echo '    <div style="font-size: 14px; line-height: 1.5;">' . $message_content . '</div>';
        echo '    <div style="font-size: 10px; opacity: 0.8; margin-top: 4px; text-align: right;">' . $formatted_time . '</div>';
        echo '  </div>';
        echo '</div>';
    } else {
        // KALIWA: Mensahe mula sa Nutritionist o Kabi (AI)[cite: 27]
        echo '<div style="display: flex; width: 100%; justify-content: flex-start; margin-bottom: 8px;">';
        echo '  <div style="background: #ffffff; color: #112d25; padding: 12px 16px; border-radius: 18px; border-bottom-left-radius: 4px; max-width: 70%; word-break: break-word; border: 1px solid #dbece5;">';
        echo '    <div style="font-size: 14px; line-height: 1.5;">' . $message_content . '</div>';
        echo '    <div style="font-size: 10px; color: #5c736c; margin-top: 4px; text-align: right;">' . $formatted_time . '</div>';
        echo '  </div>';
        echo '</div>';
    }
}
?>