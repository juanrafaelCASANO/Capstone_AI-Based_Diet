<?php
session_start();
require_once '../config.php';

/* -------------------------------
   SECURITY: ADMIN ONLY
-------------------------------- */
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../auth/login.php");
    exit();
}

/* -------------------------------
   FETCH ACTIVITY LOGS
-------------------------------- */
$result = $conn->query("
    SELECT actor_id, actor_role, action, ip_address, created_at
    FROM activity_logs
    ORDER BY created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>System Activity Logs | Admin</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
body{
    font-family:'Inter', sans-serif;
    background:#f4f7ff;
    padding:40px;
}
.container{
    background:white;
    max-width:1000px;
    margin:auto;
    padding:30px;
    border-radius:16px;
    box-shadow:0 10px 25px rgba(0,0,0,0.05);
}
h2{
    margin-top:0;
    color:#1e293b;
}
table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}
th, td{
    padding:12px;
    text-align:left;
    border-bottom:1px solid #e5e7eb;
    font-size:14px;
}
th{
    background:#2563eb;
    color:white;
    font-weight:600;
}
.role{
    text-transform:capitalize;
    font-weight:600;
}
.user{ color:#2563eb; }
.nutritionist{ color:#16a34a; }
.admin{ color:#9333ea; }

.back{
    display:inline-block;
    margin-bottom:15px;
    color:#2563eb;
    text-decoration:none;
    font-weight:600;
}
</style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back">← Back to Dashboard</a>
    <h2>📊 System Activity Logs</h2>

    <table>
        <tr>
            <th>Actor ID</th>
            <th>Role</th>
            <th>Action</th>
            <th>Date & Time</th>
        </tr>

        <?php while($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $row['actor_id'] ?></td>
            <td class="role <?= $row['actor_role'] ?>">
                <?= ucfirst($row['actor_role']) ?>
            </td>
            <td><?= htmlspecialchars($row['action']) ?></td>
            
            <td><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

</body>
</html>
