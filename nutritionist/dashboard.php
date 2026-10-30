<?php
session_start();
require_once '../config.php';

// Ensure user is logged in AND is a nutritionist
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'nutritionist') {
    header("Location: ../auth/login.php");
    exit;
}

$nutri_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

// Handle Status Change Request Submission with Supabase Storage File Upload
if (isset($_POST['request_status_change'])) {
    $requested_status = $_POST['requested_status'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    
    // File Upload Handling via Supabase Storage Bucket
    $cert_filename = null;
    if (isset($_FILES['certificate_file']) && $_FILES['certificate_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['certificate_file']['tmp_name'];
        $fileName = $_FILES['certificate_file']['name'];
        $fileType = $_FILES['certificate_file']['type'];
        $file_extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];
        if (in_array($file_extension, $allowed_types)) {
            // Generate unique filename
            $remote_filename = 'cert_' . $nutri_id . '_' . time() . '.' . $file_extension;
            
            // Supabase Credentials (Awtomatikong babasahin ang SUPABASE_SERVICE_KEY o SUPABASE_KEY)
            $supabaseUrl = getenv('SUPABASE_URL') ?: 'YOUR_SUPABASE_URL';
            $supabaseKey = getenv('SUPABASE_SERVICE_KEY') ?: (getenv('SUPABASE_KEY') ?: 'YOUR_SUPABASE_KEY');
            $bucketName = 'nutritionist-files'; // Ang iyong bucket sa Supabase
            
            $url = rtrim($supabaseUrl, '/') . "/storage/v1/object/$bucketName/$remote_filename";
            
            $fileData = file_get_contents($fileTmpPath);
            
            $headers = [
                "Authorization: Bearer $supabaseKey",
                "Content-Type: $fileType",
                "x-upsert: true"
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 || $httpCode === 201) {
                $cert_filename = $remote_filename; // Tagumpay ang pag-upload sa Supabase bucket
            } else {
                $error_msg = "Failed to upload file to Supabase Storage. (HTTP Code: $httpCode)";
            }
        } else {
            $error_msg = "Invalid file format. Please upload PDF, JPG, or PNG only.";
        }
    }
    
    if (empty($error_msg) && !empty($requested_status) && !empty($reason)) {
        if (in_array($requested_status, ['available', 'on_leave', 'unavailable'])) {
            try {
                $reqStmt = $conn->prepare("INSERT INTO nutritionist_status_requests (nutritionist_id, requested_status, reason, certificate_file, status) VALUES (?, ?, ?, ?, 'pending')");
                $reqStmt->execute([$nutri_id, $requested_status, $reason, $cert_filename]);
                $success_msg = "Your status change request has been successfully submitted to the administrator for approval.";
            } catch (Exception $e) {
                try {
                     $reqStmt = $conn->prepare("INSERT INTO nutritionist_status_requests (nutritionist_id, requested_status, reason, status) VALUES (?, ?, ?, 'pending')");
                     $reqStmt->execute([$nutri_id, $requested_status, $reason]);
                     $success_msg = "Your status change request has been submitted (File was ignored due to database structure).";
                } catch(Exception $e2) {
                     $error_msg = "Failed to submit request. Please try again.";
                }
            }
        }
    } elseif(empty($error_msg)) {
        $error_msg = "Please select a status and provide a reason.";
    }
}

// Fetch nutritionist profile info and check status (Must be 'active')
$stmt = $conn->prepare("SELECT fullname, email, status, availability_status FROM nutritionist WHERE id = ?");
$stmt->execute([$nutri_id]);
$nutri = $stmt->fetch();

if (!$nutri || ($nutri['status'] ?? '') !== 'active') {
    session_destroy();
    header("Location: ../auth/login.php?error=account_inactive");
    exit;
}

// Check if there's a pending status request
$pending_req = null;
try {
    $pendStmt = $conn->prepare("SELECT * FROM nutritionist_status_requests WHERE nutritionist_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
    $pendStmt->execute([$nutri_id]);
    $pending_req = $pendStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$unread_count = 0; 
$latest_message = null;
$pending_appointments_count = 0;

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND status = 0");
    $stmt->execute([$nutri_id]);
    $row = $stmt->fetch();
    if ($row) {
        $unread_count = $row['total'];
    }

    $msg_stmt = $conn->prepare("
        SELECT m.*, u.fullname as sender_name 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.receiver_id = ? 
        ORDER BY m.created_at DESC 
        LIMIT 1
    ");
    $msg_stmt->execute([$nutri_id]);
    $latest_message = $msg_stmt->fetch();

    // Bilang ng mga pending appointments
    $app_count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE nutritionist_id = ? AND status = 'pending'");
    $app_count_stmt->execute([$nutri_id]);
    $app_row = $app_count_stmt->fetch();
    if ($app_row) {
        $pending_appointments_count = (int)$app_row['total'];
    }
} catch (Exception $e) {
    $unread_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nutritionist Portal | AI-Based Diet Planner</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🩺</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
:root{
  --navy:#0f3a2e;
  --navy-2:#165242;
  --teal:#0d9488;
  --teal-soft:#ccfbf1;
  --teal-dark:#0f766e;
  --cream:#f4fbf7;
  --card:#ffffff;
  --text:#112d25;
  --muted:#5c736c;
  --border:#dbece5;
  --shadow:0 10px 30px rgba(15,58,46,.08);
  --radius:18px;
  --danger:#dc2626;
  --danger-bg:#fef2f2;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  margin:0;
  font-family: 'Plus Jakarta Sans', sans-serif;
  background:var(--cream);
  color:var(--text);
}
button,a{font:inherit}
a{color:inherit}

.app{min-height:100vh;display:flex;position:relative;}

/* SIDEBAR STYLES */
.sidebar{
  width:260px; flex:0 0 260px; height:100vh; position:sticky; top:0; align-self:flex-start;
  background:linear-gradient(180deg,var(--navy),#0a2820); color:#fff;
  padding:24px 18px; display:flex; flex-direction:column; overflow:hidden; z-index:20;
}
.brand{ display:flex;align-items:center;gap:12px; padding:6px 10px 28px; flex-shrink:0; }
.brand-icon{ width:42px;height:42px;border-radius:13px; display:grid;place-items:center;background:rgba(255,255,255,.12); font-size:23px; }
.brand strong{display:block;font-size:17px;letter-spacing:-.2px;}
.brand span{display:block;color:#99f6e4;font-size:12px;margin-top:2px;}
.nav-label{ padding:0 12px 8px;color:#80e0d0; text-transform:uppercase;letter-spacing:.11em;font-size:10px;font-weight:800; flex-shrink:0; }
.nav{display:grid;gap:6px;flex-shrink:0}
.nav a{ display:flex;align-items:center;gap:12px; text-decoration:none;padding:13px 12px;border-radius:12px; color:#e6fffa;font-size:14px;font-weight:650; transition:background .2s,transform .2s; position:relative; }
.nav a:hover{background:rgba(255,255,255,.09);transform:translateX(2px)}
.nav a.active{background:rgba(255,255,255,.14);color:#fff}
.nav-icon{width:24px;text-align:center;font-size:17px}
.nav-badge{ margin-left:auto; background:var(--danger); color:#fff; font-size:11px; font-weight:800; padding:2px 8px; border-radius:999px; }
.sidebar-bottom{margin-top:auto;border-top:1px solid rgba(255,255,255,.1);padding-top:16px;flex-shrink:0}
.sidebar-bottom a.logout{ display:flex;align-items:center;gap:12px;text-decoration:none;padding:13px 12px; border-radius:12px;color:#f8d7da!important;font-size:14px;font-weight:650; transition:background .2s,transform .2s; }
.sidebar-bottom a.logout:hover{background:rgba(220,53,69,.2);transform:translateX(2px)}

/* MAIN CONTAINER */
.main{min-width:0;flex:1;padding:26px clamp(18px,4vw,48px) 48px}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:26px}
.menu-btn{ display:none;border:1px solid var(--border);background:#fff; width:44px;height:44px;border-radius:12px;cursor:pointer;color:var(--text); }
.eyebrow{font-size:12px;font-weight:800;color:var(--teal);text-transform:uppercase;letter-spacing:.1em}
.welcome h1{font-size:clamp(25px,3vw,36px);line-height:1.15;margin:5px 0 0;letter-spacing:-.8px}
.profile{ display:flex;align-items:center;gap:10px;padding:7px 11px 7px 7px; background:#fff;border:1px solid var(--border);border-radius:999px; text-decoration:none;transition:border-color .2s,box-shadow .2s; }
.profile:hover{border-color:#99f6e4;box-shadow:0 4px 12px rgba(13,148,136,.08)}
.avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:var(--teal-soft);color:var(--teal-dark);font-weight:800}
.profile-name{font-size:13px;font-weight:700;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* TOP DASHBOARD GRID */
.dashboard-top-grid {
  display: grid;
  grid-template-columns: 1.6fr 1fr;
  gap: 20px;
  margin-bottom: 24px;
}

/* HERO PANEL */
.hero{
  background:linear-gradient(135deg,#e6fffa 0%,#f0fdfa 70%);
  border:1px solid #ccfbf1;border-radius:24px;padding:clamp(24px,3vw,32px);
  position:relative; overflow:hidden;
  display: flex; flex-direction: column; justify-content: center;
}
.hero:after{ content:"🩺";position:absolute;right:5%;bottom:-20px;font-size:130px;opacity:.08;pointer-events:none; }
.hero h2{font-size:clamp(24px,3vw,34px);line-height:1.1;letter-spacing:-1px;margin:8px 0 12px;max-width:90%; position:relative; z-index:2;}
.hero p{color:var(--muted);max-width:85%;line-height:1.6;margin:0 0 24px; font-size:14px; position:relative; z-index:2;}
.actions{display:flex;flex-wrap:wrap;gap:10px; position:relative; z-index:2;}
.btn{ display:inline-flex;align-items:center;justify-content:center;gap:8px; min-height:44px;padding:0 18px;border-radius:12px;border:1px solid transparent; text-decoration:none;font-weight:750;font-size:13px;cursor:pointer; transition:transform .2s,box-shadow .2s,background .2s; }
.btn:hover{transform:translateY(-1px)}
.btn-primary{background:var(--teal);color:#fff;box-shadow:0 8px 18px rgba(13,148,136,.25)}
.btn-secondary{background:#fff;color:var(--navy);border-color:var(--border)}

/* AVAILABILITY WIDGET */
.availability-card {
  background: #ffffff;
  border: 1px solid var(--border);
  border-radius: 24px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 4px 15px rgba(15,58,46,.03);
}
.availability-info h4 { margin: 0 0 6px; font-size: 15px; color: var(--navy); display:flex; align-items:center; }
.availability-info p { margin: 0 0 15px; font-size: 12px; color: var(--muted); line-height: 1.5; }

.availability-form { display: flex; flex-direction: column; gap: 10px; }
.status-select {
  width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border); background: #f8fafc; font-family: inherit; font-weight: 600; font-size: 13px; color: var(--text); outline: none; cursor:pointer;
}
.status-select:focus { border-color: var(--teal); background: #fff;}
.status-textarea {
  width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border); background: #f8fafc; font-family: inherit; font-size: 13px; color: var(--text); resize: none; min-height: 60px; outline: none;
}
.status-textarea:focus { border-color: var(--teal); background: #fff;}

/* CUSTOM FILE INPUT */
.file-upload-wrapper { position: relative; width: 100%; }
.file-upload-input {
  width: 100%; padding: 8px 10px 8px 36px; border-radius: 10px; border: 1px dashed var(--teal); background: var(--teal-soft); font-family: inherit; font-size: 12px; color: var(--teal-dark); cursor: pointer;
}
.file-upload-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--teal-dark); font-size: 14px; pointer-events: none; }
.file-upload-input::file-selector-button { display: none; }

.btn-status-save { background: var(--teal); color: #fff; border: none; padding: 10px 16px; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; transition: background .2s; width: 100%; margin-top: 5px; }
.btn-status-save:hover { background: var(--teal-dark); }

.status-indicator { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 8px; }
.status-available { background: #10b981; }
.status-on_leave { background: #f59e0b; }
.status-unavailable { background: #ef4444; }

.alert-success { background: #f0fdf4; color: #166534; padding: 10px 14px; border-radius: 10px; font-size: 12px; border: 1px solid #bbf7d0; font-weight: 600; margin-bottom:15px; }
.alert-error { background: #fff1f2; color: #9f1239; padding: 10px 14px; border-radius: 10px; font-size: 12px; border: 1px solid #fecdd3; font-weight: 600; margin-bottom:15px; }
.alert-pending { background: #fef3c7; color: #d97706; padding: 14px; border-radius: 12px; font-size: 13px; border: 1px dashed #f59e0b; font-weight: 600; text-align:center; line-height:1.5;}

.section-title{display:flex;align-items:end;justify-content:space-between;gap:15px;margin:30px 0 14px}
.section-title h3{margin:0;font-size:18px}
.section-title p{margin:0;color:var(--muted);font-size:13px}

.cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
.card{ background:var(--card);border:1px solid var(--border);border-radius:var(--radius); padding:22px;box-shadow:0 5px 18px rgba(15,58,46,.04); transition:transform .2s,box-shadow .2s,border-color .2s; }
.card:hover{transform:translateY(-3px);box-shadow:var(--shadow);border-color:#b2e3d8}
.card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}
.card-icon{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;background:var(--teal-soft);font-size:22px}
.card h3{margin:15px 0 7px;font-size:19px}
.card p{margin:0;color:var(--muted);line-height:1.6;font-size:14px}
.card .btn{margin-top:19px}

.quick-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.quick{ background:#fff;border:1px solid var(--border);border-radius:15px;padding:17px; text-decoration:none;display:block;transition:transform .2s,border-color .2s; }
.quick:hover{transform:translateY(-2px);border-color:#b2e3d8}
.quick b{font-size:14px}
.quick span{display:block;color:var(--muted);font-size:12px;margin-top:5px;line-height:1.5}

/* NOTIFICATION TOAST & MODAL STYLES */
.bottom-right-toast { position: fixed; bottom: 24px; right: 24px; width: 320px; background: #ffffff; border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 12px 35px rgba(15, 58, 46, 0.15); padding: 16px; z-index: 998; display: flex; flex-direction: column; gap: 10px; animation: slideUpToast 0.3s ease-out; }
@keyframes slideUpToast { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.toast-header { display: flex; align-items: center; justify-content: space-between; }
.toast-title-group { display: flex; align-items: center; gap: 8px; }
.toast-icon { width: 32px; height: 32px; background: var(--teal-soft); color: var(--teal-dark); border-radius: 8px; display: grid; place-items: center; font-size: 15px; }
.toast-title-group strong { font-size: 14px; color: var(--text); }
.toast-close { background: transparent; border: none; font-size: 16px; color: var(--muted); cursor: pointer; padding: 0; line-height: 1; }
.toast-close:hover { color: var(--text); }
.toast-body { font-size: 13px; color: var(--muted); line-height: 1.4; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.toast-footer { display: flex; justify-content: flex-end; gap: 8px; margin-top: 4px; }
.toast-btn { font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
.toast-btn-primary { background: var(--teal); color: #fff; }
.toast-btn-primary:hover { background: var(--teal-dark); }

.logout-modal-overlay { position: fixed; inset: 0; background: rgba(15, 58, 46, 0.5); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 9999; opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s ease; }
.logout-modal-overlay.active { opacity: 1; visibility: visible; }
.logout-modal-card { background: #ffffff; width: 90%; max-width: 380px; border-radius: 20px; padding: 28px 24px; text-align: center; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); transform: scale(0.85); transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.logout-modal-overlay.active .logout-modal-card { transform: scale(1); }
.logout-modal-icon { width: 56px; height: 56px; background: #fef2f2; color: #dc2626; font-size: 26px; border-radius: 50%; display: grid; place-items: center; margin: 0 auto 16px; }
.logout-modal-card h3 { margin: 0 0 8px; font-size: 20px; font-weight: 700; color: #112d25; }
.logout-modal-card p { margin: 0 0 24px; font-size: 14px; color: #5c736c; line-height: 1.5; }
.logout-modal-actions { display: flex; gap: 12px; }
.btn-modal-cancel { flex: 1; height: 44px; background: #f4fbf7; color: #112d25; border: 1px solid #dbece5; border-radius: 12px; font-weight: 650; font-size: 14px; cursor: pointer; transition: background 0.2s; }
.btn-modal-cancel:hover { background: #e6fffa; }
.btn-modal-logout { flex: 1; height: 44px; background: #dc2626; color: #ffffff; border: none; border-radius: 12px; font-weight: 650; font-size: 14px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: background 0.2s, transform 0.2s; }
.btn-modal-logout:hover { background: #b91c1c; transform: translateY(-1px); }

.overlay{display:none}

@media(max-width:1100px){
  .dashboard-top-grid { grid-template-columns: 1fr; }
}
@media(max-width:900px){
  .sidebar{width:240px;flex-basis:240px}
  .quick-grid{grid-template-columns:1fr}
}
@media(max-width:720px){
  .app{display:block}
  .sidebar{ position:fixed;left:0;top:0;bottom:0;min-height:100vh; transform:translateX(-105%);box-shadow:20px 0 40px rgba(0,0,0,.16); }
  .sidebar.open{transform:translateX(0)}
  .overlay{ position:fixed;inset:0;background:rgba(10,40,32,.42);z-index:15; }
  .overlay.show{display:block}
  .main{padding:17px 15px 35px}
  .menu-btn{display:grid;place-items:center}
  .topbar{align-items:center;margin-bottom:20px}
  .profile-name{display:none}
  .cards{grid-template-columns:1fr}
  .hero{padding:22px;border-radius:19px}
  .hero h2{font-size:29px}
  .hero-stat{display:none}
  .section-title{align-items:flex-start;flex-direction:column;gap:4px}
  .bottom-right-toast { right: 15px; left: 15px; width: auto; }
}
@media(max-width:420px){
  .actions .btn{width:100%}
  .welcome h1{font-size:25px}
}
</style>
</head>

<body>
<div class="app">
  <div class="overlay" id="overlay" aria-hidden="true"></div>

  <!-- SIDEBAR NAVIGATION -->
  <aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="brand">
      <div class="brand-icon">🩺</div>
      <div>
        <strong>NutriPanel</strong>
        <span>Nutritionist portal</span>
      </div>
    </div>

    <div class="nav-label">Menu</div>
    <nav class="nav">
      <a class="active" href="dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
      <a href="leaderboard.php"><span class="nav-icon">🏆</span>Leaderboard</a>
      <a href="appointments.php">
        <span class="nav-icon">📅</span>Appointments
        <?php if($pending_appointments_count > 0): ?>
          <span class="nav-badge"><?php echo $pending_appointments_count; ?></span>
        <?php endif; ?>
      </a>
      <a href="add_meal.php"><span class="nav-icon">🍲</span>Add Meal</a>
      <a href="inbox.php">
        <span class="nav-icon">📥</span>Inbox
        <?php if($unread_count > 0): ?>
          <span class="nav-badge"><?php echo $unread_count; ?></span>
        <?php endif; ?>
      </a>
      <a href="profile.php"><span class="nav-icon">👤</span>My Profile</a>
    </nav>
    <div class="sidebar-bottom">
      <a class="logout" href="javascript:void(0);" onclick="showLogoutModal();"><span class="nav-icon">↪</span>Logout</a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px">
        <button class="menu-btn" id="menuBtn" type="button" aria-label="Open navigation" aria-expanded="false">☰</button>
        <div class="welcome">
          <div class="eyebrow">Nutritionist Workspace</div>
          <h1>Welcome back, <?php echo htmlspecialchars($nutri['fullname'] ?? 'Doctor'); ?> 👋</h1>
        </div>
      </div>
      <a class="profile" href="profile.php" title="View & Edit Profile">
        <div class="avatar">
          <?php echo htmlspecialchars(strtoupper(substr($nutri['fullname'] ?? 'N', 0, 1))); ?>
        </div>
        <span class="profile-name"><?php echo htmlspecialchars($nutri['fullname'] ?? 'Nutritionist'); ?></span>
      </a>
    </header>

    <!-- TWO COLUMN GRID FOR HERO AND STATUS FORM -->
    <div class="dashboard-top-grid">
        
        <!-- LEFT COLUMN: HERO PANEL -->
        <section class="hero">
          <div>
            <div class="eyebrow">Client Consultation</div>
            <h2>Help users reach their health & dietary goals.</h2>
            <p>Manage user inquiries, evaluate AI-generated meal plans, and deliver personalized diet suggestions efficiently.</p>
            <div class="actions">
              <a class="btn btn-primary" href="inbox.php">📥 Open Client Inbox (<?php echo $unread_count; ?>)</a>
              <a class="btn btn-secondary" href="profile.php">⚙️ Account Settings</a>
            </div>
          </div>
        </section>

        <!-- RIGHT COLUMN: AVAILABILITY STATUS REQUEST WIDGET -->
        <div class="availability-card">
          <div class="availability-info">
            <h4>
              <span class="status-indicator status-<?php echo htmlspecialchars($nutri['availability_status'] ?? 'available'); ?>"></span>
              Status: 
              <span style="text-transform: capitalize; color: var(--teal-dark); margin-left:5px;">
                <?php echo str_replace('_', ' ', htmlspecialchars($nutri['availability_status'] ?? 'available')); ?>
              </span>
            </h4>
            <p>Request an availability change (e.g., for personal leave).</p>
          </div>

          <?php if (!empty($success_msg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success_msg); ?></div>
          <?php endif; ?>
          <?php if (!empty($error_msg)): ?>
            <div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error_msg); ?></div>
          <?php endif; ?>

          <?php if ($pending_req): ?>
            <div class="alert-pending">
              <i class="fa-solid fa-clock" style="font-size:20px; display:block; margin:0 auto 10px;"></i> 
              You have a pending request to change status to <br><strong><?php echo ucfirst(str_replace('_', ' ', $pending_req['requested_status'])); ?></strong>.
            </div>
          <?php else: ?>
            <!-- Added enctype for file upload -->
            <form method="POST" enctype="multipart/form-data" class="availability-form">
              <select name="requested_status" class="status-select" required>
                <option value="" disabled selected>Select desired status...</option>
                <option value="available">🟢 Available</option>
                <option value="on_leave">🟡 On Leave / Sick</option>
                <option value="unavailable">🔴 Unavailable</option>
              </select>
              
              <textarea name="reason" class="status-textarea" placeholder="Reason for status change..." required></textarea>
              
              <!-- FILE ATTACHMENT FIELD -->
              <div class="file-upload-wrapper">
                <i class="fa-solid fa-paperclip file-upload-icon"></i>
                <input type="file" name="certificate_file" class="file-upload-input" accept=".pdf,.jpg,.jpeg,.png">
              </div>
              <small style="color:var(--muted); font-size:10px; margin-top:-6px; padding-left:5px;">Optional: Attach Medical Cert (PDF, JPG, PNG)</small>
              
              <button type="submit" name="request_status_change" class="btn-status-save"><i class="fa-regular fa-paper-plane" style="margin-right:5px;"></i> Submit Request</button>
            </form>
          <?php endif; ?>
        </div>
        
    </div> <!-- End Dashboard Top Grid -->

    <div class="section-title">
      <div>
        <h3>Overview & Management</h3>
        <p>Choose an action to manage client consultations.</p>
      </div>
    </div>

    <!-- MAIN ACTION CARDS -->
    <section class="cards">
      <article class="card">
        <div class="card-top">
          <div class="card-icon">📅</div>
        </div>
        <h3>Appointments</h3>
        <p>Review and confirm schedule consultation requests sent by registered users in real-time.</p>
        <a class="btn btn-primary" href="appointments.php">Manage Bookings (<?php echo $pending_appointments_count; ?>) <span>→</span></a>
      </article>

      <article class="card">
        <div class="card-top">
          <div class="card-icon">📬</div>
        </div>
        <h3>Client Messages</h3>
        <p>Review incoming inquiries from registered users seeking professional dietary guidance and plan adjustments.</p>
        <a class="btn btn-secondary" href="inbox.php">View Inbox (<?php echo $unread_count; ?>) <span>→</span></a>
      </article>

      <article class="card">
        <div class="card-top">
          <div class="card-icon">👤</div>
        </div>
        <h3>Professional Profile</h3>
        <p>Update your credentials, availability status, contact detail records, and consultation account settings.</p>
        <a class="btn btn-secondary" href="profile.php">Manage Profile <span>→</span></a>
      </article>
    </section>

    <div class="section-title">
      <div>
        <h3>Quick navigation</h3>
        <p>Access primary workspace sections.</p>
      </div>
    </div>

    <section class="quick-grid">
      <a class="quick" href="appointments.php">
        <b>📅 Manage Bookings</b>
        <span>Confirm or cancel patient schedules.</span>
      </a>
      <a class="quick" href="inbox.php">
        <b>📥 Patient Inbox</b>
        <span>Read & reply to active user chats.</span>
      </a>
      <a class="quick" href="javascript:void(0);" onclick="showLogoutModal();">
        <b>↪ End Session</b>
        <span>Log out of the practitioner portal safely.</span>
      </a>
    </section>
  </main>

  <!-- LOWER RIGHT NOTIFICATION BAR / TOAST -->
  <?php if ($latest_message && $unread_count > 0): ?>
  <div id="notificationToast" class="bottom-right-toast">
    <div class="toast-header">
      <div class="toast-title-group">
        <div class="toast-icon">💬</div>
        <strong>New Assistance Request</strong>
      </div>
      <button class="toast-close" onclick="closeToast()" aria-label="Close notification">&times;</button>
    </div>
    <p class="toast-body">
      <b><?php echo htmlspecialchars($latest_message['sender_name'] ?? 'User'); ?>:</b> 
      <?php echo htmlspecialchars($latest_message['message'] ?? 'Needs your assistance regarding their meal plan.'); ?>
    </p>
    <div class="toast-footer">
      <a href="inbox.php" class="toast-btn toast-btn-primary">View in Inbox →</a>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- LOGOUT CONFIRMATION MODAL -->
<div id="logoutModal" class="logout-modal-overlay">
  <div class="logout-modal-card">
    <div class="logout-modal-icon">🚪</div>
    <h3>Confirm Logout</h3>
    <p>Are you sure you want to log out of your session?</p>
    <div class="logout-modal-actions">
      <button class="btn-modal-cancel" onclick="closeLogoutModal();">Cancel</button>
      <a href="../auth/logout.php" class="btn-modal-logout">Yes, Logout</a>
    </div>
  </div>
</div>

<!-- jQuery for background activity heartbeat -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Background Heartbeat para sa Dashboard at Inbox Status
setInterval(function() {
    $.post('update_activity.php', function(response) {
        // Tahimik na nag-a-update sa background
    });
}, 60000);

// File Upload Label Update (Shows filename when selected)
const fileInput = document.querySelector('.file-upload-input');
if(fileInput) {
    fileInput.addEventListener('change', function(e) {
        if(e.target.files.length > 0) {
            let fileName = e.target.files[0].name;
            if(fileName.length > 25) fileName = fileName.substring(0, 22) + '...';
            
            const smallText = this.parentElement.nextElementSibling;
            if(smallText) {
                smallText.innerHTML = '<span style="color:var(--teal-dark); font-weight:bold;">Attached: </span>' + fileName;
            }
        }
    });
}

function closeToast() {
  const toast = document.getElementById('notificationToast');
  if (toast) {
    toast.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    setTimeout(() => toast.remove(), 200);
  }
}

function showLogoutModal() {
  document.getElementById('logoutModal').classList.add('active');
}

function closeLogoutModal() {
  document.getElementById('logoutModal').classList.remove('active');
}

const logoutModalEl = document.getElementById('logoutModal');
if(logoutModalEl) {
    logoutModalEl.addEventListener('click', function(e) {
      if (e.target === this) {
        closeLogoutModal();
      }
    });
}

(function(){
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');
  const menuBtn = document.getElementById('menuBtn');

  if(!sidebar || !overlay || !menuBtn) return;

  function setMenu(open){
    sidebar.classList.toggle('open', open);
    overlay.classList.toggle('show', open);
    menuBtn.setAttribute('aria-expanded', String(open));
    overlay.setAttribute('aria-hidden', String(!open));
  }

  menuBtn.addEventListener('click', function(){
    setMenu(!sidebar.classList.contains('open'));
  });

  overlay.addEventListener('click', function(){ setMenu(false); });

  sidebar.querySelectorAll('a').forEach(function(link){
    link.addEventListener('click', function(){
      if(window.innerWidth <= 720) setMenu(false);
    });
  });

  window.addEventListener('resize', function(){
    if(window.innerWidth > 720) setMenu(false);
  });
})();
</script>

</body>
</html>