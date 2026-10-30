<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

function sendTextBeeSMS($phoneNumber, $message) {
    $apiKey = getenv('TEXTBEE_API_KEY') ?: (getenv('SUPABASE_KEY') ?: (getenv('SUPABASE_SERVICE_KEY') ?: ''));
    $phoneNumber = trim($phoneNumber);
    if (strpos($phoneNumber, '09') === 0) {
        $phoneNumber = '+63' . substr($phoneNumber, 1);
    }
    $url = 'https://api.textbee.dev/api/v1/gateway/send-sms';
    $data = ['recipients' => [$phoneNumber], 'message' => $message];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'x-api-key: ' . $apiKey]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

if (isset($_GET['status_req_id']) && isset($_GET['action'])) {
    $req_id = (int)$_GET['status_req_id'];
    $action = $_GET['action'];

    $rStmt = $conn->prepare("SELECT r.*, n.fullname, n.phone FROM nutritionist_status_requests r JOIN nutritionist n ON r.nutritionist_id = n.id WHERE r.id = ?");
    $rStmt->execute([$req_id]);
    $reqData = $rStmt->fetch(PDO::FETCH_ASSOC);

    if ($reqData) {
        $nutri_id = $reqData['nutritionist_id'];
        $new_status = $reqData['requested_status'];
        $phone = $reqData['phone'];
        $fullname = $reqData['fullname'];

        if ($action === 'approve') {
            $upNutri = $conn->prepare("UPDATE nutritionist SET availability_status = ? WHERE id = ?");
            $upNutri->execute([$new_status, $nutri_id]);
            $upReq = $conn->prepare("UPDATE nutritionist_status_requests SET status = 'approved' WHERE id = ?");
            $upReq->execute([$req_id]);
            sendTextBeeSMS($phone, "Hello " . $fullname . ", your request to change your availability status to " . ucfirst(str_replace('_', ' ', $new_status)) . " has been APPROVED.");
        } elseif ($action === 'reject') {
            $upReq = $conn->prepare("UPDATE nutritionist_status_requests SET status = 'rejected' WHERE id = ?");
            $upReq->execute([$req_id]);
            sendTextBeeSMS($phone, "Hello " . $fullname . ", your request to change your availability status has been rejected.");
        }
    }
    header("Location: manage_availability.php?msg=status_req_handled");
    exit();
}

$status_requests = [];
try {
    $statusSql = "
        SELECT sr.id, sr.requested_status, sr.reason, sr.certificate_file, sr.status, sr.created_at,
               n.id AS nutritionist_id, n.fullname AS nutritionist_name, n.email AS nutritionist_email
        FROM nutritionist_status_requests sr
        JOIN nutritionist n ON sr.nutritionist_id = n.id
        ORDER BY sr.created_at DESC
    ";
    $statusStmt = $conn->query($statusSql);
    $status_requests = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$adminName = $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Availability Requests | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f4f7ff; color: #172033; min-height: 100vh; }
        .admin-layout { display: flex; min-height: 100vh; }
        .admin-sidebar { width: 255px; background: #0f172a; color: #cbd5e1; padding: 22px 15px; position: fixed; height: 100vh; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 8px 10px 25px; color: #fff; }
        .sidebar-logo { width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; background: linear-gradient(135deg, #3b82f6, #6366f1); color: #fff; font-size: 19px; }
        .sidebar-nav { display: flex; flex-direction: column; gap: 4px; }
        .sidebar-link { display: flex; align-items: center; gap: 12px; padding: 12px 13px; border-radius: 11px; color: #cbd5e1; font-size: 13px; font-weight: 600; text-decoration: none; }
        .sidebar-link:hover, .sidebar-link.active { background: rgba(37,99,235,.22); color: #93c5fd; }
        .main-content { flex: 1; margin-left: 255px; padding: 32px 40px; }
        .main-card { background: white; border-radius: 20px; border: 1px solid #e5e7eb; box-shadow: 0 10px 35px rgba(15,23,42,.05); overflow: hidden; margin-top: 20px; }
        .toolbar { padding: 20px 24px; border-bottom: 1px solid #eef2f7; display: flex; justify-content: space-between; align-items: center; }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table th { padding: 14px 24px; background: #f8fafc; color: #64748b; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .report-table td { padding: 14px 24px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .status-badge { display: inline-block; padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-approved { background: #dcfce7; color: #15803d; }
        .status-rejected { background: #fee2e2; color: #b91c1c; }
        .btn-action-link { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; border: none; cursor: pointer; }
        .btn-view { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        .btn-approve { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .btn-reject { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
</head>
<body>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-logo"><i class="fa-solid fa-leaf"></i></div>
            <div><h2>AI Diet Planner</h2><span>Admin Panel</span></div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-link"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
            <a href="manage_availability.php" class="sidebar-link active"><i class="fa-solid fa-clock-rotate-left"></i> Availability Requests</a>
            <a href="manage_reactivations.php" class="sidebar-link"><i class="fa-solid fa-user-shield"></i> Reactivation Appeals</a>
            <a href="manage_violations.php" class="sidebar-link"><i class="fa-solid fa-triangle-exclamation"></i> Violations & Reports</a>
        </nav>
    </aside>
    <main class="main-content">
        <h1>Availability Status Requests</h1>
        <p style="color:#64748b; margin-top:5px;">Manage nutritionists' availability status changes.</p>
        <div class="main-card">
            <div class="toolbar">
                <h2>Requests List (<?= count($status_requests) ?>)</h2>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Nutritionist</th>
                        <th>Email</th>
                        <th>Requested Status</th>
                        <th>Reason</th>
                        <th>Attachment</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($status_requests)): ?>
                        <?php foreach ($status_requests as $sreq): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($sreq['nutritionist_name']) ?></strong></td>
                                <td><?= htmlspecialchars($sreq['nutritionist_email']) ?></td>
                                <td><span class="status-badge status-pending"><?= str_replace('_', ' ', $sreq['requested_status']) ?></span></td>
                                <td><?= htmlspecialchars($sreq['reason']) ?></td>
                                <td>
                                    <?php if (!empty($sreq['certificate_file'])): ?>
                                        <a href="<?= htmlspecialchars($sreq['certificate_file']) ?>" target="_blank" class="btn-action-link btn-view">View File</a>
                                    <?php else: ?>
                                        <span style="color:#94a3b8; font-style:italic;">No file</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y g:i a', strtotime($sreq['created_at'])) ?></td>
                                <td><span class="status-badge status-<?= $sreq['status'] ?>"><?= ucfirst($sreq['status']) ?></span></td>
                                <td>
                                    <?php if ($sreq['status'] === 'pending'): ?>
                                        <a href="manage_availability.php?status_req_id=<?= $sreq['id'] ?>&action=approve" class="btn-action-link btn-approve">Approve</a>
                                        <a href="manage_availability.php?status_req_id=<?= $sreq['id'] ?>&action=reject" class="btn-action-link btn-reject">Reject</a>
                                    <?php else: ?>
                                        <span style="color:#94a3b8; font-style:italic;">Processed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center; padding:40px; color:#94a3b8;">No requests found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>