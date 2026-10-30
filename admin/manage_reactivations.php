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
            sendTextBeeSMS($phone, "Hello " . $fullname . ", your reactivation request has been APPROVED. Your account is now active.");
        } elseif ($action === 'reject') {
            $upReq = $conn->prepare("UPDATE reactivation_requests SET status = 'rejected' WHERE id = ?");
            $upReq->execute([$req_id]);
            sendTextBeeSMS($phone, "Hello " . $fullname . ", your reactivation request has been rejected.");
        }
    }
    header("Location: manage_reactivations.php?msg=reactivation_handled");
    exit();
}

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

$adminName = $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reactivation Appeals | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f4f7ff; color: #172033; min-height: 100vh; }
        .admin-layout { display: flex; min-height: 100vh; }
        
        .admin-sidebar { width: 255px; min-width: 255px; background: #0f172a; color: #cbd5e1; padding: 22px 15px; position: fixed; height: 100vh; display: flex; flex-direction: column; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 8px 10px 25px; color: #fff; }
        .sidebar-logo { width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; background: linear-gradient(135deg, #3b82f6, #6366f1); color: #fff; font-size: 19px; }
        .sidebar-brand h2 { font-size: 15px; font-weight: 800; color: #fff; }
        .sidebar-brand span { font-size: 11px; color: #94a3b8; }
        .sidebar-section-title { font-size: 10px; text-transform: uppercase; letter-spacing: .12em; color: #64748b; font-weight: 700; padding: 14px 12px 8px; }
        .sidebar-nav { display: flex; flex-direction: column; gap: 4px; }
        .sidebar-link { display: flex; align-items: center; gap: 12px; padding: 12px 13px; border-radius: 11px; color: #cbd5e1; font-size: 13px; font-weight: 600; text-decoration: none; transition: .2s; }
        .sidebar-link i { width: 18px; text-align: center; font-size: 15px; }
        .sidebar-link:hover { background: rgba(59,130,246,.10); color: #fff; }
        .sidebar-link.active { background: rgba(37,99,235,.22); color: #93c5fd; }
        .sidebar-sublink { padding-left: 36px; font-size: 12px; }
        
        .sidebar-bottom { margin-top: auto; border-top: 1px solid #263244; padding-top: 16px; }
        .admin-mini { display: flex; align-items: center; gap: 10px; padding: 0 8px; }
        .avatar { width: 37px; height: 37px; border-radius: 50%; background: linear-gradient(135deg, #60a5fa, #818cf8); color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 13px; }
        .admin-mini strong { color: #fff; font-size: 12px; display: block; }
        .admin-mini span { color: #64748b; font-size: 10px; }

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
        .modal { position: fixed; inset: 0; background: rgba(15, 23, 42, .55); display: none; align-items: center; justify-content: center; padding: 20px; z-index: 1000; }
        .modal.active { display: flex; }
        .confirm-modal-box { width: 100%; max-width: 500px; background: white; border-radius: 24px; padding: 30px; text-align: center; }
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
            <a href="manage_reports.php" class="sidebar-link sidebar-sublink"><i class="fa-solid fa-clock-rotate-left"></i><span>Availability Requests</span></a>
            <a href="manage_reactivations.php" class="sidebar-link sidebar-sublink active"><i class="fa-solid fa-user-shield"></i><span>Reactivation Appeals</span></a>
            <a href="manage_violations.php" class="sidebar-link sidebar-sublink"><i class="fa-solid fa-triangle-exclamation"></i><span>Violations & Reports</span></a>
            <a href="manage_reviews.php" class="sidebar-link sidebar-sublink"><i class="fa-solid fa-star"></i><span>Reviews & Ratings</span></a>

            <a href="activity_logs.php" class="sidebar-link"><i class="fa-solid fa-clock-rotate-left"></i><span>Activity Logs</span></a>
        </nav>

        <div class="sidebar-section-title">System</div>
        <nav class="sidebar-nav">
            <a href="../auth/logout.php" class="sidebar-link"><i class="fa-solid fa-right-from-bracket"></i><span>Sign Out</span></a>
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
        <h1>Nutritionist Reactivation Appeals</h1>
        <p style="color:#64748b; margin-top:5px;">Review and process reactivation requests from deactivated nutritionists.</p>
        <div class="main-card">
            <div class="toolbar">
                <h2>Total Appeals: <?= count($reactivations) ?></h2>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Nutritionist Expert</th>
                        <th>Email</th>
                        <th>Reason / Details</th>
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
                                <td><?= htmlspecialchars($req['reason']) ?></td>
                                <td>
                                    <?php if (!empty($req['attachment'])): ?>
                                        <?php 
                                            $attachments = json_decode($req['attachment'], true);
                                            if (!is_array($attachments)) { $attachments = [$req['attachment']]; }
                                            $formatted = array_map(function($f) {
                                                $f = trim($f);
                                                return (strpos($f, 'http') === 0) ? $f : '../uploads/reports/' . $f;
                                            }, $attachments);
                                        ?>
                                        <button type="button" class="btn-action-link btn-view" onclick='openDocsModal(<?= json_encode($formatted) ?>, "Reactivation Appeal - <?= htmlspecialchars($req['nutritionist_name']) ?>")'>
                                            <i class="fa-solid fa-folder-open"></i> View Files (<?= count($attachments) ?>)
                                        </button>
                                    <?php else: ?>
                                        <span style="color:#94a3b8; font-style:italic;">No attachment</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y g:i a', strtotime($req['created_at'])) ?></td>
                                <td><span class="status-badge status-<?= $req['status'] ?>"><?= ucfirst($req['status']) ?></span></td>
                                <td>
                                    <?php if ($req['status'] === 'pending'): ?>
                                        <div style="display: flex; gap: 6px;">
                                            <a href="manage_reactivations.php?reactivate_id=<?= $req['id'] ?>&action=approve" class="btn-action-link btn-approve"><i class="fa-solid fa-check"></i> Approve</a>
                                            <a href="manage_reactivations.php?reactivate_id=<?= $req['id'] ?>&action=reject" class="btn-action-link btn-reject"><i class="fa-solid fa-xmark"></i> Reject</a>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:#94a3b8; font-style:italic;">Processed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; padding:40px; color:#94a3b8;">No reactivation appeals found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- FILE VIEWER MODAL -->
<div class="modal" id="docsModal">
    <div class="confirm-modal-box">
        <h3 id="docModalTitle" style="margin-bottom: 15px;">Attached Documents</h3>
        <div id="docGridContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; margin-bottom: 20px;"></div>
        <button type="button" class="btn-action-link btn-view" style="width: 100%; justify-content: center; height: 40px;" onclick="closeDocsModal()">Close</button>
    </div>
</div>

<script>
function openDocsModal(docList, titleText) {
    document.getElementById('docModalTitle').textContent = titleText;
    const container = document.getElementById('docGridContainer');
    container.innerHTML = '';
    docList.forEach((url, idx) => {
        const box = document.createElement('div');
        box.style.cssText = 'border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; background: #f8fafc; cursor: pointer; text-align: center;';
        box.innerHTML = `<i class="fa-solid fa-file-lines" style="font-size: 24px; color: #2563eb; margin-bottom: 5px;"></i><div style="font-size: 11px; font-weight: 600;">File ${idx + 1}</div>`;
        box.onclick = () => window.open(url, '_blank');
        container.appendChild(box);
    });
    document.getElementById('docsModal').classList.add('active');
}
function closeDocsModal() {
    document.getElementById('docsModal').classList.remove('active');
}
</script>
</body>
</html>