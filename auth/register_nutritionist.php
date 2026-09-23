<?php
session_start();
require_once '../config.php'; 

$errors = [];
$success = "";

$fullname = $_POST['fullname'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$certification = $_POST['certification'] ?? '';
$experience = $_POST['experience'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = trim($_POST['phone']);
    $certification = trim($_POST['certification']);
    $experience = trim($_POST['experience']);

    if (empty($fullname)) $errors[] = "Full name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
    if (empty($_FILES['profile_pic']['name'])) $errors[] = "Profile picture is required.";
    if (empty($_FILES['document']['name'])) $errors[] = "Verification document is required.";

    if (empty($errors)) {
        $doc_dir = "../uploads/nutritionists/";
        $pic_dir = "../uploads/profile_pics/";

        if (!is_dir($doc_dir)) mkdir($doc_dir, 0755, true);
        if (!is_dir($pic_dir)) mkdir($pic_dir, 0755, true);

        $doc_filename = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["document"]["name"]));
        $pic_filename = time() . "_pic_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["profile_pic"]["name"]));

        if (move_uploaded_file($_FILES["document"]["tmp_name"], $doc_dir . $doc_filename) && 
            move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $pic_dir . $pic_filename)) {
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // PDO Insert
            $stmt = $conn->prepare("INSERT INTO nutritionist (fullname, profile_pic, email, phone, password, certification, experience, document, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            
            if ($stmt->execute([$fullname, $pic_filename, $email, $phone, $hashed_password, $certification, $experience, $doc_filename])) {
                $success = "Registration successful! We will review your info soon.";
                $email = ""; $phone = ""; $certification = ""; $experience = "";
            } else {
                $errors[] = "Database Error occurred.";
            }
        } else {
            $errors[] = "File upload failed. Check folder permissions.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Nutritionist Registration</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family:'Inter', sans-serif; background:#f4f7ff; color: #1e293b; }
        .container { max-width:550px; margin:40px auto; background:white; padding:40px; border-radius:16px; box-shadow:0 10px 25px rgba(0,0,0,0.05); }
        h2 { margin-bottom:10px; font-weight: 800; }
        label { font-weight: 600; font-size: 13px; margin-top: 15px; display:block; }
        input, textarea { width:100%; padding:12px; margin-top:6px; border:1px solid #e2e8f0; border-radius:10px; box-sizing: border-box; }
        button { background:#2563eb; color:white; padding:14px; border:none; border-radius:10px; width:100%; margin-top:20px; font-weight:bold; cursor:pointer;}
        .error { background:#fff1f2; padding:15px; border-left: 5px solid #be123c; margin-bottom: 20px;}
        .success { background:#f0fdf4; padding:15px; border-left: 5px solid #15803d; margin-bottom: 20px;}
    </style>
</head>
<body>
<div class="container">
    <a href="../index.php" style="color:#2563eb; text-decoration:none;">← Back</a>
    <h2>Join as an Expert</h2>

    <?php if (!empty($errors)): ?><div class="error"><?php foreach ($errors as $err) echo "• $err<br>"; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success">✅ <?php echo $success; ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Full Name</label><input type="text" name="fullname" value="<?php echo htmlspecialchars($fullname); ?>" required>
        <label>Profile Photo</label><input type="file" name="profile_pic" accept="image/*" required>
        <label>Email Address</label><input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
        <label>Password</label><input type="password" name="password" required>
        <label>Confirm Password</label><input type="password" name="confirm_password" required>
        <label>Phone Number</label><input type="text" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required>
        <label>Certifications</label><textarea name="certification" required><?php echo htmlspecialchars($certification); ?></textarea>
        <label>Experience</label><textarea name="experience" required><?php echo htmlspecialchars($experience); ?></textarea>
        <label>Document (PDF/Image)</label><input type="file" name="document" required>
        <button type="submit">Submit Registration</button>
    </form>
</div>
</body>
</html>