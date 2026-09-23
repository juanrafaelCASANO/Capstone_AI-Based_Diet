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
// FETCH MEALS
// ======================================================
$meals = [];
$db_error = null;

try {

    $sql = "SELECT * FROM meal_plans ORDER BY id DESC";
    $stmt = $conn->query($sql);

    $meals = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $db_error = $e->getMessage();
    $meals = [];

}

// ======================================================
// STATISTICS
// ======================================================
$totalMeals = count($meals);

$weightLoss = 0;
$maintenance = 0;
$weightGain = 0;
$totalCalories = 0;

foreach ($meals as $meal) {

    $goal = strtolower(trim($meal['goal'] ?? ''));

    $calories = (float)($meal['calories'] ?? 0);
    $totalCalories += $calories;

    if (
        str_contains($goal, 'loss') ||
        str_contains($goal, 'lose')
    ) {
        $weightLoss++;

    } elseif (
        str_contains($goal, 'maintain') ||
        str_contains($goal, 'maintenance')
    ) {
        $maintenance++;

    } elseif (
        str_contains($goal, 'gain') ||
        str_contains($goal, 'weight gain')
    ) {
        $weightGain++;
    }
}

$averageCalories = $totalMeals > 0
    ? round($totalCalories / $totalMeals)
    : 0;

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Manage Meal Plans | Admin</title>

<!-- Google Font -->
<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
>

<!-- Font Awesome -->
<link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    rel="stylesheet"
>

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

    background:
        linear-gradient(
            135deg,
            #f4f7ff 0%,
            #eef3ff 50%,
            #f8fafc 100%
        );

    color: #172033;

    min-height: 100vh;
}

/* ======================================================
   ADMIN SIDEBAR / MAIN LAYOUT
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
    display: block;
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
    gap: 0;
}

.sidebar-link {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 0;
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

a {
    text-decoration: none;
}

button,
input,
select {
    font-family: inherit;
}


/* ======================================================
   LAYOUT
====================================================== */

.page {

    width: 100%;

    max-width: none;

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

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color: white;

    font-size: 23px;

    box-shadow:
        0 10px 25px rgba(37,99,235,.25);
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


/* ======================================================
   HEADER BUTTONS
====================================================== */

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

    box-shadow:
        0 6px 18px rgba(15,23,42,.08);
}

.add-btn {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding: 11px 17px;

    border-radius: 10px;

    background:
        linear-gradient(
            135deg,
            #16a34a,
            #22c55e
        );

    color: white;

    font-weight: 700;

    border: none;

    box-shadow:
        0 7px 18px rgba(34,197,94,.25);

    transition: .25s;
}

.add-btn:hover {

    transform: translateY(-2px);

    box-shadow:
        0 10px 25px rgba(34,197,94,.32);
}


/* ======================================================
   STATISTICS
====================================================== */

.stats-grid {

    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

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

    box-shadow:
        0 8px 25px rgba(15,23,42,.05);

    transition: .3s;
}

.stat-card:hover {

    transform: translateY(-4px);

    box-shadow:
        0 14px 30px rgba(15,23,42,.09);
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

.icon-blue {
    background: #eff6ff;
    color: #2563eb;
}

.icon-green {
    background: #f0fdf4;
    color: #16a34a;
}

.icon-orange {
    background: #fff7ed;
    color: #ea580c;
}

.icon-purple {
    background: #faf5ff;
    color: #9333ea;
}

.icon-red {
    background: #fef2f2;
    color: #dc2626;
}

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
   MAIN CARD
====================================================== */

.main-card {

    background: white;

    border-radius: 20px;

    border: 1px solid #e5e7eb;

    box-shadow:
        0 10px 35px rgba(15,23,42,.06);

    overflow: hidden;
}


/* ======================================================
   TOOLBAR
====================================================== */

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


/* SEARCH */

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

    padding:
        0 14px 0 38px;

    border: 1px solid #e2e8f0;

    border-radius: 9px;

    outline: none;

    color: #334155;

    transition: .2s;
}

.search-box input:focus {

    border-color: #93c5fd;

    box-shadow:
        0 0 0 3px rgba(59,130,246,.1);
}


/* FILTER */

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

.meal-table {

    width: 100%;

    border-collapse: collapse;

    min-width: 950px;
}

.meal-table th {

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

.meal-table td {

    padding: 14px 20px;

    border-bottom: 1px solid #f1f5f9;

    vertical-align: middle;
}

.meal-table tbody tr {

    transition: .2s;
}

.meal-table tbody tr:hover {

    background: #f8fbff;
}


/* ======================================================
   MEAL INFO
====================================================== */

.meal-info {

    display: flex;

    align-items: center;

    gap: 12px;
}

.meal-photo {

    width: 52px;
    height: 52px;

    border-radius: 12px;

    object-fit: cover;

    background: #f1f5f9;

    border: 1px solid #e2e8f0;
}

.no-photo {

    display: flex;

    align-items: center;
    justify-content: center;

    color: #94a3b8;

    font-size: 18px;
}

.meal-name {

    font-weight: 700;

    color: #1e293b;

    font-size: 14px;
}

.meal-id {

    color: #94a3b8;

    font-size: 11px;

    margin-top: 3px;
}


/* ======================================================
   GOAL BADGE
====================================================== */

.goal-badge {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 6px 10px;

    border-radius: 20px;

    background: #eff6ff;

    color: #1d4ed8;

    font-size: 11px;

    font-weight: 700;
}


/* ======================================================
   CALORIES
====================================================== */

.calorie-value {

    font-weight: 800;

    color: #111827;

    font-size: 14px;
}

.calorie-label {

    color: #94a3b8;

    font-size: 11px;
}


/* ======================================================
   DESCRIPTION
====================================================== */

.description {

    max-width: 270px;

    color: #64748b;

    font-size: 12px;

    line-height: 1.5;
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

.view-btn {

    background: #f1f5f9;

    color: #475569;
}

.view-btn:hover {

    background: #e2e8f0;

    transform: translateY(-2px);
}

.edit-btn {

    background: #eef2ff;

    color: #4f46e5;
}

.edit-btn:hover {

    background: #e0e7ff;

    transform: translateY(-2px);
}

.delete-btn {

    background: #fef2f2;

    color: #dc2626;
}

.delete-btn:hover {

    background: #fee2e2;

    transform: translateY(-2px);
}


/* ======================================================
   EMPTY STATE
====================================================== */

.empty-state {

    padding: 70px 20px;

    text-align: center;

    color: #94a3b8;
}

.empty-icon {

    width: 70px;
    height: 70px;

    margin: auto auto 15px;

    border-radius: 50%;

    display: flex;

    align-items: center;
    justify-content: center;

    background: #f1f5f9;

    color: #94a3b8;

    font-size: 25px;
}

.empty-state h3 {

    color: #475569;

    margin-bottom: 5px;

    font-size: 16px;
}

.empty-state p {

    font-size: 13px;
}


/* ======================================================
   ERROR
====================================================== */

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

    background: rgba(15,23,42,.55);

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

    max-width: 500px;

    background: white;

    border-radius: 20px;

    padding: 25px;

    box-shadow:
        0 25px 70px rgba(15,23,42,.25);

    animation: modalIn .25s ease;
}

@keyframes modalIn {

    from {
        opacity: 0;
        transform: translateY(15px) scale(.97);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
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
}

.close-modal {

    width: 35px;
    height: 35px;

    border: none;

    border-radius: 8px;

    background: #f1f5f9;

    cursor: pointer;

    color: #475569;
}

.modal-image {

    width: 100%;

    height: 220px;

    object-fit: cover;

    border-radius: 14px;

    margin-bottom: 18px;

    background: #f1f5f9;
}

.modal-info {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 12px;
}

.info-box {

    background: #f8fafc;

    padding: 13px;

    border-radius: 10px;
}

.info-box small {

    display: block;

    color: #94a3b8;

    font-size: 10px;

    text-transform: uppercase;

    font-weight: 700;

    margin-bottom: 4px;
}

.info-box strong {

    color: #1e293b;

    font-size: 13px;
}

.modal-description {

    margin-top: 15px;

    padding: 15px;

    background: #f8fafc;

    border-radius: 10px;

    color: #64748b;

    font-size: 13px;

    line-height: 1.6;
}


/* ======================================================
   RESPONSIVE
====================================================== */

@media (max-width: 1100px) {

    .stats-grid {

        grid-template-columns:
            repeat(3, 1fr);
    }

}

@media (max-width: 800px) {

    .page {

        padding: 20px 15px 40px;
    }

    .top-header {

        align-items: flex-start;

        flex-direction: column;
    }

    .header-actions {

        width: 100%;
    }

    .back-btn,
    .add-btn {

        flex: 1;

        justify-content: center;
    }

    .stats-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .toolbar {

        align-items: stretch;

        flex-direction: column;
    }

    .toolbar-controls {

        width: 100%;

        flex-direction: column;
    }

    .search-box,
    .search-box input,
    .filter-select {

        width: 100%;
    }
}


/* ======================================================
   SIDEBAR RESPONSIVE
====================================================== */

@media (max-width: 900px) {
    .admin-sidebar {
        transform: translateX(-100%);
    }

    .admin-sidebar.open {
        transform: translateX(0);
    }

    .main-content {
        margin-left: 0;
    }

    .main-content .page {
        padding-left: 25px;
        padding-right: 25px;
    }
}

@media (max-width: 650px) {

    .admin-sidebar {
        width: 78px;
        min-width: 78px;
        padding-left: 10px;
        padding-right: 10px;
    }

    .main-content {
        margin-left: 78px;
    }

    .sidebar-brand {
        justify-content: center;
        padding: 0 0 30px;
    }

    .sidebar-logo {
        width: 48px;
        height: 48px;
        flex-basis: 48px;
    }

    .sidebar-brand > div:last-child,
    .sidebar-section-title,
    .sidebar-link span {
        display: none;
    }

    .sidebar-link {
        justify-content: center;
        padding: 0;
    }

    .sidebar-link i {
        width: auto;
        font-size: 18px;
    }

    .sidebar-system {
        margin-top: 22px;
    }

    .main-content .page {
        padding: 20px 15px 35px;
    }
}


@media (max-width: 500px) {

    .title-area {

        align-items: flex-start;
    }

    .title-icon {

        width: 48px;
        height: 48px;

        font-size: 19px;
    }

    .title-area h1 {

        font-size: 22px;
    }

    .stats-grid {

        grid-template-columns: 1fr;
    }

    .header-actions {

        flex-direction: column;
    }

    .back-btn,
    .add-btn {

        width: 100%;
    }

    .modal-info {

        grid-template-columns: 1fr;
    }
}

</style>

</head>


<body>


<div class="admin-layout">

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

        <div class="sidebar-section-title">
            Main Menu
        </div>

        <nav class="sidebar-nav">

            <a href="dashboard.php" class="sidebar-link">
                <i class="fa-solid fa-grid-2"></i>
                <span>Dashboard</span>
            </a>

            <a href="meals.php" class="sidebar-link active">
                <i class="fa-solid fa-utensils"></i>
                <span>Meal Management</span>
            </a>

            <a href="manage_account.php" class="sidebar-link">
                <i class="fa-solid fa-users-gear"></i>
                <span>Accounts</span>
            </a>

            <a href="activity_logs.php" class="sidebar-link">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Activity Logs</span>
            </a>

        </nav>

        <div class="sidebar-system">
            <div class="sidebar-section-title">
                System
            </div>

            <nav class="sidebar-nav">
                <a href="../auth/login.php" class="sidebar-link signout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Sign Out</span>
                </a>
            </nav>
        </div>

    </aside>

    <main class="main-content">

<div class="page">


<!-- ==================================================
     HEADER
================================================== -->

<header class="top-header">

    <div class="title-area">

        <div class="title-icon">

            <i class="fa-solid fa-utensils"></i>

        </div>

        <div>

            <h1>Manage Meal Plans</h1>

            <p>
                Create, manage and monitor your nutrition meal database.
            </p>

        </div>

    </div>


    <div class="header-actions">

        <a
            href="dashboard.php"
            class="back-btn"
        >

            <i class="fa-solid fa-arrow-left"></i>

            Dashboard

        </a>


        <a
            href="add_meal.php"
            class="add-btn"
        >

            <i class="fa-solid fa-plus"></i>

            Add New Meal

        </a>

    </div>

</header>



<!-- ==================================================
     STATISTICS
================================================== -->

<section class="stats-grid">


    <!-- TOTAL -->

    <div class="stat-card">

        <div class="stat-top">

            <div>

                <div class="stat-label">
                    Total Meals
                </div>

                <div class="stat-number">
                    <?= number_format($totalMeals) ?>
                </div>

            </div>

            <div class="stat-icon icon-blue">

                <i class="fa-solid fa-bowl-food"></i>

            </div>

        </div>

    </div>



    <!-- WEIGHT LOSS -->

    <div class="stat-card">

        <div class="stat-top">

            <div>

                <div class="stat-label">
                    Weight Loss
                </div>

                <div class="stat-number">
                    <?= number_format($weightLoss) ?>
                </div>

            </div>

            <div class="stat-icon icon-green">

                <i class="fa-solid fa-arrow-trend-down"></i>

            </div>

        </div>

    </div>



    <!-- MAINTENANCE -->

    <div class="stat-card">

        <div class="stat-top">

            <div>

                <div class="stat-label">
                    Maintenance
                </div>

                <div class="stat-number">
                    <?= number_format($maintenance) ?>
                </div>

            </div>

            <div class="stat-icon icon-orange">

                <i class="fa-solid fa-scale-balanced"></i>

            </div>

        </div>

    </div>



    <!-- WEIGHT GAIN -->

    <div class="stat-card">

        <div class="stat-top">

            <div>

                <div class="stat-label">
                    Weight Gain
                </div>

                <div class="stat-number">
                    <?= number_format($weightGain) ?>
                </div>

            </div>

            <div class="stat-icon icon-purple">

                <i class="fa-solid fa-arrow-trend-up"></i>

            </div>

        </div>

    </div>



    <!-- AVG CALORIES -->

    <div class="stat-card">

        <div class="stat-top">

            <div>

                <div class="stat-label">
                    Avg Calories
                </div>

                <div class="stat-number">
                    <?= number_format($averageCalories) ?>
                </div>

            </div>

            <div class="stat-icon icon-red">

                <i class="fa-solid fa-fire"></i>

            </div>

        </div>

    </div>

</section>



<!-- ==================================================
     MAIN CONTENT
================================================== -->

<section class="main-card">


    <!-- TOOLBAR -->

    <div class="toolbar">

        <div class="toolbar-title">

            <h2>
                Meal Database
            </h2>

            <span id="resultCount">
                <?= number_format($totalMeals) ?> meal(s)
            </span>

        </div>


        <div class="toolbar-controls">


            <!-- SEARCH -->

            <div class="search-box">

                <i class="fa-solid fa-search"></i>

                <input
                    type="text"
                    id="searchInput"
                    placeholder="Search meals..."
                    autocomplete="off"
                >

            </div>


            <!-- FILTER -->

            <select
                id="goalFilter"
                class="filter-select"
            >

                <option value="all">
                    All Goals
                </option>

                <option value="loss">
                    Weight Loss
                </option>

                <option value="maintain">
                    Maintenance
                </option>

                <option value="gain">
                    Weight Gain
                </option>

            </select>

        </div>

    </div>



    <?php if ($db_error): ?>

        <div class="error-message">

            <strong>
                <i class="fa-solid fa-triangle-exclamation"></i>
                Database Error:
            </strong>

            <?= htmlspecialchars($db_error) ?>

        </div>

    <?php endif; ?>



    <!-- TABLE -->

    <div class="table-wrapper">

        <table class="meal-table">

            <thead>

                <tr>

                    <th>
                        Meal
                    </th>

                    <th>
                        Goal
                    </th>

                    <th>
                        Calories
                    </th>

                    <th>
                        Description
                    </th>

                    <th>
                        Actions
                    </th>

                </tr>

            </thead>


            <tbody id="mealTableBody">


            <?php if (!empty($meals)): ?>


                <?php foreach ($meals as $row): ?>


                    <?php

                    $mealId =
                        (int)($row['id'] ?? 0);

                    $title =
                        $row['title'] ?? 'Untitled Meal';

                    $goal =
                        $row['goal'] ?? 'N/A';

                    $calories =
                        (float)($row['calories'] ?? 0);

                    $description =
                        $row['description'] ?? '';

                    $photo =
                        $row['photo'] ?? '';

                    ?>


                    <tr
                        class="meal-row"
                        data-title="<?= htmlspecialchars(strtolower($title)) ?>"
                        data-goal="<?= htmlspecialchars(strtolower($goal)) ?>"
                    >


                        <!-- MEAL -->

                        <td>

                            <div class="meal-info">


                                <?php if (!empty($photo)): ?>

                                    <img
                                        src="../<?= htmlspecialchars($photo) ?>"
                                        class="meal-photo"
                                        alt="<?= htmlspecialchars($title) ?>"
                                    >

                                <?php else: ?>

                                    <div class="meal-photo no-photo">

                                        <i class="fa-solid fa-utensils"></i>

                                    </div>

                                <?php endif; ?>


                                <div>

                                    <div class="meal-name">

                                        <?= htmlspecialchars($title) ?>

                                    </div>

                                    <div class="meal-id">

                                        Meal #<?= $mealId ?>

                                    </div>

                                </div>

                            </div>

                        </td>



                        <!-- GOAL -->

                        <td>

                            <span class="goal-badge">

                                <i class="fa-solid fa-bullseye"></i>

                                <?= htmlspecialchars($goal) ?>

                            </span>

                        </td>



                        <!-- CALORIES -->

                        <td>

                            <div class="calorie-value">

                                <?= number_format($calories) ?>

                            </div>

                            <div class="calorie-label">

                                kcal

                            </div>

                        </td>



                        <!-- DESCRIPTION -->

                        <td>

                            <div class="description">

                                <?php

                                if (strlen($description) > 90) {

                                    echo htmlspecialchars(
                                        substr(
                                            $description,
                                            0,
                                            90
                                        )
                                    ) . '...';

                                } else {

                                    echo htmlspecialchars(
                                        $description
                                    );

                                }

                                ?>

                            </div>

                        </td>



                        <!-- ACTIONS -->

                        <td>

                            <div class="actions">


                                <!-- VIEW -->

                                <button
                                    type="button"
                                    class="action-btn view-btn"
                                    title="View Meal"
                                    onclick='viewMeal(
                                        <?= json_encode($title) ?>,
                                        <?= json_encode($goal) ?>,
                                        <?= json_encode($calories) ?>,
                                        <?= json_encode($description) ?>,
                                        <?= json_encode($photo) ?>
                                    )'
                                >

                                    <i class="fa-solid fa-eye"></i>

                                </button>



                                <!-- EDIT -->

                                <a
                                    href="edit_meal.php?id=<?= urlencode($mealId) ?>"
                                    class="action-btn edit-btn"
                                    title="Edit Meal"
                                >

                                    <i class="fa-solid fa-pen"></i>

                                </a>



                                <!-- DELETE -->

                                <a
                                    href="delete_meal.php?id=<?= urlencode($mealId) ?>"
                                    class="action-btn delete-btn"
                                    title="Delete Meal"
                                    onclick="
                                        return confirm(
                                            'Are you sure you want to delete this meal plan?'
                                        );
                                    "
                                >

                                    <i class="fa-solid fa-trash"></i>

                                </a>


                            </div>

                        </td>


                    </tr>


                <?php endforeach; ?>


            <?php else: ?>


                <tr id="emptyRow">

                    <td
                        colspan="5"
                        class="empty-state"
                    >

                        <div class="empty-icon">

                            <i class="fa-solid fa-utensils"></i>

                        </div>

                        <h3>
                            No Meal Plans Found
                        </h3>

                        <p>
                            Add your first meal plan to the database.
                        </p>

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


<!-- ==================================================
     VIEW MEAL MODAL
================================================== -->

<div
    class="modal"
    id="mealModal"
>

    <div class="modal-box">


        <div class="modal-header">

            <h3 id="modalTitle">
                Meal Details
            </h3>

            <button
                class="close-modal"
                onclick="closeModal()"
            >

                <i class="fa-solid fa-xmark"></i>

            </button>

        </div>


        <img
            id="modalImage"
            class="modal-image"
            src=""
            alt="Meal"
        >


        <div class="modal-info">


            <div class="info-box">

                <small>
                    Meal Goal
                </small>

                <strong id="modalGoal">
                    -
                </strong>

            </div>


            <div class="info-box">

                <small>
                    Calories
                </small>

                <strong id="modalCalories">
                    -
                </strong>

            </div>


        </div>


        <div class="modal-description">

            <strong>
                Description
            </strong>

            <p
                id="modalDescription"
                style="margin-top:6px;"
            >
                -
            </p>

        </div>


    </div>

</div>



<script>

/* ======================================================
   SEARCH + FILTER
====================================================== */

const searchInput =
    document.getElementById('searchInput');

const goalFilter =
    document.getElementById('goalFilter');

const rows =
    document.querySelectorAll('.meal-row');

const resultCount =
    document.getElementById('resultCount');


function filterMeals() {

    const search =
        searchInput.value
            .toLowerCase()
            .trim();

    const filter =
        goalFilter.value;

    let visible = 0;


    rows.forEach(row => {

        const title =
            row.dataset.title || '';

        const goal =
            row.dataset.goal || '';


        const matchesSearch =
            title.includes(search) ||
            goal.includes(search);


        let matchesGoal = true;


        if (filter === 'loss') {

            matchesGoal =
                goal.includes('loss') ||
                goal.includes('lose');

        }

        else if (filter === 'maintain') {

            matchesGoal =
                goal.includes('maintain');

        }

        else if (filter === 'gain') {

            matchesGoal =
                goal.includes('gain');

        }


        if (
            matchesSearch &&
            matchesGoal
        ) {

            row.style.display = '';

            visible++;

        }

        else {

            row.style.display = 'none';

        }

    });


    resultCount.textContent =
        visible + ' meal(s)';

}


searchInput.addEventListener(
    'input',
    filterMeals
);

goalFilter.addEventListener(
    'change',
    filterMeals
);


/* ======================================================
   VIEW MEAL MODAL
====================================================== */

function viewMeal(
    title,
    goal,
    calories,
    description,
    photo
) {

    document.getElementById(
        'modalTitle'
    ).textContent = title;


    document.getElementById(
        'modalGoal'
    ).textContent = goal;


    document.getElementById(
        'modalCalories'
    ).textContent =
        Number(calories).toLocaleString()
        + ' kcal';


    document.getElementById(
        'modalDescription'
    ).textContent =
        description || 'No description available.';


    const image =
        document.getElementById(
            'modalImage'
        );


    if (photo) {

        image.src =
            '../' + photo;

        image.style.display =
            'block';

    }

    else {

        image.style.display =
            'none';

    }


    document.getElementById(
        'mealModal'
    ).classList.add('active');

}


function closeModal() {

    document.getElementById(
        'mealModal'
    ).classList.remove('active');

}


/* ======================================================
   CLOSE MODAL WHEN CLICKING OUTSIDE
====================================================== */

document.getElementById(
    'mealModal'
).addEventListener(
    'click',
    function(event) {

        if (
            event.target === this
        ) {

            closeModal();

        }

    }
);


/* ======================================================
   ESC KEY
====================================================== */

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            closeModal();

        }

    }
);

</script>


</body>
</html>