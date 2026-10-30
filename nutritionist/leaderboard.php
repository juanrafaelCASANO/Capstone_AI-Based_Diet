<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['nutritionist_id']) && isset($_SESSION['user_id'])) {
    // Kung sakaling user ang napadpad dito, i-redirect sa tamang path
    header("Location: ../user/leaderboard.php");
    exit;
}

if (!isset($_SESSION['nutritionist_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$nutritionist_id = $_SESSION['nutritionist_id'];

// Kunin ang profile ng kasalukuyang nutritionist
$stmt = $conn->prepare("SELECT fullname, profile_pic FROM nutritionist WHERE id = ?");
$stmt->execute([$nutritionist_id]);
$nutri_user = $stmt->fetch();

if (!$nutri_user) {
    session_destroy();
    header("Location: ../auth/login.php?error=user_not_found");
    exit;
}

// Kunin ang leaderboard ng mga user batay sa calories at completed meals
try {
    $leaderboard_stmt = $conn->prepare("
        SELECT id, fullname, 
               COALESCE(total_calories, 0) as total_calories, 
               COALESCE(completed_meals, 0) as completed_meals 
        FROM users 
        WHERE role != 'admin' AND email NOT LIKE '%admin%' 
        ORDER BY total_calories DESC, completed_meals DESC, fullname ASC
    ");
    $leaderboard_stmt->execute();
    $leaders = $leaderboard_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    try {
        $leaderboard_stmt = $conn->prepare("
            SELECT id, fullname, 
                   COALESCE(total_calories, 0) as total_calories, 
                   COALESCE(completed_meals, 0) as completed_meals 
        FROM users 
        WHERE email NOT LIKE '%admin%' 
        ORDER BY total_calories DESC, completed_meals DESC, fullname ASC
    ");
        $leaderboard_stmt->execute();
        $leaders = $leaderboard_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $ex) {
        $leaders = [];
    }
}

// Kunin ang leaderboard ng mga nutritionist batay sa average rating at total reviews
$nutritionist_leaders = [];
try {
    $nutri_stmt = $conn->query("
        SELECT 
            n.id, 
            n.fullname, 
            n.profile_pic,
            COUNT(r.id) AS total_reviews,
            COALESCE(AVG(r.rating), 0) AS average_rating
        FROM nutritionist n
        LEFT JOIN nutritionist_reviews r ON n.id = r.nutritionist_id
        WHERE n.status = 'active' OR n.status = 'approved' OR n.status IS NULL
        GROUP BY n.id, n.fullname, n.profile_pic
        ORDER BY average_rating DESC, total_reviews DESC
    ");
    $nutritionist_leaders = $nutri_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $nutritionist_leaders = [];
}

function getInitials($name) {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI-Based Diet & Nutritional Planner - Leaderboard</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏆</text></svg>">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
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
body{
  margin:0;
  font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;
  background:var(--cream);
  color:var(--text);
}
a{color:inherit;text-decoration:none}
.app{min-height:100vh;display:flex;position:relative;}

.sidebar{
  width:260px;flex:0 0 260px;min-height:100vh;position:sticky;top:0;align-self:flex-start;
  background:linear-gradient(180deg,var(--navy),#123027);color:#fff;padding:24px 18px;
  display:flex;flex-direction:column;z-index:20;
}
.brand{display:flex;align-items:center;gap:12px;padding:6px 10px 28px;}
.brand-icon{width:42px;height:42px;border-radius:13px;display:grid;place-items:center;background:rgba(255,255,255,.12);font-size:23px;}
.brand strong{display:block;font-size:17px}
.brand span{display:block;color:#b9d5ca;font-size:12px;margin-top:2px}
.nav-label{padding:0 12px 8px;color:#9fc2b4;text-transform:uppercase;letter-spacing:.11em;font-size:10px;font-weight:800;}
.nav{display:grid;gap:6px}
.nav a{
  display:flex;align-items:center;gap:12px;padding:13px 12px;border-radius:12px;
  color:#d9ebe4;font-size:14px;font-weight:650;transition:background .2s,transform .2s;
}
.nav a:hover{background:rgba(255,255,255,.09);transform:translateX(2px)}
.nav a.active{background:rgba(255,255,255,.14);color:#fff}
.nav-icon{width:24px;text-align:center;font-size:17px}
.sidebar-bottom{margin-top:auto;border-top:1px solid rgba(255,255,255,.1);padding-top:16px}
.sidebar-bottom a.logout{
  display:flex;align-items:center;gap:12px;padding:13px 12px;
  border-radius:12px;color:#f8d7da!important;font-size:14px;font-weight:650;transition:background .2s,transform .2s;
}
.sidebar-bottom a.logout:hover{background:rgba(220,53,69,.2);transform:translateX(2px)}

.main{min-width:0;flex:1;padding:26px clamp(18px,4vw,48px) 48px}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:26px}
.menu-btn{display:none;border:1px solid var(--border);background:#fff;width:44px;height:44px;border-radius:12px;cursor:pointer;color:var(--text)}
.eyebrow{font-size:12px;font-weight:800;color:var(--green);text-transform:uppercase;letter-spacing:.1em}
.welcome h1{font-size:clamp(25px,3vw,36px);line-height:1.15;margin:5px 0 0;letter-spacing:-.8px}

.profile{
  display:flex;align-items:center;gap:10px;padding:7px 11px 7px 7px;
  background:#fff;border:1px solid var(--border);border-radius:999px;
}
.avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:var(--green-soft);color:var(--green);font-weight:800;overflow:hidden;}
.profile-name{font-size:13px;font-weight:700;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* LEADERBOARD SUB-TABS */
.leaderboard-tabs {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}
.lb-tab-btn {
  background: var(--card);
  border: 1px solid var(--border);
  font-size: 13.5px;
  font-weight: 700;
  color: var(--muted);
  cursor: pointer;
  padding: 10px 18px;
  border-radius: 12px;
  transition: all .2s;
  box-shadow: var(--shadow);
}
.lb-tab-btn.active {
  background: var(--navy);
  color: #fff;
  border-color: var(--navy);
}
.lb-tab-content { display: none; }
.lb-tab-content.active { display: block; }

.leaderboard-panel {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
}
.leaderboard-header {
  padding: 24px;
  background: linear-gradient(135deg, #eaf6ee 0%, #f8fbf7 70%);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.leaderboard-header h2 { margin: 0; font-size: 20px; }
.leaderboard-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}
.leaderboard-table th {
  background: #f4f8f5;
  padding: 14px 20px;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  border-bottom: 1px solid var(--border);
}
.leaderboard-table td {
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  font-size: 14px;
  font-weight: 600;
}
.leaderboard-table tr:last-child td { border-bottom: none; }
.leaderboard-table tr.current-nutritionist {
  background: var(--green-soft);
}

.rank-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  font-weight: 800;
  font-size: 14px;
}
.rank-1 { background: #fef3c7; color: #d97706; border: 2px solid #f59e0b; }
.rank-2 { background: #f1f5f9; color: #64748b; border: 2px solid #cbd5e1; }
.rank-3 { background: #ffedd5; color: #c2410c; border: 2px solid #fdba74; }
.rank-other { background: #f8fafc; color: var(--muted); }
.user-badge-icon { margin-left: 8px; font-size: 16px; }
.star-rating { color: #f59e0b; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }

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

@media(max-width:720px){
  .app{display:block}
  .sidebar{position:fixed;left:0;top:0;bottom:0;transform:translateX(-105%);transition:transform .25s ease;}
  .sidebar.open{transform:translateX(0)}
  .menu-btn{display:grid;place-items:center}
  .profile-name{display:none}
}
</style>
</head>
<body>
<div class="app">
  <div class="overlay" id="overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:15;"></div>

  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-icon">🥗</div>
      <div>
        <strong>AI Diet Planner</strong>
        <span>Nutritionist Portal</span>
      </div>
    </div>

    <div class="nav-label">Menu</div>
    <nav class="nav">
      <a class="active" href="dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
      <a href="leaderboard.php"><span class="nav-icon">🏆</span>Leaderboard</a>
      <a href="appointments.php">
        <span class="nav-icon">📅</span>Appointments
        <?php if($pending_appointments_count > 0): ?>
          <span class="nav-badge"><?php echo $pending_appointments_count; ?></span>
        <?php endif; ?>
      </a>
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

  <main class="main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px">
        <button class="menu-btn" id="menuBtn">☰</button>
        <div class="welcome">
          <div class="eyebrow">Expert Overview</div>
          <h1>Leaderboard 🏆</h1>
        </div>
      </div>
      <a class="profile" href="profile.php">
        <div class="avatar">
          <?php if (!empty($nutri_user['profile_pic']) && $nutri_user['profile_pic'] !== 'default_avatar.png' && file_exists("../uploads/profile_pics/" . $nutri_user['profile_pic'])): ?>
            <img src="<?php echo htmlspecialchars($nutri_user['profile_pic']); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
          <?php else: ?>
            <?php echo htmlspecialchars(strtoupper(substr($nutri_user['fullname'], 0, 1))); ?>
          <?php endif; ?>
        </div>
        <span class="profile-name"><?php echo htmlspecialchars($nutri_user['fullname']); ?></span>
      </a>
    </header>

    <!-- LEADERBOARD SUB-TABS -->
    <div class="leaderboard-tabs">
      <button type="button" class="lb-tab-btn active" onclick="switchLbTab('users', this)">
        <i class="fa-solid fa-users"></i> Users Ranking
      </button>
      <button type="button" class="lb-tab-btn" onclick="switchLbTab('nutritionists', this)">
        <i class="fa-solid fa-user-doctor"></i> Nutritionists Leaderboard
      </button>
    </div>

    <!-- TAB 1: USERS LEADERBOARD -->
    <div id="lb-tab-users" class="lb-tab-content active">
      <section class="leaderboard-panel">
        <div class="leaderboard-header">
          <div>
            <h2>Top Achievers (Users)</h2>
            <p style="margin:4px 0 0; color:var(--muted); font-size:13px;">List of all registered users based on completed meals and calories.</p>
          </div>
          <div style="font-size: 28px;">🌟</div>
        </div>

        <div style="overflow-x: auto;">
          <table class="leaderboard-table">
            <thead>
              <tr>
                <th style="width: 80px;">Rank</th>
                <th>User Name</th>
                <th>Completed Meals</th>
                <th>Total Calories</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($leaders) > 0): ?>
                <?php foreach ($leaders as $index => $row): 
                  $rank = $index + 1;
                  
                  if ($rank === 1 && $row['total_calories'] > 0) {
                      $badgeClass = "rank-1";
                      $badgeIcon = "👑";
                  } elseif ($rank === 2 && $row['total_calories'] > 0) {
                      $badgeClass = "rank-2";
                      $badgeIcon = "🥈";
                  } elseif ($rank === 3 && $row['total_calories'] > 0) {
                      $badgeClass = "rank-3";
                      $badgeIcon = "🥉";
                  } else {
                      $badgeClass = "rank-other";
                      $badgeIcon = ($rank <= 10 && $row['total_calories'] > 0) ? "⭐" : "";
                  }
                ?>
                  <tr>
                    <td>
                      <div class="rank-badge <?php echo $badgeClass; ?>">
                        <?php echo $rank; ?>
                      </div>
                    </td>
                    <td>
                      <?php echo htmlspecialchars($row['fullname']); ?>
                      <?php if (!empty($badgeIcon)): ?>
                        <span class="user-badge-icon"><?php echo $badgeIcon; ?></span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo (int)$row['completed_meals']; ?> meals</td>
                    <td><strong><?php echo number_format((int)$row['total_calories']); ?></strong> kcal</td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="4" style="text-align: center; color: var(--muted); padding: 40px;">No registered users found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- TAB 2: NUTRITIONISTS LEADERBOARD -->
    <div id="lb-tab-nutritionists" class="lb-tab-content">
      <section class="leaderboard-panel">
        <div class="leaderboard-header">
          <div>
            <h2>Top Nutritionists</h2>
            <p style="margin:4px 0 0; color:var(--muted); font-size:13px;">Rankings based on average star ratings and total reviews.</p>
          </div>
          <div style="font-size: 28px;">🏆</div>
        </div>

        <div style="overflow-x: auto;">
          <table class="leaderboard-table">
            <thead>
              <tr>
                <th style="width: 80px;">Rank</th>
                <th>Nutritionist Expert</th>
                <th>Average Rating</th>
                <th>Total Reviews</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($nutritionist_leaders) > 0): ?>
                <?php $n_rank = 1; foreach ($nutritionist_leaders as $nutri): 
                  $is_current_nutri = ($nutri['id'] == $nutritionist_id);

                  if ($n_rank === 1) {
                      $nBadgeClass = "rank-1";
                  } elseif ($n_rank === 2) {
                      $nBadgeClass = "rank-2";
                  } elseif ($n_rank === 3) {
                      $nBadgeClass = "rank-3";
                  } else {
                      $nBadgeClass = "rank-other";
                  }
                ?>
                  <tr class="<?php echo $is_current_nutri ? 'current-nutritionist' : ''; ?>">
                    <td>
                      <div class="rank-badge <?php echo $nBadgeClass; ?>">
                        <?php echo $n_rank; ?>
                      </div>
                    </td>
                    <td>
                      <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="avatar" style="width: 32px; height: 32px; font-size: 12px;">
                          <?php if (!empty($nutri['profile_pic']) && $nutri['profile_pic'] !== 'default_avatar.png' && file_exists("../uploads/profile_pics/" . $nutri['profile_pic'])): ?>
                            <img src="<?php echo htmlspecialchars($nutri['profile_pic']); ?>" alt="" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                          <?php else: ?>
                            <?php echo getInitials($nutri['fullname']); ?>
                          <?php endif; ?>
                        </div>
                        <div>
                          <strong><?php echo htmlspecialchars($nutri['fullname']); ?></strong>
                          <?php if ($is_current_nutri): ?>
                            <span style="font-size: 11px; background: var(--green); color: #fff; padding: 2px 6px; border-radius: 4px; margin-left: 6px;">You</span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </td>
                    <td>
                      <div class="star-rating">
                        <i class="fa-solid fa-star"></i>
                        <span><?php echo number_format($nutri['average_rating'], 2); ?> / 5.0</span>
                      </div>
                    </td>
                    <td><strong><?php echo (int)$nutri['total_reviews']; ?></strong> reviews</td>
                  </tr>
                <?php $n_rank++; endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="4" style="text-align: center; color: var(--muted); padding: 40px;">No nutritionist ratings recorded yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>

  </main>
</div>

<!-- LOGOUT MODAL STRUCTURE -->
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
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const menuBtn = document.getElementById('menuBtn');

menuBtn?.addEventListener('click', () => {
  sidebar.classList.add('open');
  overlay.style.display = 'block';
});

overlay?.addEventListener('click', () => {
  sidebar.classList.remove('open');
  overlay.style.display = 'none';
});

// LEADERBOARD SUB-TABS SWITCHING
function switchLbTab(tabName, btn) {
    document.querySelectorAll('.lb-tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.lb-tab-btn').forEach(b => b.classList.remove('active'));
    
    document.getElementById('lb-tab-' + tabName).classList.add('active');
    btn.classList.add('active');
}

// LOGOUT MODAL FUNCTIONS
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
</script>
</body>
</html>