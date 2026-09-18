<?php
session_start();
require_once '../config.php';

/* ✅ LOGOUT LOG — PUT IT HERE */
require_once '../logs/activity_logger.php';

if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    logActivity(
        $conn,
        $_SESSION['user_id'],
        $_SESSION['role'],
        "Logged out"
    );
}

/* Destroy session AFTER logging */
session_destroy();

header("Location: ../auth/login.php");
exit();
