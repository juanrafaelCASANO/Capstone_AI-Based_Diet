<?php
session_start();
require_once '../config.php';

// Siguraduhing may nakalog-in na user
if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized access');
}

$user_id = $_POST['user_id'] ?? 0;
$nutri_id = $_POST['nutritionist_id'] ?? 0;
$viewer_type = $_POST['viewer_type'] ?? 'user'; 

// Siguraduhing may valid na IDs na ipinasa
if (!$user_id || !$nutri_id) {
    exit();
}

// SQL query para sa kronolohikong pagkakasunod-sunod (mula sa pinakaluma hanggang sa pinakabagong mensahe)
$query = "SELECT * FROM messages 
          WHERE (sender_id = ? AND receiver_id = ?) 
             OR (sender_id = ? AND receiver_id = ?) 
          ORDER BY created_at ASC";

$stmt = $conn->prepare($query);
$stmt->execute([$user_id, $nutri_id, $nutri_id, $user_id]);

// Gamitin ang PDO::FETCH_ASSOC para sa malinis na array fetching
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Tukuyin ang alignment batay sa sender
    $class = ($row['sender_id'] == $user_id) ? 'user-msg' : 'nutritionist-msg';
    
    // Safety handling gamit ang htmlspecialchars at nl2br para sa line breaks
    $message_content = nl2br(htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8'));
    
    echo '<div class="message ' . $class . '">' 
         . $message_content . 
         '</div>';
}
?>