<?php
session_start();
require_once '../config.php';
require_once '../logs/activity_logger.php';

$errors = [];$success_msg = "";

// --- SEMAPHORE SMS API FUNCTION ---
function sendSMS_Semaphore($phone,$message) {
    // Replace with your actual Semaphore API Key from semaphore.co
    $apiKey = "0c0bd4d5464a07b79952873bd806bfbb"; 

    $ch = curl_init();$parameters = array(
        'apikey'  => $apiKey,
        'number'  => $phone,
        'message' => $message
    );

    curl_setopt($ch, CURLOPT_URL, 'https://semaphore.co/account#settings');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $output = curl_exec($ch);
    curl_close($ch);

    return $output;
}

// Helper function to generate and trigger SMS OTP
function generateAndSendOTP($phone) {$otp = rand(100000, 999999);
    
    $_SESSION['mfa_phone']      =$phone;
    $_SESSION['mfa_otp']        =$otp;
    $_SESSION['mfa_otp_expiry'] = time() + (5 * 60); // Code expires in 5 minutes (300 seconds)$_SESSION['mfa_step']       = 'enter_code';

    // Construct the text message
    $message = "Your AI Diet Planner verification code is: " . $otp . ". Valid for 5 minutes.";
    
    // Call the Semaphore SMS API
    sendSMS_Semaphore($phone,$message);

    return $otp;
}

// Determine current MFA step
$mfa_step = isset($_SESSION['mfa_pending']) ?$_SESSION['mfa_step'] : 1;

// --- STEP 1: INITIAL LOGIN (Email + Password) ---
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password =$_POST['password'];

    if (empty($email))$errors[] = "Email is required.";
    if (empty($password))$errors[] = "Password is required.";

    if (empty($errors)) {
        // Check Users Table
        $stmt =$conn->prepare("SELECT id, fullname, email, password, role FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);$stmt->execute();
        $result =$stmt->get_result();

        $authenticated = false;
        $user_data = [];

        if ($result->num_rows === 1) {
            $user =$result->fetch_assoc();
            if (password_verify($password, $user['password'])) {$authenticated = true;
                $user_data = [
                    'id' => $user['id'],
                    'fullname' => $user['fullname'],
                    'role' => $user['role'],
                    'redirect' => ($user['role'] === 'admin') ? "../admin/dashboard.php" : "../user/dashboard.php"
                ];
            } else {
                $errors[] = "Incorrect password.";
            }
        } else {
            // Check Nutritionist Table
            $stmt =$conn->prepare("SELECT id, fullname, email, password, status FROM nutritionist WHERE email=? LIMIT 1");
            $stmt->bind_param("s", $email);$stmt->execute();
            $result =$stmt->get_result();

            if ($result->num_rows === 1) {
                $nutri =$result->fetch_assoc();

                if (password_verify($password,$nutri['password'])) {
                    if ($nutri['status'] === 'approved') {$authenticated = true;
                        $user_data = [
                            'id' => $nutri['id'],
                            'fullname' => $nutri['fullname'],
                            'role' => 'nutritionist',
                            'redirect' => "../nutritionist/dashboard.php"
                        ];
                    } elseif ($nutri['status'] === 'pending') {$errors[] = "Your account is still pending approval from the Admin.";
                    } else {
                        $errors[] = "Your account has been rejected. Please contact support.";
                    }
                } else {
                    $errors[] = "Incorrect password.";
                }
            } else {
                $errors[] = "Email not found.";
            }
        }
        $stmt->close();

        // Pass user to MFA step
        if ($authenticated) {$_SESSION['mfa_pending'] = true;
            $_SESSION['mfa_user'] =$user_data;
            $_SESSION['mfa_step'] = 'enter_phone';$mfa_step = 'enter_phone';
        }
    }
}

// --- STEP 2A: INITIAL SEND OTP CODE ---
if (isset($_POST['send_otp'])) {
    $phone = trim($_POST['phone_number']);
    
    if (empty($phone)) {$errors[] = "Phone number is required for authentication.";
    } else {
        $otp = generateAndSendOTP($phone);
        $mfa_step = 'enter_code';$success_msg = "Verification code sent to $phone! (Demo Backup Code: <strong>$otp</strong>)";
    }
}

// --- RESEND OTP ACTION ---
if (isset($_POST['resend_otp'])) {
    if (!empty($_SESSION['mfa_phone'])) {
        $otp = generateAndSendOTP($_SESSION['mfa_phone']);
        $mfa_step = 'enter_code';$success_msg = "A new verification code has been sent to " . htmlspecialchars($_SESSION['mfa_phone']) . "! (Demo Backup Code: <strong>$otp</strong>)";
    } else {
        $errors[] = "Session expired. Please re-enter your phone number.";
        $mfa_step = 'enter_phone';
    }
}

// --- STEP 2B: VERIFY OTP CODE & COMPLETE LOGIN ---
if (isset($_POST['verify_otp'])) {
    $entered_code = trim($_POST['otp_code']);
    
    // Check if OTP has expired
    if (isset($_SESSION['mfa_otp_expiry']) && time() > $_SESSION['mfa_otp_expiry']) {$errors[] = "The verification code has expired. Please click 'Resend Code'.";
        $mfa_step = 'enter_code';
    } 
    // Check if OTP is valid
    elseif (isset($_SESSION['mfa_otp']) && $entered_code ==$_SESSION['mfa_otp']) {
        $user =$_SESSION['mfa_user'];
        
        $_SESSION['user_id']  =$user['id'];
        $_SESSION['fullname'] =$user['fullname'];
        $_SESSION['role']     =$user['role'];

        logActivity($conn, $_SESSION['user_id'],$_SESSION['role'], "Logged in with MFA");

        // Clear temporary MFA sessions
        unset($_SESSION['mfa_pending'],$_SESSION['mfa_user'], $_SESSION['mfa_phone'],$_SESSION['mfa_otp'], $_SESSION['mfa_otp_expiry'],$_SESSION['mfa_step']);

        header("Location: " . $user['redirect']);
        exit();
    } else {
        $errors[] = "Invalid verification code. Please try again.";
        $mfa_step = 'enter_code';
    }
}

// Cancel / Reset MFA Flow
if (isset($_POST['cancel_mfa'])) {
    unset($_SESSION['mfa_pending'],$_SESSION['mfa_user'], $_SESSION['mfa_phone'],$_SESSION['mfa_otp'], $_SESSION['mfa_otp_expiry'],$_SESSION['mfa_step']);
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>AI-Based Diet & Nutritional Planner - Authentication</title>
    
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<!-- Google Identity Services -->
<script src="https://accounts.google.com/gsi/client" async defer></script>

<style>
    * { 
        box-sizing:border-box; 
        margin:0; 
        padding:0; 
    }
    body { 
        font-family:'Inter',sans-serif; 
        background:#f4f7ff; 
        color:#0f172a; 
    }
    a { 
        text-decoration:none; 
        color:#2563eb; 
    }

    /* TOP BAR */
    .header {
        padding:25px 60px;
        display:flex;
        justify-content:space-between;
        align-items:center;
    }
    .logo {
        font-size:22px;
        font-weight:800;
        color:#2563eb;
    }
    .back {
        font-size:16px;
        color:#2563eb;
    }
    /* CENTER */
    .wrapper { 
        min-height:85vh; 
        display:flex; 
        justify-content:center; 
        align-items:center; 
    }
    .card { 
        background:#fff; 
        width:100%; 
        max-width:480px; 
        padding:50px 45px; 
        border-radius:20px; 
        box-shadow:0 25px 60px rgba(0,0,0,.08); 
        text-align:center; 
    }

    /* HEADER */
    .icon { 
        font-size:42px; 
        color:#2563eb;
        margin-bottom:15px;
    }
    .card h1 { 
        font-size:28px; 
        margin-bottom:5px; 
    }
    .card p { 
        color:#64748b; 
        margin-bottom:30px; 
    }

    /* GOOGLE BTN */
    .divider { 
        margin:30px 0; 
        display:flex; 
        align-items:center; 
        gap:15px; 
        color:#94a3b8; }
    .divider::before, .divider::after { 
        content:''; 
        flex:1; 
        height:1px; 
        background:#e2e8f0; 
    }

    /* FORM */
    .form-group { 
        text-align:left; 
        margin-bottom:18px; 
    }
    .form-group label { 
        font-weight:600; 
        font-size:14px; 
    }
    .input-box { 
        position:relative; 
    }
    .input-box i { 
        position:absolute; 
        top:50%; 
        left:15px; 
        transform:translateY(-50%); 
        color:#94a3b8; 
    }
    .input-box input { 
        width:100%; 
        padding:14px 14px 14px 45px; 
        border-radius:12px; 
        border:1px solid #cbd5e1; 
        font-family:'Inter',sans-serif; 
    }

    /* BUTTON */
    .btn { 
        width:100%; 
        background:#2563eb; 
        color:#fff; 
        padding:14px; 
        border:none; 
        border-radius:14px; 
        font-weight:700; 
        font-size:16px; 
        cursor:pointer; 
        margin-top:10px; 
    }
    .btn-secondary {
        background: transparent;
        color: #64748b;
        border: 1px solid #cbd5e1;
    }
    .btn:disabled {
        background: #94a3b8;
        cursor: not-allowed;
    }

    /* ALERTS */
    .error { 
        background:#fee2e2; 
        padding:10px; 
        margin-bottom:15px; 
        border-left:4px solid #dc2626; 
        text-align:left; 
        font-size: 14px;
    }
    .success {
        background:#dcfce7;
        padding:10px;
        margin-bottom:15px;
        border-left:4px solid #16a34a;
        text-align:left;
        font-size: 14px;
        color: #14532d;
    }

    /* FOOTER LINKS */
    .footer-links { 
        margin-top:25px; 
        font-size:14px; 
    }
    .footer-links a { 
        font-weight:600; 
    }
    .small { 
        margin-top:25px; 
        font-size:12px; 
        color:#94a3b8; 
    }
</style>
</head>
<body>

<div class="header">
    <div class="logo">AI Diet Planner</div>
    <a href="../index.php" class="back">← Back</a>
</div>

<div class="wrapper">
    <div class="card">

        <!-- SHOW ERRORS & SUCCESS MESSAGES -->
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $err) echo "• $err<br>"; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="success">
                <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>


        <!-- STEP 1: INITIAL CREDENTIAL LOG IN CARD -->
        <?php if ($mfa_step === 1): ?>

            <div class="icon">
                <i class="fa-solid fa-seedling"></i>
            </div>

            <h1>Welcome Back</h1>
            <p>Sign in to your AI Diet Planner account</p>

            <!-- GOOGLE SIGN-IN BUTTON -->
            <div id="g_id_onload"
                 data-client_id="YOUR_GOOGLE_CLIENT_ID"
                 data-context="signin"
                 data-ux_mode="popup"
                 data-login_uri="http://localhost/auth/google_callback.php"
                 data-auto_prompt="false">
            </div>

            <div class="g_id_signin"
                 data-type="standard"
                 data-shape="rectangular"
                 data-theme="outline"
                 data-text="signin_with"
                 data-size="large"
                 data-logo_alignment="left">
            </div>

            <div class="divider">or</div>

            <form method="POST">
                <div class="form-group">
                    <label>Email</label>
                    <div class="input-box">
                        <i class="fa-regular fa-envelope"></i>
                        <input type="email" name="email" placeholder="Enter your email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-box">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>

                <button class="btn" name="login">Sign In</button>
            </form>

            <div class="footer-links">
                Don’t have an account?
                <a href="register_user.php">Sign Up</a>
                <br><br>
                <a href="forgot_password.php">Forgot Password?</a>
            </div>

        <!-- STEP 2A: MULTI-FACTOR AUTHENTICATION (ENTER PHONE NUMBER) -->
        <?php elseif ($mfa_step === 'enter_phone'): ?>

            <div class="icon">
                <i class="fa-solid fa-shield-halved"></i>
            </div>

            <h1>Two-Factor Check</h1>
            <p>Enter your mobile phone number to receive a secure login verification code.</p>

            <form method="POST">
                <div class="form-group">
                    <label>Phone Number</label>
                    <div class="input-box">
                        <i class="fa-solid fa-mobile-screen-button"></i>
                        <input type="text" name="phone_number" placeholder="09123456789" required autofocus>
                    </div>
                </div>

                <button class="btn" name="send_otp">Send Verification Code</button>
            </form>

            <form method="POST">
                <button class="btn btn-secondary" name="cancel_mfa" style="margin-top:10px;">Cancel / Back to Login</button>
            </form>

        <!-- STEP 2B: MULTI-FACTOR AUTHENTICATION (ENTER OTP CODE & TIMER) -->
        <?php elseif ($mfa_step === 'enter_code'): ?>

            <?php 
                $remaining_seconds = isset($_SESSION['mfa_otp_expiry']) ?$_SESSION['mfa_otp_expiry'] - time() : 0;
                if ($remaining_seconds < 0)$remaining_seconds = 0;
            ?>

            <div class="icon">
                <i class="fa-solid fa-key"></i>
            </div>

            <h1>Enter Code</h1>
            <p>We've sent a code to <strong><?php echo htmlspecialchars($_SESSION['mfa_phone'] ?? ''); ?></strong>.</p>

            <!-- EXPIRATION TIMER DISPLAY -->
            <div id="timer-box" style="margin-bottom: 15px; font-weight: 600; color: #2563eb;">
                Code expires in: <span id="countdown">--:--</span>
            </div>

            <!-- MAIN VERIFY FORM -->
            <form method="POST">
                <div class="form-group">
                    <label>Verification Code</label>
                    <div class="input-box">
                        <i class="fa-solid fa-lock-open"></i>
                        <input type="text" name="otp_code" placeholder="6-digit code" maxlength="6" required autofocus>
                    </div>
                </div>

                <button class="btn" name="verify_otp">Verify & Sign In</button>
            </form>

            <!-- RESEND FORM -->
            <form method="POST" style="margin-top: 10px;">
                <button type="submit" name="resend_otp" id="resend-btn" class="btn btn-secondary" disabled>
                    Resend Code
                </button>
            </form>

            <form method="POST">
                <button class="btn btn-secondary" name="cancel_mfa" style="margin-top:10px;">Back to Login</button>
            </form>

            <!-- COUNTDOWN JAVASCRIPT -->
            <script>
                let timeLeft = <?php echo $remaining_seconds; ?>;
                const countdownElem = document.getElementById('countdown');
                const resendBtn = document.getElementById('resend-btn');

                function startTimer() {
                    const timer = setInterval(() => {
                        if (timeLeft <= 0) {
                            clearInterval(timer);
                            countdownElem.innerText = "EXPIRED";
                            countdownElem.style.color = "#dc2626";
                            resendBtn.disabled = false; // Enable Resend button when code expires
                        } else {
                            let minutes = Math.floor(timeLeft / 60);
                            let seconds = timeLeft % 60;
                            countdownElem.innerText = 
                                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                            timeLeft--;
                        }
                    }, 1000);
                }

                startTimer();
            </script>

        <?php endif; ?>

        <div class="small">
            By continuing, you agree to our Terms of Service and Privacy Policy
        </div>

    </div>
</div>

</body>
</html>