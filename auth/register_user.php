<?php
// Register_user.php
session_start();
require_once '../config.php'; // Database connection[cite: 3]

$errors = [];
$success = "";

$fullname = "";
$email = "";
$phone = "";
$age = "";
$gender = "";
$height = "";
$weight = "";
$goal = "";
$activity = "";
$diet_type = "";
$allergies = "";

// When form is submitted[cite: 3]
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // BASIC INFO[cite: 3]
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = trim($_POST['phone']);

    // PROFILE INFO (AI INPUT)[cite: 3]
    $age = intval($_POST['age']);
    $gender = $_POST['gender'];
    $height = floatval($_POST['height']);
    $weight = floatval($_POST['weight']);
    $goal = $_POST['goal'];
    $activity = $_POST['activity'];
    $diet_type = trim($_POST['diet_type']);
    $allergies = trim($_POST['allergies']);

    // Validation[cite: 3]
    if (empty($fullname)) $errors[] = "Full name is required.";
    
    // Advanced Email Validation
    $email_domain = substr(strrchr($email, "@"), 1);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email format is required.";
    } elseif (!checkdnsrr($email_domain, 'MX')) {
        $errors[] = "The email domain does not appear to be valid or able to receive emails.";
    }

    if (empty($password) || strlen($password) < 12) $errors[] = "Password must be at least 12 characters.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    if (!preg_match('/^[0-9]{11}$/', $phone)) {
        $errors[] = "Phone number must be exactly 11 digits.";
    }

    if ($age <= 0) $errors[] = "Valid age is required.";
    if ($height <= 0) $errors[] = "Valid height is required.";
    if ($weight <= 0) $errors[] = "Valid weight is required.";

    // Check if email already exists[cite: 3]
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $errors[] = "Email already registered. Please sign in.";
        }
    }

    // Insert into DB[cite: 3]
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'user';

        $stmt = $conn->prepare("
            INSERT INTO users
            (fullname, email, phone, password, role, age, gender, height, weight, goal, activity, diet_type, allergies)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        try {
            $stmt->execute([
                $fullname,
                $email,
                $phone,
                $hashed_password,
                $role,
                $age,
                $gender,
                $height,
                $weight,
                $goal,
                $activity,
                $diet_type,
                $allergies
            ]);

            $success = "Registration successful! You can now <a href='login.php' style='text-decoration: underline;'>Sign In</a>.";
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration - AI Diet Planner</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <meta name="theme-color" content="#071a12">

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>

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

        /* Ambient background */
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

        /* HEADER */
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

        /* WRAPPER & CARD */
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

        /* SUCCESS ALERT */
        .alert-card-success { 
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 24px;
            font-size: 14px;
            line-height: 1.5;
            background: var(--success-bg); 
            border: 1px solid var(--success-border); 
            color: var(--success-text); 
            animation: slideDown 0.4s ease-out forwards;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-card-success i { margin-top: 3px; font-size: 16px; }

        /* FORM STYLES */
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

        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2365736d'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
            padding-right: 40px;
        }

        textarea.form-control { resize: vertical; min-height: 100px; }

        /* PREMIUM PASSWORD UI STYLES */
        .password-section {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .pwd-labels {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 24px;
        }

        .pwd-labels label {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 15px;
            color: #1a202c;
        }

        .req-asterisk {
            color: #d32f2f;
        }

        .help-icon {
            color: #cbd5e1;
            font-size: 12px;
            cursor: help;
        }

        .show-pwd-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #1e3a8a;
            font-weight: 600;
            font-size: 13.5px;
            cursor: pointer;
            user-select: none;
            transition: color 0.2s ease;
        }

        .show-pwd-toggle:hover {
            color: var(--primary);
        }

        .pwd-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .pwd-inputs input:not(:placeholder-shown) {
            letter-spacing: 3px;
            font-family: monospace;
            font-size: 18px;
        }

        .pwd-meter-row {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 4px;
        }

        .meter-track {
            flex: 1;
            height: 8px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .meter-fill {
            height: 100%;
            width: 0%;
            background: transparent;
            transition: width 0.4s ease, background 0.4s ease;
            border-radius: 10px;
        }

        .meter-text {
            font-weight: 700;
            font-size: 13px;
            color: var(--dark);
            min-width: 45px;
        }

        .pwd-hint {
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
        }

        /* BUTTONS */
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

        /* FOOTER LINK */
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

        /* ERROR MODAL STYLES */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(7, 26, 18, 0.4);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-card {
            background: #ffffff;
            width: 100%;
            max-width: 440px;
            border-radius: 28px;
            padding: 40px 30px;
            box-shadow: 0 30px 80px rgba(7, 26, 18, 0.15);
            text-align: center;
            transform: translateY(20px) scale(0.95);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .modal-overlay.active .modal-card {
            transform: translateY(0) scale(1);
        }

        .modal-icon {
            width: 72px;
            height: 72px;
            background: #fff1f2;
            color: #e11d48;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 20px;
            box-shadow: 0 10px 25px rgba(225, 29, 72, 0.15);
        }

        .modal-title {
            font-family: 'Manrope', sans-serif;
            font-size: 24px;
            color: var(--dark);
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .modal-desc {
            color: var(--muted);
            font-size: 14.5px;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .modal-errors {
            background: var(--surface-2);
            border-radius: 16px;
            padding: 16px 20px;
            text-align: left;
            margin-bottom: 30px;
            max-height: 200px;
            overflow-y: auto;
        }

        .modal-errors ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .modal-errors li {
            position: relative;
            padding-left: 24px;
            margin-bottom: 10px;
            font-size: 14px;
            color: var(--text);
            line-height: 1.4;
        }
        
        .modal-errors li:last-child { margin-bottom: 0; }

        .modal-errors li::before {
            content: "\f071"; 
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            left: 0;
            top: 2px;
            color: #e11d48;
            font-size: 12px;
        }

        .btn-modal {
            background: var(--dark);
            color: #fff;
            border: none;
            width: 100%;
            padding: 16px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .btn-modal:hover {
            background: var(--dark-2);
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(7, 26, 18, 0.2);
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; gap: 18px; }
            .section-divider { margin-top: 10px; }
            .pwd-labels, .pwd-inputs { grid-template-columns: 1fr; gap: 12px; }
            .show-pwd-toggle { justify-content: flex-start; margin-bottom: 8px; }
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

<!-- ERROR MODAL OVERLAY -->
<div class="modal-overlay <?php echo !empty($errors) ? 'active' : ''; ?>" id="errorModal" style="<?php echo !empty($errors) ? 'display:flex;' : 'display:none;'; ?>">
    <div class="modal-card">
        <div class="modal-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3 class="modal-title">Action Required</h3>
        <p class="modal-desc">Please complete the following required information to continue with your registration.</p>
        
        <div class="modal-errors">
            <ul id="errorList">
                <?php foreach ($errors as$err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <button type="button" class="btn-modal" onclick="closeErrorModal()">Got It, Let's Fix This</button>
    </div>
</div>

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
            <h2>Create Your Profile</h2>
            <p>Set up your account and personal metrics for tailored AI meal planning</p>
        </div>

        <?php if ($success): ?>
            <div class="alert-card-success">
                <i class="fa-solid fa-circle-check"></i>
                <div><?php echo $success; ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" id="registerForm" novalidate>
            <div class="form-grid">
                
                <div class="section-divider">Account Credentials</div>

                <div class="form-group full-width">
                    <label for="fullname">Full Name</label>
                    <input type="text" id="fullname" name="fullname" class="form-control" placeholder="Enter your full name" required value="<?php echo htmlspecialchars($fullname); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="name@example.com" required value="<?php echo htmlspecialchars($email); ?>">
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <!-- Phone field restricted to 11 numbers max and numeric input only -->
                    <input type="text" id="phone" name="phone" class="form-control" placeholder="11 digits (e.g. 09123456789)" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);" required value="<?php echo htmlspecialchars($phone); ?>">
                </div>

                <!-- PREMIUM PASSWORD SECTION -->
                <div class="form-group full-width password-section">
                    <div class="pwd-labels">
                        <label for="password">Password <span class="req-asterisk">*</span> <i class="fa-regular fa-circle-question help-icon" title="Must be at least 12 characters long"></i></label>
                        <div class="show-pwd-toggle" onclick="toggleGlobalPassword()">
                            <i class="fa-regular fa-eye" id="global-eye"></i> <span id="global-eye-text">Show Password</span>
                        </div>
                        <label for="confirm_password">Confirm Password <span class="req-asterisk">*</span> <i class="fa-regular fa-circle-question help-icon"></i></label>
                    </div>

                    <div class="pwd-inputs">
                        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••••••••••" required oninput="evaluatePasswordStrength()">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••••••••••" required>
                    </div>

                    <div class="pwd-meter-row">
                        <div class="meter-track">
                            <div class="meter-fill" id="meter-fill"></div>
                        </div>
                        <div class="meter-text" id="meter-text"></div>
                    </div>

                    <div class="pwd-hint">
                        Hint: The password should be at least twelve characters long. To make it stronger, use upper and lower case letters, numbers, and symbols like ! " ? $ % ^ & ).
                    </div>
                </div>
                <!-- END PREMIUM PASSWORD SECTION -->

                <div class="section-divider">Physical & Health Profile</div>

                <div class="form-group">
                    <label for="age">Age</label>
                    <input type="number" id="age" name="age" class="form-control" placeholder="e.g. 25" required value="<?php echo htmlspecialchars($age); ?>">
                </div>

                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" class="form-control" required>
                        <option value="">Select Gender</option>
                        <option value="male" <?php if($gender == 'male') echo 'selected'; ?>>Male</option>
                        <option value="female" <?php if($gender == 'female') echo 'selected'; ?>>Female</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="height">Height (cm)</label>
                    <input type="number" step="0.1" id="height" name="height" class="form-control" placeholder="e.g. 170" required value="<?php echo htmlspecialchars($height); ?>">
                </div>

                <div class="form-group">
                    <label for="weight">Weight (kg)</label>
                    <input type="number" step="0.1" id="weight" name="weight" class="form-control" placeholder="e.g. 65" required value="<?php echo htmlspecialchars($weight); ?>">
                </div>

                <div class="form-group">
                    <label for="goal">Primary Fitness Goal</label>
                    <select id="goal" name="goal" class="form-control" required>
                        <option value="">Select Goal</option>
                        <option value="lose" <?php if($goal == 'lose') echo 'selected'; ?>>Lose Weight</option>
                        <option value="maintain" <?php if($goal == 'maintain') echo 'selected'; ?>>Maintain Weight</option>
                        <option value="gain" <?php if($goal == 'gain') echo 'selected'; ?>>Gain Muscle</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="activity">Daily Activity Level</label>
                    <select id="activity" name="activity" class="form-control" required>
                        <option value="">Select Activity</option>
                        <option value="low" <?php if($activity == 'low') echo 'selected'; ?>>Low (Sedentary)</option>
                        <option value="moderate" <?php if($activity == 'moderate') echo 'selected'; ?>>Moderate (Exercise 3-5 days/wk)</option>
                        <option value="high" <?php if($activity == 'high') echo 'selected'; ?>>High (Heavy exercise daily)</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label for="diet_type">Dietary Preference <span style="color: var(--muted); font-weight: 400;">(Optional)</span></label>
                    <input type="text" id="diet_type" name="diet_type" class="form-control" placeholder="e.g. Filipino, Vegetarian, Keto" value="<?php echo htmlspecialchars($diet_type); ?>">
                </div>

                <div class="form-group full-width">
                    <label for="allergies">Food Allergies or Restrictions <span style="color: var(--muted); font-weight: 400;">(Optional)</span></label>
                    <textarea id="allergies" name="allergies" class="form-control" placeholder="List any food allergies (e.g. Peanuts, Shellfish, Lactose)..."><?php echo htmlspecialchars($allergies); ?></textarea>
                </div>

                <div class="form-group full-width">
                    <button type="submit" class="btn-primary">Create Account <span>→</span></button>
                </div>

            </div>
        </form>

        <div class="signup-link">
            Already have an account? <a href="login.php">Sign In</a>
        </div>
    </div>
</main>

<script>
// --- Client-Side Form Validation Override ---
document.getElementById('registerForm').addEventListener('submit', function(event) {
    if (!this.checkValidity()) {
        event.preventDefault(); // Stop normal submission

        let errors = [];
        const elements = this.elements;
        
        for (let i = 0; i < elements.length; i++) {
            if (elements[i].willValidate && !elements[i].validity.valid) {
                let label = document.querySelector(`label[for="${elements[i].id}"]`);
                let fieldName = label ? label.innerText.replace(/ \*.*/, '').replace(/ \(.*\)/, '') : elements[i].name;

                if (elements[i].validity.valueMissing) {
                    errors.push(`${fieldName} is required.`);
                } else if (elements[i].validity.typeMismatch || elements[i].validity.patternMismatch) {
                    errors.push(`Please enter a valid ${fieldName}.`);
                }
            }
        }
        
        // Populate and show the modal
        const modal = document.getElementById('errorModal');
        const errorList = document.getElementById('errorList');
        
        errorList.innerHTML = '';
        
        errors.forEach(err => {
            const li = document.createElement('li');
            li.textContent = err;
            errorList.appendChild(li);
        });

        modal.style.display = 'flex';
        setTimeout(() => { modal.classList.add('active'); }, 10);
    }
});

// --- Close Modal Function ---
function closeErrorModal() {
    const modal = document.getElementById('errorModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => { modal.style.display = 'none'; }, 300);
    }
}

// --- Password Functions ---
function toggleGlobalPassword() {
    const pwd = document.getElementById('password');
    const cpwd = document.getElementById('confirm_password');
    const icon = document.getElementById('global-eye');
    const textSpan = document.getElementById('global-eye-text');
    
    if (pwd.type === 'password') {
        pwd.type = 'text';
        cpwd.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
        textSpan.innerText = 'Hide Password';
    } else {
        pwd.type = 'password';
        cpwd.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
        textSpan.innerText = 'Show Password';
    }
}

function evaluatePasswordStrength() {
    const val = document.getElementById('password').value;
    const meterFill = document.getElementById('meter-fill');
    const meterText = document.getElementById('meter-text');
    
    let strength = 0;
    
    if (val.length > 0) strength += 1;
    if (val.length >= 8) strength += 1;
    if (val.length >= 12) strength += 1;
    if (/[A-Z]/.test(val)) strength += 1;
    if (/[0-9]/.test(val)) strength += 1;
    if (/[^A-Za-z0-9]/.test(val)) strength += 1;

    let width = 0;
    let color = 'transparent';
    let text = '';

    if (val.length === 0) {
        width = 0;
        text = '';
    } else if (strength <= 2) {
        width = 25;
        color = '#ef4444'; 
        text = 'Weak';
    } else if (strength <= 4) {
        width = 50;
        color = '#f59e0b'; 
        text = 'Medium';
    } else if (strength === 5) {
        width = 75;
        color = '#84cc16'; 
        text = 'Good';
    } else {
        width = 100;
        color = 'linear-gradient(to right, #ef4444, #f59e0b, #eab308, #22c55e, #14532d)'; 
        text = 'Strong';
    }

    meterFill.style.background = color;
    meterFill.style.width = width + '%';
    meterText.innerText = text;
}
</script>

</body>
</html>