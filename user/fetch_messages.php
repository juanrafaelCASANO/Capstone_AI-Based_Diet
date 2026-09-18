<?php
session_start();
require_once '../config.php';

$user_id = $_POST['user_id'] ?? 0;
$nutri_id = $_POST['nutritionist_id'] ?? 0;
// Use the null coalescing operator (??) to set a default if not provided
$viewer_type = $_POST['viewer_type'] ?? 'user'; 

$query = "SELECT * FROM messages 
          WHERE (sender_id = $user_id AND receiver_id = $nutri_id) 
          OR (sender_id = $nutri_id AND receiver_id = $user_id) 
          ORDER BY created_at ASC";

$result = $conn->query($query);

// Inside fetch_messages.php
while($row = $result->fetch_assoc()) {
    // If the sender is the logged-in user, use 'user-msg', otherwise 'nutritionist-msg'
    $class = ($row['sender_id'] == $user_id) ? 'user-msg' : 'nutritionist-msg';
    
    echo '<div class="message ' . $class . '">' 
         . htmlspecialchars($row['message']) . 
         '</div>';
}
?>