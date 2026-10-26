<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$nutritionist_id = $_SESSION['user_id'];

$action_success = "";
$action_error = "";

// Handle status updates securely using direct button actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = $_POST['appointment_id'] ?? 0;
    $new_status = '';

    if (isset($_POST['action_confirm'])) {
        $new_status = 'confirmed';
    } elseif (isset($_POST['action_cancel'])) {
        $new_status = 'cancelled';
    } elseif (isset($_POST['action_complete'])) {
        $new_status = 'completed';
    }

    if (!empty($new_status)) {
        try {
            $update_stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ? AND nutritionist_id = ?");
            $update_stmt->execute([$new_status, $appointment_id, $nutritionist_id]);
            $action_success = "Appointment status successfully updated to " . ucfirst($new_status) . "!";
        } catch (Exception $e) {
            $action_error = "Failed to update appointment: " . $e->getMessage();
        }
    }
}

// Fetch all appointments for this nutritionist
try {
    $app_sql = "
        SELECT 
            a.id, 
            a.appointment_date, 
            a.appointment_time, 
            a.status, 
            a.notes, 
            a.created_at,
            u.fullname AS user_name,
            u.email AS user_email
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        WHERE a.nutritionist_id = ?
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ";
    $app_stmt = $conn->prepare($app_sql);
    $app_stmt->execute([$nutritionist_id]);
    $appointments = $app_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $appointments = [];
    $action_error = "Error fetching appointments: " . $e->getMessage();
}

// Get unread count for sidebar badge
$unread_count = 0;
try {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND status = 0");
    $count_stmt->execute([$nutritionist_id]);
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
<title>Manage Appointments - NutriPanel</title>
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
.nav a{display:flex;align-items:center;gap:12px;text-decoration:none;padding:13px 12px;border-radius:12px;color:#d9ebe4;font-size:14px;font-weight:650;transition:background .2s,transform .2s}
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

.alert-banner {
  padding: 12px 16px; border-radius: 12px; font-size: 13.5px; font-weight: 600; margin-bottom: 18px;
  display: flex; align-items: center; gap: 10px;
}
.alert-banner.success { background: #e6fffa; color: #0f766e; border: 1px solid #b2f5ea; }
.alert-banner.error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

.appointments-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}
.appointment-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 22px;
  box-shadow: var(--shadow);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  transition: transform .2s;
}
.appointment-card:hover { transform: translateY(-2px); border-color: var(--green); }

.client-info-row {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 16px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--border);
}
.client-avatar {
  width: 44px; height: 44px; border-radius: 50%; background: var(--green-soft);
  color: var(--green); font-weight: 700; display: grid; place-items: center; font-size: 15px; overflow: hidden;
  flex-shrink: 0;
}
.client-details strong { display: block; font-size: 15px; color: var(--text); }
.client-details span { font-size: 12px; color: var(--muted); }

.appointment-meta {
  display: grid;
  gap: 8px;
  margin-bottom: 16px;
  font-size: 13.5px;
}
.meta-item {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--text);
}
.meta-item i { color: var(--green); width: 16px; text-align: center; }

.notes-box {
  background: var(--cream);
  padding: 12px;
  border-radius: 12px;
  font-size: 13px;
  color: var(--muted);
  margin-bottom: 16px;
  border: 1px solid var(--border);
}
.notes-box strong { color: var(--text); display: block; margin-bottom: 3px; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; }

.status-badge {
  display: inline-block;
  font-size: 11px;
  font-weight: 800;
  padding: 4px 10px;
  border-radius: 99px;
  text-transform: uppercase;
  letter-spacing: .05em;
}
.status-badge.pending { background: #fef3c7; color: #b45309; }
.status-badge.confirmed { background: #d1fae5; color: #065f46; }
.status-badge.completed { background: #e0f2fe; color: #0369a1; }
.status-badge.cancelled { background: #fee2e2; color: #991b1b; }

.card-actions {
  display: flex;
  gap: 8px;
  margin-top: auto;
  padding-top: 14px;
  border-top: 1px solid var(--border);
}
.btn-action {
  flex: 1;
  padding: 9px 12px;
  border-radius: 10px;
  font-size: 12.5px;
  font-weight: 700;
  cursor: pointer;
  border: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  transition: all .2s;
}
.btn-confirm { background: #10b981; color: #fff; }
.btn-confirm:hover { background: #059669; }

.btn-complete { background: #0284c7; color: #fff; }
.btn-complete:hover { background: #0369a1; }

.btn-cancel { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
.btn-cancel:hover { background: #fecaca; }

.empty-box {
  text-align: center;
  padding: 50px 20px;
  background: var(--card);
  border-radius: 20px;
  border: 1px solid var(--border);
  color: var(--muted);
  grid-column: 1 / -1;
}
</style>
</head>
<body>

<div class="app">
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-icon">🥗</div>
      <div><strong>NutriPanel</strong><span>Nutritionist portal</span></div>
    </div>
    <div class="nav-label">Menu</div>
    <nav class="nav">
      <a href="dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
      <a href="appointments.php" class="active"><span class="nav-icon">📅</span>Appointments</a>
      <a href="inbox.php">
        <span class="nav-icon">📥</span>Inbox
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
        <div class="eyebrow">Schedule Management</div>
        <h1>Client Appointments</h1>
      </div>
    </header>

    <?php if (!empty($action_success)): ?>
      <div class="alert-banner success"><i class="fa-solid fa-circle-check"></i> <?php echo $action_success; ?></div>
    <?php endif; ?>
    <?php if (!empty($action_error)): ?>
      <div class="alert-banner error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $action_error; ?></div>
    <?php endif; ?>

    <div class="appointments-grid">
      <?php if (count($appointments) > 0): ?>
        <?php foreach ($appointments as $app): ?>
          <div class="appointment-card">
            <div>
              <div class="client-info-row">
                <div class="client-avatar">
                  <?php echo getInitials($app['user_name']); ?>
                </div>
                <div class="client-details">
                  <strong><?php echo htmlspecialchars($app['user_name']); ?></strong>
                  <span><?php echo htmlspecialchars($app['user_email']); ?></span>
                </div>
                <div style="margin-left: auto;">
                  <span class="status-badge <?php echo $app['status']; ?>"><?php echo $app['status']; ?></span>
                </div>
              </div>

              <div class="appointment-meta">
                <div class="meta-item">
                  <i class="fa-regular fa-calendar"></i>
                  <span><strong>Date:</strong> <?php echo date('F d, Y', strtotime($app['appointment_date'])); ?></span>
                </div>
                <div class="meta-item">
                  <i class="fa-regular fa-clock"></i>
                  <span><strong>Time:</strong> <?php echo date('g:i A', strtotime($app['appointment_time'])); ?></span>
                </div>
              </div>

              <?php if (!empty($app['notes'])): ?>
                <div class="notes-box">
                  <strong>Consultation Notes / Goal:</strong>
                  <?php echo htmlspecialchars($app['notes']); ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="card-actions">
              <form method="POST" style="display: flex; gap: 8px; width: 100%;">
                <input type="hidden" name="appointment_id" value="<?php echo $app['id']; ?>">
                
                <?php if ($app['status'] === 'pending'): ?>
                  <button type="submit" name="action_confirm" value="1" class="btn-action btn-confirm" title="Confirm Appointment">
                    <i class="fa-solid fa-check"></i> Confirm
                  </button>
                  <button type="submit" name="action_cancel" value="1" class="btn-action btn-cancel" title="Cancel Appointment">
                    <i class="fa-solid fa-xmark"></i> Cancel
                  </button>
                <?php elseif ($app['status'] === 'confirmed'): ?>
                  <button type="submit" name="action_complete" value="1" class="btn-action btn-complete" title="Mark as Completed">
                    <i class="fa-solid fa-flag-checkered"></i> Complete
                  </button>
                  <button type="submit" name="action_cancel" value="1" class="btn-action btn-cancel" title="Cancel Appointment">
                    <i class="fa-solid fa-xmark"></i> Cancel
                  </button>
                <?php else: ?>
                  <span style="font-size: 12px; color: var(--muted); text-align: center; width: 100%; padding: 6px;">
                    Appointment is <?php echo $app['status']; ?>
                  </span>
                <?php endif; ?>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-box">
          <i class="fa-regular fa-calendar-xmark" style="font-size: 36px; margin-bottom: 12px; display: block;"></i>
          No appointment requests found yet.
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

</body>
</html>