<?php
// register.php
session_start();
require_once '../config.php';

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = trim($_POST['phone']);

    $age = intval($_POST['age']);
    $gender = $_POST['gender'];
    $height = floatval($_POST['height']);
    $weight = floatval($_POST['weight']);
    $goal = $_POST['goal'];
    $activity = $_POST['activity'];
    $diet_type = trim($_POST['diet_type']);
    $allergies = trim($_POST['allergies']);

    if (empty($fullname)) $errors[] = "Full name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (empty($password) || strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
    if (!preg_match('/^[0-9]{11}$/', $phone)) $errors[] = "Phone number must be exactly 11 digits.";
    if ($age <= 0) $errors[] = "Valid age is required.";
    if ($height <= 0) $errors[] = "Valid height is required.";
    if ($weight <= 0) $errors[] = "Valid weight is required.";

    if (empty($errors)) {
        // PDO Check existing email
        $stmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Email already registered. Please sign in.";
        }
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'user';

        // PDO Insert
        $stmt = $conn->prepare("
            INSERT INTO users 
            (fullname, email, phone, password, role, age, gender, height, weight, goal, activity, diet_type, allergies)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt->execute([
            $fullname, $email, $phone, $hashed_password, $role, $age, $gender,
            $height, $weight, $goal, $activity, $diet_type, $allergies
        ])) {
            $success = "Registration successful! You can now <a href='login.php'>Sign In</a>.";
        } else {
            $errors[] = "Database error occurred.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family:'Inter',sans-serif; background:#f4f7ff; padding:20px; }
        .container { max-width:500px; margin:auto; background:white; padding:30px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,.05); }
        h2 { text-align:center; margin-bottom:20px; color:#2563eb; }
        input, select, textarea { width:100%; padding:12px; margin:8px 0; border:1px solid #ccc; border-radius:8px; box-sizing: border-box; }
        button { width:100%; padding:14px; background:#2563eb; color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer; }
        .error { background:#fee2e2; padding:10px; border-left:4px solid #dc2626; margin-bottom:15px; }
        .success { background:#d1fae5; padding:10px; border-left:4px solid #22c55e; margin-bottom:15px; }
        .password-box { position: relative; }
        .toggle-password { position: absolute; right: 14px; top: 22px; cursor: pointer; color: #94a3b8; }
    </style>
</head>
<body>
<div class="container">
    <h2>Create Your Profile</h2>
    <?php if (!empty($errors)): ?><div class="error"><?php foreach ($errors as $err) echo "• $err<br>"; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>

    <form method="POST">
        <input type="text" name="fullname" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Email Address" required>
        
        <div class="password-box">
            <input type="password" name="password" id="password" placeholder="Password" required>
            <i class="fa-regular fa-eye toggle-password" onclick="togglePassword('password', this)"></i>
        </div>
        <div class="password-box">
            <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
            <i class="fa-regular fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
        </div>

        <input type="text" name="phone" placeholder="Phone (11 digits)" required>
        <input type="number" name="age" placeholder="Age" required>
        <select name="gender" required><option value="">Gender</option><option value="male">Male</option><option value="female">Female</option></select>
        <input type="number" name="height" placeholder="Height (cm)" required>
        <input type="number" name="weight" placeholder="Weight (kg)" required>
        
        <select name="goal" required><option value="">Goal</option><option value="lose">Lose Weight</option><option value="maintain">Maintain</option><option value="gain">Gain Muscle</option></select>
        <select name="activity" required><option value="">Activity Level</option><option value="low">Low</option><option value="moderate">Moderate</option><option value="high">High</option></select>
        <input type="text" name="diet_type" placeholder="Diet Type (e.g. Vegetarian)">
        <textarea name="allergies" placeholder="Food Allergies (optional)"></textarea>

        <button type="submit">Create Account</button>
    </form>
    <p style="text-align:center; margin-top:15px;"><a href="login.php" style="color:#2563eb;">Sign In instead</a></p>
</div>

<script>
function togglePassword(fieldId, icon) {
    let input = document.getElementById(fieldId);
    if(input.type === "password") { input.type = "text"; icon.className = "fa-regular fa-eye-slash toggle-password"; } 
    else { input.type = "password"; icon.className = "fa-regular fa-eye toggle-password"; }
}
</script>
</body>
</html>