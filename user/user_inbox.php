<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle Review / Rating Submission (Inayos para maging tugma sa mga umiiral na kolum sa database)
$review_success = "";
$review_error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review_rating'])) {
    $nutritionist_id_target = (int)($_POST['nutritionist_id'] ?? 0);
    $rating_val = (int)($_POST['rating'] ?? 0);

    if ($rating_val < 1 || $rating_val > 5) {
        $review_error = "Please select a star rating between 1 and 5.";
    } else {
        try {
            $rev_stmt = $conn->prepare("
                INSERT INTO nutritionist_reviews (user_id, nutritionist_id, rating) 
                VALUES (?, ?, ?)
            ");
            $rev_stmt->execute([$user_id, $nutritionist_id_target, $rating_val]);
            $review_success = "Thank you! Your rating has been submitted successfully.";
        } catch (Exception $e) {
            $review_error = "Failed to submit review: " . $e->getMessage();
        }
    }
}

// Handle appointment booking submission via modal with past date/time validation
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
        $selected_datetime = strtotime($app_date . ' ' . $app_time);
        $current_datetime = time();

        if ($selected_datetime < $current_datetime) {
            $booking_error = "You cannot book an appointment in the past. Please select a valid future date and time.";
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
} catch (Exception $e) {}

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

// Kunin ang mga appointments ng user
$user_appointments = [];
$debug_error = "";
try {
    $user_app_stmt = $conn->prepare("
        SELECT 
            a.id, 
            a.nutritionist_id,
            a.appointment_date, 
            a.appointment_time, 
            a.status, 
            a.notes, 
            a.created_at,
            COALESCE(n.fullname, 'Nutritionist') AS nutritionist_name
        FROM appointments a
        LEFT JOIN nutritionist n ON a.nutritionist_id = n.id
        WHERE a.user_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $user_app_stmt->execute([$user_id]);
    $user_appointments = $user_app_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $debug_error = $e->getMessage();
    $user_appointments = [];
}

// Query to get the user's latest conversations with different nutritionists
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

// Get all active nutritionists with lat/lng
$nutri_list_sql = "
    SELECT n.id, n.fullname, n.profile_pic, n.certification, n.latitude, n.longitude, n.status AS account_status, n.availability_status, n.last_active_at, p.specialty AS specialization 
    FROM nutritionist n
    LEFT JOIN nutritionists_profile p ON n.fullname = p.name
    WHERE n.status = 'active' OR n.status = 'approved' OR n.status IS NULL
";
$nutri_list_stmt = $conn->query($nutri_list_sql);
$all_nutritionists = $nutri_list_stmt->fetchAll(PDO::FETCH_ASSOC);

function getInitials($name) {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return 999999; 
    $earthRadius = 6371; 
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}

foreach ($all_nutritionists as &$nutri) {
    $nutri['distance'] = calculateDistance($user_lat, $user_lng, $nutri['latitude'], $nutri['longitude']);
}
unset($nutri);

usort($all_nutritionists, function($a, $b) {
    return $a['distance'] <=> $b['distance'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI-Based Diet & Nutritional Planner - Inbox & Bookings</title>
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
  display:flex;flex-direction:column;z-index:20;
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
.nav-badge{ margin-left:auto; background:var(--danger); color:#fff; font-size:11px; font-weight:800; padding:2px 8px; border-radius:999px; }
.sidebar-bottom{margin-top:auto;border-top:1px solid rgba(255,255,255,.1);padding-top:16px}
.sidebar-bottom a.logout{
  display:flex;align-items:center;gap:12px;text-decoration:none;padding:13px 12px;
  border-radius:12px;color:#f8d7da!important;font-size:14px;font-weight:650;transition:background .2s;
}
.sidebar-bottom a.logout:hover{background:rgba(220,53,69,.2)}

.main{min-width:0;flex:1;padding:26px clamp(15px,4vw,48px) 40px}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:22px}
.eyebrow{font-size:12px;font-weight:800;color:var(--green);text-transform:uppercase;letter-spacing:.1em}
.topbar h1{font-size:clamp(25px,3vw,34px);line-height:1.15;margin:5px 0 0;letter-spacing:-.7px}

.inbox-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
.tab-btn { background: transparent; border: none; font-size: 14px; font-weight: 700; color: var(--muted); cursor: pointer; padding: 8px 16px; border-radius: 10px; transition: all .2s; }
.tab-btn.active { background: var(--navy); color: #fff; }
.tab-content { display: none; }
.tab-content.active { display: block; }

.inbox-toolbar { display: flex; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; align-items: center; justify-content: space-between; }
.search-box { position: relative; flex: 1; min-width: 220px; }
.search-box input {
  width: 100%; padding: 11px 16px 11px 40px; border-radius: 12px; border: 1px solid var(--border);
  background: var(--card); font-size: 13.5px; color: var(--text); outline: none; box-shadow: var(--shadow);
}
.search-box input:focus { border-color: var(--green); }

.inbox-layout { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 20px; align-items: start; }
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
.details { min-width: 0; }
.name-status-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.details strong { font-size: 16px; color: var(--text); }
.details p { font-size: 13px; color: var(--muted); margin: 4px 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.appointments-tracker-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
.user-appointment-card {
  background: var(--card); border: 1px solid var(--border); border-radius: 16px;
  padding: 20px; box-shadow: var(--shadow); display: flex; flex-direction: column; justify-content: space-between;
}
.user-app-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
.user-app-header strong { font-size: 15px; color: var(--text); }
.appointment-status-badge { font-size: 10.5px; font-weight: 800; padding: 3px 10px; border-radius: 99px; text-transform: uppercase; letter-spacing: .05em; }
.appointment-status-badge.pending { background: #fef3c7; color: #b45309; }
.appointment-status-badge.confirmed { background: #d1fae5; color: #065f46; }
.appointment-status-badge.completed { background: #e0f2fe; color: #0369a1; }
.appointment-status-badge.cancelled { background: #fee2e2; color: #991b1b; }

.user-app-details { font-size: 13px; color: var(--muted); display: grid; gap: 6px; margin-bottom: 12px; }
.user-app-details i { color: var(--green); width: 16px; text-align: center; }
.user-app-notes { background: var(--cream); padding: 10px; border-radius: 10px; font-size: 12.5px; color: var(--muted); border: 1px solid var(--border); }

.btn-rate-review {
  margin-top: 14px; width: 100%; height: 38px; background: #fef3c7; color: #b45309; border: 1px solid #fde68a;
  border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: background .2s;
}
.btn-rate-review:hover { background: #fde68a; }

/* RECOMMENDED NUTRITIONISTS SIDEBAR CARD WITH DISTANCE */
.available-card { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 20px; box-shadow: var(--shadow); position: sticky; top: 26px; }
.available-card h3 { font-size: 16px; margin: 0 0 4px; color: var(--text); font-weight: 700; display: flex; align-items: center; gap: 8px; }
.nutri-sidebar-list { display: flex; flex-direction: column; gap: 12px; max-height: calc(100vh - 200px); overflow-y: auto; padding-right: 2px; }
.nutri-sidebar-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px; border-radius: 14px; background: #f8faf8; border: 1px solid var(--border); transition: all .2s; }
.nutri-sidebar-item:hover { background: #f1f8f4; border-color: #b9d5ca; }
.nutri-meta { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1; }
.nutri-avatar { width: 38px; height: 38px; border-radius: 50%; background: var(--green-soft); color: var(--green); display: grid; place-items: center; font-size: 13px; font-weight: 700; flex-shrink: 0; overflow: hidden; }
.nutri-avatar img { width: 100%; height: 100%; object-fit: cover; }
.nutri-info-text { min-width: 0; flex: 1; }
.nutri-info-text strong { display: block; font-size: 13px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.license-sub { display: block; font-size: 10px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.distance-badge { font-size: 10px; font-weight: 700; background: #e2f0e9; color: #08734a; padding: 2px 6px; border-radius: 6px; display: inline-block; margin-top: 3px; }
.nutri-actions-mini { display: flex; gap: 6px; flex-shrink: 0; }
.action-btn-mini { width: 32px; height: 32px; border-radius: 8px; display: grid; place-items: center; font-size: 13px; text-decoration: none; cursor: pointer; border: none; }
.action-btn-mini.chat { background: var(--green); color: #fff; }
.action-btn-mini.book { background: var(--navy); color: #fff; }

/* MODAL STYLES */
.rating-modal-overlay, .appointment-modal-overlay, .logout-modal-overlay {
  position: fixed; inset: 0; background: rgba(15, 32, 25, 0.55); backdrop-filter: blur(6px);
  display: flex; align-items: center; justify-content: center; z-index: 9999;
  opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s ease; padding: 16px;
}
.rating-modal-overlay.active, .appointment-modal-overlay.active, .logout-modal-overlay.active { opacity: 1; visibility: visible; }
.rating-modal-card, .appointment-modal-card, .logout-modal-card {
  background: #ffffff; width: 100%; max-width: 380px; border-radius: 20px; padding: 28px 24px; text-align: center;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); transform: scale(0.85); transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.rating-modal-overlay.active .rating-modal-card, .appointment-modal-overlay.active .appointment-modal-card, .logout-modal-overlay.active .logout-modal-card { transform: scale(1); }
.logout-modal-icon {
  width: 56px; height: 56px; background: #fff3f1; color: #b42318; font-size: 26px;
  border-radius: 50%; display: grid; place-items: center; margin: 0 auto 16px;
}
.logout-modal-card h3 { margin: 0 0 8px; font-size: 20px; font-weight: 700; color: var(--text); }
.logout-modal-card p { margin: 0 0 24px; font-size: 14px; color: var(--muted); line-height: 1.5; }
.logout-modal-actions { display: flex; gap: 12px; }

.star-rating-container { display: flex; justify-content: center; gap: 8px; font-size: 28px; color: #cbd5e1; cursor: pointer; margin: 16px 0; }
.star-rating-container i.selected, .star-rating-container i:hover, .star-rating-container i:hover ~ i { color: #f59e0b; }

.modal-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.modal-title-wrap h3 { font-size: 22px; font-weight: 800; color: var(--navy); margin: 0; }
.modal-close-btn { background: var(--cream); border: 1px solid var(--border); width: 34px; height: 34px; border-radius: 50%; font-size: 16px; cursor: pointer; color: var(--muted); display: grid; place-items: center; }
.modal-subtitle { font-size: 13.5px; color: var(--muted); margin-bottom: 24px; text-align: left; }
.modal-form-group { margin-bottom: 16px; text-align: left; }
.modal-form-group label { display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px; color: var(--text); }
.modal-input-wrap { position: relative; }
.modal-input-wrap input, .modal-input-wrap textarea {
  width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--border);
  background: var(--cream); font-size: 14px; color: var(--text); outline: none; font-family: inherit;
}
.modal-input-wrap textarea { resize: vertical; min-height: 95px; }
.modal-actions { display: flex; gap: 10px; margin-top: 24px; }
.btn-modal-cancel { flex: 1; height: 44px; background: var(--cream); color: var(--text); border: 1px solid var(--border); border-radius: 12px; font-weight: 650; font-size: 14px; cursor: pointer; transition: background 0.2s; }
.btn-modal-cancel:hover { background: var(--green-soft); }
.btn-modal-submit, .btn-modal-logout { flex: 1; height: 44px; background: #b42318; color: #ffffff; border: none; border-radius: 12px; font-weight: 650; font-size: 14px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; cursor: pointer; transition: background 0.2s, transform 0.2s; }
.btn-modal-submit { background: var(--green); }
.btn-modal-submit:hover { background: #2e714e; }
.btn-modal-logout:hover { background: #901c12; }

.alert-banner { padding: 12px 16px; border-radius: 12px; font-size: 13.5px; font-weight: 600; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; }
.alert-banner.success { background: #e6fffa; color: #0f766e; border: 1px solid #b2f5ea; }
.alert-banner.error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.empty-box { text-align: center; padding: 40px; color: var(--muted); font-size: 14px; background: #fff; border-radius: 16px; border: 1px solid var(--border); }
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
        <span class="nav-icon">📥</span>Inbox & Bookings
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
        <div class="eyebrow">Communications & Schedule</div>
        <h1>Consultation Hub</h1>
      </div>
    </header>

    <?php if (!empty($debug_error)): ?>
      <div class="alert-banner error"><i class="fa-solid fa-triangle-exclamation"></i> DB Error: <?php echo $debug_error; ?></div>
    <?php endif; ?>
    <?php if (!empty($booking_success) || !empty($review_success)): ?>
      <div class="alert-banner success"><i class="fa-solid fa-circle-check"></i> <?php echo $booking_success . $review_success; ?></div>
    <?php endif; ?>
    <?php if (!empty($booking_error) || !empty($review_error)): ?>
      <div class="alert-banner error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $booking_error . $review_error; ?></div>
    <?php endif; ?>

    <div class="inbox-tabs">
      <button type="button" class="tab-btn active" onclick="switchTab('messages', this)">
        <i class="fa-solid fa-comments"></i> Messages (<?php echo count($conversations); ?>)
      </button>
      <button type="button" class="tab-btn" onclick="switchTab('appointments', this)">
        <i class="fa-solid fa-calendar-check"></i> My Appointments (<?php echo count($user_appointments); ?>)
      </button>
    </div>

    <!-- TAB 1: MESSAGES / INBOX -->
    <div id="tab-messages" class="tab-content active">
      <div class="inbox-toolbar">
        <div class="search-box">
          <input type="text" id="inboxSearch" placeholder="Search nutritionist by name..." onkeyup="filterInbox()">
        </div>
      </div>

      <div class="inbox-layout">
        <div class="inbox-list" id="inboxListContainer">
          <?php if (count($conversations) > 0): ?>
            <?php foreach ($conversations as $chat): ?>
              <a href="chat.php?nutritionist_id=<?php echo $chat['nutritionist_id']; ?>" class="inbox-card" data-name="<?php echo strtolower(htmlspecialchars($chat['nutritionist_name'])); ?>">
                <div class="nutritionist-info">
                  <div class="avatar-container">
                    <div class="avatar">
                      <?php if (!empty($chat['profile_pic']) && $chat['profile_pic'] !== 'default_avatar.png' && file_exists("../uploads/profile_pics/" . $chat['profile_pic'])): ?>
                        <img src="<?php echo htmlspecialchars($chat['profile_pic']); ?>" alt="">
                      <?php else: ?>
                        <?php echo getInitials($chat['nutritionist_name']); ?>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="details">
                    <div class="name-status-row">
                      <strong><?php echo htmlspecialchars($chat['nutritionist_name']); ?></strong>
                    </div>
                    <p><?php echo htmlspecialchars($chat['last_message']); ?></p>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty-box">No messages yet.</div>
          <?php endif; ?>
        </div>

        <aside class="available-card">
          <h3><i class="fa-solid fa-user-doctor" style="color:var(--green);"></i> Recommended Nutritionists</h3>
          <span class="sidebar-subtitle" style="font-size: 11px; color: var(--muted); margin-bottom: 12px; display: block;">Sorted by proximity to your location</span>
          <div class="nutri-sidebar-list">
            <?php foreach ($all_nutritionists as $nutri): ?>
              <div class="nutri-sidebar-item">
                <div class="nutri-meta">
                  <div class="nutri-avatar">
                    <?php if (!empty($nutri['profile_pic']) && $nutri['profile_pic'] !== 'default_avatar.png' && file_exists("../uploads/profile_pics/" . $nutri['profile_pic'])): ?>
                      <img src="<?php echo htmlspecialchars($nutri['profile_pic']); ?>" alt="">
                    <?php else: ?>
                      <?php echo getInitials($nutri['fullname']); ?>
                    <?php endif; ?>
                  </div>
                  <div class="nutri-info-text">
                    <strong><?php echo htmlspecialchars($nutri['fullname']); ?></strong>
                    <span class="license-sub"><?php echo htmlspecialchars($nutri['specialization'] ?? 'Nutritionist'); ?></span>
                    <?php if ($nutri['distance'] < 999999): ?>
                      <span class="distance-badge"><i class="fa-solid fa-location-dot"></i> <?php echo number_format($nutri['distance'], 1); ?> km away</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="nutri-actions-mini">
                  <button type="button" class="action-btn-mini book" onclick="openBookModal(<?php echo $nutri['id']; ?>, '<?php echo htmlspecialchars($nutri['fullname'], ENT_QUOTES); ?>')" title="Book Appointment">
                    <i class="fa-regular fa-calendar-plus"></i>
                  </button>
                  <a href="chat.php?nutritionist_id=<?php echo $nutri['id']; ?>" class="action-btn-mini chat" title="Chat Nutritionist"><i class="fa-solid fa-comment"></i></a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </aside>
      </div>
    </div>

    <!-- TAB 2: MY APPOINTMENTS TRACKER & RATE BUTTON -->
    <div id="tab-appointments" class="tab-content">
      <div class="appointments-tracker-grid">
        <?php if (count($user_appointments) > 0): ?>
          <?php foreach ($user_appointments as $app): ?>
            <div class="user-appointment-card">
              <div>
                <div class="user-app-header">
                  <strong><?php echo htmlspecialchars($app['nutritionist_name']); ?></strong>
                  <span class="appointment-status-badge <?php echo $app['status']; ?>"><?php echo $app['status']; ?></span>
                </div>
                <div class="user-app-details">
                  <div><i class="fa-regular fa-calendar"></i> <strong>Date:</strong> <?php echo date('F d, Y', strtotime($app['appointment_date'])); ?></div>
                  <div><i class="fa-regular fa-clock"></i> <strong>Time:</strong> <?php echo date('g:i A', strtotime($app['appointment_time'])); ?></div>
                </div>
                <?php if (!empty($app['notes'])): ?>
                  <div class="user-app-notes"><strong>Notes:</strong> <?php echo htmlspecialchars($app['notes']); ?></div>
                <?php endif; ?>
              </div>

              <?php if (strtolower($app['status']) === 'completed'): ?>
                <button type="button" class="btn-rate-review" onclick="openRatingModal(<?php echo $app['nutritionist_id']; ?>, '<?php echo htmlspecialchars($app['nutritionist_name'], ENT_QUOTES); ?>')">
                  <i class="fa-solid fa-star"></i> Rate Session
                </button>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-box" style="grid-column: 1 / -1;">You haven't booked any consultations yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<!-- RATING MODAL -->
<div id="ratingModal" class="rating-modal-overlay">
  <div class="rating-modal-card">
    <div class="modal-header-flex">
      <div class="modal-title-wrap"><h3>Rate Session</h3></div>
      <button type="button" class="modal-close-btn" onclick="closeRatingModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-subtitle">How was your consultation with <strong id="ratingNutriName"></strong>?</div>
    
    <form method="POST">
      <input type="hidden" name="nutritionist_id" id="ratingNutriId">
      <input type="hidden" name="rating" id="ratingValue" value="5">

      <div class="star-rating-container" id="starContainer">
        <i class="fa-solid fa-star selected" data-value="1" onclick="setRating(1)"></i>
        <i class="fa-solid fa-star selected" data-value="2" onclick="setRating(2)"></i>
        <i class="fa-solid fa-star selected" data-value="3" onclick="setRating(3)"></i>
        <i class="fa-solid fa-star selected" data-value="4" onclick="setRating(4)"></i>
        <i class="fa-solid fa-star selected" data-value="5" onclick="setRating(5)"></i>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-modal-cancel" onclick="closeRatingModal()">Cancel</button>
        <button type="submit" name="submit_review_rating" class="btn-modal-submit">Submit Rating</button>
      </div>
    </form>
  </div>
</div>

<!-- APPOINTMENT BOOKING MODAL -->
<div id="appointmentModal" class="appointment-modal-overlay">
  <div class="appointment-modal-card">
    <div class="modal-header-flex">
      <div class="modal-title-wrap"><h3>Book Consultation</h3></div>
      <button type="button" class="modal-close-btn" onclick="closeBookModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-subtitle">Schedule an appointment with <strong id="modalNutriName"></strong></div>
    <form method="POST">
      <input type="hidden" name="nutritionist_id" id="modalNutriId">
      <div class="modal-form-group">
        <label>Preferred Date</label>
        <div class="modal-input-wrap"><input type="date" name="appointment_date" min="<?php echo date('Y-m-d'); ?>" required></div>
      </div>
      <div class="modal-form-group">
        <label>Preferred Time</label>
        <div class="modal-input-wrap"><input type="time" name="appointment_time" required></div>
      </div>
      <div class="modal-form-group">
        <label>Notes / Purpose</label>
        <div class="modal-input-wrap"><textarea name="notes" required></textarea></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-modal-cancel" onclick="closeBookModal()">Cancel</button>
        <button type="submit" name="book_appointment_submit" class="btn-modal-submit">Submit Request</button>
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
function switchTab(tabName, btn) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tabName).classList.add('active');
    btn.classList.add('active');
}

function openRatingModal(nutriId, nutriName) {
    document.getElementById('ratingNutriId').value = nutriId;
    document.getElementById('ratingNutriName').textContent = nutriName;
    setRating(5);
    document.getElementById('ratingModal').classList.add('active');
}

function closeRatingModal() {
    document.getElementById('ratingModal').classList.remove('active');
}

function setRating(score) {
    document.getElementById('ratingValue').value = score;
    const stars = document.querySelectorAll('#starContainer i');
    stars.forEach((star, index) => {
        if (index < score) {
            star.classList.add('selected');
        } else {
            star.classList.remove('selected');
        }
    });
}

function openBookModal(nutriId, nutriName) {
    document.getElementById('modalNutriId').value = nutriId;
    document.getElementById('modalNutriName').textContent = nutriName;
    document.getElementById('appointmentModal').classList.add('active');
}
function closeBookModal() { document.getElementById('appointmentModal').classList.remove('active'); }
function showLogoutModal() { document.getElementById('logoutModal').classList.add('active'); }
function closeLogoutModal() { document.getElementById('logoutModal').classList.remove('active'); }

document.getElementById('logoutModal').addEventListener('click', function(e) {
  if (e.target === this) { closeLogoutModal(); }
});
document.getElementById('appointmentModal').addEventListener('click', function(e) {
  if (e.target === this) { closeBookModal(); }
});
document.getElementById('ratingModal').addEventListener('click', function(e) {
  if (e.target === this) { closeRatingModal(); }
});
</script>
</body>
</html>