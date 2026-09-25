<?php
session_start();
require_once '../config.php';

// Ensure user is logged in AND is a nutritionist[cite: 8]
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'nutritionist') {
    header("Location: ../auth/login.php");
    exit;
}

$nutri_id = $_SESSION['user_id'];

// Fetch nutritionist profile info from the CORRECT table[cite: 8]
$stmt = $conn->prepare("SELECT fullname, email FROM nutritionist WHERE id = ?");
$stmt->execute([$nutri_id]);
$nutri = $stmt->fetch();

if (!$nutri) {
    session_destroy();
    header("Location: ../auth/login.php?error=user_not_found");
    exit;
}

$unread_count = 0; 

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ?");
    $stmt->execute([$nutri_id]);
    $row = $stmt->fetch();
    if ($row) {
        $unread_count = $row['total'];
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

.app{min-height:100vh;display:flex}

/* SIDEBAR - Fixed at pantay-pantay */
.sidebar{
  width:260px;
  flex:0 0 260px;
  height:100vh;
  position:sticky;
  top:0;
  align-self:flex-start;
  background:linear-gradient(180deg,var(--navy),#0a2820);
  color:#fff;
  padding:24px 18px;
  display:flex;
  flex-direction:column;
  overflow:hidden;
  z-index:20;
}
.brand{
  display:flex;align-items:center;gap:12px;
  padding:6px 10px 28px;
  flex-shrink:0;
}
.brand-icon{
  width:42px;height:42px;border-radius:13px;
  display:grid;place-items:center;background:rgba(255,255,255,.12);
  font-size:23px;
}
.brand strong{display:block;font-size:17px;letter-spacing:-.2px;font-family:'Plus Jakarta Sans',sans-serif}
.brand span{display:block;color:#99f6e4;font-size:12px;margin-top:2px;font-family:'Plus Jakarta Sans',sans-serif}
.nav-label{
  padding:0 12px 8px;color:#80e0d0;
  text-transform:uppercase;letter-spacing:.11em;font-size:10px;font-weight:800;
  flex-shrink:0;
}
.nav{display:grid;gap:6px;flex-shrink:0}
.nav a{
  display:flex;align-items:center;gap:12px;
  text-decoration:none;padding:13px 12px;border-radius:12px;
  color:#e6fffa;font-size:14px;font-weight:650;
  font-family:'Plus Jakarta Sans',sans-serif;
  transition:background .2s,transform .2s;
  position:relative;
}
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

/* SIDEBAR BOTTOM & LOGOUT */
.sidebar-bottom{margin-top:auto;border-top:1px solid rgba(255,255,255,.1);padding-top:16px;flex-shrink:0}
.sidebar-bottom a.logout{
  display:flex;align-items:center;gap:12px;text-decoration:none;padding:13px 12px;
  border-radius:12px;color:#f8d7da!important;font-size:14px;font-weight:650;
  font-family:'Plus Jakarta Sans',sans-serif;
  transition:background .2s,transform .2s;
}
.sidebar-bottom a.logout:hover{background:rgba(220,53,69,.2);transform:translateX(2px)}

/* MAIN CONTENT */
.main{min-width:0;flex:1;padding:26px clamp(18px,4vw,48px) 48px}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:26px}
.menu-btn{
  display:none;border:1px solid var(--border);background:#fff;
  width:44px;height:44px;border-radius:12px;cursor:pointer;color:var(--text);
}
.eyebrow{font-size:12px;font-weight:800;color:var(--teal);text-transform:uppercase;letter-spacing:.1em}
.welcome h1{font-size:clamp(25px,3vw,36px);line-height:1.15;margin:5px 0 0;letter-spacing:-.8px}

.profile{
  display:flex;align-items:center;gap:10px;padding:7px 11px 7px 7px;
  background:#fff;border:1px solid var(--border);border-radius:999px;
  text-decoration:none;transition:border-color .2s,box-shadow .2s;
}
.profile:hover{border-color:#99f6e4;box-shadow:0 4px 12px rgba(13,148,136,.08)}
.avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:var(--teal-soft);color:var(--teal-dark);font-weight:800}
.profile-name{font-size:13px;font-weight:700;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* HERO SECTION */
.hero{
  background:linear-gradient(135deg,#e6fffa 0%,#f0fdfa 70%);
  border:1px solid #ccfbf1;border-radius:24px;padding:clamp(24px,4vw,38px);
  display:grid;grid-template-columns:minmax(0,1.3fr) minmax(220px,.7fr);
  gap:30px;align-items:center;overflow:hidden;position:relative;
}
.hero:after{
  content:"🩺";position:absolute;right:5%;bottom:-20px;font-size:150px;opacity:.08;pointer-events:none;
}
.hero h2{font-size:clamp(25px,3.4vw,42px);line-height:1.08;letter-spacing:-1.2px;margin:8px 0 12px;max-width:650px}
.hero p{color:var(--muted);max-width:600px;line-height:1.7;margin:0 0 22px}
.actions{display:flex;flex-wrap:wrap;gap:10px}
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  min-height:46px;padding:0 18px;border-radius:12px;border:1px solid transparent;
  text-decoration:none;font-weight:750;font-size:14px;cursor:pointer;
  transition:transform .2s,box-shadow .2s,background .2s;
}
.btn:hover{transform:translateY(-1px)}
.btn-primary{background:var(--teal);color:#fff;box-shadow:0 8px 18px rgba(13,148,136,.25)}
.btn-secondary{background:#fff;color:var(--navy);border-color:var(--border)}

.hero-stat{position:relative;z-index:1;background:#fff;border:1px solid var(--border);border-radius:18px;padding:20px;box-shadow:var(--shadow)}
.hero-stat .emoji{font-size:30px}
.hero-stat strong{display:block;font-size:17px;margin-top:10px}
.hero-stat span{display:block;color:var(--muted);font-size:13px;line-height:1.5;margin-top:5px}

.section-title{display:flex;align-items:end;justify-content:space-between;gap:15px;margin:30px 0 14px}
.section-title h3{margin:0;font-size:18px}
.section-title p{margin:0;color:var(--muted);font-size:13px}

/* GRID CARDS */
.cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
.card{
  background:var(--card);border:1px solid var(--border);border-radius:var(--radius);
  padding:22px;box-shadow:0 5px 18px rgba(15,58,46,.04);
  transition:transform .2s,box-shadow .2s,border-color .2s;
}
.card:hover{transform:translateY(-3px);box-shadow:var(--shadow);border-color:#b2e3d8}
.card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}
.card-icon{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;background:var(--teal-soft);font-size:22px}
.card h3{margin:15px 0 7px;font-size:19px}
.card p{margin:0;color:var(--muted);line-height:1.6;font-size:14px}
.card .btn{margin-top:19px}

.quick-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.quick{
  background:#fff;border:1px solid var(--border);border-radius:15px;padding:17px;
  text-decoration:none;display:block;transition:transform .2s,border-color .2s;
}
.quick:hover{transform:translateY(-2px);border-color:#b2e3d8}
.quick b{font-size:14px}
.quick span{display:block;color:var(--muted);font-size:12px;margin-top:5px;line-height:1.5}

/* LOGOUT MODAL STYLES */
.logout-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 58, 46, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.25s ease, visibility 0.25s ease;
}

.logout-modal-overlay.active {
  opacity: 1;
  visibility: visible;
}

.logout-modal-card {
  background: #ffffff;
  width: 90%;
  max-width: 380px;
  border-radius: 20px;
  padding: 28px 24px;
  text-align: center;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  transform: scale(0.85);
  transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.logout-modal-overlay.active .logout-modal-card {
  transform: scale(1);
}

.logout-modal-icon {
  width: 56px;
  height: 56px;
  background: #fef2f2;
  color: #dc2626;
  font-size: 26px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  margin: 0 auto 16px;
}

.logout-modal-card h3 {
  margin: 0 0 8px;
  font-size: 20px;
  font-weight: 700;
  color: #112d25;
}

.logout-modal-card p {
  margin: 0 0 24px;
  font-size: 14px;
  color: #5c736c;
  line-height: 1.5;
}

.logout-modal-actions {
  display: flex;
  gap: 12px;
}

.btn-modal-cancel {
  flex: 1;
  height: 44px;
  background: #f4fbf7;
  color: #112d25;
  border: 1px solid #dbece5;
  border-radius: 12px;
  font-weight: 650;
  font-size: 14px;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-modal-cancel:hover {
  background: #e6fffa;
}

.btn-modal-logout {
  flex: 1;
  height: 44px;
  background: #dc2626;
  color: #ffffff;
  border: none;
  border-radius: 12px;
  font-weight: 650;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  transition: background 0.2s, transform 0.2s;
}

.btn-modal-logout:hover {
  background: #b91c1c;
  transform: translateY(-1px);
}

/* RESPONSIVE LAYOUT */
.overlay{display:none}
@media(max-width:900px){
  .sidebar{width:240px;flex-basis:240px}
  .hero{grid-template-columns:1fr}
  .quick-grid{grid-template-columns:1fr}
}
@media(max-width:720px){
  .app{display:block}
  .sidebar{
    position:fixed;left:0;top:0;bottom:0;min-height:100vh;
    transform:translateX(-105%);box-shadow:20px 0 40px rgba(0,0,0,.16);
  }
  .sidebar.open{transform:translateX(0)}
  .overlay{
    position:fixed;inset:0;background:rgba(10,40,32,.42);z-index:15;
  }
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

    <!-- HERO PANEL -->
    <section class="hero">
      <div>
        <div class="eyebrow">Client Consultation</div>
        <h2>Help users reach their health & dietary goals.</h2>
        <p>Manage user inquiries, evaluate AI-generated meal plans, and deliver personalized diet suggestions.</p>
        <div class="actions">
          <a class="btn btn-primary" href="inbox.php">📥 Open Client Inbox</a>
          <a class="btn btn-secondary" href="profile.php">⚙️ Account Settings</a>
        </div>
      </div>
      <div class="hero-stat">
        <div class="emoji">💬</div>
        <strong>Recent Inquiries</strong>
        <span>You currently have <b><?php echo $unread_count; ?></b> active user inquiry message<?php echo $unread_count === 1 ? '' : 's'; ?>.</span>
      </div>
    </section>

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
          <div class="card-icon">📬</div>
        </div>
        <h3>Client Messages</h3>
        <p>Review incoming inquiries from registered users seeking professional dietary guidance and plan adjustments.</p>
        <a class="btn btn-primary" href="inbox.php">View Inbox (<?php echo $unread_count; ?>) <span>→</span></a>
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
      <a class="quick" href="inbox.php">
        <b>📥 Patient Inbox</b>
        <span>Read & reply to active user chats.</span>
      </a>
      <a class="quick" href="profile.php">
        <b>👤 Edit Credentials</b>
        <span>Update practitioner settings.</span>
      </a>
      <a class="quick" href="javascript:void(0);" onclick="showLogoutModal();">
        <b>↪ End Session</b>
        <span>Log out of the practitioner portal safely.</span>
      </a>
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
</script>
</body>
</html>