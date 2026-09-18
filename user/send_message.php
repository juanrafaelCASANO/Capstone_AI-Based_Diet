<?php
session_start();
require_once '../config.php';

$sender_id = $_POST['user_id'];
$receiver_id = $_POST['nutritionist_id'];
$message = $conn->real_escape_string($_POST['message']);
// If this is called from the user chat, sender_type is 'user'
$type = isset($_POST['type']) ? $_POST['type'] : 'user'; 

if(!empty($message)) {
    $conn->query("INSERT INTO messages (sender_id, receiver_id, message, sender_type) 
                  VALUES ('$sender_id', '$receiver_id', '$message', '$type')");
}
?>