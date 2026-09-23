    <?php
    session_start();
    require_once '../config.php';
    require_once '../logs/activity_logger.php';
    logActivity($conn, $_SESSION['user_id'], $_SESSION['role'], "Sent chat message");

    if(!isset($_SESSION['user_id'])){
        header("Location: ../auth/login.php");
        exit;
    }

    $user_id = $_SESSION['user_id'];

    /* Fetch available nutritionists from your table */
    /* Fetch available nutritionists */
    $nutritionists = $conn->query("SELECT id, fullname FROM nutritionist");
    ?>
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <title>AI-Based Diet & Nutritional Planner</title>
        
        <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body{
        font-family: 'Inter', sans-serif;
        background:#f4f7ff;
        display:flex;
    }

    /* Sidebar styling like dashboard */
    .sidebar {
        width:220px;
        background:#1e40af;
        min-height:100vh;
        padding:30px 20px;
        color:white;
        display:flex;
        flex-direction:column;
    }
    .sidebar .logo {
        font-size:22px;
        font-weight:800;
        margin-bottom:30px;
    }

    /* Sidebar links */
    .sidebar a {
        text-decoration:none;
        color:white;
        margin:10px 0;
        display:flex;
        align-items:center;
    }
    .sidebar a:hover {
        opacity:0.8;
    }

    /* Main content */
    .main-content {
        flex:1;
        padding:30px;
        display:flex;
        justify-content:center;
        align-items:flex-start;
    }

    /* Chat container */
    .chat-container{
        width:400px;
        max-width:100%;
        height:600px;
        display:flex;
        flex-direction:column;
        border-radius:12px;
        background:white;
        box-shadow:0 4px 20px rgba(0,0,0,0.1);
        overflow:hidden;
    }

    /* Chat header */
    .chat-header{
        padding:15px 20px;
        background:#2563eb;
        color:white;
        font-weight:600;
        display:flex;
        justify-content:space-between;
        align-items:center;
    }

    /* Chat body */
    .chat-body {
        flex: 1;
        padding: 15px;
        overflow-y: auto;
        background: #e5e7eb;
        display: flex;
        flex-direction: column; /* Stacks messages vertically */
    }

    /* Base message bubble */
    .message {
        max-width: 75%;
        margin: 8px 0;
        padding: 10px 14px;
        border-radius: 18px;
        word-wrap: break-word;
        line-height: 1.4;
        font-size: 14px;
    }

    /* User (You) - Blue bubble on the Right */
    .user-msg {
        background: #2563eb;
        color: white;
        align-self: flex-end; /* Pushes to the right side */
        border-bottom-right-radius: 2px;
    }

    /* Nutritionist - White bubble on the Left */
    .nutritionist-msg {
        background: white;
        color: #111827;
        align-self: flex-start; /* Pushes to the left side */
        border-bottom-left-radius: 2px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }

    /* Chat footer */
    .chat-footer{
        display:flex;
        padding:10px;
        border-top:1px solid #ddd;
        background:white;
    }
    .chat-footer input[type=text]{
        flex:1;
        padding:10px 15px;
        border-radius:20px;
        border:1px solid #ccc;
        outline:none;
    }
    .chat-footer button{
        padding:10px 15px;
        margin-left:8px;
        border:none;
        border-radius:20px;
        background:#2563eb;
        color:white;
        cursor:pointer;
    }

    /* Nutritionist dropdown */
    #nutritionist{
        width:100%;
        padding:8px 10px;
        margin-bottom:10px;
        border-radius:8px;
        border:1px solid #ccc;
        outline:none;
    }

    /* Back button like Weekly Plan page */
    .back {
        font-size:14px;
        font-weight:700;
        color:white;
        text-decoration:none;
        border:1px solid white;
        padding:6px 12px;
        border-radius:6px;
        display:inline-block;
        margin-bottom:15px;
        transition:0.2s;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .back:hover {
        background-color:white;
        color:#1e40af;
    }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    </head>

    <body>

    <div class="sidebar">
        <div class="logo">AI Diet Planner</div>
        <a href="dashboard.php" class="back">← Back to Dashboard</a>
    </div>

    <div class="main-content">

            <div class="chat-container">
                <div class="chat-header">
                    💬 Chat with Nutritionist
                </div>

                <select id="nutritionist">
        <?php while($row = $nutritionists->fetch()): ?>
            <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['fullname']); ?></option>
        <?php endwhile; ?>
    </select>

                <div class="chat-body" id="chat-box"></div>

                <div class="chat-footer">
                    <input type="text" id="message" placeholder="Type your message...">
                    <button id="send">Send</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    let user_id = <?php echo $user_id; ?>;
    let nutritionist_id = $('#nutritionist').val();

    function fetchMessages() {
        $.post('fetch_messages.php', {user_id, nutritionist_id}, function(data){
            $('#chat-box').html(data);
            $('#chat-box').scrollTop($('#chat-box')[0].scrollHeight);
        });
    }

    $('#send').click(function(){
        let msg = $('#message').val();
        if(msg.trim()!=''){
            $.post('send_message.php', {user_id, nutritionist_id, message:msg}, function(){
                $('#message').val('');
                fetchMessages();
            });
        }
    });

    $('#message').keypress(function(e){
        if(e.which==13){ $('#send').click(); }
    });

    $('#nutritionist').change(function(){
        nutritionist_id = $(this).val();
        fetchMessages();
    });

    // Auto refresh every 2 seconds
    setInterval(fetchMessages, 2000);
    fetchMessages();
    </script>

    </body>
    </html>
