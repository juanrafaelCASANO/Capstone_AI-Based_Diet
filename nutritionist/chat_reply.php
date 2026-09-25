<?php
session_start();
require_once '../config.php';

// Ibinalik natin sa 'user_id' dahil ito pala ang ginagamit ng system mo
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$nutri_id = $_SESSION['user_id'];
$user_id = $_GET['user_id'] ?? 0;

// Fetch nutritionist profile info (Nakatutok na sa 'nutritionist' table)
$stmt = $conn->prepare("SELECT fullname FROM nutritionist WHERE id = ?");
$stmt->execute([$nutri_id]);
$nutri = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$nutri) {
    session_destroy();
    header("Location: ../auth/login.php?error=user_not_found");
    exit;
}

// Fetch user/client details
$user_stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    header("Location: inbox.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Consultation with <?php echo htmlspecialchars($user_data['fullname'] ?? ''); ?> | Nutritionist Portal</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🩺</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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

/* SIDEBAR STYLES */
.sidebar {
  width: 260px;
  flex: 0 0 260px;
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

.client-card {
  margin-top: 15px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 14px;
  padding: 14px;
}
.client-card h4 {
  margin: 0 0 6px;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: #99f6e4;
}
.client-card-name {
  font-size: 15px;
  font-weight: 700;
  color: #fff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar-bottom { margin-top: auto; border-top: 1px solid rgba(255,255,255,.1); padding-top: 16px; }
.sidebar-bottom a.logout {
  display: flex; align-items: center; gap: 12px; padding: 13px 12px;
  border-radius: 12px; color: #f8d7da !important; font-size: 14px; font-weight: 650;
  transition: background .2s, transform .2s;
}
.sidebar-bottom a.logout:hover { background: rgba(220,53,69,.2); transform: translateX(2px); }

/* LOGOUT MODAL STYLES */
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

.overlay { display: none; }
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

    <div class="client-card">
      <h4>Active Client</h4>
      <div class="client-card-name"><?php echo htmlspecialchars($user_data['fullname'] ?? ''); ?></div>
    </div>

    <div class="nav-label" style="margin-top:20px;">Menu</div>
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

    <div class="chat-wrapper">
      <div class="chat-header">
        <div class="chat-client-info">
          <div class="chat-client-avatar">
            <?php echo htmlspecialchars(strtoupper(substr($user_data['fullname'] ?? 'U', 0, 1))); ?>
          </div>
          <div class="chat-client-details">
            <strong><?php echo htmlspecialchars($user_data['fullname'] ?? ''); ?></strong>
            <span>Active Consultation</span>
          </div>
        </div>
      </div>

      <div id="chat-box"></div>

      <div class="chat-input-area">
        <input type="text" id="message" placeholder="Type professional advice or reply..." autocomplete="off">
        <button id="send"><i class="fa-solid fa-paper-plane"></i> Send</button>
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

let user_id = <?php echo json_encode($user_id); ?>;
let nutri_id = <?php echo json_encode($nutri_id); ?>;

function fetchMessages() {
    if (!user_id || !nutri_id) return;
    $.post('fetch_messages_nutritionist.php', {
        user_id: user_id, 
        nutritionist_id: nutri_id
    }, function(data){
        const box = $('#chat-box');
        const wasNearBottom = box[0].scrollHeight - box.scrollTop() - box.outerHeight() < 80;
        box.html(data);
        if (wasNearBottom) box.scrollTop(box[0].scrollHeight);
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
            } else {
                alert('Server returned: ' + response);
            }
        }).fail(function(xhr, status, error) {
            alert('Request Failed: ' + error);
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