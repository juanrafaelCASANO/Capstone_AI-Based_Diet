<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$nutritionist_id = isset($_GET['nutritionist_id']) ? (int)$_GET['nutritionist_id'] : 0;

if ($nutritionist_id <= 0) {
    header("Location: user_inbox.php");
    exit;
}

$report_msg = "";
$report_error = "";

// Handle Report Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $category = trim($_POST['category'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if (empty($category) || empty($reason)) {
        $report_error = "Please select a category and provide a reason for the report.";
    } else {
        try {
            $rep_stmt = $conn->prepare("INSERT INTO nutritionist_reports (user_id, nutritionist_id, category, reason, status) VALUES (?, ?, ?, ?, 'pending')");
            $rep_stmt->execute([$user_id, $nutritionist_id, $category, $reason]);
            $report_msg = "Your report has been successfully submitted to the administrator for review.";
        } catch (Exception $e) {
            $report_error = "Failed to submit report. Please try again.";
        }
    }
}

// Get user latitude and longitude
$user_lat = 14.3498;
$user_lng = 121.0794;
try {
    $user_loc_stmt = $conn->prepare("SELECT latitude, longitude FROM users WHERE id = ? LIMIT 1");
    $user_loc_stmt->execute([$user_id]);
    $user_data = $user_loc_stmt->fetch(PDO::FETCH_ASSOC);
    if ($user_data && !empty($user_data['latitude']) && !empty($user_data['longitude'])) {
        $user_lat = floatval($user_data['latitude']);
        $user_lng = floatval($user_data['longitude']);
    }
} catch (Exception $e) {
    // Use default if error occurs
}

// Get specific nutritionist information including availability status and last_active_at
$nutri_stmt = $conn->prepare("
    SELECT n.id, n.fullname, n.email, n.profile_pic, 
           n.experience, n.latitude, n.longitude, n.availability_status, n.last_active_at,
           p.specialty AS specialization 
    FROM nutritionist n
    LEFT JOIN nutritionists_profile p ON n.fullname = p.name
    WHERE n.id = ? AND (n.status = 'active' OR n.status = 'approved' OR n.status IS NULL)
");
$nutri_stmt->execute([$nutritionist_id]);
$nutri = $nutri_stmt->fetch(PDO::FETCH_ASSOC);

if (!$nutri) {
    header("Location: user_inbox.php");
    exit;
}

// Function to get initials (e.g. "Juan Dela Cruz" -> "JC")
function getInitials($name) {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

// Function using Haversine formula to calculate distance in kilometers
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return 999999; 
    $earthRadius = 6371; 
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}

$distance_val = calculateDistance($user_lat, $user_lng, $nutri['latitude'], $nutri['longitude']);
$dist_label = "";
if ($distance_val < 999999) {
    if ($distance_val < 1) {
        $dist_label = round($distance_val * 1000) . " m away";
    } else {
        $dist_label = round($distance_val, 1) . " km away";
    }
}

// Count total unread messages for sidebar badge
$unread_count = 0;
try {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND status = 0");
    $count_stmt->execute([$user_id]);
    $row = $count_stmt->fetch();
    if ($row) {
        $unread_count = (int)$row['total'];
    }
} catch (Exception $e) {
    $unread_count = 0;
}

// SMART RIVE PATH RESOLVER (Awtomatikong hinahanap kung nasaan ang kabi.riv)
$possible_riv_paths = [
    '../assets/rive/uploads/kabi.riv',
    '../assets/uploads/kabi.riv',
    '../uploads/kabi.riv',
    '../../assets/rive/uploads/kabi.riv',
    'assets/rive/uploads/kabi.riv',
    'uploads/kabi.riv'
];
$resolved_riv_path = '../assets/rive/uploads/kabi.riv'; // default fallback
foreach ($possible_riv_paths as $path) {
    if (file_exists(__DIR__ . '/' . $path)) {
        $resolved_riv_path = $path;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI-Based Diet & Nutritional Planner - Nutritionist Chat</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
:root{
  --navy:#173b32;
  --navy-2:#123027;
  --green:#3b8f63;
  --green-soft:#e8f5ed;
  --cream:#f7faf6;
  --card:#fff;
  --text:#193028;
  --muted:#6c7b75;
  --border:#e3ebe6;
  --shadow:0 10px 30px rgba(27,61,48,.08);
  --radius:18px;
  --danger:#dc2626;
}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif}
body{background:var(--cream);color:var(--text)}
button,input,select{font:inherit}
button{cursor:pointer}
.app{min-height:100vh;display:flex}

.sidebar{
  width:260px;flex:0 0 260px;min-height:100vh;position:sticky;top:0;align-self:flex-start;
  background:linear-gradient(180deg,var(--navy),var(--navy-2));color:#fff;padding:24px 18px;
  display:flex;flex-direction:column;z-index:20;transition:transform .25s ease;
}
.brand{display:flex;align-items:center;gap:12px;padding:6px 10px 28px}
.brand-icon{width:42px;height:42px;border-radius:13px;display:grid;place-items:center;background:rgba(255,255,255,.12);font-size:23px}
.brand strong{display:block;font-size:17px}.brand span{display:block;color:#b9d5ca;font-size:12px;margin-top:2px}
.nav-label{padding:0 12px 8px;color:#9fc2b4;text-transform:uppercase;letter-spacing:.11em;font-size:10px;font-weight:800}
.nav{display:grid;gap:6px}
.nav a{display:flex;align-items:center;gap:12px;text-decoration:none;padding:13px 12px;border-radius:12px;color:#d9ebe4;font-size:14px;font-weight:650;transition:background .2s,transform .2s;position:relative}
.nav a:hover{background:rgba(255,255,255,.09);transform:translateX(2px)}
.nav a.active{background:rgba(255,255,255,.14);color:#fff}
.nav-icon{width:24px;text-align:center;font-size:17px}

.nav-badge{
  margin-left:auto;
  background:var(--danger);
  color:#fff;
  font-size:11px;
  font-weight:800;
  padding:2px 8px;
  border-radius:999px;
}

.sidebar-bottom{margin-top:auto;border-top:1px solid rgba(255,255,255,.1);padding-top:16px}
.sidebar-bottom a.logout{
  display:flex;align-items:center;gap:12px;text-decoration:none;padding:13px 12px;
  border-radius:12px;color:#f8d7da!important;font-size:14px;font-weight:650;transition:background .2s,transform .2s;
}
.sidebar-bottom a.logout:hover{background:rgba(220,53,69,.2);transform:translateX(2px)}

/* MODAL STYLES */
.logout-modal-overlay {
  position: fixed; inset: 0; background: rgba(23, 59, 50, 0.5); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center; z-index: 9999;
  opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s ease;
}
.logout-modal-overlay.active { opacity: 1; visibility: visible; }
.logout-modal-card {
  background: #ffffff; width: 90%; max-width: 380px; border-radius: 20px; padding: 28px 24px;
  text-align: center; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); transform: scale(0.85);
  transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.logout-modal-overlay.active .logout-modal-card { transform: scale(1); }
.logout-modal-icon {
  width: 56px; height: 56px; background: #fff3f1; color: #b42318; font-size: 26px;
  border-radius: 50%; display: grid; place-items: center; margin: 0 auto 16px;
}
.logout-modal-card h3 { margin: 0 0 8px; font-size: 20px; font-weight: 700; color: var(--text); }
.logout-modal-card p { margin: 0 0 24px; font-size: 14px; color: var(--muted); line-height: 1.5; }
.logout-modal-actions { display: flex; gap: 12px; }
.btn-modal-cancel {
  flex: 1; height: 44px; background: var(--cream); color: var(--text); border: 1px solid var(--border);
  border-radius: 12px; font-weight: 650; font-size: 14px; cursor: pointer; transition: background 0.2s;
}
.btn-modal-cancel:hover { background: var(--green-soft); }
.btn-modal-logout {
  flex: 1; height: 44px; background: #b42318; color: #ffffff; border: none; border-radius: 12px;
  font-weight: 650; font-size: 14px; display: inline-flex; align-items: center; justify-content: center;
  text-decoration: none; transition: background 0.2s, transform 0.2s;
}
.btn-modal-logout:hover { background: #901c12; transform: translateY(-1px); }

.main{min-width:0;flex:1;padding:26px clamp(15px,4vw,48px) 40px}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:22px}
.menu-btn{display:none;border:1px solid var(--border);background:#fff;width:44px;height:44px;border-radius:12px;color:var(--text);font-size:19px}
.eyebrow{font-size:12px;font-weight:800;color:var(--green);text-transform:uppercase;letter-spacing:.1em}
.topbar h1{font-size:clamp(25px,3vw,34px);line-height:1.15;margin:5px 0 0;letter-spacing:-.7px}

.profile{display:flex;align-items:center;gap:10px;padding:7px 11px 7px 7px;background:#fff;border:1px solid var(--border);border-radius:999px;text-decoration:none;transition:border-color .2s,box-shadow .2s}
.profile:hover{border-color:#b9d5ca;box-shadow:0 4px 12px rgba(27,61,48,.06)}
.avatar-top{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:var(--green-soft);color:var(--green);font-weight:800}
.profile-name{font-size:13px;font-weight:700}

.chat-layout{
  width:min(1150px,100%);margin:0 auto;
  display:grid;grid-template-columns:minmax(0,1fr) 320px;
  gap:18px;min-height:calc(100vh - 125px);
}

.chat-container{
  min-width:0;height:min(700px,calc(100vh - 125px));min-height:520px;
  display:flex;flex-direction:column;background:#fff;border:1px solid var(--border);
  border-radius:22px;box-shadow:var(--shadow);overflow:hidden;
}
.chat-header{
  padding:16px 19px;background:#fff;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;gap:12px
}
.chat-title{display:flex;align-items:center;gap:11px;min-width:0}
.chat-title-icon{width:40px;height:40px;border-radius:12px;background:var(--green-soft);display:grid;place-items:center;font-size:19px}
.chat-title strong{display:block;font-size:15px}.chat-title span{display:block;color:var(--muted);font-size:11px;margin-top:2px}
.status{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:750;white-space:nowrap}
.status-dot{width:7px;height:7px;border-radius:50%}

.info-card{
  background:#fff;border:1px solid var(--border);border-radius:20px;padding:20px;height:max-content;
  box-shadow:0 5px 18px rgba(27,61,48,.04);display:flex;flex-direction:column;gap:16px;
}
.info-card h2{font-size:16px;margin:0 0 4px;font-weight:700}

.back-inbox-btn, .report-nutri-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  padding: 10px 12px;
  border-radius: 11px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: .2s;
  text-decoration: none;
}
.back-inbox-btn {
  background: var(--green-soft);
  color: var(--green);
  border: 1px solid #c9ebd8;
  margin-bottom: 2px;
}
.back-inbox-btn:hover { background: #d4f2e1; transform: translateY(-1px); }

.report-nutri-btn {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
  margin-top: 6px;
}
.report-nutri-btn:hover { background: #fee2e2; transform: translateY(-1px); }

.nutritionist-profile-display{
  display:flex;flex-direction:column;align-items:center;text-align:center;
  padding-top:4px;
}
.profile-img-container{
  width:84px;height:84px;border-radius:50%;overflow:hidden;
  border:3px solid var(--green-soft);background:var(--green-soft);color:var(--green);margin-bottom:10px;
  display:grid;place-items:center;box-shadow:0 4px 12px rgba(0,0,0,0.06);font-size:26px;font-weight:700;
}
.profile-img-container img{width:100%;height:100%;object-fit:cover}
.nutritionist-name{font-size:16px;font-weight:700;color:var(--text);margin:0 0 10px}

.distance-badge-profile {
  font-size: 11px;
  font-weight: 700;
  background: #e2f0e9;
  color: #08734a;
  padding: 4px 10px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 14px;
}

.details-grid{
  width:100%;display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:4px;text-align:left;
}
.detail-item{background:#f8faf8;border:1px solid var(--border);padding:8px 10px;border-radius:10px}
.detail-item span{display:block;font-size:10px;color:var(--muted);font-weight:700;text-transform:uppercase}
.detail-item strong{display:block;font-size:12px;color:var(--text);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* RIVE MASCOT STYLING SA CHAT INFO CARD */
.chat-mascot-box {
  background: #f9fbfa;
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 12px;
  text-align: center;
}
.chat-mascot-container {
  width: 130px;
  height: 130px;
  margin: 0 auto;
}
.chat-mascot-container canvas {
  width: 100%;
  height: 100%;
}

.chat-body{
  flex:1;min-height:0;padding:18px;overflow-y:auto;background:#f2f6f3;
  display:flex;flex-direction:column;gap:2px;scroll-behavior:smooth
}
.chat-body:empty:before{
  content:"No messages yet. Start a conversation below.";
  margin:auto;color:#83918b;font-size:13px;text-align:center
}
.message{
  max-width:min(76%,520px);margin:6px 0;padding:11px 14px;border-radius:16px;
  word-wrap:break-word;line-height:1.5;font-size:13px
}
.user-msg{
  background:var(--green);color:#fff;align-self:flex-end;border-bottom-right-radius:4px;
  box-shadow:0 4px 10px rgba(59,143,99,.12)
}
.nutritionist-msg{
  background:#fff;color:var(--text);align-self:flex-start;border:1px solid var(--border);
  border-bottom-left-radius:4px;box-shadow:0 2px 6px rgba(27,61,48,.04)
}

.chat-footer{
  padding:12px;border-top:1px solid var(--border);background:#fff;
  display:flex;align-items:center;gap:8px
}
.chat-footer input[type=text]{
  flex:1;min-width:0;min-height:46px;padding:0 15px;border-radius:13px;
  border:1px solid var(--border);outline:none;background:#f9fbfa;color:var(--text);font-size:14px
}
.chat-footer input[type=text]:focus{border-color:var(--green);box-shadow:0 0 0 3px rgba(59,143,99,.12);background:#fff}
.chat-footer button{
  min-width:82px;min-height:46px;padding:0 16px;border:0;border-radius:13px;
  background:var(--green);color:#fff;font-weight:750;transition:.2s
}
.chat-footer button:hover{transform:translateY(-1px);box-shadow:0 7px 16px rgba(59,143,99,.18)}
.chat-footer button:disabled{opacity:.6;cursor:not-allowed;transform:none;box-shadow:none}

/* REPORT MODAL STYLING */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center; z-index: 9999;
  opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s ease;
}
.modal-overlay.active { opacity: 1; visibility: visible; }
.modal-card {
  background: #ffffff; width: 90%; max-width: 480px; border-radius: 20px; padding: 28px;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25); transform: scale(0.85);
  transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  text-align: left;
}
.modal-overlay.active .modal-card { transform: scale(1); }
.modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.modal-header h3 { font-size: 18px; font-weight: 800; color: var(--text); margin: 0; }
.close-modal-btn { background: none; border: none; font-size: 18px; cursor: pointer; color: var(--muted); }

.modal-form-group { margin-bottom: 14px; }
.modal-form-group label { display: block; font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; margin-bottom: 6px; }
.modal-form-group select, .modal-form-group textarea {
  width: 100%; padding: 11px 14px; border: 1px solid var(--border); border-radius: 10px;
  outline: none; font-size: 14px; color: var(--text); background: #f9fbfa;
}
.modal-form-group select:focus, .modal-form-group textarea:focus { border-color: var(--green); background: #fff; }
.modal-form-group textarea { resize: vertical; min-height: 90px; }

.btn-submit-report {
  width: 100%; height: 46px; background: #dc2626; color: #fff; border: none; border-radius: 10px;
  font-weight: 700; font-size: 14px; cursor: pointer; transition: background 0.2s; box-shadow: 0 4px 12px rgba(220,38,38,.2);
}
.btn-submit-report:hover { background: #b91c1c; }

.alert-box-success { background: #dcfce7; color: #15803d; padding: 12px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; font-weight: 600; }
.alert-box-error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; font-weight: 600; }

.overlay{display:none}

@media(max-width:900px){
  .sidebar{width:240px;flex-basis:240px}
  .chat-layout{grid-template-columns:1fr}
  .info-card{order: -1;}
  .chat-container{height:calc(100vh - 125px)}
}
@media(max-width:720px){
  .app{display:block}
  .sidebar{position:fixed;left:0;top:0;bottom:0;transform:translateX(-105%);box-shadow:20px 0 40px rgba(0,0,0,.16)}
  .sidebar.open{transform:translateX(0)}
  .overlay{position:fixed;inset:0;background:rgba(12,28,22,.42);z-index:15}
  .overlay.show{display:block}
  .main{padding:16px 14px 24px}
  .menu-btn{display:grid;place-items:center}
  .profile-name{display:none}
  .topbar{margin-bottom:15px}
  .chat-layout{min-height:calc(100vh - 105px)}
  .chat-container{height:calc(100vh - 105px);min-height:480px;border-radius:18px}
  .chat-body{padding:13px}
  .message{max-width:87%}
}
</style>
</head>

<body>
<div class="app">
  <div class="overlay" id="overlay" aria-hidden="true"></div>

  <aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="brand">
      <div class="brand-icon">🥗</div>
      <div><strong>AI Diet Planner</strong><span>Personal nutrition assistant</span></div>
    </div>

    <div class="nav-label">Menu</div>
    <nav class="nav">
      <a href="dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
      <a href="generate_weekly.php"><span class="nav-icon">▦</span>Weekly Meal Plan</a>
      <a href="leaderboard.php"><span class="nav-icon">🏆</span>Leaderboard</a>
      <a href="user_inbox.php" class="active">
        <span class="nav-icon">📥</span>Inbox
        <?php if($unread_count > 0): ?>
          <span class="nav-badge" id="sidebar-badge"><?php echo $unread_count; ?></span>
        <?php endif; ?>
      </a>
      <a href="profile.php"><span class="nav-icon">👤</span>My Profile</a>
    </nav>

    <div class="sidebar-bottom">
      <a class="logout" href="javascript:void(0);" onclick="showLogoutModal();"><span class="nav-icon">↪</span>Logout</a>
    </div>
  </aside>

  <main class="main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px">
        <button class="menu-btn" id="menuBtn" type="button" aria-label="Open navigation" aria-expanded="false">☰</button>
        <div>
          <div class="eyebrow">Nutrition Support</div>
          <h1>Nutritionist Chat</h1>
        </div>
      </div>
      <a class="profile" href="profile.php" title="View & Edit Profile">
        <div class="avatar-top">💬</div>
        <span class="profile-name">Live Chat</span>
      </a>
    </header>

    <section class="chat-layout">
      <section class="chat-container" aria-label="Nutritionist chat">
        <header class="chat-header">
          <div class="chat-title">
            <div class="chat-title-icon">💬</div>
            <div>
              <strong>Chat with <?php echo htmlspecialchars($nutri['fullname']); ?></strong>
              <span>Verified Nutritionist</span>
            </div>
          </div>
          <div style="display: flex; align-items: center; gap: 12px;">
            <?php 
              // Dynamic Last Active calculation
              $last_active = $nutri['last_active_at'] ?? null;
              $status_text = "Offline";
              $dot_color = "#94a3b8"; // Gray

              if ($last_active) {
                  $time_diff = time() - strtotime($last_active);
                  if ($time_diff < 180) { // Wala pang 3 minuto
                      $status_text = "Active";
                      $dot_color = "#10b981"; // Green
                  } else if ($time_diff < 3600) { // Wala pang 1 oras
                      $mins = round($time_diff / 60);
                      $status_text = "Active {$mins}m ago";
                      $dot_color = "#f59e0b"; // Yellow
                  } else if ($time_diff < 86400) { // Wala pang 1 araw
                      $hours = round($time_diff / 3600);
                      $status_text = "Active {$hours}h ago";
                      $dot_color = "#94a3b8";
                  } else {
                      $days = round($time_diff / 86400);
                      $status_text = "Active {$days}d ago";
                      $dot_color = "#94a3b8";
                  }
              }
            ?>
            <div class="status">
              <span class="status-dot" style="background: <?php echo $dot_color; ?>;"></span> 
              <?php echo $status_text; ?>
            </div>
          </div>
        </header>

        <div class="chat-body" id="chat-box" aria-live="polite"></div>

        <div class="chat-footer">
          <input type="text" id="message" placeholder="Type your message..." autocomplete="off" aria-label="Message">
          <button id="send" type="button">Send</button>
        </div>
      </section>

      <aside class="info-card">
        <!-- Back to Inbox Button -->
        <a href="user_inbox.php" class="back-inbox-btn">
          <i class="fa-solid fa-arrow-left"></i> Back to Inbox
        </a>

        <div class="nutritionist-profile-display">
          <div class="profile-img-container" id="display-pic-box">
            <?php if (!empty($nutri['profile_pic']) && $nutri['profile_pic'] !== 'default_avatar.png' && file_exists("../uploads/profile_pics/" . $nutri['profile_pic'])): ?>
              <?php 
                $filename = trim($nutri['profile_pic']);
                $imgSrc = (strpos($filename, 'http') === 0 || strpos($filename, '/') === 0) ? $filename : '../uploads/profile_pics/' . $filename;
              ?>
              <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($nutri['fullname']); ?>">
            <?php else: ?>
              <?php echo getInitials($nutri['fullname']); ?>
            <?php endif; ?>
          </div>
          <h3 class="nutritionist-name"><?php echo htmlspecialchars($nutri['fullname']); ?></h3>

          <?php if (!empty($dist_label)): ?>
            <div class="distance-badge-profile">
              <i class="fa-solid fa-location-dot"></i> <?php echo $dist_label; ?>
            </div>
          <?php endif; ?>

          <div class="details-grid">
            <div class="detail-item">
              <span>Experience</span>
              <strong><?php echo htmlspecialchars(!empty($nutri['experience']) ? $nutri['experience'] : 'N/A'); ?></strong>
            </div>
            <div class="detail-item">
              <span>Specialization</span>
              <strong><?php echo htmlspecialchars(!empty($nutri['specialization']) ? $nutri['specialization'] : 'Clinical Nutrition'); ?></strong>
            </div>
          </div>
        </div>

        <!-- RIVE MASCOT COMPANION KABI -->
        <div class="chat-mascot-box">
          <div class="chat-mascot-container">
            <canvas id="kabiChatCanvas" width="130" height="130"></canvas>
          </div>
          <span style="font-size: 11px; color: var(--muted); display: block; margin-top: 4px; font-weight: 600;">Kabi • AI Companion</span>
        </div>

        <!-- Report Nutritionist Button -->
        <button type="button" class="report-nutri-btn" onclick="openReportModal()">
          <i class="fa-solid fa-triangle-exclamation"></i> Report Nutritionist
        </button>
      </aside>
    </section>
  </main>
</div>

<!-- REPORT MODAL -->
<div id="reportModal" class="modal-overlay">
  <div class="modal-card">
    <div class="modal-header">
      <h3>Report Nutritionist</h3>
      <button type="button" class="close-modal-btn" onclick="closeReportModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <?php if (!empty($report_msg)): ?>
      <div class="alert-box-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($report_msg); ?></div>
    <?php endif; ?>
    <?php if (!empty($report_error)): ?>
      <div class="alert-box-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($report_error); ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="modal-form-group">
        <label>Violation Category</label>
        <select name="category" required>
          <option value="">Select a reason...</option>
          <option value="Inappropriate behavior or language">Inappropriate behavior or language</option>
          <option value="Medical misinformation or unsafe advice">Medical misinformation or unsafe advice</option>
          <option value="Unprofessional conduct">Unprofessional conduct</option>
          <option value="Others">Others</option>
        </select>
      </div>

      <div class="modal-form-group">
        <label>Detailed Explanation</label>
        <textarea name="reason" placeholder="Describe the issue or violation in detail..." required></textarea>
      </div>

      <button type="submit" name="submit_report" class="btn-submit-report">Submit Report to Admin</button>
    </form>
  </div>
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

<!-- INTERACTIVE RIVE SCRIPT (TINANGGAL ANG MALING STATE MACHINE PARAM PARA HINDI MAG-404 O ERROR) -->
<script src="https://unpkg.com/@rive-app/canvas@latest"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let kabiRive = null;
let typingInput = null;
let sendTrigger = null;

window.addEventListener('DOMContentLoaded', () => {
  try {
    kabiRive = new rive.Rive({
      src: "<?php echo $resolved_riv_path; ?>",
      canvas: document.getElementById("kabiChatCanvas"),
      autoplay: true,
      onLoad: () => {
        console.log("SUCCESS: Tagumpay na nag-load si Kabi!");
        kabiRive.resizeDrawingSurfaceToCanvas();

        // Subukan nating kunin ang inputs kung available sa default state machine
        try {
          const inputs = kabiRive.stateMachineInputs(kabiRive.activeStateMachineName);
          if (inputs) {
            typingInput = inputs.find(i => i.name === 'isTyping' || i.name === 'Typing' || i.name === 'isLooking');
            sendTrigger = inputs.find(i => i.name === 'send' || i.name === 'Trigger' || i.name === 'success');
          }
        } catch (e) {
          console.log("Note: Walang custom inputs/state machine, default artboard animation ang ginagamit.");
        }
      },
      onError: (err) => {
        console.error("Rive Load Error:", err);
      }
    });
  } catch (err) {
    console.error("Rive Exception:", err);
  }
});

// Habang nagti-type ang user sa message box
$('#message').on('input', function() {
  if (typingInput) {
    typingInput.value = $(this).val().trim().length > 0;
  }
});

// Kapag nagpadala ng mensahe
function triggerKabiReaction() {
  if (sendTrigger) {
    if (sendTrigger.type === 'trigger') {
      sendTrigger.fire();
    } else {
      sendTrigger.value = true;
      setTimeout(() => { sendTrigger.value = false; }, 800);
    }
  }
}

function showLogoutModal() {
  document.getElementById('logoutModal').classList.add('active');
}

function closeLogoutModal() {
  document.getElementById('logoutModal').classList.remove('active');
}

function openReportModal() {
  document.getElementById('reportModal').classList.add('active');
}

function closeReportModal() {
  document.getElementById('reportModal').classList.remove('active');
}

<?php if (!empty($report_msg) || !empty($report_error)): ?>
  window.addEventListener('DOMContentLoaded', (event) => {
    openReportModal();
  });
<?php endif; ?>

document.getElementById('logoutModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeLogoutModal();
  }
});

document.getElementById('reportModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeReportModal();
  }
});

let user_id = <?php echo $user_id; ?>;
let nutritionist_id = <?php echo $nutritionist_id; ?>;

function markMessagesAsRead() {
  if (!nutritionist_id || !user_id) return;
  $.post('mark_read_user.php', { nutritionist_id: nutritionist_id, user_id: user_id }, function(response) {
    if (response.trim() === 'success') {
      const badge = $('#sidebar-badge');
      if (badge.length) {
        badge.fadeOut(200, function() { $(this).remove(); });
      }
    }
  });
}

function fetchMessages() {
  if(!nutritionist_id) return;
  $.post('fetch_messages.php', {user_id, nutritionist_id}, function(data){
    const box = $('#chat-box');
    const wasNearBottom = box[0].scrollHeight - box.scrollTop() - box.outerHeight() < 80;
    box.html(data);
    if (wasNearBottom) box.scrollTop(box[0].scrollHeight);
  });
}

function sendMessage() {
  const input = $('#message');
  const send = $('#send');
  const msg = input.val();

  if(msg.trim() !== '' && nutritionist_id){
    send.prop('disabled', true).text('Sending…');

    triggerKabiReaction();
    if (typingInput) {
      typingInput.value = false;
    }

    $.post('send_message.php', {
      user_id: user_id, 
      nutritionist_id: nutritionist_id, 
      message: msg
    }, function(response){
      if (response.trim() === 'success') {
        input.val('');
        fetchMessages();
      } else {
        alert('Server returned: ' + response);
      }
    }).fail(function(xhr, status, error) {
      alert('Request Failed: ' + error);
    }).always(function(){
      send.prop('disabled', false).text('Send');
      input.trigger('focus');
    });
  }
}

$('#send').click(sendMessage);

$('#message').keypress(function(e){
  if(e.which === 13 && !e.shiftKey){
    e.preventDefault();
    sendMessage();
  }
});

(function(){
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');
  const menuBtn = document.getElementById('menuBtn');

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

markMessagesAsRead();
setInterval(fetchMessages, 2000);
fetchMessages();
</script>
</body>
</html>