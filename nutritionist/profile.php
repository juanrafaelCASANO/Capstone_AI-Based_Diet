<?php
session_start();
require_once '../config.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$nutritionist_id = $_SESSION['user_id'];

// Fetch detailed profile from nutritionist table
$stmt = $conn->prepare("SELECT * FROM nutritionist WHERE id = ?");
$stmt->execute([$nutritionist_id]);
$profile = $stmt->fetch();

if (!$profile) {
    die("Profile not found.");
}

// Fetch unread messages count for the sidebar badge
$unread_count = 0; 
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ?");
    $stmt->execute([$nutritionist_id]);
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
<title>My Profile | Nutritionist Portal</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🩺</text></svg>">

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
  --danger-bg: #fef2f2;
  --success: #16a34a;
  --success-bg: #dcfce7;
  --warning: #d97706;
  --warning-bg: #fef3c7;
}

* { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
  margin: 0;
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
  background: var(--cream);
  color: var(--text);
}
button, a { font: inherit; }
a { color: inherit; }

.app { min-height: 100vh; display: flex; }

/* SIDEBAR */
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
  padding: 6px 10px 28px;
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
  text-decoration: none; padding: 13px 12px; border-radius: 12px;
  color: #e6fffa; font-size: 14px; font-weight: 650;
  transition: background .2s, transform .2s;
  position: relative;
}
.nav a:hover { background: rgba(255,255,255,.09); transform: translateX(2px); }
.nav a.active { background: rgba(255,255,255,.14); color: #fff; }
.nav-icon { width: 24px; text-align: center; font-size: 17px; }

.nav-badge {
  margin-left: auto;
  background: var(--danger);
  color: #fff;
  font-size: 11px;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 999px;
}

.sidebar-bottom { margin-top: auto; border-top: 1px solid rgba(255,255,255,.1); padding-top: 16px; }
.sidebar-bottom a.logout {
  display: flex; align-items: center; gap: 12px; text-decoration: none; padding: 13px 12px;
  border-radius: 12px; color: #f8d7da!important; font-size: 14px; font-weight: 650;
  transition: background .2s, transform .2s;
}
.sidebar-bottom a.logout:hover { background: rgba(220,53,69,.2); transform: translateX(2px); }

/* MAIN CONTENT AREA */
.main { min-width: 0; flex: 1; padding: 26px clamp(18px,4vw,48px) 48px; }
.topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 26px; }
.menu-btn {
  display: none; border: 1px solid var(--border); background: #fff;
  width: 44px; height: 44px; border-radius: 12px; cursor: pointer; color: var(--text);
}
.eyebrow { font-size: 12px; font-weight: 800; color: var(--teal); text-transform: uppercase; letter-spacing: .1em; }
.welcome h1 { font-size: clamp(25px,3vw,36px); line-height: 1.15; margin: 5px 0 0; letter-spacing: -.8px; }

.profile-link {
  display: flex; align-items: center; gap: 10px; padding: 7px 11px 7px 7px;
  background: #fff; border: 1px solid var(--border); border-radius: 999px;
  text-decoration: none; transition: border-color .2s, box-shadow .2s;
}
.profile-link:hover { border-color: #99f6e4; box-shadow: 0 4px 12px rgba(13,148,136,.08); }
.avatar-small { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; background: var(--teal-soft); color: var(--teal-dark); font-weight: 800; }
.profile-name { font-size: 13px; font-weight: 700; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* DASHBOARD PROFILE CARDS & LAYOUT */
.profile-grid {
  display: grid;
  grid-template-columns: 320px 1fr;
  gap: 24px;
  align-items: start;
}

.card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 28px;
  box-shadow: 0 5px 18px rgba(15,58,46,.04);
}

/* LEFT SIDEBAR HERO PROFILE */
.profile-sidebar-card {
  text-align: center;
}
.avatar-large-wrap {
  position: relative;
  width: 120px;
  height: 120px;
  margin: 0 auto 18px;
}
.avatar-large-wrap img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
  border: 4px solid #fff;
  box-shadow: var(--shadow);
}
.profile-sidebar-card h2 {
  font-size: 22px;
  margin: 0 0 6px;
  letter-spacing: -.5px;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 750;
  text-transform: capitalize;
  margin-bottom: 20px;
}
.status-badge.approved { background: var(--success-bg); color: var(--success); }
.status-badge.pending { background: var(--warning-bg); color: var(--warning); }

/* DETAILS DATA GRID */
.details-header {
  border-bottom: 1px solid var(--border);
  padding-bottom: 16px;
  margin-bottom: 22px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.details-header h3 { margin: 0; font-size: 20px; }

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
}

.info-item {
  background: var(--cream);
  padding: 16px;
  border-radius: 14px;
  border: 1px solid var(--border);
}
.info-item label {
  display: block;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  color: var(--muted);
  letter-spacing: .05em;
  margin-bottom: 6px;
}
.info-item div {
  font-size: 15px;
  font-weight: 650;
  color: var(--text);
  word-break: break-word;
}

.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  min-height: 46px; padding: 0 20px; border-radius: 12px; border: 1px solid transparent;
  text-decoration: none; font-weight: 750; font-size: 14px; cursor: pointer;
  transition: transform .2s, box-shadow .2s, background .2s;
  width: 100%;
}
.btn:hover { transform: translateY(-1px); }
.btn-primary { background: var(--teal); color: #fff; box-shadow: 0 8px 18px rgba(13,148,136,.25); }

/* RESPONSIVE MEDIA QUERIES */
.overlay { display: none; }
@media(max-width: 900px) {
  .sidebar { width: 240px; flex-basis: 240px; }
  .profile-grid { grid-template-columns: 1fr; }
}
@media(max-width: 720px) {
  .app { display: block; }
  .sidebar {
    position: fixed; left: 0; top: 0; bottom: 0; min-height: 100vh;
    transform: translateX(-105%); box-shadow: 20px 0 40px rgba(0,0,0,.16);
  }
  .sidebar.open { transform: translateX(0); }
  .overlay {
    position: fixed; inset: 0; background: rgba(10,40,32,.42); z-index: 15;
  }
  .overlay.show { display: block; }
  .main { padding: 17px 15px 35px; }
  .menu-btn { display: grid; place-items: center; }
  .topbar { align-items: center; margin-bottom: 20px; }
  .profile-name { display: none; }
  .info-grid { grid-template-columns: 1fr; }
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
      <a class="logout" href="../auth/logout.php" onclick="return confirm('Are you sure you want to logout?');"><span class="nav-icon">↪</span>Logout</a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px">
        <button class="menu-btn" id="menuBtn" type="button" aria-label="Open navigation" aria-expanded="false">☰</button>
        <div class="welcome">
          <div class="eyebrow">Account Settings</div>
          <h1>My Profile</h1>
        </div>
      </div>
      <a class="profile-link" href="profile.php" title="View Profile">
        <div class="avatar-small">
          <?php echo htmlspecialchars(strtoupper(substr($profile['fullname'] ?? 'N', 0, 1))); ?>
        </div>
        <span class="profile-name"><?php echo htmlspecialchars($profile['fullname'] ?? 'Nutritionist'); ?></span>
      </a>
    </header>

    <!-- INTERACTIVE PROFILE GRID -->
    <div class="profile-grid">
      <!-- LEFT PROFILE SUMMARY CARD -->
      <div class="card profile-sidebar-card">
        <div class="avatar-large-wrap">
          <img src="../uploads/profile_pics/<?= htmlspecialchars($profile['profile_pic'] ?? 'default_profile.png'); ?>" alt="Profile Picture">
        </div>
        <h2><?= htmlspecialchars($profile['fullname']); ?></h2>
        
        <?php $status = strtolower($profile['status'] ?? 'pending'); ?>
        <div class="status-badge <?= $status === 'approved' ? 'approved' : 'pending'; ?>">
          <span><?= $status === 'approved' ? '●' : '○'; ?></span>
          <?= ucfirst($status); ?>
        </div>

        <a href="edit_profile.php" class="btn btn-primary">✏️ Edit Profile Details</a>
      </div>

      <!-- RIGHT DETAILS CARD -->
      <div class="card">
        <div class="details-header">
          <h3>Practitioner Information</h3>
        </div>

        <div class="info-grid">
          <div class="info-item">
            <label>Email Address</label>
            <div><?= htmlspecialchars($profile['email']); ?></div>
          </div>

          <div class="info-item">
            <label>Phone Number</label>
            <div><?= htmlspecialchars($profile['phone'] ?? 'N/A'); ?></div>
          </div>

          <div class="info-item">
            <label>Years of Experience</label>
            <div><?= htmlspecialchars($profile['experience'] ?? 'N/A'); ?></div>
          </div>

          <div class="info-item">
            <label>Certification / License</label>
            <div><?= htmlspecialchars($profile['certification'] ?? 'N/A'); ?></div>
          </div>

          <div class="info-item" style="grid-column: 1 / -1;">
            <label>Member Since</label>
            <div><?= date("F d, Y", strtotime($profile['created_at'])); ?></div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
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