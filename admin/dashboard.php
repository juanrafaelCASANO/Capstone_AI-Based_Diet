<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../auth/login.php");
    exit();
}

$meal_count = $conn->query("SELECT COUNT(*) FROM meal_plans")->fetchColumn();
$nutri_res = $conn->query("SELECT COUNT(*) FROM nutritionist");
$nutritionist_count = ($nutri_res) ? $nutri_res->fetchColumn() : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | AI Diet Planner</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body{font-family:'Inter',sans-serif;background:#f4f7ff;margin:0;padding:0;color:#1e293b;}
        header { background: #2563eb; color: #fff; padding: 20px 60px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(37,99,235,0.2); }
        .header-title { font-size: 24px; font-weight: 800; margin: 0; }
        .container{max-width:1200px;margin:30px auto;padding:0 20px;}
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background:#fff; padding:25px; border-radius:16px; box-shadow:0 10px 25px rgba(0,0,0,.05); display:flex; align-items:center; gap:20px; }
        .stat-icon { width:60px; height:60px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:24px; }
        .stat-info h3 { margin:0; font-size:14px; color:#64748b; text-transform:uppercase; }
        .stat-info p { margin:5px 0 0; font-size:24px; font-weight:700; }
        .card{background:#fff;padding:30px;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.05);margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 20px;}
        .card-content h2{margin:0 0 5px; font-size:20px;}
        .card-content p{margin:0; color:#64748b;}
        .btn{padding:12px 20px; border-radius:10px; font-weight:600; text-decoration:none; transition:0.2s; display:inline-flex; align-items:center; gap:8px;}
        .btn-primary{background:#2563eb; color:#fff;}
        .btn-primary:hover{background:#1d4ed8;}
        .btn-header-logout { background: rgba(255, 255, 255, 0.2); color: #fff; padding: 10px 18px; font-size: 14px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.3); }
        .btn-header-logout:hover { background: rgba(255, 255, 255, 0.3); }
    </style>
</head>
<body>
<header>
    <div class="header-title">Admin Control Panel</div>
    <a class="btn btn-header-logout" href="../auth/login.php">
        <i class="fa-solid fa-right-from-bracket"></i> Sign Out
    </a>
</header>
<div class="container">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #eff6ff; color: #2563eb;"><i class="fa-solid fa-utensils"></i></div>
            <div class="stat-info"><h3>Total Meals</h3><p><?= $meal_count ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #f0fdf4; color: #16a34a;"><i class="fa-solid fa-user-doctor"></i></div>
            <div class="stat-info"><h3>Nutritionists</h3><p><?= $nutritionist_count ?></p></div>
        </div>
    </div>
    <div class="card">
        <div class="card-content"><h2>Meal Plan Management</h2><p>Add, edit, or remove dietary programs.</p></div>
        <a class="btn btn-primary" href="meals.php"><i class="fa-solid fa-arrow-right"></i> Manage Meals</a>
    </div>
    <div class="card">
        <div class="card-content"><h2>Registered Experts</h2><p>Manage login accounts, emails, and credentials.</p></div>
        <a class="btn btn-primary" href="manage_account.php"><i class="fa-solid fa-users-gear"></i> View Accounts</a>
    </div>
    <div class="card">
        <div class="card-content"><h3>System Activity Logs</h3><p>Monitor login history and actions performed.</p></div>
        <a href="activity_logs.php" class="btn btn-primary">📊 View Logs</a>
    </div>
</div>
</body>
</html>