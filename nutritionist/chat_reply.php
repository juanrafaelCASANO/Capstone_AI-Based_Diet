<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$nutri_id = $_SESSION['user_id'];
$user_id = $_GET['user_id'] ?? 0;

$stmt = $conn->prepare("SELECT fullname, latitude, longitude FROM nutritionist WHERE id = ?");
$stmt->execute([$nutri_id]);
$nutri = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$nutri) {
    session_destroy();
    header("Location: ../auth/login.php?error=user_not_found");
    exit;
}

$nutri_lat = floatval($nutri['latitude'] ?? 14.3498);
$nutri_lng = floatval($nutri['longitude'] ?? 121.0794);

// Kunin ang impormasyon ng user kasama ang body info kung mayroon man
$user_stmt = $conn->prepare("SELECT fullname, email, address, latitude, longitude, age, gender, height, weight, goal FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    header("Location: inbox.php");
    exit;
}

$client_name = $user_data['fullname'] ?? 'Client';
$client_email = $user_data['email'] ?? '';
$client_address = $user_data['address'] ?? 'No address provided yet';
$client_lat = floatval($user_data['latitude'] ?? 14.3498); 
$client_lng = floatval($user_data['longitude'] ?? 121.0794);

// Body Info ng User
$client_age = $user_data['age'] ?? 'N/A';
$client_gender = $user_data['gender'] ?? 'N/A';
$client_height = $user_data['height'] ?? 'N/A';
$client_weight = $user_data['weight'] ?? 'N/A';
$client_goal = $user_data['goal'] ?? 'N/A';

// Function para sa Haversine distance calculation
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return 999999; 
    $earthRadius = 6371; 
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}

$distance_val = calculateDistance($nutri_lat, $nutri_lng, $client_lat, $client_lng);
$dist_label = "";
if ($distance_val < 999999) {
    if ($distance_val < 1) {
        $dist_label = round($distance_val * 1000) . " m away";
    } else {
        $dist_label = round($distance_val, 1) . " km away";
    }
}

// Suriin kung mayroon nang mensahe mula sa parehong panig
$check_msg_stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN sender_id = ? THEN 1 ELSE 0 END) as nutri_count,
        SUM(CASE WHEN sender_id = ? THEN 1 ELSE 0 END) as client_count
    FROM messages 
    WHERE (sender_id = ? AND receiver_id = ?) 
       OR (sender_id = ? AND receiver_id = ?)
");
$check_msg_stmt->execute([$nutri_id, $user_id, $nutri_id, $user_id, $user_id, $nutri_id]);
$msg_counts = $check_msg_stmt->fetch(PDO::FETCH_ASSOC);

$has_both_messaged = (($msg_counts['nutri_count'] ?? 0) > 0) && (($msg_counts['client_count'] ?? 0) > 0);

try {
    $mark_read_stmt = $conn->prepare("
        UPDATE messages 
        SET status = 1 
        WHERE sender_id = ? AND receiver_id = ? AND status = 0
    ");
    $mark_read_stmt->execute([$user_id,$nutri_id]);
} catch (Exception $e) {
    // Handle error if needed
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Consultation with <?php echo htmlspecialchars($client_name); ?> | Nutritionist Portal</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🩺</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Leaflet CSS para sa MapTiler -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />

<style>
:root {
  --navy: #0f3a2e;
  --navy-2: #165242;
  --teal: #0d9488;
  --teal-soft: #ccfbf1;
  --teal-dark: #0f766e;
  --cream: #f4fbf7;
  --card: #ffffff;
  --text: #112d25;
  --muted: #5c736c;
  --border: #dbece5;
  --shadow: 0 10px 30px rgba(15,58,46,.08);
  --radius: 18px;
  --danger: #dc2626;
}

* { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
  margin: 0;
  font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  background: var(--cream);
  color: var(--text);
}
button, input, a { font: inherit; }
a { color: inherit; text-decoration: none; }

.app { min-height: 100vh; display: flex; }

.sidebar {
  width: 280px;
  flex: 0 0 280px;
  min-height: 100vh;
  position: sticky;
  top: 0;
  align-self: flex-start;
  background: linear-gradient(180deg, var(--navy), #0a2820);
  color: #fff;
  padding: 24px 18px;
  display: flex;
  flex-direction: column;
  transition: transform .25s ease;
  z-index: 20;
}
.brand {
  display: flex; align-items: center; gap: 12px;
  padding: 6px 10px 20px;
}
.brand-icon {
  width: 42px; height: 42px; border-radius: 13px;
  display: grid; place-items: center; background: rgba(255,255,255,.12);
  font-size: 23px;
}
.brand strong { display: block; font-size: 17px; letter-spacing: -.2px; }
.brand span { display: block; color: #99f6e4; font-size: 12px; margin-top: 2px; }

.nav-label {
  padding: 0 12px 8px; color: #80e0d0;
  text-transform: uppercase; letter-spacing: .11em; font-size: 10px; font-weight: 800;
}
.nav { display: grid; gap: 6px; }
.nav a {
  display: flex; align-items: center; gap: 12px;
  padding: 13px 12px; border-radius: 12px;
  color: #e6fffa; font-size: 14px; font-weight: 650;
  transition: background .2s, transform .2s;
}
.nav a:hover { background: rgba(255,255,255,.09); transform: translateX(2px); }
.nav a.active { background: rgba(255,255,255,.14); color: #fff; }
.nav-icon { width: 24px; text-align: center; font-size: 17px; }

.sidebar-bottom { margin-top: auto; border-top: 1px solid rgba(255,255,255,.1); padding-top: 16px; }
.sidebar-bottom a.logout {
  display: flex; align-items: center; gap: 12px; padding: 13px 12px;
  border-radius: 12px; color: #f8d7da !important; font-size: 14px; font-weight: 650;
  transition: background .2s, transform .2s;
}
.sidebar-bottom a.logout:hover { background: rgba(220,53,69,.2); transform: translateX(2px); }

/* CONSULTATION LAYOUT (CHAT + RIGHT CLIENT PANEL) */
.consultation-layout {
  display: flex;
  gap: 24px;
  align-items: flex-start;
  flex: 1;
  min-height: 0;
}
.chat-col {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  height: 100%;
}
.client-info-col {
  width: 340px;
  flex-shrink: 0;
  position: sticky;
  top: 26px;
}
.client-info-panel {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 24px;
  box-shadow: var(--shadow);
  text-align: center;
}
.client-avatar-large {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: var(--teal-soft);
  color: var(--teal-dark);
  font-size: 24px;
  font-weight: 800;
  display: grid;
  place-items: center;
  margin: 0 auto 12px;
}
.client-info-name {
  font-size: 18px;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 8px;
}
.client-info-distance {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--teal-soft);
  color: var(--teal-dark);
  padding: 4px 10px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 16px;
}
.client-info-body {
  background: var(--cream);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 14px;
  text-align: left;
  margin-bottom: 16px;
  display: grid;
  gap: 8px;
  font-size: 13px;
  color: var(--muted);
}
.client-info-body div {
  display: flex;
  justify-content: space-between;
}
.client-info-body strong {
  color: var(--text);
}

.view-map-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  padding: 12px;
  border-radius: 12px;
  background: var(--teal);
  color: #fff;
  border: none;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: background .2s, transform .2s;
}
.view-map-btn:hover { background: var(--teal-dark); transform: translateY(-1px); }

/* MODAL STYLES */
.logout-modal-overlay {
  position: fixed; inset: 0; background: rgba(15, 58, 46, 0.5); backdrop-filter: blur(4px);
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
  width: 56px; height: 56px; background: #fef2f2; color: #dc2626; font-size: 26px;
  border-radius: 50%; display: grid; place-items: center; margin: 0 auto 16px;
}
.logout-modal-card h3 { margin: 0 0 8px; font-size: 20px; font-weight: 700; color: #112d25; }
.logout-modal-card p { margin: 0 0 24px; font-size: 14px; color: #5c736c; line-height: 1.5; }
.logout-modal-actions { display: flex; gap: 12px; }
.btn-modal-cancel {
  flex: 1; height: 44px; background: #f4fbf7; color: #112d25; border: 1px solid #dbece5;
  border-radius: 12px; font-weight: 650; font-size: 14px; cursor: pointer; transition: background 0.2s;
}
.btn-modal-cancel:hover { background: #e6fffa; }
.btn-modal-logout {
  flex: 1; height: 44px; background: #dc2626; color: #ffffff; border: none; border-radius: 12px;
  font-weight: 650; font-size: 14px; display: inline-flex; align-items: center; justify-content: center;
  text-decoration: none; transition: background 0.2s, transform 0.2s;
}
.btn-modal-logout:hover { background: #b91c1c; transform: translateY(-1px); }
.btn-modal-delete {
  flex: 1; height: 44px; background: #dc2626; color: #ffffff; border: none; border-radius: 12px;
  font-weight: 650; font-size: 14px; display: inline-flex; align-items: center; justify-content: center;
  cursor: pointer; transition: background 0.2s, transform 0.2s;
}
.btn-modal-delete:hover { background: #b91c1c; transform: translateY(-1px); }
.btn-modal-ok {
  flex: 1; height: 44px; background: var(--teal); color: #ffffff; border: none; border-radius: 12px;
  font-weight: 650; font-size: 14px; display: inline-flex; align-items: center; justify-content: center;
  cursor: pointer; transition: background 0.2s, transform 0.2s;
}
.btn-modal-ok:hover { background: var(--teal-dark); transform: translateY(-1px); }

.main { min-width: 0; flex: 1; padding: 26px clamp(18px, 4vw, 48px) 48px; display: flex; flex-direction: column; height: 100vh; }
.topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; flex-shrink: 0; }
.menu-btn {
  display: none; border: 1px solid var(--border); background: #fff;
  width: 44px; height: 44px; border-radius: 12px; cursor: pointer; color: var(--text);
}
.eyebrow { font-size: 12px; font-weight: 800; color: var(--teal); text-transform: uppercase; letter-spacing: .1em; }
.welcome h1 { font-size: clamp(22px, 2.8vw, 32px); line-height: 1.15; margin: 4px 0 0; letter-spacing: -.8px; }

.profile {
  display: flex; align-items: center; gap: 10px; padding: 7px 11px 7px 7px;
  background: #fff; border: 1px solid var(--border); border-radius: 999px;
  transition: border-color .2s, box-shadow .2s;
}
.profile:hover { border-color: #99f6e4; box-shadow: 0 4px 12px rgba(13,148,136,.08); }
.avatar { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; background: var(--teal-soft); color: var(--teal-dark); font-weight: 800; }
.profile-name { font-size: 13px; font-weight: 700; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.chat-wrapper {
  flex: 1;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  min-height: 0;
}

.chat-header {
  padding: 16px 24px;
  background: #fff;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.chat-client-info { display: flex; align-items: center; gap: 12px; }
.chat-client-avatar {
  width: 40px; height: 40px; border-radius: 50%;
  background: var(--teal-soft); color: var(--teal-dark);
  display: grid; place-items: center; font-weight: 700; font-size: 16px;
}
.chat-client-details strong { display: block; font-size: 15px; color: var(--text); }
.chat-client-details span { display: block; font-size: 12px; color: var(--muted); }

#chat-box {
  flex: 1;
  padding: 24px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--cream);
}

.msg-wrapper {
  display: flex !important;
  width: 100% !important;
  flex-direction: column;
  margin-bottom: 4px;
}

.msg-wrapper.user-row {
  align-items: flex-start !important;
}

.msg-wrapper.nutritionist-row {
  align-items: flex-end !important;
}

.message {
  max-width: 70%;
  padding: 12px 16px;
  border-radius: 18px;
  font-size: 14px;
  line-height: 1.5;
  word-wrap: break-word;
}

.message.user-bubble {
  background: #e9e9eb !important;
  color: #000000 !important;
  border-bottom-left-radius: 4px !important;
}

.message.nutritionist-bubble {
  background: #007aff !important;
  color: #ffffff !important;
  border-bottom-right-radius: 4px !important;
}

.message, .message *, #chat-box .message *, #chat-box div {
    overflow: visible !important;
    max-height: none !important;
    scrollbar-width: none !important;
}
.message::-webkit-scrollbar, #chat-box *::-webkit-scrollbar {
    display: none !important;
}

.msg-meta {
  font-size: 10px;
  opacity: 0.75;
  margin-top: 4px;
  text-align: right;
}
.message.user-bubble .msg-meta { color: #666; }
.message.nutritionist-bubble .msg-meta { color: rgba(255, 255, 255, 0.8); }

.chat-input-area {
  padding: 16px 20px;
  background: #fff;
  border-top: 1px solid var(--border);
  display: flex;
  gap: 12px;
  align-items: center;
}
.chat-input-area input[type="text"] {
  flex: 1;
  height: 48px;
  padding: 0 18px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--cream);
  color: var(--text);
  outline: none;
  font-size: 14px;
  transition: border-color .2s, background .2s;
}
.chat-input-area input[type="text"]:focus {
  border-color: var(--teal);
  background: #fff;
}
.chat-input-area button {
  height: 48px;
  padding: 0 22px;
  background: var(--teal);
  color: #fff;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  font-weight: 700;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: transform .2s, background .2s;
}
.chat-input-area button:hover {
  background: var(--teal-dark);
  transform: translateY(-1px);
}

.modal {
  position: fixed; inset: 0; background: rgba(15, 23, 42, .55);
  display: none; align-items: center; justify-content: center; padding: 20px; z-index: 1000;
  backdrop-filter: blur(5px);
}
.modal.active { display: flex; }
.modal-box {
  width: 100%; max-width: 650px; background: white; border-radius: 20px; padding: 25px;
  box-shadow: 0 25px 70px rgba(15,23,42,.25); animation: modalIn .25s ease;
}
@keyframes modalIn {
  from { opacity: 0; transform: translateY(15px) scale(.97); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.modal-header h3 { margin: 0; font-size: 18px; color: #112d25; }
.close-modal {
  width: 35px; height: 35px; border: none; border-radius: 8px; background: #f1f5f9;
  cursor: pointer; color: #475569; font-size: 16px; display: grid; place-items: center;
}
.close-modal:hover { background: #e2e8f0; }

.overlay { display: none; }
@media(max-width: 1100px) {
  .consultation-layout {
    flex-direction: column-reverse;
  }
  .client-info-col {
    width: 100%;
    position: static;
  }
}
@media(max-width: 900px) {
  .sidebar { width: 240px; flex-basis: 240px; }
}
@media(max-width: 720px) {
  .app { display: block; }
  .sidebar {
    position: fixed; left: 0; top: 0; bottom: 0; min-height: 100vh;
    transform: translateX(-105%); box-shadow: 20px 0 40px rgba(0,0,0,.16);
  }
  .sidebar.open { transform: translateX(0); }
  .overlay { position: fixed; inset: 0; background: rgba(10,40,32,.42); z-index: 15; }
  .overlay.show { display: block; }
  .main { padding: 16px 12px 20px; height: 100vh; }
  .menu-btn { display: grid; place-items: center; }
  .profile-name { display: none; }
  .message { max-width: 85%; }
}
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
<div class="app">
  <div class="overlay" id="overlay" aria-hidden="true"></div>

  <aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="brand">
      <div class="brand-icon">🩺</div>
      <div>
        <strong>NutriPanel</strong>
        <span>Nutritionist portal</span>
      </div>
    </div>

    <a href="inbox.php" style="display:inline-flex;align-items:center;gap:8px;color:#99f6e4;font-size:13px;font-weight:700;margin-bottom:16px;padding:0 10px;">
      <i class="fa-solid fa-arrow-left"></i> Back to Inbox
    </a>

    <div class="nav-label" style="margin-top:10px;">Menu</div>
    <nav class="nav">
      <a href="dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
      <a href="add_meal.php"><span class="nav-icon">🍲</span>Add Meal</a>
      <a href="inbox.php" class="active"><span class="nav-icon">📥</span>Inbox</a>
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
        <div class="welcome">
          <div class="eyebrow">Client Consultation</div>
          <h1>Live Chat Session</h1>
        </div>
      </div>
      <a class="profile" href="profile.php" title="View & Edit Profile">
        <div class="avatar">
          <?php echo htmlspecialchars(strtoupper(substr($nutri['fullname'] ?? 'N', 0, 1))); ?>
        </div>
        <span class="profile-name"><?php echo htmlspecialchars($nutri['fullname'] ?? 'Nutritionist'); ?></span>
      </a>
    </header>

    <!-- CONSULTATION LAYOUT (CHAT + RIGHT CLIENT PANEL) -->
    <div class="consultation-layout">
      <!-- Chat Column -->
      <div class="chat-col">
        <div class="chat-wrapper">
          <div class="chat-header">
            <div class="chat-client-info">
              <div class="chat-client-avatar">
                <?php echo htmlspecialchars(strtoupper(substr($client_name, 0, 1))); ?>
              </div>
              <div class="chat-client-details">
                <strong><?php echo htmlspecialchars($client_name); ?></strong>
                <span>Active Consultation</span>
              </div>
            </div>

            <?php if ($has_both_messaged): ?>
              <button type="button" onclick="showClearChatModal()" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 7px 14px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: background 0.2s;">
                <i class="fa-solid fa-broom"></i> Clear Chat
              </button>
            <?php endif; ?>
          </div>

          <div id="chat-box"></div>

          <div class="chat-input-area">
            <input type="text" id="message" placeholder="Type professional advice or reply..." autocomplete="off">
            <button id="send"><i class="fa-solid fa-paper-plane"></i> Send</button>
          </div>
        </div>
      </div>

      <!-- Client Info Right Column (Without Kabi) -->
      <div class="client-info-col">
        <div class="client-info-panel">
          <div class="client-avatar-large">
            <?php echo htmlspecialchars(strtoupper(substr($client_name, 0, 1))); ?>
          </div>
          <div class="client-info-name"><?php echo htmlspecialchars($client_name); ?></div>
          <?php if (!empty($dist_label)): ?>
            <div class="client-info-distance">
              <i class="fa-solid fa-location-dot"></i> <?php echo $dist_label; ?>
            </div>
          <?php endif; ?>

          <div class="client-info-body">
            <div><span>Age:</span> <strong><?php echo htmlspecialchars($client_age); ?></strong></div>
            <div><span>Gender:</span> <strong><?php echo htmlspecialchars($client_gender); ?></strong></div>
            <div><span>Height:</span> <strong><?php echo htmlspecialchars($client_height); ?> cm</strong></div>
            <div><span>Weight:</span> <strong><?php echo htmlspecialchars($client_weight); ?> kg</strong></div>
            <div><span>Goal:</span> <strong><?php echo htmlspecialchars($client_goal); ?></strong></div>
          </div>

          <button type="button" class="view-map-btn" onclick="openClientMap(<?php echo $client_lat; ?>, <?php echo $client_lng; ?>, '<?php echo htmlspecialchars(addslashes($client_name)); ?>', '<?php echo htmlspecialchars(addslashes($client_address)); ?>')">
            <i class="fa-solid fa-location-dot" style="margin-right: 4px;"></i> View Location Map
          </button>
        </div>
      </div>
    </div>
  </main>
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

<!-- CLEAR CHAT CONFIRMATION MODAL -->
<div id="clearChatModal" class="logout-modal-overlay">
  <div class="logout-modal-card">
    <div class="logout-modal-icon" style="background: #fef2f2; color: #dc2626;">⚠️</div>
    <h3>Clear Conversation</h3>
    <p>Are you sure you want to clear this entire conversation? This action cannot be undone.</p>
    <div class="logout-modal-actions">
      <button class="btn-modal-cancel" onclick="closeClearChatModal();">Cancel</button>
      <button class="btn-modal-delete" onclick="confirmClearChat();">Yes, Clear Chat</button>
    </div>
  </div>
</div>

<!-- APP ALERT CARD MODAL -->
<div id="appAlertModal" class="logout-modal-overlay">
  <div class="logout-modal-card">
    <div class="logout-modal-icon" id="appAlertIcon" style="background: #e6fffa; color: var(--teal);">ℹ️</div>
    <h3 id="appAlertTitle">Notice</h3>
    <p id="appAlertMessage">Message goes here...</p>
    <div class="logout-modal-actions">
      <button class="btn-modal-ok" onclick="closeAppAlert();">OK</button>
    </div>
  </div>
</div>

<!-- MAP MODAL PARA SA MAPTILER -->
<div class="modal" id="mapModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="mapModalTitle">Client Location</h3>
            <button type="button" class="close-modal" onclick="closeClientMap()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div>
            <div id="map" style="width: 100%; height: 350px; border-radius: 12px;"></div>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
<script>
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

function showAlertCard(title, message, isError = false) {
  document.getElementById('appAlertTitle').textContent = title;
  document.getElementById('appAlertMessage').textContent = message;
  const iconDiv = document.getElementById('appAlertIcon');
  if (isError) {
    iconDiv.style.background = '#fef2f2';
    iconDiv.style.color = '#dc2626';
    iconDiv.innerHTML = '⚠️';
  } else {
    iconDiv.style.background = '#e6fffa';
    iconDiv.style.color = 'var(--teal)';
    iconDiv.innerHTML = '✓';
  }
  document.getElementById('appAlertModal').classList.add('active');
}

function closeAppAlert() {
  document.getElementById('appAlertModal').classList.remove('active');
}

document.getElementById('appAlertModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeAppAlert();
  }
});

function showClearChatModal() {
  document.getElementById('clearChatModal').classList.add('active');
}

function closeClearChatModal() {
  document.getElementById('clearChatModal').classList.remove('active');
}

document.getElementById('clearChatModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeClearChatModal();
  }
});

function confirmClearChat() {
  $.post('delete_conversation.php', {
    user_id: user_id,
    nutritionist_id: nutri_id
  }, function(response) {
    closeClearChatModal();
    if (response.trim() === 'success') {
      window.location.href = 'inbox.php';
    } else {
      showAlertCard('Error', 'Failed to clear chat: ' + response, true);
    }
  }).fail(function(xhr, status, error) {
    closeClearChatModal();
    showAlertCard('Error', 'Request Failed: ' + error, true);
  });
}

let user_id = <?php echo json_encode($user_id); ?>;
let nutri_id = <?php echo json_encode($nutri_id); ?>;

let clientMap = null;

function openClientMap(lat, lng, name, address) {
    document.getElementById('mapModal').classList.add('active');
    document.getElementById('mapModalTitle').innerText = `${name}'s Location`;
    
    let latitude = lat ? lat : 14.3498;
    let longitude = lng ? lng : 121.0794;

    setTimeout(() => {
        if (!clientMap) {
            clientMap = L.map('map').setView([latitude, longitude], 14);
            
            L.tileLayer('https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=ZkO2ll8wJho8o1zc1Sug', {
                attribution: '&copy; MapTiler &copy; OpenStreetMap contributors',
                tileSize: 512,
                zoomOffset: -1
            }).addTo(clientMap);
        } else {
            clientMap.setView([latitude, longitude], 14);
            clientMap.invalidateSize();
        }

        clientMap.eachLayer((layer) => {
            if (layer instanceof L.Marker) {
                clientMap.removeLayer(layer);
            }
        });

        const customPinIcon = L.divIcon({
            className: 'custom-map-pin',
            html: `<div style="
                background-color: #0d9488; 
                width: 32px; 
                height: 32px; 
                border-radius: 50% 50% 50% 0; 
                transform: rotate(-45deg); 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                box-shadow: 0 4px 10px rgba(0,0,0,0.3);
                border: 2px solid #ffffff;">
                    <i class="fa-solid fa-user" style="transform: rotate(45deg); color: white; font-size: 14px;"></i>
               </div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 32],
            popupAnchor: [0, -32]
        });

        L.marker([latitude, longitude], { icon: customPinIcon }).addTo(clientMap)
          .bindPopup(`<b>${name}</b><br>${address ? address : 'Address not specified'}`)
          .openPopup();
    }, 200);
}

function closeClientMap() {
    document.getElementById('mapModal').classList.remove('active');
}

function fetchMessages() {
    if (!user_id || !nutri_id) return;

    const box = $('#chat-box');
    const scrollableCards = box.find('div[style*="overflow-y: auto"]');
    const cardScrollPositions = [];
    scrollableCards.each(function(index) {
        cardScrollPositions.push({
            index: index,
            scrollTop: this.scrollTop
        });
    });

    $.post('fetch_messages_nutritionist.php', {
        user_id: user_id, 
        nutritionist_id: nutri_id
    }, function(data){
        const wasNearBottom = box[0].scrollHeight - box.scrollTop() - box.outerHeight() < 80;
        
        if (box.html() !== data) {
            box.html(data);
            
            const newScrollableCards = box.find('div[style*="overflow-y: auto"]');
            newScrollableCards.each(function(index) {
                const saved = cardScrollPositions.find(item => item.index === index);
                if (saved) {
                    this.scrollTop = saved.scrollTop;
                }
            });

            if (wasNearBottom) {
                box.scrollTop(box[0].scrollHeight);
            }
        }
    });
}

function sendMessage() {
    let msg = $('#message').val();
    if (msg.trim() !== '' && user_id > 0) {
        $('#send').prop('disabled', true);
        
        $.post('send_message_nutri.php', {
            receiver_id: user_id, 
            message: msg
        }, function(response){
            if (response.trim() === 'success') {
                $('#message').val('');
                fetchMessages();
                if (!<?php echo $has_both_messaged ? 'true' : 'false'; ?>) {
                    setTimeout(() => { window.location.reload(); }, 500);
                }
            } else {
                showAlertCard('Error', 'Server returned: ' + response, true);
            }
        }).fail(function(xhr, status, error) {
            showAlertCard('Error', 'Request Failed: ' + error, true);
        }).always(function(){
            $('#send').prop('disabled', false);
            $('#message').focus();
        });
    }
}

$('#send').click(sendMessage);

$('#message').keypress(function(e){
    if (e.which === 13 && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

setInterval(fetchMessages, 2000);
fetchMessages();

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

  window.addEventListener('resize', function(){
    if(window.innerWidth > 720) setMenu(false);
  });
})();
</script>
</body>
</html>