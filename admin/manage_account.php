<?php
session_start();
require_once '../config.php';

// Security Check
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../auth/login.php");
    exit();
}

// --- LOGIC: ADD NEW EXPERT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expert'])) {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $phone = $_POST['phone']; 
    $experience = $_POST['experience'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $status = 'approved'; 

    $checkEmail = $conn->prepare("SELECT id FROM nutritionist WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    if ($checkEmail->get_result()->num_rows > 0) {
        $error = "Email already exists!";
    } else {
        $stmt = $conn->prepare("INSERT INTO nutritionist (fullname, email, phone, experience, password, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiss", $fullname, $email, $phone, $experience, $password, $status);
        if ($stmt->execute()) {
            header("Location: manage_account.php?msg=expert_added");
            exit();
        }
    }
}

// --- LOGIC: STATUS UPDATES ---
if(isset($_GET['action']) && isset($_GET['id'])){
    $id = (int)$_GET['id'];
    $action = $_GET['action']; 
    $stmt = $conn->prepare("UPDATE nutritionist SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $action, $id);
    if($stmt->execute()){
        header("Location: manage_account.php?msg=status_updated");
        exit();
    }
}

// --- LOGIC: DELETE ACCOUNT ---
if(isset($_GET['delete_id'])){
    $id = (int)$_GET['delete_id'];
    $conn->query("DELETE FROM nutritionist WHERE id = $id");
    header("Location: manage_account.php?msg=deleted");
    exit();
}

// Fetch counts
$pendingCount = $conn->query("SELECT COUNT(*) as total FROM nutritionist WHERE status = 'pending'")->fetch_assoc()['total'];
$approvedCount = $conn->query("SELECT COUNT(*) as total FROM nutritionist WHERE status = 'approved'")->fetch_assoc()['total'];
$result = $conn->query("SELECT * FROM nutritionist ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Experts | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7ff; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 1200px; margin: 0 auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .card { background: #fff; padding: 25px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .filter-section { display: flex; gap: 10px; align-items: center; }
        .filter-btn { padding: 8px 16px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; cursor: pointer; font-size: 13px; font-weight: 600; transition: 0.2s; }
        .filter-btn.active { background: #2563eb; color: white; border-color: #2563eb; }
        .btn-add-new { background: #10b981; color: white; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 6px; text-decoration: none; }
        .btn-add-new:hover { background: #059669; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f8fafc; padding: 15px; text-align: left; font-size: 11px; text-transform: uppercase; color: #64748b; border-bottom: 2px solid #edf2f7; }
        td { padding: 15px; border-bottom: 1px solid #f1f5f9; }
        tr.expert-row:hover { background: #f8fafc; cursor: pointer; }
        .action-group { display: flex; gap: 8px; justify-content: flex-end; align-items: center; }
        .btn-action { padding: 8px 12px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 5px; border: none; cursor: pointer; }
        .btn-review { background: #eff6ff; color: #2563eb; }
        .btn-edit { background: #f0fdf4; color: #16a34a; }
        .btn-delete { background: #fef2f2; color: #dc2626; }
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef9c3; color: #854d0e; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 450px; position: relative; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .close-btn { position: absolute; right: 20px; top: 20px; cursor: pointer; font-size: 20px; color: #64748b; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px; }
        .stat-box { padding: 15px; border-radius: 12px; border: 1px solid; }
        .expert-info { display: flex; align-items: center; gap: 12px; }
        .expert-img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; background: #e2e8f0; }
        .cert-container { margin-top: 10px; border: 2px dashed #e2e8f0; border-radius: 12px; min-height: 200px; display: flex; align-items: center; justify-content: center; background: #f8fafc; overflow: hidden; }
        input { width: 100%; padding: 12px; margin: 8px 0; border-radius: 8px; border: 1px solid #e2e8f0; box-sizing: border-box; font-family: inherit; }
        label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-top: 10px; display: block; }
    </style>
</head>
<body>

<div class="container">
    <div class="top-bar">
        <a href="dashboard.php" style="text-decoration:none; color:#64748b;"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
        <h2 style="margin:0;">Expert Management</h2>
    </div>

    <div class="card">
        <div class="stats-grid">
            <div class="stat-box" style="background:#fffbeb; border-color:#fef3c7;">
                <small style="color:#92400e; font-weight:700;">AWAITING VERIFICATION</small>
                <h2 style="margin:5px 0;"><?= $pendingCount ?></h2>
            </div>
            <div class="stat-box" style="background:#f0fdf4; border-color:#dcfce7;">
                <small style="color:#166534; font-weight:700;">VERIFIED EXPERTS</small>
                <h2 style="margin:5px 0;"><?= $approvedCount ?></h2>
            </div>
        </div>

        <div class="toolbar">
            <div class="filter-section">
                <button id="btnShowAll" class="filter-btn active" onclick="filterTable('all')">All Experts</button>
                <button id="btnShowPending" class="filter-btn" onclick="filterTable('pending')">Pending Only</button>
                <button class="btn-add-new" onclick="document.getElementById('addExpertModal').style.display='flex'">
                    <i class="fa-solid fa-plus"></i> Add New Expert
                </button>
            </div>
        </div>

        <table>
            <thead>
                <tr><th>Expert</th><th>Experience</th><th>Status</th><th style="text-align:right;">Actions</th></tr>
            </thead>
            <tbody id="expertTableBody">
                <?php while($row = $result->fetch_assoc()): ?>
                <tr class="expert-row" data-status="<?= strtolower($row['status']) ?>" onclick='askToVerify(<?= json_encode($row) ?>)'>
                    <td>
                        <div class="expert-info">
                            <img src="../uploads/profile_pics/<?= $row['profile_pic'] ?: 'default_profile.png' ?>" class="expert-img">
                            <div><strong><?= htmlspecialchars($row['fullname']) ?></strong><br><small><?= htmlspecialchars($row['email']) ?></small></div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($row['experience']) ?> </td>
                    <td><span class="badge badge-<?= strtolower($row['status']) ?>"><?= strtoupper($row['status']) ?></span></td>
                    <td style="text-align:right;" onclick="event.stopPropagation();">
                        <div class="action-group">
                            <button class="btn-action btn-review" onclick='askToVerify(<?= json_encode($row) ?>)'>
                                <i class="fa-solid fa-eye"></i> Review
                            </button>
                            <a href="edit_expert.php?id=<?= $row['id'] ?>" class="btn-action btn-edit">
                                <i class="fa-solid fa-pen"></i> Edit
                            </a>
                            <button class="btn-action btn-delete" onclick="openDeleteModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['fullname']) ?>')">
                                <i class="fa-solid fa-trash"></i> Delete
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="addExpertModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('addExpertModal')">&times;</span>
        <h2 style="margin-top:0;">Register New Expert</h2>
        <form method="POST">
            <input type="hidden" name="add_expert" value="1">
            <label>Full Name</label>
            <input type="text" name="fullname" required placeholder="Full Name">
            <label>Email Address</label>
            <input type="email" name="email" required placeholder="Email">
            <label>Phone Number</label>
            <input type="text" name="phone" placeholder="Phone Number">
            <label>Years of Experience</label>
            <input type="number" name="experience" required placeholder="Experience">
            <label>Account Password</label>
            <input type="password" name="password" required placeholder="Password">
            <button type="submit" class="btn-action btn-edit" style="width:100%; justify-content:center; padding:14px; margin-top:15px;">Create Account</button>
        </form>
    </div>
</div>

<div id="profileViewModal" class="modal">
    <div class="modal-content" style="width: 550px;">
        <span class="close-btn" onclick="closeModal('profileViewModal')">&times;</span>
        
        <div style="text-align: center; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 20px;">
            <img id="vProfilePic" src="" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #2563eb; margin-bottom: 10px;">
            <h2 id="vName" style="margin: 0; color: #1e293b;"></h2>
            <p id="vEmail" style="margin: 5px 0; color: #64748b; font-size: 14px;"></p>
            <span id="vStatusBadge" class="badge"></span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <label>Phone Number</label>
                <p id="vPhone" style="margin: 5px 0; font-weight: 600;"></p>
            </div>
            <div>
                <label>Experience</label>
                <p id="vExp" style="margin: 5px 0; font-weight: 600;"></p>
            </div>
        </div>

        <label>Bio / Specialization</label>
        <div id="vCertText" style="background:#f8fafc; padding:12px; border-radius:8px; font-size:13px; border:1px solid #e2e8f0; color: #475569; min-height: 40px;"></div>

        <label>Verification Document</label>
        <div class="cert-container">
            <img id="vCertImg" src="" style="max-width:100%; display:none; cursor:zoom-in;" onclick="window.open(this.src, '_blank')">
            <div id="vPdfLink" style="display:none; padding:20px; text-align:center;">
                <i class="fa-solid fa-file-pdf" style="font-size:40px; color:#dc2626;"></i><br><br>
                <a href="#" id="vDownloadBtn" target="_blank" style="color:#2563eb; font-weight:600; text-decoration: none;">Open PDF Document</a>
            </div>
            <p id="vNoCert" style="display:none; color:#64748b; font-style: italic;">No document uploaded.</p>
        </div>

        <div style="display:flex; gap:10px; margin-top:25px;">
             <a id="vApproveLink" href="#" class="btn-action btn-edit" style="flex:1; justify-content:center; padding:12px;">Approve Expert</a>
             <a id="vRejectLink" href="#" class="btn-action btn-delete" style="flex:1; justify-content:center; padding:12px;">Reject</a>
        </div>
    </div>
</div>

<div id="deleteModal" class="modal">
    <div class="modal-content" style="text-align: center;">
        <i class="fa-solid fa-circle-exclamation" style="font-size: 50px; color: #dc2626; margin-bottom: 15px;"></i>
        <h2>Delete Account?</h2>
        <p>Are you sure you want to delete <b id="delName"></b>?</p>
        <div style="display: flex; gap: 10px; justify-content: center; margin-top: 25px;">
            <button class="filter-btn" onclick="closeModal('deleteModal')">Cancel</button>
            <a id="delConfirmLink" href="#" class="btn-action btn-delete" style="padding:10px 20px;">Confirm Delete</a>
        </div>
    </div>
</div>

<script>
    function filterTable(type) {
        const rows = document.querySelectorAll('.expert-row');
        const btnAll = document.getElementById('btnShowAll');
        const btnPending = document.getElementById('btnShowPending');

        if(type === 'all') {
            btnAll.classList.add('active');
            btnPending.classList.remove('active');
            rows.forEach(row => row.style.display = '');
        } else {
            btnPending.classList.add('active');
            btnAll.classList.remove('active');
            rows.forEach(row => {
                row.style.display = (row.getAttribute('data-status') === 'pending') ? '' : 'none';
            });
        }
    }

    function askToVerify(data) {
        // Prevent event bubbling if triggered from a button click inside the row
        if(window.event) window.event.stopPropagation();

        document.getElementById('vName').innerText = data.fullname;
        document.getElementById('vEmail').innerText = data.email;
        document.getElementById('vPhone').innerText = data.phone || "Not Provided";
        document.getElementById('vExp').innerText = data.experience + " Years";
        document.getElementById('vCertText').innerText = data.certification || "No details provided.";
        
        const badge = document.getElementById('vStatusBadge');
        badge.innerText = data.status.toUpperCase();
        badge.className = 'badge badge-' + data.status.toLowerCase();

        // Profile Picture: Located in uploads/profile_pics/
        document.getElementById('vProfilePic').src = "../uploads/profile_pics/" + (data.profile_pic ? data.profile_pic : "default_profile.png");

        // Document logic: Located in uploads/
        const img = document.getElementById('vCertImg');
        const pdf = document.getElementById('vPdfLink');
        const none = document.getElementById('vNoCert');
        
        // Reset visibility to clear previous expert's data
        img.style.display = 'none';
        pdf.style.display = 'none';
        none.style.display = 'none';

        if(data.document && data.document.trim() !== "") {
            // Updated path to point to the main uploads folder as seen in your file explorer
            const src = "../uploads/" + data.document; 
            const ext = data.document.split('.').pop().toLowerCase();
            
            if(['jpg','jpeg','png', 'webp'].includes(ext)) { 
                img.src = src; 
                img.style.display = 'block'; 
            } else if(ext === 'pdf') { 
                document.getElementById('vDownloadBtn').href = src; 
                pdf.style.display = 'block'; 
            } else {
                none.style.display = 'block';
                none.innerText = "Unsupported file format (" + ext + ")";
            }
        } else { 
            none.style.display = 'block'; 
        }

        document.getElementById('vApproveLink').href = "manage_account.php?action=approved&id=" + data.id;
        document.getElementById('vRejectLink').href = "manage_account.php?action=rejected&id=" + data.id;
        document.getElementById('profileViewModal').style.display = 'flex';
    }

    function openDeleteModal(id, name) {
        document.getElementById('delName').innerText = name;
        document.getElementById('delConfirmLink').href = "manage_account.php?delete_id=" + id;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    window.onclick = function(e) { if(e.target.className === 'modal') e.target.style.display = 'none'; }
</script>
</body>
</html>