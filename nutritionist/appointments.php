<?php
session_start();
require_once '../config.php';

// Tiyaking ang naka-log in ay nutritionist
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'nutritionist') {
    header("Location: ../auth/login.php");
    exit;
}

$nutri_id = $_SESSION['user_id'];

// Kunin ang detalye ng nutritionist para sa sidebar/topbar
$stmt = $conn->prepare("SELECT fullname FROM nutritionist WHERE id = ?");
$stmt->execute([$nutri_id]);
$nutri = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle pag-update ng status (Confirm o Cancel)
if (isset($_GET['action'], $_GET['id'])) {
    $appointment_id = $_GET['id'];
    $action = $_GET['action'];
    
    $new_status = ($action === 'confirm') ? 'confirmed' : (($action === 'cancel') ? 'cancelled' : '');
    
    if (!empty($new_status)) {
        try {
            $update_stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ? AND nutritionist_id = ?");
            $update_stmt->execute([$new_status, $appointment_id, $nutri_id]);
            header("Location: appointments.php?success=updated");
            exit;
        } catch (Exception $e) {
            $error_msg = "Failed to update appointment.";
        }
    }
}

// Kunin ang lahat ng appointments para sa nutritionist na ito mula sa users table
Appointments:
$appointments_stmt = $conn->prepare("
    SELECT a.*, u.fullname as client_name, u.email as client_email, u.gender, u.age, u.goal 
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    WHERE a.nutritionist_id = ?
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$appointments_stmt->execute([$nutri_id]);
$appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Appointments | Nutritionist Portal</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📅</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --navy: #0f3a2e;
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
body {
  margin: 0;
  font-family: 'Plus Jakarta Sans', sans-serif;
  background: var(--cream);
  color: var(--text);
  display: flex;
  min-height: 100vh;
}
.sidebar {
  width: 280px;
  background: linear-gradient(180deg, var(--navy), #0a2820);
  color: #fff;
  padding: 24px 18px;
  display: flex;
  flex-direction: column;
}
.brand { display: flex; align-items: center; gap: 12px; padding: 6px 10px 20px; }
.brand-icon { width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; background: rgba(255,255,255,.12); font-size: 23px; }
.brand strong { display: block; font-size: 17px; }
.brand span { display: block; color: #99f6e4; font-size: 12px; }
.nav { display: grid; gap: 6px; margin-top: 10px; }
.nav a {
  display: flex; align-items: center; gap: 12px; padding: 13px 12px; border-radius: 12px;
  color: #e6fffa; font-size: 14px; font-weight: 650; text-decoration: none; transition: background .2s;
}
.nav a:hover, .nav a.active { background: rgba(255,255,255,.14); color: #fff; }
.main { flex: 1; padding: 30px; overflow-y: auto; }
h1 { font-size: 24px; color: var(--navy); margin-top: 0; }
.table-container {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 20px;
  box-shadow: var(--shadow);
  margin-top: 20px;
  overflow-x: auto;
}
table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
th, td { padding: 14px 12px; border-bottom: 1px solid var(--border); }
th { color: var(--muted); font-weight: 700; }
.badge {
  padding: 5px 10px; border-radius: 8px; font-size: 11px; font-weight: 700; text-transform: uppercase;
}
.badge-pending { background: #fef3c7; color: #d97706; }
.badge-confirmed { background: #e6fffa; color: var(--teal-dark); }
.badge-cancelled { background: #fef2f2; color: #dc2626; }
.btn-action {
  padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; border: none; cursor: pointer; text-decoration: none; display: inline-block; margin-right: 4px;
}
.btn-confirm { background: var(--teal); color: #fff; }
.btn-confirm:hover { background: var(--teal-dark); }
.btn-cancel { background: #fee2e2; color: #dc2626; }
.btn-cancel:hover { background: #fecaca; }
.alert-success { background: #e6fffa; color: var(--teal-dark); padding: 12px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; font-weight: 600; }
</style>
</head>
<body>

  <aside class="sidebar">
    <div class="brand">
      <div class="brand-icon">🩺</div>
      <div>
        <strong>NutriPanel</strong>
        <span>Nutritionist portal</span>
      </div>
    </div>
    <nav class="nav">
      <a href="dashboard.php">Dashboard</a>
      <a href="add_meal.php">Add Meal</a>
      <a href="inbox.php">Inbox</a>
      <a href="appointments.php" class="active">Appointments</a>
      <a href="profile.php">My Profile</a>
    </nav>
  </aside>

  <main class="main">
    <h1>Client Appointments</h1>
    <p style="color: var(--muted); font-size: 14px;">Pamahalaan ang mga humihingi ng iskedyul ng konsultasyon mula sa iyong mga kliyente.</p>

    <?php if (isset($_GET['success'])): ?>
      <div class="alert-success"><i class="fa-solid fa-circle-check"></i> Appointment status updated successfully!</div>
    <?php endif; ?>

    <div class="table-container">
      <?php if (count($appointments) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Client Name</th>
              <th>Date & Time</th>
              <th>Goal / Notes</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($appointments as $app): ?>
              <tr>
                <td>
                  <strong><?php echo htmlspecialchars($app['client_name']); ?></strong><br>
                  <span style="font-size: 12px; color: var(--muted);"><?php echo htmlspecialchars($app['client_email']); ?></span>
                </td>
                <td>
                  <i class="fa-regular fa-calendar"></i> <?php echo date('M d, Y', strtotime($app['appointment_date'])); ?><br>
                  <i class="fa-regular fa-clock"></i> <?php echo date('h:i A', strtotime($app['appointment_time'])); ?>
                </td>
                <td>
                  <span style="font-weight: 600;">Goal:</span> <?php echo htmlspecialchars($app['goal'] ?? 'N/A'); ?><br>
                  <span style="font-size: 12px; color: var(--muted);"><?php echo htmlspecialchars($app['notes'] ?: 'No additional notes.'); ?></span>
                </td>
                <td>
                  <span class="badge badge-<?php echo $app['status']; ?>"><?php echo $app['status']; ?></span>
                </td>
                <td>
                  <?php if ($app['status'] === 'pending'): ?>
                    <a href="appointments.php?action=confirm&id=<?php echo $app['id']; ?>" class="btn-action btn-confirm">Confirm</a>
                    <a href="appointments.php?action=cancel&id=<?php echo $app['id']; ?>" class="btn-action btn-cancel">Cancel</a>
                  <?php else: ?>
                    <span style="font-size: 12px; color: var(--muted);">No actions available</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="text-align: center; color: var(--muted); padding: 20px;">Wala pang nakapilang appointment requests sa ngayon.</p>
      <?php endif; ?>
    </div>
  </main>

</body>
</html>