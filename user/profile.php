<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$message = "";
$error = "";

/* =========================================================
   FETCH USER
========================================================= */
$stmt = $conn->prepare("
    SELECT
        id,
        fullname,
        email,
        phone,
        role,
        created_at,
        age,
        gender,
        height,
        weight,
        goal,
        activity,
        diet_type,
        allergies
    FROM users
    WHERE id = ?
");

$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php?error=user_not_found");
    exit;
}

/* =========================================================
   UPDATE PROFILE
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {

    $fullname  = trim($_POST['fullname'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $age       = !empty($_POST['age']) ? intval($_POST['age']) : null;
    $gender    = !empty($_POST['gender']) ? $_POST['gender'] : null;
    $height    = !empty($_POST['height']) ? floatval($_POST['height']) : null;
    $weight    = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
    $goal      = !empty($_POST['goal']) ? $_POST['goal'] : null;
    $activity  = !empty($_POST['activity']) ? $_POST['activity'] : null;
    $diet_type = trim($_POST['diet_type'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');

    /* =====================================================
       VALIDATION
    ===================================================== */
    if ($fullname === '') {
        $error = "Full name is required.";
    } elseif ($email === '') {
        $error = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($phone === '') {
        $error = "Phone number is required.";
    } elseif ($age !== null && ($age < 1 || $age > 120)) {
        $error = "Please enter a valid age.";
    } elseif ($height !== null && ($height <= 0 || $height > 300)) {
        $error = "Please enter a valid height.";
    } elseif ($weight !== null && ($weight <= 0 || $weight > 500)) {
        $error = "Please enter a valid weight.";
    }

    /* =====================================================
       CHECK DUPLICATE EMAIL
    ===================================================== */
    if ($error === '') {
        $check = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            AND id != ?
        ");
        $check->execute([$email, $user_id]);

        if ($check->rowCount() > 0) {
            $error = "That email is already registered to another account.";
        }
    }

    /* =====================================================
       UPDATE DATABASE
    ===================================================== */
    if ($error === '') {
        $update = $conn->prepare("
            UPDATE users SET
                fullname = ?,
                email = ?,
                phone = ?,
                age = ?,
                gender = ?,
                height = ?,
                weight = ?,
                goal = ?,
                activity = ?,
                diet_type = ?,
                allergies = ?
            WHERE id = ?
        ");

        $updateSuccess = $update->execute([
            $fullname,
            $email,
            $phone,
            $age,
            $gender,
            $height,
            $weight,
            $goal,
            $activity,
            $diet_type,
            $allergies,
            $user_id
        ]);

        if ($updateSuccess) {
            $message = "Your profile has been updated successfully!";

            /* Refetch updated user info */
            $stmt = $conn->prepare("
                SELECT
                    id,
                    fullname,
                    email,
                    phone,
                    role,
                    created_at,
                    age,
                    gender,
                    height,
                    weight,
                    goal,
                    activity,
                    diet_type,
                    allergies
                FROM users
                WHERE id = ?
            ");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        } else {
            $error = "Something went wrong while updating your profile.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile | AI-Based Diet & Nutritional Planner</title>
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
  --danger:#dc2626;
  --danger-bg:#fef2f2;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  margin:0;
  font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;
  background:var(--cream);
  color:var(--text);
}
button,input,select,textarea,a{font:inherit}
a{color:inherit}

.app{min-height:100vh;display:flex}

/* SIDEBAR */
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

/* MAIN CONTENT */
.main{min-width:0;flex:1;padding:26px clamp(18px,4vw,48px) 48px}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:26px}
.menu-btn{
  display:none;border:1px solid var(--border);background:#fff;
  width:44px;height:44px;border-radius:12px;cursor:pointer;color:var(--text);
}
.eyebrow{font-size:12px;font-weight:800;color:var(--green);text-transform:uppercase;letter-spacing:.1em}
.welcome h1{font-size:clamp(25px,3vw,36px);line-height:1.15;margin:5px 0 0;letter-spacing:-.8px}
.profile{
  display:flex;align-items:center;gap:10px;padding:7px 11px 7px 7px;
  background:#fff;border:1px solid var(--border);border-radius:999px;
}
.avatar-sm{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:var(--green-soft);color:var(--green);font-weight:800}
.profile-name{font-size:13px;font-weight:700;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* ALERTS */
.alert{
  padding:16px 20px;border-radius:14px;margin-bottom:24px;font-size:14px;
  font-weight:600;display:flex;align-items:center;gap:10px;
}
.alert-success{background:var(--green-soft);color:var(--navy);border:1px solid #cce5d6}
.alert-error{background:var(--danger-bg);color:var(--danger);border:1px solid #f87171}

/* PROFILE GRID */
.profile-layout{
  display:grid;grid-template-columns:300px minmax(0,1fr);gap:24px;align-items:start;
}

/* CARDS */
.card{
  background:var(--card);border:1px solid var(--border);border-radius:var(--radius);
  padding:26px;box-shadow:0 5px 18px rgba(27,61,48,.04);
}
.profile-card{text-align:center}
.avatar-lg{
  width:90px;height:90px;margin:0 auto 16px;border-radius:50%;
  background:var(--green-soft);color:var(--green);
  display:grid;place-items:center;font-size:36px;font-weight:800;
  border:3px solid #fff;box-shadow:0 0 0 3px var(--green-soft);
}
.profile-card h2{margin:0 0 6px;font-size:20px;letter-spacing:-.4px}
.profile-card p{margin:0 0 16px;color:var(--muted);font-size:13px;word-break:break-all}
.badge{
  display:inline-flex;align-items:center;gap:6px;padding:6px 14px;
  border-radius:999px;background:var(--green-soft);color:var(--green);
  font-size:12px;font-weight:750;
}

.account-summary{margin-top:20px;padding-top:20px;border-top:1px solid var(--border);text-align:left;display:grid;gap:12px}
.summary-item span{display:block;font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:700;letter-spacing:.05em}
.summary-item strong{font-size:13px;color:var(--text)}

/* FORM SPECIFIC */
.form-card h2{margin:0 0 6px;font-size:22px;letter-spacing:-.4px}
.form-description{margin:0 0 24px;color:var(--muted);font-size:14px;line-height:1.5}
.form-section{margin-bottom:28px}
.section-title{
  display:flex;align-items:center;gap:8px;font-size:15px;font-weight:750;
  color:var(--navy);margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid var(--border);
}

.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group.full{grid-column:1 / -1}
.form-group label{font-size:13px;font-weight:700;color:var(--text)}
.form-group input, .form-group select, .form-group textarea{
  width:100%;padding:11px 14px;border:1px solid var(--border);border-radius:12px;
  background:#fff;color:var(--text);outline:none;transition:border-color .2s,box-shadow .2s;
}
.form-group textarea{resize:vertical;min-height:90px}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus{
  border-color:var(--green);box-shadow:0 0 0 3px rgba(59,143,99,.15);
}

.form-actions{display:flex;justify-content:flex-end;padding-top:12px}
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  min-height:46px;padding:0 22px;border-radius:12px;border:1px solid transparent;
  text-decoration:none;font-weight:750;font-size:14px;cursor:pointer;
  transition:transform .2s,box-shadow .2s,background .2s;
}
.btn:hover{transform:translateY(-1px)}
.btn-primary{background:var(--green);color:#fff;box-shadow:0 8px 18px rgba(59,143,99,.2)}

/* RESPONSIVE LAYOUT */
.overlay{display:none}
@media(max-width:900px){
  .sidebar{width:240px;flex-basis:240px}
  .profile-layout{grid-template-columns:1fr}
}
@media(max-width:720px){
  .app{display:block}
  .sidebar{
    position:fixed;left:0;top:0;bottom:0;min-height:100vh;
    transform:translateX(-105%);box-shadow:20px 0 40px rgba(0,0,0,.16);
  }
  .sidebar.open{transform:translateX(0)}
  .overlay{position:fixed;inset:0;background:rgba(12,28,22,.42);z-index:15}
  .overlay.show{display:block}
  .main{padding:17px 15px 35px}
  .menu-btn{display:grid;place-items:center}
  .topbar{align-items:center;margin-bottom:20px}
  .profile-name{display:none}
  .form-grid{grid-template-columns:1fr}
}
</style>
</head>

<body>
<div class="app">
  <div class="overlay" id="overlay" aria-hidden="true"></div>

  <!-- SIDEBAR NAVIGATION -->
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
      <a href="dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
      <a href="generate_weekly.php"><span class="nav-icon">▦</span>Weekly Meal Plan</a>
      <a href="chat.php"><span class="nav-icon">◌</span>Nutritionist</a>
      <a class="active" href="profile.php"><span class="nav-icon">👤</span>My Profile</a>
    </nav>

    <div class="sidebar-bottom">
      <!-- FIXED LOGOUT ROUTE -->
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
          <h1>My Profile 👤</h1>
        </div>
      </div>
      <div class="profile" title="Signed-in user">
        <div class="avatar-sm">
          <?php echo htmlspecialchars(strtoupper(substr($user['fullname'] ?? 'U', 0, 1))); ?>
        </div>
        <span class="profile-name"><?php echo htmlspecialchars($user['fullname']); ?></span>
      </div>
    </header>

    <!-- ALERTS -->
    <?php if ($message): ?>
      <div class="alert alert-success">
        <span>✅</span>
        <span><?php echo htmlspecialchars($message); ?></span>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-error">
        <span>❌</span>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
    <?php endif; ?>

    <div class="profile-layout">
      <!-- LEFT PROFILE CARD -->
      <aside class="card profile-card">
        <div class="avatar-lg">
          <?php echo htmlspecialchars(strtoupper(substr($user['fullname'] ?? 'U', 0, 1))); ?>
        </div>
        <h2><?php echo htmlspecialchars($user['fullname'] ?? 'User'); ?></h2>
        <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
        <span class="badge">● Active Account</span>

        <div class="account-summary">
          <div class="summary-item">
            <span>User ID</span>
            <strong>#<?php echo htmlspecialchars($user['id']); ?></strong>
          </div>
          <div class="summary-item">
            <span>Account Role</span>
            <strong><?php echo htmlspecialchars(ucfirst($user['role'] ?? 'User')); ?></strong>
          </div>
          <div class="summary-item">
            <span>Member Since</span>
            <strong><?php echo htmlspecialchars($user['created_at'] ?? 'N/A'); ?></strong>
          </div>
        </div>
      </aside>

      <!-- RIGHT EDIT FORM -->
      <section class="card form-card">
        <h2>Edit Account & Nutrition Info</h2>
        <p class="form-description">Keep your profile details updated to generate accurate meal suggestions tailored to your health goals.</p>

        <form method="POST" action="profile.php">
          <!-- PERSONAL INFORMATION -->
          <div class="form-section">
            <div class="section-title">👤 Personal Information</div>
            <div class="form-grid">
              <div class="form-group full">
                <label for="fullname">Full Name</label>
                <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>" required>
              </div>
              <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
              </div>
              <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" maxlength="15" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
              </div>
              <div class="form-group">
                <label for="age">Age</label>
                <input type="number" id="age" name="age" min="1" max="120" value="<?php echo htmlspecialchars($user['age'] ?? ''); ?>">
              </div>
              <div class="form-group">
                <label for="gender">Gender</label>
                <select id="gender" name="gender">
                  <option value="">Select Gender</option>
                  <option value="male" <?php if (($user['gender'] ?? '') === 'male') echo 'selected'; ?>>Male</option>
                  <option value="female" <?php if (($user['gender'] ?? '') === 'female') echo 'selected'; ?>>Female</option>
                </select>
              </div>
            </div>
          </div>

          <!-- BODY MEASUREMENTS -->
          <div class="form-section">
            <div class="section-title">⚖️ Body Information</div>
            <div class="form-grid">
              <div class="form-group">
                <label for="height">Height (cm)</label>
                <input type="number" id="height" name="height" step="0.01" min="1" max="300" placeholder="e.g. 170" value="<?php echo htmlspecialchars($user['height'] ?? ''); ?>">
              </div>
              <div class="form-group">
                <label for="weight">Weight (kg)</label>
                <input type="number" id="weight" name="weight" step="0.01" min="1" max="500" placeholder="e.g. 70" value="<?php echo htmlspecialchars($user['weight'] ?? ''); ?>">
              </div>
            </div>
          </div>

          <!-- NUTRITION & PREFERENCES -->
          <div class="form-section">
            <div class="section-title">🥗 Nutrition Preferences</div>
            <div class="form-grid">
              <div class="form-group">
                <label for="goal">Nutrition Goal</label>
                <select id="goal" name="goal">
                  <option value="">Select Goal</option>
                  <option value="lose" <?php if (($user['goal'] ?? '') === 'lose') echo 'selected'; ?>>Lose Weight</option>
                  <option value="maintain" <?php if (($user['goal'] ?? '') === 'maintain') echo 'selected'; ?>>Maintain Weight</option>
                  <option value="gain" <?php if (($user['goal'] ?? '') === 'gain') echo 'selected'; ?>>Gain Weight</option>
                </select>
              </div>
              <div class="form-group">
                <label for="activity">Activity Level</label>
                <select id="activity" name="activity">
                  <option value="">Select Activity</option>
                  <option value="low" <?php if (($user['activity'] ?? '') === 'low') echo 'selected'; ?>>Low</option>
                  <option value="moderate" <?php if (($user['activity'] ?? '') === 'moderate') echo 'selected'; ?>>Moderate</option>
                  <option value="high" <?php if (($user['activity'] ?? '') === 'high') echo 'selected'; ?>>High</option>
                </select>
              </div>
              <div class="form-group full">
                <label for="diet_type">Diet Type</label>
                <input type="text" id="diet_type" name="diet_type" maxlength="50" placeholder="e.g. Filipino, Vegetarian, Low Carb" value="<?php echo htmlspecialchars($user['diet_type'] ?? ''); ?>">
              </div>
              <div class="form-group full">
                <label for="allergies">Food Allergies / Intolerances</label>
                <textarea id="allergies" name="allergies" placeholder="e.g. Peanuts, Shrimp, Dairy. Type 'None' if applicable."><?php echo htmlspecialchars($user['allergies'] ?? ''); ?></textarea>
              </div>
            </div>
          </div>

          <!-- SUBMIT ACTION -->
          <div class="form-actions">
            <button type="submit" name="update_profile" class="btn btn-primary">💾 Save Changes</button>
          </div>
        </form>
      </section>
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