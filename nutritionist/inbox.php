<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$nutri_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT fullname FROM nutritionist WHERE id = ?");
$stmt->execute([$nutri_id]);
$nutri = $stmt->fetch();

if (!$nutri) {
    session_destroy();
    header("Location: ../auth/login.php?error=user_not_found");
    exit;
}

$inbox_sql = "
    WITH conversations AS (
        SELECT 
            CASE 
                WHEN sender_id = :nutri_id THEN receiver_id 
                ELSE sender_id 
            END AS client_id,
            message,
            created_at,
            sender_id,
            ROW_NUMBER() OVER(
                PARTITION BY CASE WHEN sender_id = :nutri_id THEN receiver_id ELSE sender_id END 
                ORDER BY created_at DESC
            ) as rn
        FROM messages
        WHERE sender_id = :nutri_id OR receiver_id = :nutri_id
    ),
    unread_counts AS (
        SELECT sender_id AS client_id, COUNT(*) as unread_count
        FROM messages
        WHERE receiver_id = :nutri_id 
        GROUP BY sender_id
    )
    SELECT 
        u.id AS client_id,
        u.fullname AS client_name,
        c.message AS last_message,
        c.created_at AS last_message_time,
        c.sender_id AS last_sender_id,
        COALESCE(uc.unread_count, 0) AS unread_count
    FROM conversations c
    JOIN users u ON u.id = c.client_id
    LEFT JOIN unread_counts uc ON uc.client_id = c.client_id
    WHERE c.rn = 1
    ORDER BY c.created_at DESC
";

$inbox_stmt = $conn->prepare($inbox_sql);
$inbox_stmt->execute(['nutri_id' => $nutri_id]);
$conversations = $inbox_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inbox | Nutritionist Portal</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🩺</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
  --navy: #0f3a2e;
  --teal: #0d9488;
  --teal-soft: #ccfbf1;
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
body { margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; background: var(--cream); color: var(--text); }
a { color: inherit; text-decoration: none; }
.app { min-height: 100vh; display: flex; }

/* SIDEBAR - Fixed */
.sidebar {
  width: 260px; flex: 0 0 260px; height: 100vh; position: sticky; top: 0;
  background: linear-gradient(180deg, var(--navy), #0a2820); color: #fff; padding: 24px 18px;
  display: flex; flex-direction: column; overflow: hidden; z-index: 20;
}
.brand { display: flex; align-items: center; gap: 12px; padding: 6px 10px 28px; flex-shrink: 0; }
.brand-icon { width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; background: rgba(255,255,255,.12); font-size: 23px; }
.brand strong { display: block; font-size: 17px; font-family: 'Plus Jakarta Sans', sans-serif; } 
.brand span { display: block; color: #99f6e4; font-size: 12px; margin-top: 2px; font-family: 'Plus Jakarta Sans', sans-serif; }

.nav-label { padding: 0 12px 8px; color: #80e0d0; text-transform: uppercase; font-size: 10px; font-weight: 800; letter-spacing: .11em; flex-shrink: 0; }
.nav { display: grid; gap: 6px; flex-shrink: 0; }
.nav a { display: flex; align-items: center; gap: 12px; padding: 13px 12px; border-radius: 12px; color: #e6fffa; font-size: 14px; font-weight: 650; font-family: 'Plus Jakarta Sans', sans-serif; }
.nav a.active { background: rgba(255,255,255,.14); color: #fff; }
.nav-icon { width: 24px; text-align: center; font-size: 17px; }

.sidebar-bottom { margin-top: auto; border-top: 1px solid rgba(255,255,255,.1); padding-top: 16px; flex-shrink: 0; }
.sidebar-bottom a.logout {
  display: flex; align-items: center; gap: 12px; text-decoration: none; padding: 13px 12px;
  border-radius: 12px; color: #f8d7da !important; font-size: 14px; font-weight: 650;
  font-family: 'Plus Jakarta Sans', sans-serif; transition: background .2s, transform .2s;
}
.sidebar-bottom a.logout:hover { background: rgba(220,53,69,.2); transform: translateX(2px); }

.main { flex: 1; padding: 26px 48px; }
.topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.welcome h1 { font-size: 28px; margin: 4px 0 0; }

.inbox-list { display: flex; flex-direction: column; gap: 12px; max-width: 900px; }
.inbox-card {
  background: var(--card); border: 1px solid var(--border); border-radius: 16px;
  padding: 16px 20px; display: flex; align-items: center; justify-content: space-between;
  transition: transform .2s, box-shadow .2s; box-shadow: var(--shadow);
}
.inbox-card:hover { transform: translateY(-2px); border-color: var(--teal); }
.client-info { display: flex; align-items: center; gap: 16px; }
.avatar {
  width: 48px; height: 48px; border-radius: 50%; background: var(--teal-soft);
  color: var(--teal); font-weight: 700; display: grid; place-items: center; font-size: 18px;
}
.details strong { font-size: 16px; display: block; color: var(--text); }
.details p { font-size: 13px; color: var(--muted); margin: 4px 0 0; max-width: 500px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.badge {
  background: #e11d48; color: #fff; font-size: 12px; font-weight: 700;
  padding: 4px 10px; border-radius: 99px; min-width: 24px; text-align: center;
}
.time-stamp { font-size: 11px; color: var(--muted); margin-bottom: 4px; display: block; text-align: right; }
.empty-box { text-align: center; padding: 40px; color: var(--muted); font-size: 14px; background: #fff; border-radius: 16px; border: 1px solid var(--border); }

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
</style>
</head>
<body>

<div class="app">
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-icon">🩺</div>
      <div><strong>NutriPanel</strong><span>Nutritionist portal</span></div>
    </div>
    <div class="nav-label">Menu</div>
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
      <div class="welcome">
        <h1>Client Messages</h1>
      </div>
    </header>

    <div class="inbox-list">
      <?php if (count($conversations) > 0): ?>
        <?php foreach ($conversations as $chat): ?>
          <!-- Tandaan: Pinalitan ko ito ng chat_reply.php. Kung ibang pangalan ang file ng reply mo (hal. view_chat.php), palitan mo lang dito -->
          <a href="chat_reply.php?user_id=<?php echo $chat['client_id']; ?>" class="inbox-card">
            <div class="client-info">
              <div class="avatar">
                <?php echo htmlspecialchars(strtoupper(substr($chat['client_name'], 0, 1))); ?>
              </div>
              <div class="details">
                <strong><?php echo htmlspecialchars($chat['client_name']); ?></strong>
                <p>
                  <?php if ($chat['last_sender_id'] == $nutri_id): ?>
                    <span style="color:var(--teal);">You: </span>
                  <?php endif; ?>
                  <?php echo htmlspecialchars($chat['last_message']); ?>
                </p>
              </div>
            </div>

            <div style="text-align:right;">
              <span class="time-stamp">
                <?php echo date('M d, g:i a', strtotime($chat['last_message_time'])); ?>
              </span>
              <?php if ($chat['unread_count'] > 0): ?>
                <span class="badge"><?php echo $chat['unread_count']; ?> new</span>
              <?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-box">
          <i class="fa-solid fa-inbox" style="font-size:32px; margin-bottom:12px; display:block;"></i>
          No messages found. When clients send you a message, they will appear here.
        </div>
      <?php endif; ?>
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
</script>

</body>
</html>