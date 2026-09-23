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

// ======================================================
// ACTIONS
// ======================================================
if (isset($_POST['add_expert'])) {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $experience = (int)$_POST['experience'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO nutritionist (fullname, email, password, experience) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$fullname, $email, $password, $experience])) {
        header("Location: manage_account.php?msg=success");
        exit();
    }
}

if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM nutritionist WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: manage_account.php?msg=deleted");
    exit();
}

// ======================================================
// FETCH EXPERTS & DATA ANALYSIS
// ======================================================
$experts = [];
$db_error = null;

try {
    $stmt = $conn->query("SELECT id, fullname, email, experience FROM nutritionist ORDER BY id DESC");
    $experts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = $e->getMessage();
}

// Statistics Calculations
$totalAccounts = count($experts);
$juniorCount = 0;   // < 3 years
$midCount = 0;      // 3 - 5 years
$seniorCount = 0;   // > 5 years
$totalExp = 0;

foreach ($experts as $exp) {
    $yrs = (int)($exp['experience'] ?? 0);
    $totalExp += $yrs;

    if ($yrs < 3) {
        $juniorCount++;
    } elseif ($yrs <= 5) {
        $midCount++;
    } else {
        $seniorCount++;
    }
}

$avgExperience = $totalAccounts > 0 ? round($totalExp / $totalAccounts, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts | Admin</title>

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        /* ======================================================
           GLOBAL
        ====================================================== */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f4f7ff 0%, #eef3ff 50%, #f8fafc 100%);
            color: #172033;
            min-height: 100vh;
        }

        a {
            text-decoration: none;
        }

        button, input, select {
            font-family: inherit;
        }

        /* ======================================================
           ADMIN SIDEBAR & LAYOUT
        ====================================================== */
        .admin-layout {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        .admin-sidebar {
            width: 255px;
            min-width: 255px;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 1000;
            padding: 22px 15px;
            background: #0f172a;
            color: #cbd5e1;
            display: flex;
            flex-direction: column;
            transition: transform .25s ease;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 10px 25px;
            color: #fff;
        }

        .sidebar-logo {
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            border-radius: 13px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: #fff;
            font-size: 19px;
            box-shadow: 0 8px 20px rgba(59,130,246,.28);
        }

        .sidebar-brand h2 {
            margin: 0;
            color: #fff;
            font-size: 15px;
            font-weight: 800;
        }

        .sidebar-brand span {
            color: #94a3b8;
            font-size: 11px;
        }

        .sidebar-section-title {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #64748b;
            font-weight: 700;
            padding: 14px 12px 8px;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 13px;
            border-radius: 11px;
            margin-bottom: 4px;
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 600;
            transition: .2s;
        }

        .sidebar-link i {
            width: 18px;
            text-align: center;
            font-size: 15px;
        }

        .sidebar-link:hover {
            background: rgba(59,130,246,.10);
            color: #fff;
        }

        .sidebar-link.active {
            background: #1b356f;
            color: #93c5fd;
        }

        .sidebar-system {
            margin-top: 28px;
        }

        .sidebar-link.signout {
            color: #cbd5e1;
        }

        .main-content {
            flex: 1;
            min-width: 0;
            margin-left: 255px;
        }

        .page {
            width: 100%;
            margin: 0;
            padding: 32px 40px 50px;
        }

        /* ======================================================
           TOP HEADER
        ====================================================== */
        .top-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
        }

        .title-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .title-icon {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            color: white;
            font-size: 23px;
            box-shadow: 0 10px 25px rgba(37,99,235,.25);
        }

        .title-area h1 {
            font-size: 28px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 4px;
        }

        .title-area p {
            color: #64748b;
            font-size: 13px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 16px;
            border-radius: 10px;
            background: white;
            color: #2563eb;
            font-weight: 600;
            border: 1px solid #e2e8f0;
            transition: .25s;
        }

        .back-btn:hover {
            transform: translateX(-3px);
            border-color: #bfdbfe;
            box-shadow: 0 6px 18px rgba(15,23,42,.08);
        }

        .add-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 17px;
            border-radius: 10px;
            background: linear-gradient(135deg, #16a34a, #22c55e);
            color: white;
            font-weight: 700;
            border: none;
            box-shadow: 0 7px 18px rgba(34,197,94,.25);
            cursor: pointer;
            transition: .25s;
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(34,197,94,.32);
        }

        /* ======================================================
           STATISTICS GRID
        ====================================================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 25px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            background: rgba(255,255,255,.88);
            border: 1px solid rgba(226,232,240,.8);
            border-radius: 17px;
            padding: 20px;
            box-shadow: 0 8px 25px rgba(15,23,42,.05);
            transition: .3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 30px rgba(15,23,42,.09);
        }

        .stat-card::after {
            content: "";
            position: absolute;
            width: 80px;
            height: 80px;
            right: -25px;
            bottom: -25px;
            border-radius: 50%;
            background: rgba(37,99,235,.06);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 17px;
        }

        .icon-blue { background: #eff6ff; color: #2563eb; }
        .icon-green { background: #f0fdf4; color: #16a34a; }
        .icon-orange { background: #fff7ed; color: #ea580c; }
        .icon-purple { background: #faf5ff; color: #9333ea; }
        .icon-red { background: #fef2f2; color: #dc2626; }

        .stat-label {
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .stat-number {
            font-size: 27px;
            font-weight: 800;
            color: #111827;
        }

        /* ======================================================
           MAIN CARD & TOOLBAR
        ====================================================== */
        .main-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 35px rgba(15,23,42,.06);
            overflow: hidden;
        }

        .toolbar {
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #eef2f7;
            background: #ffffff;
        }

        .toolbar-title h2 {
            font-size: 17px;
            font-weight: 800;
            color: #111827;
        }

        .toolbar-title span {
            display: block;
            margin-top: 3px;
            color: #94a3b8;
            font-size: 12px;
        }

        .toolbar-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-box input {
            width: 260px;
            height: 40px;
            padding: 0 14px 0 38px;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            outline: none;
            color: #334155;
            transition: .2s;
        }

        .search-box input:focus {
            border-color: #93c5fd;
            box-shadow: 0 0 0 3px rgba(59,130,246,.1);
        }

        .filter-select {
            height: 40px;
            padding: 0 12px;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            background: white;
            color: #475569;
            outline: none;
            cursor: pointer;
        }

        /* ======================================================
           TABLE
        ====================================================== */
        .table-wrapper {
            overflow-x: auto;
        }

        .account-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        .account-table th {
            padding: 14px 20px;
            background: #f8fafc;
            color: #64748b;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .account-table td {
            padding: 14px 20px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .account-table tbody tr {
            transition: .2s;
        }

        .account-table tbody tr:hover {
            background: #f8fbff;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 16px;
            border: 1px solid #dbeafe;
        }

        .user-name {
            font-weight: 700;
            color: #1e293b;
            font-size: 14px;
        }

        .user-id {
            color: #94a3b8;
            font-size: 11px;
            margin-top: 2px;
        }

        .exp-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 11px;
            border-radius: 20px;
            background: #f0fdf4;
            color: #16a34a;
            font-size: 12px;
            font-weight: 700;
        }

        /* ======================================================
           ACTION BUTTONS
        ====================================================== */
        .actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .action-btn {
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            border: none;
            cursor: pointer;
            transition: .2s;
        }

        .edit-btn { background: #eef2ff; color: #4f46e5; }
        .edit-btn:hover { background: #e0e7ff; transform: translateY(-2px); }

        .delete-btn { background: #fef2f2; color: #dc2626; }
        .delete-btn:hover { background: #fee2e2; transform: translateY(-2px); }

        /* ======================================================
           EMPTY STATE & ERRORS
        ====================================================== */
        .empty-state {
            padding: 70px 20px;
            text-align: center;
            color: #94a3b8;
        }

        .empty-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 15px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            color: #94a3b8;
            font-size: 25px;
        }

        .error-message {
            margin: 20px;
            padding: 14px 16px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 10px;
            font-size: 13px;
        }

        /* ======================================================
           MODAL
        ====================================================== */
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .55);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .modal.active {
            display: flex;
        }

        .modal-box {
            width: 100%;
            max-width: 480px;
            background: white;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 25px 70px rgba(15,23,42,.25);
            animation: modalIn .25s ease;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: translateY(15px) scale(.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            font-size: 18px;
            color: #111827;
            font-weight: 800;
        }

        .close-modal {
            width: 35px;
            height: 35px;
            border: none;
            border-radius: 8px;
            background: #f1f5f9;
            cursor: pointer;
            color: #475569;
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .form-group input {
            width: 100%;
            height: 42px;
            padding: 0 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            outline: none;
            color: #1e293b;
            font-size: 14px;
            transition: .2s;
        }

        .form-group input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,.1);
        }

        .submit-btn {
            width: 100%;
            height: 44px;
            margin-top: 10px;
            border-radius: 10px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            font-weight: 700;
            border: none;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(37,99,235,.25);
            transition: .2s;
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(37,99,235,.35);
        }

        /* ======================================================
           RESPONSIVE BREAKPOINTS
        ====================================================== */
        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 900px) {
            .admin-sidebar { transform: translateX(-100%); }
            .admin-sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .main-content .page { padding-left: 25px; padding-right: 25px; }
        }

        @media (max-width: 800px) {
            .page { padding: 20px 15px 40px; }
            .top-header { align-items: flex-start; flex-direction: column; }
            .header-actions { width: 100%; }
            .back-btn, .add-btn { flex: 1; justify-content: center; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .toolbar { align-items: stretch; flex-direction: column; }
            .toolbar-controls { width: 100%; flex-direction: column; }
            .search-box, .search-box input, .filter-select { width: 100%; }
        }

        @media (max-width: 650px) {
            .admin-sidebar { width: 78px; min-width: 78px; padding: 15px 10px; }
            .main-content { margin-left: 78px; }
            .sidebar-brand { justify-content: center; padding-bottom: 30px; }
            .sidebar-logo { width: 48px; height: 48px; flex-basis: 48px; }
            .sidebar-brand > div:last-child, .sidebar-section-title, .sidebar-link span { display: none; }
            .sidebar-link { justify-content: center; padding: 12px 0; }
            .main-content .page { padding: 20px 15px 35px; }
        }

        @media (max-width: 500px) {
            .stats-grid { grid-template-columns: 1fr; }
            .header-actions { flex-direction: column; }
            .back-btn, .add-btn { width: 100%; }
        }
    </style>
</head>

<body>

<div class="admin-layout">

    <!-- SIDEBAR -->
    <aside class="admin-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-logo">
                <i class="fa-solid fa-leaf"></i>
            </div>
            <div>
                <h2>AI Diet Planner</h2>
                <span>Administration</span>
            </div>
        </div>

        <div class="sidebar-section-title">Main Menu</div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-link">
                <i class="fa-solid fa-grid-2"></i>
                <span>Dashboard</span>
            </a>
            <a href="meals.php" class="sidebar-link">
                <i class="fa-solid fa-utensils"></i>
                <span>Meal Management</span>
            </a>
            <a href="manage_account.php" class="sidebar-link active">
                <i class="fa-solid fa-users-gear"></i>
                <span>Accounts</span>
            </a>
            <a href="activity_logs.php" class="sidebar-link">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Activity Logs</span>
            </a>
        </nav>

        <div class="sidebar-system">
            <div class="sidebar-section-title">System</div>
            <nav class="sidebar-nav">
                <a href="../auth/login.php" class="sidebar-link signout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Sign Out</span>
                </a>
            </nav>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <main class="main-content">
        <div class="page">

            <!-- HEADER -->
            <header class="top-header">
                <div class="title-area">
                    <div class="title-icon">
                        <i class="fa-solid fa-user-nurse"></i>
                    </div>
                    <div>
                        <h1>Manage Accounts</h1>
                        <p>Create, audit, and oversee expert nutritionist accounts.</p>
                    </div>
                </div>

                <div class="header-actions">
                    <a href="dashboard.php" class="back-btn">
                        <i class="fa-solid fa-arrow-left"></i>
                        Dashboard
                    </a>
                    <button type="button" class="add-btn" onclick="openModal()">
                        <i class="fa-solid fa-plus"></i>
                        Add New Expert
                    </button>
                </div>
            </header>

            <!-- STATISTICS GRID -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-label">Total Experts</div>
                            <div class="stat-number"><?= number_format($totalAccounts) ?></div>
                        </div>
                        <div class="stat-icon icon-blue">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-label">Junior (< 3Yrs)</div>
                            <div class="stat-number"><?= number_format($juniorCount) ?></div>
                        </div>
                        <div class="stat-icon icon-green">
                            <i class="fa-solid fa-seedling"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-label">Mid-Level (3-5Yrs)</div>
                            <div class="stat-number"><?= number_format($midCount) ?></div>
                        </div>
                        <div class="stat-icon icon-orange">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-label">Senior (> 5Yrs)</div>
                            <div class="stat-number"><?= number_format($seniorCount) ?></div>
                        </div>
                        <div class="stat-icon icon-purple">
                            <i class="fa-solid fa-award"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-label">Avg Experience</div>
                            <div class="stat-number"><?= $avgExperience ?> <span style="font-size:14px; font-weight:600;">yrs</span></div>
                        </div>
                        <div class="stat-icon icon-red">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            </section>

            <!-- MAIN CARD SECTION -->
            <section class="main-card">

                <!-- TOOLBAR -->
                <div class="toolbar">
                    <div class="toolbar-title">
                        <h2>Account Database</h2>
                        <span id="resultCount"><?= number_format($totalAccounts) ?> expert(s)</span>
                    </div>

                    <div class="toolbar-controls">
                        <!-- SEARCH -->
                        <div class="search-box">
                            <i class="fa-solid fa-search"></i>
                            <input type="text" id="searchInput" placeholder="Search expert name or email..." autocomplete="off">
                        </div>

                        <!-- FILTER -->
                        <select id="expFilter" class="filter-select">
                            <option value="all">All Experience Levels</option>
                            <option value="junior">Junior (< 3 Years)</option>
                            <option value="mid">Mid-Level (3 - 5 Years)</option>
                            <option value="senior">Senior (> 5 Years)</option>
                        </select>
                    </div>
                </div>

                <?php if ($db_error): ?>
                    <div class="error-message">
                        <strong><i class="fa-solid fa-triangle-exclamation"></i> Database Error:</strong>
                        <?= htmlspecialchars($db_error) ?>
                    </div>
                <?php endif; ?>

                <!-- TABLE -->
                <div class="table-wrapper">
                    <table class="account-table">
                        <thead>
                            <tr>
                                <th>Expert Details</th>
                                <th>Email Address</th>
                                <th>Experience</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="accountTableBody">
                            <?php if (!empty($experts)): ?>
                                <?php foreach ($experts as $row): 
                                    $id = (int)$row['id'];
                                    $fullname = $row['fullname'] ?? 'N/A';
                                    $email = $row['email'] ?? 'N/A';
                                    $exp = (int)($row['experience'] ?? 0);

                                    // Get user initials for avatar
                                    $initials = 'E';
                                    $parts = explode(' ', trim($fullname));
                                    if (count($parts) >= 2) {
                                        $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts)-1], 0, 1));
                                    } else if (!empty($parts[0])) {
                                        $initials = strtoupper(substr($parts[0], 0, 2));
                                    }
                                ?>
                                    <tr class="account-row" 
                                        data-name="<?= htmlspecialchars(strtolower($fullname)) ?>" 
                                        data-email="<?= htmlspecialchars(strtolower($email)) ?>"
                                        data-exp="<?= $exp ?>">
                                        
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar"><?= $initials ?></div>
                                                <div>
                                                    <div class="user-name"><?= htmlspecialchars($fullname) ?></div>
                                                    <div class="user-id">ID #<?= $id ?></div>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <span style="color:#475569; font-weight:500; font-size:13px;">
                                                <?= htmlspecialchars($email) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="exp-badge">
                                                <i class="fa-solid fa-briefcase"></i>
                                                <?= $exp ?> Years
                                            </span>
                                        </td>

                                        <td>
                                            <div class="actions">
                                                <a href="edit_expert.php?id=<?= $id ?>" class="action-btn edit-btn" title="Edit Expert">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                                <a href="manage_account.php?delete_id=<?= $id ?>" 
                                                   class="action-btn delete-btn" 
                                                   title="Delete Account"
                                                   onclick="return confirm('Are you sure you want to delete this account?');">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty-state">
                                        <div class="empty-icon"><i class="fa-solid fa-user-slash"></i></div>
                                        <h3 style="color:#475569; margin-bottom:5px;">No Experts Found</h3>
                                        <p style="font-size:13px;">Add your first nutritionist account to the system.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </section>
        </div>
    </main>
</div>

<!-- ADD EXPERT MODAL -->
<div class="modal" id="addModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add New Expert</h3>
            <button type="button" class="close-modal" onclick="closeModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="fullname" required placeholder="Dr. Jane Doe">
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="jane@example.com">
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>

            <div class="form-group">
                <label>Years of Experience</label>
                <input type="number" name="experience" required min="0" placeholder="e.g. 5">
            </div>

            <button type="submit" name="add_expert" class="submit-btn">
                Create Account
            </button>
        </form>
    </div>
</div>

<script>
/* ======================================================
   SEARCH & FILTER LOGIC
====================================================== */
const searchInput = document.getElementById('searchInput');
const expFilter = document.getElementById('expFilter');
const rows = document.querySelectorAll('.account-row');
const resultCount = document.getElementById('resultCount');

function filterAccounts() {
    const search = searchInput.value.toLowerCase().trim();
    const filter = expFilter.value;
    let visible = 0;

    rows.forEach(row => {
        const name = row.dataset.name || '';
        const email = row.dataset.email || '';
        const exp = parseInt(row.dataset.exp || '0', 10);

        const matchesSearch = name.includes(search) || email.includes(search);
        let matchesFilter = true;

        if (filter === 'junior') {
            matchesFilter = exp < 3;
        } else if (filter === 'mid') {
            matchesFilter = exp >= 3 && exp <= 5;
        } else if (filter === 'senior') {
            matchesFilter = exp > 5;
        }

        if (matchesSearch && matchesFilter) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    resultCount.textContent = visible + ' expert(s)';
}

if (searchInput) searchInput.addEventListener('input', filterAccounts);
if (expFilter) expFilter.addEventListener('change', filterAccounts);

/* ======================================================
   MODAL CONTROLS
====================================================== */
function openModal() {
    document.getElementById('addModal').classList.add('active');
}

function closeModal() {
    document.getElementById('addModal').classList.remove('active');
}

document.getElementById('addModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>

</body>
</html>