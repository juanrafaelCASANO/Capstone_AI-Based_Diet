<?php
session_start();
require_once '../config.php';

// Check if nutritionist is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$nutri_id = $_SESSION['user_id'];
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Fetch User Name for the header using PDO prepared statement
$user_stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    die("User not found.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>AI-Based Diet & Nutritional Planner</title>
    
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <style>
        body{ font-family: Arial; background: #f4f7ff; margin: 0; }
        .chat-container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .chat-header { background: #166534; color: white; padding: 15px; text-align: center; }
        .chat-box { height: 400px; overflow-y: scroll; padding: 20px; display: flex; flex-direction: column; }
        .message { margin-bottom: 15px; padding: 10px; border-radius: 8px; max-width: 70%; }
        .sent { align-self: flex-end; background: #dcf8c6; }
        .received { align-self: flex-start; background: #f1f0f0; }
        .reply-area { border-top: 1px solid #ddd; padding: 15px; display: flex; }
        .reply-area input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .reply-area button { margin-left: 10px; padding: 10px 20px; background: #166534; color: white; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>

<div class="chat-container">
    <div class="chat-header">
        <a href="inbox.php" style="float: left; color: white; text-decoration: none; font-weight: bold;">⬅ Back</a>
        
        Chatting with: <?php echo htmlspecialchars($user_data['fullname']); ?>
        
        <div style="clear: both;"></div> 
    </div>
    
    <div class="chat-box" id="chatBox">
        <?php
        // Fetch messages using PDO prepared statement
        $msg_stmt = $conn->prepare("SELECT * FROM messages 
                                    WHERE (sender_id = ? AND receiver_id = ?) 
                                       OR (sender_id = ? AND receiver_id = ?) 
                                    ORDER BY id ASC");
        $msg_stmt->execute([$nutri_id, $user_id, $user_id, $nutri_id]);
        
        while ($msg = $msg_stmt->fetch(PDO::FETCH_ASSOC)):
            $class = ($msg['sender_id'] == $nutri_id) ? 'sent' : 'received';
        ?>
            <div class="message <?php echo $class; ?>">
                <?php echo htmlspecialchars($msg['message']); ?>
            </div>
        <?php endwhile; ?>
    </div>

    <form action="send_message_nutri.php" method="POST" class="reply-area">
        <input type="hidden" name="receiver_id" value="<?php echo $user_id; ?>">
        <input type="text" name="message" placeholder="Type a reply..." required>
        <button type="submit">Send</button>
    </form>
</div>

<script>
    // Auto-scroll chat box to bottom on page load
    const chatBox = document.getElementById('chatBox');
    chatBox.scrollTop = chatBox.scrollHeight;
</script>

</body>
</html>