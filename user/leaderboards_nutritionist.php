<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get leaderboard rankings of nutritionists based on average rating and total reviews
$leaderboard = [];
try {
    $sql = "
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
    ";
    $stmt = $conn->query($sql);
    $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $leaderboard = [];
}

// Get unread count for sidebar badge
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
<title>Nutritionist Leaderboard - AI Diet Planner</title>
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
.nav-badge{margin-left:auto;background:var(--danger);color:#fff;font-size:11px;font-weight:800;padding:2px 8px;border-radius:999px;}

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

.leaderboard-card{
  background:var(--card);border:1px solid var(--border);border-radius:20px;
  box-shadow:var(--shadow);overflow:hidden;
}
.leaderboard-table{width:100%;border-collapse:collapse;text-align:left;}
.leaderboard-table th{
  padding:16px 20px;background:#f8faf8;color:var(--muted);font-size:11px;
  text-transform:uppercase;letter-spacing:.05em;font-weight:700;border-bottom:1px solid var(--border);
}
.leaderboard-table td{
  padding:16px 20px;border-bottom:1px solid var(--border);font-size:14px;color:var(--text);vertical-align:middle;
}
.leaderboard-table tr:last-child td{border-bottom:none;}
.rank-badge{
  width:30px;height:30px;border-radius:50%;display:grid;place-items:center;
  font-weight:800;font-size:13px;background:var(--cream);color:var(--muted);border:1px solid var(--border);
}
.rank-1{background:#fef3c7;color:#b45309;border-color:#fde68a;}
.rank-2{background:#f1f5f9;color:#475569;border-color:#cbd5e1;}
.rank-3{background:#ffedd5;color:#c2410c;border-color:#fed7aa;}

.nutri-profile-cell{display:flex;align-items:center;gap:12px;}
.avatar{
  width:40px;height:40px;border-radius:50%;background:var(--green-soft);color:var(--green);
  font-weight:700;display:grid;place-items:center;font-size:14px;overflow:hidden;flex-shrink:0;
}
.avatar img{width:100%;height:100%;object-fit:cover;}
.star-rating{color:#f59e0b;font-weight:700;display:inline-flex;align-items:center;gap:4px;}
.empty-box{text-align:center;padding:50px 20px;color:var(--muted);font-size:14px;}
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
      <a href="leaderboard_nutritionist.php" class="active"><span class="nav-icon">🏆</span>Leaderboard</a>
      <a href="user_inbox.php">
        <span class="nav-icon">📥</span>Inbox & Bookings
        <?php if($unread_count > 0): ?>
          <span class="nav-badge"><?php echo $unread_count; ?></span>
        <?php endif; ?>
      </a>
      <a href="profile.php"><span class="nav-icon">👤</span>My Profile</a>
    </nav>
    <div class="sidebar-bottom">
      <a class="logout" href="../auth/logout.php"><span class="nav-icon">↪</span>Logout</a>
    </div>
  </aside>

  <main class="main">
    <header class="topbar">
      <div>
        <div class="eyebrow">Nutritionist Rankings</div>
        <h1>Leaderboard & Ratings</h1>
      </div>
    </header>

    <div class="leaderboard-card">
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
          <?php if (count($leaderboard) > 0): ?>
            <?php $rank = 1; foreach ($leaderboard as $row): ?>
              <tr>
                <td>
                  <div class="rank-badge <?php echo ($rank <= 3) ? 'rank-' . $rank : ''; ?>">
                    <?php if ($rank === 1): ?>🥇<?php elseif ($rank === 2): ?>🥈<?php elseif ($rank === 3): ?>🥉<?php else: ?><?php echo $rank; ?><?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="nutri-profile-cell">
                    <div class="avatar">
                      <?php if (!empty($row['profile_pic']) && $row['profile_pic'] !== 'default_avatar.png' && file_exists("../uploads/profile_pics/" . $row['profile_pic'])): ?>
                        <img src="<?php echo htmlspecialchars($row['profile_pic']); ?>" alt="">
                      <?php else: ?>
                        <?php echo getInitials($row['fullname']); ?>
                      <?php endif; ?>
                    </div>
                    <strong><?php echo htmlspecialchars($row['fullname']); ?></strong>
                  </div>
                </td>
                <td>
                  <div class="star-rating">
                    <i class="fa-solid fa-star"></i>
                    <span><?php echo number_format($row['average_rating'], 2); ?> / 5.0</span>
                  </div>
                </td>
                <td>
                  <strong><?php echo $row['total_reviews']; ?></strong> reviews
                </td>
              </tr>
            <?php $rank++; endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="4">
                <div class="empty-box">
                  <i class="fa-solid fa-trophy" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                  No nutritionist ratings recorded yet.
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

</body>
</html>