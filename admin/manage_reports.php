<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

function sendTextBeeSMS($phoneNumber, $message) {
    // Ayusin ang pagkuha ng API Key para hindi mag-ka-undefined variable warning
    $apiKey = getenv('TEXTBEE_API_KEY') ?: (getenv('SUPABASE_KEY') ?: (getenv('SUPABASE_SERVICE_KEY') ?: ''));
    
    $phoneNumber = trim($phoneNumber);
    if (strpos($phoneNumber, '09') === 0) {
        $phoneNumber = '+63' . substr($phoneNumber, 1);
    }
    $url = 'https://api.textbee.dev/api/v1/gateway/send-sms';
    $data = [
        'recipients' => [$phoneNumber],
        'message' => $message
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

if (isset($_GET['update_status']) && isset($_GET['id'])) {
    $report_id = (int)$_GET['id'];
    $new_status = $_GET['update_status'];
    if (in_array($new_status, ['pending', 'reviewed', 'resolved'])) {
        $stmt = $conn->prepare("UPDATE nutritionist_reports SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $report_id]);
    }
    header("Location: manage_reports.php?msg=status_updated");
    exit();
}

if (isset($_GET['reactivate_id']) && isset($_GET['action'])) {
    $req_id = (int)$_GET['reactivate_id'];
    $action = $_GET['action'];

    $reqStmt = $conn->prepare("SELECT r.*, n.fullname, n.phone FROM reactivation_requests r JOIN nutritionist n ON r.nutritionist_id = n.id WHERE r.id = ?");
    $reqStmt->execute([$req_id]);
    $reqData = $reqStmt->fetch(PDO::FETCH_ASSOC);

    if ($reqData) {
        $nutri_id = $reqData['nutritionist_id'];
        $phone = $reqData['phone'];
        $fullname = $reqData['fullname'];

        if ($action === 'approve') {
            $upNutri = $conn->prepare("UPDATE nutritionist SET status = 'active' WHERE id = ?");
            $upNutri->execute([$nutri_id]);

            $upReq = $conn->prepare("UPDATE reactivation_requests SET status = 'approved' WHERE id = ?");
            $upReq->execute([$req_id]);

            $message = "Hello " . $fullname . ", great news! Your reactivation request has been APPROVED by the admin. Your account is now active.";
            sendTextBeeSMS($phone, $message);

        } elseif ($action === 'reject') {
            $upReq = $conn->prepare("UPDATE reactivation_requests SET status = 'rejected' WHERE id = ?");
            $upReq->execute([$req_id]);

            $message = "Hello " . $fullname . ", we regret to inform you that your reactivation request has been rejected by the admin.";
            sendTextBeeSMS($phone, $message);
        }
    }
    header("Location: manage_reports.php?msg=reactivation_handled");
    exit();
}

// Handle Nutritionist Status Requests (Approve / Reject with SMS notification)
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

            $statusText = str_replace('_', ' ', $new_status);
            $message = "Hello " . $fullname . ", your request to change your availability status to " . ucfirst($statusText) . " has been APPROVED by the admin.";
            sendTextBeeSMS($phone, $message);

        } elseif ($action === 'reject') {
            $upReq = $conn->prepare("UPDATE nutritionist_status_requests SET status = 'rejected' WHERE id = ?");
            $upReq->execute([$req_id]);

            $message = "Hello " . $fullname . ", your request to change your availability status has been rejected by the admin.";
            sendTextBeeSMS($phone, $message);
        }
    }
    header("Location: manage_reports.php?msg=status_req_handled");
    exit();
}

$reports = [];
try {
    $sql = "
        SELECT r.id, r.category, r.reason, r.status, r.created_at,
               u.fullname AS user_name, u.email AS user_email,
               n.id AS nutritionist_id, n.fullname AS nutritionist_name, n.email AS nutritionist_email, n.status AS nutritionist_status
        FROM nutritionist_reports r
        JOIN users u ON r.user_id = u.id
        JOIN nutritionist n ON r.nutritionist_id = n.id
        ORDER BY r.created_at DESC
    ";
    $stmt = $conn->query($sql);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$reactivations = [];
try {
    $reqSql = "
        SELECT r.id, r.email, r.reason, r.status, r.created_at, r.attachment,
               n.id AS nutritionist_id, n.fullname AS nutritionist_name, n.phone AS nutritionist_phone
        FROM reactivation_requests r
        JOIN nutritionist n ON r.nutritionist_id = n.id
        ORDER BY r.created_at DESC
    ";
    $reqStmt = $conn->query($reqSql);
    $reactivations = $reqStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

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
    <title>Manage Reports | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f4f7ff 0%, #eef3ff 50%, #f8fafc 100%); color: #172033; min-height: 100vh; }
        a { text-decoration: none; }
        button { font-family: inherit; }

        .admin-layout { display: flex; min-height: 100vh; width: 100%; }
        
        .admin-sidebar { width: 255px; min-width: 255px; min-height: 100vh; position: fixed; left: 0; top: 0; bottom: 0; z-index: 1000; padding: 22px 15px; background: #0f172a; color: #cbd5e1; display: flex; flex-direction: column; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 8px 10px 25px; color: #fff; }
        .sidebar-logo { width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; background: linear-gradient(135deg, #3b82f6, #6366f1); color: #fff; font-size: 19px; box-shadow: 0 8px 20px rgba(59,130,246,.28); }
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
        .page { width: 100%; padding: 32px 40px 50px; }
        .top-header { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-bottom: 28px; }
        .title-area { display: flex; align-items: center; gap: 15px; }
        .title-icon { width: 58px; height: 58px; border-radius: 16px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #dc2626, #991b1b); color: white; font-size: 23px; box-shadow: 0 10px 25px rgba(220,38,38,.25); }
        .title-area h1 { font-size: 28px; font-weight: 800; color: #111827; margin-bottom: 4px; }
        .title-area p { color: #64748b; font-size: 13px; }
        .main-card { background: white; border-radius: 20px; border: 1px solid #e5e7eb; box-shadow: 0 10px 35px rgba(15,23,42,.06); overflow: hidden; margin-bottom: 30px; }
        .toolbar { padding: 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eef2f7; background: #ffffff; }
        .toolbar-title h2 { font-size: 17px; font-weight: 800; color: #111827; }
        .toolbar-title span { display: block; margin-top: 3px; color: #94a3b8; font-size: 12px; }
        .table-wrapper { overflow-x: auto; }
        .report-table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .report-table th { padding: 14px 20px; background: #f8fafc; color: #64748b; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        .report-table td { padding: 14px 20px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: 13px; color: #1e293b; }
        .report-table tbody tr:hover { background: #f8fbff; }
        .status-badge { display: inline-block; padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-reviewed { background: #e0f2fe; color: #0284c7; }
        .status-resolved, .status-approved { background: #dcfce7; color: #15803d; }
        .status-rejected { background: #fee2e2; color: #b91c1c; }
        .action-dropdown { padding: 6px 10px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; font-size: 12px; font-weight: 600; cursor: pointer; }
        .btn-action-link { display: inline-flex; align-items: center; justify-content: center; gap: 5px; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; cursor: pointer; border: none; }
        .btn-view { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        .btn-view:hover { background: #dbeafe; }
        .btn-approve { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .btn-approve:hover { background: #bbf7d0; }
        .btn-reject { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .btn-reject:hover { background: #fecaca; }
        .empty-state { padding: 50px 20px; text-align: center; color: #94a3b8; }
        .empty-icon { width: 50px; height: 50px; margin: 0 auto 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: #94a3b8; font-size: 20px; }
        
        .modal { position: fixed; inset: 0; background: rgba(15, 23, 42, .55); display: none; align-items: center; justify-content: center; padding: 20px; z-index: 1000; backdrop-filter: blur(5px); }
        .modal.active { display: flex; }
        .confirm-modal-box { width: 100%; max-width: 450px; background: white; border-radius: 24px; text-align: center; padding: 35px 25px; box-shadow: 0 25px 70px rgba(15,23,42,.25); }
        .confirm-icon-frame { width: 65px; height: 65px; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 28px; }
        .confirm-icon-approve { background: #f0fdf4; color: #16a34a; }
        .confirm-icon-reject { background: #fef2f2; color: #dc2626; }
        .confirm-icon-info { background: #eff6ff; color: #2563eb; }
        .confirm-icon-logout { background: #fef2f2; color: #dc2626; }
        .confirm-modal-box h3 { font-size: 20px; font-weight: 800; color: #111827; margin-bottom: 8px; }
        .confirm-modal-box p { font-size: 13.5px; color: #475569; line-height: 1.6; margin-bottom: 25px; text-align: left; background: #f8fafc; padding: 14px; border-radius: 12px; border: 1px solid #e2e8f0; max-height: 250px; overflow-y: auto; white-space: pre-wrap; word-break: break-word; }
        .confirm-modal-actions { display: flex; gap: 12px; }
        .confirm-btn-cancel, .confirm-btn-action { flex: 1; height: 44px; border-radius: 11px; font-weight: 700; font-size: 13px; cursor: pointer; border: none; display: flex; align-items: center; justify-content: center; }
        .confirm-btn-cancel { background: #ffffff; color: #1e293b; border: 1px solid #e2e8f0; }
        .confirm-btn-success { background: #16a34a; color: white; }
        .confirm-btn-danger { background: #dc2626; color: white; }
        .confirm-btn-primary { background: #2563eb; color: white; width: 100%; }
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
            <a href="manage_reports.php" class="sidebar-link active"><i class="fa-solid fa-triangle-exclamation"></i><span>Reports</span></a>
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

    <main class="main-content">
        <div class="page">
            <header class="top-header">
                <div class="title-area">
                    <div class="title-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <div>
                        <h1>Reports & Requests</h1>
                        <p>Review user violation reports, account reactivation appeals, and availability status requests.</p>
                    </div>
                </div>
            </header>

            <!-- SECTION 0: AVAILABILITY STATUS REQUESTS -->
            <section class="main-card">
                <div class="toolbar">
                    <div class="toolbar-title">
                        <h2>Nutritionist Availability Status Requests</h2>
                        <span>Total Requests: <?= count($status_requests) ?></span>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Nutritionist Expert</th>
                                <th>Email</th>
                                <th>Requested Status</th>
                                <th>Reason / Explanation</th>
                                <th>Attachment</th>
                                <th>Date Requested</th>
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
                                        <td>
                                            <span class="status-badge status-pending" style="text-transform: capitalize;">
                                                <?= str_replace('_', ' ', $sreq['requested_status']) ?>
                                            </span>
                                        </td>
                                        <td style="max-width: 200px;">
                                            <div style="overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; font-size: 13px; color: #1e293b;">
                                                <?= htmlspecialchars($sreq['reason']) ?>
                                            </div>
                                            <?php if (strlen($sreq['reason']) > 60): ?>
                                                <button type="button" style="background: none; border: none; color: #2563eb; font-size: 11px; font-weight: 700; cursor: pointer; padding: 0; margin-top: 4px;" onclick="openReasonModal('Status Request Reason - ' + <?= htmlspecialchars(json_encode($sreq['nutritionist_name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($sreq['reason']), ENT_QUOTES) ?>)">
                                                    View Full Reason &rarr;
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <!-- COLUMN PARA SA ATTACHED FILE NG STATUS REQUEST -->
                                        <td>
                                            <?php if (!empty($sreq['certificate_file'])): ?>
                                                <?php 
                                                    $certFile = $sreq['certificate_file'];
                                                    // Kung hindi pa buong URL ang naka-save, buuin natin ang Supabase Public URL
                                                    if (strpos($certFile, 'http') !== 0) {
                                                        $supabaseUrl = getenv('SUPABASE_URL') ?: 'https://mbsjtesmrowfilvxcaqb.supabase.co';
                                                        $certFile = rtrim($supabaseUrl, '/') . '/storage/v1/object/public/nutritionist-files/' . $certFile;
                                                    }
                                                ?>
                                                <a href="<?= htmlspecialchars($certFile) ?>" target="_blank" class="btn-action-link btn-view" style="font-size:11px; padding: 4px 8px;"><i class="fa-solid fa-paperclip"></i> View File</a>
                                            <?php else: ?>
                                                <span style="font-size: 11px; color: #94a3b8; font-style: italic;">No attachment</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y g:i a', strtotime($sreq['created_at'])) ?></td>
                                        <td>
                                            <?php if ($sreq['status'] === 'pending'): ?>
                                                <span class="status-badge status-pending">Pending</span>
                                            <?php elseif ($sreq['status'] === 'approved'): ?>
                                                <span class="status-badge status-approved">Approved</span>
                                            <?php else: ?>
                                                <span class="status-badge status-rejected">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($sreq['status'] === 'pending'): ?>
                                                <div style="display: flex; gap: 6px;">
                                                    <button type="button" class="btn-action-link btn-approve" onclick="openStatusConfirmModal('approve', <?= $sreq['id'] ?>, '<?= htmlspecialchars($sreq['nutritionist_name'], ENT_QUOTES) ?>', '<?= str_replace('_', ' ', $sreq['requested_status']) ?>')"><i class="fa-solid fa-check"></i> Approve</button>
                                                    <button type="button" class="btn-action-link btn-reject" onclick="openStatusConfirmModal('reject', <?= $sreq['id'] ?>, '<?= htmlspecialchars($sreq['nutritionist_name'], ENT_QUOTES) ?>', '<?= str_replace('_', ' ', $sreq['requested_status']) ?>')"><i class="fa-solid fa-xmark"></i> Reject</button>
                                                </div>
                                            <?php else: ?>
                                                <span style="font-size: 11px; color: #94a3b8; font-style: italic;">Processed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-state">
                                        <div class="empty-icon"><i class="fa-solid fa-circle-check"></i></div>
                                        <h3 style="color:#475569; margin-bottom:5px;">No Status Requests</h3>
                                        <p style="font-size:13px;">No nutritionists have submitted an availability status change request yet.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- SECTION 1: REACTIVATION REQUESTS -->
            <section class="main-card">
                <div class="toolbar">
                    <div class="toolbar-title">
                        <h2>Nutritionist Reactivation Appeals</h2>
                        <span>Total Requests: <?= count($reactivations) ?></span>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Nutritionist Expert</th>
                                <th>Email</th>
                                <th>Reason for Reactivation</th>
                                <th>Attachment</th>
                                <th>Date Requested</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($reactivations)): ?>
                                <?php foreach ($reactivations as $req): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($req['nutritionist_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($req['email']) ?></td>
                                        <td style="max-width: 220px;">
                                            <div style="overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; font-size: 13px; color: #1e293b;">
                                                <?= htmlspecialchars($req['reason']) ?>
                                            </div>
                                            <?php if (strlen($req['reason']) > 60): ?>
                                                <button type="button" style="background: none; border: none; color: #2563eb; font-size: 11px; font-weight: 700; cursor: pointer; padding: 0; margin-top: 4px;" onclick="openReasonModal('Reactivation Reason - ' + <?= htmlspecialchars(json_encode($req['nutritionist_name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($req['reason']), ENT_QUOTES) ?>)">
                                                    View Full Reason &rarr;
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($req['attachment'])): ?>
                                                <a href="../uploads/reactivations/<?= htmlspecialchars($req['attachment']) ?>" target="_blank" class="btn-action-link btn-view" style="font-size:11px; padding: 4px 8px;"><i class="fa-solid fa-paperclip"></i> View File</a>
                                            <?php else: ?>
                                                <span style="font-size: 11px; color: #94a3b8; font-style: italic;">No attachment</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y g:i a', strtotime($req['created_at'])) ?></td>
                                        <td>
                                            <?php if ($req['status'] === 'pending'): ?>
                                                <span class="status-badge status-pending">Pending</span>
                                            <?php elseif ($req['status'] === 'approved'): ?>
                                                <span class="status-badge status-approved">Approved</span>
                                            <?php else: ?>
                                                <span class="status-badge status-rejected">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($req['status'] === 'pending'): ?>
                                                <div style="display: flex; gap: 6px;">
                                                    <button type="button" class="btn-action-link btn-approve" onclick="openConfirmModal('approve', <?= $req['id'] ?>, '<?= htmlspecialchars($req['nutritionist_name'], ENT_QUOTES) ?>')"><i class="fa-solid fa-check"></i> Approve</button>
                                                    <button type="button" class="btn-action-link btn-reject" onclick="openConfirmModal('reject', <?= $req['id'] ?>, '<?= htmlspecialchars($req['nutritionist_name'], ENT_QUOTES) ?>')"><i class="fa-solid fa-xmark"></i> Reject</button>
                                                </div>
                                            <?php else: ?>
                                                <span style="font-size: 11px; color: #94a3b8; font-style: italic;">Processed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <div class="empty-icon"><i class="fa-solid fa-circle-check"></i></div>
                                        <h3 style="color:#475569; margin-bottom:5px;">No Reactivation Requests</h3>
                                        <p style="font-size:13px;">No deactivated nutritionists have submitted an appeal yet.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- SECTION 2: VIOLATION REPORTS -->
            <section class="main-card">
                <div class="toolbar">
                    <div class="toolbar-title">
                        <h2>Submitted Violations & Complaints</h2>
                        <span>Total Reports: <?= count($reports) ?></span>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Reporter (User)</th>
                                <th>Reported Expert</th>
                                <th>Category / Violation</th>
                                <th>Reason / Details</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($reports)): ?>
                                <?php foreach ($reports as $rep): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($rep['user_name']) ?></strong>
                                            <div style="font-size:11px; color:#64748b;"><?= htmlspecialchars($rep['user_email']) ?></div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($rep['nutritionist_name']) ?></strong>
                                            <div style="font-size:11px; color:#64748b;"><?= htmlspecialchars($rep['nutritionist_email']) ?></div>
                                            <div style="margin-top:2px;">
                                                <span class="status-badge <?= $rep['nutritionist_status'] === 'active' ? 'status-approved' : 'status-pending' ?>">
                                                    <?= ucfirst($rep['nutritionist_status']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td><span style="font-weight: 700; color: #b91c1c;"><?= htmlspecialchars($rep['category']) ?></span></td>
                                        <td style="max-width: 250px;">
                                            <div style="overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; font-size: 13px; color: #1e293b;">
                                                <?= htmlspecialchars($rep['reason']) ?>
                                            </div>
                                            <?php if (strlen($rep['reason']) > 60): ?>
                                                <button type="button" style="background: none; border: none; color: #2563eb; font-size: 11px; font-weight: 700; cursor: pointer; padding: 0; margin-top: 4px;" onclick="openReasonModal('Violation Details (' + <?= htmlspecialchars(json_encode($rep['category']), ENT_QUOTES) ?> + ')', <?= htmlspecialchars(json_encode($rep['reason']), ENT_QUOTES) ?>)">
                                                    View Full Details &rarr;
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y g:i a', strtotime($rep['created_at'])) ?></td>
                                        <td>
                                            <?php if ($rep['status'] === 'pending'): ?>
                                                <span class="status-badge status-pending">Pending</span>
                                            <?php elseif ($rep['status'] === 'reviewed'): ?>
                                                <span class="status-badge status-reviewed">Reviewed</span>
                                            <?php else: ?>
                                                <span class="status-badge status-resolved">Resolved</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                                <select class="action-dropdown" onchange="location.href='manage_reports.php?id=<?= $rep['id'] ?>&update_status=' + this.value">
                                                    <option value="" disabled selected>Change Status...</option>
                                                    <option value="pending">Pending</option>
                                                    <option value="reviewed">Reviewed</option>
                                                    <option value="resolved">Resolved</option>
                                                </select>
                                                <a href="manage_account.php" class="btn-action-link btn-view"><i class="fa-solid fa-user-gear"></i> Manage Account</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <div class="empty-icon"><i class="fa-solid fa-circle-check"></i></div>
                                        <h3 style="color:#475569; margin-bottom:5px;">No Reports Found</h3>
                                        <p style="font-size:13px;">There are currently no active complaints or reports against nutritionists.</p>
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

<!-- APPROVE/REJECT CONFIRMATION MODAL -->
<div class="modal" id="actionConfirmModal">
    <div class="confirm-modal-box">
        <div id="confirmIconFrame" class="confirm-icon-frame"><i id="confirmIcon" class="fa-solid fa-check"></i></div>
        <h3 id="confirmTitle">Confirm Action</h3>
        <p id="confirmDesc" style="text-align: center; background: none; border: none; padding: 0;">Are you sure you want to proceed?</p>
        <div class="confirm-modal-actions">
            <button type="button" class="confirm-btn-cancel" onclick="closeConfirmModal()">Cancel</button>
            <a id="confirmActionBtn" href="#" class="confirm-btn-action">Yes, Proceed</a>
        </div>
    </div>
</div>

<!-- VIEW FULL REASON / DETAILS MODAL -->
<div class="modal" id="reasonModal">
    <div class="confirm-modal-box">
        <div class="confirm-icon-frame confirm-icon-info"><i class="fa-solid fa-circle-info"></i></div>
        <h3 id="reasonModalTitle">Full Details</h3>
        <p id="reasonModalText"></p>
        <div class="confirm-modal-actions">
            <button type="button" class="confirm-btn-action confirm-btn-primary" onclick="closeReasonModal()">Close</button>
        </div>
    </div>
</div>

<script>
function openConfirmModal(actionType, requestId, nutritionistName) {
    const modal = document.getElementById('actionConfirmModal');
    const iconFrame = document.getElementById('confirmIconFrame');
    const icon = document.getElementById('confirmIcon');
    const title = document.getElementById('confirmTitle');
    const desc = document.getElementById('confirmDesc');
    const actionBtn = document.getElementById('confirmActionBtn');

    iconFrame.className = "confirm-icon-frame";
    actionBtn.className = "confirm-btn-action";

    if (actionType === 'approve') {
        iconFrame.classList.add('confirm-icon-approve');
        icon.className = "fa-solid fa-shield-check";
        title.textContent = "Reactivate Nutritionist";
        desc.innerHTML = `Are you sure you want to approve and reactivate <strong>${nutritionistName}</strong>'s account? An SMS notification will be automatically sent.`;
        actionBtn.textContent = "Yes, Reactivate";
        actionBtn.classList.add('confirm-btn-success');
    } else if (actionType === 'reject') {
        iconFrame.classList.add('confirm-icon-reject');
        icon.className = "fa-solid fa-triangle-exclamation";
        title.textContent = "Reject Request";
        desc.innerHTML = `Are you sure you want to reject the reactivation request for <strong>${nutritionistName}</strong>?`;
        actionBtn.textContent = "Yes, Reject";
        actionBtn.classList.add('confirm-btn-danger');
    }

    actionBtn.href = `manage_reports.php?reactivate_id=${requestId}&action=${actionType}`;
    modal.classList.add('active');
}

function openStatusConfirmModal(actionType, requestId, nutritionistName, requestedStatus) {
    const modal = document.getElementById('actionConfirmModal');
    const iconFrame = document.getElementById('confirmIconFrame');
    const icon = document.getElementById('confirmIcon');
    const title = document.getElementById('confirmTitle');
    const desc = document.getElementById('confirmDesc');
    const actionBtn = document.getElementById('confirmActionBtn');

    iconFrame.className = "confirm-icon-frame";
    actionBtn.className = "confirm-btn-action";

    if (actionType === 'approve') {
        iconFrame.classList.add('confirm-icon-approve');
        icon.className = "fa-solid fa-circle-check";
        title.textContent = "Approve Status Request";
        desc.innerHTML = `Are you sure you want to approve <strong>${nutritionistName}</strong>'s request to change status to <strong>${requestedStatus}</strong>? An SMS notification will be sent.`;
        actionBtn.textContent = "Yes, Approve";
        actionBtn.classList.add('confirm-btn-success');
    } else if (actionType === 'reject') {
        iconFrame.classList.add('confirm-icon-reject');
        icon.className = "fa-solid fa-triangle-exclamation";
        title.textContent = "Reject Status Request";
        desc.innerHTML = `Are you sure you want to reject <strong>${nutritionistName}</strong>'s status change request? An SMS notification will be sent.`;
        actionBtn.textContent = "Yes, Reject";
        actionBtn.classList.add('confirm-btn-danger');
    }

    actionBtn.href = `manage_reports.php?status_req_id=${requestId}&action=${actionType}`;
    modal.classList.add('active');
}

function openLogoutModal() {
    const modal = document.getElementById('actionConfirmModal');
    const iconFrame = document.getElementById('confirmIconFrame');
    const icon = document.getElementById('confirmIcon');
    const title = document.getElementById('confirmTitle');
    const desc = document.getElementById('confirmDesc');
    const actionBtn = document.getElementById('confirmActionBtn');

    iconFrame.className = "confirm-icon-frame confirm-icon-logout";
    icon.className = "fa-solid fa-door-closed";
    title.textContent = "Confirm Logout";
    desc.textContent = "Are you sure you want to log out of your session?";
    actionBtn.textContent = "Yes, Logout";
    actionBtn.className = "confirm-btn-action confirm-btn-danger";

    actionBtn.href = "../auth/login.php";
    modal.classList.add('active');
}

function closeConfirmModal() {
    document.getElementById('actionConfirmModal').classList.remove('active');
}

function openReasonModal(modalTitle, fullText) {
    document.getElementById('reasonModalTitle').textContent = modalTitle;
    document.getElementById('reasonModalText').textContent = fullText;
    document.getElementById('reasonModal').classList.add('active');
}

function closeReasonModal() {
    document.getElementById('reasonModal').classList.remove('active');
}

window.addEventListener('click', function(e) {
    const confirmModal = document.getElementById('actionConfirmModal');
    const reasonModal = document.getElementById('reasonModal');
    if (e.target === confirmModal) closeConfirmModal();
    if (e.target === reasonModal) closeReasonModal();
});
</script>

</body>
</html>