<?php
session_start();
require_once '../config.php';

$user_id = $_POST['user_id'] ?? 0;
$nutri_id = $_POST['nutritionist_id'] ?? 0;
$viewer_type = $_POST['viewer_type'] ?? 'user'; 

// Pinalitan ng ? para sa PDO execute array
$query = "SELECT * FROM messages 
          WHERE (sender_id = ? AND receiver_id = ?) 
          OR (sender_id = ? AND receiver_id = ?) 
          ORDER BY created_at ASC";

$stmt = $conn->prepare($query);
$stmt->execute([$user_id, $nutri_id, $nutri_id, $user_id]);

while($row = $stmt->fetch()) {
    $class = ($row['sender_id'] == $user_id) ? 'user-msg' : 'nutritionist-msg';
    
    echo '<div class="message ' . $class . '">' 
         . htmlspecialchars($row['message']) . 
         '</div>';
}
?>