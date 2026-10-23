<?php
session_start();
require_once '../config.php';

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if (empty($email) || empty($reason)) {
        $error = "Please fill in all required fields.";
    } else {
        $stmt = $conn->prepare("SELECT id, status FROM nutritionist WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $nutri = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$nutri) {
            $error = "No nutritionist account found with this email address.";
        } elseif ($nutri['status'] !== 'inactive') {
            $error = "This account is not inactive or does not require reactivation.";
        } else {
            $nutritionist_id = $nutri['id'];
            
            $checkPending = $conn->prepare("SELECT id FROM reactivation_requests WHERE nutritionist_id = ? AND status = 'pending' LIMIT 1");
            $checkPending->execute([$nutritionist_id]);
            
            if ($checkPending->fetch()) {
                $error = "You already have a pending reactivation request. Please wait for the administrator to review your active appeal.";
            } else {
                $uploaded_docs = [];

                if (!empty($_FILES['attachment']['name'][0])) {
                    $supabaseUrl = getenv('SUPABASE_URL') ?: 'https://mbsjtesmrowfilvxcaqb.supabase.co';
                    $serviceKey = getenv('SUPABASE_SERVICE_KEY') ?: getenv('SUPABASE_KEY') ?: '';
                    $bucket = 'nutritionist-files';

                    foreach ($_FILES['attachment']['name'] as $key => $name) {
                        if ($_FILES['attachment']['error'][$key] === UPLOAD_ERR_OK) {
                            $tmpName = $_FILES['attachment']['tmp_name'][$key];
                            $safeName = time() . "_" . $key . "_reactivate_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($name));
                            
                            $url = rtrim($supabaseUrl, '/') . '/storage/v1/object/' . $bucket . '/' . $safeName;
                            $fileData = file_get_contents($tmpName);

                            $ch = curl_init($url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                'Authorization: Bearer ' . $serviceKey,
                                'Content-Type: application/octet-stream',
                                'x-upsert: true'
                            ]);
                            
                            $response = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);

                            if ($httpCode >= 200 && $httpCode < 300) {
                                $uploaded_docs[] = $safeName;
                            }
                        }
                    }
                }

                if (empty($uploaded_docs)) {
                    $error = "Please upload at least one valid document for reactivation.";
                } else {
                    $attachments_json = json_encode($uploaded_docs);

                    $ins = $conn->prepare("INSERT INTO reactivation_requests (nutritionist_id, email, reason, attachment, status) VALUES (?, ?, ?, ?, 'pending')");
                    $ins->execute([$nutritionist_id, $email, $reason, $attachments_json]);
                    
                    $message = "Your reactivation request has been successfully submitted. The administrator will review your appeal shortly.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Account Reactivation - AI Diet Planner</title>
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
            --primary:#2563eb;
            --primary-dark:#1d4ed8;
            --primary-soft:#eff6ff;
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
            background:rgba(37,99,235,.08);
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
            background:linear-gradient(145deg,#3b82f6,#1d4ed8);
            box-shadow:0 9px 22px rgba(37,99,235,.24);
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

        .file-upload-box {
            border: 2px dashed var(--line);
            background: var(--surface-2);
            padding: 16px;
            border-radius: 14px;
            text-align: center;
            cursor: pointer;
            transition: .25s ease;
        }
        .file-upload-box:hover {
            border-color: var(--primary);
            background: var(--primary-soft);
        }
        .file-upload-box input[type="file"] {
            display: none;
        }
        .file-upload-label {
            font-size: 13px;
            color: var(--muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 600;
        }
        .file-upload-label i { color: var(--primary-dark); font-size: 16px; }

        .btn-primary {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 50px;
            background: linear-gradient(135deg,#3b82f6,#1d4ed8);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            box-shadow: 0 12px 26px rgba(37,99,235,.20);
            transition: transform .25s ease, box-shadow .25s ease;
            margin-top: 8px;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 32px rgba(37,99,235,.28);
        }
    </style>
</head>
<body>

<header class="header">
    <a href="../index.php" class="logo">
        <span class="logo-mark">🥗</span>
        <span>AI Diet Planner</span>
    </a>
    <a href="login.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
</header>

<div class="wrapper">
    <div class="card">
        <div class="card-icon"><i class="fa-solid fa-user-gear"></i></div>
        <h1>Account Reactivation</h1>
        <p class="subtitle">Submit your appeal and supporting documents to restore your expert account access.</p>

        <?php if (!empty($message)): ?>
            <div class="alert-card alert-card-success">
                <i class="fa-solid fa-circle-check"></i>
                <div>
                    <strong>Success</strong>
                    <?= htmlspecialchars($message) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert-card alert-card-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div>
                    <strong>Notice</strong>
                    <?= htmlspecialchars($error) ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Registered Email Address</label>
                <div class="input-box">
                    <i class="fa-regular fa-envelope field-icon"></i>
                    <input type="email" name="email" placeholder="name@example.com" required>
                </div>
            </div>

            <div class="form-group">
                <label>Reason for Reactivation / Explanation</label>
                <div class="input-box">
                    <i class="fa-regular fa-comment-dots field-icon" style="top: 22px;"></i>
                    <textarea name="reason" placeholder="Explain why your account should be reactivated..." required></textarea>
                </div>
            </div>

            <div class="form-group">
                <label>Supporting Documents / Proof (Multiple files allowed)</label>
                <div class="file-upload-box" onclick="document.getElementById('file-input').click()">
                    <input type="file" id="file-input" name="attachment[]" accept="image/*,.pdf,.doc,.docx" multiple onchange="updateFileName(this)">
                    <div class="file-upload-label" id="file-label">
                        <i class="fa-solid fa-cloud-arrow-up"></i> <span id="file-text">Choose files or drag here</span>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-primary">Submit Reactivation Request</button>
        </form>
    </div>
</div>

<script>
    function updateFileName(input) {
        const fileText = document.getElementById('file-text');
        if (input.files && input.files.length > 0) {
            if (input.files.length === 1) {
                fileText.textContent = input.files[0].name;
            } else {
                fileText.textContent = input.files.length + ' files selected';
            }
            fileText.style.color = 'var(--primary-dark)';
            fileText.style.fontWeight = '700';
        } else {
            fileText.textContent = 'Choose files or drag here';
            fileText.style.color = 'var(--muted)';
            fileText.style.fontWeight = '600';
        }
    }
</script>

</body>
</html>