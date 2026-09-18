<?php
session_start();
require_once '../config.php'; // This provides the $conn variable

// 1. Check if the nutritionist is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$nutri_id = $_SESSION['user_id']; // Now $nutri_id is defined
?>
<!DOCTYPE html>
<html>
<head>
    <title>AI-Based Diet & Nutritional Planner</title>
    
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <style>
        body{font-family:Arial; background:#f4f7ff; margin:0}
        .sidebar{width:220px; height:100vh; position:fixed; background:#166534; color:white; padding:20px}
        .sidebar a{display:block; color:white; text-decoration:none; margin:15px 0}
        .main{margin-left:240px; padding:30px}
        .user-row {
            background: white; padding: 15px; margin-bottom: 10px; 
            border-radius: 8px; display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .btn-reply { background: #166534; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>

<div class="sidebar">
    <h3>NutriPanel</h3>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="inbox.php">📥 Inbox</a>
    <a href="../index.php">🚪 Logout</a>
</div>

<div class="main">
    <h2>Patient Inquiries</h2>
    
    <?php
    // 2. Fetch unique users who have sent messages to THIS nutritionist
    $query = "SELECT DISTINCT users.id, users.fullname 
              FROM messages 
              JOIN users ON messages.sender_id = users.id 
              WHERE messages.receiver_id = $nutri_id 
              ORDER BY messages.id DESC";

    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            ?>
            <div class="user-row">
                <span><strong>From:</strong> <?php echo htmlspecialchars($row['fullname']); ?></span>
                <a href="nutritionist_chat.php?user_id=<?php echo $row['id']; ?>" class="btn-reply">Open Chat</a>
            </div>
            <?php
        }
    } else {
        echo "<p>No messages found yet.</p>";
    }
    ?>
</div>

</body>
</html>