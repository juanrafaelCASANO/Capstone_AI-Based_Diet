<?php
session_start();
require_once '../config.php';

/* ✅ LOGOUT LOG — PUT IT HERE */
require_once '../logs/activity_logger.php';

if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    // Kung ang nag-log out ay nutritionist, gawing 'offline' ang availability status
    if ($_SESSION['role'] === 'nutritionist') {
        try {
            $update_offline = $conn->prepare("UPDATE nutritionist SET availability_status = 'offline' WHERE id = ?");
            $update_offline.execute([$_SESSION['user_id']]);
        } catch (Exception $e) {
            // Handle error if needed
        }
    }

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