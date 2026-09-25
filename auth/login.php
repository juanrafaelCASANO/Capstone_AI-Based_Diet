<?php
session_start();
require_once '../config.php';
require_once '../logs/activity_logger.php';

$errors = [];
$success_msg = "";

function sendOTP_Textbee($phone, $otp) {
    $apiKey = 'txb_iXPcj9NBxTUvdjehHd4M4b6jt9DvCgLZ';   
    $deviceId = '6ab00919385b7acf3f78edd3'; 

    $phone = preg_replace('/\D/', '', $phone);
    if (substr($phone, 0, 2) === '63') {
        $phone = '0' . substr($phone, 2);
    }

    if (!preg_match('/^09\d{9}$/', $phone)) {
        return ['success' => false, 'error' => 'Invalid Philippine mobile number format.'];
    }

    $message = "Your AI Diet Planner verification code is: " . $otp . ". Valid for 5 minutes.";
    $url = "https://api.textbee.dev/api/v1/gateway/devices/{$deviceId}/send-sms";

    $payload = ['recipients' => [$phone], 'message' => $message];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['x-api-key: ' . $apiKey, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) { return ['success' => false, 'error' => 'cURL Error: ' . $curlError]; }

    return ['success' => true, 'response' => json_decode($response, true)];
}

$mfa_step = isset($_SESSION['mfa_pending']) ? $_SESSION['mfa_step'] : 1;

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $terms_accepted = isset($_POST['terms_accepted']);

    if (empty($email)) $errors[] = ['title' => 'Email Required', 'desc' => 'Please enter your registered email address.'];
    if (empty($password)) $errors[] = ['title' => 'Password Required', 'desc' => 'Your account password cannot be empty.'];
    if (!$terms_accepted) $errors[] = ['title' => 'Terms Agreement Required', 'desc' => 'You must check and accept the Terms.'];

    if (empty($errors)) {
        // PDO User Query
        $stmt = $conn->prepare("SELECT id, fullname, email, phone, password, role FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $authenticated = false;
        $user_data = [];

        if ($user) {
            if (password_verify($password, $user['password'])) {
                $authenticated = true;
                $user_data = [
                    'id' => $user['id'],
                    'fullname' => $user['fullname'],
                    'phone' => $user['phone'] ?? '',
                    'role' => $user['role'],
                    'redirect' => ($user['role'] === 'admin') ? "../admin/dashboard.php" : "../user/dashboard.php"
                ];
            } else {
                $errors[] = ['title' => 'Invalid Credentials', 'desc' => 'Incorrect password.'];
            }
        } else {
            // PDO Nutritionist Query
            $stmt = $conn->prepare("SELECT id, fullname, email, phone, password, status FROM nutritionist WHERE email=? LIMIT 1");
            $stmt->execute([$email]);
            $nutri = $stmt->fetch();

            if ($nutri) {
                if (password_verify($password, $nutri['password'])) {
                    if ($nutri['status'] === 'approved') {
                        $authenticated = true;
                        $user_data = [
                            'id' => $nutri['id'],
                            'fullname' => $nutri['fullname'],
                            'phone' => $nutri['phone'] ?? '', 
                            'role' => 'nutritionist',
                            'redirect' => "../nutritionist/dashboard.php"
                        ];
                    } elseif ($nutri['status'] === 'pending') {
                        $errors[] = ['title' => 'Account Pending', 'desc' => 'Your application is under review.'];
                    } else {
                        $errors[] = ['title' => 'Account Rejected', 'desc' => 'Your application has been declined.'];
                    }
                } else {
                    $errors[] = ['title' => 'Invalid Credentials', 'desc' => 'Incorrect password.'];
                }
            } else {
                $errors[] = ['title' => 'Account Not Found', 'desc' => 'No account found with that email.'];
            }
        }

        if ($authenticated) {
            /* === NAKA-DISABLE MUNA ANG TEXTBEE 2FA ===
            $_SESSION['mfa_pending'] = true;
            $_SESSION['mfa_user'] = $user_data;
            $_SESSION['mfa_step'] = 'enter_phone';
            $mfa_step = 'enter_phone';
            ========================================= */

            // I-SET AGAD ANG SESSION (Para malaman ng system na naka-login na)
            $_SESSION['user_id']  = $user_data['id'];
            $_SESSION['fullname'] = $user_data['fullname'];
            $_SESSION['role']     = $user_data['role'];

            // I-log ang activity na walang 2FA
            logActivity($conn, $_SESSION['user_id'], $_SESSION['role'], "Logged in directly (2FA disabled temporarily)");

            // DIRETSO NA SA DASHBOARD DEPENDE SA ROLE NIYA
            header("Location: " . $user_data['redirect']);
            exit();
        }
    }
}

if (isset($_POST['send_otp'])) {
    $phone = trim($_POST['phone_number']);
    
    if (empty($phone)) {
        $errors[] = ['title' => 'Phone Required', 'desc' => 'Input a valid mobile number.'];
    } else {
        $otp = rand(100000, 999999);
        $result = sendOTP_Textbee($phone, $otp);

        if ($result['success']) {
            $_SESSION['mfa_phone'] = $phone;
            $_SESSION['mfa_otp'] = $otp;
            $_SESSION['mfa_otp_expiry'] = time() + (5 * 60);
            $_SESSION['mfa_step'] = 'enter_code';
            $mfa_step = 'enter_code';
            $success_msg = "Verification code dispatched.";
        } else {
            $errors[] = ['title' => 'SMS Gateway Error', 'desc' => htmlspecialchars($result['error'])];
            $mfa_step = 'enter_phone';
        }
    }
}

if (isset($_POST['verify_otp'])) {
    $entered_code = trim($_POST['otp_code']);
    
    if (isset($_SESSION['mfa_otp_expiry']) && time() > $_SESSION['mfa_otp_expiry']) {
        $errors[] = ['title' => 'OTP Expired', 'desc' => 'The code has expired. Request a new one.'];
        $mfa_step = 'enter_code';
    } elseif (isset($_SESSION['mfa_otp']) && $entered_code == $_SESSION['mfa_otp']) {
        $user = $_SESSION['mfa_user'];
        
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role']     = $user['role'];

        logActivity($conn, $_SESSION['user_id'], $_SESSION['role'], "Logged in with Textbee SMS 2FA");

        unset($_SESSION['mfa_pending'], $_SESSION['mfa_user'], $_SESSION['mfa_phone'], $_SESSION['mfa_otp'], $_SESSION['mfa_otp_expiry'], $_SESSION['mfa_step']);

        header("Location: " . $user['redirect']);
        exit();
    } else {
        $errors[] = ['title' => 'Incorrect Code', 'desc' => 'The code you entered is invalid.'];
        $mfa_step = 'enter_code';
    }
}

if (isset($_POST['cancel_mfa'])) {
    unset($_SESSION['mfa_pending'], $_SESSION['mfa_user'], $_SESSION['mfa_phone'], $_SESSION['mfa_otp'], $_SESSION['mfa_otp_expiry'], $_SESSION['mfa_step']);
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>AI-Based Diet Planner - Authentication</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'Inter',sans-serif; background:#f4f7ff; color:#0f172a; }
    a { text-decoration:none; color:#2563eb; }
    .header { padding:25px 60px; display:flex; justify-content:space-between; align-items:center; }
    .logo { font-size:22px; font-weight:800; color:#2563eb; }
    .back { font-size:16px; color:#2563eb; }
    .wrapper { min-height:85vh; display:flex; justify-content:center; align-items:center; }
    .card { background:#fff; width:100%; max-width:480px; padding:50px 45px; border-radius:20px; box-shadow:0 25px 60px rgba(0,0,0,.08); text-align:center; }
    .icon { font-size:42px; color:#2563eb; margin-bottom:15px; }
    .card h1 { font-size:28px; margin-bottom:5px; }
    .card p { color:#64748b; margin-bottom:30px; }
    .form-group { text-align:left; margin-bottom:18px; }
    .form-group label { font-weight:600; font-size:14px; }
    .input-box { position:relative; }
    .input-box i { position:absolute; top:50%; left:15px; transform:translateY(-50%); color:#94a3b8; }
    .input-box input { width:100%; padding:14px 14px 14px 45px; border-radius:12px; border:1px solid #cbd5e1; font-family:'Inter',sans-serif; }
    .terms-group { display:flex; align-items:flex-start; gap:10px; text-align:left; margin-bottom:18px; font-size:13px; color:#64748b; }
    .alert-card { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; border-radius: 12px; margin-bottom: 12px; text-align: left; font-size: 13px; }
    .alert-card-danger { background: #fef2f2; border: 1px solid #fecaca; border-left: 5px solid #ef4444; color: #991b1b; }
    .alert-card-success { background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 5px solid #22c55e; color: #166534; }
    .btn { width:100%; background:#2563eb; color:#fff; padding:14px; border:none; border-radius:14px; font-weight:700; font-size:16px; cursor:pointer; margin-top:10px; }
    .btn-secondary { background: transparent; color: #64748b; border: 1px solid #cbd5e1; }
    .signup-link { margin-top: 20px; font-size: 14px; color: #64748b; }
    .small { margin-top:25px; font-size:12px; color:#94a3b8; }
</style>
</head>
<body>

<div class="header">
    <div class="logo">AI Diet Planner</div>
    <a href="../index.php" class="back">← Back</a>
</div>

<div class="wrapper">
    <div class="card">
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
                <div class="alert-card alert-card-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><strong><?php echo htmlspecialchars($err['title']); ?></strong><br><?php echo htmlspecialchars($err['desc']); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="alert-card alert-card-success">
                <i class="fa-solid fa-circle-check"></i>
                <div><strong>Success</strong><br><?php echo htmlspecialchars($success_msg); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($mfa_step === 1): ?>
            <div class="icon"><i class="fa-solid fa-seedling"></i></div>
            <h1>Welcome Back</h1>
            <p>Sign in to your AI Diet Planner account</p>
            <form method="POST">
                <div class="form-group">
                    <label>Email</label>
                    <div class="input-box"><i class="fa-regular fa-envelope"></i><input type="email" name="email" required></div>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-box"><i class="fa-solid fa-lock"></i><input type="password" name="password" required></div>
                </div>
                <div class="terms-group">
                    <input type="checkbox" name="terms_accepted" required>
                    <label>I agree to the Terms of Service and Privacy Policy.</label>
                </div>
                <button class="btn" name="login">Sign In</button>
            </form>
            <div class="signup-link">Don't have an account yet? <a href="register.php">Sign Up</a></div>

        <?php elseif ($mfa_step === 'enter_phone'): ?>
            <div class="icon"><i class="fa-solid fa-shield-halved"></i></div>
            <h1>Two-Factor Check</h1>
            <form method="POST">
                <div class="form-group">
                    <label>Phone Number</label>
                    <div class="input-box"><input type="text" name="phone_number" value="<?php echo htmlspecialchars($_SESSION['mfa_user']['phone'] ?? ''); ?>" required></div>
                </div>
                <button class="btn" name="send_otp">Send Verification Code</button>
            </form>
            <form method="POST"><button class="btn btn-secondary" name="cancel_mfa">Cancel</button></form>

        <?php elseif ($mfa_step === 'enter_code'): ?>
            <div class="icon"><i class="fa-solid fa-key"></i></div>
            <h1>Enter Code</h1>
            <form method="POST">
                <div class="form-group">
                    <label>6-Digit Verification Code</label>
                    <div class="input-box"><input type="text" name="otp_code" required></div>
                </div>
                <button class="btn" name="verify_otp">Verify & Sign In</button>
            </form>
            <form method="POST"><button class="btn btn-secondary" name="cancel_mfa">Cancel</button></form>
        <?php endif; ?>

    </div>
</div>
</body>
</html>