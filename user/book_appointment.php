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

// Kunin ang detalye ng nutritionist
$nutri_stmt = $conn->prepare("SELECT id, fullname FROM nutritionist WHERE id = ?");
$nutri_stmt->execute([$nutritionist_id]);
$nutritionist = $nutri_stmt->fetch(PDO::FETCH_ASSOC);

if (!$nutritionist) {
    header("Location: user_inbox.php");
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
    <title>Book Appointment - AI Diet Planner</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root{
            --bg:#f5f8f6;
            --surface:#ffffff;
            --surface-2:#f0f6f2;
            --text:#102019;
            --muted:#65736d;
            --line:rgba(16,32,25,.09);
            --primary:#16a36a;
            --primary-dark:#08734a;
            --primary-soft:#e3f6ed;
            --dark:#071a12;
            --dark-2:#0d281d;
            --shadow:0 24px 70px rgba(7,26,18,.10);
            --radius:28px;
            
            --error-bg: #fff1f2;
            --error-text: #9f1239;
            --error-border: #fecdd3;
            
            --success-bg: #f0fdf4;
            --success-text: #166534;
            --success-border: #bbf7d0;
        }

        *{box-sizing:border-box;margin:0;padding:0}
        
        body{
            font-family:'DM Sans',sans-serif;
            background:var(--bg);
            color:var(--text);
            overflow-x:hidden;
            line-height:1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        a{text-decoration:none;color:inherit}

        body::before{
            content:"";
            position:fixed;
            width:520px;height:520px;
            top:-230px;right:-180px;
            background:rgba(22,163,106,.10);
            filter:blur(20px);
            border-radius:50%;
            pointer-events:none;
            z-index:-1;
        }

        .header {
            padding: 24px clamp(20px, 5vw, 60px);
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .logo{
            display:inline-flex;
            align-items:center;
            gap:11px;
            font-family:'Manrope',sans-serif;
            font-size:19px;
            font-weight:800;
            letter-spacing:-.4px;
        }
        .logo-mark{
            width:40px;height:40px;
            display:grid;place-items:center;
            border-radius:13px;
            background:linear-gradient(145deg,#20bd7a,#078452);
            box-shadow:0 9px 22px rgba(22,163,106,.24);
            font-size:21px;
        }
        .logo span{color:var(--dark)}

        .back-link {
            font-size: 14px;
            font-weight: 700;
            color: var(--muted);
            transition: .25s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .back-link:hover {
            color: var(--primary-dark);
            transform: translateX(-4px);
        }

        .wrapper { 
            flex: 1;
            display: flex; 
            justify-content: center; 
            align-items: center; 
            padding: 20px;
        }

        .card { 
            background: rgba(255,255,255,.85); 
            width: 100%; 
            max-width: 500px; 
            padding: clamp(30px, 5vw, 45px); 
            border-radius: var(--radius); 
            border: 1px solid rgba(255,255,255,.9);
            box-shadow: var(--shadow); 
            backdrop-filter: blur(15px);
            text-align: center; 
            animation: slideUp 0.6s ease-out forwards;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 26px;
            border-radius: 18px;
            display: grid;
            place-items: center;
        }

        .card h1 { 
            font-family: 'Manrope', sans-serif;
            font-size: 26px;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
            color: var(--dark);
        }
        .card p.subtitle { 
            color: var(--muted); 
            margin-bottom: 28px; 
            font-size: 14.5px;
        }

        .alert-card { 
            display: flex; 
            align-items: flex-start; 
            gap: 12px; 
            padding: 14px 16px; 
            border-radius: 14px; 
            margin-bottom: 20px; 
            text-align: left; 
            font-size: 13px;
            line-height: 1.5;
        }
        .alert-card-danger { 
            background: var(--error-bg); 
            border: 1px solid var(--error-border); 
            color: var(--error-text); 
        }
        .alert-card-success { 
            background: var(--success-bg); 
            border: 1px solid var(--success-border); 
            color: var(--success-text); 
        }
        .alert-card i { margin-top: 3px; font-size: 16px; }
        .alert-card strong { font-family: 'Manrope', sans-serif; font-size: 14px; display: block; margin-bottom: 2px; }

        .form-group { 
            text-align: left; 
            margin-bottom: 18px; 
        }
        .form-group label { 
            display: block;
            font-weight: 600; 
            font-size: 13px; 
            color: var(--dark-2);
            margin-bottom: 8px;
        }

        .input-box { position: relative; }
        .input-box i.field-icon { 
            position: absolute; 
            top: 50%; 
            left: 18px; 
            transform: translateY(-50%); 
            color: #9ba7a2; 
            font-size: 15px;
        }
        .input-box input, .input-box textarea { 
            width: 100%; 
            padding: 14px 18px 14px 48px; 
            border-radius: 14px; 
            border: 1px solid var(--line); 
            background: var(--surface-2); 
            font-family: inherit;
            font-size: 14.5px;
            color: var(--text);
            transition: all 0.25s ease;
        }
        .input-box textarea {
            padding-left: 48px;
            padding-top: 14px;
            resize: vertical;
            min-height: 110px;
        }
        .input-box input:focus, .input-box textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px var(--primary-soft);
        }

        .btn-primary {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 50px;
            background: linear-gradient(135deg,#19ad70,#078151);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            box-shadow: 0 12px 26px rgba(8,129,81,.20);
            transition: transform .25s ease, box-shadow .25s ease;
            margin-top: 8px;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 32px rgba(8,129,81,.28);
        }
    </style>
</head>
<body>

<header class="header">
    <a href="user_inbox.php" class="logo">
        <span class="logo-mark">🥗</span>
        <span>AI Diet Planner</span>
    </a>
    <a href="user_inbox.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Inbox</a>
</header>

<div class="wrapper">
    <div class="card">
        <div class="card-icon"><i class="fa-regular fa-calendar-plus"></i></div>
        <h1>Book Consultation</h1>
        <p class="subtitle">Schedule an appointment with <strong><?php echo htmlspecialchars($nutritionist['fullname']); ?></strong>.</p>

        <?php if (!empty($success_msg)): ?>
            <div class="alert-card alert-card-success">
                <i class="fa-solid fa-circle-check"></i>
                <div>
                    <strong>Success</strong>
                    <?php echo $success_msg; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert-card alert-card-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div>
                    <strong>Notice</strong>
                    <?php echo $error_msg; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Preferred Date</label>
                <div class="input-box">
                    <i class="fa-regular fa-calendar field-icon"></i>
                    <input type="date" name="appointment_date" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Preferred Time</label>
                <div class="input-box">
                    <i class="fa-regular fa-clock field-icon"></i>
                    <input type="time" name="appointment_time" required>
                </div>
            </div>

            <div class="form-group">
                <label>Notes / Purpose of Consultation</label>
                <div class="input-box">
                    <i class="fa-regular fa-comment-dots field-icon" style="top: 22px;"></i>
                    <textarea name="notes" placeholder="Sabihin sa nutritionist ang iyong layunin o concern..." required></textarea>
                </div>
            </div>

            <button type="submit" class="btn-primary">Submit Appointment Request</button>
        </form>
    </div>
</div>

</body>
</html>