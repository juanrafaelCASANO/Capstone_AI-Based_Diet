<?php
// forgot_password.php
session_start();
require_once '../config.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$phpmailer_path = __DIR__ . '/PHPMailer/src/';

if (file_exists($phpmailer_path . 'Exception.php')) {
    require_once $phpmailer_path . 'Exception.php';
    require_once $phpmailer_path . 'PHPMailer.php';
    require_once $phpmailer_path . 'SMTP.php';
} else {
    die("PHPMailer files not found in: " . $phpmailer_path);
}

$errors = [];
$success = "";

if (isset($_POST['send_reset'])) {
    $email = trim($_POST['email']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    } else {
        // PDO Code Here
        $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));

            $stmt2 = $conn->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE id=?");
            $stmt2->execute([$token, $expires, $user['id']]);

            $reset_link = "http://localhost/AI_BASED_DIET&NUTRITIONAL_PLANNER/auth/reset_password.php?token=$token";

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'yourgmail@gmail.com'; 
                $mail->Password   = 'abcd efgh ijkl mnop';  
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('yourgmail@gmail.com', 'AI Diet Planner');
                $mail->addAddress($email, $user['fullname']);

                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body    = "<h3>Hi {$user['fullname']},</h3>
                                  <p>You requested a password reset. Click the button below:</p>
                                  <p><a href='$reset_link' style='background:#2563eb; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Reset Password</a></p>
                                  <p>Or copy this link: $reset_link</p>
                                  <p>This link expires in 1 hour.</p>";

                $mail->send();
                $success = "A reset link has been sent. Please check your inbox.";
            } catch (Exception $e) {
                $errors[] = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $errors[] = "This email is not registered in our system.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI-Based Diet & Nutritional Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7ff; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 400px; }
        h2 { margin-bottom: 10px; color: #1e293b; }
        p { color: #64748b; font-size: 14px; margin-bottom: 25px; }
        input { width: 100%; padding: 12px; margin: 10px 0 20px; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; }
        button { width: 100%; background: #2563eb; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .error { background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; }
        .success { background: #dcfce7; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #2563eb; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Forgot Password?</h2>
    <p>Enter your email and we'll send you a link to reset your password.</p>
    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $err) echo "• $err<br>"; ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php endif; ?>
    <form method="POST">
        <label style="font-size: 14px; font-weight: 600; color: #475569;">Email Address</label>
        <input type="email" name="email" placeholder="e.g. name@example.com" required>
        <button type="submit" name="send_reset">Send Reset Link</button>
    </form>
    <a href="login.php" class="back-link">← Back to Login</a>
</div>
</body>
</html>