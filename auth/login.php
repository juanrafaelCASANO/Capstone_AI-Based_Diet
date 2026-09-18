<?php
session_start();
require_once '../config.php';
require_once '../logs/activity_logger.php';

// --- REMOVED logActivity FROM HERE ---

$errors = [];

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email)) $errors[] = "Email is required.";
    if (empty($password)) $errors[] = "Password is required.";

    if (empty($errors)) {

        $stmt = $conn->prepare("SELECT id, fullname, email, password, role FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];

                // LOG ACTIVITY AFTER SESSION IS SET
                logActivity($conn, $_SESSION['user_id'], $_SESSION['role'], "Logged in");

                if ($user['role'] === 'admin') {
                    header("Location: ../admin/dashboard.php");
                } else {
                    header("Location: ../user/dashboard.php");
                }
                exit();
            } else {
                $errors[] = "Incorrect password.";
            }

        } else {
            $stmt = $conn->prepare("SELECT id, fullname, email, password, status FROM nutritionist WHERE email=? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $nutri = $result->fetch_assoc();

                if (password_verify($password, $nutri['password'])) {
                    if ($nutri['status'] === 'approved') {
                        $_SESSION['user_id']  = $nutri['id'];
                        $_SESSION['fullname'] = $nutri['fullname'];
                        $_SESSION['role'] = 'nutritionist';

                        // LOG ACTIVITY AFTER SESSION IS SET
                        logActivity($conn, $_SESSION['user_id'], $_SESSION['role'], "Logged in");

                        header("Location: ../nutritionist/dashboard.php");
                        exit();
                    } 
                    elseif ($nutri['status'] === 'pending') {
                        $errors[] = "Your account is still pending approval from the Admin.";
                    } 
                    else {
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
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>AI-Based Diet & Nutritional Planner</title>
    
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
    .google-btn { 
        border:1px solid #cbd5e1; 
        padding:14px; 
        border-radius:12px; 
        font-weight:600; 
        display:flex; 
        align-items:center; 
        justify-content:center; 
        gap:10px; 
        cursor:pointer; 
        background:#fff; 
    }
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

    /* ERROR MESSAGE */
    .error { 
        background:#fee2e2; 
        padding:10px; 
        margin-bottom:15px; 
        border-left:4px solid #dc2626; 
        text-align:left; 
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

<!-- LOGIN -->
<div class="wrapper">
    <div class="card">

        <div class="icon">
            <i class="fa-solid fa-seedling"></i>
        </div>

        <h1>Welcome Back</h1>
        <p>Sign in to your AI Diet Planner account</p>

        <!-- SHOW ERRORS -->
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $err) echo "• $err<br>"; ?>
            </div>
        <?php endif; ?>

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

        <!-- FORM -->
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

        <div class="small">
            By continuing, you agree to our Terms of Service and Privacy Policy
        </div>

    </div>
</div>

</body>
</html>
