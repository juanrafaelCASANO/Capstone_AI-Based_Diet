<?php
// reset_password.php
session_start();
require_once '../config.php';

$errors = [];
$success = "";
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    die("Invalid request. No token provided.");
}

// PDO Check token
$stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die("This password reset link is invalid or has expired.");
}

$user_id = $user['id'];

if (isset($_POST['reset_password'])) {
    $new_pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    if (strlen($new_pass) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    } elseif ($new_pass !== $confirm_pass) {
        $errors[] = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);

        // PDO Update Password
        $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        
        if ($update_stmt->execute([$hashed_password, $user_id])) {
            $success = "Password successfully updated! You can now <a href='login.php'>Login</a>.";
        } else {
            $errors[] = "Something went wrong.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set New Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7ff; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 400px; }
        input { width: 100%; padding: 12px; margin: 10px 0 20px; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; }
        button { width: 100%; background: #2563eb; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .error { background: #fee2e2; color: #dc2626; padding: 12px; margin-bottom: 20px; }
        .success { background: #dcfce7; color: #166534; padding: 12px; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Set New Password</h2>
    <?php if (!empty($errors)): ?><div class="error"><?php foreach ($errors as $err) echo "• $err<br>"; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?php echo $success; ?></div>
    <?php else: ?>
        <form method="POST">
            <label>New Password</label>
            <input type="password" name="password" required>
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required>
            <button type="submit" name="reset_password">Update Password</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>