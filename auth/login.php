<?php
session_start();
require_once '../config.php';
require_once '../logs/activity_logger.php';

$errors = [];
$success_msg = "";

// ======================================================
// TEXTBEE SMS HELPER FUNCTION FOR 2FA
// ======================================================
function sendTextBeeSMS($phoneNumber, $message) {
    $textbeeKey = getenv('TEXTBEE_API_KEY'); 
    
    $phoneNumber = trim($phoneNumber);
    if (strpos($phoneNumber, '09') === 0) {
        $phoneNumber = '+63' . substr($phoneNumber, 1);
    }

    $url = 'https://api.textbee.dev/api/v1/gateway/send-sms';
    
    $data = [
        'recipients' => [$phoneNumber],
        'message' => $message
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'TEXTBEE_API_KEY: ' . $textbeeKey
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

// Handle Cancel OTP / Back to Login
if (isset($_GET['cancel'])) {
    unset($_SESSION['temp_auth']);
    header("Location: login.php");
    exit();
}

// Handle Resend OTP (Resets 3-minute timer)
if (isset($_GET['resend'])) {
    if (isset($_SESSION['temp_auth']) && !empty($_SESSION['temp_auth']['phone'])) {
        $new_otp_code = rand(100000, 999999);
        $_SESSION['temp_auth']['otp'] = $new_otp_code;
        $_SESSION['temp_auth']['otp_expiry'] = time() + 180; // 180 seconds = 3 minutes

        $message = "Your new AI Diet Planner 2FA verification code is: " . $new_otp_code . ". Valid for 3 minutes.";
        sendTextBeeSMS($_SESSION['temp_auth']['phone'], $message);

        $success_msg = "A new verification code has been sent to your phone.";
    }
}

// Handle URL query parameter errors
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'user_not_found') {
        $errors[] = ['title' => 'Account Not Found', 'desc' => 'No account was found matching your session or credentials.'];
    } elseif ($_GET['error'] === 'unauthorized') {
        $errors[] = ['title' => 'Access Denied', 'desc' => 'You do not have permission to access that page.'];
    } elseif ($_GET['error'] === 'account_inactive') {
        $errors[] = ['title' => 'Account Inactive', 'desc' => 'Your nutritionist account is currently inactive. Please contact the administrator.'];
    } else {
        $errors[] = ['title' => 'Authentication Error', 'desc' => htmlspecialchars($_GET['error'])];
    }
}

// STEP 2: VERIFY OTP CODE
if (isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp_code'] ?? '');
    
    if (empty($entered_otp)) {
        $errors[] = ['title' => 'OTP Required', 'desc' => 'Please enter the 6-digit verification code sent to your phone.'];
    } else {
        if (isset($_SESSION['temp_auth'])) {
            if (time() > $_SESSION['temp_auth']['otp_expiry']) {
                $errors[] = ['title' => 'OTP Expired', 'desc' => 'Your verification code has expired after 3 minutes. Please click Resend OTP.'];
            } elseif ((string)$_SESSION['temp_auth']['otp'] === (string)$entered_otp) {
                $user_id  = $_SESSION['temp_auth']['id'];
                $role     = $_SESSION['temp_auth']['role'];
                $fullname = $_SESSION['temp_auth']['fullname'];
                $redirect_url = $_SESSION['temp_auth']['redirect'];

                $_SESSION['user_id']  = $user_id;
                $_SESSION['fullname'] = $fullname;
                $_SESSION['role']     = $role;

                $cookie_token = hash('sha256', $user_id . $role . 'aidietplanner_secret_salt');
                setcookie('trusted_device_' . $user_id, $cookie_token, time() + (7 * 86400), "/", "", false, true);

                unset($_SESSION['temp_auth']);

                if (function_exists('logActivity')) {
                    logActivity($conn, $user_id, $role, "Logged in successfully with 2FA");
                }

                header("Location: " . $redirect_url);
                exit();
            } else {
                $errors[] = ['title' => 'Invalid OTP', 'desc' => 'The verification code you entered is incorrect. Please try again.'];
            }
        } else {
            $errors[] = ['title' => 'Session Expired', 'desc' => 'Please sign in again.'];
        }
    }
}

// STEP 1: INITIAL LOGIN (Email & Password Check)
if (isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $terms_accepted = isset($_POST['terms_accepted']);

    if (empty($email)) $errors[] = ['title' => 'Email Required', 'desc' => 'Please enter your registered email address.'];
    if (empty($password)) $errors[] = ['title' => 'Password Required', 'desc' => 'Your account password cannot be empty.'];
    if (!$terms_accepted) $errors[] = ['title' => 'Terms Agreement Required', 'desc' => 'You must check and accept the Terms of Service.'];

    if (empty($errors)) {
        $authenticated = false;
        $user_data = [];

        // 1. Check "users" table first (Regular Users & Admins)
        $stmt = $conn->prepare("SELECT id, fullname, email, phone, password, role FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

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
                $errors[] = ['title' => 'Invalid Credentials', 'desc' => 'Incorrect password entered.'];
            }
        } else {
            // 2. Check "nutritionist" table (Experts)
            $stmt = $conn->prepare("SELECT id, fullname, email, phone, password, status FROM nutritionist WHERE email=? LIMIT 1");
            $stmt->execute([$email]);
            $nutri = $stmt->fetch();

            if ($nutri) {
                if (password_verify($password, $nutri['password'])) {
                    $nutri_status = $nutri['status'] ?? 'pending';

                    if ($nutri_status === 'active' || $nutri_status === 'approved') {
                        $authenticated = true;
                        $user_data = [
                            'id' => $nutri['id'],
                            'fullname' => $nutri['fullname'],
                            'phone' => $nutri['phone'] ?? '', 
                            'role' => 'nutritionist',
                            'redirect' => "../nutritionist/dashboard.php"
                        ];
                    } elseif ($nutri_status === 'pending') {
                        $errors[] = ['title' => 'Account Pending', 'desc' => 'Your application is under review by an admin.'];
                    } elseif ($nutri_status === 'inactive') {
                        $errors[] = ['title' => 'Account Inactive', 'desc' => 'Your nutritionist account is currently inactive. <a href="request_reactivation.php" style="color: #9f1239; font-weight: 700; text-decoration: underline;">Click here to request reactivation</a>.'];
                    } else {
                        $errors[] = ['title' => 'Account Rejected', 'desc' => 'Your nutritionist application has been declined.'];
                    }
                } else {
                    $errors[] = ['title' => 'Invalid Credentials', 'desc' => 'Incorrect password entered.'];
                }
            } else {
                $errors[] = ['title' => 'Account Not Found', 'desc' => 'No registered account found with this email address.'];
            }
        }

        if ($authenticated) {
            $user_id = $user_data['id'];
            $role = $user_data['role'];
            
            $expected_token = hash('sha256', $user_id . $role . 'aidietplanner_secret_salt');
            $has_trusted_cookie = isset($_COOKIE['trusted_device_' . $user_id]) && ($_COOKIE['trusted_device_' . $user_id] === $expected_token);

            if ($has_trusted_cookie) {
                $_SESSION['user_id']  = $user_id;
                $_SESSION['fullname'] = $user_data['fullname'];
                $_SESSION['role']     = $role;

                if (function_exists('logActivity')) {
                    logActivity($conn, $user_id, $role, "Logged in successfully (Trusted Browser/Device - Bypassed OTP)");
                }

                header("Location: " . $user_data['redirect']);
                exit();
            } else {
                $otp_code = rand(100000, 999999);
                $otp_expiry = time() + 180; 

                $raw_phone = $user_data['phone'];
                $masked_phone = strlen($raw_phone) >= 4 ? substr($raw_phone, 0, 2) . '••••••' . substr($raw_phone, -3) : '••••••••••';

                $_SESSION['temp_auth'] = [
                    'id' => $user_id,
                    'fullname' => $user_data['fullname'],
                    'phone' => $raw_phone,
                    'masked_phone' => $masked_phone,
                    'role' => $role,
                    'redirect' => $user_data['redirect'],
                    'otp' => $otp_code,
                    'otp_expiry' => $otp_expiry
                ];

                if (!empty($raw_phone)) {
                    $message = "Your AI Diet Planner 2FA verification code is: " . $otp_code . ". Valid for 3 minutes.";
                    sendTextBeeSMS($raw_phone, $message);
                }
            }
        }
    }
}

$is_otp_stage = isset($_SESSION['temp_auth']);
$masked_phone_display = $is_otp_stage ? ($_SESSION['temp_auth']['masked_phone'] ?? 'your phone') : '';
$remaining_seconds = $is_otp_stage ? max(0, $_SESSION['temp_auth']['otp_expiry'] - time()) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $is_otp_stage ? 'Two-Factor Authentication' : 'Sign In'; ?> - AI Diet Planner</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#071a12">
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
            --shadow-sm:0 12px 35px rgba(7,26,18,.08);
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
        button{font:inherit}

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
        
        body::after{
            content:"";
            position:fixed;
            width:400px;height:400px;
            bottom:-150px;left:-150px;
            background:rgba(22,163,106,.07);
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
            max-width: 480px; 
            padding: clamp(35px, 5vw, 50px); 
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
            font-size: 28px;
            border-radius: 18px;
            display: grid;
            place-items: center;
        }

        .card h1 { 
            font-family: 'Manrope', sans-serif;
            font-size: 28px;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
            color: var(--dark);
        }
        .card p.subtitle { 
            color: var(--muted); 
            margin-bottom: 32px; 
            font-size: 15px;
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
            animation: slideDown 0.4s ease-out forwards;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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
            margin-bottom: 20px; 
        }
        .form-group label { 
            display: block;
            font-weight: 600; 
            font-size: 14px; 
            color: var(--dark-2);
            margin-bottom: 8px;
        }

        .password-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .password-header label {
            margin-bottom: 0;
        }
        .forgot-link {
            font-size: 13px;
            color: var(--primary-dark);
            font-weight: 700;
            text-decoration: none;
            transition: color 0.25s ease;
        }
        .forgot-link:hover {
            color: var(--primary);
            text-decoration: underline;
        }
        
        .input-box { position: relative; }
        .input-box i.field-icon { 
            position: absolute; 
            top: 50%; 
            left: 18px; 
            transform: translateY(-50%); 
            color: #9ba7a2; 
            font-size: 16px;
            transition: .25s ease;
        }
        .input-box i.toggle-password {
            position: absolute;
            top: 50%;
            right: 18px;
            transform: translateY(-50%);
            color: #9ba7a2;
            font-size: 16px;
            cursor: pointer;
            transition: color 0.25s ease;
            z-index: 5;
            display: none;
        }
        .input-box i.toggle-password:hover {
            color: var(--primary-dark);
        }
        .input-box input { 
            width: 100%; 
            padding: 15px 48px 15px 48px; 
            border-radius: 14px; 
            border: 1px solid var(--line); 
            background: var(--surface-2); 
            font-family: inherit;
            font-size: 15px;
            color: var(--text);
            transition: all 0.25s ease;
        }
        .input-box input.no-toggle {
            padding-right: 18px;
        }
        .input-box input:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px var(--primary-soft);
        }

        .terms-group { 
            display: flex; 
            align-items: flex-start; 
            gap: 12px; 
            text-align: left; 
            margin-bottom: 24px; 
        }
        .terms-group input[type="checkbox"] {
            margin-top: 3px;
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
        }
        .terms-group label {
            font-size: 13px; 
            color: var(--muted); 
            cursor: pointer;
            line-height: 1.5;
        }

        .btn-primary {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 52px;
            background: linear-gradient(135deg,#19ad70,#078151);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            box-shadow: 0 12px 26px rgba(8,129,81,.20);
            transition: transform .25s ease, box-shadow .25s ease;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 32px rgba(8,129,81,.28);
        }

        .signup-link { 
            margin-top: 24px; 
            font-size: 14px; 
            color: var(--muted); 
        }
        .signup-link a {
            color: var(--primary-dark);
            font-weight: 700;
            transition: .25s ease;
        }
        .signup-link a:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        .resend-box {
            margin-top: 16px;
            font-size: 14px;
            color: var(--muted);
        }
        .resend-link {
            color: var(--primary-dark);
            font-weight: 700;
            text-decoration: none;
            pointer-events: none;
            opacity: 0.4;
            cursor: not-allowed;
            transition: all 0.25s ease;
        }
        .resend-link.active {
            pointer-events: auto;
            opacity: 1;
            cursor: pointer;
        }
        .resend-link.active:hover {
            text-decoration: underline;
            color: var(--primary);
        }
        
        @media(max-width: 480px) {
            .card { padding: 30px 20px; border-radius: 24px; }
            .card h1 { font-size: 24px; }
        }
    </style>
</head>
<body>

<header class="header">
    <a href="../index.php" class="logo" aria-label="AI Diet Planner home">
        <span class="logo-mark">🥗</span>
        <span>AI Diet Planner</span>
    </a>
    <a href="../index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back</a>
</header>

<div class="wrapper">
    <div class="card">
        <?php if (!empty($success_msg)): ?>
            <div class="alert-card alert-card-success">
                <i class="fa-solid fa-circle-check"></i>
                <div>
                    <strong>Success</strong>
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
                <div class="alert-card alert-card-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>
                        <strong><?php echo htmlspecialchars($err['title']); ?></strong>
                        <?php echo $err['desc']; // Ginamit nang walang htmlspecialchars para gumana ang HTML link sa loob ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!$is_otp_stage): ?>
            <!-- STEP 1: LOGIN FORM -->
            <div class="card-icon"><i class="fa-solid fa-seedling"></i></div>
            <h1>Welcome Back</h1>
            <p class="subtitle">Sign in to your AI Diet Planner account</p>
            
            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-box">
                        <i class="fa-regular fa-envelope field-icon"></i>
                        <input type="email" id="email" name="email" class="no-toggle" placeholder="Enter your email" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="password-header">
                        <label for="password">Password</label>
                        <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                    </div>
                    <div class="input-box">
                        <i class="fa-solid fa-lock field-icon"></i>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required oninput="handlePasswordInput(this, 'toggle_pwd')">
                        <i class="fa-solid fa-eye toggle-password" id="toggle_pwd" onclick="togglePassword('password', this)" title="Show/Hide Password"></i>
                    </div>
                </div>
                
                <div class="terms-group">
                    <input type="checkbox" id="terms" name="terms_accepted" required>
                    <label for="terms">I agree to the Terms of Service and Privacy Policy.</label>
                </div>
                
                <button class="btn-primary" name="login">Sign In</button>
            </form>
            
            <div class="signup-link">Don't have an account yet? <a href="register.php">Sign Up</a></div>

        <?php else: ?>
            <!-- STEP 2: 2FA OTP VERIFICATION FORM -->
            <div class="card-icon"><i class="fa-solid fa-shield-halved"></i></div>
            <h1>Two-Factor Authentication</h1>
            <p class="subtitle">
                Enter the 6-digit code sent via SMS to <strong><?php echo htmlspecialchars($masked_phone_display); ?></strong>.<br>
                <span style="font-size: 13px; color: #dc2626; font-weight: 600;" id="timer-text">Code expires in: <span id="countdown">03:00</span></span>
            </p>
            
            <form method="POST" action="login.php" id="otp-form">
                <div class="form-group">
                    <label for="otp_code">Verification Code (OTP)</label>
                    <div class="input-box">
                        <i class="fa-solid fa-key field-icon"></i>
                        <input type="text" id="otp_code" name="otp_code" class="no-toggle" placeholder="Enter 6-digit code" maxlength="6" pattern="\d{6}" autocomplete="one-time-code" required autofocus style="text-align: center; letter-spacing: 4px; font-weight: 700; font-size: 18px;">
                    </div>
                </div>
                
                <button class="btn-primary" name="verify_otp" id="verify-btn">Verify & Proceed</button>
            </form>

            <div class="resend-box">
                Didn't receive the code? <a href="login.php?resend=true" id="resend-btn" class="resend-link">Resend OTP</a>
            </div>
            
            <div class="signup-link"><a href="login.php?cancel=true">Cancel / Back to Sign In</a></div>

            <script>
                let timeLeft = <?php echo $remaining_seconds; ?>;
                const countdownElement = document.getElementById('countdown');
                const timerText = document.getElementById('timer-text');
                const verifyBtn = document.getElementById('verify-btn');
                const resendBtn = document.getElementById('resend-btn');
                const otpInput = document.getElementById('otp_code');

                const timer = setInterval(function() {
                    if (timeLeft <= 0) {
                        clearInterval(timer);
                        countdownElement.textContent = "00:00";
                        timerText.innerHTML = "<span style='color: #dc2626; font-weight: 700;'>Verification code has expired. Please click Resend OTP.</span>";
                        
                        resendBtn.classList.add('active');
                        verifyBtn.disabled = true;
                        verifyBtn.style.opacity = "0.5";
                        verifyBtn.style.cursor = "not-allowed";
                        otpInput.disabled = true;
                    } else {
                        let minutes = Math.floor(timeLeft / 60);
                        let seconds = timeLeft % 60;
                        countdownElement.textContent = 
                            String(minutes).padStart(2, '0') + ":" + String(seconds).padStart(2, '0');
                        timeLeft--;
                    }
                }, 1000);
            </script>
        <?php endif; ?>
    </div>
</div>

<script>
    function handlePasswordInput(input, iconId) {
        const icon = document.getElementById(iconId);
        if (input.value.length > 0) {
            icon.style.display = 'block';
        } else {
            icon.style.display = 'none';
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    function togglePassword(inputId, icon) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
</script>

</body>
</html>