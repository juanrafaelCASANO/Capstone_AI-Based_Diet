<?php
// Register_user.php
session_start();
require_once '../config.php'; // Database connection[cite: 3]

$errors = [];
$success = "";

$firstname = "";
$middlename = "";
$lastname = "";
$address = "";
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
$latitude = "14.3498";
$longitude = "121.0794";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname']);
    $middlename = trim($_POST['middlename']);
    $lastname = trim($_POST['lastname']);
    $address = trim($_POST['address']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = trim($_POST['phone']);
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : 14.3498;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : 121.0794;

    $age = intval($_POST['age']);
    $gender = $_POST['gender'];
    $height = floatval($_POST['height']);
    $weight = floatval($_POST['weight']);
    $goal = $_POST['goal'];
    $activity = $_POST['activity'];
    $diet_type = trim($_POST['diet_type']);
    $allergies = trim($_POST['allergies']);

    if (empty($firstname)) $errors[] = "First name is required.";
    if (empty($lastname)) $errors[] = "Last name is required.";
    if (empty($address)) $errors[] = "Address is required.";
    
    $email_domain = substr(strrchr($email, "@"), 1);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email format is required.";
    } elseif (!checkdnsrr($email_domain, 'MX')) {
        $errors[] = "The email domain does not appear to be valid or able to receive emails.";
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter (A-Z).";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter (a-z).";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number (0-9).";
    } elseif (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character (e.g. @, #, $, !).";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (!preg_match('/^[0-9]{11}$/', $phone)) {
        $errors[] = "Phone number must be exactly 11 digits.";
    }

    if ($age <= 0) $errors[] = "Valid age is required.";
    if ($height <= 0) $errors[] = "Valid height is required.";
    if ($weight <= 0) $errors[] = "Valid weight is required.";

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $errors[] = "Email already registered. Please sign in.";
        }
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'user';
        $fullname = trim($firstname . ' ' . (!empty($middlename) ? $middlename . ' ' : '') . $lastname);

        $stmt = $conn->prepare("
            INSERT INTO users
            (fullname, email, phone, password, role, age, gender, height, weight, goal, activity, diet_type, allergies, address, latitude, longitude)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        try {
            $stmt->execute([
                $fullname, $email, $phone, $hashed_password, $role, $age, $gender, 
                $height, $weight, $goal, $activity, $diet_type, $allergies, $address, $latitude, $longitude
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
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />

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

        .header {
            padding: 24px clamp(20px, 5vw, 60px);
            display: flex; justify-content: space-between; align-items: center; width: 100%;
        }

        .logo {
            display: inline-flex; align-items: center; gap: 11px;
            font-family: 'Manrope', sans-serif; font-size: 19px; font-weight: 800;
        }
        
        .logo-mark {
            width: 40px; height: 40px; display: grid; place-items: center;
            border-radius: 13px; background: linear-gradient(145deg,#20bd7a,#078452); color: #fff; font-size: 21px;
        }

        .back-link { font-size: 14px; font-weight: 700; color: var(--muted); display: flex; align-items: center; gap: 6px; }
        
        .wrapper {
            flex: 1; display: flex; justify-content: center; align-items: flex-start; padding: 10px clamp(15px, 4vw, 40px) 60px;
        }

        .card {
            background: rgba(255,255,255,.85); width: 100%; max-width: 780px;
            padding: clamp(30px, 5vw, 45px); border-radius: var(--radius); box-shadow: var(--shadow); backdrop-filter: blur(15px);
        }

        .card-header { text-align: center; margin-bottom: 30px; }
        .card-header h2 { font-family: 'Manrope', sans-serif; font-size: clamp(26px, 4vw, 32px); color: var(--dark); margin-bottom: 8px; }
        .card-header p { color: var(--muted); font-size: 15px; }

        .alert-card-success { 
            display: flex; align-items: flex-start; gap: 12px; padding: 14px 18px; border-radius: 14px; margin-bottom: 24px;
            font-size: 14px; background: var(--success-bg); border: 1px solid var(--success-border); color: var(--success-text); 
        }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px 24px; }
        .form-group { display: flex; flex-direction: column; position: relative; }
        .form-group.full-width { grid-column: 1 / -1; }

        .section-divider {
            grid-column: 1 / -1; font-family: 'Manrope', sans-serif; font-size: 18px; font-weight: 700; color: var(--dark);
            margin-top: 15px; margin-bottom: 5px; padding-bottom: 12px; border-bottom: 1px solid var(--line);
            display: flex; align-items: center; gap: 10px;
        }
        .section-divider::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--primary); }

        label { font-weight: 600; font-size: 14px; color: var(--dark-2); margin-bottom: 8px; display: flex; justify-content: space-between; }

        .form-control {
            width: 100%; padding: 14px 16px; border-radius: 12px; border: 1px solid var(--line);
            background: var(--surface-2); font-family: inherit; font-size: 14.5px; color: var(--text); transition: all 0.25s ease;
        }
        .form-control:focus { outline: none; border-color: var(--primary); background: #fff; box-shadow: 0 0 0 4px var(--primary-soft); }

        select.form-control {
            cursor: pointer; appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2365736d'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 16px center; background-size: 16px; padding-right: 40px;
        }
        textarea.form-control { resize: vertical; min-height: 100px; }

        .address-row { display: flex; gap: 10px; }
        .btn-pick-map {
            background: var(--primary-soft); color: var(--primary-dark); border: 1px solid #c9ebd8;
            padding: 0 16px; border-radius: 12px; font-weight: 700; font-size: 13.5px;
            cursor: pointer; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; transition: .2s;
        }
        .btn-pick-map:hover { background: #d4f2e1; }

        /* ADDRESS AUTOCOMPLETE SUGGESTIONS DROPDOWN */
        #address-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #ffffff;
            border: 2px solid var(--primary);
            border-top: none;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
            max-height: 220px;
            overflow-y: auto;
            z-index: 99999;
            display: none;
            margin-top: 2px;
        }
        .suggestion-item {
            padding: 12px 16px;
            font-size: 14px;
            color: var(--text);
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s;
        }
        .suggestion-item:last-child { border-bottom: none; }
        .suggestion-item:hover { background: var(--primary-soft); color: var(--primary-dark); font-weight: 600; }

        /* MAP MODAL */
        .map-modal {
            position: fixed; inset: 0; background: rgba(15, 23, 42, .6);
            display: none; align-items: center; justify-content: center; padding: 20px; z-index: 9999;
            backdrop-filter: blur(5px);
        }
        .map-modal.active { display: flex; }
        .map-modal-box {
            width: 100%; max-width: 680px; background: white; border-radius: 24px; padding: 25px;
            box-shadow: 0 25px 70px rgba(15,23,42,.25);
        }
        .map-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .map-modal-header h3 { margin: 0; font-size: 18px; color: var(--dark); font-weight: 700; }
        .close-map-modal {
            width: 35px; height: 35px; border: none; border-radius: 8px; background: #f1f5f9;
            cursor: pointer; color: #475569; font-size: 16px; display: grid; place-items: center;
        }
        .close-map-modal:hover { background: #e2e8f0; }

        #modal-map-container {
            width: 100%;
            height: 340px;
            border-radius: 14px;
            border: 1px solid var(--line);
        }

        /* PINED ADDRESS CARD & CHOOSE BUTTON INSIDE MODAL */
        .map-footer-card {
            margin-top: 15px;
            background: var(--surface-2);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px 18px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .selected-address-info {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13.5px;
            color: var(--dark);
        }
        .selected-address-info i {
            color: var(--primary);
            font-size: 16px;
            margin-top: 2px;
        }
        .btn-choose-address {
            background: linear-gradient(135deg,#19ad70,#078151);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 8px 20px rgba(8,129,81,.2);
            transition: transform 0.2s;
        }
        .btn-choose-address:hover {
            transform: translateY(-2px);
        }

        .password-box { position: relative; width: 100%; }
        .password-box .form-control { padding-right: 48px; }
        .toggle-password-icon {
            position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
            cursor: pointer; color: #9ba7a2; font-size: 16px; background: none; border: none;
        }

        .password-segments-container { margin-top: 8px; display: flex; flex-direction: column; gap: 4px; }
        .segments-track { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; width: 100%; }
        .segment { height: 6px; background-color: #e2e8f0; border-radius: 3px; }
        .strength-text-label { font-size: 12px; font-weight: 700; text-align: right; min-height: 18px; }

        .btn-primary {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; min-height: 52px; background: linear-gradient(135deg,#19ad70,#078151);
            color: #fff; border: none; border-radius: 14px; font-weight: 700; font-size: 15px;
            cursor: pointer; margin-top: 15px; box-shadow: 0 12px 26px rgba(8,129,81,.20);
        }
        .btn-primary:hover { transform: translateY(-3px); }

        .signup-link { text-align: center; margin-top: 24px; font-size: 14.5px; color: var(--muted); }
        .signup-link a { color: var(--primary-dark); font-weight: 700; }

        .modal-overlay {
            position: fixed; inset: 0; background: rgba(7, 26, 18, 0.4);
            backdrop-filter: blur(8px); z-index: 9999; display: flex;
            justify-content: center; align-items: center; padding: 20px;
            opacity: 0; visibility: hidden; transition: all 0.3s ease;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card {
            background: #ffffff; width: 100%; max-width: 440px; border-radius: 28px;
            padding: 40px 30px; box-shadow: 0 30px 80px rgba(7, 26, 18, 0.15); text-align: center;
        }
        .modal-icon {
            width: 72px; height: 72px; background: #fff1f2; color: #e11d48;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 32px; margin: 0 auto 20px;
        }
        .modal-title { font-family: 'Manrope', sans-serif; font-size: 24px; color: var(--dark); margin-bottom: 12px; }
        .modal-desc { color: var(--muted); font-size: 14.5px; margin-bottom: 24px; }
        .modal-errors { background: var(--surface-2); border-radius: 16px; padding: 16px 20px; text-align: left; margin-bottom: 30px; max-height: 200px; overflow-y: auto; }
        .modal-errors ul { list-style: none; padding: 0; margin: 0; }
        .modal-errors li { position: relative; padding-left: 24px; margin-bottom: 10px; font-size: 14px; color: var(--text); }
        .btn-modal { background: var(--dark); color: #fff; border: none; width: 100%; padding: 16px; border-radius: 14px; font-weight: 700; cursor: pointer; }

        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; gap: 18px; } }
    </style>
</head>
<body>

<div class="modal-overlay <?php echo !empty($errors) ? 'active' : ''; ?>" id="errorModal" style="<?php echo !empty($errors) ? 'display:flex;' : 'display:none;'; ?>">
    <div class="modal-card">
        <div class="modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
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
    <a href="../index.php" class="logo">
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

        <form method="POST" id="registerForm">
            <div class="form-grid">
                
                <div class="section-divider">Account Credentials</div>

                <div class="form-group">
                    <label for="firstname">First Name <span style="color: #d32f2f;">*</span></label>
                    <input type="text" id="firstname" name="firstname" class="form-control" placeholder="Enter first name" required value="<?php echo htmlspecialchars($firstname); ?>">
                </div>

                <div class="form-group">
                    <label for="middlename">Middle Name <span style="color: var(--muted); font-weight: 400;">(Optional)</span></label>
                    <input type="text" id="middlename" name="middlename" class="form-control" placeholder="Enter middle name" value="<?php echo htmlspecialchars($middlename); ?>">
                </div>

                <div class="form-group">
                    <label for="lastname">Last Name <span style="color: #d32f2f;">*</span></label>
                    <input type="text" id="lastname" name="lastname" class="form-control" placeholder="Enter last name" required value="<?php echo htmlspecialchars($lastname); ?>">
                </div>

                <!-- ADDRESS FIELD WITH AUTOCOMPLETE & MAP PIN -->
                <div class="form-group">
                    <label for="address">Address <span style="color: #d32f2f;">*</span></label>
                    <div class="address-row">
                        <input type="text" id="address" name="address" class="form-control" placeholder="e.g. Biñan, Laguna" autocomplete="off" required value="<?php echo htmlspecialchars($address); ?>">
                        <button type="button" class="btn-pick-map" onclick="openMapModal()">
                            <i class="fa-solid fa-map-location-dot"></i> Pin Map
                        </button>
                    </div>
                    <!-- Dropdown suggestions container -->
                    <div id="address-suggestions"></div>

                    <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($latitude); ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($longitude); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span style="color: #d32f2f;">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="name@example.com" required value="<?php echo htmlspecialchars($email); ?>">
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number <span style="color: #d32f2f;">*</span></label>
                    <input type="text" id="phone" name="phone" class="form-control" placeholder="11 digits (e.g. 09123456789)" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);" required value="<?php echo htmlspecialchars($phone); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password <span style="color: #d32f2f;">*</span></label>
                    <div class="password-box">
                        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••••••" oninput="checkPasswordStrength(this.value)" required>
                        <button type="button" class="toggle-password-icon" onclick="togglePasswordVisibility('password', this)" title="Show/Hide Password">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                    
                    <div class="password-segments-container">
                        <div class="segments-track">
                            <div class="segment" id="seg-1"></div>
                            <div class="segment" id="seg-2"></div>
                            <div class="segment" id="seg-3"></div>
                            <div class="segment" id="seg-4"></div>
                        </div>
                        <div class="strength-text-label" id="strength-text" style="color: var(--muted);"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span style="color: #d32f2f;">*</span></label>
                    <div class="password-box">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
                        <button type="button" class="toggle-password-icon" onclick="togglePasswordVisibility('confirm_password', this)" title="Show/Hide Password">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group full-width" style="font-size: 13px; color: var(--muted); margin-top: -10px;">
                    Password must be at least 8 characters long and include an uppercase letter (A-Z), lowercase letter (a-z), a number (0-9), and a special symbol (e.g. @, #, $, !).
                </div>

                <div class="section-divider">Physical & Health Profile</div>

                <div class="form-group">
                    <label for="age">Age <span style="color: #d32f2f;">*</span></label>
                    <input type="number" id="age" name="age" class="form-control" placeholder="e.g. 25" required value="<?php echo htmlspecialchars($age); ?>">
                </div>

                <div class="form-group">
                    <label for="gender">Gender <span style="color: #d32f2f;">*</span></label>
                    <select id="gender" name="gender" class="form-control" required>
                        <option value="">Select Gender</option>
                        <option value="male" <?php if($gender == 'male') echo 'selected'; ?>>Male</option>
                        <option value="female" <?php if($gender == 'female') echo 'selected'; ?>>Female</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="height">Height (cm) <span style="color: #d32f2f;">*</span></label>
                    <input type="number" step="0.1" id="height" name="height" class="form-control" placeholder="e.g. 170" required value="<?php echo htmlspecialchars($height); ?>">
                </div>

                <div class="form-group">
                    <label for="weight">Weight (kg) <span style="color: #d32f2f;">*</span></label>
                    <input type="number" step="0.1" id="weight" name="weight" class="form-control" placeholder="e.g. 65" required value="<?php echo htmlspecialchars($weight); ?>">
                </div>

                <div class="form-group">
                    <label for="goal">Primary Fitness Goal <span style="color: #d32f2f;">*</span></label>
                    <select id="goal" name="goal" class="form-control" required>
                        <option value="">Select Goal</option>
                        <option value="lose" <?php if($goal == 'lose') echo 'selected'; ?>>Lose Weight</option>
                        <option value="maintain" <?php if($goal == 'maintain') echo 'selected'; ?>>Maintain Weight</option>
                        <option value="gain" <?php if($goal == 'gain') echo 'selected'; ?>>Gain Muscle</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="activity">Daily Activity Level <span style="color: #d32f2f;">*</span></label>
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
                    <textarea id="allergies" name="allergies" class="form-control" placeholder="List any food allergies..."><?php echo htmlspecialchars($allergies); ?></textarea>
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

<!-- MAP MODAL WITH GPS AUTO-DETECTION AND CLICK-TO-PIN -->
<div class="map-modal" id="mapModal">
    <div class="map-modal-box">
        <div class="map-modal-header">
            <h3>Set Your Location</h3>
            <button type="button" class="close-map-modal" onclick="closeMapModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div>
            <div id="modal-map-container"></div>
            
            <!-- Address preview card and Choose Address button -->
            <div class="map-footer-card">
                <div class="selected-address-info">
                    <i class="fa-solid fa-location-dot"></i>
                    <div>
                        <strong style="font-size: 12px; color: var(--muted); display: block; margin-bottom: 2px;">Selected Address:</strong>
                        <span id="modal-address-text">Click anywhere on the map to place a pin.</span>
                    </div>
                </div>
                <button type="button" id="btnChooseAddress" class="btn-choose-address" onclick="confirmSelectedAddress()" style="opacity: 0.6; pointer-events: none;">
                    <i class="fa-solid fa-check"></i> Choose Address
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
<script>
let mapInstance = null;
let markerInstance = null;
let debounceTimer = null;
let tempLat = null;
let tempLng = null;
let tempAddressText = "";

const addressInput = document.getElementById('address');
const suggestionsBox = document.getElementById('address-suggestions');

addressInput.addEventListener('input', function() {
    const query = this.value.trim();
    clearTimeout(debounceTimer);

    if (query.length < 3) {
        suggestionsBox.style.display = 'none';
        suggestionsBox.innerHTML = '';
        return;
    }

    debounceTimer = setTimeout(() => {
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=ph&limit=5`, {
            headers: {
                'Accept-Language': 'en'
            }
        })
        .then(response => response.json())
        .then(data => {
            suggestionsBox.innerHTML = '';
            if (data && data.length > 0) {
                suggestionsBox.style.display = 'block';
                data.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'suggestion-item';
                    div.textContent = item.display_name;
                    div.addEventListener('click', function() {
                        addressInput.value = item.display_name;
                        document.getElementById('longitude').value = item.lon;
                        document.getElementById('latitude').value = item.lat;
                        suggestionsBox.style.display = 'none';
                    });
                    suggestionsBox.appendChild(div);
                });
            } else {
                suggestionsBox.style.display = 'none';
            }
        })
        .catch(err => {
            console.error('Autocomplete error:', err);
            suggestionsBox.style.display = 'none';
        });
    }, 300);
});

document.addEventListener('click', function(e) {
    if (!addressInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
        suggestionsBox.style.display = 'none';
    }
});

function openMapModal() {
    const modal = document.getElementById('mapModal');
    modal.classList.add('active');

    setTimeout(() => {
        if (!mapInstance) {
            // Default center muna sa Pilipinas (Biñan/Manila area)
            mapInstance = L.map('modal-map-container').setView([14.3498, 121.0794], 14);

            // MapTiler Streets tile layer (Google Maps style)
            L.tileLayer('https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=ZkO2ll8wJho8o1zc1Sug', {
                maxZoom: 19,
                attribution: '&copy; MapTiler &copy; OpenStreetMap contributors'
            }).addTo(mapInstance);

            mapInstance.on('click', function(e) {
                setPinPosition(e.latlng.lat, e.latlng.lng);
            });

            // Subukang kunin ang GPS location ng device kung pinayagan sa settings
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const userLat = position.coords.latitude;
                        const userLng = position.coords.longitude;
                        mapInstance.setView([userLat, userLng], 15);
                        // Optional: Awtomatikong i-set ang pin sa kasalukuyang GPS location
                        setPinPosition(userLat, userLng);
                    },
                    (error) => {
                        console.log("Geolocation permission denied or unavailable.", error);
                    },
                    { timeout: 10000 }
                );
            }
        }

        mapInstance.invalidateSize();
    }, 250);
}

function closeMapModal() {
    document.getElementById('mapModal').classList.remove('active');
}

async function setPinPosition(lat, lng) {
    tempLat = lat;
    tempLng = lng;

    const customIcon = L.divIcon({
        className: 'custom-map-pin',
        html: '<div style="background-color: #16a36a; width: 36px; height: 36px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); border: 2px solid white;"><i class="fa-solid fa-location-dot" style="transform: rotate(45deg); color: white; font-size: 16px;"></i></div>',
        iconSize: [36, 36],
        iconAnchor: [18, 36],
        popupAnchor: [0, -36]
    });

    if (markerInstance) {
        markerInstance.setLatLng([lat, lng]);
    } else {
        markerInstance = L.marker([lat, lng], { draggable: true, icon: customIcon }).addTo(mapInstance);
        markerInstance.on('dragend', function() {
            const pos = markerInstance.getLatLng();
            setPinPosition(pos.lat, pos.lng);
        });
    }

    mapInstance.panTo([lat, lng]);
    document.getElementById('modal-address-text').textContent = "Fetching address...";

    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
        const data = await response.json();
        if (data && data.display_name) {
            tempAddressText = data.display_name;
            document.getElementById('modal-address-text').textContent = tempAddressText;
        } else {
            tempAddressText = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
            document.getElementById('modal-address-text').textContent = tempAddressText;
        }
    } catch (e) {
        console.error("Geocoding error", e);
        tempAddressText = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
        document.getElementById('modal-address-text').textContent = tempAddressText;
    }

    // Enable ang "Choose Address" button kapag may pin na nakalagay
    const chooseBtn = document.getElementById('btnChooseAddress');
    chooseBtn.style.opacity = '1';
    chooseBtn.style.pointerEvents = 'auto';
}

function confirmSelectedAddress() {
    if (tempLat !== null && tempLng !== null) {
        document.getElementById('latitude').value = tempLat;
        document.getElementById('longitude').value = tempLng;
        if (tempAddressText) {
            document.getElementById('address').value = tempAddressText;
        }
        closeMapModal();
    }
}

function closeErrorModal() {
    const modal = document.getElementById('errorModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => { modal.style.display = 'none'; }, 300);
    }
}

function togglePasswordVisibility(fieldId, btnElement) {
    const passwordInput = document.getElementById(fieldId);
    const icon = btnElement.querySelector('i');
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        passwordInput.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

function checkPasswordStrength(password) {
    let strength = 0;
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password) && /[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;

    const seg1 = document.getElementById('seg-1');
    const seg2 = document.getElementById('seg-2');
    const seg3 = document.getElementById('seg-3');
    const seg4 = document.getElementById('seg-4');
    const textEl = document.getElementById('strength-text');

    [seg1, seg2, seg3, seg4].forEach(seg => {
        seg.style.backgroundColor = '#e2e8f0';
    });

    if (password.length === 0) {
        textEl.textContent = '';
        return;
    }

    let activeCount = strength;
    if (activeCount > 4) activeCount = 4;

    let color = '#ef4444';
    let text = 'Weak';
    if (strength === 2) { color = '#f97316'; text = 'Fair'; }
    else if (strength === 3) { color = '#eab308'; text = 'Good'; }
    else if (strength >= 4) { color = '#10b981'; text = 'Strong'; }

    const segments = [seg1, seg2, seg3, seg4];
    for (let i = 0; i < activeCount; i++) {
        segments[i].style.backgroundColor = color;
    }

    textEl.textContent = text;
    textEl.style.color = color;
}
</script>

</body>
</html>