<?php
session_start();
require_once '../config.php';
require_once '../logs/activity_logger.php';

$errors = [];
$success_msg = "";

// Handle URL query parameter errors (e.g., login.php?error=user_not_found)
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'user_not_found') {
        $errors[] = ['title' => 'Account Not Found', 'desc' => 'No account was found matching your session or credentials.'];
    } elseif ($_GET['error'] === 'unauthorized') {
        $errors[] = ['title' => 'Access Denied', 'desc' => 'You do not have permission to access that page.'];
    } else {
        $errors[] = ['title' => 'Authentication Error', 'desc' => htmlspecialchars($_GET['error'])];
    }
}

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
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
                        $errors[] = ['title' => 'Account Pending', 'desc' => 'Your application is under review by an admin.'];
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
            // Set User Session Variables
            $_SESSION['user_id']  = $user_data['id'];
            $_SESSION['fullname'] = $user_data['fullname'];
            $_SESSION['role']     = $user_data['role'];

            // Log activity
            if (function_exists('logActivity')) {
                logActivity($conn, $_SESSION['user_id'], $_SESSION['role'], "Logged in successfully");
            }

            // Redirect directly to the correct dashboard
            header("Location: " . $user_data['redirect']);
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign In - AI Diet Planner</title>
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

        /* Ambient background */
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

        /* HEADER (Simplified Navbar) */
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

        /* AUTH CONTAINER */
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

        /* ALERTS */
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
        .alert-card i { margin-top: 3px; font-size: 16px; }
        .alert-card strong { font-family: 'Manrope', sans-serif; font-size: 14px; display: block; margin-bottom: 2px; }

        /* FORMS */
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
        
        .input-box { position: relative; }
        .input-box i { 
            position: absolute; 
            top: 50%; 
            left: 18px; 
            transform: translateY(-50%); 
            color: #9ba7a2; 
            font-size: 16px;
            transition: .25s ease;
        }
        .input-box input { 
            width: 100%; 
            padding: 15px 18px 15px 48px; 
            border-radius: 14px; 
            border: 1px solid var(--line); 
            background: var(--surface-2); 
            font-family: inherit;
            font-size: 15px;
            color: var(--text);
            transition: all 0.25s ease;
        }
        .input-box input:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px var(--primary-soft);
        }
        .input-box input:focus + i, 
        .input-box input:not(:placeholder-shown) + i {
            color: var(--primary-dark);
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

        /* BUTTONS */
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
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
                <div class="alert-card alert-card-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>
                        <strong><?php echo htmlspecialchars($err['title']); ?></strong>
                        <?php echo htmlspecialchars($err['desc']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="card-icon"><i class="fa-solid fa-seedling"></i></div>
        <h1>Welcome Back</h1>
        <p class="subtitle">Sign in to your AI Diet Planner account</p>
        
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-box">
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                    <i class="fa-regular fa-envelope"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-box">
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <i class="fa-solid fa-lock"></i>
                </div>
            </div>
            
            <div class="terms-group">
                <input type="checkbox" id="terms" name="terms_accepted" required>
                <label for="terms">I agree to the Terms of Service and Privacy Policy.</label>
            </div>
            
            <button class="btn-primary" name="login">Sign In</button>
        </form>
        
        <div class="signup-link">Don't have an account yet? <a href="register.php">Sign Up</a></div>
    </div>
</div>

</body>
</html>