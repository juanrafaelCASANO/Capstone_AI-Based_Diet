<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// PDO Query
$stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// If user does not exist in the database, clear session and redirect to login
if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php?error=user_not_found");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI-Based Diet & Nutritional Planner</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">

<style>
:root{
  --navy:#173b32;
  --navy-2:#245848;
  --green:#3b8f63;
  --green-soft:#e8f5ed;
  --cream:#f7faf6;
  --card:#ffffff;
  --text:#193028;
  --muted:#6c7b75;
  --border:#e3ebe6;
  --shadow:0 10px 30px rgba(27,61,48,.08);
  --radius:18px;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  margin:0;
  font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;
  background:var(--cream);
  color:var(--text);
}
button,a{font:inherit}
a{color:inherit}
.app{min-height:100vh;display:flex}
.sidebar{
  width:260px;
  flex:0 0 260px;
  min-height:100vh;
  position:sticky;
  top:0;
  align-self:flex-start;
  background:linear-gradient(180deg,var(--navy),#123027);
  color:#fff;
  padding:24px 18px;
  display:flex;
  flex-direction:column;
  transition:transform .25s ease;
  z-index:20;
}
.brand{
  display:flex;align-items:center;gap:12px;
  padding:6px 10px 28px;
}
.brand-icon{
  width:42px;height:42px;border-radius:13px;
  display:grid;place-items:center;background:rgba(255,255,255,.12);
  font-size:23px;
}
.brand strong{display:block;font-size:17px;letter-spacing:-.2px}
.brand span{display:block;color:#b9d5ca;font-size:12px;margin-top:2px}
.nav-label{
  padding:0 12px 8px;color:#9fc2b4;
  text-transform:uppercase;letter-spacing:.11em;font-size:10px;font-weight:800;
}
.nav{display:grid;gap:6px}
.nav a{
  display:flex;align-items:center;gap:12px;
  text-decoration:none;padding:13px 12px;border-radius:12px;
  color:#d9ebe4;font-size:14px;font-weight:650;
  transition:background .2s,transform .2s;
}
.nav a:hover{background:rgba(255,255,255,.09);transform:translateX(2px)}
.nav a.active{background:rgba(255,255,255,.14);color:#fff}
.nav-icon{width:24px;text-align:center;font-size:17px}

/* SIDEBAR BOTTOM & LOGOUT STYLES */
.sidebar-bottom{margin-top:auto;border-top:1px solid rgba(255,255,255,.1);padding-top:16px}
.sidebar-bottom a.logout{
  display:flex;align-items:center;gap:12px;text-decoration:none;padding:13px 12px;
  border-radius:12px;color:#f8d7da!important;font-size:14px;font-weight:650;
  transition:background .2s,transform .2s;
}
.sidebar-bottom a.logout:hover{background:rgba(220,53,69,.2);transform:translateX(2px)}

.main{min-width:0;flex:1;padding:26px clamp(18px,4vw,48px) 48px}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:26px}
.menu-btn{
  display:none;border:1px solid var(--border);background:#fff;
  width:44px;height:44px;border-radius:12px;cursor:pointer;color:var(--text);
}
.eyebrow{font-size:12px;font-weight:800;color:var(--green);text-transform:uppercase;letter-spacing:.1em}
.welcome h1{font-size:clamp(25px,3vw,36px);line-height:1.15;margin:5px 0 0;letter-spacing:-.8px}

/* CLICKABLE PROFILE CHIP IN TOP BAR */
.profile{
  display:flex;align-items:center;gap:10px;padding:7px 11px 7px 7px;
  background:#fff;border:1px solid var(--border);border-radius:999px;
  text-decoration:none;transition:border-color .2s,box-shadow .2s;
}
.profile:hover{border-color:#b9d5ca;box-shadow:0 4px 12px rgba(27,61,48,.06)}
.avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:var(--green-soft);color:var(--green);font-weight:800}
.profile-name{font-size:13px;font-weight:700;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

.hero{
  background:linear-gradient(135deg,#eaf6ee 0%,#f8fbf7 70%);
  border:1px solid #dcebe1;border-radius:24px;padding:clamp(24px,4vw,38px);
  display:grid;grid-template-columns:minmax(0,1.3fr) minmax(220px,.7fr);
  gap:30px;align-items:center;overflow:hidden;position:relative;
}
.hero:after{
  content:"🥗";position:absolute;right:5%;bottom:-20px;font-size:150px;opacity:.08;pointer-events:none;
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
.btn-primary{background:var(--green);color:#fff;box-shadow:0 8px 18px rgba(59,143,99,.2)}
.btn-secondary{background:#fff;color:var(--navy);border-color:var(--border)}
.hero-stat{position:relative;z-index:1;background:#fff;border:1px solid var(--border);border-radius:18px;padding:20px;box-shadow:var(--shadow)}
.hero-stat .emoji{font-size:30px}
.hero-stat strong{display:block;font-size:17px;margin-top:10px}
.hero-stat span{display:block;color:var(--muted);font-size:13px;line-height:1.5;margin-top:5px}

.section-title{display:flex;align-items:end;justify-content:space-between;gap:15px;margin:30px 0 14px}
.section-title h3{margin:0;font-size:18px}
.section-title p{margin:0;color:var(--muted);font-size:13px}

.cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
.card{
  background:var(--card);border:1px solid var(--border);border-radius:var(--radius);
  padding:22px;box-shadow:0 5px 18px rgba(27,61,48,.04);
  transition:transform .2s,box-shadow .2s,border-color .2s;
}
.card:hover{transform:translateY(-3px);box-shadow:var(--shadow);border-color:#d5e5dc}
.card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}
.card-icon{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;background:var(--green-soft);font-size:22px}
.card h3{margin:15px 0 7px;font-size:19px}
.card p{margin:0;color:var(--muted);line-height:1.6;font-size:14px}
.card .btn{margin-top:19px}

.quick-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.quick{
  background:#fff;border:1px solid var(--border);border-radius:15px;padding:17px;
  text-decoration:none;display:block;transition:transform .2s,border-color .2s;
}
.quick:hover{transform:translateY(-2px);border-color:#cbded4}
.quick b{font-size:14px}
.quick span{display:block;color:var(--muted);font-size:12px;margin-top:5px;line-height:1.5}

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
    position:fixed;inset:0;background:rgba(12,28,22,.42);z-index:15;
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

  <aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="brand">
      <div class="brand-icon">🥗</div>
      <div>
        <strong>AI Diet Planner</strong>
        <span>Personal nutrition assistant</span>
      </div>
    </div>

    <div class="nav-label">Menu</div>
    <nav class="nav">
      <a class="active" href="dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
      <a href="generate_weekly.php"><span class="nav-icon">▦</span>Weekly Meal Plan</a>
      <a href="chat.php"><span class="nav-icon">◌</span>Nutritionist</a>
      <a href="profile.php"><span class="nav-icon">👤</span>My Profile</a>
    </nav>

    <div class="sidebar-bottom">
      <!-- FIXED LOGOUT ROUTE & CONFIRMATION -->
      <a class="logout" href="../auth/logout.php" onclick="return confirm('Are you sure you want to logout?');"><span class="nav-icon">↪</span>Logout</a>
    </div>
  </aside>

  <main class="main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px">
        <button class="menu-btn" id="menuBtn" type="button" aria-label="Open navigation" aria-expanded="false">☰</button>
        <div class="welcome">
          <div class="eyebrow">Your nutrition dashboard</div>
          <h1>Welcome, <?php echo htmlspecialchars($user['fullname']); ?> 👋</h1>
        </div>
      </div>
      <a class="profile" href="profile.php" title="View & Edit Profile">
        <div class="avatar">
          <?php echo htmlspecialchars(strtoupper(substr($user['fullname'], 0, 1))); ?>
        </div>
        <span class="profile-name"><?php echo htmlspecialchars($user['fullname']); ?></span>
      </a>
    </header>

    <section class="hero">
      <div>
        <div class="eyebrow">Smart meal planning</div>
        <h2>Eat better with a plan made around your goals.</h2>
        <p>Generate a weekly Filipino-based diet plan and use your nutritionist chat to make practical adjustments along the way.</p>
        <div class="actions">
          <a class="btn btn-primary" href="generate_weekly.php">🍽️ Generate Weekly Plan</a>
          <a class="btn btn-secondary" href="chat.php">💬 Talk to Nutritionist</a>
        </div>
      </div>
      <div class="hero-stat">
        <div class="emoji">🌱</div>
        <strong>Personalized nutrition</strong>
        <span>Build healthier routines with meal ideas designed to fit your planning needs.</span>
      </div>
    </section>

    <div class="section-title">
      <div>
        <h3>Get started</h3>
        <p>Choose what you want to do next.</p>
      </div>
    </div>

    <section class="cards">
      <article class="card">
        <div class="card-top">
          <div class="card-icon">🍱</div>
        </div>
        <h3>Your AI Meal Plan</h3>
        <p>Generate a weekly Filipino-based diet plan tailored to your goals and make meal planning easier.</p>
        <a class="btn btn-primary" href="generate_weekly.php">Generate Plan <span>→</span></a>
      </article>

      <article class="card">
        <div class="card-top">
          <div class="card-icon">💬</div>
        </div>
        <h3>Consult a Nutritionist</h3>
        <p>Open the nutritionist chat to discuss your plan and get guidance for improving your food choices.</p>
        <a class="btn btn-secondary" href="chat.php">Open Chat <span>→</span></a>
      </article>
    </section>

    <div class="section-title">
      <div>
        <h3>Quick navigation</h3>
        <p>Everything you need is one tap away.</p>
      </div>
    </div>

    <section class="quick-grid">
      <a class="quick" href="generate_weekly.php">
        <b>📅 Weekly Meal Plan</b>
        <span>Create your next 7-day meal plan.</span>
      </a>
      <a class="quick" href="chat.php">
        <b>🧑‍⚕️ Nutritionist Chat</b>
        <span>Get help refining your nutrition plan.</span>
      </a>
      <a class="quick" href="profile.php">
        <b>👤 Edit Profile</b>
        <span>Update preferences & body metrics.</span>
      </a>
    </section>
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