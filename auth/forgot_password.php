<?php
session_start();
require_once '../config.php';

$errors = [];
$success_msg = "";

// TextBee SMS Helper Function
function sendTextBeeSMS($phoneNumber, $message) {
    $apiKey = 'txb_3fsPevHJvqEIoVVr9cR2QmVxKsB4wRm4'; 
    $phoneNumber = trim($phoneNumber);
    if (strpos($phoneNumber, '09') === 0) {
        $phoneNumber = '+63' . substr($phoneNumber, 1);
    }
    $url = 'https://api.textbee.dev/api/v1/gateway/send-sms';
    $data = ['recipients' => [$phoneNumber], 'message' => $message];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// Function para i-clear ang reset sessions
function clearResetSession() {
    unset(
        $_SESSION['reset_phase'], 
        $_SESSION['reset_otp'], 
        $_SESSION['reset_email'], 
        $_SESSION['reset_phone'], 
        $_SESSION['reset_tbl'], 
        $_SESSION['reset_expiry'],
        $_SESSION['reset_verified']
    );
}

// Handle Change Email / Go Back to Step 1
if (isset($_GET['back_to_email'])) {
    clearResetSession();
    header("Location: forgot_password.php");
    exit();
}

// Handle Cancel / Back to Homepage (Login)
if (isset($_GET['cancel'])) {
    clearResetSession();
    header("Location: ../index.php");
    exit();
}

// Handle Resend SMS Code (3 Minutes Reset)
if (isset($_GET['resend_code'])) {
    if (isset($_SESSION['reset_email']) && !empty($_SESSION['reset_phone'])) {
        $otp = rand(100000, 999999);
        $_SESSION['reset_otp'] = $otp;
        $_SESSION['reset_expiry'] = time() + 180; // 3 Minuto (180 Seconds)
        
        $message = "Your AI Diet Planner password reset code is: " . $otp . ". Valid for 3 minutes.";
        sendTextBeeSMS($_SESSION['reset_phone'], $message);
        
        $success_msg = "A new 6-digit verification code has been sent to your registered phone number.";
    } else {
        clearResetSession();
        header("Location: forgot_password.php");
        exit();
    }
}

// Alamin kung anong phase/step ang kasalukuyang aktibo (Default: request)
$phase = $_SESSION['reset_phase'] ?? 'request';

// ======================================================
// STEP 1: REQUEST SMS CODE (Email Checking)
// ======================================================
if (isset($_POST['send_code'])) {
    $email = trim($_POST['email']);
    $user_data = null;
    $tbl_name = "";

    // Suriin ang users table
    $stmt1 = $conn->prepare("SELECT id, phone FROM users WHERE email=? LIMIT 1");
    $stmt1->execute([$email]);
    if ($stmt1->rowCount() > 0) {
        $user_data = $stmt1->fetch();
        $tbl_name = "users";
    } else {
        // Suriin ang nutritionist table
        $stmt2 = $conn->prepare("SELECT id, phone FROM nutritionist WHERE email=? LIMIT 1");
        $stmt2->execute([$email]);
        if ($stmt2->rowCount() > 0) {
            $user_data = $stmt2->fetch();
            $tbl_name = "nutritionist";
        }
    }

    if ($user_data && !empty($user_data['phone'])) {
        $otp = rand(100000, 999999);
        $_SESSION['reset_otp'] = $otp;
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_phone'] = $user_data['phone'];
        $_SESSION['reset_tbl'] = $tbl_name;
        $_SESSION['reset_expiry'] = time() + 180; // 3 Minuto (180 Seconds)
        $_SESSION['reset_phase'] = 'verify_code'; // Lilipat sa Step 2
        
        $message = "Your AI Diet Planner password reset code is: " . $otp . ". Valid for 3 minutes.";
        sendTextBeeSMS($user_data['phone'], $message);
        
        $phase = 'verify_code';
        $success_msg = "A 6-digit verification code has been sent to your registered phone number.";
    } else {
        $errors[] = "Email address not found or no mobile number is registered with this account.";
    }
}

// ======================================================
// STEP 2: VERIFY SMS OTP CODE
// ======================================================
if (isset($_POST['verify_code'])) {
    $code = trim($_POST['otp_code']);

    if (time() > $_SESSION['reset_expiry']) {
        $errors[] = "Your verification code has expired after 3 minutes. Please click Resend Code.";
        $phase = 'verify_code';
    } elseif ((string)$code === (string)$_SESSION['reset_otp']) {
        $_SESSION['reset_verified'] = true;
        $_SESSION['reset_phase'] = 'new_password'; // Lilipat sa Step 3
        $phase = 'new_password';
        $success_msg = "Code verified successfully! Please enter your new password.";
    } else {
        $errors[] = "The verification code you entered is incorrect. Please try again.";
        $phase = 'verify_code';
    }
}

// ======================================================
// STEP 3: SET NEW PASSWORD & RE-ENTER NEW PASSWORD
// ======================================================
if (isset($_POST['update_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true) {
        $errors[] = "Unauthorized access. Please verify your phone code first.";
        clearResetSession();
        $phase = 'request';
    } elseif (strlen($new_password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $errors[] = "Password must contain at least one uppercase letter (A-Z).";
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $errors[] = "Password must contain at least one lowercase letter (a-z).";
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $errors[] = "Password must contain at least one number (0-9).";
    } elseif (!preg_match('/[^a-zA-Z0-9]/', $new_password)) {
        $errors[] = "Password must contain at least one special character (e.g. @, #, $, !).";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "New password and Re-entered password do not match.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $tbl = $_SESSION['reset_tbl'];
        $email = $_SESSION['reset_email'];

        $update_stmt = $conn->prepare("UPDATE $tbl SET password=? WHERE email=?");
        if ($update_stmt->execute([$hashed_password, $email])) {
            clearResetSession();
            $_SESSION['reset_phase'] = 'success_completed';
            $phase = 'success_completed';
        } else {
            $errors[] = "Failed to update password. Please try again.";
        }
    }
}

// Kalkulahin ang natitirang segundo para sa Countdown Timer ng Step 2
$remaining_seconds = ($phase === 'verify_code' && isset($_SESSION['reset_expiry'])) ? max(0, $_SESSION['reset_expiry'] - time()) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - AI Diet Planner</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --bg: #fcfdfc;
            --surface: #ffffff;
            --text: #102019;
            --text-muted: #65736d;
            --line: rgba(16,32,25,.09);
            --primary: #16a36a;
            --primary-dark: #08734a;
            --primary-soft: #e3f6ed;
            --error-bg: #fff1f2;
            --error-text: #9f1239;
            --success-bg: #f0fdf4;
            --success-text: #166534;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: "";
            position: absolute;
            width: 600px; height: 600px;
            top: -200px; right: -200px;
            background: rgba(22,163,106,.05);
            filter: blur(40px);
            border-radius: 50%;
            z-index: -1;
        }

        .auth-card {
            background: var(--surface);
            width: 100%;
            max-width: 440px;
            padding: 45px 40px;
            border-radius: 30px;
            box-shadow: 0 25px 60px rgba(7, 26, 18, 0.05);
            text-align: center;
            z-index: 1;
            border: 1px solid rgba(0,0,0,0.02);
        }

        .card-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 26px;
            border-radius: 18px;
            display: grid;
            place-items: center;
        }

        .auth-card h1 {
            font-family: 'Manrope', sans-serif;
            font-size: 26px;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
            color: var(--text);
            font-weight: 700;
        }

        .auth-card p.subtitle {
            color: var(--text-muted);
            font-size: 15px;
            margin-bottom: 30px;
            line-height: 1.4;
        }

        .form-group {
            text-align: left;
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: var(--text);
            margin-bottom: 8px;
        }

        .input-box { position: relative; }
        .input-box i.field-icon { 
            position: absolute; 
            top: 50%; 
            left: 16px; 
            transform: translateY(-50%); 
            color: #9ba7a2; 
            font-size: 15px;
        }
        .input-box i.toggle-password {
            position: absolute;
            top: 50%;
            right: 16px;
            transform: translateY(-50%);
            color: #9ba7a2;
            font-size: 15px;
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
            padding: 14px 44px 14px 44px; 
            border-radius: 12px; 
            border: 1px solid var(--line); 
            background: #f8faf9; 
            font-family: inherit;
            font-size: 14px;
            color: var(--text);
            transition: all 0.25s ease;
        }
        .input-box input.no-toggle {
            padding-right: 16px;
        }
        .input-box input:focus {
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
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(22, 163, 106, 0.15);
            transition: transform .25s ease, box-shadow .25s ease, background .25s ease;
            margin-top: 10px;
            text-decoration: none;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            background: var(--primary-dark);
            box-shadow: 0 14px 25px rgba(22, 163, 106, 0.25);
        }

        .alert-mini {
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error { background: var(--error-bg); color: var(--error-text); border: 1px solid #fecdd3; }
        .alert-success { background: var(--success-bg); color: var(--success-text); border: 1px solid #bbf7d0; }

        .resend-box {
            margin-top: 15px;
            font-size: 13px;
            color: var(--text-muted);
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

        .navigation-links {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
        }

        .change-email-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--primary-dark);
            font-weight: 700;
            text-decoration: none;
            transition: color 0.25s ease;
        }
        .change-email-btn:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        .back-link {
            display: inline-block;
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.25s ease;
        }
        .back-link:hover { color: var(--primary-dark); }

        @media (max-width: 480px) {
            .auth-card { padding: 35px 24px; max-width: 92%; }
        }
    </style>
</head>
<body>

<div class="auth-card">
    
    <?php if (!empty($success_msg)): ?>
        <div class="alert-mini alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <span><?php echo htmlspecialchars($success_msg); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert-mini alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div>
                <?php foreach ($errors as $err) echo htmlspecialchars($err) . "<br>"; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($phase === 'request'): ?>
        <!-- ========================================== -->
        <!-- STEP 1: EMAIL INPUT                        -->
        <!-- ========================================== -->
        <div class="card-icon"><i class="fa-solid fa-seedling"></i></div>
        <h1>Forgot Password</h1>
        <p class="subtitle">Enter your registered email address to receive an SMS verification code.</p>

        <form method="POST" action="forgot_password.php">
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-box">
                    <i class="fa-regular fa-envelope field-icon"></i>
                    <input type="email" id="email" name="email" class="no-toggle" placeholder="Enter your email" required autofocus>
                </div>
            </div>
            
            <button type="submit" name="send_code" class="btn-primary">Send SMS Code</button>
        </form>

        <div class="navigation-links">
            <a href="forgot_password.php?cancel=true" class="back-link">← Back to Login</a>
        </div>

    <?php elseif ($phase === 'verify_code'): ?>
        <!-- ========================================== -->
        <!-- STEP 2: SMS VERIFICATION CODE              -->
        <!-- ========================================== -->
        <div class="card-icon"><i class="fa-solid fa-key"></i></div>
        <h1>Forgot Password</h1>
        <p class="subtitle">
            Enter the 6-digit code sent to your mobile phone.<br>
            <span style="font-size: 13px; color: #dc2626; font-weight: 600;" id="timer-text">Code expires in: <span id="countdown">03:00</span></span>
        </p>

        <form method="POST" action="forgot_password.php">
            <div class="form-group">
                <label for="otp_code">SMS Verification Code</label>
                <div class="input-box">
                    <i class="fa-solid fa-key field-icon"></i>
                    <input type="text" id="otp_code" name="otp_code" class="no-toggle" placeholder="Enter 6-digit code" maxlength="6" pattern="\d{6}" required autofocus style="letter-spacing: 2px;">
                </div>
            </div>
            
            <button type="submit" name="verify_code" id="verify-btn" class="btn-primary">Verify Code</button>
        </form>

        <div class="resend-box">
            Didn't receive the code? <a href="forgot_password.php?resend_code=true" id="resend-btn" class="resend-link">Resend Code</a>
        </div>

        <div class="navigation-links">
            <a href="forgot_password.php?back_to_email=true" class="change-email-btn"><i class="fa-solid fa-arrow-left"></i> Change Email / Go Back</a>
            <a href="forgot_password.php?cancel=true" class="back-link">← Back to Login</a>
        </div>

        <script>
            // Live Countdown Timer for 3 minutes (180 seconds)
            let timeLeft = <?php echo $remaining_seconds; ?>;
            const countdownElement = document.getElementById('countdown');
            const timerText = document.getElementById('timer-text');
            const verifyBtn = document.getElementById('verify-btn');
            const resendBtn = document.getElementById('resend-btn');

            const timer = setInterval(function() {
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    countdownElement.textContent = "00:00";
                    timerText.innerHTML = "<span style='color: #dc2626; font-weight: 700;'>Code has expired. Click 'Resend Code' below.</span>";
                    
                    resendBtn.classList.add('active');
                    verifyBtn.disabled = true;
                    verifyBtn.style.opacity = "0.5";
                    verifyBtn.style.cursor = "not-allowed";
                } else {
                    let minutes = Math.floor(timeLeft / 60);
                    let seconds = timeLeft % 60;
                    countdownElement.textContent = 
                        String(minutes).padStart(2, '0') + ":" + String(seconds).padStart(2, '0');
                    timeLeft--;
                }
            }, 1000);
        </script>

    <?php elseif ($phase === 'new_password'): ?>
        <!-- ========================================== -->
        <!-- STEP 3: NEW PASSWORD & RE-ENTER PASSWORD   -->
        <!-- ========================================== -->
        <div class="card-icon"><i class="fa-solid fa-lock"></i></div>
        <h1>Reset Password</h1>
        <p class="subtitle">Create a new password for your account.<br><small style="font-size:12px; color:#65736d;">(Must be at least 8 chars with uppercase, lowercase, number, and special symbol e.g., @, #, $)</small></p>

        <form method="POST" action="forgot_password.php">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <div class="input-box">
                    <i class="fa-solid fa-lock field-icon"></i>
                    <input type="password" id="new_password" name="new_password" placeholder="Enter your new password" required autofocus oninput="handlePasswordInput(this, 'toggle_new')">
                    <i class="fa-solid fa-eye toggle-password" id="toggle_new" onclick="togglePassword('new_password', this)" title="Show/Hide Password"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Re-enter New Password</label>
                <div class="input-box">
                    <i class="fa-solid fa-check-double field-icon"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your new password" required oninput="handlePasswordInput(this, 'toggle_confirm')">
                    <i class="fa-solid fa-eye toggle-password" id="toggle_confirm" onclick="togglePassword('confirm_password', this)" title="Show/Hide Password"></i>
                </div>
            </div>
            
            <button type="submit" name="update_password" class="btn-primary">Reset Password</button>
        </form>

        <div class="navigation-links">
            <a href="forgot_password.php?cancel=true" class="back-link">← Back to Login</a>
        </div>

    <?php elseif ($phase === 'success_completed'): ?>
        <!-- ========================================== -->
        <!-- SUCCESS STATE: UI MATCHED MATCHING CARD    -->
        <!-- ========================================== -->
        <div class="card-icon" style="background: var(--success-bg); color: var(--primary-dark);">
            <i class="fa-solid fa-circle-check" style="font-size:32px;"></i>
        </div>
        <h1>Password Reset Successful!</h1>
        <p class="subtitle">Your password has been reset successfully. You can now log in using your new credentials.</p>

        <a href="forgot_password.php?cancel=true" class="btn-primary">Go to Login <span>→</span></a>
    <?php endif; ?>

</div>

<script>
    // Control visibility of the eye icon based on whether text is typed
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

    // Toggle hide / show password text
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