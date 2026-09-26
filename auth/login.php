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
<title>AI-Based Diet Planner - Authentication</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'Inter',sans-serif; background:#f4f7ff; color:#0f172a; }
    a { text-decoration:none; color:#2563eb; }
    
    .header { padding:25px 60px; display:flex; justify-content:space-between; align-items:center; }
    .logo { font-size:22px; font-weight:800; color:#2563eb; }
    
    /* Back link hover effect */
    .back { 
        font-size:16px; 
        color:#2563eb; 
        transition: all 0.3s ease;
        display: inline-block;
    }
    .back:hover {
        transform: translateX(-3px);
    }
    
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
    
    /* Submit Button with Pop-up Effect */
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
        transition: all 0.3s ease; 
    }
    .btn:hover {
        background-color: #1d4ed8; 
        transform: translateY(-3px); 
        box-shadow: 0 6px 15px rgba(37,99,235,0.3); 
    }
    
    .signup-link { margin-top: 20px; font-size: 14px; color: #64748b; }
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

        <div class="icon"><i class="fa-solid fa-seedling"></i></div>
        <h1>Welcome Back</h1>
        <p>Sign in to your AI Diet Planner account</p>
        <form method="POST" action="login.php">
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
    </div>
</div>
</body>
</html>