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
    if (empty($_FILES['document']['name'][0])) $errors[] = "At least one verification document is required.";

    if (empty($errors)) {
        $doc_dir = "../uploads/nutritionists/";
        $pic_dir = "../uploads/profile_pics/";

        if (!is_dir($doc_dir)) mkdir($doc_dir, 0755, true);
        if (!is_dir($pic_dir)) mkdir($pic_dir, 0755, true);

        // Upload Profile Picture
        $pic_filename = time() . "_pic_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["profile_pic"]["name"]));
        $pic_uploaded = move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $pic_dir . $pic_filename);

        // Upload Multiple Verification Documents
        $uploaded_docs = [];
        foreach ($_FILES['document']['name'] as $key => $name) {
            if ($_FILES['document']['error'][$key] === UPLOAD_ERR_OK) {
                $doc_filename = time() . "_" . $key . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($name));
                if (move_uploaded_file($_FILES['document']['tmp_name'][$key], $doc_dir . $doc_filename)) {
                    $uploaded_docs[] = $doc_filename;
                }
            }
        }

        if ($pic_uploaded && !empty($uploaded_docs)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $documents_json = json_encode($uploaded_docs);

            // PDO Insert
            $stmt = $conn->prepare("INSERT INTO nutritionist (fullname, profile_pic, email, phone, password, certification, experience, document, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            
            if ($stmt->execute([$fullname, $pic_filename, $email, $phone, $hashed_password, $certification, $experience, $documents_json])) {
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
    <title>Nutritionist Registration - AI Diet Planner</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
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
            --success-bg: #f0fdf4;
            --success-text: #166534;
            --success-border: #bbf7d0;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            line-height: 1.5;
        }

        a { text-decoration: none; color: inherit; }

        body::before {
            content: "";
            position: fixed;
            width: 520px; height: 520px;
            top: -230px; right: -180px;
            background: rgba(22,163,106,.10);
            filter: blur(20px);
            border-radius: 50%;
            pointer-events: none;
            z-index: -1;
        }
        
        body::after {
            content: "";
            position: fixed;
            width: 400px; height: 400px;
            bottom: -150px; left: -150px;
            background: rgba(22,163,106,.07);
            filter: blur(20px);
            border-radius: 50%;
            pointer-events: none;
            z-index: -1;
        }

        .header {
            padding: 24px clamp(20px, 5vw, 60px);
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            font-family: 'Manrope', sans-serif;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: -.4px;
        }
        
        .logo-mark {
            width: 40px; height: 40px;
            display: grid; place-items: center;
            border-radius: 13px;
            background: linear-gradient(145deg,#20bd7a,#078452);
            box-shadow: 0 9px 22px rgba(22,163,106,.24);
            font-size: 21px;
        }
        
        .logo span { color: var(--dark); }

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

        .wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 10px clamp(15px, 4vw, 40px) 60px;
        }

        .card {
            background: rgba(255,255,255,.85);
            width: 100%;
            max-width: 780px;
            padding: clamp(30px, 5vw, 45px);
            border-radius: var(--radius);
            border: 1px solid rgba(255,255,255,.9);
            box-shadow: var(--shadow);
            backdrop-filter: blur(15px);
            animation: slideUp 0.6s ease-out forwards;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .card-header h2 {
            font-family: 'Manrope', sans-serif;
            font-size: clamp(26px, 4vw, 32px);
            letter-spacing: -0.5px;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .card-header p {
            color: var(--muted);
            font-size: 15px;
        }

        .alert-card {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 24px;
            font-size: 14px;
            line-height: 1.5;
            animation: slideDown 0.4s ease-out forwards;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-card-danger { background: var(--error-bg); border: 1px solid var(--error-border); color: var(--error-text); }
        .alert-card-success { background: var(--success-bg); border: 1px solid var(--success-border); color: var(--success-text); }
        
        .alert-card i { margin-top: 3px; font-size: 16px; }
        .alert-content { display: flex; flex-direction: column; gap: 4px; }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width { grid-column: 1 / -1; }

        .section-divider {
            grid-column: 1 / -1;
            font-family: 'Manrope', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
            margin-top: 15px;
            margin-bottom: 5px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-divider::before {
            content: "";
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--primary);
        }

        label {
            font-weight: 600;
            font-size: 14px;
            color: var(--dark-2);
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: var(--surface-2);
            font-family: inherit;
            font-size: 14.5px;
            color: var(--text);
            transition: all 0.25s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px var(--primary-soft);
        }

        .form-control::placeholder { color: #9ba7a2; }

        textarea.form-control { resize: vertical; min-height: 100px; }

        input[type="file"].form-control {
            padding: 10px 14px;
            background: var(--surface-2);
            color: var(--muted);
            cursor: pointer;
            border: 1px dashed var(--line);
        }

        input[type="file"]::-webkit-file-upload-button {
            background: #fff;
            border: 1px solid var(--line);
            padding: 8px 14px;
            border-radius: 8px;
            color: var(--dark-2);
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            font-size: 13px;
            margin-right: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(7,26,18,0.02);
        }

        input[type="file"]::-webkit-file-upload-button:hover {
            background: var(--primary-soft);
            color: var(--primary-dark);
            border-color: rgba(22, 163, 106, 0.2);
        }

        .password-box { position: relative; width: 100%; }
        .password-box .form-control { padding-right: 45px; }
        
        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9ba7a2;
            font-size: 16px;
            transition: color 0.25s ease;
            user-select: none;
        }
        .toggle-password:hover { color: var(--primary-dark); }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            min-height: 52px;
            background: linear-gradient(135deg,#19ad70,#078151);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            margin-top: 15px;
            box-shadow: 0 12px 26px rgba(8,129,81,.20);
            transition: transform .25s ease, box-shadow .25s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 32px rgba(8,129,81,.28);
        }

        .signup-link {
            text-align: center;
            margin-top: 24px;
            font-size: 14.5px;
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

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; gap: 18px; }
            .section-divider { margin-top: 10px; }
        }

        @media (max-width: 480px) {
            .header { padding: 20px 15px; }
            .logo { font-size: 16px; }
            .logo-mark { width: 36px; height: 36px; font-size: 19px; border-radius: 11px; }
            .card { padding: 30px 20px; border-radius: 24px; }
            .card-header h2 { font-size: 24px; }
        }
    </style>
</head>
<body>

<header class="header">
    <a href="../index.php" class="logo" aria-label="AI Diet Planner home">
        <span class="logo-mark">🥗</span>
        <span>AI Diet Planner</span>
    </a>
    <a href="register.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Account Type</a>
</header>

<main class="wrapper">
    <div class="card">
        <div class="card-header">
            <h2>Join as a Nutritionist</h2>
            <p>Register your credentials to start guiding clients and managing diets</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert-card alert-card-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div class="alert-content">
                    <?php foreach ($errors as $err): ?>
                        <span><?php echo htmlspecialchars($err); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-card alert-card-success">
                <i class="fa-solid fa-circle-check"></i>
                <div class="alert-content">
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                
                <div class="section-divider">Account Credentials</div>

                <div class="form-group full-width">
                    <label for="fullname">Full Name</label>
                    <input type="text" id="fullname" name="fullname" class="form-control" placeholder="e.g. Dr. Jane Doe" value="<?php echo htmlspecialchars($fullname); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="name@example.com" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number (11 digits)</label>
                    <input type="text" id="phone" name="phone" class="form-control" placeholder="09123456789" value="<?php echo htmlspecialchars($phone); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-box">
                        <input type="password" id="password" name="password" class="form-control" placeholder="At least 6 characters" required>
                        <i class="fa-regular fa-eye toggle-password" onclick="togglePasswordVisibility('password', this)"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-box">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
                        <i class="fa-regular fa-eye toggle-password" onclick="togglePasswordVisibility('confirm_password', this)"></i>
                    </div>
                </div>

                <div class="section-divider">Professional Credentials</div>

                <div class="form-group">
                    <label for="profile_pic">Profile Photo</label>
                    <input type="file" id="profile_pic" name="profile_pic" class="form-control" accept="image/*" required>
                </div>

                <div class="form-group">
                    <label for="document">Verification Documents (Multiple PDF/Images)</label>
                    <input type="file" id="document" name="document[]" class="form-control" accept="image/*,.pdf" multiple required>
                </div>

                <div class="form-group full-width">
                    <label for="certification">Certifications & Licenses</label>
                    <textarea id="certification" name="certification" class="form-control" placeholder="List your professional licenses, degrees, or certifications..." required><?php echo htmlspecialchars($certification); ?></textarea>
                </div>

                <div class="form-group full-width">
                    <label for="experience">Professional Experience</label>
                    <textarea id="experience" name="experience" class="form-control" placeholder="Describe your relevant work history and areas of expertise..." required><?php echo htmlspecialchars($experience); ?></textarea>
                </div>

                <div class="form-group full-width">
                    <button type="submit" class="btn-primary">Submit Registration <span>→</span></button>
                </div>

            </div>
        </form>

        <div class="signup-link">
            Already have an account? <a href="login.php">Sign In</a>
        </div>
    </div>
</main>

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