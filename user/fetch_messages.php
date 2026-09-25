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
    // If sent by user -> user-msg (Right)
    // If sent by nutritionist -> nutritionist-msg (Left)
    $class = ($row['sender_id'] == $user_id) ? 'user-msg' : 'nutritionist-msg';
    $message_content = nl2br(htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8'));
    $time_formatted = date('g:i A', strtotime($row['created_at']));

    echo '<div class="message ' . $class . '">';
    echo $message_content;
    echo '<div style="font-size:10px; opacity:0.75; text-align:right; margin-top:4px;">' . $time_formatted . '</div>';
    echo '</div>';
}
?>