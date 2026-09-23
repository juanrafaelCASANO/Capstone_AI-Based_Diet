<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$nutri_id = $_SESSION['user_id'];
$user_id = $_GET['user_id'] ?? 0;

// Fetch user details for the header using PDO
$user_stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_data = $user_stmt->fetch();

if (!$user_data) {
    die("User not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-Based Diet & Nutritional Planner</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #166534; --primary-hover: #14532d; --primary-light: #f0fdf4;
            --bg-body: #f8fafc; --surface: #ffffff; --text-main: #0f172a;
            --text-muted: #64748b; --border-color: #e2e8f0; --sidebar-width: 280px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: var(--sidebar-width); background: linear-gradient(180deg, #166534 0%, #0f3d21 100%); color: white; padding: 2rem 1.5rem; display: flex; flex-direction: column; z-index: 10; }
        .back-btn { color: #cbd5e1; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; margin-bottom: 2.5rem; font-weight: 600; font-size: 0.9rem; }
        .back-btn:hover { color: white; }
        .client-profile-card { background: rgba(255, 255, 255, 0.08); padding: 1.25rem; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar h3 { font-size: 0.75rem; text-transform: uppercase; color: #86efac; margin-bottom: 0.5rem; }
        .client-name { font-size: 1.1rem; font-weight: 700; color: white; word-break: break-word; }
        .chat-area { flex: 1; display: flex; flex-direction: column; height: 100vh; background: var(--bg-body); position: relative; }
        .chat-header-bar { padding: 1.25rem 2rem; background: var(--surface); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; }
        .chat-header-title { font-weight: 700; font-size: 1.1rem; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem; }
        #chat-box { flex: 1; background: var(--bg-body); padding: 2rem; overflow-y: auto; display: flex; flex-direction: column; gap: 1rem; }
        .message { padding: 0.85rem 1.15rem; border-radius: 12px; max-width: 65%; font-size: 0.95rem; line-height: 1.5; word-wrap: break-word; }
        .message.nutritionist { background: var(--primary); color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
        .message.user { background: var(--surface); color: var(--text-main); align-self: flex-start; border: 1px solid var(--border-color); border-bottom-left-radius: 4px; }
        .input-group { padding: 1.25rem 2rem; background: var(--surface); border-top: 1px solid var(--border-color); display: flex; gap: 1rem; align-items: center; }
        input[type="text"] { flex: 1; padding: 0.85rem 1rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-body); color: var(--text-main); outline: none; font-size: 0.95rem; }
        button { padding: 0.85rem 1.5rem; background: var(--primary); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<div class="sidebar">
    <a href="dashboard.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back to Clients</a>
    <div class="client-profile-card">
        <h3>Consultation Active</h3>
        <div class="client-name"><?php echo htmlspecialchars($user_data['fullname']); ?></div>
    </div>
</div>

<div class="chat-area">
    <div class="chat-header-bar">
        <div class="chat-header-title"><i class="fa-regular fa-comments"></i> Live Chat Session</div>
    </div>
    <div id="chat-box"></div>
    <div class="input-group">
        <input type="text" id="message" placeholder="Write advice or reply..." autocomplete="off">
        <button id="send"><i class="fa-solid fa-paper-plane"></i> Send</button>
    </div>
</div>

<script>
const user_id = <?php echo $user_id; ?>;
const nutri_id = <?php echo $nutri_id; ?>;

function fetchMessages() {
    $.post('../user/fetch_messages.php', {
        user_id: user_id, 
        nutritionist_id: nutri_id,
        viewer_type: 'nutritionist'
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
            type: 'nutritionist'
        }, function(){
            $('#message').val('');
            fetchMessages();
        });
    }
});

$('#message').keypress(function (e) {
    if (e.which == 13) { $('#send').click(); return false; }
});

setInterval(fetchMessages, 2000);
fetchMessages();
</script>
</body>
</html>