<?php
/**
 * Activity Logger
 * Logs actions of users, nutritionists, and admins
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

function logActivity($conn, $actor_id, $actor_role, $action)
{
    if (!$actor_id || !$actor_role || !$action) {
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $current_time = date('Y-m-d H:i:s');

    try {
        // Unang subok: Standard insert kasama ang created_at
        $stmt = $conn->prepare("
            INSERT INTO activity_logs (actor_id, actor_role, action, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $actor_id,
            $actor_role,
            $action,
            $ip,
            $current_time
        ]);
    } catch (PDOException $e) {
        // Alternatibo: Kung mag-error pa rin sa ID, isasama natin ang manual max ID pati ang created_at
        $stmtId = $conn->query("SELECT COALESCE(MAX(id), 0) + 1 FROM activity_logs");
        $nextId = $stmtId->fetchColumn();

        $stmt = $conn->prepare("
            INSERT INTO activity_logs (id, actor_id, actor_role, action, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $nextId,
            $actor_id,
            $actor_role,
            $action,
            $ip,
            $current_time
        ]);
    }
}
?>