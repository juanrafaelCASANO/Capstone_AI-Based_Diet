<?php
// Simply redirect to chat_reply.php passing any user_id parameter
$user_id = $_GET['user_id'] ?? 0;
header("Location: chat_reply.php?user_id=" . $user_id);
exit;
?>