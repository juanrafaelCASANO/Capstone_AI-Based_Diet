<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized access');
}

$user_id = intval($_POST['user_id'] ?? $_SESSION['user_id']);
$nutri_id = intval($_POST['nutritionist_id'] ?? 0);

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
    // Piliting ilagay sa kaliwa (nutritionist-msg) kung ang mensahe ay galing kay AI Assistant
    $is_ai = (strpos($row['message'], 'Kabi') !== false || strpos($row['message'], '🤖') !== false);

    if ($is_ai) {
        $class = 'nutritionist-msg';
    } else {
        $class = ($row['sender_id'] == $user_id) ? 'user-msg' : 'nutritionist-msg';
    }
    
    $raw_message = htmlspecialchars_decode($row['message'], ENT_QUOTES);

    // KUNG ANG USER ANG NAGBASA NG CHAT: Alisin ang "Edit Plan" button sa HTML card para hindi niya ito makita o ma-edit
    if ($row['sender_id'] == $user_id && !$is_ai) {
        // Tinatanggal ang link patungo sa nutritionist_edit_plan.php para sa panig ng client
        $raw_message = preg_replace('/<a\s+href="nutritionist_edit_plan\.php[^>]*>.*?<\/a>/i', '', $raw_message);
    }

    $message_content = nl2br($raw_message);
    $time_formatted = date('g:i A', strtotime($row['created_at']));

    echo '<div class="message ' . $class . '">';
    echo $message_content;
    echo '<div style="font-size:10px; opacity:0.75; text-align:right; margin-top:4px;">' . $time_formatted . '</div>';
    echo '</div>';
}
?>