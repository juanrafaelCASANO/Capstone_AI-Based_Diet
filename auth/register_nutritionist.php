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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nutritionist Registration | AI Diet Planner</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background: #f4f7ff; 
            color: #0f172a; 
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        a { 
            text-decoration: none; 
            color: #2563eb; 
        }

        .header {
            padding: 25px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 22px;
            font-weight: 800;
            color: #2563eb;
        }

        .back {
            font-size: 15px;
            font-weight: 600;
            color: #2563eb;
            transition: color 0.2s;
        }

        .back:hover {
            color: #1d4ed8;
        }

        .container { 
            max-width: 680px; 
            width: 100%;
            margin: 10px auto 40px auto; 
            background: white; 
            padding: 40px; 
            border-radius: 16px; 
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05); 
        }

        .header-title {
            text-align: center;
            margin-bottom: 25px;
        }

        .header-title h2 { 
            font-size: 26px;
            font-weight: 800; 
            color: #1e293b;
            margin-bottom: 6px;
        }

        .header-title p {
            color: #64748b;
            font-size: 14px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        label { 
            font-weight: 600; 
            font-size: 13px; 
            color: #334155;
            margin-bottom: 6px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        textarea { 
            width: 100%; 
            padding: 12px 14px; 
            border: 1px solid #cbd5e1; 
            border-radius: 8px; 
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: #0f172a;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        textarea { 
            resize: vertical; 
            min-height: 80px;
        }

        /* Custom File Input Styling */
        .file-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        input[type="file"] {
            width: 100%;
            padding: 9px 12px;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            font-size: 13px;
            color: #64748b;
            cursor: pointer;
        }

        input[type="file"]::-webkit-file-upload-button {
            background: #e2e8f0;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            color: #334155;
            font-weight: 600;
            font-size: 12px;
            margin-right: 10px;
            cursor: pointer;
            transition: background 0.2s;
        }

        input[type="file"]::-webkit-file-upload-button:hover {
            background: #cbd5e1;
        }

        /* Password Toggle Styling */
        .password-box {
            position: relative;
            width: 100%;
        }

        .password-box input {
            padding-right: 42px;
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 16px;
            user-select: none;
        }

        .toggle-password:hover {
            color: #2563eb;
        }

        button[type="submit"] { 
            width: 100%; 
            background: #2563eb; 
            color: white; 
            padding: 14px; 
            border: none; 
            border-radius: 8px; 
            font-size: 15px;
            font-weight: 700; 
            cursor: pointer; 
            margin-top: 10px; 
            transition: background 0.2s;
        }

        button[type="submit"]:hover {
            background: #1d4ed8;
        }

        .error { 
            background: #fee2e2; 
            color: #991b1b;
            padding: 12px 16px; 
            border-radius: 8px;
            margin-bottom: 20px; 
            border-left: 4px solid #dc2626; 
            font-size: 14px;
            line-height: 1.5;
        }

        .success { 
            background: #d1fae5; 
            color: #065f46;
            padding: 12px 16px; 
            border-radius: 8px;
            margin-bottom: 20px; 
            border-left: 4px solid #22c55e; 
            font-size: 14px;
        }

        .footer-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #64748b;
        }

        .footer-link a {
            font-weight: 600;
        }

        @media (max-width: 640px) {
            .header {
                padding: 20px;
            }

            .container {
                padding: 25px 20px;
                margin: 0 auto 20px auto;
                border-radius: 0;
                box-shadow: none;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <a href="../index.php" class="logo">🥗 AI Diet Planner</a>
    <a href="register.php" class="back">← Switch Account Type</a>
</div>

<div class="container">
    <div class="header-title">
        <h2>Join as a Nutritionist</h2>
        <p>Register your credentials to start guiding clients and managing diets</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $err) echo "• " . htmlspecialchars($err) . "<br>"; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-grid">
            
            <div class="form-group full-width">
                <label>Full Name</label>
                <input type="text" name="fullname" placeholder="e.g. Dr. Jane Doe" value="<?php echo htmlspecialchars($fullname); ?>" required>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="name@example.com" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>

            <div class="form-group">
                <label>Phone Number (11 digits)</label>
                <input type="text" name="phone" placeholder="09123456789" value="<?php echo htmlspecialchars($phone); ?>" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="password-box">
                    <input type="password" name="password" id="password" placeholder="At least 6 characters" required>
                    <i class="fa-regular fa-eye toggle-password" onclick="togglePasswordVisibility('password', this)"></i>
                </div>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <div class="password-box">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter password" required>
                    <i class="fa-regular fa-eye toggle-password" onclick="togglePasswordVisibility('confirm_password', this)"></i>
                </div>
            </div>

            <div class="form-group">
                <label>Profile Photo</label>
                <div class="file-input-wrapper">
                    <input type="file" name="profile_pic" accept="image/*" required>
                </div>
            </div>

            <div class="form-group">
                <label>Verification Document (PDF/Image)</label>
                <div class="file-input-wrapper">
                    <input type="file" name="document" accept="image/*,.pdf" required>
                </div>
            </div>

            <div class="form-group full-width">
                <label>Certifications & Licenses</label>
                <textarea name="certification" placeholder="List your professional licenses, degrees, or certifications..." required><?php echo htmlspecialchars($certification); ?></textarea>
            </div>

            <div class="form-group full-width">
                <label>Professional Experience</label>
                <textarea name="experience" placeholder="Describe your relevant work history and areas of expertise..." required><?php echo htmlspecialchars($experience); ?></textarea>
            </div>

            <div class="form-group full-width">
                <button type="submit">Submit Registration</button>
            </div>

        </div>
    </form>

    <div class="footer-link">
        Already have an account? <a href="login.php">Sign In</a>
    </div>
</div>

<script>
function togglePasswordVisibility(fieldId, iconElement) {
    const passwordInput = document.getElementById(fieldId);
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        iconElement.classList.remove("fa-eye");
        iconElement.classList.add("fa-eye-slash");
    } else {
        passwordInput.type = "password";
        iconElement.classList.remove("fa-eye-slash");
        iconElement.classList.add("fa-eye");
    }
}
</script>

</body>
</html>