<?php
session_start();
require_once '../config.php';

// Tiyaking ang naka-log in ay isang user/client
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$nutritionist_id = $_GET['nutritionist_id'] ?? 0;

// Kunin ang detalye ng nutritionist para makita ng user kung sino ang ibinubook nila
$nutri_stmt = $conn->prepare("SELECT id, fullname FROM nutritionist WHERE id = ?");
$nutri_stmt->execute([$nutritionist_id]);
$nutritionist = $nutri_stmt->fetch(PDO::FETCH_ASSOC);

if (!$nutritionist) {
    header("Location: inbox.php");
    exit;
}

$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if (empty($appointment_date) || empty($appointment_time)) {
        $error_msg = "Please select both a preferred date and time.";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO appointments (user_id, nutritionist_id, appointment_date, appointment_time, notes, status) 
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$user_id, $nutritionist_id, $appointment_date, $appointment_time, $notes]);
            $success_msg = "Appointment request sent successfully! Waiting for nutritionist confirmation.";
        } catch (Exception $e) {
            $error_msg = "Failed to book appointment: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book Appointment with <?php echo htmlspecialchars($nutritionist['fullname']); ?></title>
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
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  padding: 20px;
}
.container {
  width: 100%;
  max-width: 500px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 30px;
  box-shadow: var(--shadow);
}
h2 { margin-top: 0; color: var(--navy); font-size: 22px; }
.subtitle { font-size: 13px; color: var(--muted); margin-bottom: 24px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--text); }
.form-group input, .form-group textarea {
  width: 100%;
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--cream);
  font-size: 14px;
  outline: none;
  transition: border-color .2s, background .2s;
}
.form-group input:focus, .form-group textarea:focus {
  border-color: var(--teal);
  background: #fff;
}
.btn-submit {
  width: 100%;
  padding: 13px;
  background: var(--teal);
  color: #fff;
  border: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  transition: background .2s, transform .2s;
}
.btn-submit:hover { background: var(--teal-dark); transform: translateY(-1px); }
.alert-success { background: #e6fffa; color: var(--teal-dark); padding: 12px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; font-weight: 600; }
.alert-error { background: #fef2f2; color: #dc2626; padding: 12px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; font-weight: 600; }
.back-link { display: inline-block; margin-top: 16px; font-size: 13px; font-weight: 700; color: var(--teal); text-decoration: none; }
.back-link:hover { text-decoration: underline; }
</style>
</head>
<body>

<div class="container">
  <h2>Book Consultation</h2>
  <div class="subtitle">Schedule an appointment with <strong><?php echo htmlspecialchars($nutritionist['fullname']); ?></strong></div>

  <?php if (!empty($success_msg)): ?>
    <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $success_msg; ?></div>
  <?php endif; ?>

  <?php if (!empty($error_msg)): ?>
    <div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_msg; ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label for="appointment_date">Preferred Date</label>
      <input type="date" id="appointment_date" name="appointment_date" required min="<?php echo date('Y-m-d'); ?>">
    </div>

    <div class="form-group">
      <label for="appointment_time">Preferred Time</label>
      <input type="time" id="appointment_time" name="appointment_time" required>
    </div>

    <div class="form-group">
      <label for="notes">Notes / Purpose of Consultation</label>
      <textarea id="notes" name="notes" rows="4" placeholder="Sabihin sa nutritionist ang iyong layunin o concern..."></textarea>
    </div>

    <button type="submit" class="btn-submit">Submit Appointment Request</button>
  </form>

  <a href="inbox.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Inbox</a>
</div>

</body>
</html>