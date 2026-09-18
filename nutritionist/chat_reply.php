<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['nutri_id'])){
    header("Location: login.php");
    exit;
}

$nutri_id = $_SESSION['nutri_id'];
$user_id = $_GET['user_id']; // The ID of the user we are replying to

// Fetch user details for the header
$user_query = $conn->query("SELECT fullname FROM users WHERE id = $user_id");
$user_data = $user_query->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>AI-Based Diet & Nutritional Planner</title>
    
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7ff; margin: 0; display: flex; }
        .sidebar { width: 220px; background: #1e3a8a; height: 100vh; color: white; padding: 20px; }
        .chat-area { flex: 1; display: flex; flex-direction: column; height: 100vh; padding: 20px; }
        #chat-box { flex: 1; background: white; border-radius: 12px; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .input-group { margin-top: 15px; display: flex; gap: 10px; }
        input { flex: 1; padding: 12px; border-radius: 8px; border: 1px solid #ddd; outline: none; }
        button { padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 8px; cursor: pointer; }
        .back-btn { color: white; text-decoration: none; display: block; margin-bottom: 20px; font-weight: bold; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<div class="sidebar">
    <a href="dashboard.php" class="back-btn">← Back to Clients</a>
    <h3>Consultation</h3>
    <p>Client: <br><strong><?php echo $user_data['fullname']; ?></strong></p>
</div>

<div class="chat-area">
    <div id="chat-box"></div>
    <div class="input-group">
        <input type="text" id="message" placeholder="Write advice or reply...">
        <button id="send">Send Message</button>
    </div>
</div>

<script>
const user_id = <?php echo $user_id; ?>;
const nutri_id = <?php echo $nutri_id; ?>;

function fetchMessages() {
    $.post('../user/fetch_messages.php', {
        user_id: user_id, 
        nutritionist_id: nutri_id,
        viewer_type: 'nutritionist' // This time the viewer is the nutritionist
    }, function(data){
        $('#chat-box').html(data);
        $('#chat-box').scrollTop($('#chat-box')[0].scrollHeight);
    });
}

$('#send').click(function(){
    let msg = $('#message').val();
    if(msg.trim() != ''){
        $.post('../user/send_message.php', {
            user_id: user_id, 
            nutritionist_id: nutri_id, 
            message: msg,
            type: 'nutritionist' // Identify sender as nutritionist
        }, function(){
            $('#message').val('');
            fetchMessages();
        });
    }
});

// Refresh every 2 seconds
setInterval(fetchMessages, 2000);
fetchMessages();
</script>

</body>
</html>