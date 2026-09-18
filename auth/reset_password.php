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

// 1. Verify if the token exists and is still valid (less than 1 hour old)
$stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND token_expires > NOW() LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("This password reset link is invalid or has expired. Please request a new one.");
}

$user = $result->fetch_assoc();
$user_id = $user['id'];

// 2. Handle the Password Update
if (isset($_POST['reset_password'])) {
    $new_pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    if (strlen($new_pass) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    } elseif ($new_pass !== $confirm_pass) {
        $errors[] = "Passwords do not match.";
    } else {
        // Hash new password
        $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);

        // Update database and clear the token so it can't be used again
        $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expires = NULL WHERE id = ?");
        $update_stmt->bind_param("si", $hashed_password, $user_id);

        if ($update_stmt->execute()) {
            $success = "Password successfully updated! You can now <a href='login.php'>Login</a>.";
        } else {
            $errors[] = "Something went wrong. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI-Based Diet & Nutritional Planner</title>
    
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7ff; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 400px; }
        h2 { margin-bottom: 10px; color: #1e293b; }
        p { color: #64748b; font-size: 14px; margin-bottom: 25px; }
        input { width: 100%; padding: 12px; margin: 10px 0 20px; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; }
        button { width: 100%; background: #2563eb; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .error { background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .success { background: #dcfce7; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Set New Password</h2>
    <p>Please enter your new password below.</p>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $err) echo "• $err<br>"; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php else: ?>
        <form method="POST">
            <label>New Password</label>
            <input type="password" name="password" required placeholder="Min 6 characters">
            
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" required placeholder="Repeat password">
            
            <button type="submit" name="reset_password">Update Password</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>