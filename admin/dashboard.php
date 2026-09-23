<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
| The dashboard keeps the existing meal_plans and nutritionist queries.
| Optional statistics are checked safely so the dashboard will not break
| if your database does not contain those tables yet.
|--------------------------------------------------------------------------
*/

function tableExists($conn, $tableName) {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = CURRENT_SCHEMA()
              AND table_name = ?
        ");
        $stmt->execute([$tableName]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function countTable($conn, $tableName) {
    if (!tableExists($conn, $tableName)) {
        return 0;
    }

    try {
        return (int)$conn->query("SELECT COUNT(*) FROM \"$tableName\"")->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function safeQuery($conn, $sql, $default = 0) {
    try {
        $result = $conn->query($sql);
        if ($result) {
            return $result->fetchColumn();
        }
    } catch (Exception $e) {
        // Keep dashboard available even when an optional query fails.
    }
    return $default;
}

$meal_count = (int)safeQuery($conn, "SELECT COUNT(*) FROM meal_plans", 0);
$nutritionist_count = (int)safeQuery($conn, "SELECT COUNT(*) FROM nutritionist", 0);

/* Optional tables from the rest of the system */
$user_count = countTable($conn, 'users');
$activity_count = countTable($conn, 'activity_logs');
$weekly_plan_count = countTable($conn, 'weekly_meal_plan');
$profile_count = countTable($conn, 'nutritionists_profile');

/* Useful totals */
$total_records = $meal_count + $nutritionist_count + $user_count;

/* Recent activity data, when activity_logs exists */
$recentActivities = [];

if (tableExists($conn, 'activity_logs')) {
    try {
        $activityStmt = $conn->query("
            SELECT *
            FROM activity_logs
            ORDER BY id DESC
            LIMIT 6
        ");
        $recentActivities = $activityStmt ? $activityStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Exception $e) {
        $recentActivities = [];
    }
}

/* Monthly chart: use current database information when possible.
   If no compatible date column exists, the chart still renders
   a useful distribution of the current dashboard totals. */
$chartLabels = ['Meals', 'Nutritionists', 'Users', 'Weekly Plans'];
$chartValues = [$meal_count, $nutritionist_count, $user_count, $weekly_plan_count];

$adminName = $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | AI Diet Planner</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-soft: #eff6ff;
            --success: #16a34a;
            --success-soft: #f0fdf4;
            --warning: #d97706;
            --warning-soft: #fffbeb;
            --danger: #dc2626;
            --danger-soft: #fef2f2;
            --purple: #7c3aed;
            --purple-soft: #f5f3ff;
            --bg: #f5f7fb;
            --surface: #ffffff;
            --text: #172033;
            --muted: #64748b;
            --border: #e5eaf1;
            --sidebar: #0f172a;
            --sidebar-hover: #1e293b;
            --shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            --radius: 18px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button {
            font-family: inherit;
        }

        /* SIDEBAR */
        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 255px;
            background: var(--sidebar);
            color: #cbd5e1;
            padding: 22px 15px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: transform .25s ease;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 10px 25px;
            color: #fff;
        }

        .brand-logo {
            width: 42px;
            height: 42px;
            border-radius: 13px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            box-shadow: 0 8px 20px rgba(59, 130, 246, .28);
            font-size: 19px;
        }

        .brand strong {
            font-size: 15px;
            display: block;
        }

        .brand small {
            color: #94a3b8;
            font-size: 11px;
        }

        .nav-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #64748b;
            font-weight: 700;
            padding: 14px 12px 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 13px;
            border-radius: 11px;
            margin-bottom: 4px;
            font-size: 13px;
            font-weight: 600;
            transition: .2s;
        }

        .nav-item i {
            width: 18px;
            text-align: center;
            font-size: 15px;
        }

        .nav-item:hover,
        .nav-item.active {
            background: var(--sidebar-hover);
            color: #fff;
        }

        .nav-item.active {
            background: rgba(37, 99, 235, .22);
            color: #93c5fd;
        }

        .sidebar-bottom {
            margin-top: auto;
        }

        .admin-mini {
            border-top: 1px solid #263244;
            padding: 16px 8px 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            width: 37px;
            height: 37px;
            border-radius: 50%;
            background: linear-gradient(135deg, #60a5fa, #818cf8);
            color: #fff;
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 13px;
        }

        .admin-mini strong {
            color: #fff;
            font-size: 12px;
            display: block;
            max-width: 140px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .admin-mini span {
            color: #64748b;
            font-size: 10px;
        }

        /* MAIN */
        .main {
            margin-left: 255px;
            min-height: 100vh;
        }

        .topbar {
            height: 76px;
            background: rgba(255, 255, 255, .92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 34px;
            position: sticky;
            top: 0;
            z-index: 900;
        }

        .page-heading h1 {
            margin: 0;
            font-size: 21px;
            font-weight: 800;
        }

        .page-heading p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .icon-button {
            width: 39px;
            height: 39px;
            border: 1px solid var(--border);
            background: #fff;
            border-radius: 11px;
            display: grid;
            place-items: center;
            color: var(--muted);
            cursor: pointer;
            transition: .2s;
        }

        .icon-button:hover {
            color: var(--primary);
            border-color: #bfdbfe;
            background: var(--primary-soft);
        }

        .logout {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            color: #475569;
            border: 1px solid var(--border);
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            transition: .2s;
        }

        .logout:hover {
            color: var(--danger);
            border-color: #fecaca;
            background: var(--danger-soft);
        }

        .mobile-menu {
            display: none;
        }

        .content {
            padding: 30px 34px 45px;
            max-width: 1550px;
            margin: auto;
        }

        /* WELCOME */
        .welcome {
            background:
                radial-gradient(circle at 90% 10%, rgba(255,255,255,.20), transparent 30%),
                linear-gradient(135deg, #1d4ed8, #4f46e5);
            color: #fff;
            border-radius: 22px;
            padding: 28px 30px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 18px 40px rgba(37, 99, 235, .18);
        }

        .welcome::after {
            content: '';
            position: absolute;
            width: 180px;
            height: 180px;
            border: 30px solid rgba(255,255,255,.08);
            border-radius: 50%;
            right: -45px;
            bottom: -90px;
        }

        .welcome h2 {
            margin: 0 0 7px;
            font-size: 22px;
        }

        .welcome p {
            margin: 0;
            max-width: 650px;
            color: #dbeafe;
            font-size: 13px;
            line-height: 1.6;
        }

        /* STAT CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 17px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 21px;
            box-shadow: var(--shadow);
            transition: transform .2s, box-shadow .2s;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(15, 23, 42, .09);
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 19px;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 13px;
            display: grid;
            place-items: center;
            font-size: 17px;
        }

        .blue { background: var(--primary-soft); color: var(--primary); }
        .green { background: var(--success-soft); color: var(--success); }
        .orange { background: var(--warning-soft); color: var(--warning); }
        .purple { background: var(--purple-soft); color: var(--purple); }

        .stat-menu {
            color: #94a3b8;
            font-size: 13px;
        }

        .stat-card h3 {
            margin: 0;
            color: var(--muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-weight: 700;
        }

        .stat-value {
            font-size: 29px;
            font-weight: 800;
            margin: 5px 0 0;
            letter-spacing: -.04em;
        }

        .stat-note {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 7px;
        }

        /* GRID */
        .dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(300px, .9fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 22px;
            min-width: 0;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 18px;
        }

        .panel-title {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
        }

        .panel-subtitle {
            color: var(--muted);
            font-size: 11px;
            margin-top: 4px;
        }

        .panel-link {
            color: var(--primary);
            font-size: 11px;
            font-weight: 700;
        }

        .chart-wrap {
            height: 285px;
            position: relative;
        }

        /* ACTIVITY */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .activity-item {
            display: flex;
            gap: 11px;
            padding: 10px 4px;
            border-bottom: 1px solid #f1f5f9;
        }

        .activity-item:last-child {
            border-bottom: 0;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            background: var(--primary-soft);
            color: var(--primary);
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            font-size: 12px;
        }

        .activity-text {
            min-width: 0;
        }

        .activity-text strong {
            display: block;
            font-size: 11px;
            margin-bottom: 3px;
        }

        .activity-text span {
            display: block;
            color: var(--muted);
            font-size: 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .empty-state {
            text-align: center;
            padding: 45px 15px;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 27px;
            margin-bottom: 10px;
            color: #cbd5e1;
        }

        .empty-state p {
            margin: 0;
            font-size: 11px;
        }

        /* QUICK ACTIONS */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 13px;
        }

        .action-card {
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 17px;
            transition: .2s;
            background: #fff;
        }

        .action-card:hover {
            border-color: #bfdbfe;
            background: #f8fbff;
            transform: translateY(-2px);
        }

        .action-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--primary-soft);
            color: var(--primary);
            display: grid;
            place-items: center;
            margin-bottom: 13px;
        }

        .action-card h3 {
            margin: 0 0 5px;
            font-size: 12px;
        }

        .action-card p {
            margin: 0;
            color: var(--muted);
            font-size: 10px;
            line-height: 1.5;
        }

        /* OVERVIEW */
        .overview-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .progress-row {
            margin-bottom: 18px;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 7px;
        }

        .progress-label span:last-child {
            color: var(--muted);
        }

        .progress {
            height: 8px;
            border-radius: 99px;
            background: #eef2f7;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #2563eb, #60a5fa);
            min-width: 3%;
            transition: width .8s ease;
        }

        /* RESPONSIVE */
        @media (max-width: 1150px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 900px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main {
                margin-left: 0;
            }

            .mobile-menu {
                display: grid;
                width: 39px;
                height: 39px;
                place-items: center;
                border: 1px solid var(--border);
                background: #fff;
                border-radius: 11px;
                cursor: pointer;
                color: var(--text);
            }

            .topbar {
                padding: 0 18px;
                gap: 12px;
            }

            .dashboard-grid,
            .overview-grid {
                grid-template-columns: 1fr;
            }

            .content {
                padding: 22px 18px 35px;
            }

            .overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(15,23,42,.45);
                z-index: 999;
            }

            .overlay.show {
                display: block;
            }
        }

        @media (max-width: 600px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .actions-grid {
                grid-template-columns: 1fr;
            }

            .top-actions .icon-button {
                display: none;
            }

            .logout span {
                display: none;
            }

            .logout {
                padding: 10px 12px;
            }

            .welcome {
                padding: 22px;
            }

            .welcome h2 {
                font-size: 18px;
            }

            .panel {
                padding: 17px;
            }

            .chart-wrap {
                height: 240px;
            }
        }
    </style>
</head>

<body>

<div class="overlay" id="overlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="brand-logo">
            <i class="fa-solid fa-leaf"></i>
        </div>
        <div>
            <strong>AI Diet Planner</strong>
            <small>Administration</small>
        </div>
    </div>

    <div class="nav-label">Main Menu</div>

    <a class="nav-item active" href="dashboard.php">
        <i class="fa-solid fa-grid-2"></i>
        Dashboard
    </a>

    <a class="nav-item" href="meals.php">
        <i class="fa-solid fa-utensils"></i>
        Meal Management
    </a>

    <a class="nav-item" href="manage_account.php">
        <i class="fa-solid fa-users-gear"></i>
        Accounts
    </a>

    <a class="nav-item" href="activity_logs.php">
        <i class="fa-solid fa-clock-rotate-left"></i>
        Activity Logs
    </a>

    <div class="nav-label">System</div>

    <a class="nav-item" href="../auth/login.php">
        <i class="fa-solid fa-right-from-bracket"></i>
        Sign Out
    </a>

    <div class="sidebar-bottom">
        <div class="admin-mini">
            <div class="avatar">
                <?= strtoupper(substr($adminName, 0, 1)) ?>
            </div>
            <div>
                <strong><?= htmlspecialchars($adminName) ?></strong>
                <span>System Administrator</span>
            </div>
        </div>
    </div>
</aside>

<main class="main">

    <header class="topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="mobile-menu" id="mobileMenu" aria-label="Open navigation">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="page-heading">
                <h1>Admin Dashboard</h1>
                <p>Monitor and manage your AI Diet Planner system.</p>
            </div>
        </div>

        <div class="top-actions">
            <button class="icon-button" onclick="location.reload()" title="Refresh dashboard">
                <i class="fa-solid fa-arrows-rotate"></i>
            </button>

            <a class="logout" href="../auth/login.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Sign Out</span>
            </a>
        </div>
    </header>

    <section class="content">

        <div class="welcome">
            <h2>Welcome back, <?= htmlspecialchars($adminName) ?> 👋</h2>
            <p>
                Here's a live overview of your meal planning platform.
                Review system activity, registered professionals, meal records,
                and important management tools from one place.
            </p>
        </div>

        <!-- STATISTICS -->
        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon blue">
                        <i class="fa-solid fa-bowl-food"></i>
                    </div>
                    <i class="fa-solid fa-ellipsis stat-menu"></i>
                </div>
                <h3>Total Meals</h3>
                <div class="stat-value"><?= number_format($meal_count) ?></div>
                <div class="stat-note">Available meal records</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon green">
                        <i class="fa-solid fa-user-doctor"></i>
                    </div>
                    <i class="fa-solid fa-ellipsis stat-menu"></i>
                </div>
                <h3>Nutritionists</h3>
                <div class="stat-value"><?= number_format($nutritionist_count) ?></div>
                <div class="stat-note">Registered nutritionist accounts</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon orange">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <i class="fa-solid fa-ellipsis stat-menu"></i>
                </div>
                <h3>Users</h3>
                <div class="stat-value"><?= number_format($user_count) ?></div>
                <div class="stat-note">Registered system users</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon purple">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <i class="fa-solid fa-ellipsis stat-menu"></i>
                </div>
                <h3>Weekly Plans</h3>
                <div class="stat-value"><?= number_format($weekly_plan_count) ?></div>
                <div class="stat-note">Generated meal plan records</div>
            </div>

        </div>

        <!-- CHART + ACTIVITY -->
        <div class="dashboard-grid">

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">System Overview</h2>
                        <div class="panel-subtitle">Current records across the platform</div>
                    </div>
                </div>

                <div class="chart-wrap">
                    <canvas id="overviewChart"></canvas>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Recent Activity</h2>
                        <div class="panel-subtitle">Latest recorded system actions</div>
                    </div>

                    <a class="panel-link" href="activity_logs.php">View all</a>
                </div>

                <div class="activity-list">
                    <?php if (!empty($recentActivities)): ?>

                        <?php foreach ($recentActivities as $activity): ?>
                            <?php
                                $action = $activity['action']
                                    ?? $activity['activity']
                                    ?? $activity['description']
                                    ?? 'System activity';

                                $user = $activity['user']
                                    ?? $activity['fullname']
                                    ?? $activity['email']
                                    ?? 'System user';

                                $date = $activity['created_at']
                                    ?? $activity['timestamp']
                                    ?? $activity['date']
                                    ?? '';
                            ?>

                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fa-solid fa-bolt"></i>
                                </div>

                                <div class="activity-text">
                                    <strong><?= htmlspecialchars($action) ?></strong>
                                    <span>
                                        <?= htmlspecialchars($user) ?>
                                        <?= $date ? ' • ' . htmlspecialchars($date) : '' ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="empty-state">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            <p>No recent activity available yet.</p>
                        </div>

                    <?php endif; ?>
                </div>
            </section>

        </div>

        <!-- QUICK ACTIONS -->
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Quick Management</h2>
                    <div class="panel-subtitle">Frequently used administration tools</div>
                </div>
            </div>

            <div class="actions-grid">

                <a class="action-card" href="meals.php">
                    <div class="action-icon">
                        <i class="fa-solid fa-utensils"></i>
                    </div>
                    <h3>Manage Meals</h3>
                    <p>Add, edit, review, or remove meal records.</p>
                </a>

                <a class="action-card" href="manage_account.php">
                    <div class="action-icon">
                        <i class="fa-solid fa-user-gear"></i>
                    </div>
                    <h3>Manage Accounts</h3>
                    <p>Review registered users and nutritionists.</p>
                </a>

                <a class="action-card" href="activity_logs.php">
                    <div class="action-icon">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <h3>Activity Logs</h3>
                    <p>Monitor logins and important system actions.</p>
                </a>

                <a class="action-card" href="dashboard.php">
                    <div class="action-icon">
                        <i class="fa-solid fa-arrows-rotate"></i>
                    </div>
                    <h3>Refresh Data</h3>
                    <p>Reload the dashboard and update the displayed totals.</p>
                </a>

            </div>
        </section>

        <!-- DATA DISTRIBUTION -->
        <div class="overview-grid">

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Data Distribution</h2>
                        <div class="panel-subtitle">Relative size of current system records</div>
                    </div>
                </div>

                <?php
                    $maxValue = max($meal_count, $nutritionist_count, $user_count, $weekly_plan_count, 1);
                    $items = [
                        ['Meals', $meal_count],
                        ['Nutritionists', $nutritionist_count],
                        ['Users', $user_count],
                        ['Weekly Plans', $weekly_plan_count]
                    ];
                ?>

                <?php foreach ($items as $item): ?>
                    <?php $percentage = min(100, ($item[1] / $maxValue) * 100); ?>

                    <div class="progress-row">
                        <div class="progress-label">
                            <span><?= htmlspecialchars($item[0]) ?></span>
                            <span><?= number_format($item[1]) ?></span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" style="width: <?= $percentage ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">System Summary</h2>
                        <div class="panel-subtitle">Current platform data</div>
                    </div>
                </div>

                <div class="progress-row">
                    <div class="progress-label">
                        <span>Total tracked records</span>
                        <span><?= number_format($total_records) ?></span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: <?= min(100, max(3, $total_records > 0 ? 100 : 3)) ?>%;"></div>
                    </div>
                </div>

                <div class="progress-row">
                    <div class="progress-label">
                        <span>Activity log entries</span>
                        <span><?= number_format($activity_count) ?></span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: <?= min(100, max(3, $activity_count)) ?>%;"></div>
                    </div>
                </div>

                <div class="progress-row">
                    <div class="progress-label">
                        <span>Nutritionist profiles</span>
                        <span><?= number_format($profile_count) ?></span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: <?= min(100, max(3, $profile_count)) ?>%;"></div>
                    </div>
                </div>

                <div class="progress-row" style="margin-bottom:0;">
                    <div class="progress-label">
                        <span>Weekly meal plan records</span>
                        <span><?= number_format($weekly_plan_count) ?></span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: <?= min(100, max(3, $weekly_plan_count)) ?>%;"></div>
                    </div>
                </div>
            </section>

        </div>

    </section>
</main>

<script>
    // Mobile sidebar
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const mobileMenu = document.getElementById('mobileMenu');

    mobileMenu.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
    });

    overlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    });

    // Dashboard chart
    const chartLabels = <?= json_encode($chartLabels) ?>;
    const chartValues = <?= json_encode($chartValues) ?>;

    const ctx = document.getElementById('overviewChart');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Records',
                data: chartValues,
                borderRadius: 9,
                borderSkipped: false,
                backgroundColor: [
                    'rgba(37, 99, 235, .78)',
                    'rgba(22, 163, 74, .78)',
                    'rgba(217, 119, 6, .78)',
                    'rgba(124, 58, 237, .78)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    padding: 12,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        color: '#94a3b8'
                    },
                    grid: {
                        color: '#eef2f7'
                    }
                },
                x: {
                    ticks: {
                        color: '#64748b'
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
</script>

</body>
</html>
