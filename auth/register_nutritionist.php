<?php
session_start();
require_once '../config.php'; // Ensure this file defines $conn

$errors = [];
$success = "";

// Initialize variables for form persistence
$fullname = $_POST['fullname'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$certification = $_POST['certification'] ?? '';
$experience = $_POST['experience'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitize Inputs
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = trim($_POST['phone']);
    $certification = trim($_POST['certification']);
    $experience = trim($_POST['experience']);

    // 2. Validation
    if (empty($fullname)) $errors[] = "Full name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
    if (empty($_FILES['profile_pic']['name'])) $errors[] = "Profile picture is required.";
    if (empty($_FILES['document']['name'])) $errors[] = "Verification document is required.";

    if (empty($errors)) {
        // 3. Setup Directories
        $doc_dir = "../uploads/nutritionists/";
        $pic_dir = "../uploads/profile_pics/";

        if (!is_dir($doc_dir)) mkdir($doc_dir, 0755, true);
        if (!is_dir($pic_dir)) mkdir($pic_dir, 0755, true);

        // 4. Generate Unique Filenames
        $doc_filename = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["document"]["name"]));
        $pic_filename = time() . "_pic_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["profile_pic"]["name"]));

        // 5. Move Files
        if (move_uploaded_file($_FILES["document"]["tmp_name"], $doc_dir . $doc_filename) && 
            move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $pic_dir . $pic_filename)) {
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // 6. Database Insertion using Prepared Statements
            $sql = "INSERT INTO nutritionist (fullname, profile_pic, email, phone, password, certification, experience, document, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssss", $fullname, $pic_filename, $email, $phone, $hashed_password, $certification, $experience, $doc_filename);

            if ($stmt->execute()) {
                $success = "Registration successful! We will review your info soon.";
                
                // --- THE FIX: Clear everything EXCEPT Full Name ---
                $email = "";
                $phone = "";
                $certification = "";
                $experience = "";
            } else {
                $errors[] = "Database Error: " . $stmt->error;
            }
            $stmt->close();
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
    <title>AI-Based Diet & Nutritional Planner</title>
    
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family:'Inter', sans-serif; background:#f4f7ff; padding:10px; color: #1e293b; }
        .header { padding:20px 60px; display:flex; justify-content:space-between; align-items:center; }
        .logo { font-size:22px; font-weight:800; color:#2563eb; }
        .container { max-width:550px; margin:20px auto; background:white; padding:40px; border-radius:16px; box-shadow:0 10px 25px rgba(0,0,0,0.05); }
        h2 { margin-bottom:10px; color:#1e293b; font-weight: 800; }
        p.sub-title { color: #64748b; margin-bottom: 25px; font-size: 14px; }
        label { font-weight: 600; font-size: 13px; color: #475569; display: block; margin-top: 15px; text-transform: uppercase; letter-spacing: 0.5px; }
        input, textarea { width:100%; padding:12px; margin-top:6px; border:1px solid #e2e8f0; border-radius:10px; font-family: inherit; font-size: 15px; }
        input:focus { outline: none; border-color: #2563eb; }
        .password-wrapper { position: relative; width: 100%; }
        .password-wrapper i { position: absolute; right: 15px; top: 55%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; }
        button { background:#2563eb; color:white; padding:14px; border:none; border-radius:10px; font-weight:700; cursor:pointer; width:100%; margin-top:30px; font-size: 16px; }
        button:hover { background: #1d4ed8; }
        .error { background:#fff1f2; padding:15px; margin-bottom:20px; border-radius:10px; color: #be123c; border-left: 5px solid #be123c; font-size: 14px; }
        .success { background:#f0fdf4; padding:15px; margin-bottom:20px; border-radius:10px; color: #15803d; border-left: 5px solid #15803d; font-size: 14px; }
    </style>
</head>
<body>

<div class="header">
    <div class="logo">🥗 AI Diet Planner</div>
    <a href="../index.php" style="text-decoration:none; color:#2563eb; font-weight:600;">← Back to Home</a>
</div>

<div class="container">
    <h2>Join as an Expert</h2>
    <p class="sub-title">Complete the form below to register as a certified nutritionist.</p>

    <?php if (!empty($errors)): ?>
        <div class="error"><?php foreach ($errors as $err) echo "• $err<br>"; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success">✅ <?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Full Name</label>
        <input type="text" name="fullname" value="<?php echo htmlspecialchars($fullname); ?>" placeholder="e.g. Dr. Jane Smith" required>

        <label>Profile Photo</label>
        <input type="file" name="profile_pic" accept="image/*">

        <label>Email Address</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="name@example.com" required>

        <label>Password</label>
        <div class="password-wrapper">
            <input type="password" name="password" id="pass1" required>
            <i class="fa-solid fa-eye" id="togglePass1"></i>
        </div>

        <label>Confirm Password</label>
        <div class="password-wrapper">
            <input type="password" name="confirm_password" id="pass2" required>
            <i class="fa-solid fa-eye" id="togglePass2"></i>
        </div>

        <label>Phone Number</label>
        <input type="text" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="09XXXXXXXXX" required>

        <label>Certifications & Education</label>
        <textarea name="certification" rows="3" placeholder="List your professional licenses..." required><?php echo htmlspecialchars($certification); ?></textarea>

        <label>Professional Experience</label>
        <textarea name="experience" rows="3" placeholder="Tell us about your background..." required><?php echo htmlspecialchars($experience); ?></textarea>

        <label>Verification Document (PDF or Image)</label>
        <input type="file" name="document">

        <button type="submit">Submit Registration</button>
    </form>
</div>

<script>
    function setupPasswordToggle(toggleId, inputId) {
        const toggle = document.getElementById(toggleId);
        const input = document.getElementById(inputId);

        toggle.addEventListener('click', function() {
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }

    setupPasswordToggle('togglePass1', 'pass1');
    setupPasswordToggle('togglePass2', 'pass2');
</script>

</body>
</html>