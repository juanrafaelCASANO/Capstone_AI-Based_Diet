<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

if (isset($_GET['update_status']) && isset($_GET['id'])) {
    $report_id = (int)$_GET['id'];
    $new_status = $_GET['update_status'];
    if (in_array($new_status, ['pending', 'reviewed', 'resolved'])) {
        $stmt = $conn->prepare("UPDATE nutritionist_reports SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $report_id]);
    }
    header("Location: manage_violations.php?msg=status_updated");
    exit();
}

$reports = [];
try {
    $sql = "
        SELECT r.id, r.category, r.reason, r.status, r.created_at, r.attachment,
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

$adminName = $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Violations & Complaints | Admin</title>
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
        
        .quick-nav-bar { display: flex; gap: 12px; margin-bottom: 24px; background: #ffffff; padding: 14px 18px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(15,23,42,.03); }
        .quick-nav-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 10px; background: #f8fafc; border: 1px solid #cbd5e1; color: #334155; font-size: 13px; font-weight: 700; text-decoration: none; transition: all .2s; }
        .quick-nav-btn:hover { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; transform: translateY(-1px); }
        .quick-nav-btn.active { background: #eff6ff; color: #2563eb; border-color: #2563eb; }

        .main-card { background: white; border-radius: 20px; border: 1px solid #e5e7eb; box-shadow: 0 10px 35px rgba(15,23,42,.05); overflow: hidden; }
        .toolbar { padding: 20px 24px; border-bottom: 1px solid #eef2f7; display: flex; justify-content: space-between; align-items: center; }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table th { padding: 14px 24px; background: #f8fafc; color: #64748b; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .report-table td { padding: 14px 24px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .status-badge { display: inline-block; padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-reviewed { background: #e0f2fe; color: #0284c7; }
        .status-resolved { background: #dcfce7; color: #15803d; }
        .btn-action-link { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; border: none; cursor: pointer; }
        .btn-view { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        .action-dropdown { padding: 6px 10px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; font-size: 12px; font-weight: 600; }
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
            <div><h2>AI Diet Planner</h2><span>Admin Panel</span></div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-link"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
            <a href="meals.php" class="sidebar-link"><i class="fa-solid fa-utensils"></i> Meal Management</a>
            <a href="manage_account.php" class="sidebar-link"><i class="fa-solid fa-users-gear"></i> Accounts</a>
            <a href="manage_reports.php" class="sidebar-link active"><i class="fa-solid fa-triangle-exclamation"></i> Reports</a>
            <a href="activity_logs.php" class="sidebar-link"><i class="fa-solid fa-clock-rotate-left"></i> Activity Logs</a>
        </nav>
    </aside>
    <main class="main-content">
        <h1>Reports & Requests</h1>
        <p style="color:#64748b; margin-top:5px; margin-bottom: 20px;">Review nutritionist availability status requests, appeals, and violation reports.</p>

        <!-- NAVIGATION TABS -->
        <div class="quick-nav-bar">
            <a href="manage_reports.php" class="quick-nav-btn">
                <i class="fa-solid fa-clock-rotate-left" style="color:#2563eb;"></i> Availability Requests
            </a>
            <a href="manage_reactivations.php" class="quick-nav-btn">
                <i class="fa-solid fa-user-shield" style="color:#16a34a;"></i> Reactivation Appeals
            </a>
            <a href="manage_violations.php" class="quick-nav-btn active">
                <i class="fa-solid fa-triangle-exclamation" style="color:#dc2626;"></i> Violations & Complaints
            </a>
        </div>

        <div class="main-card">
            <div class="toolbar">
                <h2><i class="fa-solid fa-triangle-exclamation" style="color:#dc2626;"></i> Submitted Violations & Complaints</h2>
                <span>Total Reports: <?= count($reports) ?></span>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Reporter (User)</th>
                        <th>Reported Expert</th>
                        <th>Category</th>
                        <th>Reason / Details</th>
                        <th>Attachment</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($reports)): ?>
                        <?php foreach ($reports as $rep): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($rep['user_name']) ?></strong><br><span style="font-size:11px; color:#64748b;"><?= htmlspecialchars($rep['user_email']) ?></span></td>
                                <td><strong><?= htmlspecialchars($rep['nutritionist_name']) ?></strong><br><span style="font-size:11px; color:#64748b;"><?= htmlspecialchars($rep['nutritionist_email']) ?></span></td>
                                <td><span style="color:#b91c1c; font-weight:700;"><?= htmlspecialchars($rep['category']) ?></span></td>
                                <td><?= htmlspecialchars($rep['reason']) ?></td>
                                <td>
                                    <?php if (!empty($rep['attachment'])): ?>
                                        <?php 
                                            $rawAtt = trim($rep['attachment']);
                                            $repAttachments = [];
                                            
                                            // Suriin kung JSON array o string
                                            $decoded = json_decode($rawAtt, true);
                                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                                $repAttachments = $decoded;
                                            } elseif (strpos($rawAtt, ',') !== false) {
                                                $repAttachments = array_map('trim', explode(',', $rawAtt));
                                            } else {
                                                $repAttachments = [$rawAtt];
                                            }
                                            
                                            $formatted = array_map(function($f) {
                                                $f = trim($f);
                                                if (empty($f)) return '';
                                                if (strpos($f, 'http') === 0) {
                                                    return $f;
                                                }
                                                return '../uploads/reports/' . ltrim($f, '/');
                                            }, array_filter($repAttachments));
                                        ?>
                                        <?php if (!empty($formatted)): ?>
                                            <button type="button" class="btn-action-link btn-view" onclick='openDocsModal(<?= json_encode(array_values($formatted)) ?>, "Violation Report #<?= $rep['id'] ?>")'>
                                                <i class="fa-solid fa-folder-open"></i> View Files (<?= count($formatted) ?>)
                                            </button>
                                        <?php else: ?>
                                            <span style="color:#94a3b8; font-style:italic;">No attachment</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#94a3b8; font-style:italic;">No attachment</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y g:i a', strtotime($rep['created_at'])) ?></td>
                                <td><span class="status-badge status-<?= $rep['status'] ?>"><?= ucfirst($rep['status']) ?></span></td>
                                <td>
                                    <select class="action-dropdown" onchange="location.href='manage_violations.php?id=<?= $rep['id'] ?>&update_status=' + this.value">
                                        <option value="" disabled selected>Change Status...</option>
                                        <option value="pending">Pending</option>
                                        <option value="reviewed">Reviewed</option>
                                        <option value="resolved">Resolved</option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center; padding:40px; color:#94a3b8;">No reports found.</td></tr>
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