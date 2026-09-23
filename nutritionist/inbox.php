<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$nutri_id = $_SESSION['user_id'];
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
        :root { --primary: #166534; --primary-hover: #14532d; --primary-light: #f0fdf4; --bg-body: #f8fafc; --surface: #ffffff; --text-main: #0f172a; --text-muted: #64748b; --border-color: #e2e8f0; --sidebar-width: 260px; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; }
        .app-container { display: flex; width: 100%; }
        .sidebar { width: var(--sidebar-width); background: linear-gradient(180deg, #166534 0%, #0f3d21 100%); color: white; padding: 1.5rem; position: fixed; height: 100vh; display: flex; flex-direction: column; z-index: 100; }
        .sidebar h3 { font-size: 1.25rem; font-weight: 700; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; color: #ffffff; }
        .sidebar nav { display: flex; flex-direction: column; gap: 0.5rem; }
        .sidebar a { display: flex; align-items: center; gap: 0.85rem; color: #e2e8f0; text-decoration: none; padding: 0.85rem 1rem; border-radius: 10px; font-weight: 500; }
        .sidebar a:hover { background: rgba(255, 255, 255, 0.12); color: #ffffff; }
        .main { margin-left: var(--sidebar-width); padding: 2.5rem; flex: 1; max-width: 1200px; }
        .header-title { font-size: 1.75rem; font-weight: 700; color: var(--text-main); margin-bottom: 1.5rem; }
        .inquiry-list { display: flex; flex-direction: column; gap: 1rem; }
        .user-row { background: var(--surface); padding: 1.25rem 1.5rem; border-radius: 14px; display: flex; justify-content: space-between; align-items: center; border: 1px solid var(--border-color); }
        .user-info { display: flex; align-items: center; gap: 1rem; }
        .avatar-placeholder { width: 44px; height: 44px; border-radius: 50%; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700; }
        .user-name { font-size: 1.05rem; font-weight: 600; color: var(--text-main); }
        .btn-reply { background: var(--primary); color: white; padding: 0.65rem 1.25rem; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; }
        .empty-state { background: var(--surface); padding: 3rem 2rem; text-align: center; border-radius: 14px; border: 1px dashed var(--border-color); color: var(--text-muted); }
    </style>
</head>
<body>
<div class="app-container">
    <div class="sidebar">
        <h3>🥗 NutriPanel</h3>
        <nav>
            <a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
            <a href="inbox.php"><i class="fa-solid fa-inbox"></i> Inbox</a>
            <a href="../index.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </nav>
    </div>
    <div class="main">
        <h2 class="header-title">Patient Inquiries</h2>
        <div class="inquiry-list">
        <?php
        $stmt = $conn->prepare("SELECT DISTINCT users.id, users.fullname 
                  FROM messages 
                  JOIN users ON messages.sender_id = users.id 
                  WHERE messages.receiver_id = ? 
                  ORDER BY users.id DESC");
        $stmt->execute([$nutri_id]);

        $rows = $stmt->fetchAll();
        if ($rows) {
            foreach($rows as $row) {
                $initial = strtoupper(substr($row['fullname'], 0, 1));
                ?>
                <div class="user-row">
                    <div class="user-info">
                        <div class="avatar-placeholder"><?php echo $initial; ?></div>
                        <div class="user-details">
                            <span class="user-label" style="font-size:0.75rem; color:#64748b;">Patient</span>
                            <span class="user-name"><?php echo htmlspecialchars($row['fullname']); ?></span>
                        </div>
                    </div>
                    <a href="nutritionist_chat.php?user_id=<?php echo $row['id']; ?>" class="btn-reply">
                        <i class="fa-regular fa-paper-plane"></i> Open Chat
                    </a>
                </div>
                <?php
            }
        } else {
            echo '<div class="empty-state"><i class="fa-regular fa-comments" style="font-size:2.5rem; margin-bottom:1rem;"></i><p>No messages found yet.</p></div>';
        }
        ?>
        </div>
    </div>
</div>
</body>
</html>