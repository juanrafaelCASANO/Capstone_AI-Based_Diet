<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT n.id, n.fullname, n.email, n.profile_pic, 
               p.specialty AS specialization 
        FROM nutritionist n
        LEFT JOIN nutritionists_profile p ON n.fullname = p.name
        WHERE n.status = 'approved' OR n.status IS NULL";

$nutritionists = $conn->query($sql);
$nutritionist_list = [];

if($nutritionists) {
    while($row = $nutritionists->fetch(PDO::FETCH_ASSOC)) {
        $nutritionist_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI-Based Diet & Nutritional Planner</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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
.nav a{display:flex;align-items:center;gap:12px;text-decoration:none;padding:13px 12px;border-radius:12px;color:#d9ebe4;font-size:14px;font-weight:650;transition:background .2s,transform .2s}
.nav a:hover{background:rgba(255,255,255,.09);transform:translateX(2px)}
.nav a.active{background:rgba(255,255,255,.14);color:#fff}
.nav-icon{width:24px;text-align:center;font-size:17px}

.sidebar-bottom{margin-top:auto;border-top:1px solid rgba(255,255,255,.1);padding-top:16px}
.sidebar-bottom a.logout{
  display:flex;align-items:center;gap:12px;text-decoration:none;padding:13px 12px;
  border-radius:12px;color:#f8d7da!important;font-size:14px;font-weight:650;transition:background .2s,transform .2s;
}
.sidebar-bottom a.logout:hover{background:rgba(220,53,69,.2);transform:translateX(2px)}

/* LOGOUT MODAL STYLES */
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
.avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:var(--green-soft);color:var(--green);font-weight:800}
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
.status{display:inline-flex;align-items:center;gap:6px;color:var(--green);font-size:11px;font-weight:750;white-space:nowrap}
.status-dot{width:7px;height:7px;border-radius:50%;background:#4aa66f}

.info-card{
  background:#fff;border:1px solid var(--border);border-radius:20px;padding:20px;height:max-content;
  box-shadow:0 5px 18px rgba(27,61,48,.04);display:flex;flex-direction:column;gap:16px;
}
.info-card h2{font-size:16px;margin:0 0 4px;font-weight:700}
.select-wrapper label{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:6px}
#nutritionist{
  width:100%;min-height:44px;padding:0 12px;border:1px solid var(--border);border-radius:11px;
  background:#fff;color:var(--text);outline:none;font-size:14px;cursor:pointer
}
#nutritionist:focus{border-color:var(--green);box-shadow:0 0 0 3px rgba(59,143,99,.12)}

.nutritionist-profile-display{
  display:flex;flex-direction:column;align-items:center;text-align:center;
  padding-top:12px;border-top:1px solid var(--border);
}
.profile-img-container{
  width:96px;height:96px;border-radius:50%;overflow:hidden;
  border:3px solid var(--green-soft);background:#e8f0ec;margin-bottom:12px;
  display:grid;place-items:center;box-shadow:0 4px 12px rgba(0,0,0,0.06);
}
.profile-img-container img{width:100%;height:100%;object-fit:cover}
.profile-img-placeholder{font-size:42px}
.nutritionist-name{font-size:16px;font-weight:700;color:var(--text);margin:0 0 4px}
.nutritionist-spec{font-size:12px;font-weight:600;color:var(--green);background:var(--green-soft);padding:4px 12px;border-radius:99px;display:inline-block;margin-bottom:12px}

.details-grid{
  width:100%;display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;text-align:left;
}
.detail-item{background:#f8faf8;border:1px solid var(--border);padding:8px 10px;border-radius:10px}
.detail-item span{display:block;font-size:10px;color:var(--muted);font-weight:700;text-transform:uppercase}
.detail-item strong{display:block;font-size:12px;color:var(--text);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

.nutritionist-bio{font-size:12px;color:var(--muted);line-height:1.5;margin:0;text-align:left;width:100%}

.chat-body{
  flex:1;min-height:0;padding:18px;overflow-y:auto;background:#f2f6f3;
  display:flex;flex-direction:column;gap:2px;scroll-behavior:smooth
}
.chat-body:empty:before{
  content:"Start a conversation with your nutritionist.";
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
      <a class="active" href="chat.php"><span class="nav-icon">◌</span>Nutritionist</a>
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
          <div class="eyebrow">Nutrition support</div>
          <h1>Nutritionist Chat</h1>
        </div>
      </div>
      <a class="profile" href="profile.php" title="View & Edit Profile">
        <div class="avatar">💬</div>
        <span class="profile-name">Live Chat</span>
      </a>
    </header>

    <section class="chat-layout">
      <section class="chat-container" aria-label="Nutritionist chat">
        <header class="chat-header">
          <div class="chat-title">
            <div class="chat-title-icon">💬</div>
            <div>
              <strong>Chat with Nutritionist</strong>
              <span id="active-nutritionist-label">Select a nutritionist to begin</span>
            </div>
          </div>
          <div class="status"><span class="status-dot"></span>Connected</div>
        </header>

        <div class="chat-body" id="chat-box" aria-live="polite"></div>

        <div class="chat-footer">
          <input type="text" id="message" placeholder="Type your message..." autocomplete="off" aria-label="Message">
          <button id="send" type="button">Send</button>
        </div>
      </section>

      <aside class="info-card">
        <div>
          <h2>Select Nutritionist</h2>
          <div class="select-wrapper">
            <select id="nutritionist" aria-label="Select nutritionist">
              <?php foreach($nutritionist_list as $nutri): ?>
                <option 
                  value="<?php echo $nutri['id']; ?>"
                  data-fullname="<?php echo htmlspecialchars($nutri['fullname'] ?? ''); ?>"
                  data-pic="<?php echo htmlspecialchars($nutri['profile_pic'] ?? ''); ?>"
                  data-spec="<?php echo htmlspecialchars($nutri['specialization'] ?? 'Certified Nutritionist'); ?>"
                  data-exp="<?php echo htmlspecialchars($nutri['experience'] ?? 'N/A'); ?>"
                  data-cert="<?php echo htmlspecialchars($nutri['certification'] ?? 'Certified'); ?>"
                  data-bio="<?php echo htmlspecialchars($nutri['bio'] ?? 'Available for diet plans and nutritional guidance.'); ?>">
                  <?php echo htmlspecialchars($nutri['fullname']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="nutritionist-profile-display">
          <div class="profile-img-container" id="display-pic-box">
            <span class="profile-img-placeholder">🧑‍⚕️</span>
          </div>
          <h3 class="nutritionist-name" id="display-name">-</h3>
          <span class="nutritionist-spec" id="display-spec">Certified Nutritionist</span>

          <div class="details-grid">
            <div class="detail-item">
              <span>Experience</span>
              <strong id="display-exp">N/A</strong>
            </div>
            <div class="detail-item">
              <span>License / Cert</span>
              <strong id="display-cert">Verified</strong>
            </div>
          </div>

          <p class="nutritionist-bio" id="display-bio">Select a specialist above to start chatting.</p>
        </div>
      </aside>
    </section>
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

let user_id = <?php echo $user_id; ?>;
let nutritionist_id = $('#nutritionist').val();

function updateProfileCard() {
  const selected = $('#nutritionist option:selected');
  if(!selected.length) return;

  const name = selected.data('fullname');
  let pic = selected.data('pic');
  const spec = selected.data('spec');
  const exp = selected.data('exp');
  const cert = selected.data('cert');
  const bio = selected.data('bio');

  $('#display-name').text(name);
  $('#display-spec').text(spec);
  $('#display-exp').text(exp);
  $('#display-cert').text(cert);
  $('#display-bio').text(bio);
  $('#active-nutritionist-label').text('Talking to ' + name);

  const $picBox = $('#display-pic-box');
  $picBox.empty();

  if (pic && pic.toString().trim() !== '') {
    let filename = pic.toString().trim();
    let imageSrc = (filename.startsWith('http') || filename.startsWith('/')) ? filename : '../uploads/profile_pics/' + filename;

    const $img = $('<img>', { src: imageSrc, alt: name });
    $img.on('error', function() {
      $picBox.html('<span class="profile-img-placeholder">🧑‍⚕️</span>');
    });

    $picBox.append($img);
  } else {
    $picBox.html('<span class="profile-img-placeholder">🧑‍⚕️</span>');
  }
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

$('#nutritionist').change(function(){
  nutritionist_id = $(this).val();
  updateProfileCard();
  $('#chat-box').html('');
  fetchMessages();
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

updateProfileCard();
setInterval(fetchMessages, 2000);
fetchMessages();
</script>
</body>
</html>