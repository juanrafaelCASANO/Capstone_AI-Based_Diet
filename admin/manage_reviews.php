<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$reviews = [];
try {
    // Sinusubukan munang kunin kasama ang review column
    $sql = "
        SELECT r.id, r.rating, r.review, r.created_at,
               u.fullname AS user_name, u.email AS user_email,
               n.fullname AS nutritionist_name, n.email AS nutritionist_email
        FROM nutritionist_reviews r
        JOIN users u ON r.user_id = u.id
        JOIN nutritionist n ON r.nutritionist_id = n.id
        ORDER BY r.created_at DESC
    ";
    $stmt = $conn->query($sql);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback kung sakaling wala pa ang review column sa database table
    try {
        $sql = "
            SELECT r.id, r.rating, r.created_at,
                   u.fullname AS user_name, u.email AS user_email,
                   n.fullname AS nutritionist_name, n.email AS nutritionist_email
            FROM nutritionist_reviews r
            JOIN users u ON r.user_id = u.id
            JOIN nutritionist n ON r.nutritionist_id = n.id
            ORDER BY r.created_at DESC
        ";
        $stmt = $conn->query($sql);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($reviews as &$rev) {
            $rev['review'] = 'No comment provided';
        }
        unset($rev);
    } catch (PDOException $ex) {
        $reviews = [];
    }
}

$adminName = $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nutritionist Reviews | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f4f7ff; color: #172033; min-height: 100vh; }
        .admin-layout { display: flex; min-height: 100vh; }
        
        .admin-sidebar { width: 255px; min-width: 255px; background: #0f172a; color: #cbd5e1; padding: 22px 15px; position: fixed; height: 100vh; display: flex; flex-direction: column; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 8px 10px 25px; color: #fff; }
        .sidebar-logo { width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; background: linear-gradient(135deg, #3b82f6, #6366f1); color: #fff; font-size: 19px; }
        .sidebar-brand h2 { font-size: 15px; font-weight: 800; color: #fff; }
        .sidebar-brand span { font-size: 11px; color: #94a3b8; }
        .sidebar-section-title { font-size: 10px; text-transform: uppercase; letter-spacing: .12em; color: #64748b; font-weight: 700; padding: 14px 12px 8px; }
        .sidebar-nav { display: flex; flex-direction: column; gap: 4px; }
        .sidebar-link { display: flex; align-items: center; gap: 12px; padding: 12px 13px; border-radius: 11px; color: #cbd5e1; font-size: 13px; font-weight: 600; text-decoration: none; transition: .2s; }
        .sidebar-link i { width: 18px; text-align: center; font-size: 15px; }
        .sidebar-link:hover { background: rgba(59,130,246,.10); color: #fff; }
        .sidebar-link.active { background: rgba(37,99,235,.22); color: #93c5fd; }
        .sidebar-sublink { padding-left: 36px; font-size: 12px; }
        
        .sidebar-bottom { margin-top: auto; border-top: 1px solid #263244; padding-top: 16px; }
        .admin-mini { display: flex; align-items: center; gap: 10px; padding: 0 8px; }
        .avatar { width: 37px; height: 37px; border-radius: 50%; background: linear-gradient(135deg, #60a5fa, #818cf8); color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 13px; }
        .admin-mini strong { color: #fff; font-size: 12px; display: block; }
        .admin-mini span { color: #64748b; font-size: 10px; }

        .main-content { flex: 1; margin-left: 255px; padding: 32px 40px; }
        .main-card { background: white; border-radius: 20px; border: 1px solid #e5e7eb; box-shadow: 0 10px 35px rgba(15,23,42,.05); overflow: hidden; margin-top: 20px; }
        .toolbar { padding: 20px 24px; border-bottom: 1px solid #eef2f7; display: flex; justify-content: space-between; align-items: center; }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table th { padding: 14px 24px; background: #f8fafc; color: #64748b; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .report-table td { padding: 14px 24px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .star-rating { color: #f59e0b; font-size: 13px; }
    </style>
</head>
<body>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-logo"><i class="fa-solid fa-leaf"></i></div>
            <div><h2>AI Diet Planner</h2><span>Administration</span></div>
        </div>
        
        <div class="sidebar-section-title">Main Menu</div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-link"><i class="fa-solid fa-chart-pie"></i><span>Dashboard</span></a>
            <a href="meals.php" class="sidebar-link"><i class="fa-solid fa-utensils"></i><span>Meal Management</span></a>
            <a href="manage_account.php" class="sidebar-link"><i class="fa-solid fa-users-gear"></i><span>Accounts</span></a>
            
            <a href="manage_reports.php" class="sidebar-link"><i class="fa-solid fa-triangle-exclamation"></i><span>Reports</span></a>
            <a href="manage_reports.php" class="sidebar-link sidebar-sublink"><i class="fa-solid fa-clock-rotate-left"></i><span>Availability Requests</span></a>
            <a href="manage_reactivations.php" class="sidebar-link sidebar-sublink"><i class="fa-solid fa-user-shield"></i><span>Reactivation Appeals</span></a>
            <a href="manage_violations.php" class="sidebar-link sidebar-sublink"><i class="fa-solid fa-triangle-exclamation"></i><span>Violations & Reports</span></a>
            <a href="manage_reviews.php" class="sidebar-link sidebar-sublink active"><i class="fa-solid fa-star"></i><span>Reviews & Ratings</span></a>

            <a href="activity_logs.php" class="sidebar-link"><i class="fa-solid fa-clock-rotate-left"></i><span>Activity Logs</span></a>
        </nav>

        <div class="sidebar-section-title">System</div>
        <nav class="sidebar-nav">
            <a href="../auth/logout.php" class="sidebar-link"><i class="fa-solid fa-right-from-bracket"></i><span>Sign Out</span></a>
        </nav>

        <div class="sidebar-bottom">
            <div class="admin-mini">
                <div class="avatar"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
                <div>
                    <strong><?= htmlspecialchars($adminName) ?></strong>
                    <span>System Administrator</span>
                </div>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <h1>Nutritionist Reviews & Ratings</h1>
        <p style="color:#64748b; margin-top:5px;">Monitor user feedback, ratings, and reviews given to nutritionists after consultations.</p>
        <div class="main-card">
            <div class="toolbar">
                <h2>Total Reviews: <?= count($reviews) ?></h2>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>User (Client)</th>
                        <th>Nutritionist Expert</th>
                        <th>Rating</th>
                        <th>Review / Comment</th>
                        <th>Date Given</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($reviews)): ?>
                        <?php foreach ($reviews as $rev): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($rev['user_name']) ?></strong><br><span style="font-size:11px; color:#64748b;"><?= htmlspecialchars($rev['user_email']) ?></span></td>
                                <td><strong><?= htmlspecialchars($rev['nutritionist_name']) ?></strong><br><span style="font-size:11px; color:#64748b;"><?= htmlspecialchars($rev['nutritionist_email']) ?></span></td>
                                <td>
                                    <div class="star-rating">
                                        <?> 
                                            $rating = (int)$rev['rating'];
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $rating) {
                                                    echo '<i class="fa-solid fa-star"></i>';
                                                } else {
                                                    echo '<i class="fa-regular fa-star" style="color: #cbd5e1;"></i>';
                                                }
                                            }
                                        ?>
                                        <span style="font-weight: 700; color: #1e293b; margin-left: 5px;">(<?= $rating ?>/5)</span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($rev['review'] ?? 'No comment provided') ?></td>
                                <td><?= date('M d, Y g:i a', strtotime($rev['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding:40px; color:#94a3b8;">No reviews or ratings found yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>