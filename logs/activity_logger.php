<?php
/**
 * Activity Logger
 * Logs actions of users, nutritionists, and admins
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

/**
 * Log system activity
 *
 * @param mysqli $conn
 * @param int $actor_id
 * @param string $actor_role (user | nutritionist | admin)
 * @param string $action
 */
function logActivity($conn, $actor_id, $actor_role, $action)
{
    if (!$actor_id || !$actor_role || !$action) {
        return;
    }

    $stmt = $conn->prepare("
        INSERT INTO activity_logs (actor_id, actor_role, action, ip_address)
        VALUES (?, ?, ?, ?)
    ");

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    $stmt->bind_param(
        "isss",
        $actor_id,
        $actor_role,
        $action,
        $ip
    );

    $stmt->execute();
    $stmt->close();
}
