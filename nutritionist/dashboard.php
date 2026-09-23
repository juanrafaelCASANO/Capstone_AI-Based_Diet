<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$nutri_id = $_SESSION['user_id'];
$unread_count = 0; 

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ?");
    $stmt->execute([$nutri_id]);
    $row = $stmt->fetch();
    if ($row) {
        $unread_count = $row['total'];
    }
} catch (Exception $e) {
    $unread_count = 0;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>AI-Based Diet & Nutritional Planner</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <style>
        body{font-family:Arial; background:#f0fdf4; margin:0}
        .sidebar{width:220px; height:100vh; position:fixed; background:#166534; color:white; padding:20px}
        .sidebar a{display:block; color:white; text-decoration:none; margin:15px 0}
        .main{margin-left:240px; padding:30px}
        .card{background:white; padding:20px; border-radius:12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom:20px}
        .badge {background: red; color: white; padding: 2px 8px; border-radius: 50%; font-size: 12px;}
    </style>
</head>
<body>
<div class="sidebar">
    <h3>NutriPanel</h3>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="inbox.php">📥 Inbox <?php if($unread_count > 0) echo "<span class='badge'>$unread_count</span>"; ?></a>
    <a href="profile.php">👤 Profile</a>
    <a href="../index.php">🚪 Logout</a>
</div>
<div class="main">
    <h2>Nutritionist Portal: Welcome!</h2>
    <div class="card">
        <h3>Recent Inquiries</h3>
        <p>You have <strong><?php echo $unread_count; ?></strong> new messages from users seeking diet advice.</p>
        <a href="inbox.php"><button style="padding:10px; cursor:pointer;">View All Messages</button></a>
    </div>
</div>
</body>
</html>