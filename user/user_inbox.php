<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle appointment booking submission via modal
$booking_success = "";
$booking_error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment_submit'])) {
    $nutri_id_target = $_POST['nutritionist_id'] ?? 0;
    $app_date = $_POST['appointment_date'] ?? '';
    $app_time = $_POST['appointment_time'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if (empty($app_date) || empty($app_time)) {
        $booking_error = "Please select both a preferred date and time.";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO appointments (user_id, nutritionist_id, appointment_date, appointment_time, notes, status) 
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$user_id, $nutri_id_target, $app_date, $app_time, $notes]);
            $booking_success = "Appointment request sent successfully! Waiting for nutritionist confirmation.";
        } catch (Exception $e) {
            $booking_error = "Failed to book appointment: " . $e->getMessage();
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

// Get total unread messages for sidebar badge
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

// Query to get the user's latest conversations with different nutritionists (including last_active_at)
$inbox_sql = "
    WITH conversations AS (
        SELECT 
            CASE 
                WHEN sender_id = :user_id THEN receiver_id 
                ELSE sender_id 
            END AS nutritionist_id,
            message,
            created_at,
            sender_id,
            ROW_NUMBER() OVER(
                PARTITION BY CASE WHEN sender_id = :user_id THEN receiver_id ELSE sender_id END 
                ORDER BY created_at DESC
            ) as rn
        FROM messages
        WHERE sender_id = :user_id OR receiver_id = :user_id
    ),
    unread_counts AS (
        SELECT sender_id AS nutritionist_id, COUNT(*) as unread_count
        FROM messages
        WHERE receiver_id = :user_id AND status = 0
        GROUP BY sender_id
    )
    SELECT 
        n.id AS nutritionist_id,
        n.fullname AS nutritionist_name,
        n.profile_pic,
        n.status AS account_status,
        n.availability_status,
        n.last_active_at,
        c.message AS last_message,
        c.created_at AS last_message_time,
        c.sender_id AS last_sender_id,
        COALESCE(uc.unread_count, 0) AS unread_count
    FROM conversations c
    JOIN nutritionist n ON n.id = c.nutritionist_id
    LEFT JOIN unread_counts uc ON uc.nutritionist_id = c.nutritionist_id
    WHERE c.rn = 1
    ORDER BY c.created_at DESC
";

$inbox_stmt = $conn->prepare($inbox_sql);
$inbox_stmt->execute(['user_id' => $user_id]);
$conversations = $inbox_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active nutritionists with lat/lng and last_active_at for distance and status calculation
$nutri_list_sql = "
    SELECT n.id, n.fullname, n.profile_pic, n.certification, n.latitude, n.longitude, n.status AS account_status, n.availability_status, n.last_active_at, p.specialty AS specialization 
    FROM nutritionist n
    LEFT JOIN nutritionists_profile p ON n.fullname = p.name
    WHERE n.status = 'active' OR n.status = 'approved' OR n.status IS NULL
";
$nutri_list_stmt = $conn->query($nutri_list_sql);
$all_nutritionists = $nutri_list_stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Calculate distance for each nutritionist and add to array
foreach ($all_nutritionists as &$nutri) {
    $nutri['distance'] = calculateDistance($user_lat, $user_lng, $nutri['latitude'], $nutri['longitude']);
}
unset($nutri);

// Sort nutritionists from closest to farthest
usort($all_nutritionists, function($a, $b) {
    return $a['distance'] <=> $b['distance'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI-Based Diet & Nutritional Planner - Inbox</title>
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
a{color:inherit;text-decoration:none}
.app{min-height:100vh;display:flex}

.sidebar{
  width:260px;flex:0 0 260px;min-height:100vh;position:sticky;top:0;align-self:flex-start;
  background:linear-gradient(180deg,var(--navy),var(--navy-2));color:#fff;padding:24px 18px;
  display:flex;flex-direction:column;z-index:20;transition:transform .25s ease;
}
.brand{display:flex;align-items:center;gap:12px;padding:6px 10px 28px}
.brand-icon{width:42px;height:42px;border-radius:13px;display:grid;place-items:center;background:rgba(255,255,255,.12);font-size:23px}
.brand strong{display:block;font-size:17px}
.brand span{display:block;color:#b9d5ca;font-size:12px;margin-top:2px}
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

.main{min-width:0;flex:1;padding:26px clamp(15px,4vw,48px) 40px}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:22px}
.eyebrow{font-size:12px;font-weight:800;color:var(--green);text-transform:uppercase;letter-spacing:.1em}
.topbar h1{font-size:clamp(25px,3vw,34px);line-height:1.15;margin:5px 0 0;letter-spacing:-.7px}

/* SEARCH & FILTER TOOLBAR STYLES */
.inbox-toolbar {
  display: flex;
  gap: 12px;
  margin-bottom: 18px;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
}
.search-box {
  position: relative;
  flex: 1;
  min-width: 220px;
}
.search-box input {
  width: 100%;
  padding: 11px 16px 11px 40px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--card);
  font-size: 13.5px;
  color: var(--text);
  outline: none;
  transition: border-color .2s;
  box-shadow: var(--shadow);
}
.search-box input:focus {
  border-color: var(--green);
}
.filter-pills {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.pill-btn {
  padding: 8px 14px;
  border-radius: 99px;
  border: 1px solid var(--border);
  background: var(--card);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
  cursor: pointer;
  transition: all .2s;
  box-shadow: var(--shadow);
}
.pill-btn:hover {
  border-color: var(--green);
  color: var(--text);
}
.pill-btn.active {
  background: var(--navy);
  color: #fff;
  border-color: var(--navy);
}

/* INBOX LAYOUT GRID */
.inbox-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: 20px;
  align-items: start;
}

.inbox-list { display: flex; flex-direction: column; gap: 12px; }
.inbox-card {
  background: var(--card); border: 1px solid var(--border); border-radius: 16px;
  padding: 16px 20px; display: flex; align-items: center; justify-content: space-between;
  transition: transform .2s, box-shadow .2s; box-shadow: var(--shadow);
}
.inbox-card:hover { transform: translateY(-2px); border-color: var(--green); }
.nutritionist-info { display: flex; align-items: center; gap: 16px; min-width: 0; }
.avatar-container { position: relative; flex-shrink: 0; display: inline-block; }
.avatar {
  width: 48px; height: 48px; border-radius: 50%; background: var(--green-soft);
  color: var(--green); font-weight: 700; display: grid; place-items: center; font-size: 16px; overflow: hidden;
}
.avatar img { width: 100%; height: 100%; object-fit: cover; }

.status-dot {
  position: absolute; bottom: 1px; right: 1px; width: 11px; height: 11px;
  border-radius: 50%; border: 2px solid #fff; z-index: 2;
}
.status-dot.available { background: #10b981; }
.status-dot.on_leave { background: #f59e0b; }
.status-dot.unavailable { background: #ef4444; }
.status-dot.offline { background: #94a3b8; }

.details { min-width: 0; }
.name-status-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.details strong { font-size: 16px; color: var(--text); }
.status-text-badge {
  font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 99px; text-transform: uppercase;
}
.status-text-badge.available { background: #d1fae5; color: #065f46; }
.status-text-badge.on_leave { background: #fef3c7; color: #92400e; }
.status-text-badge.unavailable { background: #fee2e2; color: #991b1b; }
.status-text-badge.offline { background: #f1f5f9; color: #475569; }

.details p { font-size: 13px; color: var(--muted); margin: 4px 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.badge {
  background: var(--danger); color: #fff; font-size: 12px; font-weight: 700;
  padding: 4px 10px; border-radius: 99px; min-width: 24px; text-align: center; display: inline-block;
}
.time-stamp { font-size: 11px; color: var(--muted); margin-bottom: 4px; display: block; text-align: right; white-space: nowrap; }
.empty-box { text-align: center; padding: 40px; color: var(--muted); font-size: 14px; background: #fff; border-radius: 16px; border: 1px solid var(--border); }

/* RECOMMENDED NUTRITIONISTS SIDEBAR CARD */
.available-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 20px;
  box-shadow: var(--shadow);
  position: sticky;
  top: 26px;
}
.available-card h3 {
  font-size: 16px;
  margin: 0 0 4px;
  color: var(--text);
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
}
.sidebar-subtitle {
  font-size: 11px;
  color: var(--muted);
  margin-bottom: 14px;
  display: block;
}
.nutri-sidebar-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-height: calc(100vh - 200px);
  overflow-y: auto;
  padding-right: 2px;
}
.nutri-sidebar-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 12px;
  border-radius: 14px;
  background: #f8faf8;
  border: 1px solid var(--border);
  transition: all .2s;
}
.nutri-sidebar-item:hover {
  background: #f1f8f4;
  border-color: #b9d5ca;
}
.nutri-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  flex: 1;
}
.nutri-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: var(--green-soft);
  color: var(--green);
  display: grid;
  place-items: center;
  font-size: 13px;
  font-weight: 700;
  flex-shrink: 0;
  overflow: hidden;
}
.nutri-avatar img { width: 100%; height: 100%; object-fit: cover; }
.nutri-info-text { min-width: 0; flex: 1; }
.nutri-info-text strong {
  display: block;
  font-size: 13px;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.nutri-info-text .certified-label {
  display: inline-block;
  font-size: 10px;
  font-weight: 700;
  color: var(--green);
  margin-right: 4px;
}
.nutri-status-mini {
  font-size: 9.5px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 99px;
  display: inline-block;
}
.nutri-status-mini.available { background: #d1fae5; color: #065f46; }
.nutri-status-mini.on_leave { background: #fef3c7; color: #92400e; }
.nutri-status-mini.unavailable { background: #fee2e2; color: #991b1b; }
.nutri-status-mini.offline { background: #f1f5f9; color: #475569; }

.license-sub {
  display: block;
  font-size: 10px;
  color: var(--muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.distance-badge {
  font-size: 10px;
  font-weight: 700;
  background: #e2f0e9;
  color: #08734a;
  padding: 2px 6px;
  border-radius: 6px;
  display: inline-block;
  margin-top: 3px;
}
.nutri-actions-mini {
  display: flex;
  gap: 6px;
  flex-shrink: 0;
}
.action-btn-mini {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: grid;
  place-items: center;
  font-size: 13px;
  text-decoration: none;
  cursor: pointer;
  border: none;
  transition: background .2s, transform .2s;
}
.action-btn-mini.chat {
  background: var(--green);
  color: #fff;
}
.action-btn-mini.chat:hover {
  background: #2e714e;
  transform: translateY(-1px);
}
.action-btn-mini.book {
  background: var(--navy);
  color: #fff;
}
.action-btn-mini.book:hover {
  background: #0f2620;
  transform: translateY(-1px);
}

@media(max-width: 992px) {
  .inbox-layout { grid-template-columns: 1fr; }
  .available-card { position: static; }
}

/* MODAL STYLES */
.logout-modal-overlay {
  position: fixed; inset: 0; background: rgba(23, 59, 50, 0.5); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center; z-index: 9999;
  opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s ease;
}
.logout-modal-overlay.active { opacity: 1; visibility: visible; }
.logout-modal-card {
  background: #ffffff; width: 90%; max-width: 400px; border-radius: 20px; padding: 28px 24px;
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

/* ALERT NOTIFICATIONS IN INBOX */
.alert-banner {
  padding: 12px 16px; border-radius: 12px; font-size: 13.5px; font-weight: 600; margin-bottom: 18px;
  display: flex; align-items: center; gap: 10px;
}
.alert-banner.success { background: #e6fffa; color: #0f766e; border: 1px solid #b2f5ea; }
.alert-banner.error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
</style>
</head>
<body>

<div class="app">
  <aside class="sidebar">
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
          <span class="nav-badge"><?php echo $unread_count; ?></span>
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
      <div>
        <div class="eyebrow">Messages</div>
        <h1>Nutritionist Inbox</h1>
      </div>
    </header>

    <?php if (!empty($booking_success)): ?>
      <div class="alert-banner success"><i class="fa-solid fa-circle-check"></i> <?php echo $booking_success; ?></div>
    <?php endif; ?>
    <?php if (!empty($booking_error)): ?>
      <div class="alert-banner error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $booking_error; ?></div>
    <?php endif; ?>

    <!-- SEARCH & FILTER TOOLBAR -->
    <div class="inbox-toolbar">
      <div class="search-box">
        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 14px;"></i>
        <input type="text" id="inboxSearch" placeholder="Search nutritionist by name..." onkeyup="filterInbox()">
      </div>
      <div class="filter-pills">
        <button type="button" class="pill-btn active" onclick="filterByStatus('all', this)">All</button>
        <button type="button" class="pill-btn" onclick="filterByStatus('available', this)">Available</button>
        <button type="button" class="pill-btn" onclick="filterByStatus('on_leave', this)">On Leave</button>
        <button type="button" class="pill-btn" onclick="filterByStatus('unavailable', this)">Unavailable</button>
        <button type="button" class="pill-btn" onclick="filterByStatus('offline', this)">Offline</button>
      </div>
    </div>

    <div class="inbox-layout">
      <div class="inbox-list" id="inboxListContainer">
        <?php if (count($conversations) > 0): ?>
          <?php foreach ($conversations as $chat): ?>
            <?php 
              $raw_availability = trim(strtolower($chat['availability_status'] ?? 'available'));
              $account_status = trim(strtolower($chat['account_status'] ?? 'active'));
              $last_active = $chat['last_active_at'] ?? null;

              $is_offline_by_time = false;
              if ($last_active) {
                  $inactive_seconds = time() - strtotime($last_active);
                  if ($inactive_seconds > 900) {
                      $is_offline_by_time = true;
                  }
              } else {
                  $is_offline_by_time = true;
              }

              if ($account_status !== 'active' && $account_status !== 'approved') {
                  $status_class = 'offline';
                  $status_label = 'Offline';
              } elseif ($is_offline_by_time) {
                  $status_class = 'offline';
                  $status_label = 'Offline';
              } else {
                  if (in_array($raw_availability, ['available', 'on_leave', 'unavailable'])) {
                      $status_class = $raw_availability;
                      $status_label = ucwords(str_replace('_', ' ', $raw_availability));
                  } else {
                      $status_class = 'available';
                      $status_label = 'Available';
                  }
              }
            ?>
            <a href="chat.php?nutritionist_id=<?php echo $chat['nutritionist_id']; ?>" 
               class="inbox-card" 
               data-name="<?php echo strtolower(htmlspecialchars($chat['nutritionist_name'])); ?>" 
               data-status="<?php echo $status_class; ?>">
              <div class="nutritionist-info">
                <div class="avatar-container">
                  <div class="avatar">
                    <?php if (!empty($chat['profile_pic']) && $chat['profile_pic'] !== 'default_avatar.png' && file_exists("../uploads/profile_pics/" . $chat['profile_pic'])): ?>
                      <?php 
                        $filename = trim($chat['profile_pic']);
                        $imgSrc = (strpos($filename, 'http') === 0 || strpos($filename, '/') === 0) ? $filename : '../uploads/profile_pics/' . $filename;
                      ?>
                      <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($chat['nutritionist_name']); ?>">
                    <?php else: ?>
                      <?php echo getInitials($chat['nutritionist_name']); ?>
                    <?php endif; ?>
                  </div>
                  <span class="status-dot <?php echo $status_class; ?>" title="Status: <?php echo $status_label; ?>"></span>
                </div>

                <div class="details">
                  <div class="name-status-row">
                    <strong><?php echo htmlspecialchars($chat['nutritionist_name']); ?></strong>
                    <span class="status-text-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                  </div>
                  <p>
                    <?php if ($chat['last_sender_id'] == $user_id): ?>
                      <span style="color:var(--green);">You: </span>
                    <?php endif; ?>
                    
                    <?php 
                      if (strpos($chat['last_message'], 'Weekly Meal Plan') !== false) {
                          echo '🥗 Shared a Weekly Meal Plan';
                      } else {
                          echo htmlspecialchars($chat['last_message']);
                      }
                    ?>
                  </p>
                </div>
              </div>

              <div style="text-align:right; flex-shrink:0; margin-left:12px;">
                <span class="time-stamp">
                  <?php echo date('M d, g:i a', strtotime($chat['last_message_time'])); ?>
                </span>
                <?php if ($chat['unread_count'] > 0): ?>
                  <span class="badge"><?php echo $chat['unread_count']; ?> new</span>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-box" id="emptyInboxMsg">
            <i class="fa-solid fa-inbox" style="font-size:32px; margin-bottom:12px; display:block;"></i>
            No messages yet. Select a recommended nutritionist on the right to start a conversation.
          </div>
        <?php endif; ?>
      </div>

      <!-- RECOMMENDED NUTRITIONISTS SIDEBAR CARD -->
      <aside class="available-card">
        <h3><i class="fa-solid fa-user-doctor" style="color:var(--green);"></i> Recommended Nutritionists</h3>
        <span class="sidebar-subtitle">Closest to your location</span>
        <div class="nutri-sidebar-list">
          <?php if (count($all_nutritionists) > 0): ?>
            <?php foreach ($all_nutritionists as $nutri): ?>
              <?php 
                $license_text = trim($nutri['certification'] ?? '');
                if (empty($license_text)) {
                    $license_text = trim($nutri['specialization'] ?? '');
                }
                $dist_label = "";
                if ($nutri['distance'] < 999999) {
                    if ($nutri['distance'] < 1) {
                        $dist_label = round($nutri['distance'] * 1000) . " m away";
                    } else {
                        $dist_label = round($nutri['distance'], 1) . " km away";
                    }
                }

                $raw_s = trim(strtolower($nutri['availability_status'] ?? 'available'));
                $acc_s = trim(strtolower($nutri['account_status'] ?? 'active'));
                $nutri_last_active = $nutri['last_active_at'] ?? null;

                $nutri_offline_by_time = false;
                if ($nutri_last_active) {
                    $nutri_inactive_sec = time() - strtotime($nutri_last_active);
                    if ($nutri_inactive_sec > 900) {
                        $nutri_offline_by_time = true;
                    }
                } else {
                    $nutri_offline_by_time = true;
                }

                if ($acc_s !== 'active' && $acc_s !== 'approved' && $acc_s !== '') {
                    $s_class = 'offline';
                    $s_label = 'Offline';
                } elseif ($nutri_offline_by_time) {
                    $s_class = 'offline';
                    $s_label = 'Offline';
                } else {
                    if (in_array($raw_s, ['available', 'on_leave', 'unavailable'])) {
                        $s_class = $raw_s;
                        $s_label = ucwords(str_replace('_', ' ', $raw_s));
                    } else {
                        $s_class = 'available';
                        $s_label = 'Available';
                    }
                }
              ?>
              <div class="nutri-sidebar-item">
                <div class="nutri-meta">
                  <div class="avatar-container" style="flex-shrink:0;">
                    <div class="nutri-avatar">
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
                    <span class="status-dot <?php echo $s_class; ?>" style="width:10px; height:10px;" title="Status: <?php echo $s_label; ?>"></span>
                  </div>

                  <div class="nutri-info-text">
                    <strong><?php echo htmlspecialchars($nutri['fullname']); ?></strong>
                    <div>
                      <span class="certified-label">Certified</span>
                      <span class="nutri-status-mini <?php echo $s_class; ?>"><?php echo $s_label; ?></span>
                    </div>
                    <?php if (!empty($license_text)): ?>
                      <span class="license-sub"><?php echo htmlspecialchars($license_text); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($dist_label)): ?>
                      <span class="distance-badge"><i class="fa-solid fa-location-dot"></i> <?php echo $dist_label; ?></span>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="nutri-actions-mini">
                  <button type="button" class="action-btn-mini book" onclick="openBookModal(<?php echo $nutri['id']; ?>, '<?php echo htmlspecialchars($nutri['fullname'], ENT_QUOTES); ?>')" title="Book Appointment">
                    <i class="fa-regular fa-calendar-plus"></i>
                  </button>
                  <a href="chat.php?nutritionist_id=<?php echo $nutri['id']; ?>" class="action-btn-mini chat" title="Chat Nutritionist">
                    <i class="fa-solid fa-comment"></i>
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="text-align:center; padding:20px; color:var(--muted); font-size:13px;">
              No available nutritionists at the moment.
            </div>
          <?php endif; ?>
        </div>
      </aside>
    </div>
  </main>
</div>

<!-- APPOINTMENT BOOKING MODAL -->
<div id="appointmentModal" class="logout-modal-overlay">
  <div class="logout-modal-card" style="max-width: 440px; text-align: left;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
      <h3 style="margin: 0; font-size: 20px;">Book Consultation</h3>
      <button type="button" onclick="closeBookModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--muted);">&times;</button>
    </div>
    <p style="font-size: 13.5px; color: var(--muted); margin-bottom: 20px;">Schedule an appointment with <strong id="modalNutriName" style="color: var(--text);"></strong></p>
    
    <form method="POST">
      <input type="hidden" name="nutritionist_id" id="modalNutriId">
      
      <div style="margin-bottom: 16px;">
        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px; color: var(--text);">Preferred Date</label>
        <input type="date" name="appointment_date" min="<?php echo date('Y-m-d'); ?>" required style="width: 100%; padding: 11px 14px; border-radius: 12px; border: 1px solid var(--border); background: var(--cream); font-size: 14px; outline: none;">
      </div>

      <div style="margin-bottom: 16px;">
        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px; color: var(--text);">Preferred Time</label>
        <input type="time" name="appointment_time" required style="width: 100%; padding: 11px 14px; border-radius: 12px; border: 1px solid var(--border); background: var(--cream); font-size: 14px; outline: none;">
      </div>

      <div style="margin-bottom: 24px;">
        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px; color: var(--text);">Notes / Purpose of Consultation</label>
        <textarea name="notes" placeholder="Sabihin sa nutritionist ang iyong layunin o concern..." style="width: 100%; padding: 11px 14px; border-radius: 12px; border: 1px solid var(--border); background: var(--cream); font-size: 14px; outline: none; resize: vertical; min-height: 90px;"></textarea>
      </div>

      <div style="display: flex; gap: 10px;">
        <button type="button" class="btn-modal-cancel" onclick="closeBookModal()">Cancel</button>
        <button type="submit" name="book_appointment_submit" style="flex: 1; height: 44px; background: var(--navy); color: #fff; border: none; border-radius: 12px; font-weight: 650; font-size: 14px; cursor: pointer; transition: background 0.2s;">Submit Request</button>
      </div>
    </form>
  </div>
</div>

<!-- LOGOUT MODAL -->
<div id="logoutModal" class="logout-modal-overlay">
  <div class="logout-modal-card">
    <div class="logout-modal-icon">🚪</div>
    <h3>Confirm Logout</h3>
    <p>Are you sure you want to logout of your session?</p>
    <div class="logout-modal-actions">
      <button class="btn-modal-cancel" onclick="closeLogoutModal();">Cancel</button>
      <a href="../auth/logout.php" class="btn-modal-logout">Yes, Logout</a>
    </div>
  </div>
</div>

<script>
let currentStatusFilter = 'all';

function filterInbox() {
    const query = document.getElementById('inboxSearch').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.inbox-card');
    
    cards.forEach(card => {
        const name = card.getAttribute('data-name');
        const status = card.getAttribute('data-status');
        
        const matchesSearch = name.includes(query);
        const matchesStatus = (currentStatusFilter === 'all' || status === currentStatusFilter);

        if (matchesSearch && matchesStatus) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

function filterByStatus(status, btn) {
    currentStatusFilter = status;
    
    document.querySelectorAll('.pill-btn').forEach(b => {
        b.classList.remove('active');
    });
    event.currentTarget.classList.add('active');

    filterInbox();
}

function openBookModal(nutriId, nutriName) {
    document.getElementById('modalNutriId').value = nutriId;
    document.getElementById('modalNutriName').textContent = nutriName;
    document.getElementById('appointmentModal').classList.add('active');
}

function closeBookModal() {
    document.getElementById('appointmentModal').classList.remove('active');
}

function showLogoutModal() {
  document.getElementById('logoutModal').classList.add('active');
}

function closeLogoutModal() {
  document.getElementById('logoutModal').classList.remove('active');
}

document.getElementById('logoutModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeLogoutModal();
  }
});

document.getElementById('appointmentModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeBookModal();
  }
});
</script>

</body>
</html>