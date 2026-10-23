<?php
session_start();
require_once '../config.php';

// ======================================================
// ADMIN SECURITY
// ======================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$adminName = $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'Admin User';

// AJAX handler para i-mark as seen ang notifications kapag binuksan ang inbox
if (isset($_GET['action']) && $_GET['action'] === 'mark_seen') {
    $_SESSION['notifications_seen'] = true;
    exit;
}

// ======================================================
// FETCH NOTIFICATIONS & PENDING QUEUE DATA
// ======================================================
$notifications = [];
try {
    $stmt1 = $conn->query("SELECT id, 'Reactivation Request' as type, 'Account reactivation requested by nutritionist' as message, created_at FROM reactivation_requests WHERE status = 'pending' ORDER BY created_at DESC LIMIT 3");
    if ($stmt1) { $notifications = array_merge($notifications, $stmt1->fetchAll(PDO::FETCH_ASSOC)); }
} catch (Exception $e) {}

try {
    $stmt2 = $conn->query("SELECT id, 'Status Request' as type, 'Nutritionist status change requested' as message, created_at FROM nutritionist_status_requests WHERE status = 'pending' ORDER BY created_at DESC LIMIT 3");
    if ($stmt2) { $notifications = array_merge($notifications, $stmt2->fetchAll(PDO::FETCH_ASSOC)); }
} catch (Exception $e) {}

try {
    $stmt3 = $conn->query("SELECT id, 'Nutritionist Report' as type, 'New report filed regarding nutritionist' as message, created_at FROM nutritionist_reports WHERE status = 'pending' ORDER BY created_at DESC LIMIT 3");
    if ($stmt3) { $notifications = array_merge($notifications, $stmt3->fetchAll(PDO::FETCH_ASSOC)); }
} catch (Exception $e) {}

usort($notifications, function($a, $b) {
    $timeA = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
    $timeB = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
    return $timeB - $timeA;
});

$unreadCount = isset($_SESSION['notifications_seen']) && $_SESSION['notifications_seen'] === true ? 0 : count($notifications);

// ======================================================
// STATISTICS, METRICS & STATUS BREAKDOWNS
// ======================================================
$totalMeals = 0;
$totalNutritionists = 0;
$totalUsers = 0;
$totalWeeklyPlans = 0;
$totalActivityLogs = 0;

$nutri_active = 0;
$nutri_on_leave = 0;
$nutri_unavailable = 0;
$nutri_inactive = 0;

try { $totalMeals = $conn->query("SELECT COUNT(*) FROM meal_plans")->fetchColumn(); } catch (Exception $e) {}
try { $totalNutritionists = $conn->query("SELECT COUNT(*) FROM nutritionist")->fetchColumn(); } catch (Exception $e) {}
try { $totalUsers = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(); } catch (Exception $e) {}
try { $totalWeeklyPlans = $conn->query("SELECT COUNT(*) FROM weekly_meal_plan")->fetchColumn(); } catch (Exception $e) {}
try { $totalActivityLogs = $conn->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn(); } catch (Exception $e) {}

// Nutritionist status metrics
try { $nutri_active = $conn->query("SELECT COUNT(*) FROM nutritionist WHERE status = 'active' AND availability_status = 'available'")->fetchColumn(); } catch (Exception $e) {}
try { $nutri_on_leave = $conn->query("SELECT COUNT(*) FROM nutritionist WHERE status = 'active' AND availability_status = 'on_leave'")->fetchColumn(); } catch (Exception $e) {}
try { $nutri_unavailable = $conn->query("SELECT COUNT(*) FROM nutritionist WHERE status = 'active' AND availability_status = 'unavailable'")->fetchColumn(); } catch (Exception $e) {}
try { $nutri_inactive = $conn->query("SELECT COUNT(*) FROM nutritionist WHERE status != 'active'")->fetchColumn(); } catch (Exception $e) {}

// Pending Action Items para sa Dashboard Widget (Pinagsamang Status Requests at Reactivation Requests)
$pending_status_reqs = [];
try {
    $psr_stmt = $conn->query("SELECT sr.id, 'status_request' as req_type, sr.requested_status as subtitle, sr.reason, sr.created_at, n.fullname FROM nutritionist_status_requests sr JOIN nutritionist n ON sr.nutritionist_id = n.id WHERE sr.status = 'pending'");
    if ($psr_stmt) { 
        $pending_status_reqs = array_merge($pending_status_reqs, $psr_stmt->fetchAll(PDO::FETCH_ASSOC)); 
    }
} catch (Exception $e) {}

try {
    $pr_stmt = $conn->query("SELECT r.id, 'reactivation_request' as req_type, 'Reactivation Appeal' as subtitle, r.reason, r.created_at, n.fullname FROM reactivation_requests r JOIN nutritionist n ON r.nutritionist_id = n.id WHERE r.status = 'pending'");
    if ($pr_stmt) { 
        $pending_status_reqs = array_merge($pending_status_reqs, $pr_stmt->fetchAll(PDO::FETCH_ASSOC)); 
    }
} catch (Exception $e) {}

usort($pending_status_reqs, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$pending_status_reqs = array_slice($pending_status_reqs, 0, 3);

$recent_logs = [];
try {
    $log_stmt = $conn->query("SELECT action, description, created_at FROM activity_logs ORDER BY created_at DESC LIMIT 4");
    if ($log_stmt) { $recent_logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC); }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | AI Diet Planner</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f4f7ff 0%, #eef3ff 50%, #f8fafc 100%); color: #172033; min-height: 100vh; }
        a { text-decoration: none; }
        button, input, select { font-family: inherit; }

        .admin-layout { display: flex; min-height: 100vh; width: 100%; }
        .admin-sidebar { width: 255px; min-width: 255px; min-height: 100vh; position: fixed; left: 0; top: 0; bottom: 0; z-index: 1000; padding: 22px 15px; background: #0f172a; color: #cbd5e1; display: flex; flex-direction: column; transition: transform .25s ease; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 8px 10px 25px; color: #fff; }
        .sidebar-logo { width: 42px; height: 42px; flex: 0 0 42px; border-radius: 13px; display: grid; place-items: center; background: linear-gradient(135deg, #3b82f6, #6366f1); color: #fff; font-size: 19px; box-shadow: 0 8px 20px rgba(59,130,246,.28); }
        .sidebar-brand h2 { margin: 0; color: #fff; font-size: 15px; font-weight: 800; }
        .sidebar-brand span { color: #94a3b8; font-size: 11px; }
        .sidebar-section-title { font-size: 10px; text-transform: uppercase; letter-spacing: .12em; color: #64748b; font-weight: 700; padding: 14px 12px 8px; }
        .sidebar-nav { display: flex; flex-direction: column; }
        .sidebar-link { display: flex; align-items: center; gap: 12px; padding: 12px 13px; border-radius: 11px; margin-bottom: 4px; color: #cbd5e1; font-size: 13px; font-weight: 600; transition: .2s; cursor: pointer; border: none; background: transparent; width: 100%; text-align: left; }
        .sidebar-link i { width: 18px; text-align: center; font-size: 15px; }
        .sidebar-link:hover { background: rgba(59,130,246,.10); color: #fff; }
        .sidebar-link.active { background: rgba(37, 99, 235, .22); color: #93c5fd; }
        .sidebar-bottom { margin-top: auto; }
        .admin-mini { border-top: 1px solid #263244; padding: 16px 8px 4px; display: flex; align-items: center; gap: 10px; }
        .avatar { width: 37px; height: 37px; border-radius: 50%; background: linear-gradient(135deg, #60a5fa, #818cf8); color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 13px; }
        .admin-mini strong { color: #fff; font-size: 12px; display: block; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .admin-mini span { color: #64748b; font-size: 10px; }

        .main-content { flex: 1; min-width: 0; margin-left: 255px; }
        .page { width: 100%; margin: 0; padding: 32px 40px 50px; }

        .top-header { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-bottom: 28px; }
        .title-area h1 { font-size: 28px; font-weight: 800; color: #111827; margin-bottom: 4px; }
        .title-area p { color: #64748b; font-size: 13px; }
        
        .header-actions { display: flex; align-items: center; gap: 10px; position: relative; }
        .action-icon-btn { width: 44px; height: 44px; border-radius: 12px; background: #fff; border: 1px solid #e2e8f0; color: #475569; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: .2s; position: relative; }
        .action-icon-btn:hover { background: #f8fafc; border-color: #cbd5e1; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 25px; }
        .stat-card { background: rgba(255,255,255,.88); border: 1px solid rgba(226,232,240,.8); border-radius: 17px; padding: 20px; box-shadow: 0 8px 25px rgba(15,23,42,.05); transition: .3s; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 14px 30px rgba(15,23,42,.09); }
        .stat-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .stat-icon { width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 17px; }
        .icon-blue { background: #eff6ff; color: #2563eb; }
        .icon-green { background: #f0fdf4; color: #16a34a; }
        .icon-orange { background: #fff7ed; color: #ea580c; }
        .icon-purple { background: #faf5ff; color: #9333ea; }
        .stat-label { color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
        .stat-number { font-size: 27px; font-weight: 800; color: #111827; }

        .dashboard-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 20px; margin-bottom: 25px; }
        .dash-card { background: white; border-radius: 20px; border: 1px solid #e5e7eb; box-shadow: 0 10px 35px rgba(15,23,42,.06); padding: 24px; }
        .dash-card h3 { font-size: 17px; font-weight: 800; color: #111827; margin-bottom: 4px; }
        .dash-card p { font-size: 12px; color: #94a3b8; margin-bottom: 20px; }

        .section-title { font-size: 17px; font-weight: 800; color: #111827; margin-bottom: 4px; }
        .section-desc { font-size: 12px; color: #94a3b8; margin-bottom: 15px; }
        
        .quick-mgmt-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px; }
        .qm-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px; text-decoration: none; color: inherit; transition: .2s; }
        .qm-card:hover { border-color: #93c5fd; box-shadow: 0 4px 15px rgba(37,99,235,.1); transform: translateY(-2px); }
        .qm-icon { width: 40px; height: 40px; border-radius: 10px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 16px; margin-bottom: 12px; }
        .qm-icon-orange { background: #fff7ed; color: #ea580c; }
        .qm-card h4 { font-size: 13px; font-weight: 800; color: #1e293b; margin-bottom: 4px; }
        .qm-card p { font-size: 11px; color: #64748b; line-height: 1.4; }

        .progress-container { margin-bottom: 16px; }
        .progress-container:last-child { margin-bottom: 0; }
        .progress-header { display: flex; justify-content: space-between; font-size: 12px; font-weight: 700; color: #1e293b; margin-bottom: 6px; }
        .progress-bar-bg { width: 100%; height: 6px; background: #f1f5f9; border-radius: 10px; overflow: hidden; }
        .progress-bar-fill { height: 100%; background: #2563eb; border-radius: 10px; }

        #inboxDropdown { position: absolute; right: 0; top: 54px; width: 360px; background: #fff; border-radius: 16px; box-shadow: 0 15px 40px rgba(15,23,42,0.15); border: 1px solid #e2e8f0; display: none; flex-direction: column; z-index: 1000; overflow: hidden; }
        .modal { position: fixed; inset: 0; background: rgba(15, 23, 42, .55); display: none; align-items: center; justify-content: center; padding: 20px; z-index: 1000; backdrop-filter: blur(5px); }
        .modal.active { display: flex; }
        .modal-box { width: 100%; max-width: 420px; background: white; border-radius: 24px; padding: 35px 25px; text-align: center; box-shadow: 0 25px 70px rgba(15,23,42,.25); }

        @media (max-width: 1100px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .dashboard-grid { grid-template-columns: 1fr; } .quick-mgmt-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 900px) { .admin-sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } .main-content .page { padding-left: 20px; padding-right: 20px; } }
        @media (max-width: 500px) { .quick-mgmt-grid { grid-template-columns: 1fr; } }
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
            <a href="dashboard.php" class="sidebar-link active"><i class="fa-solid fa-chart-pie"></i><span>Dashboard</span></a>
            <a href="meals.php" class="sidebar-link"><i class="fa-solid fa-utensils"></i><span>Meal Management</span></a>
            <a href="manage_account.php" class="sidebar-link"><i class="fa-solid fa-users-gear"></i><span>Accounts</span></a>
            <a href="manage_reports.php" class="sidebar-link"><i class="fa-solid fa-triangle-exclamation"></i><span>Reports</span></a>
            <a href="activity_logs.php" class="sidebar-link"><i class="fa-solid fa-clock-rotate-left"></i><span>Activity Logs</span></a>
        </nav>

        <div class="sidebar-section-title">System</div>
        <nav class="sidebar-nav">
            <button type="button" class="sidebar-link" onclick="openLogoutModal()">
                <i class="fa-solid fa-right-from-bracket"></i><span>Sign Out</span>
            </button>
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
        <div class="page">
            <header class="top-header">
                <div class="title-area">
                    <h1>Admin Dashboard</h1>
                    <p>Monitor and manage your AI Diet Planner system.</p>
                </div>
                
                <div class="header-actions">
                    <button onclick="location.reload();" class="action-icon-btn" title="Refresh Site">
                        <i class="fa-solid fa-rotate"></i>
                    </button>

                    <div style="position: relative;">
                        <button type="button" onclick="toggleInboxDropdown()" class="action-icon-btn" title="Inbox & Notifications" style="color: #2563eb;">
                            <i class="fa-solid fa-inbox" style="font-size: 18px;"></i>
                            <?php if (isset($unreadCount) && $unreadCount > 0): ?>
                                <span id="notifBadge" style="position: absolute; top: -5px; right: -5px; background: #dc2626; color: white; font-size: 10px; font-weight: 700; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #fff;"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
                            <?php endif; ?>
                        </button>

                        <div id="inboxDropdown">
                            <div style="padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; background: #f8fafc;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <i class="fa-solid fa-inbox" style="color: #2563eb; font-size: 16px;"></i>
                                    <h3 style="font-size: 14px; font-weight: 800; color: #1e293b; margin: 0;">System Notifications</h3>
                                </div>
                                <span id="unreadText" style="font-size: 11px; font-weight: 700; background: #dbeafe; color: #1d4ed8; padding: 4px 10px; border-radius: 20px;"><?= isset($unreadCount) ? $unreadCount : 0 ?> New</span>
                            </div>

                            <div style="max-height: 350px; overflow-y: auto;">
                                <?php if (!empty($notifications)): ?>
                                    <?php foreach (array_slice($notifications, 0, 10) as $notif): 
                                        $iconClass = ($notif['type'] == 'Reactivation Request') ? 'fa-user-check' : 'fa-user-clock';
                                        $iconColor = ($notif['type'] == 'Reactivation Request') ? '#16a34a' : '#ea580c';
                                        $bgColor = ($notif['type'] == 'Reactivation Request') ? '#f0fdf4' : '#fff7ed';
                                    ?>
                                        <div style="padding: 14px 20px; border-bottom: 1px solid #f1f5f9; display: flex; gap: 14px; cursor: pointer;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
                                            <div style="width: 36px; height: 36px; border-radius: 50%; background: <?= $bgColor ?>; color: <?= $iconColor ?>; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0;">
                                                <i class="fa-solid <?= $iconClass ?>"></i>
                                            </div>
                                            <div style="flex: 1; min-width: 0;">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                                    <strong style="font-size: 13px; color: #1e293b;"><?= htmlspecialchars($notif['type']) ?></strong>
                                                    <span style="font-size: 10px; color: #94a3b8; font-weight: 500;"><?= isset($notif['created_at']) ? date('M d, g:i A', strtotime($notif['created_at'])) : 'Just now' ?></span>
                                                </div>
                                                <p style="font-size: 12px; color: #64748b; margin: 0; line-height: 1.4;"><?= htmlspecialchars($notif['message']) ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="padding: 40px 20px; text-align: center;">
                                        <div style="width: 60px; height: 60px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                                            <i class="fa-solid fa-inbox" style="font-size: 24px; color: #cbd5e1;"></i>
                                        </div>
                                        <h4 style="font-size: 14px; color: #475569; font-weight: 600; margin-bottom: 5px;">You're all caught up!</h4>
                                        <p style="font-size: 12px; color: #94a3b8;">No pending notifications.</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div style="padding: 14px; text-align: center; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                                <a href="manage_reports.php" style="font-size: 12.5px; font-weight: 700; color: #2563eb; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px;">
                                    Open Full Inbox <i class="fa-solid fa-arrow-right" style="font-size: 11px;"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- STATS ROW -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-top">
                        <div><div class="stat-label">Total Meals</div><div class="stat-number"><?= number_format($totalMeals) ?></div></div>
                        <div class="stat-icon icon-blue"><i class="fa-solid fa-bowl-food"></i></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <div><div class="stat-label">Nutritionists</div><div class="stat-number"><?= number_format($totalNutritionists) ?></div></div>
                        <div class="stat-icon icon-green"><i class="fa-solid fa-user-nurse"></i></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <div><div class="stat-label">Users</div><div class="stat-number"><?= number_format($totalUsers) ?></div></div>
                        <div class="stat-icon icon-orange"><i class="fa-solid fa-users"></i></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <div><div class="stat-label">Weekly Plans</div><div class="stat-number"><?= number_format($totalWeeklyPlans) ?></div></div>
                        <div class="stat-icon icon-purple"><i class="fa-solid fa-calendar-days"></i></div>
                    </div>
                </div>
            </section>

            <!-- SECTION 1: PENDING APPROVALS QUEUE & RECENT ACTIVITY -->
            <div class="dashboard-grid">
                <div class="dash-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                        <div>
                            <h3>Pending Approvals Queue</h3>
                            <p style="margin: 0;">Actionable requests awaiting admin review</p>
                        </div>
                        <a href="manage_reports.php" style="font-size: 12px; font-weight: 700; color: #2563eb; padding: 6px 12px; background: #eff6ff; border-radius: 8px;">View All</a>
                    </div>

                    <?php if (!empty($pending_status_reqs)): ?>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <?php foreach ($pending_status_reqs as $freq): 
                                $isReactivation = ($freq['req_type'] === 'reactivation_request');
                                $badgeBg = $isReactivation ? '#dcfce7' : '#fef3c7';
                                $badgeColor = $isReactivation ? '#15803d' : '#d97706';
                                $badgeText = $isReactivation ? 'Reactivation Appeal' : str_replace('_', ' ', $freq['subtitle']);
                            ?>
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px; border-radius: 12px; background: #fffbf0; border: 1px solid #fde68a;">
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 3px;">
                                            <strong style="font-size: 13px; color: #1e293b;"><?= htmlspecialchars($freq['fullname']) ?></strong>
                                            <span style="font-size: 10px; background: <?= $badgeBg ?>; color: <?= $badgeColor ?>; padding: 2px 8px; border-radius: 10px; font-weight: 700; text-transform: capitalize;"><?= $badgeText ?></span>
                                        </div>
                                        <p style="font-size: 11.5px; color: #64748b; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($freq['reason']) ?></p>
                                    </div>
                                    <a href="manage_reports.php" style="padding: 6px 12px; background: #d97706; color: white; border-radius: 8px; font-size: 11.5px; font-weight: 700; flex-shrink: 0; text-decoration: none;">Review</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="padding: 35px 20px; text-align: center; border: 1px dashed #e2e8f0; border-radius: 12px; background: #f8fafc;">
                            <i class="fa-solid fa-circle-check" style="font-size: 24px; color: #16a34a; margin-bottom: 8px; display: block;"></i>
                            <h4 style="font-size: 13.5px; color: #475569; margin-bottom: 2px;">No Pending Approvals</h4>
                            <p style="font-size: 11.5px; color: #94a3b8; margin: 0;">All nutritionist status and reactivation requests are cleared.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dash-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                        <div>
                            <h3>Recent Activity</h3>
                            <p style="margin: 0;">Latest recorded actions</p>
                        </div>
                        <a href="activity_logs.php" style="font-size: 12px; font-weight: 700; color: #2563eb; padding: 6px 12px; background: #eff6ff; border-radius: 8px;">Logs</a>
                    </div>
                    
                    <div>
                        <?php if (!empty($recent_logs)): ?>
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <?php foreach ($recent_logs as $log): 
                                    $actionText = strtolower($log['action']);
                                    $iconClass = "fa-bolt"; $iconBg = "#eff6ff"; $iconColor = "#2563eb";
                                    if (strpos($actionText, 'login') !== false) { $iconClass = "fa-arrow-right-to-bracket"; $iconBg = "#f0fdf4"; $iconColor = "#16a34a"; }
                                    elseif (strpos($actionText, 'logout') !== false) { $iconClass = "fa-arrow-right-from-bracket"; $iconBg = "#fef2f2"; $iconColor = "#dc2626"; }
                                ?>
                                    <div style="display: flex; gap: 10px; align-items: flex-start; padding: 9px 12px; border-radius: 10px; border: 1px solid #f1f5f9; background: #fff;">
                                        <div style="width: 30px; height: 30px; border-radius: 8px; background: <?= $iconBg ?>; color: <?= $iconColor ?>; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0;">
                                            <i class="fa-solid <?= $iconClass ?>"></i>
                                        </div>
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                                                <strong style="font-size: 12px; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($log['action']) ?></strong>
                                                <span style="font-size: 9.5px; color: #94a3b8;"><?= date('g:i A', strtotime($log['created_at'])) ?></span>
                                            </div>
                                            <p style="font-size: 11px; color: #64748b; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($log['description'] ?? 'System action') ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="padding: 30px 20px; text-align: center; border: 1px dashed #e2e8f0; border-radius: 12px; background: #f8fafc;">
                                <p style="font-size: 11.5px; color: #94a3b8; margin: 0;">No activity logged yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- QUICK MANAGEMENT WIDGET -->
            <div class="dash-card" style="margin-bottom: 25px;">
                <h3 class="section-title">Quick Management</h3>
                <p class="section-desc">Frequently used administration tools</p>
                
                <div class="quick-mgmt-grid">
                    <a href="meals.php" class="qm-card">
                        <div class="qm-icon"><i class="fa-solid fa-utensils"></i></div>
                        <h4>Manage Meals</h4>
                        <p>Add, edit, review, or remove meal records.</p>
                    </a>
                    
                    <a href="manage_account.php" class="qm-card">
                        <div class="qm-icon"><i class="fa-solid fa-users-gear"></i></div>
                        <h4>Manage Accounts</h4>
                        <p>Review registered users and nutritionists.</p>
                    </a>
                    
                    <a href="manage_reports.php" class="qm-card">
                        <div class="qm-icon qm-icon-orange"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <h4>Reports</h4>
                        <p>Review system issue tickets and logs.</p>
                    </a>
                    
                    <a href="activity_logs.php" class="qm-card">
                        <div class="qm-icon"><i class="fa-solid fa-chart-line"></i></div>
                        <h4>Activity Logs</h4>
                        <p>Monitor logins and important actions.</p>
                    </a>
                </div>
            </div>

            <!-- SECTION 2: NUTRITIONIST STATUS RATIO & PLATFORM BREAKDOWN -->
            <div class="dashboard-grid">
                <div class="dash-card">
                    <h3 class="section-title">Nutritionist Status Ratios</h3>
                    <p class="section-desc">Real-time availability breakdown of practitioners</p>
                    
                    <div class="progress-container">
                        <div class="progress-header"><span>🟢 Available / Active</span><span><?= number_format($nutri_active) ?></span></div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: <?= $totalNutritionists > 0 ? ($nutri_active/$totalNutritionists)*100 : 0 ?>%; background: #10b981;"></div></div>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-header"><span>🟡 On Leave / Sick</span><span><?= number_format($nutri_on_leave) ?></span></div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: <?= $totalNutritionists > 0 ? ($nutri_on_leave/$totalNutritionists)*100 : 0 ?>%; background: #f59e0b;"></div></div>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-header"><span>🔴 Unavailable</span><span><?= number_format($nutri_unavailable) ?></span></div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: <?= $totalNutritionists > 0 ? ($nutri_unavailable/$totalNutritionists)*100 : 0 ?>%; background: #ef4444;"></div></div>
                    </div>

                    <div class="progress-container">
                        <div class="progress-header"><span>⚫ Inactive / Deactivated</span><span><?= number_format($nutri_inactive) ?></span></div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: <?= $totalNutritionists > 0 ? ($nutri_inactive/$totalNutritionists)*100 : 0 ?>%; background: #64748b;"></div></div>
                    </div>
                </div>

                <div class="dash-card">
                    <h3 class="section-title">Platform Records Distribution</h3>
                    <p class="section-desc">Relative size of current system database rows</p>
                    
                    <div class="progress-container">
                        <div class="progress-header"><span>Published Meals</span><span><?= number_format($totalMeals) ?></span></div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 100%;"></div></div>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-header"><span>Registered Users</span><span><?= number_format($totalUsers) ?></span></div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 100%; background: #3b82f6;"></div></div>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-header"><span>Generated Weekly Plans</span><span><?= number_format($totalWeeklyPlans) ?></span></div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 100%; background: #9333ea;"></div></div>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-header"><span>Activity Logs Recorded</span><span><?= number_format($totalActivityLogs) ?></span></div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: 100%; background: #0ea5e9;"></div></div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- LOGOUT CONFIRMATION MODAL -->
<div class="modal" id="actionConfirmModal">
    <div class="modal-box">
        <div style="width: 65px; height: 65px; border-radius: 50%; background: #fef2f2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 20px;">
            <i class="fa-solid fa-door-closed"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 800; color: #111827; margin-bottom: 8px;">Confirm Logout</h3>
        <p style="font-size: 13px; color: #64748b; line-height: 1.5; margin-bottom: 25px;">Are you sure you want to log out of your session?</p>
        <div style="display: flex; gap: 12px;">
            <button type="button" onclick="closeLogoutModal()" style="flex: 1; height: 44px; border-radius: 11px; font-weight: 700; font-size: 13px; cursor: pointer; border: 1px solid #e2e8f0; background: #fff; color: #1e293b;">Cancel</button>
            <a href="../auth/login.php" style="flex: 1; height: 44px; border-radius: 11px; font-weight: 700; font-size: 13px; cursor: pointer; border: none; display: flex; align-items: center; justify-content: center; background: #dc2626; color: white; text-decoration: none;">Yes, Logout</a>
        </div>
    </div>
</div>

<script>
function toggleInboxDropdown() {
    const dropdown = document.getElementById('inboxDropdown');
    const notifBadge = document.getElementById('notifBadge');
    const unreadText = document.getElementById('unreadText');
    
    if (dropdown.style.display === 'flex') {
        dropdown.style.display = 'none';
    } else {
        dropdown.style.display = 'flex';
        if (notifBadge) notifBadge.style.display = 'none';
        if (unreadText) unreadText.innerText = '0 New';
        fetch('dashboard.php?action=mark_seen').catch(err => console.error(err));
    }
}

function openLogoutModal() { document.getElementById('actionConfirmModal').classList.add('active'); }
function closeLogoutModal() { document.getElementById('actionConfirmModal').classList.remove('active'); }

window.addEventListener('click', function(e) {
    const inboxBtn = document.querySelector('button[onclick="toggleInboxDropdown()"]');
    const dropdown = document.getElementById('inboxDropdown');
    const logoutModal = document.getElementById('actionConfirmModal');
    if (dropdown && inboxBtn && !dropdown.contains(e.target) && !inboxBtn.contains(e.target)) {
        dropdown.style.display = 'none';
    }
    if (e.target === logoutModal) closeLogoutModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLogoutModal();
        const dropdown = document.getElementById('inboxDropdown');
        if (dropdown) dropdown.style.display = 'none';
    }
});
</script>

</body>
</html>